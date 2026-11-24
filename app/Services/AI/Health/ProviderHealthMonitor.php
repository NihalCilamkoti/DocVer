<?php

namespace App\Services\AI\Health;

use App\Models\ProviderHealth;
use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Cache;

class ProviderHealthMonitor
{
    public function __construct(
        protected CircuitBreaker $circuitBreaker
    ) {}

    public function recordResult(string $provider, bool $success, int $latencyMs, ?string $errorMessage = null): void
    {
        $cbState = $this->circuitBreaker->getState($provider);
        $status = $success ? 'healthy' : ($cbState === CircuitBreaker::STATE_OPEN ? 'unhealthy' : 'degraded');

        try {
            ProviderHealth::updateOrCreate(
                ['provider_name' => $provider],
                [
                    'status' => $status,
                    'latency_ms' => $latencyMs,
                    'last_checked_at' => now(),
                    'error_details' => $errorMessage,
                    'metadata' => [
                        'circuit_breaker_state' => $cbState,
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]
            );
        } catch (\Throwable) {
            // In tests without provider_health table, continue with cache
        }

        // Ephemeral fast cache
        Cache::put("docver:health:{$provider}", [
            'status' => $status,
            'latency_ms' => $latencyMs,
            'circuit_breaker' => $cbState,
            'last_checked_at' => now()->toIso8601String(),
        ], 300);
    }

    public function checkProvider(AIProviderInterface $provider): array
    {
        $start = microtime(true);
        $name = $provider->getName();

        if (! $provider->isConfigured()) {
            $result = [
                'provider' => $name,
                'status' => 'unconfigured',
                'latency_ms' => 0,
                'message' => 'API credentials not configured in environment',
            ];
            $this->recordResult($name, false, 0, 'Not configured');
            return $result;
        }

        try {
            $healthy = $provider->ping();
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $this->recordResult($name, $healthy, $durationMs, $healthy ? null : 'Ping check failed');

            return [
                'provider' => $name,
                'status' => $healthy ? 'healthy' : 'degraded',
                'latency_ms' => $durationMs,
                'circuit_breaker' => $this->circuitBreaker->getState($name),
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->recordResult($name, false, $durationMs, $e->getMessage());

            return [
                'provider' => $name,
                'status' => 'unhealthy',
                'latency_ms' => $durationMs,
                'error' => $e->getMessage(),
                'circuit_breaker' => $this->circuitBreaker->getState($name),
            ];
        }
    }
}
