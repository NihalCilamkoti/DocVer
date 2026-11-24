<?php

namespace App\Services\AI\Quota\DTOs;

class AIQuotaResultDTO
{
    public function __construct(
        public bool $isQuotaError,
        public string $type, // 'daily_user', 'provider_rate_limit', 'provider_daily', 'provider_monthly', 'provider_outage', 'invalid_config'
        public string $friendlyMessage,
        public int $remainingSeconds,
        public string $remainingTimeHuman,
        public ?string $resetsAt = null,
        public ?string $resetsAtHuman = null,
        public ?string $fallbackProviderAttempted = null,
        public bool $retryAllowed = false,
        public array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'is_quota_error' => $this->isQuotaError,
            'quota_details' => [
                'type' => $this->type,
                'friendly_message' => $this->friendlyMessage,
                'remaining_seconds' => $this->remainingSeconds,
                'remaining_time_human' => $this->remainingTimeHuman,
                'resets_at' => $this->resetsAt,
                'resets_at_human' => $this->resetsAtHuman,
                'fallback_provider_attempted' => $this->fallbackProviderAttempted,
                'retry_allowed' => $this->retryAllowed,
                'metadata' => $this->metadata,
            ],
        ];
    }
}
