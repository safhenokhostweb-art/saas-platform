<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Installer Master Key
    |--------------------------------------------------------------------------
    | AES-256-GCM encryption key for CredentialVault (customer server secrets).
    | Must be a base64-encoded 32-byte random value.
    | Generate with: base64_encode(random_bytes(32))
    */
    'master_key' => env('INSTALLER_MASTER_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Local Recovery Repository
    |--------------------------------------------------------------------------
    | policy:   local   → local only
    |           arvan   → Arvan only
    |           both    → local AND Arvan (recommended)
    |
    | priority: local_first → restore prefers local, falls back to Arvan on failure
    |           arvan_first → restore prefers Arvan, falls back to local on failure
    |
    | local_path: absolute directory on the server where backups are stored.
    |             Defaults to storage/app/local-recovery-repo when empty.
    |
    | min_free_gb: refuse to write if the local path has less than this many GB free.
    */
    'recovery_repository' => [
        'policy'      => env('RECOVERY_POLICY', 'both'),
        'priority'    => env('RECOVERY_PRIORITY', 'local_first'),
        'local_path'  => env('RECOVERY_LOCAL_PATH', ''),
        'min_free_gb' => (int) env('RECOVERY_MIN_FREE_GB', 10),
        // Verified copies kept per scope (platform; each customer site).
        // Older copies are pruned after every successful store and by
        // `php artisan recovery:prune-local --execute`.
        // INITIAL_INSTALLATION_BASELINE is never pruned.
        'keep_per_scope' => (int) env('RECOVERY_LOCAL_KEEP', 2),
    ],
];
