<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * DirectAdmin shared hosting driver — ARCHITECTURE STUB.
 *
 * Status: NOT IMPLEMENTED. Architecture only.
 * To implement: wire DirectAdmin API HTTP calls (port 2222 or 2223).
 *
 * Capabilities: cron, scheduler, SSL, database creation.
 * Limitations: no supervisor, no redis, no storage symlinks.
 *
 * Authentication: DirectAdmin API Key (preferred) or Login:Password.
 * API Docs: https://www.directadmin.com/api.php
 */
class DirectAdminDeploymentDriver implements DeploymentDriverInterface
{
    public function __construct(
        private readonly CustomerRemoteServer $target,
        private readonly CredentialVault  $vault,
    ) {}

    public function driverType(): DriverType
    {
        return DriverType::DIRECTADMIN;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forDirectAdmin();
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->target->host)) {
            $errors[] = 'Host is required';
        }

        if (empty($this->target->username)) {
            $errors[] = 'DirectAdmin username is required';
        }

        if (empty($this->target->api_token_encrypted) && empty($this->target->password_encrypted)) {
            $errors[] = 'Either API token or password is required';
        }

        return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        throw new \BadMethodCallException(
            'DirectAdminDeploymentDriver::rollbackStage() is not yet implemented. ' .
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
            "DirectAdminDeploymentDriver is an architecture stub. " .
            "Implement using the DirectAdmin API (https://www.directadmin.com/api.php)."
        );
    }
}
