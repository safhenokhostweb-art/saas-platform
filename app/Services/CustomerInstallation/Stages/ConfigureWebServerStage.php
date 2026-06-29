<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ConfigureWebServerStage extends AbstractStage
{
    public function name(): string { return 'CONFIGURE_WEB_SERVER'; }
    public function label(): string { return 'پیکربندی وب‌سرور'; }
    public function estimatedSeconds(): int { return 5; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req       = $ctx->request;
            $domain    = $req->domain;
            $webRoot   = $ctx->webRoot();
            $slug      = $ctx->customerSlug();
            $configPath = "/etc/nginx/sites-available/{$slug}";
            $enablePath = "/etc/nginx/sites-enabled/{$slug}";

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

            $sslCert    = '/etc/ssl/safhenokhost/safhenokhost.crt';
            $sslKey     = '/etc/ssl/safhenokhost/safhenokhost.key';

            $nginxConfig = <<<NGINX
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
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
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
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
    client_max_body_size 50M;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
}
NGINX;

            $encoded = base64_encode($nginxConfig);

            // Write nginx config
            $writeResult = $ctx->connection->execute(
                "echo " . escapeshellarg($encoded) . " | base64 -d > " . escapeshellarg($configPath)
            );

            if ($writeResult->failed()) {
                return StageResult::failure($this->name(), 'Failed to write nginx config: ' . $writeResult->output(), $this->elapsed());
            }

            // Enable site
            $ctx->connection->execute("ln -sf " . escapeshellarg($configPath) . " " . escapeshellarg($enablePath));

            // Test nginx config
            $testResult = $ctx->connection->execute('nginx -t 2>&1');
            if ($testResult->failed() && !str_contains($testResult->output(), 'successful')) {
                // Remove invalid config
                $ctx->connection->execute("rm -f " . escapeshellarg($configPath) . " " . escapeshellarg($enablePath));
                return StageResult::failure($this->name(), 'Nginx config test failed: ' . $testResult->output(), $this->elapsed());
            }

            // Reload nginx
            $ctx->connection->execute('nginx -s reload 2>&1 || systemctl reload nginx 2>&1 || service nginx reload 2>&1');

            return StageResult::success($this->name(), [
                'domain'      => $domain,
                'config_path' => $configPath,
                'web_root'    => $webRoot,
                'ssl_enabled' => $req->sslEnabled,
            ], [
                'config_path' => $configPath,
                'enable_path' => $enablePath,
            ], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        foreach (['config_path', 'enable_path'] as $key) {
            if (!empty($snapshot[$key])) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot[$key]));
            }
        }
        try {
            $ctx->connection->execute('nginx -s reload 2>&1 || systemctl reload nginx 2>&1');
        } catch (\Throwable) {}
    }
}
