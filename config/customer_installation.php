<?php

return [

    'ssh' => [
        'timeout_connect' => env('CI_SSH_TIMEOUT_CONNECT', 10),
        'timeout_execute'  => env('CI_SSH_TIMEOUT_EXECUTE', 600),
        'timeout_upload'   => env('CI_SSH_TIMEOUT_UPLOAD', 1800),
    ],

    'upload' => [
        'chunk_size_bytes' => 4 * 1024 * 1024,
        'kbps_limit'       => env('CI_UPLOAD_KBPS_LIMIT', 0),
        'retry_attempts'   => 3,
        'retry_delay_ms'   => 2000,
    ],

    'package' => [
        'arvan_prefix'     => env('CI_PACKAGE_ARVAN_PREFIX', 'saas-platform/releases/customer-site'),
        'local_temp_path'  => storage_path('app/install-packages'),
    ],

    'encryption' => [
        'algorithm'        => 'aes-256-gcm',
        'master_key_env'   => 'INSTALLER_MASTER_KEY',
    ],

    'pipeline' => [
        'redis_progress_ttl' => 86400,
        'resume_enabled'     => true,
    ],

    'drivers' => [
        'ssh'  => \App\Services\CustomerInstallation\Drivers\SshRemoteDriver::class,
        'fake' => \App\Services\CustomerInstallation\Drivers\FakeRemoteDriver::class,
    ],

    'required_php_extensions' => [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd',
        'intl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql',
        'redis', 'tokenizer', 'xml', 'zip', 'pcntl', 'posix',
    ],

    'disk_minimums' => [
        'web_root_bytes' => 2 * 1024 * 1024 * 1024,
        'tmp_bytes'      => 512 * 1024 * 1024,
    ],

];
