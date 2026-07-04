<?php

return [

    'install_modes' => [
        'FULL_SERVER_INSTALL' => [
            'supported_drivers' => ['ssh'],
            'required_capabilities' => ['upload', 'extract', 'env', 'database', 'migrate', 'seed'],
            'allowed_operations' => [
                'upload', 'extract', 'env', 'database', 'migrate', 'seed',
                'nginx', 'apache', 'cron', 'queue', 'ssl', 'backup', 'restore',
            ],
            'forbidden_operations' => [],
        ],
        'HOSTING_PANEL_INSTALL' => [
            'supported_drivers' => ['cpanel', 'directadmin', 'shared', 'sftp'],
            'required_capabilities' => ['file_upload', 'file_extract'],
            'required_capability_groups' => [
                ['database_management', 'manual_db_credentials'],
            ],
            'optional_capabilities' => ['cron_management', 'ssl_management', 'backup_restore'],
            'stages' => [
                ['name' => 'VALIDATE_HOSTING_TARGET'],
                ['name' => 'UPLOAD_PACKAGE', 'requires_all' => ['file_upload']],
                ['name' => 'EXTRACT_PACKAGE', 'requires_all' => ['file_extract']],
                ['name' => 'GENERATE_ENVIRONMENT'],
                ['name' => 'PREPARE_DATABASE', 'requires_all' => ['manual_db_credentials']],
                ['name' => 'EXECUTE_REMOTE_DEPLOYMENT'],
                ['name' => 'CONFIGURE_CRON_IF_SUPPORTED', 'requires_all' => ['cron_management'], 'optional' => true],
                ['name' => 'VERIFY_HTTP'],
                ['name' => 'INITIALIZE_BACKUP_IF_SUPPORTED', 'requires_all' => ['backup_restore'], 'optional' => true],
                ['name' => 'CUSTOMER_READY'],
            ],
            'allowed_operations' => [
                'upload_package', 'extract_package', 'env', 'panel_database',
                'database_import', 'remote_deployment', 'artisan_if_supported',
                'panel_cron', 'panel_ssl',
            ],
            'forbidden_operations' => [
                'require_ssh', 'require_supervisor', 'require_root', 'nginx_reload',
                'system_cron', 'hardcoded_var_www', 'linux_service_control',
            ],
        ],
    ],

    'ssh' => [
        'timeout_connect' => env('CI_SSH_TIMEOUT_CONNECT', 10),
        'timeout_execute'  => env('CI_SSH_TIMEOUT_EXECUTE', 1800),
        'timeout_upload'   => env('CI_SSH_TIMEOUT_UPLOAD', 1800),
    ],

    'cpanel' => [
        'api_port' => (int) env('CI_CPANEL_API_PORT', 2083),
        'timeout_connect' => (int) env('CI_CPANEL_TIMEOUT_CONNECT', 5),
        'timeout_request' => (int) env('CI_CPANEL_TIMEOUT_REQUEST', 15),
        'evidence_ttl_seconds' => (int) env('CI_CPANEL_EVIDENCE_TTL', 900),
    ],

    'hosting' => [
        // Relative panel roots such as public_html are portable; providers may configure their document_root.
        'document_root' => env('CI_HOSTING_DOCUMENT_ROOT', 'public_html'),
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

    /*
     * Legacy drivers (CustomerRemoteServer) — backward compatible.
     * Used by RemoteConnectionService::buildDriver().
     */
    'drivers' => [
        'ssh'    => \App\Services\CustomerInstallation\Drivers\SshRemoteDriver::class,
        'cpanel' => \App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver::class,
        'fake'   => \App\Services\CustomerInstallation\Drivers\FakeRemoteDriver::class,
    ],

    /*
     * Universal deployment drivers (DeploymentTarget) — new architecture.
     * Used by RemoteConnectionService::buildDriverForTarget().
     * Only 'ssh' and 'fake' are fully implemented; others are architecture stubs.
     */
    'deployment_drivers' => [
        'ssh'         => \App\Services\CustomerInstallation\Drivers\SshDeploymentDriver::class,
        'cpanel'      => \App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver::class,
        'directadmin' => \App\Services\CustomerInstallation\Drivers\DirectAdminDeploymentDriver::class,
        'sftp'        => \App\Services\CustomerInstallation\Drivers\SftpDeploymentDriver::class,
        'docker'      => \App\Services\CustomerInstallation\Drivers\DockerDeploymentDriver::class,
        'fake'        => \App\Services\CustomerInstallation\Drivers\FakeRemoteDriver::class,
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

    /*
     * DNS Provider configuration for automatic subdomain management.
     * Token and root_domain are stored in dns_provider_settings table (encrypted).
     * These config values control pipeline behavior only — not the provider credentials.
     */
    'dns' => [
        'skip_propagation'        => env('CI_DNS_SKIP_PROPAGATION', false),
        'propagation_max_wait_sec' => (int) env('CI_DNS_PROPAGATION_WAIT', 300),
        'propagation_poll_sec'    => (int) env('CI_DNS_PROPAGATION_POLL', 15),
    ],

    'nginx' => [
        'ssl_cert'           => env('CI_NGINX_SSL_CERT', '/etc/ssl/safhenokhost/safhenokhost.crt'),
        'ssl_key'            => env('CI_NGINX_SSL_KEY',  '/etc/ssl/safhenokhost/safhenokhost.key'),
        'fpm_socket_fallback' => env('CI_NGINX_FPM_SOCKET', '/run/php/php8.3-fpm.sock'),
    ],

];
