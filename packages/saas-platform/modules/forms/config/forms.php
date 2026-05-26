<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Forms Module Layout Configuration
    |--------------------------------------------------------------------------
    |
    | These settings allow consuming applications (customer-site, saas-platform,
    | tenant themes, white-label themes) to override the Blade layouts used
    | by the Forms module without modifying package views.
    |
    | Each consuming app should publish this config and adjust the paths
    | to match its own layout structure.
    |
    | Example overrides:
    |   'admin' => 'admin.layout'   // customer-site convention
    |   'admin' => 'layouts.admin'  // saas-platform convention
    |   'site'  => 'themes.default.layout'
    |
    */

    'layouts' => [
        /*
         * Layout used for admin/management views (index, create, edit, etc.)
         * PHASE 6.95 — TASK 6: Context-aware layout resolution.
         *   - saas-platform (platform): 'layouts.admin'
         *   - customer-site (tenant):   'admin.layout'
         *
         * The PlatformContextService determines the runtime context and
         * resolves the appropriate layout automatically.
         */
        'admin' => env('FORMS_LAYOUT_ADMIN', 'layouts.admin'),

        /*
         * Tenant-specific admin layout override.
         * When the runtime context is tenant (customer-site), this layout
         * is used instead of the default 'admin' layout.
         */
        'admin_tenant' => env('FORMS_LAYOUT_ADMIN_TENANT', 'admin.layout'),

        /*
         * Layout used for public/site-facing views (form display, submission)
         */
        'site' => env('FORMS_LAYOUT_SITE', 'themes.default.layout'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Shared Components
    |--------------------------------------------------------------------------
    |
    | Blade components referenced by package views that may differ between
    | consuming applications. Override these paths in the consuming app's
    | published config to match its own component structure.
    |
    */

    'components' => [
        /*
         * The module guide info box used in admin index views.
         * saas-platform: admin.partials.module-guide-box
         * customer-site: admin.partials.module-guide-box
         */
        'module-guide-box' => env('FORMS_COMPONENT_MODULE_GUIDE_BOX', 'admin.partials.module-guide-box'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Types
    |--------------------------------------------------------------------------
    |
    | Available field types for forms. Used by the edit view to populate
    | the field type dropdown.
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

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Number of items per page for forms and submissions listings.
    |
    */
    'pagination' => env('FORMS_PAGINATION', 25),

    /*
    |--------------------------------------------------------------------------
    | Export Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of records to include in CSV exports.
    |
    */
    'export_limit' => env('FORMS_EXPORT_LIMIT', 10000),

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable optional features.
    |
    */
    'seo_enabled' => env('FORMS_SEO_ENABLED', true),
    'captcha_enabled' => env('FORMS_CAPTCHA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Site Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class used for the "site" relationship on Form and
    | FormSubmission. Override in the consuming app's published config.
    |
    */
    'site_model' => \App\Models\Site::class,
];
