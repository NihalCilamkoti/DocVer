<?php

namespace App\Services\AI\Evaluation;

use App\Models\AIEvaluationLog;
use App\Services\AI\Intent\TaskIntentDTO;
use Illuminate\Support\Facades\Log;

class AIEvaluationLogger
{
    public const CURRENT_PROMPT_VERSION = 'v2.4.0';

    /**
     * Record an AI interaction for performance monitoring, grounding evaluation, and prompt refinement.
     */
    public function logInteraction(
        string $prompt,
        TaskIntentDTO $intent,
        array $conversationHistory,
        array $documentReferences,
        string $responseContent,
        string $provider,
        string $model,
        float $groundingScore,
        bool $isSuccessful = true,
        ?string $errorCategory = null,
        int $durationMs = 0,
        ?int $userId = null,
        ?int $workspaceId = null,
        ?int $comparisonJobId = null,
        ?string $conversationId = null,
        array $metadata = []
    ): ?AIEvaluationLog {
        try {
            $expected = [
                'intent' => $intent->intent,
                'target_slot' => $intent->targetSlot,
                'scope' => $intent->scope,
                'constraints' => $intent->constraints,
            ];

            return AIEvaluationLog::create([
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'comparison_job_id' => $comparisonJobId,
                'conversation_id' => $conversationId,
                'task_type' => $intent->intent,
                'prompt_version' => self::CURRENT_PROMPT_VERSION,
                'input_prompt' => $prompt,
                'conversation_context' => array_slice($conversationHistory, -5),
                'document_references' => $documentReferences,
                'expected_characteristics' => $expected,
                'actual_response' => $responseContent,
                'provider' => $provider,
                'model' => $model,
                'is_successful' => $isSuccessful,
                'error_category' => $errorCategory,
                'grounding_score' => $groundingScore,
                'duration_ms' => $durationMs,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning("AIEvaluationLogger failed to record interaction: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Record explicit user feedback on an AI response (thumbs up/down and notes).
     */
    public function recordUserFeedback(int $logId, string $rating, ?string $feedback = null): bool
    {
        $log = AIEvaluationLog::find($logId);
        if (!$log) {
            return false;
        }

        $isSuccessful = $rating !== 'down';
        $errorCategory = ($rating === 'down') ? ($log->error_category ?? 'user_reported_inaccuracy') : null;

        return (bool) $log->update([
            'user_rating' => $rating,
            'user_feedback' => $feedback,
            'is_successful' => $isSuccessful,
            'error_category' => $errorCategory,
        ]);
    }

    /**
     * Retrieve aggregated evaluation metrics.
     */
    public function getMetrics(): array
    {
        $total = AIEvaluationLog::count();
        if ($total === 0) {
            return [
                'total_queries' => 0,
                'success_rate' => 100.0,
                'avg_grounding_score' => 1.0,
                'prompt_version' => self::CURRENT_PROMPT_VERSION,
                'unsuccessful_count' => 0,
            ];
        }

        $successful = AIEvaluationLog::where('is_successful', true)->count();
        $unsuccessful = AIEvaluationLog::where('is_successful', false)->count();
        $avgGrounding = AIEvaluationLog::avg('grounding_score') ?? 1.0;

        return [
            'total_queries' => $total,
            'success_rate' => round(($successful / $total) * 100, 2),
            'avg_grounding_score' => round($avgGrounding, 2),
            'prompt_version' => self::CURRENT_PROMPT_VERSION,
            'unsuccessful_count' => $unsuccessful,
            'error_categories' => AIEvaluationLog::whereNotNull('error_category')
                ->selectRaw('error_category, count(*) as count')
                ->groupBy('error_category')
                ->pluck('count', 'error_category')
                ->toArray(),
        ];
    }
}
