<?php

namespace App\Services\AI\Quota;

use App\Services\AI\Exceptions\AllProvidersUnavailableException;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Quota\DTOs\AIQuotaResultDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AIQuotaResolver
{
    public function __construct(
        protected AIQuotaManager $quotaManager
    ) {}

    /**
     * Resolve a friendly quota result when a user's plan limit is exceeded.
     */
    public function resolveUserQuotaLimit(int $userId, string $plan = 'free', ?string $timezone = 'UTC'): AIQuotaResultDTO
    {
        $planConfig = config("ai.user_plans.{$plan}", config('ai.user_plans.free', [
            'daily_requests' => 20,
            'daily_tokens' => 100000,
        ]));

        $limitRequests = $planConfig['daily_requests'] ?? 20;

        $now = Carbon::now($timezone ?: 'UTC');
        $resetAt = $now->copy()->endOfDay()->addSecond();
        $remainingSeconds = max(1, $now->diffInSeconds($resetAt));

        $remainingHuman = $this->formatRemainingTime($remainingSeconds);
        $resetHuman = $this->formatResetTimestamp($resetAt, $timezone);

        $message = "You've used all your AI requests for today ({$limitRequests} requests on your " . ucfirst($plan) . " plan). Don't worry! Your AI access will reset in approximately {$remainingHuman} ({$resetHuman}).";

        return new AIQuotaResultDTO(
            isQuotaError: true,
            type: 'daily_user',
            friendlyMessage: $message,
            remainingSeconds: $remainingSeconds,
            remainingTimeHuman: $remainingHuman,
            resetsAt: $resetAt->toIso8601String(),
            resetsAtHuman: $resetHuman,
            fallbackProviderAttempted: null,
            retryAllowed: false,
            metadata: [
                'plan' => $plan,
                'limit_requests' => $limitRequests,
            ]
        );
    }

    /**
     * Resolve friendly quota result for upstream provider rate limits (429 / RPM / TPM).
     */
    public function resolveProviderRateLimit(string $provider, ?int $retryAfterSeconds = null, ?string $fallbackProvider = null): AIQuotaResultDTO
    {
        $seconds = $retryAfterSeconds ?: 60;
        $now = Carbon::now('UTC');
        $resetAt = $now->copy()->addSeconds($seconds);
        $remainingHuman = $this->formatRemainingTime($seconds);
        $resetHuman = $this->formatResetTimestamp($resetAt);

        $providerName = config("ai.providers.{$provider}.name", ucfirst($provider));

        if ($fallbackProvider) {
            $fallbackName = config("ai.providers.{$fallbackProvider}.name", ucfirst($fallbackProvider));
            $message = "Our primary AI provider ({$providerName}) is currently handling too many requests. We're automatically transferring your request to {$fallbackName}.";
        } else {
            $message = "Our AI service ({$providerName}) is currently handling too many requests. We've reached a temporary rate limit. Please try again in {$remainingHuman}.";
        }

        return new AIQuotaResultDTO(
            isQuotaError: true,
            type: 'provider_rate_limit',
            friendlyMessage: $message,
            remainingSeconds: $seconds,
            remainingTimeHuman: $remainingHuman,
            resetsAt: $resetAt->toIso8601String(),
            resetsAtHuman: $resetHuman,
            fallbackProviderAttempted: $fallbackProvider,
            retryAllowed: true,
            metadata: [
                'provider' => $provider,
            ]
        );
    }

    /**
     * Resolve friendly quota result when all providers have exhausted daily or overall capacity.
     */
    public function resolveAllProvidersExhausted(?string $lastProvider = null): AIQuotaResultDTO
    {
        $now = Carbon::now('UTC');
        $resetAt = $now->copy()->endOfDay()->addSecond();
        $remainingSeconds = max(60, $now->diffInSeconds($resetAt));

        $remainingHuman = $this->formatRemainingTime($remainingSeconds);
        $resetHuman = $this->formatResetTimestamp($resetAt);

        $message = "Our AI assistant has temporarily reached its available usage limits across all configured providers. We're sorry for the interruption! Access will resume in approximately {$remainingHuman} ({$resetHuman}).";

        return new AIQuotaResultDTO(
            isQuotaError: true,
            type: 'provider_daily',
            friendlyMessage: $message,
            remainingSeconds: $remainingSeconds,
            remainingTimeHuman: $remainingHuman,
            resetsAt: $resetAt->toIso8601String(),
            resetsAtHuman: $resetHuman,
            fallbackProviderAttempted: $lastProvider,
            retryAllowed: false,
            metadata: [
                'all_providers_exhausted' => true,
            ]
        );
    }

    /**
     * Resolve unexpected provider error, outage, or bad configuration.
     */
    public function resolveGeneralError(\Throwable $e, ?string $provider = null): AIQuotaResultDTO
    {
        $message = $e->getMessage();
        $isAuth = str_contains($message, '401') || str_contains($message, '403') || str_contains(strtolower($message), 'api key') || str_contains(strtolower($message), 'unauthorized');
        $isRate = str_contains($message, '429') || str_contains(strtolower($message), 'rate limit') || str_contains(strtolower($message), 'quota');
        $isAll = $e instanceof AllProvidersUnavailableException || str_contains(strtolower($message), 'all ai providers failed');

        if ($isAuth) {
            return new AIQuotaResultDTO(
                isQuotaError: true,
                type: 'invalid_config',
                friendlyMessage: "AI services are temporarily unavailable due to a service configuration update. Our system administrators have been notified.",
                remainingSeconds: 300,
                remainingTimeHuman: "a few moments",
                resetsAt: null,
                resetsAtHuman: "shortly",
                fallbackProviderAttempted: $provider,
                retryAllowed: false
            );
        }

        if ($isAll) {
            return $this->resolveAllProvidersExhausted($provider);
        }

        if ($isRate) {
            return $this->resolveProviderRateLimit($provider ?: 'primary', 60);
        }

        return new AIQuotaResultDTO(
            isQuotaError: true,
            type: 'provider_outage',
            friendlyMessage: "Our AI assistant is temporarily unavailable. We're working to restore the service. Please try again shortly.",
            remainingSeconds: 60,
            remainingTimeHuman: "1 minute",
            resetsAt: null,
            resetsAtHuman: "shortly",
            fallbackProviderAttempted: $provider,
            retryAllowed: true
        );
    }

    /**
     * Format seconds into a friendly human-readable duration:
     * e.g., "3 hours and 20 minutes", "45 seconds", "1 minute"
     */
    public function formatRemainingTime(int $totalSeconds): string
    {
        if ($totalSeconds <= 0) {
            return "a few seconds";
        }

        $hours = (int) floor($totalSeconds / 3600);
        $minutes = (int) floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours} " . Str::plural('hour', $hours);
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes} " . Str::plural('minute', $minutes);
        }

        if ($seconds > 0 && ($hours === 0 || empty($parts))) {
            $parts[] = "{$seconds} " . Str::plural('second', $seconds);
        }

        if (count($parts) > 1) {
            return implode(' and ', array_slice($parts, 0, 2));
        }

        return $parts[0];
    }

    /**
     * Format reset timestamp in a human-readable phrase.
     */
    public function formatResetTimestamp(Carbon $resetsAt, ?string $timezone = 'UTC'): string
    {
        $now = Carbon::now($timezone ?: 'UTC');
        $resetsAt = $resetsAt->copy()->setTimezone($timezone ?: 'UTC');

        if ($resetsAt->isToday()) {
            return "today at " . $resetsAt->format('g:i A T');
        }

        if ($resetsAt->isTomorrow()) {
            return "tomorrow at " . $resetsAt->format('g:i A T');
        }

        return $resetsAt->format('M j, Y \a\t g:i A T');
    }
}
