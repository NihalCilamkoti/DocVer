<?php

namespace App\Models;

use App\Enums\ComparisonStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ComparisonJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'user_id',
        'title',
        'status',
        'processing_progress',
        'completed_chunks',
        'last_completed_stage',
        'current_stage',
        'cancelled_at',
        'error_message',
        'similarity_score',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => ComparisonStatus::class,
        'processing_progress' => 'integer',
        'completed_chunks' => 'array',
        'similarity_score' => 'integer',
        'metadata' => 'array',
        'cancelled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ComparisonJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentMetadata::class);
    }

    public function documentA(): HasOne
    {
        return $this->hasOne(DocumentMetadata::class)->where('slot', 'A');
    }

    public function documentB(): HasOne
    {
        return $this->hasOne(DocumentMetadata::class)->where('slot', 'B');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ComparisonReport::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at', 'asc');
    }
}
