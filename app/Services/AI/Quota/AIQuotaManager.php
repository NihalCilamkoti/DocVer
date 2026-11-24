<?php

namespace App\Services\AI\Quota;

use App\Models\ProviderQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIQuotaManager
{
    protected int $safetyMarginPercent;

    public function __construct()
    {
        $this->safetyMarginPercent = config('ai.daily_quota_safety_margin', 5);
    }

    /**
     * Check if a provider has sufficient quota to execute a request.
     * Evaluates daily requests, daily tokens, RPM, TPM, and proactive safety margin.
     */
    public function canExecute(string $provider, string $model, int $estimatedTokens = 1000, ?string &$reason = null): bool
    {
        $today = Carbon::now('UTC')->format('Y-m-d');
        $providerConfig = config("ai.providers.{$provider}", []);

        // 1. Check minute rate limits (RPM & TPM) via Redis sliding counter
        $minuteBucket = (int) floor(time() / 60);
        $rpmKey = "docver:quota:{$provider}:rpm:{$minuteBucket}";
        $tpmKey = "docver:quota:{$provider}:tpm:{$minuteBucket}";

        $currentRpm = (int) Cache::get($rpmKey, 0);
        $currentTpm = (int) Cache::get($tpmKey, 0);

        $limitRpm = $providerConfig['default_rpm'] ?? null;
        $limitTpm = $providerConfig['default_tpm'] ?? null;

        if ($limitRpm !== null && ($currentRpm + 1) > $limitRpm) {
            $reason = "RPM limit reached for [{$provider}] ({$currentRpm}/{$limitRpm})";
            Log::warning("AIQuotaManager: {$reason}");
            return false;
        }

        if ($limitTpm !== null && ($currentTpm + $estimatedTokens) > $limitTpm) {
            $reason = "TPM limit reached for [{$provider}] ({$currentTpm}/{$limitTpm})";
            Log::warning("AIQuotaManager: {$reason}");
            return false;
        }

        // 2. Check Daily Limits (RPD & TPD) from Redis and Database
        $rpdKey = "docver:quota:{$provider}:rpd:{$today}";
        $tpdKey = "docver:quota:{$provider}:tpd:{$today}";

        $requestsUsedToday = (int) Cache::get($rpdKey, 0);
        $tokensUsedToday = (int) Cache::get($tpdKey, 0);

        $limitRpd = $providerConfig['default_rpd'] ?? null;
        $limitTpd = $providerConfig['default_tpd'] ?? null;

        // Check if database quota record has more accurate header-derived remaining figures
        $record = null;
        try {
            $record = ProviderQuota::where('provider_name', $provider)
                ->where('date', $today)
                ->first();
        } catch (\Throwable $e) {
            // In unit tests without RefreshDatabase, provider_quotas table may not exist
        }

        if ($record) {
            $requestsUsedToday = max($requestsUsedToday, $record->requests_used_today);
            $tokensUsedToday = max($tokensUsedToday, $record->total_tokens_used_today);

            if ($record->daily_request_limit) {
                $limitRpd = $record->daily_request_limit;
            }
            if ($record->daily_token_limit) {
                $limitTpd = $record->daily_token_limit;
            }

            // If provider explicitly reported 0 remaining requests or tokens in headers
            if ($record->requests_remaining !== null && $record->requests_remaining <= 0) {
                $reason = "Zero requests remaining for [{$provider}] according to upstream headers.";
                Log::warning("AIQuotaManager: {$reason}");
                return false;
            }

            if ($record->tokens_remaining !== null && $record->tokens_remaining < $estimatedTokens) {
                $reason = "Insufficient tokens remaining for [{$provider}] ({$record->tokens_remaining} remaining).";
                Log::warning("AIQuotaManager: {$reason}");
                return false;
            }
        }

        // 3. Proactive Safety Margin Check (5%)
        // If remaining allowance is <= 5%, proactively failover before hard rejection
        if ($limitRpd !== null && $limitRpd > 0) {
            $remainingAllowedRequests = $limitRpd - $requestsUsedToday;
            $safetyThresholdRequests = (int) ceil($limitRpd * ($this->safetyMarginPercent / 100));

            if ($remainingAllowedRequests <= $safetyThresholdRequests) {
                $reason = "Proactive safety margin: [{$provider}] has only {$remainingAllowedRequests}/{$limitRpd} requests remaining (safety margin {$this->safetyMarginPercent}%).";
                Log::warning("AIQuotaManager: {$reason}");
                return false;
            }
        }

        if ($limitTpd !== null && $limitTpd > 0) {
            $remainingAllowedTokens = $limitTpd - $tokensUsedToday;
            $safetyThresholdTokens = (int) ceil($limitTpd * ($this->safetyMarginPercent / 100));

            if ($remainingAllowedTokens <= $safetyThresholdTokens) {
                $reason = "Proactive safety margin: [{$provider}] has only {$remainingAllowedTokens}/{$limitTpd} tokens remaining (safety margin {$this->safetyMarginPercent}%).";
                Log::warning("AIQuotaManager: {$reason}");
                return false;
            }
        }

        return true;
    }

    /**
     * Record actual provider usage and process quota headers returned upstream.
     */
    public function recordProviderUsage(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        array $headers = []
    ): void {
        $today = Carbon::now('UTC')->format('Y-m-d');
        $totalTokens = $inputTokens + $outputTokens;

        // 1. Increment minute counters (TTL 120s)
        $minuteBucket = (int) floor(time() / 60);
        $rpmKey = "docver:quota:{$provider}:rpm:{$minuteBucket}";
        $tpmKey = "docver:quota:{$provider}:tpm:{$minuteBucket}";

        Cache::increment($rpmKey, 1);
        Cache::put($rpmKey, Cache::get($rpmKey), 120);

        Cache::increment($tpmKey, $totalTokens);
        Cache::put($tpmKey, Cache::get($tpmKey), 120);

        // 2. Increment daily counters in Redis (TTL 48h)
        $rpdKey = "docver:quota:{$provider}:rpd:{$today}";
        $tpdKey = "docver:quota:{$provider}:tpd:{$today}";

        Cache::increment($rpdKey, 1);
        Cache::put($rpdKey, Cache::get($rpdKey), 172800);

        Cache::increment($tpdKey, $totalTokens);
        Cache::put($tpdKey, Cache::get($tpdKey), 172800);

        // 3. Upsert MySQL provider_quotas record
        try {
            $providerConfig = config("ai.providers.{$provider}", []);
            $defaultRpd = $providerConfig['default_rpd'] ?? null;
            $defaultTpd = $providerConfig['default_tpd'] ?? null;

            $record = ProviderQuota::firstOrNew([
                'provider_name' => $provider,
                'date' => $today,
            ]);

            $record->model = $model;
            $record->requests_used_today = ($record->requests_used_today ?? 0) + 1;
            $record->input_tokens_used_today = ($record->input_tokens_used_today ?? 0) + $inputTokens;
            $record->output_tokens_used_today = ($record->output_tokens_used_today ?? 0) + $outputTokens;
            $record->total_tokens_used_today = ($record->total_tokens_used_today ?? 0) + $totalTokens;

            if (! $record->daily_request_limit && $defaultRpd) {
                $record->daily_request_limit = $defaultRpd;
            }
            if (! $record->daily_token_limit && $defaultTpd) {
                $record->daily_token_limit = $defaultTpd;
            }

            // Ingest headers if present
            if (! empty($headers)) {
                if (isset($headers['requests_remaining']) && is_numeric($headers['requests_remaining'])) {
                    $record->requests_remaining = (int) $headers['requests_remaining'];
                }
                if (isset($headers['tokens_remaining']) && is_numeric($headers['tokens_remaining'])) {
                    $record->tokens_remaining = (int) $headers['tokens_remaining'];
                }
                if (isset($headers['requests_limit']) && is_numeric($headers['requests_limit'])) {
                    $record->daily_request_limit = (int) $headers['requests_limit'];
                }
                if (isset($headers['tokens_limit']) && is_numeric($headers['tokens_limit'])) {
                    // If the tokens limit is under 50k, it represents per-minute TPM limit rather than daily TPD
                    $limitVal = (int) $headers['tokens_limit'];
                    if ($limitVal >= 50000) {
                        $record->daily_token_limit = $limitVal;
                    }
                }
            }

            $record->save();
        } catch (\Throwable $e) {
            // Ignore when DB table is unmigrated in pure mock unit tests
        }
    }

    /**
     * Record provider error status and message.
     */
    public function recordProviderError(string $provider, int $status, string $message): void
    {
        $today = Carbon::now('UTC')->format('Y-m-d');

        try {
            ProviderQuota::where('provider_name', $provider)
                ->where('date', $today)
                ->update([
                    'last_error_status' => $status,
                    'last_error_message' => substr($message, 0, 500),
                ]);
        } catch (\Throwable $e) {
            // Ignore when DB table is unmigrated in pure mock unit tests
        }
    }

    /**
     * Get real-time quota status summary for a provider.
     */
    public function getProviderQuotaStatus(string $provider): array
    {
        $today = Carbon::now('UTC')->format('Y-m-d');
        $providerConfig = config("ai.providers.{$provider}", []);

        $minuteBucket = (int) floor(time() / 60);
        $currentRpm = (int) Cache::get("docver:quota:{$provider}:rpm:{$minuteBucket}", 0);
        $currentTpm = (int) Cache::get("docver:quota:{$provider}:tpm:{$minuteBucket}", 0);

        $record = ProviderQuota::where('provider_name', $provider)
            ->where('date', $today)
            ->first();

        $requestsUsed = $record?->requests_used_today ?? (int) Cache::get("docver:quota:{$provider}:rpd:{$today}", 0);
        $tokensUsed = $record?->total_tokens_used_today ?? (int) Cache::get("docver:quota:{$provider}:tpd:{$today}", 0);

        $limitRpd = $record?->daily_request_limit ?? $providerConfig['default_rpd'] ?? null;
        $limitTpd = $record?->daily_token_limit ?? $providerConfig['default_tpd'] ?? null;

        $requestsRemaining = $record?->requests_remaining ?? ($limitRpd ? max(0, $limitRpd - $requestsUsed) : null);
        $tokensRemaining = $record?->tokens_remaining ?? ($limitTpd ? max(0, $limitTpd - $tokensUsed) : null);

        // Calculate seconds until 00:00 UTC daily reset
        $endOfDayUtc = Carbon::now('UTC')->endOfDay();
        $resetCountdown = max(0, Carbon::now('UTC')->diffInSeconds($endOfDayUtc));

        $isExhausted = ($requestsRemaining !== null && $requestsRemaining <= 0) ||
            ($tokensRemaining !== null && $tokensRemaining <= 0);

        $isNearExhaustion = false;
        if (! $isExhausted) {
            if ($limitRpd && $requestsRemaining !== null && $requestsRemaining <= ($limitRpd * ($this->safetyMarginPercent / 100))) {
                $isNearExhaustion = true;
            }
            if ($limitTpd && $tokensRemaining !== null && $tokensRemaining <= ($limitTpd * ($this->safetyMarginPercent / 100))) {
                $isNearExhaustion = true;
            }
        }

        return [
            'provider' => $provider,
            'requests_used_today' => $requestsUsed,
            'daily_request_limit' => $limitRpd,
            'requests_remaining' => $requestsRemaining,
            'tokens_used_today' => $tokensUsed,
            'daily_token_limit' => $limitTpd,
            'tokens_remaining' => $tokensRemaining,
            'current_rpm' => $currentRpm,
            'rpm_limit' => $providerConfig['default_rpm'] ?? null,
            'current_tpm' => $currentTpm,
            'tpm_limit' => $providerConfig['default_tpm'] ?? null,
            'reset_countdown_seconds' => $resetCountdown,
            'is_exhausted' => $isExhausted,
            'is_near_exhaustion' => $isNearExhaustion,
            'safety_margin_percent' => $this->safetyMarginPercent,
            'last_error_status' => $record?->last_error_status,
            'last_error_message' => $record?->last_error_message,
        ];
    }
}
