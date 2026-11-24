<?php

namespace App\Services\AI\Health;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        protected int $failureThreshold = 3,
        protected int $cooldownSeconds = 60,
        protected int $halfOpenSuccessThreshold = 2
    ) {
        $this->failureThreshold = config('ai.circuit_breaker.failure_threshold', 3);
        $this->cooldownSeconds = config('ai.circuit_breaker.cooldown_seconds', 60);
        $this->halfOpenSuccessThreshold = config('ai.circuit_breaker.half_open_success_threshold', 2);
    }

    public function isAvailable(string $provider): bool
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            $openedAt = Cache::get($this->openTimeKey($provider));
            $cooldown = Cache::get($this->customCooldownKey($provider), $this->cooldownSeconds);

            if ($openedAt && (time() - $openedAt) > $cooldown) {
                // Transition to half-open to allow trial request
                $this->setState($provider, self::STATE_HALF_OPEN);
                Cache::put($this->halfOpenSuccessKey($provider), 0, 300);
                return true;
            }

            return false;
        }

        // HALF_OPEN: allow trial calls
        return true;
    }

    public function recordSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $successes = Cache::increment($this->halfOpenSuccessKey($provider));
            if ($successes >= $this->halfOpenSuccessThreshold) {
                $this->reset($provider);
            }
            return;
        }

        // Clear failures on success in closed state
        Cache::forget($this->failureCountKey($provider));
    }

    public function recordFailure(string $provider, ?int $retryAfterSeconds = null): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            // Immediate trip back to open
            $this->tripOpen($provider, $retryAfterSeconds);
            return;
        }

        $failures = Cache::increment($this->failureCountKey($provider));
        // Keep failure count alive for 5 minutes
        Cache::put($this->failureCountKey($provider), $failures, 300);

        if ($failures >= $this->failureThreshold) {
            $this->tripOpen($provider, $retryAfterSeconds);
        }
    }

    public function getState(string $provider): string
    {
        return Cache::get($this->stateKey($provider), self::STATE_CLOSED);
    }

    public function reset(string $provider): void
    {
        Cache::put($this->stateKey($provider), self::STATE_CLOSED, 86400);
        Cache::forget($this->failureCountKey($provider));
        Cache::forget($this->openTimeKey($provider));
        Cache::forget($this->customCooldownKey($provider));
        Cache::forget($this->halfOpenSuccessKey($provider));
    }

    protected function tripOpen(string $provider, ?int $retryAfterSeconds = null): void
    {
        $cooldown = $retryAfterSeconds ? max($retryAfterSeconds, 5) : $this->cooldownSeconds;

        $this->setState($provider, self::STATE_OPEN);
        Cache::put($this->openTimeKey($provider), time(), $cooldown + 300);
        Cache::put($this->customCooldownKey($provider), $cooldown, $cooldown + 300);
        Cache::forget($this->halfOpenSuccessKey($provider));
    }

    protected function setState(string $provider, string $state): void
    {
        Cache::put($this->stateKey($provider), $state, 86400);
    }

    protected function stateKey(string $provider): string
    {
        return "docver:cb:{$provider}:state";
    }

    protected function failureCountKey(string $provider): string
    {
        return "docver:cb:{$provider}:failures";
    }

    protected function openTimeKey(string $provider): string
    {
        return "docver:cb:{$provider}:opened_at";
    }

    protected function customCooldownKey(string $provider): string
    {
        return "docver:cb:{$provider}:cooldown";
    }

    protected function halfOpenSuccessKey(string $provider): string
    {
        return "docver:cb:{$provider}:half_open_successes";
    }
}
