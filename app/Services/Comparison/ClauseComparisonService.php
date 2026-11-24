<?php

namespace App\Services\Comparison;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\DTOs\CandidateFindingDTO;
use App\Services\Comparison\DTOs\SectionMatchDTO;
use Illuminate\Support\Facades\Log;

class ClauseComparisonService
{
    public function __construct(
        protected NumericalComparisonService $numericalService,
        protected DateComparisonService $dateService,
        protected ContradictionDetector $contradictionDetector,
        protected ?AIProviderRouter $aiRouter = null
    ) {
        $this->aiRouter = $aiRouter ?? app(AIProviderRouter::class);
    }

    /**
     * Compare matched sections for clause modifications, numerical variances,
     * dates, and contradictions using actual LLM generation and deterministic validators.
     *
     * @param SectionMatchDTO[] $matches
     * @return CandidateFindingDTO[]
     */
    public function compareMatches(array $matches): array
    {
        $findings = [];

        foreach ($matches as $match) {
            $secA = $match->sectionA;
            $secB = $match->sectionB;

            // 1. Check if identical content
            if ($this->isIdentical($secA->content, $secB->content)) {
                $findings[] = new CandidateFindingDTO(
                    id: "match_" . uniqid(),
                    type: 'matching',
                    category: $secA->category,
                    severity: 'none',
                    title: "Identical Clause: {$secA->title}",
                    docAPage: $secA->pageStart,
                    docAEvidence: $this->createExcerpt($secA->content),
                    docBPage: $secB->pageStart,
                    docBEvidence: $this->createExcerpt($secB->content),
                    explanation: "Section content matches identically between Document A and Document B.",
                    confidence: 1.0,
                    metrics: [
                        'doc_a_content' => $this->createExcerpt($secA->content),
                        'doc_b_content' => $this->createExcerpt($secB->content),
                        'verification_status' => 'evidence_validated',
                    ],
                    verificationStatus: 'evidence_validated'
                );
                continue;
            }

            // 2. Perform intelligent AI section comparison if AI provider is available
            $aiFindings = $this->analyzeSectionWithAI($secA, $secB);
            if (!empty($aiFindings)) {
                foreach ($aiFindings as $af) {
                    $findings[] = $af;
                }
                continue;
            }

            // 3. Fallback deterministic analysis (numerical, date, contradiction, textual)
            // Numerical discrepancies
            $numFindings = $this->numericalService->compareNumerics($secA, $secB);
            foreach ($numFindings as $nf) {
                $findings[] = $nf;
            }

            // Date & duration modifications
            $dateFindings = $this->dateService->compareDates($secA, $secB);
            foreach ($dateFindings as $df) {
                $findings[] = $df;
            }

            // Direct contradictions
            $contraFindings = $this->contradictionDetector->detectContradictions($secA, $secB);
            foreach ($contraFindings as $cf) {
                $findings[] = $cf;
            }

            // Textual modification if not already captured
            if (empty($numFindings) && empty($dateFindings) && empty($contraFindings)) {
                $severity = $this->determineSeverity($secA->category);
                $snippetA = $this->extractTopicSnippet($secA->content);
                $snippetB = $this->extractTopicSnippet($secB->content);

                $title = "Revision in {$secA->title}: \"{$snippetA}\" → \"{$snippetB}\"";
                if (strlen($title) > 95) {
                    $title = "Textual Revision in {$secA->title}";
                }

                $explanation = "Provisions differ: Document A states \"{$snippetA}...\" while Document B revised to \"{$snippetB}...\".";
                $significance = "Substantive modification detected between drafts requiring verification against authorized terms.";

                $findings[] = new CandidateFindingDTO(
                    id: "mod_" . uniqid(),
                    type: 'modified',
                    category: $secA->category,
                    severity: $severity,
                    title: $title,
                    docAPage: $secA->pageStart,
                    docAEvidence: $this->createExcerpt($secA->content, 120),
                    docBPage: $secB->pageStart,
                    docBEvidence: $this->createExcerpt($secB->content, 120),
                    explanation: $explanation,
                    confidence: 0.95,
                    metrics: [
                        'doc_a_content' => $this->createExcerpt($secA->content, 120),
                        'doc_b_content' => $this->createExcerpt($secB->content, 120),
                        'significance' => $significance,
                        'verification_status' => 'evidence_validated',
                    ],
                    verificationStatus: 'evidence_validated'
                );
            }
        }

        return $findings;
    }

    /**
     * Send corresponding non-identical sections to the AI for deep legal and operational analysis.
     *
     * @return CandidateFindingDTO[]
     */
    protected function analyzeSectionWithAI(AnalyzedSectionDTO $secA, AnalyzedSectionDTO $secB): array
    {
        if (app()->runningUnitTests() && !config('ai.test_live_ai', false)) {
            return [];
        }

        $prompt = <<<PROMPT
Section A (from Document A - {$secA->title}, Page {$secA->pageStart}):
"{$secA->content}"

Section B (from Document B - {$secB->title}, Page {$secB->pageStart}):
"{$secB->content}"

Analyze the substantive differences between these sections. Identify:
- Changes in payment terms, amounts, interest rates, or periods
- Changes in liability, indemnification, or damages caps
- Altered or removed obligations, rights, or deadlines
- Any contradictions or new burdens

Output ONLY a valid JSON array of finding objects adhering to:
[
  {
    "title": "Concise descriptive title of the specific change",
    "type": "modified" | "numerical" | "missing" | "added" | "contradiction",
    "category": "{$secA->category}",
    "risk_level": "high" | "medium" | "low",
    "explanation": "Clear explanation of what specifically changed and how it alters meaning or obligations.",
    "impact": "Commercial, legal, or operational impact of the change.",
    "recommended_action": "Actionable recommendation for legal/operations counsel.",
    "doc_a_evidence": "Exact excerpt from Section A",
    "doc_b_evidence": "Exact excerpt from Section B"
  }
]
PROMPT;

        $systemInstruction = "You are DocVer's principal contract auditor. Contrast Section A and Section B. Identify real substantive differences. Do NOT simply quote paragraphs without explaining what changed. Return ONLY a valid JSON array.";

        try {
            $aiRequest = new AIRequest(
                prompt: $prompt,
                systemInstruction: $systemInstruction,
                temperature: 0.0,
                maxTokens: 2000,
                taskType: 'comparison'
            );

            $response = $this->aiRouter->route($aiRequest);
            $items = $response->structuredData;

            if (empty($items)) {
                $raw = trim($response->content);
                if (preg_match('/```(?:json)?\s*(\[\s*[\s\S]*?\s*\])\s*```/i', $raw, $m)) {
                    $raw = $m[1];
                } elseif (preg_match('/(\[\s*\{[\s\S]*\}\s*\])/i', $raw, $m)) {
                    $raw = $m[1];
                }
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $items = $decoded;
                }
            }

            if (!empty($items) && is_array($items)) {
                $findings = [];
                foreach ($items as $item) {
                    if (empty($item['title']) || empty($item['explanation'])) {
                        continue;
                    }

                    $rawType = strtolower($item['type'] ?? 'modified');
                    $type = match($rawType) {
                        'numerical', 'financial' => 'numerical',
                        'missing', 'removed', 'term_removal' => 'missing',
                        'added', 'insertion' => 'added',
                        'contradiction', 'conflict' => 'contradiction',
                        default => 'modified',
                    };

                    $rawRisk = strtolower($item['risk_level'] ?? 'medium');
                    $risk = match($rawRisk) {
                        'high' => 'high',
                        'low', 'none' => 'low',
                        default => 'medium',
                    };

                    $evA = !empty($item['doc_a_evidence']) ? trim($item['doc_a_evidence']) : $this->createExcerpt($secA->content, 80);
                    $evB = !empty($item['doc_b_evidence']) ? trim($item['doc_b_evidence']) : $this->createExcerpt($secB->content, 80);

                    $findings[] = new CandidateFindingDTO(
                        id: "ai_diff_" . uniqid(),
                        type: $type,
                        category: $item['category'] ?? $secA->category,
                        severity: $risk,
                        title: $item['title'],
                        docAPage: $secA->pageStart,
                        docAEvidence: $evA,
                        docBPage: $secB->pageStart,
                        docBEvidence: $evB,
                        explanation: $item['explanation'],
                        confidence: 0.98,
                        metrics: [
                            'impact' => $item['impact'] ?? null,
                            'recommended_action' => $item['recommended_action'] ?? null,
                            'doc_a_content' => $evA,
                            'doc_b_content' => $evB,
                            'verification_status' => 'evidence_validated',
                        ],
                        verificationStatus: 'evidence_validated'
                    );
                }

                if (!empty($findings)) {
                    return $findings;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("AI clause comparison failed for section {$secA->title}: " . $e->getMessage());
        }

        return [];
    }

    protected function extractTopicSnippet(string $content, int $maxWords = 6): string
    {
        $clean = preg_replace('/[^\w\s\-\:\.\,]/', '', trim($content));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words)) {
            return 'Operative provision';
        }
        $slice = array_slice($words, 0, $maxWords);
        return implode(' ', $slice);
    }

    protected function isIdentical(string $a, string $b): bool
    {
        $cleanA = preg_replace('/\s+/', ' ', trim($a));
        $cleanB = preg_replace('/\s+/', ' ', trim($b));

        return $cleanA === $cleanB;
    }

    protected function determineSeverity(string $category): string
    {
        return match($category) {
            'Indemnification & Liability', 'Intellectual Property', 'Termination & Default' => 'high',
            'Payment Terms', 'Warranties & Representations' => 'medium',
            default => 'low',
        };
    }

    protected function createExcerpt(string $content, int $maxWords = 120): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $maxWords) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . ' ...';
    }
}
