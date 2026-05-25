<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Forms Module Manifest
    |--------------------------------------------------------------------------
    |
    | This file is the canonical source of truth for the Forms module metadata.
    | It replaces the legacy module-features/forms.json file.
    |
    | PHASE 6.95 — TASK 10: Signed Module Manifest Structure
    | This structure powers future updates, compatibility validation,
    | marketplace, licensing, and remote delivery.
    |
    */

    'name' => 'forms',
    'module_name' => 'فرم‌ها و صفحات',
    'module_slug' => 'forms',
    'version' => '1.0.2',
    'release_channel' => 'stable',
    'last_updated' => '2026-05-24 22:00:00',

    /*
    |--------------------------------------------------------------------------
    | Compatibility
    |--------------------------------------------------------------------------
    |
    | Defines the platform and dependency versions this module is compatible with.
    |
    */
    'compatibility' => [
        'php' => '^8.4',
        'laravel' => '^10.50',
        'platform' => '^1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Distribution
    |--------------------------------------------------------------------------
    |
    | Signed manifest structure for future remote delivery.
    | 'signed' will be set to true when remote distribution is active.
    | 'checksum' will contain the manifest hash for integrity verification.
    |
    */
    'distribution' => [
        'signed' => false,
        'checksum' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Context-aware menu items for sidebar rendering.
    | Each item can specify its visibility context.
    |
    */
    'menu' => [
        [
            'key' => 'forms',
            'title' => 'فرم‌ها',
            'route' => 'admin.forms.index',
            'context' => 'shared',
            'icon' => 'document-text',
        ],
        [
            'key' => 'forms-sites',
            'title' => 'اتصال به سایت‌ها',
            'route' => 'admin.forms.sites.edit',
            'context' => 'platform',
            'icon' => 'link',
        ],
        [
            'key' => 'forms-licensing',
            'title' => 'لایسنس فرم‌ها',
            'route' => null,
            'context' => 'platform',
            'icon' => 'key',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Features (saas-platform only)
    |--------------------------------------------------------------------------
    |
    | These features are ONLY available in the central admin context.
    | They are hidden from tenant sites (customer-site).
    |
    */
    'platform_features' => [
        [
            'id' => 'feat_f_010',
            'title' => 'مدیریت اتصال به سایت‌ها',
            'category' => 'platform',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'اتصال فرم‌ها به سایت‌های مختلف از طریق پنل مرکزی',
        ],
        [
            'id' => 'feat_f_011',
            'title' => 'مدیریت لایسنس فرم‌ها',
            'category' => 'platform',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'بررسی و مدیریت لایسنس ماژول فرم‌ها برای سایت‌ها',
        ],
        [
            'id' => 'feat_f_012',
            'title' => 'تنظیمات سطح پلتفرم',
            'category' => 'platform',
            'status' => 'active',
            'priority' => 'medium',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'تنظیمات سراسری فرم‌ها در سطح پلتفرم',
        ],
        [
            'id' => 'feat_f_013',
            'title' => 'گزارش‌گیری بین‌سایتی',
            'category' => 'platform',
            'status' => 'active',
            'priority' => 'low',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Features (customer-site only)
    |--------------------------------------------------------------------------
    |
    | These features are ONLY available in tenant site context.
    | They are hidden from the central admin (saas-platform).
    |
    */
    'tenant_features' => [
        [
            'id' => 'feat_f_020',
            'title' => 'ساخت و مدیریت فرم‌ها',
            'category' => 'tenant',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'ایجاد، ویرایش و حذف فرم‌ها در سایت مشتری',
        ],
        [
            'id' => 'feat_f_021',
            'title' => 'مشاهده ارسال‌های فرم',
            'category' => 'tenant',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'مشاهده و جستجوی ارسال‌های فرم‌ها',
        ],
        [
            'id' => 'feat_f_022',
            'title' => 'خروجی اکسل ارسال‌ها',
            'category' => 'tenant',
            'status' => 'active',
            'priority' => 'medium',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'دانلود گزارش ارسال‌ها به صورت CSV',
        ],
        [
            'id' => 'feat_f_023',
            'title' => 'پیش‌نمایش فرم',
            'category' => 'tenant',
            'status' => 'active',
            'priority' => 'medium',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => true,
            'implementation_details' => 'پیش‌نمایش فرم قبل از انتشار',
        ],
        [
            'id' => 'feat_f_024',
            'title' => 'تحلیل پایه ارسال‌ها',
            'category' => 'tenant',
            'status' => 'active',
            'priority' => 'low',
            'created_at' => '2026-05-24',
            'updated_at' => '2026-05-24',
            'implemented' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Features (both contexts)
    |--------------------------------------------------------------------------
    |
    | These features are available in BOTH saas-platform and customer-site.
    |
    */
    'shared_features' => [
        [
            'id' => 'feat_f_001',
            'title' => 'ساخت فرم (Drag & Drop)',
            'category' => 'core',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => true,
            'implementation_details' => 'FormField با position + متد sortFields + SortableJS frontend',
        ],
        [
            'id' => 'feat_f_002',
            'title' => 'انواع فیلدهای ورودی',
            'category' => 'core',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => true,
            'implementation_details' => 'FormField model با type, label, name, is_required',
        ],
        [
            'id' => 'feat_f_003',
            'title' => 'اعتبارسنجی هوشمند',
            'category' => 'core',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => true,
            'implementation_details' => 'is_required در انواع + اعتبارسنجی frontend با preview',
        ],
        [
            'id' => 'feat_f_004',
            'title' => 'ذخیره و مدیریت ارسال‌ها',
            'category' => 'core',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => true,
            'implementation_details' => 'FormSubmission model + FormSubmissionController',
        ],
        [
            'id' => 'feat_f_005',
            'title' => 'فرم‌های شرطی',
            'category' => 'core',
            'status' => 'active',
            'priority' => 'medium',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => false,
        ],
        [
            'id' => 'feat_f_006',
            'title' => 'مدیریت صفحات (سیستم)',
            'category' => 'core',
            'status' => 'active',
            'priority' => 'high',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => true,
            'implementation_details' => 'Page model کامل + PageController CRUD + SortableJS',
        ],
        [
            'id' => 'feat_f_007',
            'title' => 'گزارش‌گیری فرم',
            'category' => 'utility',
            'status' => 'active',
            'priority' => 'low',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => false,
        ],
        [
            'id' => 'feat_f_008',
            'title' => 'API ارسال‌ها',
            'category' => 'analytics',
            'status' => 'active',
            'priority' => 'medium',
            'created_at' => '2026-05-16',
            'updated_at' => '2026-05-16',
            'implemented' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy flat features list (backward compatibility)
    |--------------------------------------------------------------------------
    |
    | This merged list is provided for backward compatibility with code that
    | reads config('module-manifest-forms.features'). New code should use
    | the context-aware sections above.
    |
    */
    'features' => [
        ['id' => 'feat_f_001', 'title' => 'ساخت فرم (Drag & Drop)', 'category' => 'core', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => true, 'implementation_details' => 'FormField با position + متد sortFields + SortableJS frontend'],
        ['id' => 'feat_f_002', 'title' => 'انواع فیلدهای ورودی', 'category' => 'core', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => true, 'implementation_details' => 'FormField model با type, label, name, is_required'],
        ['id' => 'feat_f_003', 'title' => 'اعتبارسنجی هوشمند', 'category' => 'core', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => true, 'implementation_details' => 'is_required در انواع + اعتبارسنجی frontend با preview'],
        ['id' => 'feat_f_004', 'title' => 'ذخیره و مدیریت ارسال‌ها', 'category' => 'core', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => true, 'implementation_details' => 'FormSubmission model + FormSubmissionController'],
        ['id' => 'feat_f_005', 'title' => 'فرم‌های شرطی', 'category' => 'core', 'status' => 'active', 'priority' => 'medium', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => false],
        ['id' => 'feat_f_006', 'title' => 'مدیریت صفحات (سیستم)', 'category' => 'core', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => true, 'implementation_details' => 'Page model کامل + PageController CRUD + SortableJS'],
        ['id' => 'feat_f_007', 'title' => 'گزارش‌گیری فرم', 'category' => 'utility', 'status' => 'active', 'priority' => 'low', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => false],
        ['id' => 'feat_f_008', 'title' => 'API ارسال‌ها', 'category' => 'analytics', 'status' => 'active', 'priority' => 'medium', 'created_at' => '2026-05-16', 'updated_at' => '2026-05-16', 'implemented' => false],
        ['id' => 'feat_f_010', 'title' => 'مدیریت اتصال به سایت‌ها', 'category' => 'platform', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'اتصال فرم‌ها به سایت‌های مختلف از طریق پنل مرکزی'],
        ['id' => 'feat_f_011', 'title' => 'مدیریت لایسنس فرم‌ها', 'category' => 'platform', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'بررسی و مدیریت لایسنس ماژول فرم‌ها برای سایت‌ها'],
        ['id' => 'feat_f_012', 'title' => 'تنظیمات سطح پلتفرم', 'category' => 'platform', 'status' => 'active', 'priority' => 'medium', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'تنظیمات سراسری فرم‌ها در سطح پلتفرم'],
        ['id' => 'feat_f_013', 'title' => 'گزارش‌گیری بین‌سایتی', 'category' => 'platform', 'status' => 'active', 'priority' => 'low', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => false],
        ['id' => 'feat_f_020', 'title' => 'ساخت و مدیریت فرم‌ها', 'category' => 'tenant', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'ایجاد، ویرایش و حذف فرم‌ها در سایت مشتری'],
        ['id' => 'feat_f_021', 'title' => 'مشاهده ارسال‌های فرم', 'category' => 'tenant', 'status' => 'active', 'priority' => 'high', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'مشاهده و جستجوی ارسال‌های فرم‌ها'],
        ['id' => 'feat_f_022', 'title' => 'خروجی اکسل ارسال‌ها', 'category' => 'tenant', 'status' => 'active', 'priority' => 'medium', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'دانلود گزارش ارسال‌ها به صورت CSV'],
        ['id' => 'feat_f_023', 'title' => 'پیش‌نمایش فرم', 'category' => 'tenant', 'status' => 'active', 'priority' => 'medium', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => true, 'implementation_details' => 'پیش‌نمایش فرم قبل از انتشار'],
        ['id' => 'feat_f_024', 'title' => 'تحلیل پایه ارسال‌ها', 'category' => 'tenant', 'status' => 'active', 'priority' => 'low', 'created_at' => '2026-05-24', 'updated_at' => '2026-05-24', 'implemented' => false],
    ],

    'upcoming_features' => [
        [
            'id' => 'upc_f_001',
            'title' => 'اتصال به CRM',
        ],
    ],

    'changelog' => [
        [
            'date' => '2026-05-16',
            'version' => '1.0.0',
            'description' => 'ایجاد فایل اولیه با 8 ویژگی',
        ],
        [
            'date' => '2026-05-16',
            'version' => '1.0.1',
            'description' => '5 ویژگی پیاده‌سازی شد (ساخت, انواع, اعتبارسنجی, ارسال‌ها, صفحات)',
        ],
        [
            'date' => '2026-05-24',
            'version' => '1.0.2',
            'description' => 'جداسازی ویژگی‌ها به سه بخش platform, tenant, shared',
        ],
    ],

    'last_checkpoint' => [
        'step' => 'گام 6.95',
        'message' => 'جداسازی context-aware ویژگی‌ها + ساختار امضای ماژول',
    ],
];
