<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tickets Module Manifest
    |--------------------------------------------------------------------------
    |
    | Canonical metadata for the Tickets module.
    | Follows the Forms module-manifest.php pattern (PHASE 6.95).
    | Powers module registry, compatibility validation, and remote delivery.
    |
    */

    'name'            => 'tickets',
    'module_name'     => 'تیکت‌های پشتیبانی',
    'module_slug'     => 'tickets',
    'version'         => '1.1.0',
    'schema_version'  => '1',
    'release_channel' => 'stable',
    'last_updated'    => '2026-05-25 00:00:00',

    'compatibility' => [
        'php'      => '^8.1',
        'laravel'  => '^10.0',
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
    */

    'menu' => [
        [
            'key'     => 'tickets',
            'title'   => 'تیکت‌ها',
            'route'   => 'tickets.admin.index',
            'context' => 'shared',
            'icon'    => 'chat-bubble-left-right',
        ],
        [
            'key'     => 'tickets-sla',
            'title'   => 'گزارش SLA',
            'route'   => 'tickets.admin.sla.index',
            'context' => 'platform',
            'icon'    => 'clock',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Features (saas-platform only)
    |--------------------------------------------------------------------------
    */

    'platform_features' => [
        [
            'id'             => 'feat_t_010',
            'title'          => 'مدیریت تیکت‌های همه سایت‌ها',
            'category'       => 'platform',
            'status'         => 'active',
            'priority'       => 'high',
            'implemented'    => true,
            'implementation_details' => 'Admin TicketController با فیلتر site_id',
        ],
        [
            'id'          => 'feat_t_011',
            'title'       => 'گزارش SLA',
            'category'    => 'platform',
            'status'      => 'active',
            'priority'    => 'medium',
            'implemented' => true,
            'implementation_details' => 'TicketSlaController + TicketSlaService feature-flagged',
        ],
        [
            'id'          => 'feat_t_012',
            'title'       => 'تخصیص به کارشناس',
            'category'    => 'platform',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'TicketAssignmentController + TicketAssignmentService',
        ],
        [
            'id'          => 'feat_t_013',
            'title'       => 'مانیتورینگ و سلامت ماژول',
            'category'    => 'platform',
            'status'      => 'active',
            'priority'    => 'low',
            'implemented' => true,
            'implementation_details' => 'TicketMonitoringService',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Features (customer-site only)
    |--------------------------------------------------------------------------
    */

    'tenant_features' => [
        [
            'id'          => 'feat_t_020',
            'title'       => 'ارسال تیکت جدید',
            'category'    => 'tenant',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'TicketSubmissionController + TicketPortalService با site_id isolation',
        ],
        [
            'id'          => 'feat_t_021',
            'title'       => 'مشاهده تیکت‌های خود',
            'category'    => 'tenant',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'TicketPortalController با customer_id + site_id scope',
        ],
        [
            'id'          => 'feat_t_022',
            'title'       => 'پاسخ به تیکت',
            'category'    => 'tenant',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'TicketSubmissionController::storeReply با validation',
        ],
        [
            'id'          => 'feat_t_023',
            'title'       => 'آپلود پیوست',
            'category'    => 'tenant',
            'status'      => 'active',
            'priority'    => 'medium',
            'implemented' => true,
            'implementation_details' => 'config tickets.attachments feature-flagged',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Features (both contexts)
    |--------------------------------------------------------------------------
    */

    'shared_features' => [
        [
            'id'          => 'feat_t_001',
            'title'       => 'CRUD تیکت',
            'category'    => 'core',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
        ],
        [
            'id'          => 'feat_t_002',
            'title'       => 'مدیریت وضعیت (state machine)',
            'category'    => 'core',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
            'implementation_details' => 'TicketLifecycleService با allowedTransitions',
        ],
        [
            'id'          => 'feat_t_003',
            'title'       => 'اولویت‌بندی تیکت',
            'category'    => 'core',
            'status'      => 'active',
            'priority'    => 'medium',
            'implemented' => true,
        ],
        [
            'id'          => 'feat_t_004',
            'title'       => 'رشته پیام‌ها (chat thread)',
            'category'    => 'core',
            'status'      => 'active',
            'priority'    => 'high',
            'implemented' => true,
        ],
        [
            'id'          => 'feat_t_005',
            'title'       => 'API JSON',
            'category'    => 'core',
            'status'      => 'active',
            'priority'    => 'medium',
            'implemented' => true,
            'implementation_details' => 'TicketApiController با Sanctum auth',
        ],
    ],

    'changelog' => [
        [
            'date'        => '2026-05-25',
            'version'     => '1.1.0',
            'description' => 'Canonical migration: 9 controllers, 14 services, 3 repo contracts, 3 route files, 3 policies, full view segregation',
        ],
        [
            'date'        => '2026-05-23',
            'version'     => '1.0.0',
            'description' => 'Initial modular extraction from app/ directory',
        ],
    ],

    'last_checkpoint' => [
        'step'    => 'PHASE 7.1',
        'message' => 'Canonical migration complete — dual authority architecture enforced',
    ],

];
