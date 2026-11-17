<?php

/*
|--------------------------------------------------------------------------
| Unified customer & site onboarding (ADR-088)
|--------------------------------------------------------------------------
|
| Industry profiles map a business vertical (BusinessVerticals) to the
| canonical theme the wizard pre-selects for it. The theme's pages — its
| home page included (e.g. the solar-industry theme's `solar-page-home`) —
| are bound to the site automatically once its installation completes.
|
| The kits a vertical needs are NOT listed here: they are derived from the
| theme-runtime access policy (a family's `required_vertical` and
| `entitlement_keys`), so this file never restates the licensing rules.
*/

return [
    'industry_profiles' => [
        'solar_energy' => [
            'theme_slug' => 'solar-industry',
        ],
    ],

    // Labels a platform subdomain may never take: they are platform hosts.
    'reserved_subdomains' => [
        'www', 'panel', 'admin', 'api', 'app', 'mail', 'smtp', 'imap', 'pop',
        'ftp', 'ns1', 'ns2', 'cdn', 'static', 'assets', 'deployment', 'hub',
        'dashboard', 'status', 'support', 'billing', 'staging', 'test',
    ],
];
