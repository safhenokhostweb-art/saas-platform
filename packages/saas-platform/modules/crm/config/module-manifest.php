<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRM Suite Module Manifest
    |--------------------------------------------------------------------------
    |
    | Canonical metadata for the CRM module. Follows the Forms/Tickets
    | module-manifest.php pattern (PHASE 6.95 / PHASE 7.1).
    |
    | module_slug is the real marketplace catalog slug ('crm-suite' —
    | Module id=58, see 2026_08_01_110000_seed_crm_marketplace_module.php),
    | not the package's internal short name ('crm') — this is what
    | site_modules/License::modules actually carries, and what
    | AdminMenu's manifest lookup keys on.
    |
    */

    'name'            => 'crm',
    'module_name'     => 'مخاطبین و CRM',
    'module_slug'     => 'crm-suite',
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
    | {siteId} param — scoped to the authenticated user's own site). The
    | full CRUD suite (companies/leads/opportunities/...) lives only in
    | routes/web.php for platform staff and is intentionally not listed
    | here.
    */

    'menu' => [
        [
            'key'     => 'crm',
            'title'   => 'مخاطبین و CRM',
            'route'   => 'crm.site.dashboard',
            'context' => 'shared',
            'icon'    => 'users',
        ],
    ],

    'platform_features' => [
        [
            'id'          => 'feat_c_010',
            'title'       => 'مدیریت کامل CRM همه سایت‌ها',
            'category'    => 'platform',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'routes/web.php — Admin CRUD controllers با پیشوند admin/crm/{siteId}',
        ],
    ],

    'tenant_features' => [
        [
            'id'          => 'feat_c_020',
            'title'       => 'داشبورد خلاصه CRM',
            'category'    => 'tenant',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'CrmCustomerDashboardController — routes/site.php',
        ],
    ],

    'shared_features' => [
        [
            'id'          => 'feat_c_001',
            'title'       => 'مخاطبین، شرکت‌ها، فرصت‌های فروش',
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
            'description' => 'انتشار اولیه ماژول CRM (crm-suite) در مارکت‌پلیس.',
        ],
    ],

    'last_checkpoint' => [
        'step'    => 'PHASE 19',
        'message' => 'CRM module manifest — powers dynamic AdminMenu discovery on customer-site.',
    ],

];
