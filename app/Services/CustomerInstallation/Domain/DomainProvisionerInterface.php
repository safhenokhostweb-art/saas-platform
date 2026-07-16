<?php

namespace App\Services\CustomerInstallation\Domain;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\Dto\DomainResult;

/**
 * Phase 15-D — hosting-agnostic domain provisioning contract, mirroring
 * DatabaseProvisionerInterface/SslProvisionerInterface. ProvisionDomainStage
 * calls only this interface; it never knows whether the underlying mechanism
 * is a no-op (VPS — nginx vhost creation already covers this, see
 * ConfigureWebServerStage) or a real cPanel UAPI SubDomain/AddonDomain call.
 */
interface DomainProvisionerInterface
{
    /**
     * Create (or confirm) the hosting-account-level domain slot for $domain.
     * Must be idempotent: if the domain already exists, return
     * DomainResult::alreadyExists() rather than attempting to recreate it.
     * Never throws — a real API failure returns DomainResult::failed();
     * ProvisionDomainStage treats that as a fatal, pipeline-aborting error.
     */
    public function createDomain(InstallationContext $ctx, string $domain): DomainResult;

    /**
     * Rollback operation. $snapshot is exactly what createDomain()'s
     * DomainResult produced (via ProvisionDomainStage), NEVER reconstructed
     * from guesswork. Must not throw. Callers must only invoke this when the
     * domain was actually created by this installer — see
     * ProvisionDomainStage::rollback()'s created_by_installer guard; this
     * method itself does not re-check that flag.
     */
    public function removeDomain(InstallationContext $ctx, string $domain, array $snapshot): void;

    /**
     * Read-only existence probe. Returns true/false when the driver can
     * answer authoritatively, or null when unverifiable on this transport
     * (matches DatabaseProvisionerInterface::databaseExists()'s contract).
     * Must not throw.
     */
    public function domainExists(InstallationContext $ctx, string $domain): ?bool;

    /**
     * Resolve the real document root cPanel/the hosting account assigned to
     * this domain, or null if unknown/not applicable (VPS). Must not throw.
     */
    public function resolveDocumentRoot(InstallationContext $ctx, string $domain): ?string;
}
