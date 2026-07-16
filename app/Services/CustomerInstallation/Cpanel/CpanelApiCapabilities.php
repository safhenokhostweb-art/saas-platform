<?php

namespace App\Services\CustomerInstallation\Cpanel;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Ssl\CpanelSslApiAdapter;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Phase 15-E — Part 2: turns "cPanel has this capability" (a static,
 * per-driver-type assumption baked into DriverCapabilities::forCpanel())
 * into "THIS cPanel account, right now, actually responds to this API" — an
 * empirical, per-server check.
 *
 * Deliberately NOT wired into DriverCapabilities/StageCapabilityGate: that
 * gate is a cheap, synchronous, pure function consulted before every single
 * Stage across the whole pipeline — making it depend on a live HTTP probe
 * would add real latency and API load to a path that currently costs
 * nothing, and would be a large, hard-to-roll-back architecture change for
 * a single phase (the Phase 15-E brief explicitly asks for small, revertible
 * changes and for the existing Provisioner/Factory structure to be
 * preserved). This class is instead a standalone diagnostic collaborator:
 * useful for an operator-facing "test this cPanel connection" check, and for
 * the one place in the current pipeline where probing genuinely improves
 * the operator-facing message quality without changing control flow —
 * CpanelSslProvisioner::issueCertificate()'s verifyAutoSslAvailable() call
 * (Part 5) already gets its answer directly from CpanelSslApiAdapter for
 * that exact reason. This class exists for every OTHER capability question
 * an operator or a future admin UI might ask, and to document, in one place,
 * how confidently each one can actually be answered.
 *
 * Confidence notes, tracked per-method:
 *   - supportsDomainInfoApi(): HIGH confidence probe — DomainInfo::domains_data
 *     is a genuine read-only query; a successful response is a direct,
 *     reliable signal.
 *   - supportsAutoSslApi(): MEDIUM confidence — delegates to
 *     CpanelSslApiAdapter::verifyAutoSslAvailable(), itself a proxy (SSL::list_certs
 *     responding) rather than a direct AutoSSL-specific probe — see that
 *     method's docblock.
 *   - supportsAddonDomainApi(): LOW confidence — there is no known safe,
 *     non-mutating UAPI call that specifically confirms AddonDomain module
 *     availability without attempting a real add/remove. This method
 *     honestly falls back to supportsDomainInfoApi() as the only available
 *     proxy (an account that cannot even query domain data is certainly not
 *     going to manage addon domains either) and documents that limitation
 *     rather than inventing an unverified probe endpoint. The REAL signal
 *     for addon-domain capability remains CpanelDomainProvisioner::createDomain()'s
 *     own try/catch — already handled, unchanged by this class.
 *
 * Results are cached per-instance per-server (keyed by server id) since a
 * single pipeline run may ask the same question more than once.
 */
class CpanelApiCapabilities
{
    /** @var array<int, bool> */
    private array $domainInfoCache = [];

    /** @var array<int, bool> */
    private array $autoSslCache = [];

    public function __construct(
        private readonly CpanelUapiClient   $uapi,
        private readonly CpanelSslApiAdapter $sslAdapter,
    ) {}

    public function supportsDomainInfoApi(CustomerRemoteServer $server): bool
    {
        if (isset($this->domainInfoCache[$server->id])) {
            return $this->domainInfoCache[$server->id];
        }

        try {
            $this->uapi->call($server, 'DomainInfo', 'domains_data');
            $result = true;
        } catch (RuntimeException|ConnectionException) {
            $result = false;
        }

        return $this->domainInfoCache[$server->id] = $result;
    }

    public function supportsAutoSslApi(CustomerRemoteServer $server): bool
    {
        if (isset($this->autoSslCache[$server->id])) {
            return $this->autoSslCache[$server->id];
        }

        return $this->autoSslCache[$server->id] = $this->sslAdapter->verifyAutoSslAvailable($server);
    }

    /**
     * See this class's docblock — honestly derived from
     * supportsDomainInfoApi(), not an independent probe. Do not treat a
     * `true` here as proof AddonDomain::addaddondomain will succeed; it only
     * means the account can be queried at all.
     */
    public function supportsAddonDomainApi(CustomerRemoteServer $server): bool
    {
        return $this->supportsDomainInfoApi($server);
    }
}
