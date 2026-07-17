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
 * Pure SFTP driver — ARCHITECTURE STUB.
 *
 * Status: NOT IMPLEMENTED. Architecture only.
 * To implement: use phpseclib3\Net\SFTP for file transfer only.
 *
 * Capabilities: upload/download only.
 * Limitations: no command execution, no service configuration, no database.
 * Use case: pure shared hosting where only file transfer is possible.
 *           Manual post-install steps (migrations, .env) required.
 *
 * Authentication: Username + Password (SFTP does not use API tokens).
 */
class SftpDeploymentDriver implements DeploymentDriverInterface
{
    public function __construct(
        private readonly CustomerRemoteServer $target,
        private readonly CredentialVault  $vault,
    ) {}

    public function driverType(): DriverType
    {
        return DriverType::SFTP;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forSftp();
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->target->host)) {
            $errors[] = 'Host is required';
        }

        if (empty($this->target->username)) {
            $errors[] = 'Username is required';
        }

        if (empty($this->target->password_encrypted)) {
            $errors[] = 'Password is required for SFTP authentication';
        }

        return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        // SFTP driver: rollback means removing uploaded files.
        // Not yet implemented — architecture stub.
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
        // SFTP has no command execution capability — this should never be called.
        // Pipeline stages must check capabilities()->supportsQueue etc. first.
        throw new \BadMethodCallException(
            'SftpDeploymentDriver does not support remote command execution. ' .
            'Check capabilities() before calling execute().'
        );
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
            "SftpDeploymentDriver is an architecture stub. " .
            "Implement using phpseclib3\\Net\\SFTP."
        );
    }
}
