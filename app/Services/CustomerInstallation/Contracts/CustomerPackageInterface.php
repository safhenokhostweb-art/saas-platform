<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Services\CustomerInstallation\Dto\PackageManifest;

interface CustomerPackageInterface
{
    public function latestVersion(): string;

    public function getManifest(string $version): PackageManifest;

    public function downloadLocally(string $version): string;

    public function verify(string $localPath, string $expectedSha256): bool;

    public function uploadToRemote(
        string $localPath,
        RemoteConnectionInterface $conn,
        string $remotePath,
        ?callable $progress = null
    ): void;

    public function cleanup(string $localPath): void;
}
