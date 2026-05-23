<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module Assets Configuration
    |--------------------------------------------------------------------------
    |
    | This config file defines which modules have publishable assets that
    | should be symlinked (in dev/staging) or published (in production).
    |
    | Each entry requires:
    |   'name'   => The module name (used as the directory under public/vendor/)
    |   'source' => The absolute path to the module's assets directory
    |
    | In non-production environments, the EnsureModuleAssetsLinked middleware
    | will automatically create symlinks for all registered modules.
    |
    */

    'modules' => [
        // Tickets module assets
        [
            'name' => 'tickets',
            'source' => base_path('vendor/saas-platform/modules-tickets/Resources/assets'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback to packages directory (development)
    |--------------------------------------------------------------------------
    |
    | When working in the main saas-platform repository (not as a vendor package),
    | the assets may be located in the packages/ directory instead of vendor/.
    | The AssetSymlinkService will try both locations automatically.
    |
    */

    'dev_source_paths' => [
        'tickets' => base_path('packages/saas-platform/modules/tickets/Resources/assets'),
    ],

];
