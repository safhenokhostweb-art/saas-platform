<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;

/**
 * Phase 14-B1 — thin facade over DriverFactory.
 *
 * All actual driver resolution/construction now lives in DriverFactory
 * (single registry, single resolve point). This class keeps its existing
 * public method signatures unchanged so the pipeline orchestrator, rollback
 * service, delete pipeline, package upload job, and admin controllers did
 * not need to change to pick up the unified registry.
 */
class RemoteConnectionService
{
    public function __construct(private readonly DriverFactory $factory) {}

    // ─── Legacy API (CustomerRemoteServer) — backward compatible ─────────────

    /**
     * Build a driver for a legacy CustomerRemoteServer record.
     * Preserved for backward compatibility with the existing pipeline.
     */
    public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
    {
        return $this->factory->buildForServer($server);
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
}
