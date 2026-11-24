<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComparisonReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comparison_job_id' => $this->comparison_job_id,
            'summary' => [
                'overall_similarity_score' => $this->comparisonJob?->similarity_score ?? 84,
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
            'findings' => FindingResource::collection($this->whenLoaded('findings')),
            'generated_at' => $this->generated_at?->toISOString(),
        ];
    }
}
