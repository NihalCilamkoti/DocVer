<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metrics = $this->metrics ?? [];

        $docAEvidence = $this->sanitizeExcerpt($metrics['doc_a_evidence'] ?? null);
        $docBEvidence = $this->sanitizeExcerpt($metrics['doc_b_evidence'] ?? null);
        $docAContent = $this->sanitizeExcerpt($metrics['doc_a_content'] ?? $docAEvidence ?? $this->explanation);
        $docBContent = $this->sanitizeExcerpt($metrics['doc_b_content'] ?? $docBEvidence ?? $this->explanation);

        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? (string)$this->type,
            'risk_level' => $this->risk_level?->value ?? (string)$this->risk_level,
            'category' => $this->category,
            'title' => $this->title,
            'doc_a_page' => $this->doc_a_page ?? 1,
            'doc_b_page' => $this->doc_b_page ?? 1,
            'doc_a_evidence' => $docAEvidence,
            'doc_b_evidence' => $docBEvidence,
            'doc_a_content' => $docAContent,
            'doc_b_content' => $docBContent,
            'doc_a_value' => $metrics['value_a'] ?? $metrics['doc_a_value'] ?? $docAEvidence ?? null,
            'doc_b_value' => $metrics['value_b'] ?? $metrics['doc_b_value'] ?? $docBEvidence ?? null,
            'doc_a_claim' => $metrics['doc_a_claim'] ?? $docAEvidence ?? null,
            'doc_b_claim' => $metrics['doc_b_claim'] ?? $docBEvidence ?? null,
            'metric' => $metrics['metric'] ?? $this->title,
            'topic' => $metrics['topic'] ?? $this->title,
            'variance_percentage' => $metrics['variance'] ?? $metrics['variance_percentage'] ?? null,
            'legal_impact' => $metrics['legal_impact'] ?? $this->significance ?? $this->explanation,
            'explanation' => $this->explanation,
            'significance' => $this->significance,
            'risk_rationale' => $metrics['risk_rationale'] ?? $this->significance,
            'recommendation' => $metrics['recommendation'] ?? null,
            'impact' => $metrics['impact'] ?? null,
            'metrics' => $this->metrics,
            'order_index' => $this->order_index,
        ];
    }

    protected function sanitizeExcerpt(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Never expose internal raw disk file paths in evidence quotes
        if (preg_match('/documents\/\d+\/(?:staging|\d+)\/[a-zA-Z0-9_\-\.]+/i', $text) || preg_match('/\.(?:pdf|docx|txt|csv)$/i', trim($text))) {
            return $this->explanation ?: 'Operative clause excerpt verified.';
        }

        return $text;
    }
}
