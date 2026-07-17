<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

/**
 * Phase 14-B1 — single resolution point for every deployment driver.
 *
 * Before this class, drivers were resolved from two independently maintained
 * config registries (customer_installation.drivers / .deployment_drivers)
 * that could disagree about which server types were registered — the root
 * cause of the "No driver registered for server type: cpanel" incident.
 * No other class should read config('customer_installation.drivers')
 * directly; go through resolve()/buildForServer() instead.
 */
class DriverFactory
{
    public function __construct(private readonly CredentialVault $vault) {}

    /**
     * Build a driver for a legacy CustomerRemoteServer record.
     *
     * @throws RuntimeException if the type is unregistered or not yet implemented.
     */
    public function buildForServer(CustomerRemoteServer $server): DeploymentDriverInterface
    {
        $type  = DriverType::from($server->type);
        $class = $this->resolve($type);

        if ($type === DriverType::FAKE) {
            return new $class();
        }

        if ($type === DriverType::SSH || $type === DriverType::CPANEL) {
            return new $class($server, $this->vault);
        }

        // Every other non-fake driver (directadmin/sftp/docker) is not yet
        // wired into this branch. resolve() already guarantees
        // isImplemented() before returning a class name, so this branch is
        // unreachable today — it exists to fail loudly instead of a
        // confusing TypeError if one of those is ever marked implemented
        // without first adding it to this list.
        throw new RuntimeException(
            "Driver type '{$type->value}' cannot be built from a CustomerRemoteServer record."
        );
    }

    /**
     * Resolve the driver class registered for a type, enforcing that it is
     * actually implemented before handing it back to a caller. This is the
     * only place in the codebase that reads
     * config('customer_installation.drivers').
     *
     * @throws RuntimeException
     */
    public function resolve(DriverType $type): string
    {
        $drivers = config('customer_installation.drivers', []);

        if (!isset($drivers[$type->value])) {
            throw new RuntimeException("No driver registered for server type: {$type->value}");
        }

        if (!$type->isImplemented()) {
            throw new RuntimeException(
                "Driver type '{$type->value}' ({$type->label()}) is registered but not yet implemented. " .
                'It cannot be used for a live connection.'
            );
        }

        return $drivers[$type->value];
    }
}
