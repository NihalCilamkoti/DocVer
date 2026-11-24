<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComparisonReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'comparison_job_id',
        'total_differences',
        'high_risk_count',
        'medium_risk_count',
        'low_risk_count',
        'matching_clauses_count',
        'missing_clauses_count',
        'modified_clauses_count',
        'numerical_discrepancies_count',
        'contradictions_count',
        'executive_summary',
        'generated_at',
    ];

    protected $casts = [
        'total_differences' => 'integer',
        'high_risk_count' => 'integer',
        'medium_risk_count' => 'integer',
        'low_risk_count' => 'integer',
        'matching_clauses_count' => 'integer',
        'missing_clauses_count' => 'integer',
        'modified_clauses_count' => 'integer',
        'numerical_discrepancies_count' => 'integer',
        'contradictions_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function comparisonJob(): BelongsTo
    {
        return $this->belongsTo(ComparisonJob::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ComparisonFinding::class)->orderBy('order_index', 'asc');
    }
}
