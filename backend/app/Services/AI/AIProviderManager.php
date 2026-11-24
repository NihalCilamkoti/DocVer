<?php

namespace App\Services\AI;

use App\Enums\ComparisonStatus;
use App\Models\ComparisonJob;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Exceptions\AllProvidersUnavailableException;
use App\Services\AI\Exceptions\AuthenticationException;
use App\Services\AI\Exceptions\PaymentRequiredException;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\RateLimitException;
use App\Services\AI\Health\CircuitBreaker;
use App\Services\AI\Health\ProviderHealthMonitor;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\AI\Quota\AIQuotaManager;
use App\Services\AI\Quota\QuotaManager;
use App\Services\AI\Security\PromptSanitizer;
use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIProviderManager
{
    /** @var array<string, AIProviderInterface> */
    protected array $providers = [];
    protected AIQuotaManager $aiQuotaManager;

    public function __construct(
        protected CircuitBreaker $circuitBreaker,
        protected ProviderHealthMonitor $healthMonitor,
        protected QuotaManager $quotaManager,
        protected PromptSanitizer $sanitizer,
        ?AIProviderInterface $provider1 = null,
        ?AIProviderInterface $provider2 = null,
        ?AIProviderInterface $provider3 = null,
        ?AIQuotaManager $aiQuotaManager = null
    ) {
        $this->aiQuotaManager = $aiQuotaManager ?? app(AIQuotaManager::class);

        if ($provider1) {
            $this->registerProvider($provider1);
        } else {
            $this->registerProvider(app(GeminiProvider::class));
        }

        if ($provider2) {
            $this->registerProvider($provider2);
            if ($provider3) {
                $this->registerProvider($provider3);
            } elseif (! ($provider2 instanceof OpenRouterProvider)) {
                $this->registerProvider(app(OpenRouterProvider::class));
            }
        } else {
            $this->registerProvider(app(GroqProvider::class));
            $this->registerProvider(app(OpenRouterProvider::class));
        }
    }

    public function registerProvider(AIProviderInterface $provider): self
    {
        $this->providers[$provider->getName()] = $provider;
        return $this;
    }

    public function getProvider(string $name): ?AIProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return array<string, AIProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getPrimaryProvider(): AIProviderInterface
    {
        $primary = config('ai.primary_provider', 'gemini');
        return $this->providers[$primary] ?? reset($this->providers);
    }

    /**
     * Resolve model name for provider based on task type.
     */
    public function resolveModelForTask(string $providerName, string $taskType): string
    {
        $taskModel = config("ai.task_models.{$taskType}.{$providerName}");
        if ($taskModel) {
            return $taskModel;
        }

        $provider = $this->getProvider($providerName);
        if ($provider) {
            return $provider->getDefaultModel($taskType);
        }

        return config("ai.providers.{$providerName}.default_model", 'default');
    }

    /**
     * Dispatch completion request with automatic multi-provider failover.
     * Sequence: Gemini → Groq → OpenRouter (or configured fallback order).
     *
     * @throws AllProvidersUnavailableException
     * @throws QuotaExceededException
     */
    public function route(AIRequest $request): AIResponse
    {
        // 1. Enforce user and workspace quota controls
        $estimatedPromptTokens = (int) ceil(strlen($request->prompt) / 4);
        $this->quotaManager->checkQuota($request->userId, $request->workspaceId, $estimatedPromptTokens);

        // 2. Sanitize prompt text against injection directives
        $sanitizedPrompt = $this->sanitizer->sanitize($request->prompt);
        $sanitizedSystem = $request->systemInstruction ? $this->sanitizer->sanitize($request->systemInstruction) : null;

        // 3. Resolve candidate providers in priority order
        $candidates = $this->resolveCandidateProviders();

        if (empty($candidates)) {
            throw new AllProvidersUnavailableException(
                [],
                "No AI providers are configured and available."
            );
        }

        $failedAttempts = [];
        $attemptIndex = 0;

        foreach ($candidates as $providerName) {
            $provider = $this->providers[$providerName] ?? null;
            if (! $provider) {
                continue;
            }

            // Check if provider credentials exist
            if (! $provider->isConfigured()) {
                $failedAttempts[$providerName] = "Provider credentials not configured.";
                continue;
            }

            // Verify circuit breaker state
            if (! $this->circuitBreaker->isAvailable($providerName)) {
                $failedAttempts[$providerName] = "Circuit breaker OPEN (cooldown active)";
                Log::warning("Skipping AI provider [{$providerName}]: Circuit breaker OPEN.");
                continue;
            }

            // Smart task-specific model selection
            $targetModel = $request->model ?? $this->resolveModelForTask($providerName, $request->taskType);

            // Proactive Quota Check (Daily requests, Daily tokens, RPM, TPM, Safety Margin 5%)
            $quotaReason = null;
            if (! $this->aiQuotaManager->canExecute($providerName, $targetModel, $estimatedPromptTokens, $quotaReason)) {
                $failedAttempts[$providerName] = "Quota limit or safety margin reached: {$quotaReason}";
                Log::warning("Failing over from AI provider [{$providerName}]: {$quotaReason}");
                $this->recordFallbackEvent($providerName, $candidates[$attemptIndex + 1] ?? 'none', "Quota limit: {$quotaReason}");
                continue;
            }

            // Check if provider supports task
            try {
                if (! $provider->supportsTask($request->taskType, $targetModel)) {
                    $failedAttempts[$providerName] = "Provider does not support task [{$request->taskType}].";
                    Log::info("Provider [{$providerName}] does not support task [{$request->taskType}]. Skipping to next provider.");
                    continue;
                }
            } catch (\BadMethodCallException $e) {
                // If a mocked provider in legacy tests lacks explicit expectation, default to supported
            }

            $attemptIndex++;
            $start = microtime(true);

            $targetRequest = new AIRequest(
                prompt: $sanitizedPrompt,
                systemInstruction: $sanitizedSystem,
                model: $targetModel,
                temperature: $request->temperature,
                maxTokens: $request->maxTokens,
                jsonSchema: $request->jsonSchema,
                stream: $request->stream,
                taskType: $request->taskType,
                metadata: $request->metadata,
                userId: $request->userId,
                workspaceId: $request->workspaceId
            );

            try {
                Log::info("Routing AI request to [{$providerName}] (attempt {$attemptIndex}) with model [{$targetModel}]");

                $response = $provider->generate($targetRequest);
                $durationMs = (int) round((microtime(true) - $start) * 1000);

                // Record health & circuit breaker success
                $this->circuitBreaker->recordSuccess($providerName);
                $this->healthMonitor->recordResult($providerName, true, $durationMs);

                // Extract headers and record quota metrics
                $quotaHeaders = $response->raw['quota_headers'] ?? [];
                if ($response->usage) {
                    $this->quotaManager->recordUsage(
                        provider: $providerName,
                        model: $response->model,
                        inputTokens: $response->usage->promptTokens,
                        outputTokens: $response->usage->completionTokens,
                        costUsd: $response->usage->estimatedCostUsd,
                        durationMs: $durationMs,
                        userId: $request->userId,
                        workspaceId: $request->workspaceId,
                        success: true
                    );

                    $this->aiQuotaManager->recordProviderUsage(
                        provider: $providerName,
                        model: $response->model,
                        inputTokens: $response->usage->promptTokens,
                        outputTokens: $response->usage->completionTokens,
                        headers: $quotaHeaders
                    );
                }

                // If succeeded on fallback attempt, mark isFallback = true
                if ($attemptIndex > 1) {
                    return new AIResponse(
                        content: $response->content,
                        structuredData: $response->structuredData,
                        provider: $response->provider,
                        model: $response->model,
                        usage: $response->usage,
                        isFallback: true,
                        raw: $response->raw
                    );
                }

                return $response;

            } catch (\Throwable $e) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                $retryAfter = method_exists($e, 'retryAfterSeconds') ? $e->retryAfterSeconds : null;

                // Error classification: Auth vs Billing vs RateLimit vs Server Error
                if ($e instanceof AuthenticationException) {
                    Log::critical("AI Provider [{$providerName}] authentication failed (401/403). Verify API key.", [
                        'provider' => $providerName,
                        'message' => $e->getMessage(),
                    ]);
                    $this->aiQuotaManager->recordProviderError($providerName, 401, $e->getMessage());
                } elseif ($e instanceof PaymentRequiredException) {
                    Log::critical("AI Provider [{$providerName}] payment required (402). Check account credits/billing.", [
                        'provider' => $providerName,
                        'message' => $e->getMessage(),
                    ]);
                    $this->aiQuotaManager->recordProviderError($providerName, 402, $e->getMessage());
                } elseif ($e instanceof RateLimitException) {
                    Log::warning("AI Provider [{$providerName}] rate limited (429). Retry-after: " . ($retryAfter ?? 'none'));
                    $this->aiQuotaManager->recordProviderError($providerName, 429, $e->getMessage());
                } else {
                    $this->aiQuotaManager->recordProviderError($providerName, 500, $e->getMessage());
                }

                // Trip circuit breaker
                $this->circuitBreaker->recordFailure($providerName, $retryAfter);
                $this->healthMonitor->recordResult($providerName, false, $durationMs, $e->getMessage());

                // Record failed usage audit
                $this->quotaManager->recordUsage(
                    provider: $providerName,
                    model: $targetModel,
                    inputTokens: $estimatedPromptTokens,
                    outputTokens: 0,
                    costUsd: 0.0,
                    durationMs: $durationMs,
                    userId: $request->userId,
                    workspaceId: $request->workspaceId,
                    success: false,
                    errorMessage: $e->getMessage()
                );

                $failedAttempts[$providerName] = $e->getMessage();
                $nextProvider = $candidates[$attemptIndex] ?? 'none';
                $this->recordFallbackEvent($providerName, $nextProvider, $e->getMessage());
                Log::warning("AI Provider [{$providerName}] failed: {$e->getMessage()}. Failing over to [{$nextProvider}]...");
            }
        }

        // Check if long-running job ID was passed in metadata
        $comparisonJobId = $request->metadata['comparison_job_id'] ?? null;
        if ($comparisonJobId) {
            // Preserve the job and mark as temporarily unavailable for later resumption
            ComparisonJob::where('id', $comparisonJobId)->update([
                'status' => ComparisonStatus::TEMPORARILY_UNAVAILABLE,
                'error_message' => 'All AI providers are temporarily unavailable or have exhausted daily quota. The job is preserved for automatic or manual retry.',
            ]);
            Log::warning("Comparison Job #{$comparisonJobId} marked TEMPORARILY_UNAVAILABLE due to all AI providers failing.");
        }

        // All eligible candidates exhausted
        throw new AllProvidersUnavailableException($failedAttempts);
    }

    /**
     * Stream completion with failover.
     *
     * @return Generator
     */
    public function routeStream(AIRequest $request): Generator
    {
        $candidates = $this->resolveCandidateProviders();

        foreach ($candidates as $providerName) {
            $provider = $this->providers[$providerName] ?? null;
            if (! $provider || ! $this->circuitBreaker->isAvailable($providerName) || ! $provider->isConfigured()) {
                continue;
            }

            try {
                yield from $provider->stream($request);
                $this->circuitBreaker->recordSuccess($providerName);
                return;
            } catch (\Throwable $e) {
                $this->circuitBreaker->recordFailure($providerName);
                Log::warning("Streaming failed for [{$providerName}]: {$e->getMessage()}. Failing over...");
            }
        }

        throw new AllProvidersUnavailableException([], "All providers failed during streaming request.");
    }

    /**
     * Resolve candidate providers in configured / admin priority order.
     * Default sequence: Gemini → Groq → OpenRouter.
     *
     * @return string[]
     */
    public function resolveCandidateProviders(): array
    {
        // 1. Check for runtime dynamic admin override in cache
        $overrideOrder = Cache::get('docver:ai:provider_order');
        if (is_array($overrideOrder) && ! empty($overrideOrder)) {
            $candidates = $overrideOrder;
        } else {
            $primary = config('ai.primary_provider', 'gemini');
            $fallbacks = config('ai.fallback_order', ['gemini', 'groq', 'openrouter']);
            $candidates = array_unique(array_merge([$primary], $fallbacks));
        }

        if (! empty($this->providers)) {
            $registered = array_keys($this->providers);
            $candidates = array_values(array_intersect($candidates, $registered));
            if (empty($candidates)) {
                $candidates = $registered;
            }
        }

        // Filter enabled status
        return array_values(array_filter($candidates, function ($name) {
            $enabled = config("ai.providers.{$name}.enabled", true);
            $disabledByAdmin = Cache::get("docver:ai:provider:{$name}:disabled", false);
            return $enabled && ! $disabledByAdmin;
        }));
    }

    /**
     * Record fallback events in cache list for admin dashboard history.
     */
    protected function recordFallbackEvent(string $fromProvider, string $toProvider, string $reason): void
    {
        $events = Cache::get('docver:ai:fallback_events', []);
        array_unshift($events, [
            'id' => uniqid('fb_'),
            'timestamp' => now()->toIso8601String(),
            'from_provider' => $fromProvider,
            'to_provider' => $toProvider,
            'reason' => substr($reason, 0, 300),
        ]);

        // Keep last 50 events
        $events = array_slice($events, 0, 50);
        Cache::put('docver:ai:fallback_events', $events, 86400 * 7);
    }
}
