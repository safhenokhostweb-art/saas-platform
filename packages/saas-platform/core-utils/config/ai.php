<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Provider
    |--------------------------------------------------------------------------
    | The default AI provider to use. Supported: "openai", "claude", "none"
    | Falls back automatically to the next healthy provider if unavailable.
    */
    'provider' => env('AI_PROVIDER', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Chain
    |--------------------------------------------------------------------------
    | Ordered list of providers tried when the active provider fails.
    */
    'fallback_chain' => array_filter(
        explode(',', env('AI_FALLBACK_CHAIN', 'claude,openai'))
    ),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Provider
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key'          => env('OPENAI_API_KEY'),
        'base_url'         => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model'            => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'model_advanced'   => env('OPENAI_MODEL_ADVANCED', 'gpt-4o'),
        'max_tokens'       => (int) env('OPENAI_MAX_TOKENS', 2000),
        'temperature'      => (float) env('OPENAI_TEMPERATURE', 0.7),
        'timeout'          => (int) env('OPENAI_TIMEOUT', 30),
        'enabled'          => (bool) env('OPENAI_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic Claude Provider
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key'          => env('CLAUDE_API_KEY'),
        'base_url'         => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
        'model'            => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
        'model_advanced'   => env('CLAUDE_MODEL_ADVANCED', 'claude-sonnet-4-6'),
        'max_tokens'       => (int) env('CLAUDE_MAX_TOKENS', 2000),
        'timeout'          => (int) env('CLAUDE_TIMEOUT', 30),
        'enabled'          => (bool) env('CLAUDE_ENABLED', false),
        'api_version'      => env('CLAUDE_API_VERSION', '2023-06-01'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global AI Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'forms_ai'          => (bool) env('AI_FEATURE_FORMS', true),
        'tickets_ai'        => (bool) env('AI_FEATURE_TICKETS', true),
        'submission_ai'     => (bool) env('AI_FEATURE_SUBMISSIONS', true),
        'search_ai'         => (bool) env('AI_FEATURE_SEARCH', true),
        'automation_ai'     => (bool) env('AI_FEATURE_AUTOMATION', true),
        'streaming'         => (bool) env('AI_FEATURE_STREAMING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Rate Limits
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'global_rpm'        => (int) env('AI_GLOBAL_RPM', 60),
        'per_tenant_rpm'    => (int) env('AI_TENANT_RPM', 20),
        'per_user_rpm'      => (int) env('AI_USER_RPM', 10),
        'burst_factor'      => (float) env('AI_BURST_FACTOR', 1.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Quota Defaults (can be overridden per tenant plan)
    |--------------------------------------------------------------------------
    */
    'quotas' => [
        'free_plan_daily'   => (int) env('AI_QUOTA_FREE', 10),
        'basic_plan_daily'  => (int) env('AI_QUOTA_BASIC', 100),
        'pro_plan_daily'    => (int) env('AI_QUOTA_PRO', 500),
        'enterprise_daily'  => (int) env('AI_QUOTA_ENTERPRISE', 2000),
        'default_daily'     => (int) env('AI_QUOTA_DEFAULT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Estimation (USD per 1K tokens — approximate)
    |--------------------------------------------------------------------------
    */
    'cost_rates' => [
        'openai' => [
            'gpt-4o-mini'  => ['input' => 0.00015, 'output' => 0.00060],
            'gpt-4o'       => ['input' => 0.00500, 'output' => 0.01500],
        ],
        'claude' => [
            'claude-haiku-4-5-20251001'   => ['input' => 0.00025, 'output' => 0.00125],
            'claude-sonnet-4-6'           => ['input' => 0.00300, 'output' => 0.01500],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'injection_detection'  => (bool) env('AI_INJECTION_DETECTION', true),
        'data_redaction'       => (bool) env('AI_DATA_REDACTION', true),
        'audit_prompts'        => (bool) env('AI_AUDIT_PROMPTS', true),
        'audit_responses'      => (bool) env('AI_AUDIT_RESPONSES', false),
        'sandbox_mode'         => (bool) env('AI_SANDBOX_MODE', false),
        'max_prompt_length'    => (int) env('AI_MAX_PROMPT_LENGTH', 8000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled'    => (bool) env('AI_CACHE_ENABLED', true),
        'ttl'        => (int) env('AI_CACHE_TTL', 3600),
        'prefix'     => 'ai:suggestion',
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Monitor
    |--------------------------------------------------------------------------
    */
    'health' => [
        'check_interval'       => (int) env('AI_HEALTH_INTERVAL', 300),
        'failure_threshold'    => (int) env('AI_FAILURE_THRESHOLD', 3),
        'recovery_window'      => (int) env('AI_RECOVERY_WINDOW', 120),
        'circuit_open_ttl'     => (int) env('AI_CIRCUIT_OPEN_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'log_ttl_days'   => (int) env('AI_AUDIT_LOG_TTL_DAYS', 30),
        'max_log_entries'=> (int) env('AI_AUDIT_MAX_ENTRIES', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Controls
    |--------------------------------------------------------------------------
    */
    'emergency' => [
        'kill_switch'       => (bool) env('AI_KILL_SWITCH', false),
        'degraded_mode'     => (bool) env('AI_DEGRADED_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staged Rollout
    |--------------------------------------------------------------------------
    | Percentage (0-100) of tenants who get AI features in staged rollout.
    */
    'rollout_percentage' => (int) env('AI_ROLLOUT_PERCENTAGE', 100),
];
