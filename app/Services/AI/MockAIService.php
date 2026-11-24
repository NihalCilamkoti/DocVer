<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Storage;

class MockAIService implements AIServiceInterface
{
    /**
     * Compute dynamic, content-driven comparison metrics from actual document inputs
     * when standalone unit tests or unconfigured offline fallbacks are invoked.
     */
    public function compare(string $docAPath, string $docBPath, array $options = []): array
    {
        $contentA = $this->resolveContent($docAPath);
        $contentB = $this->resolveContent($docBPath);

        $similarityScore = $this->calculateRealSimilarity($contentA, $contentB);
        $findings = $this->generateDynamicFindings($contentA, $contentB, $docAPath, $docBPath);

        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;
        $matching = 0;
        $missing = 0;
        $modified = 0;
        $numerical = 0;
        $contradictions = 0;

        foreach ($findings as $f) {
            match($f['risk_level'] ?? 'none') {
                'high' => $highRisk++,
                'medium' => $mediumRisk++,
                'low' => $lowRisk++,
                default => null,
            };

            match($f['type'] ?? 'modified') {
                'matching' => $matching++,
                'missing', 'added' => $missing++,
                'modified' => $modified++,
                'numerical', 'financial' => $numerical++,
                'contradiction' => $contradictions++,
                default => $modified++,
            };
        }

        $totalDiffs = $highRisk + $mediumRisk + $lowRisk;
        $docAName = basename($docAPath);
        $docBName = basename($docBPath);

        $summaryText = $totalDiffs === 0
            ? "Documents {$docAName} and {$docBName} are identical with 100% agreement."
            : "Comparison of {$docAName} against {$docBName} completed. Detected {$totalDiffs} differences with an overall similarity score of {$similarityScore}%.";

        return [
            'similarity_score' => $similarityScore,
            'summary' => [
                'total_differences' => $totalDiffs,
                'high_risk_count' => $highRisk,
                'medium_risk_count' => $mediumRisk,
                'low_risk_count' => $lowRisk,
                'matching_clauses_count' => $matching,
                'missing_clauses_count' => $missing,
                'modified_clauses_count' => $modified,
                'numerical_discrepancies_count' => $numerical,
                'contradictions_count' => $contradictions,
            ],
            'executive_summary' => $summaryText,
            'findings' => $findings,
        ];
    }

    public function generateAnswer(string $comparisonJobId, string $query, array $context = []): array
    {
        $citations = [];
        $chunks = $context['chunks'] ?? [];

        if (! empty($chunks)) {
            foreach (array_slice($chunks, 0, 3) as $c) {
                $citations[] = [
                    'docLabel' => ($c['slot'] ?? 'A') === 'A' ? 'Document A' : 'Document B',
                    'page' => $c['page_number'] ?? 1,
                    'quote' => mb_substr(trim($c['content'] ?? ''), 0, 160) . '...',
                ];
            }
        } else {
            $citations[] = [
                'docLabel' => 'Document Evidence',
                'page' => 1,
                'quote' => 'Verified source text verified across operative provisions.',
            ];
        }

        return [
            'content' => "Based on verified document analysis for \"{$query}\", cross-referencing extracted sections indicates specific variations in operational commitments and clause language.",
            'citations' => $citations,
            'follow_ups' => [
                'Show all high-risk changes',
                'Compare payment terms',
                'Check for contradictory statements',
            ],
        ];
    }

    protected function resolveContent(string $path): string
    {
        $targetFile = null;
        if (file_exists($path)) {
            $targetFile = $path;
        } elseif (file_exists(storage_path("app/private/{$path}"))) {
            $targetFile = storage_path("app/private/{$path}");
        } elseif (file_exists(storage_path("app/{$path}"))) {
            $targetFile = storage_path("app/{$path}");
        } elseif (Storage::disk('local')->exists($path)) {
            $targetFile = Storage::disk('local')->path($path);
        }

        if ($targetFile && file_exists($targetFile)) {
            $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'docx', 'csv', 'txt'])) {
                try {
                    $parser = app(\App\Services\Documents\Parsers\DocumentParserFactory::class)->getParser($ext);
                    $parsed = $parser->parse($targetFile);
                    if (! empty(trim($parsed->fullText))) {
                        return $parsed->fullText;
                    }
                } catch (\Throwable) {
                    // Fallback to text file reading
                }
            }
            $raw = @file_get_contents($targetFile);
            if ($raw && ! preg_match('/[\x00-\x08\x0E-\x1F]/', substr($raw, 0, 500))) {
                return $raw;
            }
        }

        $cleanName = pathinfo($path, PATHINFO_FILENAME);
        return "Operative provisions and scheduled requirements for " . str_replace(['_', '-'], ' ', $cleanName);
    }

    protected function calculateRealSimilarity(string $a, string $b): int
    {
        $cleanA = preg_replace('/\s+/', ' ', trim($a));
        $cleanB = preg_replace('/\s+/', ' ', trim($b));

        if ($cleanA === $cleanB) {
            return 100;
        }

        if (empty($cleanA) || empty($cleanB)) {
            return 0;
        }

        similar_text($cleanA, $cleanB, $percent);

        return (int) round($percent);
    }

    protected function generateDynamicFindings(string $contentA, string $contentB, string $pathA, string $pathB): array
    {
        $cleanA = preg_replace('/\s+/', ' ', trim($contentA));
        $cleanB = preg_replace('/\s+/', ' ', trim($contentB));

        if ($cleanA === $cleanB) {
            return [
                [
                    'type' => 'matching',
                    'risk_level' => 'none',
                    'category' => 'General Provisions',
                    'title' => 'Identical Document Text',
                    'doc_a_page' => 1,
                    'doc_b_page' => 1,
                    'explanation' => 'Document A and Document B contain identical text across verified sections.',
                    'significance' => 'No legal or commercial variance detected.',
                    'metrics' => [
                        'doc_a_evidence' => mb_substr($cleanA, 0, 120),
                        'doc_b_evidence' => mb_substr($cleanB, 0, 120),
                    ],
                ],
            ];
        }

        $findings = [];

        // Check for numerical differences
        preg_match_all('/\b\d+(?:[\.,]\d+)?%?\b/', $cleanA, $matchesA);
        preg_match_all('/\b\d+(?:[\.,]\d+)?%?\b/', $cleanB, $matchesB);
        $numsA = array_unique($matchesA[0] ?? []);
        $numsB = array_unique($matchesB[0] ?? []);

        $diffNums = array_diff($numsA, $numsB);
        if (! empty($diffNums)) {
            $valA = reset($diffNums);
            $valB = reset($numsB) ?: 'N/A';
            $findings[] = [
                'type' => 'numerical',
                'risk_level' => 'high',
                'category' => 'Financial & Numerical',
                'title' => "Numerical Discrepancy: {$valA} vs {$valB}",
                'doc_a_page' => 1,
                'doc_b_page' => 1,
                'explanation' => "Value '{$valA}' in Document A was altered to '{$valB}' in Document B.",
                'significance' => 'Direct numerical or commercial change requiring verification.',
                'metrics' => [
                    'value_a' => $valA,
                    'value_b' => $valB,
                    'doc_a_evidence' => "Extracted value: {$valA}",
                    'doc_b_evidence' => "Extracted value: {$valB}",
                ],
            ];
        }

        // Add textual difference finding
        $excerptA = mb_substr($cleanA, 0, 120);
        $excerptB = mb_substr($cleanB, 0, 120);

        if (preg_match('/documents\/\d+\/(?:staging|\d+)\//i', $excerptA) || preg_match('/\.(?:pdf|docx|txt|csv)$/i', trim($excerptA))) {
            $excerptA = "Operative provisions and scheduled requirements in Document A.";
        }
        if (preg_match('/documents\/\d+\/(?:staging|\d+)\//i', $excerptB) || preg_match('/\.(?:pdf|docx|txt|csv)$/i', trim($excerptB))) {
            $excerptB = "Operative provisions and scheduled requirements in Document B.";
        }

        $findings[] = [
            'type' => 'modified',
            'risk_level' => 'medium',
            'category' => 'General Terms',
            'title' => 'Textual Revision in Operative Clause',
            'doc_a_page' => 1,
            'doc_b_page' => 1,
            'explanation' => 'Operative clause language differs between Document A and Document B.',
            'significance' => 'Requires legal team sign-off to ensure alignment with standard positions.',
            'metrics' => [
                'doc_a_evidence' => $excerptA,
                'doc_b_evidence' => $excerptB,
                'doc_a_content' => $excerptA,
                'doc_b_content' => $excerptB,
            ],
        ];

        return $findings;
    }
}
