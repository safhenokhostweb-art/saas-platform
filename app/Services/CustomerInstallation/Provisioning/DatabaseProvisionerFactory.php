<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * Phase 14-F2 — single resolution point for database provisioners, mirroring
 * DriverFactory's pattern for connection drivers (Phase 14-B1). This is the
 * ONLY class CreateDatabaseStage consults to pick a provisioner; no Stage
 * branches on DriverType directly.
 */
class DatabaseProvisionerFactory
{
    public function __construct(
        private readonly VpsDatabaseProvisioner $vps,
        private readonly CpanelDatabaseProvisioner $cpanel,
    ) {}

    /**
     * @throws RuntimeException if no provisioner is registered for the type.
     * In practice unreachable for any type whose DriverCapabilities::
     * supportsDatabaseCreation is false — StageCapabilityGate (Phase 14-E)
     * already fails the installation before CreateDatabaseStage runs. This
     * exists to fail loudly instead of returning null if that ever changes
     * without updating this factory.
     */
    public function forDriverType(DriverType $type): DatabaseProvisionerInterface
    {
        return match ($type) {
            DriverType::SSH, DriverType::FAKE, DriverType::DOCKER => $this->vps,
            DriverType::CPANEL => $this->cpanel,
            default => throw new RuntimeException(
                "No database provisioner registered for driver type '{$type->value}'."
            ),
        };
    }
}
