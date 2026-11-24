<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AuthenticationException;
use App\Services\AI\Exceptions\ContextLengthExceededException;
use App\Services\AI\Exceptions\PaymentRequiredException;
use App\Services\AI\Exceptions\ProviderServerException;
use App\Services\AI\Exceptions\RateLimitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

abstract class BaseAIProvider implements AIProviderInterface
{
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $providerConfig = config("ai.providers.{$this->getName()}", []);
        $costInputPerMillion = $providerConfig['cost_input_per_million'] ?? 0.10;
        $costOutputPerMillion = $providerConfig['cost_output_per_million'] ?? 0.30;

        $inputCost = ($inputTokens / 1000000) * $costInputPerMillion;
        $outputCost = ($outputTokens / 1000000) * $costOutputPerMillion;

        return round($inputCost + $outputCost, 6);
    }

    public function getDefaultModel(string $taskType = 'general'): string
    {
        $taskModel = config("ai.task_models.{$taskType}.{$this->getName()}");
        if ($taskModel) {
            return $taskModel;
        }

        return config("ai.providers.{$this->getName()}.default_model", 'default');
    }

    public function getMaxContextLength(): int
    {
        return config("ai.providers.{$this->getName()}.max_context_tokens", 128000);
    }

    public function supportsTask(string $taskType, ?string $model = null): bool
    {
        $taskModel = config("ai.task_models.{$taskType}.{$this->getName()}");
        return $taskModel !== null || in_array($taskType, ['general', 'extraction', 'comparison', 'contradiction', 'chat', 'report']);
    }

    public function extractQuotaHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtolower((string) $k)] = is_array($v) ? ($v[0] ?? null) : $v;
        }

        return [
            'requests_limit' => isset($normalized['x-ratelimit-limit-requests']) ? (int) $normalized['x-ratelimit-limit-requests'] : null,
            'requests_remaining' => isset($normalized['x-ratelimit-remaining-requests']) ? (int) $normalized['x-ratelimit-remaining-requests'] : null,
            'tokens_limit' => isset($normalized['x-ratelimit-limit-tokens']) ? (int) $normalized['x-ratelimit-limit-tokens'] : null,
            'tokens_remaining' => isset($normalized['x-ratelimit-remaining-tokens']) ? (int) $normalized['x-ratelimit-remaining-tokens'] : null,
            'reset_requests' => $normalized['x-ratelimit-reset-requests'] ?? null,
            'reset_tokens' => $normalized['x-ratelimit-reset-tokens'] ?? null,
            'retry_after' => isset($normalized['retry-after']) ? (int) $normalized['retry-after'] : null,
        ];
    }

    /**
     * Map HTTP response failure to typed AI exceptions with Retry-After inspection.
     *
     * @throws AIProviderException
     */
    protected function handleResponseError(Response $response): void
    {
        $status = $response->status();
        $body = $response->body();
        $provider = $this->getName();

        // 1. Check Retry-After header
        $retryAfter = $this->parseRetryAfter($response);

        // 2. Authentication failure
        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($provider, "Authentication failed: {$body}");
        }

        // 3. Payment required (OpenRouter credit exhaustion)
        if ($status === 402) {
            throw new PaymentRequiredException($provider, "Credit or payment required: {$body}");
        }

        // 4. Rate limit (429)
        if ($status === 429) {
            throw new RateLimitException($provider, "Rate limit exceeded (HTTP 429): {$body}", $retryAfter);
        }

        // 5. Context length exceeded (400 with token limit notice)
        if ($status === 400 && (str_contains(strtolower($body), 'context') || str_contains(strtolower($body), 'token limit') || str_contains(strtolower($body), 'maximum context length'))) {
            throw new ContextLengthExceededException($provider, "Prompt exceeded context window: {$body}");
        }

        // 6. Upstream server errors (5xx)
        if ($status >= 500) {
            throw new ProviderServerException($provider, "Upstream server failure (HTTP {$status}): {$body}", $status);
        }

        // Generic provider exception
        throw new AIProviderException("Provider error (HTTP {$status}): {$body}", $provider, $status, $retryAfter);
    }

    protected function parseRetryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');
        if (! $header) {
            return null;
        }

        if (is_numeric($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);
        if ($timestamp !== false) {
            return max(0, $timestamp - time());
        }

        return null;
    }

    /**
     * Execute HTTP request with exponential backoff and jitter.
     */
    protected function executeWithBackoff(callable $callable, int $maxRetries = 2): mixed
    {
        $attempt = 0;
        $initialDelayMs = config('ai.retry.initial_delay_ms', 500);
        $maxDelayMs = config('ai.retry.max_delay_ms', 3000);

        while (true) {
            try {
                return $callable();
            } catch (RateLimitException|ProviderServerException $e) {
                $attempt++;
                if ($attempt > $maxRetries) {
                    throw $e;
                }

                $delayMs = min($maxDelayMs, $initialDelayMs * (2 ** ($attempt - 1)));
                // Add jitter between 0 and 200ms
                $jitter = random_int(0, 200);
                $totalDelayMs = $delayMs + $jitter;

                if ($e instanceof RateLimitException && $e->retryAfterSeconds) {
                    $totalDelayMs = max($totalDelayMs, $e->retryAfterSeconds * 1000);
                }

                Log::warning("AI Provider [{$this->getName()}] transient failure (attempt {$attempt}/{$maxRetries}). Backing off for {$totalDelayMs}ms: {$e->getMessage()}");
                usleep($totalDelayMs * 1000);
            }
        }
    }
}
