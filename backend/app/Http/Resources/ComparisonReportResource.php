<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComparisonReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $findings = $this->whenLoaded('findings');
        $findingsCollection = FindingResource::collection($findings);

        $clauses = [];
        $numerical = [];
        $contradictions = [];

        if ($this->relationLoaded('findings') && $this->findings) {
            foreach ($this->findings as $f) {
                $type = $f->type?->value ?? (string)$f->type;
                if (in_array($type, ['numerical', 'financial'])) {
                    $numerical[] = new FindingResource($f);
                } elseif ($type === 'contradiction') {
                    $contradictions[] = new FindingResource($f);
                } else {
                    $clauses[] = new FindingResource($f);
                }
            }
        }

        $overallScore = $this->comparisonJob?->similarity_score;
        if ($overallScore === null) {
            $total = ($this->matching_clauses_count ?? 0) + ($this->total_differences ?? 0);
            $overallScore = $total > 0 ? (int) round((($this->matching_clauses_count ?? 0) / $total) * 100) : 100;
        }

        return [
            'id' => $this->id,
            'comparison_job_id' => $this->comparison_job_id,
            'summary' => [
                'overall_similarity_score' => $overallScore,
                'total_differences' => $this->total_differences,
                'high_risk_count' => $this->high_risk_count,
                'medium_risk_count' => $this->medium_risk_count,
                'low_risk_count' => $this->low_risk_count,
                'matching_clauses_count' => $this->matching_clauses_count,
                'missing_clauses_count' => $this->missing_clauses_count,
                'modified_clauses_count' => $this->modified_clauses_count,
                'numerical_discrepancies_count' => $this->numerical_discrepancies_count,
                'contradictions_count' => $this->contradictions_count,
            ],
            'executive_summary' => $this->executive_summary,
            'clauses' => $clauses,
            'numerical_discrepancies' => $numerical,
            'contradictions' => $contradictions,
            'findings' => $findingsCollection,
            'generated_at' => $this->generated_at?->toISOString(),
        ];
    }
}
