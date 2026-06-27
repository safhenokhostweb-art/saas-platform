<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;

interface RemoteConnectionInterface
{
    public function connect(): void;

    public function testConnection(): ConnectionTestResult;

    public function execute(string $command, array $env = []): CommandResult;

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void;

    public function download(string $remotePath, string $localPath): void;

    public function fileExists(string $remotePath): bool;

    public function diskFree(string $path): int;

    public function disconnect(): void;
}
