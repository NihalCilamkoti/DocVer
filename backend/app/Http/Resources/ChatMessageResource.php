<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'content' => $this->content,
            'citations' => $this->citations,
            'suggested_follow_ups' => $this->follow_ups,
            'follow_ups' => $this->follow_ups,
            'is_quota_error' => $this->resource->is_quota_error ?? false,
            'quota_details' => $this->resource->quota_details ?? null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
