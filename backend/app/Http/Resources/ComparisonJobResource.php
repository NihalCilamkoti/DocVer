<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComparisonJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'status' => $this->status?->value ?? (string)$this->status,
            'similarity_score' => $this->similarity_score,
            'error_message' => $this->error_message,
            'document_a' => new DocumentMetadataResource($this->whenLoaded('documentA')),
            'document_b' => new DocumentMetadataResource($this->whenLoaded('documentB')),
            'report' => new ComparisonReportResource($this->whenLoaded('report')),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
