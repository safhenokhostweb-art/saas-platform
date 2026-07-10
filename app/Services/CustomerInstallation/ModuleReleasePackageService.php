<?php

namespace App\Services\CustomerInstallation;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Services\CustomerInstallation\Contracts\CustomerPackageInterface;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\PackageManifest;
use RuntimeException;

/**
 * Phase 13-H — CustomerPackageInterface implementation for a single
 * module's released packages, sourced from module_releases' artifact
 * columns (13-G) instead of CustomerInstallationPackage.
 *
 * Per the Phase 13-D design: the interface itself is unmodified —
 * latestVersion() has no module-slug parameter because this
 * implementation is meant to be constructed once per module, with the
 * slug fixed at construction time, and bound for the duration of a
 * single run. Not wired into any pipeline/orchestrator in this commit —
 * that binding is a later phase's responsibility.
 */
class ModuleReleasePackageService implements CustomerPackageInterface
{
    public function __construct(private readonly string $moduleSlug) {}

    public function latestVersion(): string
    {
        $release = $this->latestReleaseWithPackage();
        return $release->version;
    }

    public function getManifest(string $version): PackageManifest
    {
        $release = $this->releaseFor($version);

        if (empty($release->package_sha256) || empty($release->package_path)) {
            throw new RuntimeException("Module {$this->moduleSlug} v{$version} has no registered package. Run module:package-build first.");
        }

        return new PackageManifest(
            version: $release->version,
            sha256: $release->package_sha256,
            sizeBytes: (int) $release->package_size_bytes,
            releasedAt: optional($release->distributed_at)->toIso8601String() ?? $release->created_at->toIso8601String(),
            minPhp: '8.1',
            minMysql: '8.0',
            requiredExtensions: [],
            arvanKey: $release->package_key ?? '',
        );
    }

    public function downloadLocally(string $version): string
    {
        $release = $this->releaseFor($version);

        if (empty($release->package_path) || !file_exists($release->package_path)) {
            throw new RuntimeException("Package file not found for {$this->moduleSlug} v{$version}: " . ($release->package_path ?? '(none)'));
        }

        // The artifact is already local (built by ModulePackageBuilderService) —
        // return a fresh temp copy so the caller can safely clean it up without
        // touching the registered artifact itself.
        $tempCopy = sys_get_temp_dir() . '/' . $this->moduleSlug . '-' . $version . '-' . uniqid() . '.zip';
        if (!copy($release->package_path, $tempCopy)) {
            throw new RuntimeException("Failed to copy package to temp path: {$tempCopy}");
        }

        return $tempCopy;
    }

    public function verify(string $localPath, string $expectedSha256): bool
    {
        if (!file_exists($localPath)) {
            throw new RuntimeException("Package file not found: {$localPath}");
        }

        return hash_equals($expectedSha256, hash_file('sha256', $localPath));
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

    // ─── Internal ────────────────────────────────────────────────────────────

    private function module(): Module
    {
        $module = Module::where('slug', $this->moduleSlug)->first();
        if (!$module) {
            throw new RuntimeException("Module [{$this->moduleSlug}] not found.");
        }
        return $module;
    }

    private function releaseFor(string $version): ModuleRelease
    {
        $release = ModuleRelease::where('module_id', $this->module()->id)
            ->where('version', $version)
            ->first();

        if (!$release) {
            throw new RuntimeException("No module_releases row for {$this->moduleSlug} v{$version}.");
        }

        return $release;
    }

    private function latestReleaseWithPackage(): ModuleRelease
    {
        $release = ModuleRelease::where('module_id', $this->module()->id)
            ->where('is_active', true)
            ->whereNotNull('package_sha256')
            ->get()
            ->reduce(function (?ModuleRelease $best, ModuleRelease $r) {
                if ($best === null) {
                    return $r;
                }
                return version_compare($r->version, $best->version, '>') ? $r : $best;
            });

        if (!$release) {
            throw new RuntimeException("No packaged release found for module [{$this->moduleSlug}].");
        }

        return $release;
    }
}
