<?php

namespace App\Services\CustomerInstallation;

use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\InstallationStageInterface;

/**
 * Phase 14-E — single source of truth for "can this stage actually run
 * against this driver?". Pure/stateless so it's trivially unit-testable
 * without a database, queue, or real connection.
 */
final class StageCapabilityGate
{
    /**
     * @return string[] capability keys the stage requires that the driver's
     * capabilities() does not support. Empty array = fully satisfied.
     */
    public function missingCapabilities(InstallationStageInterface $stage, DeploymentDriverInterface $driver): array
    {
        $capabilities = $driver->capabilities();

        return array_values(array_filter(
            $stage->requiredCapabilities(),
            fn (string $capability): bool => !(bool) ($capabilities->{$capability} ?? false)
        ));
    }

    public function isSatisfied(InstallationStageInterface $stage, DeploymentDriverInterface $driver): bool
    {
        return empty($this->missingCapabilities($stage, $driver));
    }
}
