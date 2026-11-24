<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentMetadataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'name' => $this->original_name,
            'size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'page_count' => $this->page_count,
            'checksum' => $this->checksum_sha256,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
