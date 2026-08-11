<?php

/**
 * Phase 18 — Part 5: the module never hardcodes a Core Blade layout path.
 * Views extend config('seo-advanced.layouts.admin') instead — the host
 * app can override this (via a published config or env vars) without
 * this module ever needing to know the real layout's name, matching the
 * same pattern already used by the forms module.
 */
return [

    'layouts' => [
        'admin' => env('SEO_ADVANCED_LAYOUT_ADMIN', 'layouts.admin'),
        'site' => env('SEO_ADVANCED_LAYOUT_SITE', 'layouts.app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Loading
    |--------------------------------------------------------------------------
    |
    | Phase X: routes/web.php is the platform-staff CRUD surface
    | (admin/seo-advanced/{siteId}/..., {siteId} route param, auth+admin
    | gate) — it belongs only in saas-platform, never in customer-site.
    | routes/site.php (tenant-scoped, no {siteId}) always loads regardless
    | of this flag. Mirrors TicketsServiceProvider's
    | config('tickets.routes.site', true) convention, inverted since here
    | it's the *web* file that's optional.
    */
    'routes' => [
        'web' => env('SEO_ADVANCED_ROUTES_WEB', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Search Console (Phase 18-E — Part 2)
    |--------------------------------------------------------------------------
    |
    | Unset by default — GoogleSearchConsoleService::isConnected() and every
    | admin action degrade gracefully (empty/"not connected") until a real
    | Google Cloud OAuth client is configured here.
    */
    'search_console' => [
        'client_id' => env('SEO_ADVANCED_GOOGLE_CLIENT_ID'),
        'client_secret' => env('SEO_ADVANCED_GOOGLE_CLIENT_SECRET'),
    ],

];
