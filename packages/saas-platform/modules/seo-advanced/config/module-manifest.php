<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO Advanced Module Manifest
    |--------------------------------------------------------------------------
    |
    | Canonical metadata for the SEO Advanced module. Follows the
    | Forms/Tickets module-manifest.php pattern (PHASE 6.95 / PHASE 7.1).
    |
    | module_slug matches the real marketplace catalog slug ('seo-advanced'
    | — Module id=57, see 2026_07_29_110000_seed_seo_advanced_marketplace_module.php).
    | This is what site_modules/License::modules actually carries, and what
    | AdminMenu's manifest lookup keys on.
    |
    */

    'name'            => 'seo-advanced',
    'module_name'     => 'سئو پیشرفته',
    'module_slug'     => 'seo-advanced',
    'version'         => '1.0.0',
    'schema_version'  => '1',
    'release_channel' => 'stable',
    'last_updated'    => '2026-08-02 00:00:00',

    'compatibility' => [
        'php'      => '^8.1',
        'laravel'  => '^10.0|^11.0',
        'platform' => '^1.0',
    ],

    'distribution' => [
        'signed'   => false,
        'checksum' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    | 'route' points at the tenant-facing routes/site.php route (no
    | {siteId} param). Full editing (meta/sitemap/robots/schema/redirects/
    | keywords/search-console) lives only in routes/web.php for platform
    | staff and is intentionally not listed here.
    */

    'menu' => [
        [
            'key'     => 'seo-advanced',
            'title'   => 'سئو پیشرفته',
            'route'   => 'seo-advanced.site.report',
            'context' => 'shared',
            'icon'    => 'magnifying-glass',
        ],
    ],

    'platform_features' => [
        [
            'id'          => 'feat_s_010',
            'title'       => 'مدیریت کامل سئو همه سایت‌ها',
            'category'    => 'platform',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'routes/web.php — Admin controllers با پیشوند admin/seo-advanced/{siteId}',
        ],
    ],

    'tenant_features' => [
        [
            'id'          => 'feat_s_020',
            'title'       => 'گزارش خلاصه سئو',
            'category'    => 'tenant',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'SeoAdvancedCustomerReportController — routes/site.php',
        ],
    ],

    'shared_features' => [
        [
            'id'          => 'feat_s_001',
            'title'       => 'متا، Sitemap، Robots.txt، Schema.org',
            'category'    => 'core',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
        ],
    ],

    'changelog' => [
        [
            'date'        => '2026-08-02',
            'version'     => '1.0.0',
            'description' => 'انتشار اولیه ماژول سئو پیشرفته در مارکت‌پلیس.',
        ],
    ],

    'last_checkpoint' => [
        'step'    => 'PHASE 18',
        'message' => 'SEO Advanced module manifest — powers dynamic AdminMenu discovery on customer-site.',
    ],

];
