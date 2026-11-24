<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\CandidateFindingDTO;

class MissingSectionDetector
{
    /**
     * Identify missing sections (removed in Doc B) and added sections (introduced in Doc B).
     *
     * @param AnalyzedSectionDTO[] $unmatchedA
     * @param AnalyzedSectionDTO[] $unmatchedB
     * @return CandidateFindingDTO[]
     */
    public function detectMissingAndAdded(array $unmatchedA, array $unmatchedB): array
    {
        $findings = [];

        // 1. Sections present in Doc A but missing in Doc B (Omission / Removal)
        foreach ($unmatchedA as $secA) {
            $severity = $this->determineRiskSeverity($secA->category);
            $excerpt = $this->createExcerpt($secA->content);

            $findings[] = new CandidateFindingDTO(
                id: "missing_" . uniqid(),
                type: 'missing',
                category: $secA->category,
                severity: $severity,
                title: "Clause Removed: {$secA->title}",
                docAPage: $secA->pageStart,
                docAEvidence: $excerpt,
                docBPage: null,
                docBEvidence: null,
                explanation: "The section '{$secA->title}' appears in Document A (page {$secA->pageStart}) but has been omitted entirely from Document B.",
                confidence: 0.99,
                metrics: [
                    'word_count' => $secA->getWordCount(),
                    'clause_number' => $secA->clauseNumber,
                ],
                verificationStatus: 'evidence_validated'
            );
        }

        // 2. Sections present in Doc B but not in Doc A (New Insertion)
        foreach ($unmatchedB as $secB) {
            $severity = $this->determineRiskSeverity($secB->category);
            $excerpt = $this->createExcerpt($secB->content);

            $findings[] = new CandidateFindingDTO(
                id: "added_" . uniqid(),
                type: 'added',
                category: $secB->category,
                severity: $severity,
                title: "New Clause Added: {$secB->title}",
                docAPage: null,
                docAEvidence: null,
                docBPage: $secB->pageStart,
                docBEvidence: $excerpt,
                explanation: "A new section '{$secB->title}' was introduced in Document B (page {$secB->pageStart}) that has no counterpart in Document A.",
                confidence: 0.99,
                metrics: [
                    'word_count' => $secB->getWordCount(),
                    'clause_number' => $secB->clauseNumber,
                ],
                verificationStatus: 'evidence_validated'
            );
        }

        return $findings;
    }

    protected function determineRiskSeverity(string $category): string
    {
        return match($category) {
            'Indemnification & Liability', 'Intellectual Property', 'Termination & Default' => 'high',
            'Payment Terms', 'Warranties & Representations', 'Governing Law & Disputes' => 'medium',
            default => 'low',
        };
    }

    protected function createExcerpt(string $content, int $maxWords = 40): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $maxWords) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . ' ...';
    }
}
