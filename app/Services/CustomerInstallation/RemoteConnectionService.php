<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use RuntimeException;

class RemoteConnectionService
{
    public function __construct(private readonly CredentialVault $vault) {}

    public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
    {
        $drivers = config('customer_installation.drivers', []);
        $type = $server->type;

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
            'last_tested_at'    => now(),
            'last_test_result'  => $result->connected ? 'success' : 'failure',
        ]);

        // Store fingerprint on first successful test
        if ($result->connected && $result->fingerprint && !$server->key_fingerprint) {
            $server->update(['key_fingerprint' => $result->fingerprint]);
        }

        return $result;
    }
}
