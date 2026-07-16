<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Phase 15-D — thin wrapper around the two UAPI SSL calls
 * CpanelSslProvisioner needs, isolating the unverified API surface (see
 * CpanelSslProvisioner's docblock — confidence LOW on the exact function
 * names/parameters) behind a single, narrow seam.
 *
 * Goal: if the guessed UAPI function names turn out wrong once verified
 * against a live cPanel account, only THIS class needs to change — the
 * pipeline (CpanelSslProvisioner, IssueSslStage, ProvisionDomainStage) keeps
 * calling certificateExists()/certificateStatus()/triggerAutoSslCheck()
 * exactly as before. This mirrors why CpanelUapiClient itself already
 * exists as a seam between every cPanel Provisioner and the raw HTTP call.
 *
 * Every method here may throw (RuntimeException for a UAPI-level error,
 * ConnectionException for a transport failure) — callers decide how to
 * degrade, exactly like CpanelUapiClient::call() itself.
 */
class CpanelSslApiAdapter
{
    public function __construct(private readonly CpanelUapiClient $uapi) {}

    /** @throws RuntimeException|ConnectionException */
    public function triggerAutoSslCheck(CustomerRemoteServer $server, string $domain): void
    {
        $this->uapi->call($server, 'SSL', 'start_autossl_check', ['domain' => $domain]);
    }

    /** @throws RuntimeException|ConnectionException */
    public function listCertificates(CustomerRemoteServer $server, string $domain): array
    {
        return $this->uapi->call($server, 'SSL', 'list_certs', ['domain' => $domain]);
    }

    /**
     * Read-only: does a certificate already cover $domain? Returns null
     * (unverifiable) on any failure — never throws.
     */
    public function certificateExists(CustomerRemoteServer $server, string $domain): ?bool
    {
        $entry = $this->certificateStatus($server, $domain);

        return $entry === null ? null : ($entry !== []);
    }

    /**
     * Read-only: certificate metadata for $domain (currently just
     * not_after), or [] if no certificate is found, or null if the UAPI
     * call itself failed (unverifiable — distinct from "confirmed absent").
     * Never throws.
     */
    public function certificateStatus(CustomerRemoteServer $server, string $domain): ?array
    {
        try {
            $data = $this->listCertificates($server, $domain);
        } catch (RuntimeException|ConnectionException) {
            return null;
        }

        return $this->findCertForDomain($data, $domain) ?? [];
    }

    /**
     * Phase 15-E — Part 5: a non-mutating signal for "does the SSL UAPI
     * module even respond on this account", distinct from "is a cert
     * present for this domain". Deliberately reuses SSL::list_certs (already
     * a read-only call this class makes elsewhere) rather than triggering
     * SSL::start_autossl_check just to test availability — probing with a
     * mutating-shaped call would be wasteful and, if that guessed function
     * name is ever wrong, would throw for a reason unrelated to what this
     * method claims to check. If list_certs itself is unreachable/unknown,
     * start_autossl_check (same module) is exceedingly unlikely to work
     * either — a reasonable, honest proxy, not a direct probe of AutoSSL
     * specifically (see CpanelSslProvisioner's docblock: no independently
     * verified "is AutoSSL enabled" endpoint is known).
     */
    public function verifyAutoSslAvailable(CustomerRemoteServer $server): bool
    {
        try {
            $this->uapi->call($server, 'SSL', 'list_certs', []);
            return true;
        } catch (RuntimeException|ConnectionException) {
            return false;
        }
    }

    /**
     * Phase 15-E — Part 5: stronger than certificateExists() — also treats a
     * certificate with a parseable, already-past not_after as NOT issued
     * (expired). A certificate entry with no not_after metadata at all is
     * treated as issued (present is present; this pipeline never depends on
     * exact expiry accounting today — see the Phase 15-A renewal design
     * note). Never throws.
     */
    public function verifyCertificateIssued(CustomerRemoteServer $server, string $domain): bool
    {
        $status = $this->certificateStatus($server, $domain);

        if (empty($status)) {
            return false;
        }

        $notAfter = $status['not_after'] ?? null;
        if ($notAfter === null) {
            return true;
        }

        $expiry = strtotime((string) $notAfter);

        return $expiry === false || $expiry > time();
    }

    /**
     * UAPI list responses vary between plain string arrays and arrays of
     * objects keyed by entity type — handle both defensively, same posture
     * as CpanelDatabaseProvisioner::listContains().
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
}
