<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderHealth extends Model
{
    use HasFactory;

    protected $table = 'provider_health';

    protected $fillable = [
        'provider_name',
        'status',
        'latency_ms',
        'last_checked_at',
        'error_details',
        'metadata',
    ];

    protected $casts = [
        'latency_ms' => 'integer',
        'last_checked_at' => 'datetime',
        'metadata' => 'array',
    ];
}
