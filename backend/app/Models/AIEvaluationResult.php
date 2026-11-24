<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIEvaluationResult extends Model
{
    use HasFactory;

    protected $table = 'ai_evaluation_results';

    protected $fillable = [
        'run_id',
        'test_id',
        'category',
        'input',
        'expected_task',
        'expected_answer',
        'actual_output',
        'score',
        'passed',
        'failure_category',
        'latency_ms',
        'tokens_used',
        'provider',
        'model',
        'details',
    ];

    protected $casts = [
        'expected_answer' => 'array',
        'score' => 'float',
        'passed' => 'boolean',
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AIEvaluationRun::class, 'run_id', 'run_id');
    }
}
