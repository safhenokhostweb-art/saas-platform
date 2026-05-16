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
        // مقدار صحیح: env('DEEPSEEK_API_KEY')
        'key' => env('DEEPSEEK_API_KEY'),
    ],

];
