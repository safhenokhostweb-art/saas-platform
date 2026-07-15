<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;

/**
 * Phase 14-F3 — hosting-agnostic contract for installing the Laravel
 * scheduler's `php artisan schedule:run` recurring task. ConfigureSchedulerStage
 * calls only this interface; it never knows whether the mechanism is an
 * /etc/cron.d file (VPS) or a cPanel UAPI Cron::add_line call.
 */
interface CronProvisionerInterface
{
    /**
     * Never throws for "couldn't install" (that's an optional, gracefully
     * skippable outcome — see ProvisioningResult::skipped()). May throw for
     * a genuinely unexpected error (e.g. no CustomerRemoteServer record).
     */
    public function installScheduler(InstallationContext $ctx): ProvisioningResult;

    /** Best-effort rollback — must not throw. */
    public function removeScheduler(InstallationContext $ctx, array $snapshot): void;
}
