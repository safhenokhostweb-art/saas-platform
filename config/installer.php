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
];
