<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Provisioning\QueueProvisionerFactory;

/**
 * Phase 14-F3 — this stage no longer knows Supervisor, SSH commands, or
 * cPanel. It resolves the right QueueProvisionerInterface via
 * QueueProvisionerFactory for whatever driver is connected and calls only
 * that interface.
 */
class ConfigureQueueStage extends AbstractStage
{
    public function __construct(private readonly QueueProvisionerFactory $provisioners) {}

    public function name(): string { return 'CONFIGURE_QUEUE'; }
    public function label(): string { return 'پیکربندی صف پردازش'; }
    public function estimatedSeconds(): int { return 8; }
    public function canRollback(): bool { return true; }

    /**
     * Phase 14-F3 — supportsQueueWorker (not supportsSupervisor): a queue
     * worker can now exist on cPanel too (cron-based, see
     * CpanelQueueProvisioner), just not via Supervisor. Gating on
     * supportsSupervisor here would incorrectly skip this stage on cPanel
     * before the cron-based provisioner ever got a chance to run.
     * Optional: a site can complete installation without a running queue
     * worker, so a missing capability skips rather than fails.
     */
    public function requiredCapabilities(): array
    {
        return ['supportsQueueWorker'];
    }

    public function isCapabilityRequiredToProceed(): bool
    {
        return false;
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());
            $result = $provisioner->installWorker($ctx);

            return StageResult::success($this->name(), $result->output, $result->snapshot, $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());
        $provisioner->removeWorker($ctx, $snapshot);
    }
}
