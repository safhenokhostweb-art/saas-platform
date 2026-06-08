<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Tenant customer sites call the saas-platform API for features like
    | support-ticket unread counts, marketplace listing, etc.
    | Origins are fully env-driven — no hardcoded domain names.
    |
    | CORS_ALLOWED_ORIGINS — comma-separated list, e.g.:
    |   https://tenantone.com,https://tenanttwo.com
    |
    | CORS_ALLOWED_ORIGINS_PATTERNS — regex patterns, e.g.:
    |   ^https://.*\.yourdomain\.com$
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))
    ),

    'allowed_origins_patterns' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS', '')))
    ),

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'X-License-Key'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
