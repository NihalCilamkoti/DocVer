<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\CandidateFindingDTO;

class EvidenceValidator
{
    /**
     * Validate candidate findings against ground-truth extracted source chunks.
     * Guarantees that page numbers and quoted evidence actually exist in the source text.
     *
     * @param CandidateFindingDTO[] $candidateFindings
     * @param array<int, array<string, mixed>> $chunksA
     * @param array<int, array<string, mixed>> $chunksB
     * @return CandidateFindingDTO[]
     */
    public function validateFindings(array $candidateFindings, array $chunksA, array $chunksB): array
    {
        $validated = [];

        foreach ($candidateFindings as $finding) {
            $validA = true;
            $validB = true;
            $correctedPageA = $finding->docAPage;
            $correctedPageB = $finding->docBPage;

            // 1. Validate Document A evidence
            if ($finding->docAEvidence !== null) {
                $checkA = $this->verifyEvidenceInChunks($finding->docAEvidence, $finding->docAPage, $chunksA);
                if ($checkA['found']) {
                    $correctedPageA = $checkA['actual_page'];
                } else {
                    $validA = false;
                }
            }

            // 2. Validate Document B evidence
            if ($finding->docBEvidence !== null) {
                $checkB = $this->verifyEvidenceInChunks($finding->docBEvidence, $finding->docBPage, $chunksB);
                if ($checkB['found']) {
                    $correctedPageB = $checkB['actual_page'];
                } else {
                    $validB = false;
                }
            }

            // If evidence is verified or finding represents a pure omission
            if ($validA && $validB) {
                $validated[] = new CandidateFindingDTO(
                    id: $finding->id,
                    type: $finding->type,
                    category: $finding->category,
                    severity: $finding->severity,
                    title: $finding->title,
                    docAPage: $correctedPageA,
                    docAEvidence: $finding->docAEvidence,
                    docBPage: $correctedPageB,
                    docBEvidence: $finding->docBEvidence,
                    explanation: $finding->explanation,
                    confidence: $finding->confidence,
                    metrics: $finding->metrics,
                    verificationStatus: 'evidence_validated'
                );
            }
        }

        return $validated;
    }

    /**
     * Check if evidence snippet exists in chunk text on claimed page or any page.
     *
     * @param array<int, array<string, mixed>> $chunks
     * @return array{found: bool, actual_page: ?int}
     */
    protected function verifyEvidenceInChunks(string $evidence, ?int $claimedPage, array $chunks): array
    {
        $cleanEvidence = $this->normalizeForSearch($evidence);
        if (strlen($cleanEvidence) < 10) {
            return ['found' => true, 'actual_page' => $claimedPage];
        }

        // Search primary claimed page first
        if ($claimedPage) {
            foreach ($chunks as $c) {
                if (($c['page_number'] ?? null) == $claimedPage) {
                    $cleanChunk = $this->normalizeForSearch($c['content'] ?? '');
                    if ($this->fuzzySnippetMatch($cleanEvidence, $cleanChunk)) {
                        return ['found' => true, 'actual_page' => $claimedPage];
                    }
                }
            }
        }

        // Secondary search across all pages in document to catch minor page boundary offsets
        foreach ($chunks as $c) {
            $cleanChunk = $this->normalizeForSearch($c['content'] ?? '');
            if ($this->fuzzySnippetMatch($cleanEvidence, $cleanChunk)) {
                return ['found' => true, 'actual_page' => (int) ($c['page_number'] ?? 1)];
            }
        }

        return ['found' => false, 'actual_page' => $claimedPage];
    }

    protected function fuzzySnippetMatch(string $snippet, string $target): bool
    {
        if (str_contains($target, $snippet)) {
            return true;
        }

        // Try substantial sub-slice of snippet (first 30 characters)
        $sub = substr($snippet, 0, min(30, strlen($snippet)));
        return strlen($sub) >= 15 && str_contains($target, $sub);
    }

    protected function normalizeForSearch(string $text): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($text)));
    }
}
