<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\CandidateFindingDTO;

class NumericalComparisonService
{
    /**
     * Map currency symbols and codes to standardized ISO currency codes.
     */
    protected const CURRENCY_MAP = [
        '$'   => 'USD',
        'usd' => 'USD',
        '₹'   => 'INR',
        'inr' => 'INR',
        'rs.' => 'INR',
        'rs'  => 'INR',
        '€'   => 'EUR',
        'eur' => 'EUR',
        '£'   => 'GBP',
        'gbp' => 'GBP',
        '¥'   => 'JPY',
        'jpy' => 'JPY',
    ];

    /**
     * Extract and normalize numerical/financial entities, then detect substantive discrepancies.
     *
     * @return CandidateFindingDTO[]
     */
    public function compareNumerics(AnalyzedSectionDTO $secA, AnalyzedSectionDTO $secB): array
    {
        $entitiesA = $this->extractNumericalEntities($secA->content);
        $entitiesB = $this->extractNumericalEntities($secB->content);

        $findings = [];
        $matchedBIndices = [];

        foreach ($entitiesA as $idxA => $itemA) {
            $matchingB = null;
            $bestBIndex = null;

            // Search for candidate counterpart in B (same unit/type or closest position)
            foreach ($entitiesB as $idxB => $itemB) {
                if (in_array($idxB, $matchedBIndices)) continue;

                if ($itemA['unit'] === $itemB['unit'] || $itemA['type'] === $itemB['type']) {
                    $matchingB = $itemB;
                    $bestBIndex = $idxB;
                    break;
                }
            }

            if ($matchingB && $bestBIndex !== null) {
                $matchedBIndices[] = $bestBIndex;

                // Compare normalized values.
                // If ₹10,000 vs INR 10,000: both normalized to 10000.0 INR -> NOT a discrepancy!
                if ($itemA['normalized_key'] !== $matchingB['normalized_key']) {
                    $diffPercent = $this->calculateVariancePercent($itemA['value'], $matchingB['value']);
                    $severity = abs($diffPercent) >= 20.0 ? 'high' : 'medium';

                    $title = $itemA['type'] === 'currency'
                        ? "Financial Discrepancy in {$secA->title}"
                        : "Numerical Discrepancy in {$secA->title}";

                    $findings[] = new CandidateFindingDTO(
                        id: "num_diff_" . uniqid(),
                        type: $itemA['type'] === 'currency' ? 'financial' : 'numerical',
                        category: $secA->category,
                        severity: $severity,
                        title: $title,
                        docAPage: $secA->pageStart,
                        docAEvidence: $itemA['raw_snippet'],
                        docBPage: $secB->pageStart,
                        docBEvidence: $matchingB['raw_snippet'],
                        explanation: "Value changed from {$itemA['raw']} in Document A to {$matchingB['raw']} in Document B (" . ($diffPercent >= 0 ? "+{$diffPercent}%" : "{$diffPercent}%") . ").",
                        confidence: 0.98,
                        metrics: [
                            'value_a' => $itemA['raw'],
                            'value_b' => $matchingB['raw'],
                            'normalized_a' => $itemA['normalized_key'],
                            'normalized_b' => $matchingB['normalized_key'],
                            'variance_percent' => $diffPercent,
                        ],
                        verificationStatus: 'evidence_validated'
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * Extract financial currencies, percentages, and quantities with sentence evidence.
     *
     * @return array<int, array{type: string, value: float, unit: string, raw: string, normalized_key: string, raw_snippet: string}>
     */
    public function extractNumericalEntities(string $text): array
    {
        $entities = [];

        // 1. Currency Regex: e.g. $1,000,000 | USD 50,000 | ₹10,000 | 10000 INR
        $currencyRegex = '/(?:([$₹€£¥]|(?:USD|INR|EUR|GBP|JPY|Rs\.?))\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]+)?(?:\s*(?:million|billion|k))?)|([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]+)?(?:\s*(?:million|billion|k))?)\s*([$₹€£¥]|(?:USD|INR|EUR|GBP|JPY|Rs\.?)))/iu';

        if (preg_match_all($currencyRegex, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $rawMatch = $m[0][0];
                $offset = $m[0][1];

                $symbol = ! empty($m[1][0]) ? $m[1][0] : ($m[4][0] ?? 'USD');
                $numStr = ! empty($m[2][0]) ? $m[2][0] : ($m[3][0] ?? '0');

                $normalizedUnit = self::CURRENCY_MAP[strtolower(trim($symbol))] ?? 'USD';
                $numericValue = $this->parseNumericValue($numStr);

                $entities[] = [
                    'type' => 'currency',
                    'value' => $numericValue,
                    'unit' => $normalizedUnit,
                    'raw' => $rawMatch,
                    'normalized_key' => "{$numericValue} {$normalizedUnit}",
                    'raw_snippet' => $this->extractSurroundingSentence($text, $offset, strlen($rawMatch)),
                ];
            }
        }

        // 2. Percentage Regex: e.g. 15% | 15.5 percent
        $percentRegex = '/([0-9]+(?:\.[0-9]+)?)\s*(%|percent)/i';
        if (preg_match_all($percentRegex, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $rawMatch = $m[0][0];
                $offset = $m[0][1];
                $val = (float) $m[1][0];

                $entities[] = [
                    'type' => 'percentage',
                    'value' => $val,
                    'unit' => '%',
                    'raw' => $rawMatch,
                    'normalized_key' => "{$val}%",
                    'raw_snippet' => $this->extractSurroundingSentence($text, $offset, strlen($rawMatch)),
                ];
            }
        }

        return $entities;
    }

    protected function parseNumericValue(string $str): float
    {
        $clean = strtolower(str_replace(',', '', trim($str)));
        $multiplier = 1.0;

        if (str_contains($clean, 'million')) {
            $multiplier = 1000000.0;
            $clean = str_replace('million', '', $clean);
        } elseif (str_contains($clean, 'billion')) {
            $multiplier = 1000000000.0;
            $clean = str_replace('billion', '', $clean);
        } elseif (str_contains($clean, 'k')) {
            $multiplier = 1000.0;
            $clean = str_replace('k', '', $clean);
        }

        return (float) trim($clean) * $multiplier;
    }

    protected function calculateVariancePercent(float $valA, float $valB): float
    {
        if ($valA == 0.0) {
            return $valB == 0.0 ? 0.0 : 100.0;
        }

        return round((($valB - $valA) / $valA) * 100, 2);
    }

    protected function extractSurroundingSentence(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 60);
        $len = min(strlen($text) - $start, $length + 120);
        $snippet = substr($text, $start, $len);

        // Clean boundary to full words
        return trim(preg_replace('/\s+/', ' ', $snippet));
    }
}
