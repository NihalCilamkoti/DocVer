<?php

namespace App\Services\AI;

use App\Models\UsageRecord;
use App\Services\AI\Quota\AIQuotaManager;
use Illuminate\Support\Facades\Log;

class AIUsageTracker
{
    public function __construct(
        protected AIQuotaManager $quotaManager
    ) {}

    /**
     * Record AI execution metrics to usage_records table and update provider quotas.
     */
    public function trackUsage(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $costUsd,
        int $durationMs,
        ?int $userId,
        ?int $workspaceId,
        bool $success,
        ?string $errorMessage = null,
        array $headers = []
    ): void {
        $totalTokens = $inputTokens + $outputTokens;

        // 1. Update Provider Quotas via AIQuotaManager
        if ($success) {
            $this->quotaManager->recordProviderUsage(
                provider: $provider,
                model: $model,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                headers: $headers
            );
        }

        // 2. Persist to UsageRecord for workspace billing audit
        if ($workspaceId) {
            try {
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
            } catch (\Throwable $e) {
                Log::error("Failed to persist UsageRecord: " . $e->getMessage());
            }
        }
    }
}
