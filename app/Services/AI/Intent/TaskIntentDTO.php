<?php

namespace App\Services\AI\Intent;

class TaskIntentDTO
{
    /**
     * @param string $intent e.g. section_specific, clause_diff, numerical_comparison, missing_clause, contradiction_check, role_and_task_extraction, summarization, general_qa
     * @param string $rawQuery
     * @param array<string> $targetSections e.g. ['4', 'section 4']
     * @param array<string> $targetClauses e.g. ['termination', 'payment terms', 'liability cap']
     * @param string $targetSlot 'A', 'B', or 'both'
     * @param string $scope 'single_section', 'clause', 'whole_document', 'cross_document'
     * @param array<string> $constraints e.g. ['strict_section_only', 'exact_numbers', 'negative_if_missing']
     * @param array<string> $keywords
     */
    public function __construct(
        public string $intent,
        public string $rawQuery,
        public array $targetSections = [],
        public array $targetClauses = [],
        public string $targetSlot = 'both',
        public string $scope = 'cross_document',
        public array $constraints = [],
        public array $keywords = []
    ) {}

    public function __get(string $name): mixed
    {
        if ($name === 'type') {
            return $this->intent;
        }
        return null;
    }

    public function isSectionSpecific(): bool
    {
        return $this->intent === 'section_specific' || !empty($this->targetSections);
    }

    public function isNumerical(): bool
    {
        return $this->intent === 'numerical_comparison';
    }

    public function isClauseDiff(): bool
    {
        return $this->intent === 'clause_diff' || !empty($this->targetClauses);
    }

    public function isSummarization(): bool
    {
        return $this->intent === 'summarization';
    }

    public function isRoleOrTaskExtraction(): bool
    {
        return $this->intent === 'role_and_task_extraction';
    }

    public function isClauseExplanation(): bool
    {
        return $this->intent === 'clause_explanation';
    }

    public function isRiskAnalysis(): bool
    {
        return $this->intent === 'risk_analysis';
    }

    public function isMultiDocSummary(): bool
    {
        return $this->intent === 'multi_document_summary';
    }

    public function isGeneralChat(): bool
    {
        return $this->intent === 'general_chat';
    }

    public function hasConstraint(string $constraint): bool
    {
        return in_array($constraint, $this->constraints, true);
    }

    public function isFollowUp(): bool
    {
        return $this->intent === 'follow_up_refinement';
    }

    public function isDocumentComparison(): bool
    {
        return $this->intent === 'document_comparison' || $this->intent === 'clause_diff';
    }

    public function isAuditReportRequest(): bool
    {
        return in_array('formal_verification_report', $this->constraints, true);
    }

    public function isSimpleComparison(): bool
    {
        return in_array('simple_comparison_summary', $this->constraints, true) || !in_array('formal_verification_report', $this->constraints, true);
    }

    public function isDirectRoleQuestion(): bool
    {
        return in_array('direct_answer_first', $this->constraints, true);
    }

    public function isMemberCountQuestion(): bool
    {
        return in_array('count_members', $this->constraints, true);
    }

    public function isJsonOnly(): bool
    {
        return in_array('json_only', $this->constraints);
    }

    public function isYesNoOnly(): bool
    {
        return in_array('yes_no_only', $this->constraints);
    }

    public function isOnlyNames(): bool
    {
        return in_array('only_names', $this->constraints);
    }

    public function isOnlyDates(): bool
    {
        return in_array('only_dates', $this->constraints);
    }

    public function isOnlyAmount(): bool
    {
        return in_array('only_amount', $this->constraints);
    }

    public function isOnlyNumerical(): bool
    {
        return in_array('only_numerical', $this->constraints);
    }

    public function isOnlyResponsibilities(): bool
    {
        return in_array('only_responsibilities', $this->constraints);
    }

    public function forbidsRecommendations(): bool
    {
        return in_array('forbid_recommendations', $this->constraints);
    }

    public function hasSingleDocTarget(): bool
    {
        return $this->targetSlot === 'A' || $this->targetSlot === 'B';
    }
}
