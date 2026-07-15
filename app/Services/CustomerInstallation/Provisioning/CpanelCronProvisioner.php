<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * Phase 14-F3 — real cPanel scheduler provisioning via UAPI (Cron::add_line
 * / Cron::remove_line), through the shared CpanelCronLine mechanism.
 */
class CpanelCronProvisioner implements CronProvisionerInterface
{
    public function __construct(private readonly CpanelCronLine $cronLine) {}

    public function installScheduler(InstallationContext $ctx): ProvisioningResult
    {
        $command = 'cd ' . $ctx->webRoot() . ' && php artisan schedule:run >> /dev/null 2>&1';

        return $this->cronLine->install(
            $ctx,
            $command,
            'cPanel UAPI Cron::add_line (scheduler) failed — manual cron setup required'
        );
    }

    public function removeScheduler(InstallationContext $ctx, array $snapshot): void
    {
        $this->cronLine->remove($ctx, $snapshot);
    }
}
