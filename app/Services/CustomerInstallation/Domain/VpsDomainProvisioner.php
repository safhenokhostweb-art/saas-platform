<?php

namespace App\Services\CustomerInstallation\Domain;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\Dto\DomainResult;

/**
 * Phase 15-D — VPS has no account-level "domain slot" concept to create:
 * nginx `server_name` binds the domain the moment ConfigureWebServerStage
 * writes the vhost (unchanged, out of this phase's scope). This provisioner
 * is therefore a documented no-op, mirroring VpsSslProvisioner's role for
 * the Arvan-managed case — ProvisionDomainStage still runs on VPS (so the
 * pipeline shape is identical across drivers) but nothing here does real work.
 */
class VpsDomainProvisioner implements DomainProvisionerInterface
{
    public function createDomain(InstallationContext $ctx, string $domain): DomainResult
    {
        return DomainResult::skippedForDriver(
            'Domain creation is not applicable on VPS — nginx vhost binding (ConfigureWebServerStage) already covers this.'
        );
    }

    public function removeDomain(InstallationContext $ctx, string $domain, array $snapshot): void
    {
        // No-op — nothing was created here to remove.
    }

    public function domainExists(InstallationContext $ctx, string $domain): ?bool
    {
        return true; // VPS has no domain registry to query against.
    }

    public function resolveDocumentRoot(InstallationContext $ctx, string $domain): ?string
    {
        return null; // No override — WebRootResolver's own /var/www/{slug} default applies.
    }
}
