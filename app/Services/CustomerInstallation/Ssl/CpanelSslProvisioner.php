<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Ssl\Dto\SslResult;
use App\Services\CustomerInstallation\Ssl\Dto\SslStatus;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Phase 15-B1/15-B2 — cPanel AutoSSL trigger/check via UAPI, no WHM/root.
 *
 * IMPORTANT — unverified API surface, confidence LOW: the UAPI `SSL` module
 * function names used below (`start_autossl_check`, `list_certs`) are this
 * implementation's best-documented understanding of cPanel's end-user "Run
 * AutoSSL Check" feature, but they have NOT been exercised against a live
 * cPanel account from this environment (no live cPanel access was available
 * during implementation — see the Phase 15-A audit). AutoSSL's UAPI surface
 * has also changed across cPanel versions historically, so a name that is
 * correct on one target version may not be on another.
 *
 * How to verify before removing this notice: register a real (or trial)
 * cPanel account with a test domain, point CpanelUapiClient at it, and run
 * issueCertificate()/checkCertificate() with a debugger or temporary
 * var_dump() of the raw UAPI response — then update
 * CpanelDatabaseProvisionerTest-style Http::fake() assertions here to lock
 * in the confirmed shape (see CpanelDatabaseProvisionerTest for the pattern
 * this class's own tests already follow structurally, just against
 * unconfirmed endpoint names).
 *
 * Regardless of whether the exact function name is correct, every call here
 * is wrapped so that ANY UAPI-level or transport-level failure — including
 * "unknown function", AutoSSL not enabled on the hosting package (a WHM/root
 * decision this class can never make), or a timeout — degrades to a graceful
 * skip (SslResult::skipped() / SslStatus::unknown()), never a crash. This
 * satisfies the Phase 15-B1 requirement independent of API-name accuracy.
 *
 * Zero-deploy escape hatch: config('customer_installation.ssl.cpanel_autossl_enabled')
 * (env CI_CPANEL_AUTOSSL_ENABLED, default true) lets an operator disable
 * this provisioner entirely without a code change if the UAPI calls above
 * turn out incorrect in production before they can be fixed and redeployed.
 *
 * No WHM/root credential is ever used or required — only the same cPanel
 * account credential (username + password/API token) already stored on
 * CustomerRemoteServer for database/cron provisioning.
 */
class CpanelSslProvisioner implements SslProvisionerInterface
{
    public function __construct(private readonly CpanelUapiClient $uapi) {}

    public function issueCertificate(InstallationContext $ctx, string $domain): SslResult
    {
        if (!config('customer_installation.ssl.cpanel_autossl_enabled', true)) {
            return SslResult::skipped(
                'cPanel AutoSSL provisioning is disabled via CI_CPANEL_AUTOSSL_ENABLED=false — '
                . 'install a certificate manually or ask the hosting provider.'
            );
        }

        try {
            $this->uapi->call($this->server($ctx), 'SSL', 'start_autossl_check', [
                'domain' => $domain,
            ]);
        } catch (RuntimeException|ConnectionException $e) {
            return SslResult::skipped(
                'cPanel AutoSSL check could not be triggered for ' . $domain . ': ' . $e->getMessage()
                . ' — AutoSSL may not be enabled on this hosting package. Contact the hosting provider '
                . 'or install a certificate manually.'
            );
        }

        // AutoSSL issuance is asynchronous on cPanel's side (it runs the
        // domain validation + issuance in the background) — this call only
        // confirms the check was accepted, not that a certificate exists
        // yet. checkCertificate() is the authoritative "is it live" answer.
        return SslResult::issued($this->certPathLabel($domain), $this->certPathLabel($domain), [
            'provider' => 'cpanel_autossl',
            'note'     => 'AutoSSL check triggered — issuance is asynchronous on cPanel; verify via checkCertificate() before assuming HTTPS is live.',
        ]);
    }

    public function renewCertificate(InstallationContext $ctx, string $domain): SslResult
    {
        // cPanel AutoSSL renews automatically on its own schedule once
        // enabled — there is no separate "renew" UAPI action distinct from
        // re-running the same check. Re-trigger the check as a best-effort
        // nudge; never treated as a hard requirement.
        return $this->issueCertificate($ctx, $domain);
    }

    public function checkCertificate(InstallationContext $ctx, string $domain): SslStatus
    {
        try {
            $data = $this->uapi->call($this->server($ctx), 'SSL', 'list_certs', ['domain' => $domain]);
        } catch (RuntimeException|ConnectionException) {
            return SslStatus::unknown('cPanel SSL status could not be verified via UAPI');
        }

        $cert = $this->findCertForDomain($data, $domain);
        if ($cert === null) {
            return SslStatus::none('No certificate found on this cPanel account for ' . $domain);
        }

        return SslStatus::active(
            'cpanel_autossl',
            $cert['not_after'] ?? null,
            'Certificate present on cPanel account'
        );
    }

    /**
     * UAPI list responses vary in shape between cPanel versions — handle
     * defensively, same posture as CpanelDatabaseProvisioner::listContains().
     */
    private function findCertForDomain(array $data, string $domain): ?array
    {
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $domains = $entry['domains'] ?? ($entry['domain'] ?? null);
            $domains = is_array($domains) ? $domains : [$domains];

            if (in_array($domain, $domains, true)) {
                return [
                    'not_after' => $entry['not_after'] ?? $entry['expires_at'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * cPanel manages certificate storage internally (typically under
     * /var/cpanel/ssl/, root-only) — this class never has or reports a real
     * filesystem path, only a label. Never a certificate/key content.
     */
    private function certPathLabel(string $domain): string
    {
        return "cpanel-autossl:{$domain}";
    }

    private function server(InstallationContext $ctx): CustomerRemoteServer
    {
        $server = CustomerRemoteServer::find($ctx->installation->remote_server_id);

        if (!$server) {
            throw new RuntimeException('CustomerRemoteServer record not found for this installation.');
        }

        return $server;
    }
}
