<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * Docker container deployment driver — ARCHITECTURE STUB.
 *
 * Status: NOT IMPLEMENTED. Architecture only.
 * To implement: wire Docker SDK / Docker remote API (TCP/Unix socket).
 *
 * Capabilities: queue, scheduler, cron, database, redis, storage link, reboot.
 * Limitations: no supervisor (use process managers), no direct SSL (reverse proxy).
 *
 * Authentication: SSH key/password to Docker host, then exec into container.
 * Implementation approach:
 *   1. SSH into Docker host
 *   2. docker exec {container} {command}
 *   3. docker cp for file transfers
 *
 * Alternative: Docker Remote API (HTTP/TLS on port 2376).
 */
class DockerDeploymentDriver implements DeploymentDriverInterface
{
    public function __construct(
        private readonly DeploymentTarget $target,
        private readonly CredentialVault  $vault,
    ) {}

    public function driverType(): DriverType
    {
        return DriverType::DOCKER;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forDocker();
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->target->host)) {
            $errors[] = 'Docker host is required';
        }

        if (empty($this->target->username)) {
            $errors[] = 'SSH username for Docker host is required';
        }

        if (empty($this->target->private_key_encrypted) && empty($this->target->password_encrypted)) {
            $errors[] = 'SSH key or password is required to access Docker host';
        }

        return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        throw new \BadMethodCallException(
            'DockerDeploymentDriver::rollbackStage() is not yet implemented. ' .
            'Driver is architecture stub only.'
        );
    }

    // ─── RemoteConnectionInterface (stubs) ────────────────────────────────────

    public function connect(): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function testConnection(): ConnectionTestResult
    {
        $this->notImplemented(__METHOD__);
    }

    public function execute(string $command, array $env = []): CommandResult
    {
        $this->notImplemented(__METHOD__);
    }

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function download(string $remotePath, string $localPath): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function fileExists(string $remotePath): bool
    {
        $this->notImplemented(__METHOD__);
    }

    public function diskFree(string $path): int
    {
        $this->notImplemented(__METHOD__);
    }

    public function disconnect(): void
    {
        // No-op for stub
    }

    private function notImplemented(string $method): never
    {
        throw new \BadMethodCallException(
            "{$method} is not yet implemented. " .
            "DockerDeploymentDriver is an architecture stub. " .
            "Implement using Docker Remote API or SSH+docker exec pattern."
        );
    }
}
