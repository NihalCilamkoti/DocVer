<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan,
            'status' => $this->status,
            'max_file_size_mb' => $this->max_file_size_mb,
            'max_page_limit' => $this->max_page_limit,
            'retention_days' => $this->retention_days,
            'role' => $this->pivot?->role ?? ($this->owner_id === $request->user()?->id ? 'owner' : 'member'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
