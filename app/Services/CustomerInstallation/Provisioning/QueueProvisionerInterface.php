<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * Phase 14-F3 — hosting-agnostic contract for installing a queue worker.
 * ConfigureQueueStage calls only this interface; it never knows whether the
 * mechanism is a Supervisor program (VPS) or a cron-based
 * `queue:work --stop-when-empty` job (cPanel, which has no Supervisor).
 */
interface QueueProvisionerInterface
{
    /**
     * Never throws for "couldn't install" — see ProvisioningResult::skipped().
     */
    public function installWorker(InstallationContext $ctx): ProvisioningResult;

    /** Best-effort rollback — must not throw. */
    public function removeWorker(InstallationContext $ctx, array $snapshot): void;
}
