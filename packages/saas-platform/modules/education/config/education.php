<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy KB Compatibility
    |--------------------------------------------------------------------------
    */
    'legacy_kb_compat' => env('EDUCATION_LEGACY_KB_COMPAT', true),

    /*
    |--------------------------------------------------------------------------
    | Search Driver
    |--------------------------------------------------------------------------
    */
    'search_driver' => env('EDUCATION_SEARCH_DRIVER', 'fulltext'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags (Phase 1)
    |--------------------------------------------------------------------------
    */
    'walkthroughs_enabled'    => env('EDUCATION_WALKTHROUGHS', true),
    'learning_paths_enabled'  => env('EDUCATION_LEARNING_PATHS', true),
    'release_notes_enabled'   => env('EDUCATION_RELEASE_NOTES', true),
    'progress_tracking'       => env('EDUCATION_PROGRESS_TRACKING', true),

    /*
    |--------------------------------------------------------------------------
    | Phase EDU-11 Feature Flags
    |--------------------------------------------------------------------------
    */
    'feature_discovery'    => env('EDUCATION_FEATURE_DISCOVERY', true),
    'usage_tracking'       => env('EDUCATION_USAGE_TRACKING', true),
    'recommendations'      => env('EDUCATION_RECOMMENDATIONS', true),
    'auto_documentation'   => env('EDUCATION_AUTO_DOCS', true),
    'walkthrough_triggers' => env('EDUCATION_WALKTHROUGH_TRIGGERS', true),

    /*
    |--------------------------------------------------------------------------
    | AI Service
    |--------------------------------------------------------------------------
    | Phase 1 stub by default. Set EDUCATION_AI_ENABLED=true and wire a
    | concrete EducationAiContentGeneratorInterface implementation to enable.
    */
    'ai_enabled' => env('EDUCATION_AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | EDU-15: AI Content Generation Provider
    |--------------------------------------------------------------------------
    | Provider used by AiContentProviderInterface.
    | Options: 'null' (no-op), 'claude', 'openai'
    |
    | EDUCATION_AI_PROVIDER=claude also requires EDUCATION_CLAUDE_API_KEY.
    | EDUCATION_AI_PROVIDER=openai also requires EDUCATION_OPENAI_API_KEY.
    */
    'ai_provider' => env('EDUCATION_AI_PROVIDER', 'null'),

    'ai_generation' => [
        'claude_api_key'  => env('EDUCATION_CLAUDE_API_KEY', ''),
        'claude_model'    => env('EDUCATION_CLAUDE_MODEL', 'claude-sonnet-4-6'),
        'openai_api_key'  => env('EDUCATION_OPENAI_API_KEY', ''),
        'openai_model'    => env('EDUCATION_OPENAI_MODEL', 'gpt-4o'),
        'max_tokens'      => env('EDUCATION_AI_MAX_TOKENS', 2048),
        'author_id'       => env('EDUCATION_AI_AUTHOR_ID', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | EDU-17: AI Runtime Configuration
    |--------------------------------------------------------------------------
    | Controls provider selection, HTTP timeout, and circuit breaker behaviour.
    |
    | circuit_breaker.failure_threshold  — consecutive failures before OPEN
    | circuit_breaker.open_ttl_seconds   — how long the circuit stays OPEN
    | circuit_breaker.recovery_window_seconds — window in which failures accumulate
    */
    'ai' => [

        'provider' => env('EDUCATION_AI_PROVIDER', null),

        'timeout_seconds' => env('EDUCATION_AI_TIMEOUT_SECONDS', 60),

        'circuit_breaker' => [

            'enabled'                  => env('EDUCATION_AI_CIRCUIT_ENABLED', true),

            'failure_threshold'        => env('EDUCATION_AI_CIRCUIT_FAILURE_THRESHOLD', 5),

            'open_ttl_seconds'         => env('EDUCATION_AI_CIRCUIT_OPEN_TTL_SECONDS', 300),

            'recovery_window_seconds'  => env('EDUCATION_AI_CIRCUIT_RECOVERY_WINDOW_SECONDS', 60),

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Recommendation Engine Thresholds
    |--------------------------------------------------------------------------
    */
    'recommendation' => [
        'low_usage_threshold'  => env('EDUCATION_REC_LOW_USAGE', 3),
        'max_active_per_user'  => env('EDUCATION_REC_MAX_ACTIVE', 10),
        'purge_after_days'     => env('EDUCATION_REC_PURGE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => env('EDUCATION_PAGINATION', 20),

    /*
    |--------------------------------------------------------------------------
    | Layouts
    |--------------------------------------------------------------------------
    */
    'layouts' => [
        'admin'        => 'layouts.admin',
        'admin_tenant' => 'admin.layout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'admin_prefix'   => 'admin/education',
        'api_prefix'     => 'api/education',
        'mobile_enabled' => env('EDUCATION_MOBILE_ROUTES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Providers
    |--------------------------------------------------------------------------
    */
    'media_providers' => ['local', 'aparat', 'youtube'],

    /*
    |--------------------------------------------------------------------------
    | Coverage Tracker
    |--------------------------------------------------------------------------
    */
    'coverage_tracking' => env('EDUCATION_COVERAGE_TRACKING', true),

];
