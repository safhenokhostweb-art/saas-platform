<?php

return [
    /*
     * Absolute (or app-relative) path to the directory holding
     * quality-check.mjs and its node_modules/playwright installation.
     * saas-platform defaults to its own scripts/headless/. A host with no
     * local Node/Playwright installation of its own (customer-site) points
     * this at saas-platform's copy via a cross-repo relative path — the
     * same sibling-checkout assumption its composer.json symlink path
     * repositories already make explicit.
     */
    'script_root' => env('THEME_RUNTIME_SCRIPT_ROOT', base_path('scripts/headless')),

    /*
     * Exact HTTPS hosts that tenant runtimes may contact for dynamic feed
     * widgets. Empty by default: API binding is opt-in and fail-closed.
     */
    'dynamic_api_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('THEME_RUNTIME_DYNAMIC_API_HOSTS', ''))
    ))),

    /*
     * Phase 3C/M3 client-side mirror of InteractionPolicy's server-side
     * host allowlists (defense in depth — the server remains the authority
     * and revalidates every target). Exact HTTPS hostnames (lowercase, no
     * scheme). Two lists:
     *   - interaction_api_hosts: hosts the runtime may fetch (call_api).
     *   - interaction_hosts: hosts the runtime may navigate to (https form).
     * Same-origin relative paths are always allowed for navigate and need
     * no entry. Empty by default: https navigation/API calls are opt-in,
     * mirroring InteractionPolicy's deny-by-default server contract.
     */
    'interaction_api_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('THEME_RUNTIME_INTERACTION_API_HOSTS', ''))
    ))),

    'interaction_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('THEME_RUNTIME_INTERACTION_HOSTS', ''))
    ))),
];
