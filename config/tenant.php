<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    | Domain or subdomain based tenant identification.
    | Platform supports Reseller tenants (white-label) via subdomain or custom domain.
    */

    'resolver' => [
        /** زیردامنه‌های نادیده‌گرفته‌شده (مثل www) */
        'ignore_subdomains' => ['www', 'api', 'admin', 'panel'],

        /** آیا تشخیص Reseller از دامنه فعال است */
        'reseller_from_domain' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Types
    |--------------------------------------------------------------------------
    */

    'types' => [
        'reseller' => \App\Models\Reseller::class,
        'site' => \App\Models\Site::class,
    ],

];
