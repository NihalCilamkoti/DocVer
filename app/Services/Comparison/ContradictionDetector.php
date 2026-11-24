<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\CandidateFindingDTO;

class ContradictionDetector
{
    /**
     * Paired contradiction triggers that represent legal polarity shifts.
     */
    protected const CONTRADICTION_PATTERNS = [
        [
            'patternA' => '/\bshall\s+(?:indemnify|defend|hold\s+harmless)\b/i',
            'patternB' => '/\b(?:shall\s+not|neither\s+party\s+shall)\s+(?:indemnify|be\s+liable)\b/i',
            'title' => 'Direct Contradiction in Indemnification Obligations',
            'severity' => 'high',
        ],
        [
            'patternA' => '/\bexclusive\s+(?:license|rights?|ownership)\b/i',
            'patternB' => '/\bnon-exclusive\s+(?:license|rights?)\b/i',
            'title' => 'Contradiction: Exclusivity Replaced with Non-Exclusive Rights',
            'severity' => 'high',
        ],
        [
            'patternA' => '/\bunlimited\s+liability\b/i',
            'patternB' => '/\b(?:limited|capped)\s+to\b/i',
            'title' => 'Contradiction: Unlimited Liability Capped',
            'severity' => 'high',
        ],
        [
            'patternA' => '/\bwithout\s+cause\b/i',
            'patternB' => '/\bfor\s+cause\s+only\b/i',
            'title' => 'Contradiction in Termination Rights (At-Will vs Cause Only)',
            'severity' => 'high',
        ],
        [
            'patternA' => '/\bwork[\s\-]for[\s\-]hire\b/i',
            'patternB' => '/\bvendor\s+retains?\s+(?:all\s+)?(?:ownership|ip)\b/i',
            'title' => 'Contradiction: Work-for-Hire Assignment Negated',
            'severity' => 'high',
        ],
    ];

    /**
     * Detect direct contradictions between matched sections.
     *
     * @return CandidateFindingDTO[]
     */
    public function detectContradictions(AnalyzedSectionDTO $secA, AnalyzedSectionDTO $secB): array
    {
        $findings = [];

        foreach (self::CONTRADICTION_PATTERNS as $rule) {
            $matchA = preg_match($rule['patternA'], $secA->content, $mA, PREG_OFFSET_CAPTURE);
            $matchB = preg_match($rule['patternB'], $secB->content, $mB, PREG_OFFSET_CAPTURE);

            // Also check inverse (patternB in A, patternA in B)
            $inverseMatchA = preg_match($rule['patternB'], $secA->content, $invA, PREG_OFFSET_CAPTURE);
            $inverseMatchB = preg_match($rule['patternA'], $secB->content, $invB, PREG_OFFSET_CAPTURE);

            if (($matchA && $matchB) || ($inverseMatchA && $inverseMatchB)) {
                $snippetA = $matchA ? $this->extractSnippet($secA->content, $mA[0][1], strlen($mA[0][0]))
                                    : $this->extractSnippet($secA->content, $invA[0][1], strlen($invA[0][0]));

                $snippetB = $matchB ? $this->extractSnippet($secB->content, $mB[0][1], strlen($mB[0][0]))
                                    : $this->extractSnippet($secB->content, $invB[0][1], strlen($invB[0][0]));

                $findings[] = new CandidateFindingDTO(
                    id: "contra_" . uniqid(),
                    type: 'contradiction',
                    category: $secA->category,
                    severity: $rule['severity'],
                    title: $rule['title'],
                    docAPage: $secA->pageStart,
                    docAEvidence: $snippetA,
                    docBPage: $secB->pageStart,
                    docBEvidence: $snippetB,
                    explanation: "Document A establishes one legal stance while Document B adopts a contradictory obligation.",
                    confidence: 0.97,
                    metrics: [
                        'rule' => $rule['title'],
                    ],
                    verificationStatus: 'evidence_validated'
                );
            }
        }

        return $findings;
    }

    protected function extractSnippet(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 40);
        $len = min(strlen($text) - $start, $length + 80);
        return trim(preg_replace('/\s+/', ' ', substr($text, $start, $len)));
    }
}
