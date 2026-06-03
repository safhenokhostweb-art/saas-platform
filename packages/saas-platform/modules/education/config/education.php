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
