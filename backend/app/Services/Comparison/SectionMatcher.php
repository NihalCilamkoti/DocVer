<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\SectionMatchDTO;

class SectionMatcher
{
    public const MATCH_THRESHOLD = 0.35;

    public function __construct(
        protected SemanticRetrievalService $retrievalService
    ) {}

    /**
     * Match sections between Doc A and Doc B, returning matches and unmatched sets.
     *
     * @param AnalyzedSectionDTO[] $sectionsA
     * @param AnalyzedSectionDTO[] $sectionsB
     * @return array{
     *     matches: SectionMatchDTO[],
     *     unmatchedA: AnalyzedSectionDTO[],
     *     unmatchedB: AnalyzedSectionDTO[]
     * }
     */
    public function matchSections(array $sectionsA, array $sectionsB): array
    {
        $matches = [];
        $matchedBIds = [];

        foreach ($sectionsA as $secA) {
            $bestMatch = null;
            $bestScore = 0.0;
            $matchType = 'semantic';

            // 1. First priority: Exact clause number match
            if ($secA->clauseNumber) {
                foreach ($sectionsB as $secB) {
                    if (! in_array($secB->id, $matchedBIds) && $secB->clauseNumber === $secA->clauseNumber) {
                        $bestMatch = $secB;
                        $bestScore = 0.95;
                        $matchType = 'clause_number';
                        break;
                    }
                }
            }

            // 2. Second priority: Exact title match (excluding generic titles like 'Preamble' or 'General Provisions')
            if (! $bestMatch && $secA->title && ! in_array(strtolower(trim($secA->title)), ['preamble', 'general provisions', 'document content', 'general', 'document overview'])) {
                foreach ($sectionsB as $secB) {
                    if (! in_array($secB->id, $matchedBIds) && strcasecmp(trim($secA->title), trim($secB->title)) === 0) {
                        $bestMatch = $secB;
                        $bestScore = 0.90;
                        $matchType = 'exact_heading';
                        break;
                    }
                }
            }

            // 3. Third priority: Candidate retrieval + similarity scoring
            if (! $bestMatch) {
                $candidates = array_filter($sectionsB, fn($b) => ! in_array($b->id, $matchedBIds));
                $topCandidates = $this->retrievalService->retrieveCandidates($secA->title . ' ' . $secA->content, $candidates, 3);

                if (! empty($topCandidates)) {
                    $candidateSection = $topCandidates[0]['section'];
                    $contentSim = $this->retrievalService->calculateSimilarity($secA, $candidateSection);
                    $sameCategory = ($secA->category === $candidateSection->category);
                    $isGeneric = in_array(strtolower(trim($secA->title)), ['preamble', 'general provisions', 'document content', 'general', 'document overview']);

                    // Require genuine semantic similarity (>= 0.30, or >= 0.35 if categories differ or generic titles)
                    $requiredSim = ($sameCategory && ! $isGeneric) ? 0.30 : 0.35;

                    if ($contentSim >= $requiredSim) {
                        $bestMatch = $candidateSection;
                        $bestScore = max($topCandidates[0]['score'], $contentSim);
                        $matchType = ($secA->title === $bestMatch->title && ! $isGeneric) ? 'exact_heading' : 'semantic';
                    }
                }
            }

            if ($bestMatch) {
                $matches[] = new SectionMatchDTO(
                    sectionA: $secA,
                    sectionB: $bestMatch,
                    similarityScore: $bestScore,
                    matchType: $matchType
                );
                $matchedBIds[] = $bestMatch->id;
            }
        }

        // Determine unmatched sections
        $matchedAIds = array_map(fn($m) => $m->sectionA->id, $matches);
        $unmatchedA = array_values(array_filter($sectionsA, fn($a) => ! in_array($a->id, $matchedAIds)));
        $unmatchedB = array_values(array_filter($sectionsB, fn($b) => ! in_array($b->id, $matchedBIds)));

        return [
            'matches' => $matches,
            'unmatchedA' => $unmatchedA,
            'unmatchedB' => $unmatchedB,
        ];
    }
}
