<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * Phase 15-B1 — single resolution point for SSL provisioners, mirroring
 * DatabaseProvisionerFactory/CronProvisionerFactory/QueueProvisionerFactory.
 * IssueSslStage never branches on DriverType directly.
 *
 * ArvanCloud is deliberately NOT a case here — see SslProvisionerInterface's
 * docblock and ArvanCdnDomainDecider. The Arvan-managed decision is made by
 * the caller (IssueSslStage) BEFORE this factory is consulted at all.
 */
class SslProvisionerFactory
{
    public function __construct(
        private readonly VpsSslProvisioner $vps,
        private readonly CpanelSslProvisioner $cpanel,
    ) {}

    /**
     * @throws RuntimeException if no provisioner is registered for the type.
     * In practice unreachable for any type whose DriverCapabilities::
     * supportsSSL is false (SFTP, DirectAdmin stub, Docker) — StageCapabilityGate
     * already skips IssueSslStage before this is called. Exists to fail
     * loudly instead of returning null if that ever changes without updating
     * this factory.
     */
    public function forDriverType(DriverType $type): SslProvisionerInterface
    {
        return match ($type) {
            DriverType::SSH, DriverType::FAKE, DriverType::DOCKER => $this->vps,
            DriverType::CPANEL => $this->cpanel,
            default => throw new RuntimeException(
                "No SSL provisioner registered for driver type '{$type->value}'."
            ),
        };
    }
}
