<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Layout Resolution
    |--------------------------------------------------------------------------
    |
    | Package views extend these layout keys. Consuming apps publish the
    | config and override these values to point to their own layout files.
    | The FormsServiceProvider pattern is replicated here for consistency.
    |
    */

    'layouts' => [
        'admin'        => 'layouts.admin',
        'admin_tenant' => 'layouts.admin',
        'site'         => 'layouts.app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Settings
    |--------------------------------------------------------------------------
    */

    'site_model'           => \App\Models\Site::class,
    'user_model'           => \App\Models\User::class,
    'settings_model'       => \App\Models\Setting::class,
    'sms_gateway_resolver' => \App\Services\Sms\SmsGatewayResolver::class,

    'statuses'             => ['open', 'in_progress', 'closed'],
    'priorities'           => ['low', 'normal', 'high'],
    'pagination'           => 20,
    'ticket_number_prefix' => 'TKT',

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'enabled'   => true,
        'max_files' => 5,
        'max_size'  => 5120, // KB
        'allowed'   => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'txt'],
        'disk'      => 'public',
        'path'      => 'tickets',
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA (Service Level Agreement)
    |--------------------------------------------------------------------------
    |
    | Enabled/disabled via config('feature-flags.tickets.sla').
    | All SLA services check that flag before performing any operation.
    |
    */

    'sla' => [
        'first_response_minutes' => 60,    // 1 hour
        'resolution_minutes'     => 1440,  // 24 hours

        'by_priority' => [
            'high'   => ['first_response' => 30,  'resolution' => 480],
            'normal' => ['first_response' => 60,  'resolution' => 1440],
            'low'    => ['first_response' => 240, 'resolution' => 4320],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat / Reply Thread
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'max_message_length' => 5000,
        'auto_close_days'    => 7,   // Auto-close if no reply after N days (0 = disabled)
        'auto_in_progress'   => true, // Move to in_progress on first staff reply
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Assignment
    |--------------------------------------------------------------------------
    |
    | Enabled/disabled via config('feature-flags.tickets.auto_assign').
    |
    */

    'assignment' => [
        'auto_assign'  => false,
        'round_robin'  => false,
        'notify_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'on_created'        => true,
        'on_status_changed' => true,
        'on_assigned'       => true,
        'on_reply'          => true,
        'on_sla_breach'     => true,
    ],

];
