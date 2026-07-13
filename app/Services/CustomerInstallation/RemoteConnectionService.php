<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use RuntimeException;

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

    // ─── Universal API (DeploymentTarget) — new architecture ─────────────────

    /**
     * Build a DeploymentDriverInterface for a DeploymentTarget.
     * Resolves the driver class via DriverFactory, validates, and returns it.
     *
     * @deprecated Phase 14-B1 — no production caller. DeploymentTarget /
     * DeploymentOrchestrator are architecture scaffolding with nothing wired
     * to create a DeploymentTarget row yet. Kept working (not removed) so the
     * existing DeploymentOrchestratorTest continues to pass — see
     * DeploymentOrchestrator's own @deprecated notice before building on it.
     */
    public function buildDriverForTarget(DeploymentTarget $target): DeploymentDriverInterface
    {
        $driver = $this->factory->buildForTarget($target);

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
     *
     * @deprecated Phase 14-B1 — see buildDriverForTarget().
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
}
