<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Forms Module Configuration
    |--------------------------------------------------------------------------
    |
    | This config file is merged into the application config under 'forms'.
    |
    */

    'field_types' => [
        'text' => 'متن',
        'textarea' => 'متن بلند',
        'email' => 'ایمیل',
        'number' => 'عدد',
        'date' => 'تاریخ (شمسی)',
        'radio' => 'رادیو',
        'checkbox' => 'چک‌باکس',
        'select' => 'لیست انتخابی',
    ],

    'pagination' => 25,

    'export_limit' => 10000,

    /*
    |--------------------------------------------------------------------------
    | Tenant-Aware Settings
    |--------------------------------------------------------------------------
    |
    | Controls whether forms are isolated per tenant (site).
    | When enabled, each form can be assigned to specific sites.
    |
    */
    'tenant_aware' => env('FORMS_TENANT_AWARE', true),

    /*
    |--------------------------------------------------------------------------
    | Multi-Site Assignment
    |--------------------------------------------------------------------------
    |
    | When enabled, forms can be assigned to multiple sites via the form_site pivot.
    |
    */
    'multi_site' => env('FORMS_MULTI_SITE', true),

    /*
    |--------------------------------------------------------------------------
    | SEO Fields
    |--------------------------------------------------------------------------
    |
    | Enable SEO fields (meta_title, meta_description, canonical_url, meta_robots)
    | and intro_text for forms.
    |
    */
    'seo_enabled' => env('FORMS_SEO_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Captcha Integration
    |--------------------------------------------------------------------------
    |
    | Enable captcha support in form preview and frontend rendering.
    |
    */
    'captcha_enabled' => env('FORMS_CAPTCHA_ENABLED', true),
];
