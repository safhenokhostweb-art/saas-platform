<?php

namespace App\Services\CustomerInstallation\Domain;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\Dto\DomainResult;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Phase 15-D — cPanel domain/subdomain/addon-domain provisioning via UAPI,
 * no WHM/root. Handles all three of the account-level domain shapes:
 *
 *   1. Main domain  — the domain IS the account's already-registered primary
 *      domain. Nothing to create; domainExists() is authoritative here.
 *   2. Subdomain    — the target domain is a direct child of the account's
 *      main domain (e.g. shop.example.com under example.com). Created via
 *      UAPI SubDomain::addsubdomain.
 *   3. Addon domain — a completely separate registered domain hosted on the
 *      same cPanel account (e.g. othercompany.com). Created via UAPI
 *      AddonDomain::addaddondomain.
 *
 * IMPORTANT — unverified API surface, confidence LOW (same posture as
 * CpanelSslProvisioner, see its docblock): the exact UAPI module/function
 * names and parameter shapes below (`DomainInfo::domains_data`,
 * `SubDomain::addsubdomain`, `AddonDomain::addaddondomain`,
 * `SubDomain::delsubdomain`, `AddonDomain::deladdondomain`) are this
 * implementation's best-documented understanding of cPanel's UAPI, NOT
 * verified against a live cPanel account from this environment. Unlike SSL
 * (which degrades to a soft skip on any failure), a failed domain creation
 * here is reported as DomainResult::failed() and ProvisionDomainStage
 * treats it as a real pipeline failure — because without a resolved domain,
 * no later stage can run correctly. Verify against a live/trial cPanel
 * account before relying on this in production, the same way
 * CpanelDatabaseProvisionerTest verified the Mysql module with Http::fake().
 *
 * No WHM/root credential is ever used — only the same cPanel account
 * credential (username + password/API token) already stored on
 * CustomerRemoteServer for database/cron/SSL provisioning.
 */
class CpanelDomainProvisioner implements DomainProvisionerInterface
{
    public function __construct(private readonly CpanelUapiClient $uapi) {}

    public function createDomain(InstallationContext $ctx, string $domain): DomainResult
    {
        $domain = $this->normalize($domain);
        $server = $this->server($ctx);

        $accountData = $this->fetchAccountDomainData($server);
        if ($accountData === null) {
            return DomainResult::failed("Could not query cPanel account domain data (DomainInfo) for {$domain}.");
        }

        $mainDomain = $this->normalize($accountData['main_domain'] ?? '');

        // Case 1: main domain — already exists by definition, nothing to create.
        if ($mainDomain !== '' && $domain === $mainDomain) {
            $docRoot = $this->documentRootFor($accountData, $domain) ?? "/home/{$server->username}/public_html";
            return DomainResult::alreadyExists($docRoot, 'main');
        }

        // Idempotency: already provisioned as a sub/addon domain on a previous run.
        $existingRoot = $this->documentRootFor($accountData, $domain);
        if ($existingRoot !== null) {
            return DomainResult::alreadyExists($existingRoot, $this->domainTypeFrom($accountData, $domain));
        }

        try {
            if ($mainDomain !== '' && $this->isDirectSubdomainOf($domain, $mainDomain)) {
                return $this->createSubdomain($server, $domain, $mainDomain);
            }

            return $this->createAddonDomain($server, $domain);
        } catch (RuntimeException|ConnectionException $e) {
            return DomainResult::failed("cPanel domain creation failed for {$domain}: " . $e->getMessage());
        }
    }

    public function removeDomain(InstallationContext $ctx, string $domain, array $snapshot): void
    {
        $server = $this->server($ctx);
        $type   = $snapshot['type'] ?? null;

        try {
            if ($type === 'subdomain') {
                $this->uapi->call($server, 'SubDomain', 'delsubdomain', [
                    'domain' => $this->normalize($domain),
                ]);
            } elseif ($type === 'addon') {
                $this->uapi->call($server, 'AddonDomain', 'deladdondomain', [
                    'domain'    => $this->normalize($domain),
                    'subdomain' => $snapshot['meta']['subdomain_prefix'] ?? '',
                ]);
            }
            // 'main' type is never removed — see ProvisionDomainStage's
            // created_by_installer guard, which never even calls this for a
            // pre-existing main domain.
        } catch (RuntimeException|ConnectionException) {
            // Best-effort — matches every other Provisioner's rollback contract.
        }
    }

    public function domainExists(InstallationContext $ctx, string $domain): ?bool
    {
        $accountData = $this->fetchAccountDomainData($this->server($ctx));
        if ($accountData === null) {
            return null;
        }

        $domain = $this->normalize($domain);
        if ($this->normalize($accountData['main_domain'] ?? '') === $domain) {
            return true;
        }

        return $this->documentRootFor($accountData, $domain) !== null;
    }

    public function resolveDocumentRoot(InstallationContext $ctx, string $domain): ?string
    {
        $server      = $this->server($ctx);
        $accountData = $this->fetchAccountDomainData($server);
        if ($accountData === null) {
            return null;
        }

        $domain = $this->normalize($domain);
        if ($this->normalize($accountData['main_domain'] ?? '') === $domain) {
            return $this->documentRootFor($accountData, $domain) ?? "/home/{$server->username}/public_html";
        }

        return $this->documentRootFor($accountData, $domain);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function createSubdomain(CustomerRemoteServer $server, string $domain, string $mainDomain): DomainResult
    {
        $label = rtrim(substr($domain, 0, -strlen('.' . $mainDomain)), '.');

        $this->uapi->call($server, 'SubDomain', 'addsubdomain', [
            'domain'     => $label,
            'rootdomain' => $mainDomain,
        ]);

        $docRoot = "/home/{$server->username}/public_html/{$label}";

        return DomainResult::created($docRoot, 'subdomain', ['subdomain_prefix' => $label]);
    }

    private function createAddonDomain(CustomerRemoteServer $server, string $domain): DomainResult
    {
        // cPanel's AddonDomain::addaddondomain requires an internal
        // "subdomain" label distinct from the addon domain name itself —
        // conventionally the addon domain's own name with dots replaced,
        // to keep it unique and filesystem-safe.
        $label = Str::slug(str_replace('.', '-', $domain));
        $dir   = "public_html/{$domain}";

        $this->uapi->call($server, 'AddonDomain', 'addaddondomain', [
            'newdomain' => $domain,
            'subdomain' => $label,
            'dir'       => $dir,
        ]);

        $docRoot = "/home/{$server->username}/{$dir}";

        return DomainResult::created($docRoot, 'addon', ['subdomain_prefix' => $label]);
    }

    /**
     * Single UAPI call backing domainExists()/resolveDocumentRoot()/
     * createDomain()'s idempotency check — never queried twice per
     * operation. Returns null (unverifiable) on any failure; callers must
     * never treat null as "does not exist".
     */
    private function fetchAccountDomainData(CustomerRemoteServer $server): ?array
    {
        try {
            return $this->uapi->call($server, 'DomainInfo', 'domains_data');
        } catch (RuntimeException|ConnectionException) {
            return null;
        }
    }

    /**
     * UAPI list/detail shapes vary between cPanel versions — handle
     * defensively, same posture as CpanelDatabaseProvisioner::listContains()
     * and CpanelSslProvisioner::findCertForDomain().
     */
    private function documentRootFor(array $accountData, string $domain): ?string
    {
        $domains = $accountData['domains'] ?? $accountData['sub_domains'] ?? [];

        foreach ($domains as $key => $entry) {
            $entryDomain = is_array($entry) ? ($entry['domain'] ?? $key) : $entry;
            if ($this->normalize((string) $entryDomain) !== $domain) {
                continue;
            }

            if (is_array($entry)) {
                return $entry['documentroot'] ?? $entry['document_root'] ?? null;
            }
        }

        return null;
    }

    private function domainTypeFrom(array $accountData, string $domain): ?string
    {
        $domains = $accountData['domains'] ?? [];

        foreach ($domains as $key => $entry) {
            $entryDomain = is_array($entry) ? ($entry['domain'] ?? $key) : $entry;
            if ($this->normalize((string) $entryDomain) === $domain && is_array($entry)) {
                return $entry['domain_type'] ?? null;
            }
        }

        return null;
    }

    private function isDirectSubdomainOf(string $domain, string $mainDomain): bool
    {
        return str_ends_with($domain, '.' . $mainDomain);
    }

    private function normalize(string $domain): string
    {
        return strtolower(trim($domain, '. '));
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
