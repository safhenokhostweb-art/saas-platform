<?php

return [

    'ssh' => [
        'timeout_connect' => env('CI_SSH_TIMEOUT_CONNECT', 10),
        'timeout_execute'  => env('CI_SSH_TIMEOUT_EXECUTE', 1800),
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

    /*
     * Phase 14-B1 — single driver registry. Previously this file had two
     * parallel registries ('drivers' for CustomerRemoteServer and
     * 'deployment_drivers' for DeploymentTarget) that could disagree about
     * which server types were registered — this was the root cause of the
     * "No driver registered for server type: cpanel" incident (Installation
     * #44), because the legacy lookup never saw the cpanel/directadmin
     * entries the newer one already had.
     *
     * DriverFactory is now the ONLY class that reads this key. Do not add a
     * second registry — add the driver here and mark it implemented in
     * App\Services\CustomerInstallation\Enums\DriverType::isImplemented().
     *
     * 'ssh', 'cpanel', and 'fake' are implemented (Phase 14-C added cpanel,
     * over SSH transport — see CpanelDeploymentDriver's docblock).
     * directadmin/sftp/docker are registered (so DriverFactory can report a
     * precise "not yet implemented" error) but are architecture stubs — see
     * each class's docblock in App\Services\CustomerInstallation\Drivers.
     */
    'drivers' => [
        'ssh'         => \App\Services\CustomerInstallation\Drivers\SshRemoteDriver::class,
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
