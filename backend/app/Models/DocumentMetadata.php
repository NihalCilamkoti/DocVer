<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMetadata extends Model
{
    use HasFactory;

    protected $table = 'document_metadata';

    protected $fillable = [
        'workspace_id',
        'comparison_job_id',
        'slot',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'page_count',
        'checksum_sha256',
        'is_sanitized',
        'expires_at',
        'deleted_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'page_count' => 'integer',
        'is_sanitized' => 'boolean',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function comparisonJob(): BelongsTo
    {
        return $this->belongsTo(ComparisonJob::class);
    }
}
