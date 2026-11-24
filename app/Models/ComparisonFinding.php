<?php

namespace App\Models;

use App\Enums\FindingType;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComparisonFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'comparison_report_id',
        'type',
        'risk_level',
        'category',
        'title',
        'doc_a_page',
        'doc_b_page',
        'explanation',
        'significance',
        'metrics',
        'order_index',
    ];

    protected $casts = [
        'type' => FindingType::class,
        'risk_level' => RiskLevel::class,
        'doc_a_page' => 'integer',
        'doc_b_page' => 'integer',
        'metrics' => 'array',
        'order_index' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ComparisonReport::class, 'comparison_report_id');
    }
}
