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
];
