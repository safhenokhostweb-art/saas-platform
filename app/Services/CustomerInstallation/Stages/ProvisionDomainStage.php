<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Domain\DomainProvisionerFactory;

/**
 * Phase 15-D — runs immediately after the VALIDATE_* stages and BEFORE
 * UPLOAD_PACKAGE. Placement rationale (see the Phase 15-D audit, Part 1):
 * ExtractPackageStage is the FIRST stage to call $ctx->webRoot() (in its own
 * buildPreSnapshot()), which happens before UPLOAD_PACKAGE even runs in
 * pipeline order — so "before CreateDatabase" or "before ConfigureWebServer"
 * (the two placements this phase's brief offered) would both be too late:
 * webRoot() must already resolve correctly by the time EXTRACT_PACKAGE
 * builds its pre-snapshot. Running this immediately after validation (before
 * any file is even uploaded) also fails fast — no wasted upload bandwidth
 * if domain provisioning is impossible.
 *
 * Required, not optional (isCapabilityRequiredToProceed()=true): unlike SSL,
 * a failed domain resolution has no acceptable degraded state — every later
 * stage that touches the filesystem depends on a correct document root.
 * Mirrors CreateDatabaseStage's "essential" posture, not IssueSslStage's
 * "optional infra" one.
 *
 * VPS: DomainProvisionerFactory resolves VpsDomainProvisioner, a documented
 * no-op (skipped_success) — nginx vhost binding (ConfigureWebServerStage)
 * already covers domain binding on VPS, unchanged by this phase.
 *
 * cPanel: real UAPI-backed creation (CpanelDomainProvisioner), idempotent —
 * a pre-existing domain (main/sub/addon) is detected and reported as
 * already_exists rather than recreated.
 *
 * Domain ownership safety (Part 8): the rollback snapshot always records
 * created_by_installer. rollback() only calls removeDomain() when this
 * installer is the one that actually created the domain — a pre-existing
 * customer domain (main domain, or a sub/addon domain from a prior manual
 * setup) is never touched, even on a failed/rolled-back installation.
 */
class ProvisionDomainStage extends AbstractStage
{
    public function __construct(private readonly DomainProvisionerFactory $provisioners) {}

    public function name(): string { return 'PROVISION_DOMAIN'; }
    public function label(): string { return 'ایجاد دامنه'; }
    public function estimatedSeconds(): int { return 15; }
    public function canRollback(): bool { return true; }

    public function requiredCapabilities(): array
    {
        return ['supportsDomainCreation'];
    }

    public function isCapabilityRequiredToProceed(): bool
    {
        return true;
    }

    /**
     * Safe default before execution: created_by_installer=false. If this
     * stage crashes mid-way (process killed after cPanel actually created
     * the domain but before execute() returns a snapshot), rollback must
     * never assume ownership it cannot prove — under-cleaning (leaving an
     * orphaned domain) is the safe failure mode, over-cleaning (deleting a
     * customer's pre-existing domain) is not.
     */
    public function buildPreSnapshot(InstallationContext $ctx): array
    {
        return [
            'domain'               => $ctx->request->domain,
            'created_by_installer' => false,
        ];
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $domain      = $ctx->request->domain;
            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

            $result = $provisioner->createDomain($ctx, $domain);

            if (!$result->success) {
                return StageResult::failure(
                    $this->name(),
                    "Domain provisioning failed for {$domain}: " . ($result->message ?? 'unknown error'),
                    $this->elapsed()
                );
            }

            $createdByInstaller = $result->status === 'created';

            return StageResult::success($this->name(), [
                'domain'               => $domain,
                'document_root'        => $result->documentRoot,
                'type'                 => $result->type,
                'status'               => $result->status,
                'created_by_installer' => $createdByInstaller,
            ], [
                'domain'               => $domain,
                'document_root'        => $result->documentRoot,
                'type'                 => $result->type,
                'meta'                 => $result->meta,
                'created_by_installer' => $createdByInstaller,
            ], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!($snapshot['created_by_installer'] ?? false)) {
            // Never touch a domain this installer did not create — see the
            // Part 8 ownership safety rule in this class's docblock.
            return;
        }

        $domain      = $snapshot['domain'] ?? $ctx->request->domain;
        $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

        $provisioner->removeDomain($ctx, $domain, $snapshot);
    }
}
