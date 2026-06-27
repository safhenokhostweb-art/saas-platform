<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * In-memory fake driver for unit and feature testing.
 * Records all commands and uploads for assertion.
 * Implements DeploymentDriverInterface so tests exercise the full interface.
 */
class FakeRemoteDriver implements DeploymentDriverInterface
{
    private bool $connected = false;
    private array $commandLog = [];
    private array $uploadLog = [];
    private array $filesystem = [];
    private array $diskFreeMap = [];

    /** Canned responses keyed by command pattern */
    private array $responses = [];

    /** If true, next execute() call throws */
    private bool $shouldFail = false;
    private string $failMessage = 'Simulated failure';

    // ─── DeploymentDriverInterface ────────────────────────────────────────────

    public function driverType(): DriverType
    {
        return DriverType::FAKE;
    }

    public function capabilities(): DriverCapabilities
    {
        return DriverCapabilities::forVps(); // fake reports full VPS capabilities for testing
    }

    public function validate(): ValidationResult
    {
        return ValidationResult::pass();
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        // No-op in fake driver — tests can assert stage rollback logic directly
    }

    // ─── Control ──────────────────────────────────────────────────────────────

    public function willFail(string $message = 'Simulated failure'): void
    {
        $this->shouldFail = true;
        $this->failMessage = $message;
    }

    public function addResponse(string $pattern, int $exitCode, string $stdout): void
    {
        $this->responses[$pattern] = ['exit_code' => $exitCode, 'stdout' => $stdout];
    }

    public function setDiskFree(string $path, int $bytes): void
    {
        $this->diskFreeMap[$path] = $bytes;
    }

    public function putFile(string $remotePath): void
    {
        $this->filesystem[$remotePath] = true;
    }

    public function getCommandLog(): array
    {
        return $this->commandLog;
    }

    public function getUploadLog(): array
    {
        return $this->uploadLog;
    }

    public function wasConnected(): bool
    {
        return $this->connected;
    }

    // ─── RemoteConnectionInterface ────────────────────────────────────────────

    public function connect(): void
    {
        $this->connected = true;
    }

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(
            connected:         true,
            os:                'Ubuntu 22.04 (fake)',
            phpVersion:        '8.2.0',
            phpExtensions:     config('customer_installation.required_php_extensions', []),
            diskFreeBytes:     5 * 1024 * 1024 * 1024,
            mysqlAccessible:   true,
            supervisorPresent: true,
            pingMs:            1,
            warnings:          [],
            fingerprint:       'fake-fingerprint-sha256',
            error:             null,
        );
    }

    public function execute(string $command, array $env = []): CommandResult
    {
        if ($this->shouldFail) {
            $this->shouldFail = false;
            throw new \RuntimeException($this->failMessage);
        }

        $this->commandLog[] = $command;

        foreach ($this->responses as $pattern => $response) {
            if (str_contains($command, $pattern)) {
                return new CommandResult(
                    exitCode:   $response['exit_code'],
                    stdout:     $response['stdout'],
                    stderr:     '',
                    durationMs: 1,
                );
            }
        }

        // Default: success with empty output
        return new CommandResult(exitCode: 0, stdout: '', stderr: '', durationMs: 1);
    }

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void
    {
        $this->uploadLog[] = ['local' => $localPath, 'remote' => $remotePath];
        $this->filesystem[$remotePath] = true;

        if ($progress) {
            $progress(100, 1, 1);
        }
    }

    public function download(string $remotePath, string $localPath): void
    {
        if (!isset($this->filesystem[$remotePath])) {
            throw new \RuntimeException("Fake file not found: {$remotePath}");
        }
        file_put_contents($localPath, 'fake-content');
    }

    public function fileExists(string $remotePath): bool
    {
        return isset($this->filesystem[$remotePath]);
    }

    public function diskFree(string $path): int
    {
        return $this->diskFreeMap[$path] ?? $this->diskFreeMap['default'] ?? (10 * 1024 * 1024 * 1024);
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}
