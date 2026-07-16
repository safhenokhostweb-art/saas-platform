<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

/**
 * Phase 15-B2 — extracted from ConfigureWebServerStage's execute() body.
 *
 * Before this phase, IssueSslStage re-invoked the entire ConfigureWebServerStage
 * (via a constructor-injected Stage instance) after issuing a certificate, so
 * the newly-issued cert path would be picked up in the HTTPS server block.
 * That coupled two Stage classes together — one Stage calling another Stage's
 * execute() mid-pipeline is an anti-pattern (it hides a real collaborator
 * dependency behind a Stage-shaped object, and risks re-running unrelated
 * per-stage bookkeeping if either Stage's execute() ever grows one).
 *
 * This class is the shared collaborator both now depend on instead:
 * ConfigureWebServerStage::execute() calls write() as its entire body (no
 * behavior change), and IssueSslStage calls the same write() directly after
 * a successful issuance — no Stage-to-Stage dependency anywhere.
 */
class NginxVhostWriter
{
    private const STAGE_NAME = 'CONFIGURE_WEB_SERVER';

    public function __construct(
        private readonly ArvanCdnDomainDecider $arvanDecider,
        private readonly VpsSslProvisioner     $sslProvisioner,
    ) {}

    public function write(InstallationContext $ctx): StageResult
    {
        $startMs = (int) round(microtime(true) * 1000);

        try {
            return $this->doWrite($ctx, $startMs);
        } catch (\Throwable $e) {
            return StageResult::failure(self::STAGE_NAME, $e->getMessage(), $this->elapsedSince($startMs));
        }
    }

    private function doWrite(InstallationContext $ctx, int $startMs): StageResult
    {
        $req        = $ctx->request;
        $domain     = $req->domain;
        $webRoot    = $ctx->webRoot();
        $slug       = $ctx->customerSlug();
        $configPath = "/etc/nginx/sites-available/{$slug}";
        $enablePath = "/etc/nginx/sites-enabled/{$slug}";

        // Detect the actual PHP-FPM socket path on this server.
        // Hardcoding php8.3-fpm.sock fails on servers with different PHP versions.
        $phpFpmSocket = $this->detectPhpFpmSocket($ctx);

        $arvanCidrs = implode("\n    ", [
            'set_real_ip_from 185.143.232.0/22;',
            'set_real_ip_from 185.215.228.0/22;',
            'set_real_ip_from 93.115.0.0/20;',
            'set_real_ip_from 94.101.182.0/23;',
            'set_real_ip_from 92.114.16.0/24;',
            'set_real_ip_from 37.130.202.0/23;',
            'set_real_ip_from 103.37.196.0/22;',
            'set_real_ip_from 5.160.139.0/24;',
            'set_real_ip_from 46.224.2.0/23;',
            'set_real_ip_from 2.144.3.0/24;',
            'set_real_ip_from 217.218.31.0/24;',
            'set_real_ip_from 188.229.116.0/24;',
            'set_real_ip_from 188.0.240.0/21;',
        ]);

        $httpBlock = <<<NGINX
# Customer tenant: {$domain}
server {
    listen 80;
    server_name {$domain};
    root {$webRoot}/public;
    index index.php;

    {$arvanCidrs}
    real_ip_header Ar-Real-Ip;
    real_ip_recursive on;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:{$phpFpmSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param HTTPS \$forwarded_https;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
    client_max_body_size 50M;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
}
NGINX;

        // The HTTPS block is only written once a real, domain-appropriate
        // certificate path is known: either the shared platform certificate
        // (architecturally correct only for ArvanCloud-proxied domains — see
        // ArvanCdnDomainDecider) or a real per-domain Let's Encrypt
        // certificate already present on disk (checked live, so a re-run
        // after IssueSslStage's issuance picks it up). If neither applies
        // yet, only the HTTP block is written; IssueSslStage issues the
        // certificate afterward and calls write() again to add the HTTPS
        // block. No shared "safhenokhost" certificate is ever used for a
        // customer's own domain.
        $sslPaths = $this->resolveSslCertPaths($ctx, $domain, $req->sslEnabled);

        $httpsBlock = '';
        if ($sslPaths !== null) {
            $sslCert = $sslPaths['cert'];
            $sslKey  = $sslPaths['key'];

            $httpsBlock = <<<NGINX

# HTTPS
server {
    listen 443 ssl;
    server_name {$domain};
    root {$webRoot}/public;
    index index.php;

    ssl_certificate     {$sslCert};
    ssl_certificate_key {$sslKey};
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    {$arvanCidrs}
    real_ip_header Ar-Real-Ip;
    real_ip_recursive on;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:{$phpFpmSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param HTTPS \$https;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
    client_max_body_size 50M;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
}
NGINX;
        }

        $nginxConfig = $httpBlock . $httpsBlock;
        $encoded     = base64_encode($nginxConfig);

        // Write nginx config
        $writeResult = $ctx->connection->execute(
            "echo " . escapeshellarg($encoded) . " | base64 -d > " . escapeshellarg($configPath)
        );

        if ($writeResult->failed()) {
            return StageResult::failure(self::STAGE_NAME, 'Failed to write nginx config: ' . $writeResult->output(), $this->elapsedSince($startMs));
        }

        // Enable site
        $ctx->connection->execute("ln -sf " . escapeshellarg($configPath) . " " . escapeshellarg($enablePath));

        // Test nginx config
        $testResult = $ctx->connection->execute('nginx -t 2>&1');
        if ($testResult->failed() && !str_contains($testResult->output(), 'successful')) {
            // Remove invalid config
            $ctx->connection->execute("rm -f " . escapeshellarg($configPath) . " " . escapeshellarg($enablePath));
            return StageResult::failure(self::STAGE_NAME, 'Nginx config test failed: ' . $testResult->output(), $this->elapsedSince($startMs));
        }

        // Reload nginx
        $ctx->connection->execute('nginx -s reload 2>&1 || systemctl reload nginx 2>&1 || service nginx reload 2>&1');

        return StageResult::success(self::STAGE_NAME, [
            'domain'            => $domain,
            'config_path'       => $configPath,
            'web_root'          => $webRoot,
            'ssl_enabled'       => $req->sslEnabled,
            'ssl_block_written' => $sslPaths !== null,
        ], [
            'config_path' => $configPath,
            'enable_path' => $enablePath,
        ], $this->elapsedSince($startMs));
    }

    /**
     * @return array{cert: string, key: string}|null
     */
    private function resolveSslCertPaths(InstallationContext $ctx, string $domain, bool $sslEnabled): ?array
    {
        if (!$sslEnabled) {
            return null;
        }

        if ($this->arvanDecider->isManagedByArvan($domain)) {
            return [
                'cert' => config('customer_installation.nginx.ssl_cert', '/etc/ssl/safhenokhost/safhenokhost.crt'),
                'key'  => config('customer_installation.nginx.ssl_key', '/etc/ssl/safhenokhost/safhenokhost.key'),
            ];
        }

        $status = $this->sslProvisioner->checkCertificate($ctx, $domain);
        if (!$status->active) {
            return null;
        }

        return [
            'cert' => "/etc/letsencrypt/live/{$domain}/fullchain.pem",
            'key'  => "/etc/letsencrypt/live/{$domain}/privkey.pem",
        ];
    }

    private function detectPhpFpmSocket(InstallationContext $ctx): string
    {
        // Get the PHP version actually running on the remote server
        $versionResult = $ctx->connection->execute(
            "php -r \"echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;\" 2>/dev/null"
        );
        $phpVersion = trim($versionResult->stdout);

        if (!preg_match('/^\d+\.\d+$/', $phpVersion)) {
            $phpVersion = '8.3';
        }

        // Try both common socket locations for the detected version
        foreach (["/run/php/php{$phpVersion}-fpm.sock", "/var/run/php/php{$phpVersion}-fpm.sock"] as $candidate) {
            $check = $ctx->connection->execute("test -S " . escapeshellarg($candidate) . " && echo exists || echo absent");
            if (str_contains($check->stdout, 'exists')) {
                return $candidate;
            }
        }

        // No socket for detected version — find any available php-fpm socket
        $findResult = $ctx->connection->execute(
            "ls /run/php/php*-fpm.sock 2>/dev/null || ls /var/run/php/php*-fpm.sock 2>/dev/null | head -1"
        );
        $found = trim($findResult->stdout);
        if (!empty($found) && str_contains($found, '.sock')) {
            return strtok($found, "\n");
        }

        // Last resort: use the configured fallback (PHP 8.3)
        return config('customer_installation.nginx.fpm_socket_fallback', "/run/php/php{$phpVersion}-fpm.sock");
    }

    private function elapsedSince(int $startMs): int
    {
        return (int) round(microtime(true) * 1000) - $startMs;
    }
}
