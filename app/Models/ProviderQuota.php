<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_name',
        'model',
        'date',
        'requests_used_today',
        'input_tokens_used_today',
        'output_tokens_used_today',
        'total_tokens_used_today',
        'daily_request_limit',
        'daily_token_limit',
        'requests_remaining',
        'tokens_remaining',
        'last_reset_at',
        'last_error_status',
        'last_error_message',
        'metadata',
    ];

    protected $casts = [
        'requests_used_today' => 'integer',
        'input_tokens_used_today' => 'integer',
        'output_tokens_used_today' => 'integer',
        'total_tokens_used_today' => 'integer',
        'daily_request_limit' => 'integer',
        'daily_token_limit' => 'integer',
        'requests_remaining' => 'integer',
        'tokens_remaining' => 'integer',
        'last_reset_at' => 'datetime',
        'last_error_status' => 'integer',
        'metadata' => 'array',
    ];
}
