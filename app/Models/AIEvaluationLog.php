<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIEvaluationLog extends Model
{
    use HasFactory;

    protected $table = 'ai_evaluation_logs';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'comparison_job_id',
        'conversation_id',
        'task_type',
        'prompt_version',
        'input_prompt',
        'conversation_context',
        'document_references',
        'expected_characteristics',
        'actual_response',
        'provider',
        'model',
        'is_successful',
        'error_category',
        'grounding_score',
        'user_rating',
        'user_feedback',
        'duration_ms',
        'metadata',
    ];

    protected $casts = [
        'conversation_context' => 'array',
        'document_references' => 'array',
        'expected_characteristics' => 'array',
        'metadata' => 'array',
        'is_successful' => 'boolean',
        'grounding_score' => 'float',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function comparisonJob(): BelongsTo
    {
        return $this->belongsTo(ComparisonJob::class);
    }
}
