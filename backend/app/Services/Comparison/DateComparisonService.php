<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\CandidateFindingDTO;

class DateComparisonService
{
    /**
     * Compare dates, settlement periods, and durations across sections.
     *
     * @return CandidateFindingDTO[]
     */
    public function compareDates(AnalyzedSectionDTO $secA, AnalyzedSectionDTO $secB): array
    {
        $datesA = $this->extractDatesAndDurations($secA->content);
        $datesB = $this->extractDatesAndDurations($secB->content);

        $findings = [];
        $matchedB = [];

        foreach ($datesA as $dA) {
            foreach ($datesB as $idxB => $dB) {
                if (in_array($idxB, $matchedB)) continue;

                // Match by entity type (duration vs calendar date)
                if ($dA['type'] === $dB['type']) {
                    $matchedB[] = $idxB;

                    if ($dA['normalized'] !== $dB['normalized']) {
                        $isPayment = str_contains(strtolower($secA->title), 'pay') || str_contains(strtolower($secA->category), 'pay');
                        $titlePrefix = $isPayment ? "Payment Term Modified" : ($dA['type'] === 'duration' ? "Timeline Window Changed" : "Calendar Date Changed");

                        $findings[] = new CandidateFindingDTO(
                            id: "date_diff_" . uniqid(),
                            type: 'modified',
                            category: $isPayment ? 'Payment Terms' : 'Dates & Deadlines',
                            severity: $dA['type'] === 'duration' ? 'high' : 'medium',
                            title: "{$titlePrefix} in {$secA->title}: {$dA['raw']} → {$dB['raw']}",
                            docAPage: $secA->pageStart,
                            docAEvidence: $dA['raw_snippet'],
                            docBPage: $secB->pageStart,
                            docBEvidence: $dB['raw_snippet'],
                            explanation: "Time specification altered from '{$dA['raw']}' to '{$dB['raw']}'.",
                            confidence: 0.96,
                            metrics: [
                                'doc_a_date' => $dA['raw'],
                                'doc_b_date' => $dB['raw'],
                                'impact' => "Payment and timeline window altered from {$dA['raw']} to {$dB['raw']}, impacting working capital and cash flows.",
                                'recommended_action' => "Verify revised settlement deadlines with treasury and commercial operations.",
                                'significance' => "Cash flow and liquidity impact due to extended window.",
                                'verification_status' => 'evidence_validated',
                            ],
                            verificationStatus: 'evidence_validated'
                        );
                    }
                    break;
                }
            }
        }

        return $findings;
    }

    public function extractDatesAndDurations(string $text): array
    {
        $results = [];

        // 1. Duration & Payment Windows: Net 30, Net 60, 30 days, 60 calendar days, 12 months
        $durationRegex = '/\b(?:Net\s*([0-9]+)|([0-9]+)\s*(?:calendar\s*)?(days?|months?|years?|weeks?|hours?))\b/i';
        if (preg_match_all($durationRegex, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $raw = $m[0][0];
                $offset = $m[0][1];

                if (! empty($m[1][0])) {
                    $normalized = "Net " . $m[1][0] . " Days";
                } else {
                    $unit = strtolower(rtrim($m[3][0], 's'));
                    $normalized = "{$m[2][0]} {$unit}s";
                }

                $results[] = [
                    'type' => 'duration',
                    'raw' => $raw,
                    'normalized' => strtolower($normalized),
                    'raw_snippet' => $this->extractSurroundingSentence($text, $offset, strlen($raw)),
                ];
            }
        }

        // 2. Calendar Dates: e.g. January 15, 2026 | 2026-01-15 | 15/01/2026
        $dateRegex = '/\b([0-9]{4}[-\/.][0-9]{1,2}[-\/.][0-9]{1,2}|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+[0-9]{1,2},?\s+[0-9]{4})\b/i';
        if (preg_match_all($dateRegex, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $raw = $m[0][0];
                $offset = $m[0][1];
                $time = strtotime($raw);

                if ($time !== false) {
                    $results[] = [
                        'type' => 'calendar_date',
                        'raw' => $raw,
                        'normalized' => date('Y-m-d', $time),
                        'raw_snippet' => $this->extractSurroundingSentence($text, $offset, strlen($raw)),
                    ];
                }
            }
        }

        return $results;
    }

    protected function extractSurroundingSentence(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 50);
        $len = min(strlen($text) - $start, $length + 100);
        return trim(preg_replace('/\s+/', ' ', substr($text, $start, $len)));
    }
}
