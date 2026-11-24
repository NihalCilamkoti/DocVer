<?php

namespace App\Services\AI\Config;

use App\Services\AI\Intent\TaskIntentDTO;

class TokenManager
{
    /**
     * Get task-optimal temperature. Factual and numerical audits must have 0.0.
     */
    public function getTemperatureForIntent(TaskIntentDTO $intent): float
    {
        return match ($intent->intent) {
            'numerical_comparison', 'clause_diff' => 0.0,
            'section_specific', 'contradiction_check', 'missing_clause' => 0.05,
            'role_and_task_extraction' => 0.1,
            'summarization' => 0.15,
            default => 0.15,
        };
    }

    /**
     * Get task-optimal max token output limit.
     */
    public function getMaxTokensForIntent(TaskIntentDTO $intent): int
    {
        return match ($intent->intent) {
            'role_and_task_extraction', 'summarization' => 3000,
            'clause_diff', 'numerical_comparison' => 2048,
            'section_specific' => 1500,
            default => 2048,
        };
    }

    /**
     * Estimate token count from string length (~4 chars per token).
     */
    public function estimateTokenCount(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Ensure prompt fits within model context limit.
     */
    public function truncateToTokenLimit(string $prompt, int $maxAllowedTokens = 30000): string
    {
        $estimated = $this->estimateTokenCount($prompt);
        if ($estimated <= $maxAllowedTokens) {
            return $prompt;
        }

        $charLimit = $maxAllowedTokens * 4;
        return mb_substr($prompt, 0, $charLimit) . "\n\n...[Context capped for model safety]...";
    }
}
