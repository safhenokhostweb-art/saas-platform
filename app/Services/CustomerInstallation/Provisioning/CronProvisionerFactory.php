<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * Phase 14-F3 — single resolution point for scheduler (cron) provisioners,
 * mirroring DriverFactory (Phase 14-B1) and DatabaseProvisionerFactory
 * (Phase 14-F2). ConfigureSchedulerStage never branches on DriverType itself.
 */
class CronProvisionerFactory
{
    public function __construct(
        private readonly VpsCronProvisioner $vps,
        private readonly CpanelCronProvisioner $cpanel,
    ) {}

    /**
     * @throws RuntimeException if no provisioner is registered for the type.
     * In practice unreachable for any type that isn't SSH/CPANEL/FAKE/DOCKER
     * — DriverType::isImplemented() already blocks every other type before
     * any Stage runs. Exists to fail loudly rather than silently if that
     * ever changes without updating this factory.
     */
    public function forDriverType(DriverType $type): CronProvisionerInterface
    {
        return match ($type) {
            DriverType::SSH, DriverType::FAKE, DriverType::DOCKER => $this->vps,
            DriverType::CPANEL => $this->cpanel,
            default => throw new RuntimeException(
                "No cron provisioner registered for driver type '{$type->value}'."
            ),
        };
    }
}
