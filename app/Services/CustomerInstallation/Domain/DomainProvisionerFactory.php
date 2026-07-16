<?php

namespace App\Services\CustomerInstallation\Domain;

use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * Phase 15-D — single resolution point for domain provisioners, mirroring
 * DatabaseProvisionerFactory/SslProvisionerFactory. ProvisionDomainStage
 * (and WebRootResolver's tier-2 live-query fallback) never branch on
 * DriverType directly.
 */
class DomainProvisionerFactory
{
    public function __construct(
        private readonly VpsDomainProvisioner $vps,
        private readonly CpanelDomainProvisioner $cpanel,
    ) {}

    /**
     * @throws RuntimeException if no provisioner is registered for the type.
     * In practice unreachable for any type whose DriverCapabilities::
     * supportsDomainCreation is false — StageCapabilityGate already fails
     * the installation before ProvisionDomainStage runs. Exists to fail
     * loudly instead of returning null if that ever changes without
     * updating this factory.
     */
    public function forDriverType(DriverType $type): DomainProvisionerInterface
    {
        return match ($type) {
            DriverType::SSH, DriverType::FAKE, DriverType::DOCKER => $this->vps,
            DriverType::CPANEL => $this->cpanel,
            default => throw new RuntimeException(
                "No domain provisioner registered for driver type '{$type->value}'."
            ),
        };
    }
}
