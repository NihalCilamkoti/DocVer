<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\DocumentRelationshipDTO;

class DocumentRelationshipClassifier
{
    /**
     * Inspect sections, titles, and text to determine relationship, purposes, and optimal comparison mode.
     *
     * @param AnalyzedSectionDTO[] $sectionsA
     * @param AnalyzedSectionDTO[] $sectionsB
     */
    public function classify(
        array $sectionsA,
        array $sectionsB,
        string $docAName = 'Document A',
        string $docBName = 'Document B',
        string $userPrompt = ''
    ): DocumentRelationshipDTO {
        $textA = implode("\n", array_map(fn($s) => $s->title . "\n" . $s->content, $sectionsA));
        $textB = implode("\n", array_map(fn($s) => $s->title . "\n" . $s->content, $sectionsB));

        $lowerA = strtolower($textA . ' ' . $docAName);
        $lowerB = strtolower($textB . ' ' . $docBName);

        $typeA = $this->detectDocumentType($lowerA, $sectionsA);
        $typeB = $this->detectDocumentType($lowerB, $sectionsB);

        $purposeA = $this->determinePurpose($typeA, $lowerA, $docAName);
        $purposeB = $this->determinePurpose($typeB, $lowerB, $docBName);

        // Calculate dimensional similarities:
        // 1. Exact textual overlap (Jaccard similarity on significant tokens)
        $textualOverlap = $this->calculateTokenOverlap($lowerA, $lowerB);

        // 2. Structural heading alignment
        $structuralSimilarity = $this->calculateHeadingOverlap($sectionsA, $sectionsB);

        // 3. Topic similarity
        $topicSimilarity = $this->calculateTopicSimilarity($lowerA, $lowerB);

        // 4. Semantic similarity based on type compatibility and topical alignment
        $semanticSimilarity = $this->calculateSemanticSimilarity($typeA, $typeB, $topicSimilarity);

        // Determine if they are genuine revisions of the same document
        $isContractPair = ($typeA === 'contract' && $typeB === 'contract');
        $hasRevisionMarker = (bool) preg_match('/(?:[\b_]|^)(?:revised|amended|v\d+|draft\s*\d+|version|update)(?:[\b_]|$)/i', $docAName . ' ' . $docBName);
        $isSameSubject = ($typeA === $typeB) && ($textualOverlap > 0.35 || $structuralSimilarity > 0.30 || $isContractPair);
        $isVersionRevision = ($isSameSubject && ($textualOverlap >= 0.35 || $hasRevisionMarker || $structuralSimilarity >= 0.30 || $isContractPair));

        // Classify relationship
        if ($isVersionRevision) {
            $relationship = 'versions_of_same_document';
            $recommendedMode = 'version_comparison';
            $summary = "Document A ({$docAName}) and Document B ({$docBName}) are different versions of the same document sharing core structural provisions.";
        } elseif ($typeA === $typeB && ($textualOverlap > 0.25 || $structuralSimilarity > 0.25)) {
            $relationship = 'related_topics';
            $recommendedMode = 'general_comparison';
            $summary = "Document A ({$docAName}) and Document B ({$docBName}) cover related topics within the same domain (" . ucfirst(str_replace('_', ' ', $typeA)) . ") but have distinct structural terms.";
        } elseif ($typeA !== $typeB && ($typeA === 'schedule' || $typeB === 'schedule' || $typeA === 'roles' || $typeB === 'roles')) {
            $relationship = 'different_purposes';
            $recommendedMode = 'general_comparison';
            $summary = "Document A ({$docAName}) focuses on {$purposeA}, while Document B ({$docBName}) focuses on {$purposeB}. They serve completely different purposes and are not revisions of one another.";
        } elseif ($textualOverlap < 0.15 && $structuralSimilarity < 0.15 && $topicSimilarity < 0.20) {
            $relationship = 'unrelated';
            $recommendedMode = 'general_comparison';
            $summary = "Document A ({$docAName}) and Document B ({$docBName}) have different subjects and purposes. They are independent documents rather than revisions.";
        } else {
            $relationship = 'different_purposes';
            $recommendedMode = 'general_comparison';
            $summary = "Document A ({$docAName}) and Document B ({$docBName}) address distinct operational or informational subjects.";
        }

        // If the user explicitly asked for version comparison but they are unrelated, keep recommendedMode as general_comparison
        $lowerPrompt = strtolower($userPrompt);
        if (preg_match('/\b(?:version|revision|what\s+changed|clause\s+removed|redline|diff)\b/i', $lowerPrompt) && $isVersionRevision) {
            $recommendedMode = 'version_comparison';
        }

        // Generate dynamic similarity explanation
        $textualPct = (int) round($textualOverlap * 100);
        $structuralPct = (int) round($structuralSimilarity * 100);
        $topicPct = (int) round($topicSimilarity * 100);
        $semanticPct = (int) round($semanticSimilarity * 100);

        if ($relationship === 'versions_of_same_document') {
            $simExplanation = "High structural alignment ({$structuralPct}%) and textual overlap ({$textualPct}%) confirm these documents are different versions of the same document.";
        } elseif ($relationship === 'different_purposes' || $relationship === 'unrelated') {
            $simExplanation = "Low textual overlap ({$textualPct}%) and distinct heading structures ({$structuralPct}%) indicate that these documents serve different operational purposes ({$purposeA} vs. {$purposeB}) rather than being conflicting revisions.";
        } else {
            $simExplanation = "Moderate topical overlap ({$topicPct}%) with distinct document sections ({$structuralPct}% structural match).";
        }

        $similarities = $this->extractMeaningfulSimilarities($typeA, $typeB, $textA, $textB, $lowerA, $lowerB);
        $differences = $this->extractMeaningfulDifferences($typeA, $typeB, $sectionsA, $sectionsB, $docAName, $docBName);
        $uniqueA = $this->extractUniqueKeyPoints($sectionsA, $lowerB);
        $uniqueB = $this->extractUniqueKeyPoints($sectionsB, $lowerA);

        return new DocumentRelationshipDTO(
            purposeA: $purposeA,
            purposeB: $purposeB,
            typeA: $typeA,
            typeB: $typeB,
            relationship: $relationship,
            isVersionRevision: $isVersionRevision,
            recommendedMode: $recommendedMode,
            relationshipSummary: $summary,
            similarities: $similarities,
            differences: $differences,
            uniqueA: $uniqueA,
            uniqueB: $uniqueB,
            contentOverlap: round($textualOverlap, 2),
            topicSimilarity: round($topicSimilarity, 2),
            semanticSimilarity: round($semanticSimilarity, 2),
            structuralSimilarity: round($structuralSimilarity, 2),
            textualOverlap: round($textualOverlap, 2),
            similarityExplanation: $simExplanation
        );
    }

    protected function detectDocumentType(string $text, array $sections): string
    {
        return match(true) {
            str_contains($text, 'wake up') || str_contains($text, 'routine') || (str_contains($text, 'schedule') && preg_match('/\b(?:am|pm|breakfast|sleep|studying|gym|hour|hours|daily)\b/', $text)) => 'schedule',
            str_contains($text, 'roles') || str_contains($text, 'assigned') || str_contains($text, 'team members') || str_contains($text, 'responsibilities') && (str_contains($text, 'lead') || str_contains($text, 'taken by') || str_contains($text, 'assigned to')) => 'roles',
            (str_contains($text, 'parties') || str_contains($text, 'party') || str_contains($text, 'vendor') || str_contains($text, 'client') || str_contains($text, 'provider') || str_contains($text, 'termination')) && (str_contains($text, 'agreement') || str_contains($text, 'indemnif') || str_contains($text, 'governing law') || str_contains($text, 'contract') || str_contains($text, 'services') || str_contains($text, 'clause')) => 'contract',
            str_contains($text, 'policy') || str_contains($text, 'acceptable use') || str_contains($text, 'terms of service') || str_contains($text, 'privacy policy') => 'policy',
            str_contains($text, 'curriculum vitae') || (str_contains($text, 'resume') && str_contains($text, 'experience')) || (str_contains($text, 'education') && str_contains($text, 'skills')) => 'resume',
            str_contains($text, 'job description') || (str_contains($text, 'responsibilities') && str_contains($text, 'qualifications') && str_contains($text, 'salary')) => 'job_description',
            str_contains($text, 'balance sheet') || (str_contains($text, 'revenue') && str_contains($text, 'fiscal year')) => 'financial_report',
            default => 'general',
        };
    }

    protected function determinePurpose(string $type, string $text, string $docName): string
    {
        return match($type) {
            'schedule' => 'personal daily schedule, time allocation, and activity timeline',
            'roles' => 'team member role assignments, operational duties, and project responsibilities',
            'contract' => 'commercial and legal agreement outlining binding contractual obligations',
            'policy' => 'organizational policy and operational governance standards',
            'resume' => 'professional candidate profile, work history, and qualifications',
            'job_description' => 'position requirements, role scope, and candidate qualifications',
            'financial_report' => 'financial statement metrics and fiscal performance review',
            default => 'informational document covering ' . str_replace(['_', '-'], ' ', pathinfo($docName, PATHINFO_FILENAME)),
        };
    }

    protected function calculateTokenOverlap(string $a, string $b): float
    {
        $wordsA = array_unique(array_filter(preg_split('/\W+/', $a), fn($w) => strlen($w) > 3));
        $wordsB = array_unique(array_filter(preg_split('/\W+/', $b), fn($w) => strlen($w) > 3));

        if (empty($wordsA) || empty($wordsB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));

        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    protected function calculateHeadingOverlap(array $sectionsA, array $sectionsB): float
    {
        $titlesA = array_unique(array_filter(array_map(fn($s) => strtolower(trim($s->title)), $sectionsA)));
        $titlesB = array_unique(array_filter(array_map(fn($s) => strtolower(trim($s->title)), $sectionsB)));

        if (empty($titlesA) || empty($titlesB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($titlesA, $titlesB));
        $min = min(count($titlesA), count($titlesB));

        return $min > 0 ? ($intersection / $min) : 0.0;
    }

    protected function calculateTopicSimilarity(string $lowerA, string $lowerB): float
    {
        $domainKeywords = [
            'legal' => ['liability', 'indemnity', 'warranty', 'governing', 'jurisdiction', 'arbitration', 'termination', 'breach', 'confidentiality'],
            'technical' => ['software', 'code', 'database', 'api', 'server', 'application', 'technology', 'system', 'architecture', 'blockchain'],
            'schedule' => ['time', 'schedule', 'daily', 'routine', 'hour', 'morning', 'evening', 'activity', 'timeline', 'milestone'],
            'roles' => ['role', 'assigned', 'team', 'member', 'duties', 'responsibilities', 'lead', 'management', 'head'],
            'financial' => ['fee', 'payment', 'invoice', 'amount', 'dollar', 'budget', 'revenue', 'expense', 'cost'],
        ];

        $matchedDomainsA = [];
        $matchedDomainsB = [];

        foreach ($domainKeywords as $dom => $words) {
            foreach ($words as $w) {
                if (str_contains($lowerA, $w)) {
                    $matchedDomainsA[$dom] = true;
                    break;
                }
            }
            foreach ($words as $w) {
                if (str_contains($lowerB, $w)) {
                    $matchedDomainsB[$dom] = true;
                    break;
                }
            }
        }

        $shared = count(array_intersect_key($matchedDomainsA, $matchedDomainsB));
        $total = count(array_unique(array_merge(array_keys($matchedDomainsA), array_keys($matchedDomainsB))));

        return $total > 0 ? ($shared / $total) : 0.0;
    }

    protected function calculateSemanticSimilarity(string $typeA, string $typeB, float $topicSimilarity): float
    {
        if ($typeA === $typeB && $typeA !== 'general') {
            return max(0.85, $topicSimilarity);
        }

        return max(0.05, $topicSimilarity * 0.7);
    }

    protected function extractMeaningfulSimilarities(
        string $typeA,
        string $typeB,
        string $rawA,
        string $rawB,
        string $lowerA,
        string $lowerB
    ): array {
        $sims = [];

        if ($typeA === $typeB && $typeA !== 'general') {
            $sims[] = "Both documents belong to the same functional category (" . ucfirst(str_replace('_', ' ', $typeA)) . ").";
        }

        // Dynamically extract shared proper nouns / names between documents without hardcoding
        preg_match_all('/\b[A-Z][a-z]{2,}(?:\s+[A-Z][a-z]{2,})*\b/', $rawA, $matchesA);
        preg_match_all('/\b[A-Z][a-z]{2,}(?:\s+[A-Z][a-z]{2,})*\b/', $rawB, $matchesB);

        $ignoreWords = ['The', 'This', 'That', 'Document', 'Section', 'Page', 'Date', 'Time', 'Total', 'All', 'Both'];
        $cleanNamesA = array_diff(array_unique($matchesA[0] ?? []), $ignoreWords);
        $cleanNamesB = array_diff(array_unique($matchesB[0] ?? []), $ignoreWords);
        $sharedNames = array_values(array_intersect($cleanNamesA, $cleanNamesB));

        if (!empty($sharedNames)) {
            $sampleNames = array_slice($sharedNames, 0, 3);
            $sims[] = "Both documents reference shared names/entities: " . implode(', ', $sampleNames) . ".";
        }

        if (empty($sims)) {
            $sims[] = "No significant structural or content overlap was identified between the two documents.";
        }

        return $sims;
    }

    protected function extractMeaningfulDifferences(
        string $typeA,
        string $typeB,
        array $sectionsA,
        array $sectionsB,
        string $nameA,
        string $nameB
    ): array {
        $diffs = [];

        $purposeA = $this->determinePurpose($typeA, '', $nameA);
        $purposeB = $this->determinePurpose($typeB, '', $nameB);

        if ($typeA !== $typeB) {
            $diffs[] = "Primary Purpose: {$nameA} focuses on {$purposeA}, whereas {$nameB} focuses on {$purposeB}.";
        }

        $topA = !empty($sectionsA) ? $sectionsA[0]->title : 'primary content';
        $topB = !empty($sectionsB) ? $sectionsB[0]->title : 'primary content';

        $diffs[] = "Focus Areas: {$nameA} organizes information under '{$topA}', while {$nameB} covers '{$topB}'.";

        return $diffs;
    }

    protected function extractUniqueKeyPoints(array $sections, string $otherText): array
    {
        $unique = [];
        foreach ($sections as $s) {
            $titleLower = strtolower(trim($s->title));
            if (!empty($titleLower) && !str_contains($otherText, $titleLower)) {
                $unique[] = $s->title . ": " . $this->createExcerpt($s->content, 20);
            }
        }

        if (empty($unique) && !empty($sections)) {
            $unique[] = $sections[0]->title . ": " . $this->createExcerpt($sections[0]->content, 20);
        }

        return array_slice($unique, 0, 4);
    }

    protected function createExcerpt(string $content, int $maxWords = 25): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $maxWords) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . '...';
    }
}
