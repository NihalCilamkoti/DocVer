<?php

namespace App\Services\AI\Health;

use App\Models\ProviderHealth;
use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Cache;

class AIHealthMonitor extends ProviderHealthMonitor
{
    /**
     * Retrieve status for provider including recent error log and latency.
     */
    public function getStatus(string $provider): array
    {
        $cached = Cache::get("docver:health:{$provider}");
        if ($cached) {
            return $cached;
        }

        $record = ProviderHealth::where('provider_name', $provider)->first();
        $cbState = $this->circuitBreaker->getState($provider);

        return [
            'provider' => $provider,
            'status' => $record?->status ?? 'unknown',
            'latency_ms' => $record?->latency_ms ?? 0,
            'circuit_breaker' => $cbState,
            'last_checked_at' => $record?->last_checked_at?->toIso8601String(),
            'error_details' => $record?->error_details,
        ];
    }
}
