<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;

class RemoteConnectionService
{
    public function __construct(private readonly CredentialVault $vault) {}

    // ─── Legacy API (CustomerRemoteServer) — backward compatible ─────────────

    /**
     * Build a driver for a legacy CustomerRemoteServer record.
     * Preserved for backward compatibility with the existing pipeline.
     */
    public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
    {
        $drivers = config('customer_installation.drivers', []);
        $type    = $server->type;

        if (!isset($drivers[$type])) {
            throw new RuntimeException("No driver registered for server type: {$type}");
        }

        $driverClass = $drivers[$type];

        if ($type === 'ssh') {
            return new $driverClass($server, $this->vault);
        }

        if ($type === 'fake') {
            return new $driverClass();
        }

        return app($driverClass, ['server' => $server, 'vault' => $this->vault]);
    }

    public function testAndRecord(CustomerRemoteServer $server): ConnectionTestResult
    {
        $driver = $this->buildDriver($server);
        $result = $driver->testConnection();

        $server->update([
            'last_tested_at'   => now(),
            'last_test_result' => $result->connected ? 'success' : 'failure',
        ]);

        if ($result->connected && $result->fingerprint && !$server->key_fingerprint) {
            $server->update(['key_fingerprint' => $result->fingerprint]);
        }

        return $result;
    }

    // ─── Universal API (DeploymentTarget) — new architecture ─────────────────

    /**
     * Build a DeploymentDriverInterface for a DeploymentTarget.
     * Resolves the driver class from config, validates, and returns the driver.
     */
    public function buildDriverForTarget(DeploymentTarget $target): DeploymentDriverInterface
    {
        $driverType  = $target->driverType();
        $driverClass = $this->resolveDriverClass($driverType);

        if ($driverType === DriverType::FAKE) {
            return new $driverClass();
        }

        /** @var DeploymentDriverInterface $driver */
        $driver = new $driverClass($target, $this->vault);

        $validation = $driver->validate();
        if (!$validation->passed) {
            throw new RuntimeException(
                "DeploymentTarget #{$target->id} validation failed: " .
                implode('; ', $validation->errors)
            );
        }

        return $driver;
    }

    /**
     * Test a DeploymentTarget and persist the result.
     */
    public function testAndRecordTarget(DeploymentTarget $target): ConnectionTestResult
    {
        $driver = $this->buildDriverForTarget($target);
        $result = $driver->testConnection();

        $update = [
            'last_tested_at'   => now(),
            'last_test_result' => $result->connected ? 'success' : 'failure',
        ];

        if ($result->connected && $result->fingerprint && !$target->key_fingerprint) {
            $update['key_fingerprint'] = $result->fingerprint;
        }

        $target->update($update);

        return $result;
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function resolveDriverClass(DriverType $type): string
    {
        $drivers = config('customer_installation.deployment_drivers', []);

        if (!isset($drivers[$type->value])) {
            throw new RuntimeException(
                "No deployment driver registered for type '{$type->value}'. " .
                "Register it in config/customer_installation.php under 'deployment_drivers'."
            );
        }

        return $drivers[$type->value];
    }
}
