<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * Phase 14-F3 — cPanel has no Supervisor (shared hosting cannot run
 * persistent daemons), so the queue worker is provisioned as a short-lived
 * cron job instead: `queue:work --stop-when-empty --max-time=50` running
 * every minute, exiting once the queue is empty (or after 50s) rather than
 * running forever. This is the standard pattern for Laravel queues on
 * shared hosting. Uses the same UAPI cron mechanism as CpanelCronProvisioner.
 */
class CpanelQueueProvisioner implements QueueProvisionerInterface
{
    public function __construct(private readonly CpanelCronLine $cronLine) {}

    public function installWorker(InstallationContext $ctx): ProvisioningResult
    {
        $command = 'cd ' . $ctx->webRoot()
            . ' && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1';

        return $this->cronLine->install(
            $ctx,
            $command,
            'cPanel UAPI Cron::add_line (queue worker) failed — manual setup required'
        );
    }

    public function removeWorker(InstallationContext $ctx, array $snapshot): void
    {
        $this->cronLine->remove($ctx, $snapshot);
    }
}
