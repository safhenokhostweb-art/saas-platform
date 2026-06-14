<?php

return [

    'layouts' => [
        'admin'        => env('FORMS_LAYOUT_ADMIN', 'layouts.admin'),
        'admin_tenant' => env('FORMS_LAYOUT_ADMIN_TENANT', 'admin.layout'),
        'site'         => env('FORMS_LAYOUT_SITE', 'themes.default.layout'),
    ],

    'components' => [
        'module-guide-box' => env('FORMS_COMPONENT_MODULE_GUIDE_BOX', 'admin.partials.module-guide-box'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Types
    |--------------------------------------------------------------------------
    */
    'field_types' => [
        'text'           => 'متن',
        'textarea'       => 'متن بلند',
        'email'          => 'ایمیل',
        'number'         => 'عدد',
        'phone'          => 'تلفن',
        'date'           => 'تاریخ (شمسی)',
        'radio'          => 'رادیو',
        'checkbox'       => 'چک‌باکس',
        'select'         => 'لیست انتخابی',
        'rating'         => 'امتیاز (ستاره)',
        'section_header' => 'عنوان بخش',
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Type Categories (for builder palette)
    |--------------------------------------------------------------------------
    */
    'field_type_categories' => [
        [
            'label' => 'اطلاعات پایه',
            'types' => ['text', 'textarea', 'email', 'number', 'phone'],
        ],
        [
            'label' => 'انتخاب',
            'types' => ['select', 'radio', 'checkbox'],
        ],
        [
            'label' => 'تاریخ و امتیاز',
            'types' => ['date', 'rating'],
        ],
        [
            'label' => 'ساختار',
            'types' => ['section_header'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Type Icons (Heroicon paths for builder palette)
    |--------------------------------------------------------------------------
    */
    'field_type_icons' => [
        'text'           => 'bars-3-bottom-left',
        'textarea'       => 'document-text',
        'email'          => 'envelope',
        'number'         => 'hashtag',
        'phone'          => 'phone',
        'date'           => 'calendar',
        'radio'          => 'check-circle',
        'checkbox'       => 'check-badge',
        'select'         => 'chevron-up-down',
        'rating'         => 'star',
        'section_header' => 'minus',
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Mode
    |--------------------------------------------------------------------------
    | When true (saas-platform context), admin CRUD routes are NOT registered.
    | Only public form-serving routes are active. Tenant admin (customer-site)
    | manages all forms independently in their own panel.
    */
    'platform_mode' => env('FORMS_PLATFORM_MODE', false),

    'pagination'    => env('FORMS_PAGINATION', 25),
    'export_limit'  => env('FORMS_EXPORT_LIMIT', 10000),
    'seo_enabled'   => env('FORMS_SEO_ENABLED', true),
    'captcha_enabled' => env('FORMS_CAPTCHA_ENABLED', true),
    'site_model'    => \App\Models\Site::class,

    /*
    |--------------------------------------------------------------------------
    | Date Formatter
    |--------------------------------------------------------------------------
    | Callable that formats a Carbon/datetime to Persian or Gregorian string.
    | Signature: callable(Carbon|string $date, string $format): string
    | Defaults to simple Carbon format if not configured.
    */
    'date_formatter' => env('FORMS_DATE_FORMATTER', null),

    /*
    |--------------------------------------------------------------------------
    | Automation
    |--------------------------------------------------------------------------
    */
    'automation' => [
        'enabled'       => env('FORMS_AUTOMATION_ENABLED', true),
        'queue'         => env('FORMS_AUTOMATION_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'enabled'       => env('FORMS_ANALYTICS_ENABLED', true),
        'default_days'  => 30,
    ],
];
