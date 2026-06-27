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
 * cPanel shared hosting driver — ARCHITECTURE STUB.
 *
 * Status: NOT IMPLEMENTED. Architecture only.
 * To implement: wire cPanel UAPI + WHM API v1 HTTP calls.
 *
 * Capabilities: cron, scheduler, SSL, database creation.
 * Limitations: no supervisor, no redis, no storage symlinks.
 *
 * Authentication: cPanel API Token (preferred) or Username/Password.
 * API Docs: https://api.docs.cpanel.net/
 */
class CpanelDeploymentDriver implements DeploymentDriverInterface
{
    public function __construct(
        private readonly DeploymentTarget $target,
        private readonly CredentialVault  $vault,
    ) {}

    public function driverType(): DriverType
    {
        return DriverType::CPANEL;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forCpanel();
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->target->host)) {
            $errors[] = 'Host is required';
        }

        if (empty($this->target->username)) {
            $errors[] = 'cPanel username is required';
        }

        if (empty($this->target->api_token_encrypted) && empty($this->target->password_encrypted)) {
            $errors[] = 'Either API token or password is required';
        }

        return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        throw new \BadMethodCallException(
            'CpanelDeploymentDriver::rollbackStage() is not yet implemented. ' .
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
            "CpanelDeploymentDriver is an architecture stub. " .
            "Implement using the cPanel UAPI (https://api.docs.cpanel.net/)."
        );
    }
}
