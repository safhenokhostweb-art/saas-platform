<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Ssl\Dto\SslResult;
use App\Services\CustomerInstallation\Ssl\Dto\SslStatus;

/**
 * Phase 15-B1 — real Let's Encrypt provisioning for VPS/Docker (SSH
 * transport), via the `certbot` binary already expected to exist on the
 * target server (see config('customer_installation.ssl.certbot_bin')).
 *
 * Uses the webroot challenge (`certbot certonly --webroot`), not the nginx
 * plugin (`certbot --nginx`): the nginx plugin edits the vhost file itself,
 * which would fight with ConfigureWebServerStage's own template-based
 * rewrite (Phase 15-B1) and make the two sources of truth for the vhost
 * content diverge. Webroot mode only requires port 80 already serving
 * {webRoot}/public — satisfied because ConfigureWebServerStage always runs
 * before this stage — and never touches nginx config; ConfigureWebServerStage
 * (re-invoked by IssueSslStage after a successful issuance) is the only
 * writer of the HTTPS server block.
 *
 * Security: certificate and key are never downloaded from the remote server.
 * They stay in Certbot's own /etc/letsencrypt/live/{domain}/ directory;
 * this class only ever returns that path string.
 */
class VpsSslProvisioner implements SslProvisionerInterface
{
    /** Run by certbot after every successful issuance or renewal. */
    public const DEPLOY_HOOK = 'systemctl reload nginx';

    /**
     * Phase 15-B1 — deliberately does NOT pre-check checkCertificate() before
     * running certbot: `certbot certonly` is itself idempotent (it skips
     * re-issuance and exits 0 with "not yet due for renewal" when a valid
     * certificate already covers the domain), and the orchestrator's own
     * stage-resume logic (CustomerInstallation::hasCompletedStage()) already
     * prevents this stage from re-running at all once ISSUE_SSL has
     * completed successfully for an installation. A provisioner-level
     * pre-check would be redundant defense-in-depth, not load-bearing —
     * simpler to let certbot own that idempotency itself.
     */
    public function issueCertificate(InstallationContext $ctx, string $domain): SslResult
    {
        if (!$this->certbotAvailable($ctx)) {
            return SslResult::skipped(
                'certbot is not installed on this server — SSL issuance skipped. '
                . 'Install certbot and retry the installation, or install a certificate manually.'
            );
        }

        $webRoot = $ctx->webRoot();
        $email   = trim($ctx->request->email);
        $bin     = config('customer_installation.ssl.certbot_bin', 'certbot');

        // One lineage per site domain (--cert-name keeps the path at
        // /etc/letsencrypt/live/{domain}/ when www is added later, --expand
        // lets it grow). The deploy hook is stored in the renewal config, so
        // certbot's own timer reloads nginx after every automatic renewal.
        $names = array_values(array_unique(array_filter([$domain, ...$ctx->certificateAliases])));
        $nameArgs = implode('', array_map(fn (string $name) => ' -d ' . escapeshellarg($name), $names));

        $cmd = escapeshellcmd($bin) . ' certonly --webroot -w ' . escapeshellarg($webRoot . '/public')
            . ' --cert-name ' . escapeshellarg($domain) . $nameArgs . ' --expand'
            . ' --non-interactive --agree-tos '
            // The onboarding wizard records no email; certbot refuses -m ''.
            . ($email !== '' ? '-m ' . escapeshellarg($email) : '--register-unsafely-without-email')
            . ' --no-eff-email --deploy-hook ' . escapeshellarg(self::DEPLOY_HOOK) . ' 2>&1';

        $result = $ctx->connection->execute($cmd);

        // Certbot's own exit code is authoritative when it fails, but the
        // one true signal of success is the certificate actually existing
        // afterward — a stale/failed run can still exit 0 in some certbot
        // versions (e.g. "no action taken" on a pre-existing valid cert).
        $verify = $this->checkCertificate($ctx, $domain);
        if (!$verify->active) {
            return SslResult::failed(
                'Certbot issuance failed for ' . $domain . ': ' . $this->redact($result->output()),
                ['exit_code' => $result->exitCode]
            );
        }

        return SslResult::issued($this->certPath($domain), $this->keyPath($domain), [
            'provider' => 'letsencrypt',
        ]);
    }

    public function renewCertificate(InstallationContext $ctx, string $domain): SslResult
    {
        if (!$this->certbotAvailable($ctx)) {
            return SslResult::skipped('certbot is not installed on this server — renewal skipped.');
        }

        $bin = config('customer_installation.ssl.certbot_bin', 'certbot');
        $cmd = escapeshellcmd($bin) . ' renew --cert-name ' . escapeshellarg($domain)
            . ' --non-interactive --deploy-hook ' . escapeshellarg(self::DEPLOY_HOOK) . ' 2>&1';

        $result = $ctx->connection->execute($cmd);

        $status = $this->checkCertificate($ctx, $domain);
        if (!$status->active) {
            return SslResult::failed('Certbot renewal failed for ' . $domain . ': ' . $this->redact($result->output()));
        }

        return SslResult::issued($this->certPath($domain), $this->keyPath($domain), ['provider' => 'letsencrypt']);
    }

    public function checkCertificate(InstallationContext $ctx, string $domain): SslStatus
    {
        try {
            $certFile = $this->certPath($domain);
            $exists = $ctx->connection->execute('test -f ' . escapeshellarg($certFile) . ' && echo yes || echo no');

            if (!str_contains($exists->stdout, 'yes')) {
                return SslStatus::none();
            }

            $result = $ctx->connection->execute(
                'openssl x509 -enddate -noout -in ' . escapeshellarg($certFile) . ' 2>/dev/null'
            );

            return SslStatus::active('letsencrypt', $this->parseNotAfter($result->stdout), 'Certificate present on server');
        } catch (\Throwable) {
            return SslStatus::unknown();
        }
    }

    /**
     * DNS names (SANs) of the certificate lineage $certName, lowercased; []
     * when it does not exist or cannot be read. NginxVhostWriter uses it to
     * decide which alias hosts get an HTTPS redirect block.
     *
     * @return list<string>
     */
    public function certificateNames(InstallationContext $ctx, string $certName): array
    {
        try {
            $result = $ctx->connection->execute(
                'openssl x509 -in ' . escapeshellarg($this->certPath($certName)) . ' -noout -text 2>/dev/null | grep -o ' . escapeshellarg('DNS:[^,]*')
            );
        } catch (\Throwable) {
            return [];
        }

        preg_match_all('/DNS:([A-Za-z0-9.*-]+)/', (string) $result->stdout, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    private function certbotAvailable(InstallationContext $ctx): bool
    {
        $bin = config('customer_installation.ssl.certbot_bin', 'certbot');
        $result = $ctx->connection->execute('command -v ' . escapeshellarg($bin) . ' >/dev/null 2>&1 && echo present || echo missing');

        return str_contains($result->stdout, 'present');
    }

    private function certPath(string $domain): string
    {
        return "/etc/letsencrypt/live/{$domain}/fullchain.pem";
    }

    private function keyPath(string $domain): string
    {
        return "/etc/letsencrypt/live/{$domain}/privkey.pem";
    }

    /** e.g. "notAfter=Sep  7 00:00:00 2026 GMT" -> "2026-09-07" (best-effort, never throws). */
    private function parseNotAfter(string $opensslOutput): ?string
    {
        if (!preg_match('/notAfter=(.+)/', trim($opensslOutput), $m)) {
            return null;
        }

        $timestamp = strtotime(trim($m[1]));

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * Certbot's own stdout/stderr does not print key material for the
     * commands this class runs, but this is kept as defense-in-depth —
     * mirroring InstallationStateManager::sanitizeMessage() — since this
     * string can end up in customer_installation_logs via StageResult.
     */
    private function redact(string $output): string
    {
        return preg_replace('/-----BEGIN [^-]+-----.*?-----END [^-]+-----/s', '[REDACTED KEY MATERIAL]', $output) ?? $output;
    }
}
