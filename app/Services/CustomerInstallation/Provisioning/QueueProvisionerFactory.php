<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * Phase 14-F3 — single resolution point for queue worker provisioners,
 * mirroring DriverFactory (14-B1), DatabaseProvisionerFactory (14-F2), and
 * CronProvisionerFactory. ConfigureQueueStage never branches on DriverType.
 */
class QueueProvisionerFactory
{
    public function __construct(
        private readonly VpsQueueProvisioner $vps,
        private readonly CpanelQueueProvisioner $cpanel,
    ) {}

    /**
     * @throws RuntimeException if no provisioner is registered for the type.
     */
    public function forDriverType(DriverType $type): QueueProvisionerInterface
    {
        return match ($type) {
            DriverType::SSH, DriverType::FAKE, DriverType::DOCKER => $this->vps,
            DriverType::CPANEL => $this->cpanel,
            default => throw new RuntimeException(
                "No queue provisioner registered for driver type '{$type->value}'."
            ),
        };
    }
}
