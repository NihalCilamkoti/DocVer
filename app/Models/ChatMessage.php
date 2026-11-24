<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'comparison_job_id',
        'user_id',
        'sender',
        'content',
        'citations',
        'follow_ups',
    ];

    protected $casts = [
        'citations' => 'array',
        'follow_ups' => 'array',
    ];

    public function comparisonJob(): BelongsTo
    {
        return $this->belongsTo(ComparisonJob::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
