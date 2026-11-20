<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
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

    private readonly NginxVhostTemplate $template;

    private readonly ArvanRealIpSnippet $realIpSnippet;

    private readonly DomainDnsPreflight $preflight;

    public function __construct(
        private readonly ArvanCdnDomainDecider $arvanDecider,
        private readonly VpsSslProvisioner     $sslProvisioner,
        ?NginxVhostTemplate $template = null,
        ?ArvanRealIpSnippet $realIpSnippet = null,
        ?DomainDnsPreflight $preflight = null,
    ) {
        $this->template = $template ?? new NginxVhostTemplate();
        $this->realIpSnippet = $realIpSnippet ?? new ArvanRealIpSnippet();
        $this->preflight = $preflight ?? app(DomainDnsPreflight::class);
    }

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

        // The vhost includes the ArvanCloud real-IP snippet, a server-level
        // file. Install it when it is missing (never overwrite it) so that
        // nginx -t cannot fail on a server that has never had it.
        $snippetResult = $ctx->connection->execute($this->realIpSnippet->ensureCommand());
        if ($snippetResult->failed()) {
            return StageResult::failure(
                self::STAGE_NAME,
                'Failed to install nginx snippet ' . ArvanRealIpSnippet::REMOTE_PATH . ': ' . $snippetResult->output(),
                $this->elapsedSince($startMs),
            );
        }
        $snippetStatus = str_contains($snippetResult->stdout, ArvanRealIpSnippet::STATUS_PRESENT)
            ? ArvanRealIpSnippet::STATUS_PRESENT
            : ArvanRealIpSnippet::STATUS_INSTALLED;

        // Hosts answered next to the domain and redirected to it: its www
        // name (customer domains) and, after a domain change, the previous
        // domains. With a real certificate on a non-CDN domain, port 80 only
        // redirects to HTTPS; aliases the certificate covers get a 443
        // redirect block too.
        $redirectHosts = array_values(array_unique(array_filter([
            $this->preflight->wwwAlias($domain),
            ...$ctx->vhostAliases,
        ])));
        $arvanManaged = $this->arvanDecider->isManagedByArvan($domain);
        $forceHttps = $sslPaths !== null && !$arvanManaged;
        $httpsRedirects = $forceHttps ? $this->httpsRedirectGroups($ctx, $domain, $redirectHosts) : [];

        $nginxConfig = $this->template->render($domain, $webRoot, $phpFpmSocket, $sslPaths, $redirectHosts, $forceHttps, $httpsRedirects);
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
            'real_ip_snippet'   => $snippetStatus,
            'redirect_hosts'    => $redirectHosts,
            'force_https'       => $forceHttps,
            'https_redirects'   => array_merge([], ...array_column($httpsRedirects, 'names')),
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

    /**
     * 443 redirect blocks for alias hosts some certificate on the server
     * covers: the domain's own lineage (its www name) first, then a lineage
     * named after the alias or its bare domain (a previous domain's own
     * certificate), then the shared CDN certificate for a platform
     * subdomain. Aliases no certificate covers are redirected on port 80 only.
     *
     * @param list<string> $aliases
     * @return list<array{names: list<string>, cert: string, key: string}>
     */
    private function httpsRedirectGroups(InstallationContext $ctx, string $domain, array $aliases): array
    {
        if ($aliases === []) {
            return [];
        }

        $groups = [];
        $remaining = $aliases;
        $lineages = array_values(array_unique([$domain, ...$aliases, ...array_map(
            fn (string $a) => str_starts_with($a, 'www.') ? substr($a, 4) : $a,
            $aliases,
        )]));

        foreach ($lineages as $lineage) {
            if ($remaining === []) {
                break;
            }
            $covered = array_values(array_intersect($remaining, $this->sslProvisioner->certificateNames($ctx, $lineage)));
            if ($covered === []) {
                continue;
            }
            $groups[] = [
                'names' => $covered,
                'cert'  => "/etc/letsencrypt/live/{$lineage}/fullchain.pem",
                'key'   => "/etc/letsencrypt/live/{$lineage}/privkey.pem",
            ];
            $remaining = array_values(array_diff($remaining, $covered));
        }

        $cdn = array_values(array_filter($remaining, fn (string $a) => $this->arvanDecider->isManagedByArvan($a)));
        if ($cdn !== []) {
            $groups[] = [
                'names' => $cdn,
                'cert'  => config('customer_installation.nginx.ssl_cert', '/etc/ssl/safhenokhost/safhenokhost.crt'),
                'key'   => config('customer_installation.nginx.ssl_key', '/etc/ssl/safhenokhost/safhenokhost.key'),
            ];
        }

        return $groups;
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
