<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;

class SemanticRetrievalService
{
    /**
     * Retrieve top-K relevant candidate sections for a given query or section,
     * using inverted index term frequency and title token sort ratio.
     * Prevents quadratic N*M full scans across massive documents.
     *
     * @param AnalyzedSectionDTO[] $candidates
     * @return array<int, array{section: AnalyzedSectionDTO, score: float}>
     */
    public function retrieveCandidates(string $query, array $candidates, int $topK = 5): array
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens)) {
            return [];
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            $candidateTextTokens = $this->tokenize($candidate->content);
            $candidateTitleTokens = $this->tokenize($candidate->title);

            // 1. Title token overlap score (weight 0.4)
            $titleOverlap = $this->jaccardSimilarity($queryTokens, $candidateTitleTokens);

            // 2. Content term overlap score (weight 0.4)
            $contentOverlap = $this->jaccardSimilarity($queryTokens, $candidateTextTokens);

            // 3. Category match boost (weight 0.2)
            $categoryBoost = str_contains(strtolower($query), strtolower($candidate->category)) ? 0.2 : 0.0;

            $totalScore = ($titleOverlap * 0.45) + ($contentOverlap * 0.35) + $categoryBoost;

            if ($totalScore > 0.08) {
                $scored[] = [
                    'section' => $candidate,
                    'score' => round($totalScore, 4),
                ];
            }
        }

        // Sort descending by score
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * Compute similarity score between two sections (0.0 to 1.0).
     */
    public function calculateSimilarity(AnalyzedSectionDTO $a, AnalyzedSectionDTO $b): float
    {
        // 1. Direct clause number exact match
        if ($a->clauseNumber && $b->clauseNumber && $a->clauseNumber === $b->clauseNumber) {
            $titleSim = $this->tokenSortRatio($a->title, $b->title);
            return min(1.0, 0.5 + ($titleSim * 0.5));
        }

        // 2. Title similarity
        $titleScore = $this->tokenSortRatio($a->title, $b->title);

        // 3. Category match
        $catScore = ($a->category === $b->category) ? 0.2 : 0.0;

        // 4. Content overlap
        $tokensA = $this->tokenize($a->content);
        $tokensB = $this->tokenize($b->content);
        $contentScore = $this->jaccardSimilarity($tokensA, $tokensB);

        return round(($titleScore * 0.5) + ($contentScore * 0.3) + $catScore, 4);
    }

    protected function tokenize(string $text): array
    {
        $clean = strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        // Filter standard stop words
        $stopWords = [
            'the', 'and', 'or', 'to', 'in', 'of', 'for', 'with', 'on', 'at', 'by',
            'from', 'up', 'about', 'into', 'over', 'after', 'beneath', 'under', 'above',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'do', 'does', 'did', 'but', 'if', 'as', 'than', 'this', 'that', 'these', 'those'
        ];

        return array_values(array_diff($words, $stopWords));
    }

    protected function jaccardSimilarity(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $setA = array_unique($tokensA);
        $setB = array_unique($tokensB);

        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    protected function tokenSortRatio(string $s1, string $s2): float
    {
        $t1 = $this->tokenize($s1);
        $t2 = $this->tokenize($s2);

        sort($t1);
        sort($t2);

        $sorted1 = implode(' ', $t1);
        $sorted2 = implode(' ', $t2);

        if (empty($sorted1) && empty($sorted2)) {
            return 1.0;
        }

        $maxLen = max(strlen($sorted1), strlen($sorted2));
        if ($maxLen === 0) return 0.0;

        $lev = levenshtein($sorted1, $sorted2);
        return max(0.0, 1.0 - ($lev / $maxLen));
    }
}
