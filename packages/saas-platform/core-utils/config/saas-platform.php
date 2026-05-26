<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Version
    |--------------------------------------------------------------------------
    | Canonical version of the running platform host. Used by ModuleListCommand
    | and PlatformCompatibilityService to evaluate module compatibility ranges.
    | Override in the host app's config/saas-platform.php (published).
    */
    'platform_version' => env('SAAS_PLATFORM_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Runtime Role
    |--------------------------------------------------------------------------
    | Explicit role hint for PlatformContext. When set, overrides heuristic
    | detection. Valid values: central_admin, tenant_admin, tenant_public, api.
    | Leave null to allow PlatformContext to auto-detect from TenantContext.
    */
    'role' => env('SAAS_PLATFORM_ROLE', null),

    /*
    |--------------------------------------------------------------------------
    | Application Name Fallback
    |--------------------------------------------------------------------------
    | Used by package views/emails when config('app.name') is not available.
    */
    'app_name' => env('APP_NAME', 'SaaS Platform'),

];
