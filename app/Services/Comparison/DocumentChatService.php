<?php

namespace App\Services\Comparison;

use App\Models\ComparisonJob;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Security\PromptSanitizer;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Generator;
use Illuminate\Support\Facades\Log;

class DocumentChatService
{
    public function __construct(
        protected EphemeralIndexManager $indexManager,
        protected SemanticRetrievalService $retrievalService,
        protected AIProviderRouter $aiRouter,
        protected PromptSanitizer $sanitizer,
        protected ?AIOrchestrator $orchestrator = null
    ) {
        $this->orchestrator = $this->orchestrator ?? app(AIOrchestrator::class);
    }

    /**
     * Answer document query with verified evidence citations and follow-up suggestions.
     * Uses centralized AIOrchestrator with Intent Classification, Section-Aware Context, and Response Validation.
     */
    public function answerQuery(ComparisonJob $job, string $query, ?int $userId = null): array
    {
        return $this->orchestrator->answerComparisonQuery($job, $query, $userId);
    }

    /**
     * Stream answer chunk by chunk via generator.
     */
    public function streamAnswer(ComparisonJob $job, string $query, ?int $userId = null): Generator
    {
        yield from $this->orchestrator->streamComparisonQuery($job, $query, $userId);
    }

    protected function retrieveTopChunks(string $query, array $chunks, string $slot, int $topK = 3): array
    {
        if (empty($chunks)) {
            return [];
        }

        $scored = [];
        $queryWords = array_unique(preg_split('/\s+/', strtolower($query), -1, PREG_SPLIT_NO_EMPTY));

        foreach ($chunks as $chunk) {
            $content = strtolower($chunk['content'] ?? '');
            $title = strtolower($chunk['section_title'] ?? '');

            $matchCount = 0;
            foreach ($queryWords as $w) {
                if (strlen($w) > 3 && (str_contains($content, $w) || str_contains($title, $w))) {
                    $matchCount++;
                }
            }

            $score = count($queryWords) > 0 ? ($matchCount / count($queryWords)) : 0.0;
            $scored[] = [
                'chunk' => $chunk,
                'score' => $score,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $topK);
    }

    protected function createQuote(string $content, int $maxWords = 25): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $maxWords) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . ' ...';
    }

    protected function generateFollowUps(string $query): array
    {
        $lower = strtolower($query);

        if (str_contains($lower, 'financial') || str_contains($lower, 'pay') || str_contains($lower, 'amount')) {
            return [
                'Show all payment timeline changes',
                'Are there any new recurring fee obligations?',
                'Compare liability caps between versions',
            ];
        }

        if (str_contains($lower, 'terminat')) {
            return [
                'What are the notice periods for termination?',
                'Is termination for convenience allowed?',
                'What penalties apply upon contract default?',
            ];
        }

        return [
            'What are the highest risk changes?',
            'Show all numerical discrepancies',
            'Were any key clauses omitted in Document B?',
        ];
    }
}
