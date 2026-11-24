<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default / Primary Provider
    |--------------------------------------------------------------------------
    */
    'primary_provider' => env('AI_PRIMARY_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Failover Order (Default sequence: Gemini → Groq → OpenRouter)
    |--------------------------------------------------------------------------
    */
    'fallback_order' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('AI_FALLBACK_ORDER', env('AI_FALLBACK_PROVIDERS', 'gemini,groq,openrouter')))
    ))),

    'enable_automatic_failover' => filter_var(env('AI_ENABLE_AUTOMATIC_FAILOVER', true), FILTER_VALIDATE_BOOLEAN),
    'daily_quota_safety_margin' => (int) env('AI_DAILY_QUOTA_SAFETY_MARGIN', 5), // 5% safety margin
    'max_retries' => (int) env('AI_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configurations (Three Providers)
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'gemini' => [
            'name' => 'Google Gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'default_model' => env('GEMINI_MODEL', env('GEMINI_DEFAULT_MODEL', 'gemini-3.8-flash')),
            'reasoning_model' => env('GEMINI_REASONING_MODEL', 'gemini-3.8-flash'),
            'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 60),
            'priority' => 1,
            'enabled' => env('GEMINI_ENABLED', true),
            'max_context_tokens' => 1000000,
            // Cost per 1M tokens in USD ($0.075 input / $0.30 output for Flash)
            'cost_input_per_million' => 0.075,
            'cost_output_per_million' => 0.30,
            // Rate limits (Estimated/Configurable default)
            'default_rpm' => 15,
            'default_rpd' => 1500,
            'default_tpm' => 1000000,
            'default_tpd' => null,
        ],

        'groq' => [
            'name' => 'Groq',
            'api_key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'default_model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'fast_model' => env('GROQ_FAST_MODEL', 'openai/gpt-oss-20b'),
            'reasoning_model' => env('GROQ_REASONING_MODEL', 'openai/gpt-oss-120b'),
            'timeout_seconds' => (int) env('GROQ_TIMEOUT_SECONDS', 45),
            'priority' => 2,
            'enabled' => env('GROQ_ENABLED', true),
            'max_context_tokens' => 128000,
            // Cost per 1M tokens in USD
            'cost_input_per_million' => 0.59,
            'cost_output_per_million' => 0.79,
            // Rate limits (Groq free tier defaults)
            'default_rpm' => 30,
            'default_rpd' => 14400,
            'default_tpm' => 6000,
            'default_tpd' => 500000,
        ],

        'openrouter' => [
            'name' => 'OpenRouter',
            'api_key' => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'default_model' => env('OPENROUTER_MODEL', env('OPENROUTER_DEFAULT_MODEL', 'anthropic/claude-3.5-sonnet')),
            'reasoning_model' => env('OPENROUTER_REASONING_MODEL', 'openai/gpt-4o'),
            'timeout_seconds' => (int) env('OPENROUTER_TIMEOUT_SECONDS', 60),
            'priority' => 3,
            'enabled' => env('OPENROUTER_ENABLED', true),
            'max_context_tokens' => 200000,
            // Cost per 1M tokens in USD
            'cost_input_per_million' => 3.00,
            'cost_output_per_million' => 15.00,
            'default_rpm' => 200,
            'default_rpd' => null,
            'default_tpm' => null,
            'default_tpd' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Task-Specific Model Mappings (Smart Task Routing)
    |--------------------------------------------------------------------------
    */
    'task_models' => [
        'extraction' => [
            'gemini' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'groq' => env('GROQ_FAST_MODEL', 'openai/gpt-oss-20b'),
            'openrouter' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
        'comparison' => [
            'gemini' => env('GEMINI_REASONING_MODEL', 'gemini-3.8-flash'),
            'groq' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'openrouter' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
        'contradiction' => [
            'gemini' => env('GEMINI_REASONING_MODEL', 'gemini-3.8-flash'),
            'groq' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'openrouter' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
        'chat' => [
            'gemini' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'groq' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'openrouter' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
        'report' => [
            'gemini' => env('GEMINI_REASONING_MODEL', 'gemini-3.8-flash'),
            'groq' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            'openrouter' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
        'general' => [
            'gemini' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'groq' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'openrouter' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('AI_CB_COOLDOWN_SECONDS', 60),
        'half_open_success_threshold' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry & Backoff
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_retries' => (int) env('AI_MAX_RETRIES', 2),
        'initial_delay_ms' => 500,
        'max_delay_ms' => 3000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota Controls
    |--------------------------------------------------------------------------
    */
    'quotas' => [
        'default_user_daily_token_limit' => (int) env('AI_USER_DAILY_TOKEN_LIMIT', 500000),
        'default_workspace_monthly_token_limit' => (int) env('AI_WORKSPACE_MONTHLY_TOKEN_LIMIT', 10000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | User & Plan Quotas
    |--------------------------------------------------------------------------
    */
    'user_plans' => [
        'free' => [
            'daily_requests' => (int) env('AI_FREE_DAILY_REQUESTS', 20),
            'daily_tokens' => (int) env('AI_FREE_DAILY_TOKENS', 100000),
            'monthly_tokens' => (int) env('AI_FREE_MONTHLY_TOKENS', 2000000),
            'rate_limit_rpm' => 5,
        ],
        'professional' => [
            'daily_requests' => (int) env('AI_PRO_DAILY_REQUESTS', 200),
            'daily_tokens' => (int) env('AI_PRO_DAILY_TOKENS', 1000000),
            'monthly_tokens' => (int) env('AI_PRO_MONTHLY_TOKENS', 25000000),
            'rate_limit_rpm' => 30,
        ],
        'enterprise' => [
            'daily_requests' => (int) env('AI_ENTERPRISE_DAILY_REQUESTS', 2000),
            'daily_tokens' => (int) env('AI_ENTERPRISE_DAILY_TOKENS', 10000000),
            'monthly_tokens' => (int) env('AI_ENTERPRISE_MONTHLY_TOKENS', 200000000),
            'rate_limit_rpm' => 120,
        ],
    ],
];
