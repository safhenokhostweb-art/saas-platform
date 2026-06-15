<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'hcaptcha' => [
        'sitekey' => env('HCAPTCHA_SITEKEY', ''),
        'secret' => env('HCAPTCHA_SECRET', ''),
        'skip_in_local' => filter_var(env('AUTH_CAPTCHA_SKIP_IN_LOCAL', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID', ''),
        'sandbox' => filter_var(env('ZARINPAL_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'idpay' => [
        'api_key' => env('IDPAY_API_KEY', ''),
        'sandbox' => filter_var(env('IDPAY_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | آدرس دامنهٔ اصلی برای callback بانک (مثلاً https://safhenokhost.ir).
    | اگر تنظیم شود، بانک کاربر را به این آدرس برمی‌گرداند و سایت مالک
    | آن را به پنل فوروارد می‌کند. برای حفظ لینک بانک روی دامنهٔ اصلی.
    */
    'payment_callback_url' => env('PAYMENT_CALLBACK_URL', null),

    /*
    | تعداد روز پشتیبانی رایگان برای مشتریان جدید (خرید لایسنس قالب سایت).
    | در این مدت مشتری می‌تواند ماژول‌ها را بروزرسانی کند. مقدار پیش‌فرض: ۳۰ روز.
    */
    'free_support_days_for_new_license' => (int) env('FREE_SUPPORT_DAYS_FOR_NEW_LICENSE', 30),

    /*
    |--------------------------------------------------------------------------
    | DeepSeek API
    |--------------------------------------------------------------------------
    |
    | استفاده‌شده توسط Agent Layer و DeepSeekClient
    |
    */

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
    ],

    'local_ai' => [
        'enabled'  => filter_var(env('LOCAL_AI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'endpoint' => env('LOCAL_AI_ENDPOINT', 'http://127.0.0.1:11434/api/generate'),
        'model'    => env('LOCAL_AI_MODEL', 'llama3'),
    ],

    // 8.3d: Slack webhook alerting for critical anomalies + deployment events
    'slack_alert' => [
        'webhook' => env('SLACK_ALERT_WEBHOOK'),
        'channel' => env('SLACK_ALERT_CHANNEL', '#alerts'),
    ],

    // 8.4d: OpenTelemetry OTLP export to Jaeger/Tempo
    // Set OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318 to enable
    'otel' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
    ],

    // Theme Builder AI — GapGPT (OpenAI-compatible) or Claude
    'theme_ai' => [
        'provider'  => env('THEME_AI_PROVIDER', 'gapgpt'),
        'model'     => env('THEME_AI_MODEL', 'gpt-4o-mini'),
    ],

    'gapgpt' => [
        'key'       => env('GAPGPT_API_KEY', ''),
        'base_url'  => env('GAPGPT_BASE_URL', 'https://api.gapgpt.app/v1'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', ''),
    ],

];
