<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Ssl\Dto\SslResult;
use App\Services\CustomerInstallation\Ssl\Dto\SslStatus;

/**
 * Phase 15-B1 — hosting-agnostic SSL provisioning contract, mirroring
 * DatabaseProvisionerInterface/CronProvisionerInterface/QueueProvisionerInterface.
 * IssueSslStage and PostInstallValidationService call only this interface;
 * neither knows whether the underlying mechanism is Certbot over SSH (VPS)
 * or a cPanel UAPI AutoSSL trigger.
 *
 * ArvanCloud-managed domains never reach an implementation of this interface
 * at all — that decision is made by ArvanCdnDomainDecider, one layer above,
 * before SslProvisionerFactory is ever consulted (see IssueSslStage). Arvan
 * is therefore deliberately NOT one of this interface's implementations.
 *
 * Security invariant: no method may ever return, log, or persist certificate
 * or private key CONTENT. issueCertificate()/renewCertificate() return only
 * remote filesystem paths (SslResult::$certPath/$keyPath); checkCertificate()
 * returns only metadata (SslStatus). The key material itself must remain on
 * the target server's own filesystem (Certbot) or inside the hosting panel's
 * own storage (cPanel AutoSSL) — never downloaded to the platform, never
 * written to a log, never stored in the database.
 */
interface SslProvisionerInterface
{
    /**
     * Issue a new certificate for $domain. Never throws — a failure to
     * issue (missing certbot, AutoSSL not enabled, ACME challenge failure)
     * is optional-infra territory (see IssueSslStage::isCapabilityRequiredToProceed())
     * and must degrade to SslResult::skipped()/failed(), not an exception.
     */
    public function issueCertificate(InstallationContext $ctx, string $domain): SslResult;

    /**
     * Renew an existing certificate for $domain. In practice this is mostly
     * a manual/scheduled-command entry point (Certbot and cPanel AutoSSL
     * both auto-renew on their own schedule outside this pipeline) — see the
     * Phase 15-A design report — but the contract is defined here so a
     * future renewal command has one canonical implementation to call.
     * Never throws; same degrade contract as issueCertificate().
     */
    public function renewCertificate(InstallationContext $ctx, string $domain): SslResult;

    /**
     * Read-only probe: is there currently an active, valid certificate for
     * $domain? Must never throw — any transport/API failure degrades to
     * SslStatus::unknown().
     */
    public function checkCertificate(InstallationContext $ctx, string $domain): SslStatus;
}
