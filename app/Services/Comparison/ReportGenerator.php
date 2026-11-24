<?php

namespace App\Services\Comparison;

use App\Enums\FindingType;
use App\Enums\RiskLevel;
use App\Models\ComparisonFinding;
use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Services\Comparison\DTOs\CandidateFindingDTO;
use App\Services\Comparison\DTOs\ComparisonResultDTO;
use App\Services\Comparison\DTOs\DocumentRelationshipDTO;

class ReportGenerator
{
    /**
     * Generate structured comparison result and persist report & findings to database.
     *
     * @param CandidateFindingDTO[] $validatedFindings
     */
    public function generateAndPersist(
        ComparisonJob $job,
        array $validatedFindings,
        ?DocumentRelationshipDTO $relationship = null
    ): ComparisonResultDTO {
        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;
        $matching = 0;
        $missing = 0;
        $modified = 0;
        $numerical = 0;
        $contradictions = 0;

        $isGeneralOrDifferentPurposes = $relationship && $relationship->isDifferentPurposes();

        foreach ($validatedFindings as $f) {
            if ($isGeneralOrDifferentPurposes) {
                // Do not assign legal risk severity to general, ordinary document differences
                // Leave risk counts at 0 for different-purpose documents
            } else {
                match($f->severity) {
                    'high' => $highRisk++,
                    'medium' => $mediumRisk++,
                    'low' => $lowRisk++,
                    default => null,
                };
            }

            match($f->type) {
                'matching', 'unchanged' => $matching++,
                'missing', 'removed' => $missing++,
                'added' => $missing++,
                'modified' => $modified++,
                'numerical', 'financial' => $numerical++,
                'contradiction' => $contradictions++,
                'unique_a', 'unique_b', 'not_comparable' => null,
                default => null,
            };
        }

        $totalDiffs = $isGeneralOrDifferentPurposes
            ? count($validatedFindings)
            : ($highRisk + $mediumRisk + $lowRisk);

        // Calculate similarity score (0 to 100)
        if ($isGeneralOrDifferentPurposes) {
            // Textual overlap without artificial legal penalties
            $similarityScore = (int) round(($relationship->textualOverlap ?? $relationship->contentOverlap) * 100);
        } else {
            $totalEvaluated = $matching + $totalDiffs;
            if ($totalEvaluated === 0) {
                $similarityScore = 100;
            } else {
                $baseRatio = ($matching / $totalEvaluated) * 100;
                $penalty = ($highRisk * 6) + ($mediumRisk * 2) + ($lowRisk * 0.5);
                $similarityScore = (int) max(10, min(100, round($baseRatio - $penalty)));
            }
        }

        $focusPrompt = trim($job->metadata['focus_prompt'] ?? '');

        if ($isGeneralOrDifferentPurposes) {
            $executiveSummary = $this->buildGeneralComparisonExecutiveSummary($job, $relationship, $validatedFindings, $similarityScore);
        } else {
            $executiveSummary = $this->generateAIExecutiveSummary($job, $similarityScore, $validatedFindings)
                ?? $this->buildExecutiveSummary(
                    similarityScore: $similarityScore,
                    totalDiffs: $totalDiffs,
                    highRisk: $highRisk,
                    missing: $missing,
                    numerical: $numerical,
                    contradictions: $contradictions,
                    focusPrompt: $focusPrompt
                );
        }

        // Delete any existing report for this job (idempotent regeneration)
        $job->report()?->delete();

        // Persist ComparisonReport
        $report = ComparisonReport::create([
            'comparison_job_id' => $job->id,
            'total_differences' => $totalDiffs,
            'high_risk_count' => $highRisk,
            'medium_risk_count' => $mediumRisk,
            'low_risk_count' => $lowRisk,
            'matching_clauses_count' => $matching,
            'missing_clauses_count' => $missing,
            'modified_clauses_count' => $modified,
            'numerical_discrepancies_count' => $numerical,
            'contradictions_count' => $contradictions,
            'executive_summary' => $executiveSummary,
            'generated_at' => now(),
        ]);

        // Persist ComparisonFindings
        foreach ($validatedFindings as $index => $finding) {
            $findingType = match($finding->type) {
                'matching', 'unchanged' => FindingType::MATCHING,
                'missing', 'removed' => FindingType::MISSING,
                'added' => FindingType::ADDED,
                'unique_a' => FindingType::UNIQUE_A,
                'unique_b' => FindingType::UNIQUE_B,
                'not_comparable' => FindingType::NOT_COMPARABLE,
                'numerical' => FindingType::NUMERICAL,
                'financial' => FindingType::FINANCIAL,
                'contradiction' => FindingType::CONTRADICTION,
                default => FindingType::MODIFIED,
            };

            $riskLevel = match($finding->severity) {
                'high' => RiskLevel::HIGH,
                'medium' => RiskLevel::MEDIUM,
                'low' => RiskLevel::LOW,
                default => RiskLevel::NONE,
            };

            $docAEv = $finding->docAEvidence ?: $finding->title;
            $docBEv = $finding->docBEvidence ?: $finding->title;

            if (preg_match('/documents\/\d+\/(?:staging|\d+)\/[a-zA-Z0-9_\-\.]+/i', (string)$docAEv)) {
                $docAEv = $finding->explanation ?: $finding->title;
            }
            if (preg_match('/documents\/\d+\/(?:staging|\d+)\/[a-zA-Z0-9_\-\.]+/i', (string)$docBEv)) {
                $docBEv = $finding->explanation ?: $finding->title;
            }

            $significance = $finding->metrics['impact']
                ?? $finding->metrics['significance']
                ?? ($isGeneralOrDifferentPurposes
                    ? 'Information is unique to this document.'
                    : ($finding->severity === 'high' ? 'Material variance requiring review.' : 'Operational reference.'));

            $mergedMetrics = array_merge($finding->metrics ?? [], [
                'doc_a_evidence' => $docAEv,
                'doc_b_evidence' => $docBEv,
                'doc_a_content' => $docAEv,
                'doc_b_content' => $docBEv,
                'impact' => $significance,
                'recommended_action' => $finding->metrics['recommended_action'] ?? null,
                'confidence' => $finding->confidence,
                'verification_status' => $finding->verificationStatus,
            ]);

            ComparisonFinding::create([
                'comparison_report_id' => $report->id,
                'type' => $findingType,
                'risk_level' => $riskLevel,
                'category' => $finding->category,
                'title' => $finding->title,
                'doc_a_page' => $finding->docAPage,
                'doc_b_page' => $finding->docBPage,
                'explanation' => $finding->explanation,
                'significance' => $significance,
                'metrics' => $mergedMetrics,
                'order_index' => $index,
            ]);
        }

        $summary = [
            'total_differences' => $totalDiffs,
            'high_risk_count' => $highRisk,
            'medium_risk_count' => $mediumRisk,
            'low_risk_count' => $lowRisk,
            'matching_clauses_count' => $matching,
            'missing_clauses_count' => $missing,
            'modified_clauses_count' => $modified,
            'numerical_discrepancies_count' => $numerical,
            'contradictions_count' => $contradictions,
        ];

        return new ComparisonResultDTO(
            similarityScore: $similarityScore,
            executiveSummary: $executiveSummary,
            summary: $summary,
            findings: $validatedFindings
        );
    }

    protected function buildExecutiveSummary(
        int $similarityScore,
        int $totalDiffs,
        int $highRisk,
        int $missing,
        int $numerical,
        int $contradictions,
        string $focusPrompt = ''
    ): string {
        $parts = [];
        $parts[] = "Document comparison completed with a similarity score of {$similarityScore}%.";

        if ($totalDiffs === 0) {
            if (! empty($focusPrompt)) {
                $parts[] = "No provisions or discrepancies related to the requested focus ('{$focusPrompt}') were detected in either document.";
            } else {
                $parts[] = "No substantive discrepancies or material variations were detected between both document versions.";
            }
            return implode(' ', $parts);
        }

        $parts[] = "A total of {$totalDiffs} discrepancies were detected, including {$highRisk} high-risk variations.";

        if ($contradictions > 0) {
            $parts[] = "Identified {$contradictions} direct legal contradictions in obligations.";
        }
        if ($numerical > 0) {
            $parts[] = "Detected {$numerical} financial or numerical discrepancies.";
        }
        if ($missing > 0) {
            $parts[] = "Flagged {$missing} removed or added clauses requiring stakeholder review.";
        }

        return implode(' ', $parts);
    }

    protected function buildGeneralComparisonExecutiveSummary(
        ComparisonJob $job,
        DocumentRelationshipDTO $relationship,
        array $findings,
        int $similarityScore
    ): string {
        $docAName = $job->documentA?->original_name ?? 'Document A';
        $docBName = $job->documentB?->original_name ?? 'Document B';

        $simList = !empty($relationship->similarities)
            ? implode("\n", array_map(fn($s) => "- {$s}", $relationship->similarities))
            : "- No substantive structural overlap was identified between the two documents.";

        $diffList = !empty($relationship->differences)
            ? implode("\n", array_map(fn($d) => "- {$d}", $relationship->differences))
            : "- Document A and Document B address distinct topics.";

        $uniqueAList = !empty($relationship->uniqueA)
            ? implode("\n", array_map(fn($u) => "- {$u}", $relationship->uniqueA))
            : "- Primary provisions specific to {$docAName}.";

        $uniqueBList = !empty($relationship->uniqueB)
            ? implode("\n", array_map(fn($u) => "- {$u}", $relationship->uniqueB))
            : "- Specific content unique to {$docBName}.";

        $topicScore = (int) round(($relationship->topicSimilarity ?? 0.0) * 100);
        $structuralScore = (int) round(($relationship->structuralSimilarity ?? 0.0) * 100);
        $explanation = $relationship->similarityExplanation ?: "The {$similarityScore}% textual score reflects distinct vocabularies between {$docAName} and {$docBName}.";

        return <<<SUMMARY
### 1. Documents Compared
- **Document A:** {$docAName}
- **Document B:** {$docBName}

### 2. Overall Difference
{$relationship->relationshipSummary}

### 3. Main Differences
{$diffList}

### 4. Important Information Unique to Each Document
**Unique to {$docAName}:**
{$uniqueAList}

**Unique to {$docBName}:**
{$uniqueBList}

### 5. Similarities
{$simList}

### 6. Similarity Analysis
- **Textual Overlap:** {$similarityScore}%
- **Structural Heading Alignment:** {$structuralScore}%
- **Topic Domain Match:** {$topicScore}%
*Score Explanation:* {$explanation}

### 7. Important Conclusion
{$docAName} and {$docBName} serve entirely different purposes and are independent documents rather than revisions of one another.
SUMMARY;
    }

    protected function generateAIExecutiveSummary(ComparisonJob $job, int $similarityScore, array $findings): ?string
    {
        return app(\App\Services\AI\AIOrchestrator::class)->generateExecutiveReport($job, $similarityScore, $findings);
    }
}
