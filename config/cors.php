<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | customer-site.test (and any tenant domain) calls the saas-platform API
    | for features like support-ticket unread counts, marketplace listing, etc.
    | Those cross-origin requests require proper CORS headers.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://customer-site.test',
        'https://customer-site.test',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'X-License-Key'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
