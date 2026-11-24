<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\ProviderHealth;
use App\Models\UsageRecord;
use App\Services\AI\Health\CircuitBreaker;
use App\Services\AI\Health\ProviderHealthMonitor;
use App\Services\AI\Quota\AIQuotaManager;
use App\Services\AI\Router\AIProviderRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProviderManagementController extends BaseApiController
{
    public function __construct(
        protected AIProviderRouter $router,
        protected CircuitBreaker $circuitBreaker,
        protected ProviderHealthMonitor $healthMonitor,
        protected AIQuotaManager $quotaManager
    ) {}

    /**
     * List all providers with health, circuit breaker state, priority, and quota statistics.
     * Never exposes credentials or secrets.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $providers = $this->router->getProviders();
        $dbHealth = ProviderHealth::all()->keyBy('provider_name');
        $priorityOrder = $this->router->resolveCandidateProviders();

        $data = [];
        foreach ($providers as $name => $provider) {
            $health = $dbHealth->get($name);
            $disabled = Cache::get("docver:ai:provider:{$name}:disabled", false);
            $cbState = $this->circuitBreaker->getState($name);
            $quota = $this->quotaManager->getProviderQuotaStatus($name);

            $status = $health?->status ?? 'unknown';
            if ($quota['is_exhausted']) {
                $status = 'quota_exhausted';
            } elseif ($quota['is_near_exhaustion']) {
                $status = 'degraded';
            }

            $data[] = [
                'provider' => $name,
                'display_name' => config("ai.providers.{$name}.name", ucfirst($name)),
                'is_configured' => $provider->isConfigured(),
                'is_enabled' => ! $disabled,
                'circuit_breaker_state' => $cbState,
                'status' => $status,
                'latency_ms' => $health?->latency_ms ?? 0,
                'last_checked_at' => $health?->last_checked_at?->toIso8601String(),
                'priority_rank' => array_search($name, $priorityOrder) !== false
                    ? array_search($name, $priorityOrder) + 1
                    : 99,
                'default_model' => $provider->getDefaultModel(),
                'max_context_tokens' => $provider->getMaxContextLength(),
                'supports_json' => $provider->supportsStructuredOutput(),
                'supports_streaming' => $provider->supportsStreaming(),
                // Quota & Rate Limit Details
                'requests_used_today' => $quota['requests_used_today'],
                'daily_request_limit' => $quota['daily_request_limit'],
                'requests_remaining' => $quota['requests_remaining'],
                'tokens_used_today' => $quota['tokens_used_today'],
                'daily_token_limit' => $quota['daily_token_limit'],
                'tokens_remaining' => $quota['tokens_remaining'],
                'current_rpm' => $quota['current_rpm'],
                'rpm_limit' => $quota['rpm_limit'],
                'current_tpm' => $quota['current_tpm'],
                'tpm_limit' => $quota['tpm_limit'],
                'reset_countdown_seconds' => $quota['reset_countdown_seconds'],
                'is_exhausted' => $quota['is_exhausted'],
                'is_near_exhaustion' => $quota['is_near_exhaustion'],
                'safety_margin_percent' => $quota['safety_margin_percent'],
                'cost_input_per_million' => config("ai.providers.{$name}.cost_input_per_million", 0.10),
                'cost_output_per_million' => config("ai.providers.{$name}.cost_output_per_million", 0.30),
            ];
        }

        // Sort by priority rank
        usort($data, fn($a, $b) => $a['priority_rank'] <=> $b['priority_rank']);

        return $this->successResponse($data, 'AI Provider configuration and health status');
    }

    /**
     * Update provider settings: enable/disable, priority, or reset circuit breaker.
     */
    public function update(Request $request, string $provider): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $instance = $this->router->getProvider($provider);
        if (! $instance) {
            return $this->errorResponse("Provider [{$provider}] not registered.", [], 404);
        }

        $validated = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'reset_circuit_breaker' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        if (isset($validated['is_enabled'])) {
            Cache::put("docver:ai:provider:{$provider}:disabled", ! $validated['is_enabled'], 86400 * 30);
        }

        if (! empty($validated['reset_circuit_breaker'])) {
            $this->circuitBreaker->reset($provider);
        }

        if (isset($validated['priority'])) {
            $order = $this->router->resolveCandidateProviders();
            // Move $provider to position
            $order = array_values(array_diff($order, [$provider]));
            array_splice($order, $validated['priority'] - 1, 0, [$provider]);
            Cache::put('docver:ai:provider_order', $order, 86400 * 30);
        }

        return $this->successResponse([
            'provider' => $provider,
            'is_enabled' => ! Cache::get("docver:ai:provider:{$provider}:disabled", false),
            'circuit_breaker' => $this->circuitBreaker->getState($provider),
            'priority_order' => $this->router->resolveCandidateProviders(),
        ], "Provider [{$provider}] updated successfully.");
    }

    /**
     * Test ping connectivity for a provider.
     */
    public function ping(Request $request, string $provider): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $instance = $this->router->getProvider($provider);
        if (! $instance) {
            return $this->errorResponse("Provider [{$provider}] not registered.", [], 404);
        }

        $result = $this->healthMonitor->checkProvider($instance);

        return $this->successResponse($result, "Connectivity check for [{$provider}]");
    }

    /**
     * Retrieve recent automatic failover / fallback event history.
     */
    public function fallbackEvents(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $events = Cache::get('docver:ai:fallback_events', []);

        return $this->successResponse($events, 'AI provider failover event log');
    }

    /**
     * Manually switch the primary provider.
     */
    public function switchPrimary(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $validated = $request->validate([
            'primary_provider' => ['required', 'string', 'in:gemini,groq,openrouter'],
        ]);

        $newPrimary = $validated['primary_provider'];
        $currentOrder = $this->router->resolveCandidateProviders();

        // Move newPrimary to index 0
        $newOrder = array_values(array_unique(array_merge([$newPrimary], $currentOrder)));
        Cache::put('docver:ai:provider_order', $newOrder, 86400 * 30);

        return $this->successResponse([
            'primary_provider' => $newPrimary,
            'priority_order' => $newOrder,
        ], "Primary provider manually switched to [{$newPrimary}].");
    }

    /**
     * Get comprehensive AI usage overview for administrators.
     */
    public function usageOverview(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $records = UsageRecord::where('metric_type', 'ai_tokens')
            ->where('recorded_at', '>=', now()->subDays(30))
            ->get();

        $byProvider = [
            'gemini' => ['total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'estimated_cost_usd' => 0.0, 'requests' => 0, 'successes' => 0, 'failures' => 0],
            'groq' => ['total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'estimated_cost_usd' => 0.0, 'requests' => 0, 'successes' => 0, 'failures' => 0],
            'openrouter' => ['total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'estimated_cost_usd' => 0.0, 'requests' => 0, 'successes' => 0, 'failures' => 0],
        ];

        $totalRequests = 0;
        $totalTokens = 0;
        $totalCost = 0.0;

        foreach ($records as $rec) {
            $meta = $rec->metadata ?? [];
            $p = $meta['provider'] ?? 'gemini';
            $qty = (int) $rec->quantity;
            $cost = (float) ($meta['estimated_cost_usd'] ?? 0.0);
            $inTokens = (int) ($meta['input_tokens'] ?? 0);
            $outTokens = (int) ($meta['output_tokens'] ?? 0);
            $success = (bool) ($meta['success'] ?? true);

            $totalRequests++;
            $totalTokens += $qty;
            $totalCost += $cost;

            if (isset($byProvider[$p])) {
                $byProvider[$p]['total_tokens'] += $qty;
                $byProvider[$p]['input_tokens'] += $inTokens;
                $byProvider[$p]['output_tokens'] += $outTokens;
                $byProvider[$p]['estimated_cost_usd'] += $cost;
                $byProvider[$p]['requests']++;
                if ($success) {
                    $byProvider[$p]['successes']++;
                } else {
                    $byProvider[$p]['failures']++;
                }
            }
        }

        $fallbackEvents = Cache::get('docver:ai:fallback_events', []);
        $circuitBreakers = [];
        $quotaStatuses = [];

        foreach (['gemini', 'groq', 'openrouter'] as $p) {
            $circuitBreakers[$p] = $this->circuitBreaker->getState($p);
            $quotaStatuses[$p] = $this->quotaManager->getProviderQuotaStatus($p);
        }

        return $this->successResponse([
            'summary' => [
                'total_requests' => $totalRequests,
                'total_tokens' => $totalTokens,
                'total_cost_usd' => round($totalCost, 4),
                'fallback_events_count' => count($fallbackEvents),
            ],
            'by_provider' => $byProvider,
            'circuit_breakers' => $circuitBreakers,
            'quota_statuses' => $quotaStatuses,
            'recent_fallback_events' => array_slice(array_reverse($fallbackEvents), 0, 10),
            'plan_quotas' => config('ai.user_plans'),
        ], 'AI usage and quota overview retrieved successfully');
    }

    /**
     * Get cost breakdown per provider for workspace / application.
     */
    public function costSummary(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->errorResponse('Access restricted to super administrators.', [], 403);
        }

        $records = UsageRecord::where('metric_type', 'ai_tokens')
            ->where('recorded_at', '>=', now()->subDays(30))
            ->get();

        $byProvider = [
            'gemini' => ['total_tokens' => 0, 'estimated_cost_usd' => 0.0, 'requests' => 0],
            'groq' => ['total_tokens' => 0, 'estimated_cost_usd' => 0.0, 'requests' => 0],
            'openrouter' => ['total_tokens' => 0, 'estimated_cost_usd' => 0.0, 'requests' => 0],
        ];

        foreach ($records as $rec) {
            $meta = $rec->metadata ?? [];
            $p = $meta['provider'] ?? 'gemini';
            if (isset($byProvider[$p])) {
                $byProvider[$p]['total_tokens'] += (int) $rec->quantity;
                $byProvider[$p]['estimated_cost_usd'] += (float) ($meta['estimated_cost_usd'] ?? 0.0);
                $byProvider[$p]['requests']++;
            }
        }

        return $this->successResponse([
            'period' => 'last_30_days',
            'by_provider' => $byProvider,
        ], 'Provider cost and token consumption summary');
    }
}
