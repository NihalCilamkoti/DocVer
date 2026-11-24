<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIEvaluationRun extends Model
{
    use HasFactory;

    protected $table = 'ai_evaluation_runs';

    protected $fillable = [
        'run_id',
        'provider',
        'model',
        'category',
        'total_tests',
        'passed_tests',
        'failed_tests',
        'overall_accuracy',
        'instruction_accuracy',
        'citation_accuracy',
        'grounding_accuracy',
        'hallucination_rate',
        'avg_latency_ms',
        'total_tokens',
        'duration_ms',
        'status',
        'metrics',
    ];

    protected $casts = [
        'overall_accuracy' => 'float',
        'instruction_accuracy' => 'float',
        'citation_accuracy' => 'float',
        'grounding_accuracy' => 'float',
        'hallucination_rate' => 'float',
        'metrics' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(AIEvaluationResult::class, 'run_id', 'run_id');
    }
}
