<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | برای API محصولات (صفحهٔ platform-products در سایت مالک روی دامنهٔ دیگر):
    | در production می‌توانید CORS_ORIGINS را در .env تنظیم کنید (مثلاً https://safhenokhost.ir).
    | خالی یا * = همهٔ دامنه‌ها مجاز
    */
    'allowed_origins' => (function () {
        $v = env('CORS_ORIGINS', '*');
        if ($v === '' || $v === '*') {
            return ['*'];
        }
        return array_map('trim', array_filter(explode(',', $v)));
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
