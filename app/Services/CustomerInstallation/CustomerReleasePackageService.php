<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallationPackage;
use App\Services\CustomerInstallation\Contracts\CustomerPackageInterface;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\PackageManifest;
use App\Services\Infrastructure\ArvanBackupService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CustomerReleasePackageService implements CustomerPackageInterface
{
    public function __construct(private readonly ArvanBackupService $arvan) {}

    public function latestVersion(): string
    {
        $pkg = CustomerInstallationPackage::active();
        if (!$pkg) {
            throw new RuntimeException('No active release package registered. Run: php artisan customer-install:register-package');
        }
        return $pkg->version;
    }

    public function getManifest(string $version): PackageManifest
    {
        $pkg = CustomerInstallationPackage::where('version', $version)->first();
        if (!$pkg) {
            throw new RuntimeException("Package version {$version} not found in registry.");
        }

        return new PackageManifest(
            version:            $pkg->version,
            sha256:             $pkg->sha256,
            sizeBytes:          $pkg->size_bytes,
            releasedAt:         $pkg->released_at->toIso8601String(),
            minPhp:             '8.1',
            minMysql:           '8.0',
            requiredExtensions: config('customer_installation.required_php_extensions', []),
            arvanKey:           $pkg->arvan_key,
        );
    }

    public function downloadLocally(string $version): string
    {
        $manifest = $this->getManifest($version);
        $pkg = CustomerInstallationPackage::where('version', $version)->firstOrFail();
        $tempDir = config('customer_installation.package.local_temp_path', storage_path('app/install-packages'));

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $localPath = $tempDir . '/customer-site-' . $version . '-' . uniqid() . '.zip';

        // Use local file when registered (no Arvan dependency)
        if (!empty($pkg->local_path)) {
            if (!file_exists($pkg->local_path)) {
                throw new RuntimeException("Local package file not found: {$pkg->local_path}");
            }
            Log::info("CIE: Copying local package {$version} from: {$pkg->local_path}");
            if (!copy($pkg->local_path, $localPath)) {
                throw new RuntimeException("Failed to copy local package from {$pkg->local_path}");
            }
            return $localPath;
        }

        Log::info("CIE: Downloading package {$version} from Arvan: {$manifest->arvanKey}");

        $result = $this->arvan->download($manifest->arvanKey, $localPath);
        if (!$result['success']) {
            throw new RuntimeException("Package download failed: " . $result['message']);
        }

        return $localPath;
    }

    public function verify(string $localPath, string $expectedSha256): bool
    {
        if (!file_exists($localPath)) {
            throw new RuntimeException("Package file not found: {$localPath}");
        }

        $actual = hash_file('sha256', $localPath);
        return hash_equals($expectedSha256, $actual);
    }

    public function uploadToRemote(
        string $localPath,
        RemoteConnectionInterface $conn,
        string $remotePath,
        ?callable $progress = null
    ): void {
        $conn->upload($localPath, $remotePath, $progress);
    }

    public function cleanup(string $localPath): void
    {
        if (file_exists($localPath)) {
            @unlink($localPath);
        }
    }
}
