<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Ssl\Dto\SslResult;
use App\Services\CustomerInstallation\Ssl\Dto\SslStatus;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Phase 15-B1/15-B2/15-D — cPanel AutoSSL trigger/check via UAPI, no WHM/root.
 *
 * Phase 15-D — no longer calls CpanelUapiClient directly. All UAPI SSL calls
 * now go through CpanelSslApiAdapter, so that if the exact function names
 * below turn out wrong once verified against a live cPanel account, only
 * the Adapter needs to change — this class, IssueSslStage, and
 * PostInstallValidationService are untouched. See CpanelSslApiAdapter's
 * docblock for the seam rationale, and its own docblock for the same
 * unverified-API-surface caveat that applied here before this refactor
 * (confidence LOW on `start_autossl_check`/`list_certs`).
 *
 * Regardless of whether the exact function name is correct, every call here
 * is wrapped so that ANY UAPI-level or transport-level failure — including
 * "unknown function", AutoSSL not enabled on the hosting package (a WHM/root
 * decision this class can never make), or a timeout — degrades to a graceful
 * skip (SslResult::skipped() / SslStatus::unknown()), never a crash.
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
    public function __construct(private readonly CpanelSslApiAdapter $adapter) {}

    public function issueCertificate(InstallationContext $ctx, string $domain): SslResult
    {
        if (!config('customer_installation.ssl.cpanel_autossl_enabled', true)) {
            return SslResult::skipped(
                'cPanel AutoSSL provisioning is disabled via CI_CPANEL_AUTOSSL_ENABLED=false — '
                . 'install a certificate manually or ask the hosting provider.'
            );
        }

        $server = $this->server($ctx);

        // Phase 15-E — Part 5: distinguish "the SSL UAPI module does not
        // respond on this account at all" (this check) from "the module
        // responded but the actual trigger call failed" (the catch block
        // below) — both still degrade to a safe skip, but with a message
        // that tells the operator which situation they are actually in.
        if (!$this->adapter->verifyAutoSslAvailable($server)) {
            return SslResult::skipped(
                'cPanel SSL API is not reachable on this account for ' . $domain . ' — AutoSSL may not be '
                . 'available on this cPanel version/package. Install a certificate manually or ask the '
                . 'hosting provider.'
            );
        }

        try {
            $this->adapter->triggerAutoSslCheck($server, $domain);
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
        $status = $this->adapter->certificateStatus($this->server($ctx), $domain);

        if ($status === null) {
            return SslStatus::unknown('cPanel SSL status could not be verified via UAPI');
        }

        if ($status === []) {
            return SslStatus::none('No certificate found on this cPanel account for ' . $domain);
        }

        return SslStatus::active('cpanel_autossl', $status['not_after'] ?? null, 'Certificate present on cPanel account');
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
