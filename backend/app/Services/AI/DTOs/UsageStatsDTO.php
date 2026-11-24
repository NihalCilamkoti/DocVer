<?php

namespace App\Services\AI\DTOs;

readonly class UsageStatsDTO
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public float $estimatedCostUsd = 0.0,
        public int $durationMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'duration_ms' => $this->durationMs,
        ];
    }
}
