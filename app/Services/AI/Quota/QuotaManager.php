<?php

namespace App\Services\AI\Quota;

use App\Models\UsageRecord;
use App\Services\AI\Exceptions\QuotaExceededException;
use Illuminate\Support\Facades\Cache;

class QuotaManager
{
    protected int $defaultUserDailyTokenLimit;
    protected int $defaultWorkspaceMonthlyTokenLimit;

    public function __construct()
    {
        $this->defaultUserDailyTokenLimit = config('ai.quotas.default_user_daily_token_limit', 500000);
        $this->defaultWorkspaceMonthlyTokenLimit = config('ai.quotas.default_workspace_monthly_token_limit', 10000000);
    }

    /**
     * Enforce per-user and per-workspace quota limits before issuing AI request.
     *
     * @throws QuotaExceededException
     */
    public function checkQuota(?int $userId, ?int $workspaceId, int $estimatedTokens = 1000): void
    {
        if ($userId) {
            $today = now()->format('Y-m-d');
            $userKey = "docver:quota:user_{$userId}:date_{$today}:tokens";
            $reqKey = "docver:quota:user_{$userId}:date_{$today}:requests";
            $currentDailyTokens = (int) Cache::get($userKey, 0);
            $currentDailyRequests = (int) Cache::get($reqKey, 0);

            $plan = 'free';
            if ($workspaceId) {
                $ws = \App\Models\Workspace::find($workspaceId);
                if ($ws && $ws->plan) {
                    $plan = strtolower($ws->plan);
                }
            }

            $planConfig = config("ai.user_plans.{$plan}", config('ai.user_plans.free', [
                'daily_requests' => 20,
                'daily_tokens' => 100000,
            ]));

            $limitDailyRequests = $planConfig['daily_requests'] ?? 20;
            $limitDailyTokens = $planConfig['daily_tokens'] ?? $this->defaultUserDailyTokenLimit;

            if ($currentDailyRequests >= $limitDailyRequests) {
                throw new QuotaExceededException(
                    "Daily user AI request limit reached ({$currentDailyRequests}/{$limitDailyRequests} requests used today).",
                    'daily_user'
                );
            }

            if (($currentDailyTokens + $estimatedTokens) > $limitDailyTokens) {
                throw new QuotaExceededException(
                    "Daily user AI token limit exceeded ({$currentDailyTokens}/{$limitDailyTokens} tokens used today).",
                    'daily_user'
                );
            }
        }

        if ($workspaceId) {
            $month = now()->format('Y-m');
            $wsKey = "docver:quota:ws_{$workspaceId}:month_{$month}:tokens";
            $currentMonthlyTokens = (int) Cache::get($wsKey, 0);

            if (($currentMonthlyTokens + $estimatedTokens) > $this->defaultWorkspaceMonthlyTokenLimit) {
                throw new QuotaExceededException(
                    "Monthly workspace AI token limit exceeded ({$currentMonthlyTokens}/{$this->defaultWorkspaceMonthlyTokenLimit} tokens used this month).",
                    'monthly_workspace'
                );
            }
        }
    }

    /**
     * Record completed AI token consumption, updating quotas and usage_records table.
     */
    public function recordUsage(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $costUsd,
        int $durationMs,
        ?int $userId,
        ?int $workspaceId,
        bool $success,
        ?string $errorMessage = null
    ): void {
        $totalTokens = $inputTokens + $outputTokens;

        if ($userId) {
            $today = now()->format('Y-m-d');
            $userKey = "docver:quota:user_{$userId}:date_{$today}:tokens";
            Cache::increment($userKey, $totalTokens);
            Cache::put($userKey, Cache::get($userKey), 172800);

            $reqKey = "docver:quota:user_{$userId}:date_{$today}:requests";
            Cache::increment($reqKey, 1);
            Cache::put($reqKey, Cache::get($reqKey), 172800);
        }

        if ($workspaceId) {
            $month = now()->format('Y-m');
            $wsKey = "docver:quota:ws_{$workspaceId}:month_{$month}:tokens";
            Cache::increment($wsKey, $totalTokens);
            Cache::put($wsKey, Cache::get($wsKey), 2678400);

            // Persist record to MySQL
            UsageRecord::create([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'metric_type' => 'ai_tokens',
                'quantity' => $totalTokens,
                'recorded_at' => now(),
                'metadata' => [
                    'provider' => $provider,
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'estimated_cost_usd' => $costUsd,
                    'duration_ms' => $durationMs,
                    'success' => $success,
                    'error' => $errorMessage,
                ],
            ]);
        }

        if ($success) {
            try {
                app(AIQuotaManager::class)->recordProviderUsage(
                    provider: $provider,
                    model: $model,
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens
                );
            } catch (\Throwable $e) {
                // Non-fatal if database quota sync fails
            }
        }
    }
}
