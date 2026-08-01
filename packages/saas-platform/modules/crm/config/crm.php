<?php

/**
 * Phase 19 — Part 1: mirrors the Tickets module's decoupling convention
 * (packages/saas-platform/modules/tickets/config/tickets.php) — this
 * module's own src/ never imports App\Models\* directly; it resolves the
 * host app's User/Site models through this config instead, and
 * crm:validate-isolation enforces that mechanically (config/ itself is
 * the one, deliberate exception — this is the sanctioned place a module
 * declares its host bindings).
 */
return [

    'layouts' => [
        'admin' => env('CRM_LAYOUT_ADMIN', 'layouts.admin'),
        'site' => env('CRM_LAYOUT_SITE', 'layouts.app'),
    ],

    'site_model' => \App\Models\Site::class,
    'user_model' => \App\Models\User::class,

    'pagination' => 20,

    'lifecycle_stages' => ['lead', 'prospect', 'customer'],

    'lead_statuses' => ['new', 'contacted', 'qualified', 'unqualified', 'converted'],

    'opportunity_statuses' => ['open', 'won', 'lost'],

    'activity_types' => ['call', 'meeting', 'email', 'sms', 'note', 'system'],

    'task_statuses' => ['pending', 'completed', 'cancelled'],

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Subject Type Aliases
    |--------------------------------------------------------------------------
    |
    | Short, URL-safe aliases for the entities Activity/Task/Attachment can
    | attach to — never expose the full class name in a route.
    */
    'subject_types' => [
        'contact' => \SaaSPlatform\Modules\Crm\Models\CrmContact::class,
        'company' => \SaaSPlatform\Modules\Crm\Models\CrmCompany::class,
        'lead' => \SaaSPlatform\Modules\Crm\Models\CrmLead::class,
        'opportunity' => \SaaSPlatform\Modules\Crm\Models\CrmOpportunity::class,
    ],

    'attachments' => [
        'max_files' => 5,
        'max_size' => 5120, // KB
        'allowed' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xlsx', 'zip', 'txt'],
        'disk' => 'private',
        'path' => 'crm-attachments',
    ],

];
