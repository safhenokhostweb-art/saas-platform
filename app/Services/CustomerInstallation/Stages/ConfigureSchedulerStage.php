<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Provisioning\CronProvisionerFactory;

/**
 * Phase 14-F3 — this stage no longer knows /etc/cron.d, cron syntax, or
 * cPanel. It resolves the right CronProvisionerInterface via
 * CronProvisionerFactory for whatever driver is connected and calls only
 * that interface.
 */
class ConfigureSchedulerStage extends AbstractStage
{
    public function __construct(private readonly CronProvisionerFactory $provisioners) {}

    public function name(): string { return 'CONFIGURE_SCHEDULER'; }
    public function label(): string { return 'پیکربندی زمانبند'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

    /**
     * Phase 14-E — unchanged: supportsCron was already true for both VPS
     * and cPanel (cPanel genuinely has its own cron mechanism), so this
     * gate never needed to change when CpanelCronProvisioner became real in
     * Phase 14-F3 — it always correctly let the stage attempt on cPanel.
     * Optional: a site can complete installation without scheduled tasks
     * running, so a missing capability skips rather than fails.
     */
    public function requiredCapabilities(): array
    {
        return ['supportsCron'];
    }

    public function isCapabilityRequiredToProceed(): bool
    {
        return false;
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());
            $result = $provisioner->installScheduler($ctx);

            return StageResult::success($this->name(), $result->output, $result->snapshot, $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());
        $provisioner->removeScheduler($ctx, $snapshot);
    }
}
