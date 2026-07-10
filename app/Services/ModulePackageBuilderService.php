<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use ZipArchive;

/**
 * Phase 13-H — Source → Package → Artifact Registration.
 *
 * Builds a deployable ZIP of a module's source tree and records its
 * identity (sha256/path/size) on the corresponding module_releases row.
 *
 * Boundary: this never creates a module_releases row — that remains
 * ModuleReleaseProducerInterface's exclusive responsibility (12-F). This
 * service only attaches an artifact reference to a release that already
 * exists, and only touches App\Models directly because it must — that's
 * why it lives in the host app rather than core-utils.
 */
class ModulePackageBuilderService
{
    /** Paths never bundled into a module package. */
    protected const EXCLUDED_DIRS = ['.git', 'node_modules', 'vendor'];
    protected const EXCLUDED_FILES = ['.DS_Store'];

    public function __construct(
        protected ModuleVersionRegistry $registry,
    ) {}

    /**
     * @return array{module_release_id: int, package_path: string, package_sha256: string, package_size_bytes: int}
     */
    public function build(string $slug, string $version, bool $force = false): array
    {
        $module = Module::where('slug', $slug)->first();
        if (!$module) {
            throw new RuntimeException("Module [{$slug}] not found in the modules table.");
        }

        $release = ModuleRelease::where('module_id', $module->id)
            ->where('version', $version)
            ->first();

        if (!$release) {
            throw new RuntimeException(
                "No module_releases row for {$slug} v{$version}. Run `module:release {$slug} --type=...` first — "
                . "this builder only attaches a package to an existing release, it never creates one."
            );
        }

        if (!empty($release->package_sha256) && !$force) {
            throw new RuntimeException(
                "{$slug} v{$version} already has a registered package (sha256={$release->package_sha256}). "
                . "Pass force=true to rebuild and overwrite."
            );
        }

        $sourcePath = $this->registry->path($slug);
        if (!$sourcePath || !is_dir($sourcePath)) {
            throw new RuntimeException("Module source path not found on disk for [{$slug}] (checked ModuleVersionRegistry).");
        }

        $storageDir = storage_path('app/modules/packages');
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $zipPath = $storageDir . "/{$slug}-{$version}.zip";

        $this->createZip($sourcePath, $zipPath);

        $sha256 = hash_file('sha256', $zipPath);
        $sizeBytes = filesize($zipPath);

        $release->update([
            'package_sha256' => $sha256,
            'package_path' => $zipPath,
            'package_key' => null,
            'package_size_bytes' => $sizeBytes,
        ]);

        Log::info('module_package.built', [
            'slug' => $slug,
            'version' => $version,
            'path' => $zipPath,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
        ]);

        return [
            'module_release_id' => $release->id,
            'package_path' => $zipPath,
            'package_sha256' => $sha256,
            'package_size_bytes' => $sizeBytes,
        ];
    }

    protected function createZip(string $sourcePath, string $zipPath): void
    {
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to open ZIP for writing: {$zipPath}");
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            $relative = ltrim(str_replace($sourcePath, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/\\');
            $relative = str_replace('\\', '/', $relative);

            if ($this->isExcluded($relative)) {
                continue;
            }

            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException("Failed to finalize ZIP: {$zipPath}");
        }
    }

    protected function isExcluded(string $relativePath): bool
    {
        $segments = explode('/', $relativePath);

        foreach ($segments as $segment) {
            if (in_array($segment, self::EXCLUDED_DIRS, true)) {
                return true;
            }
        }

        $basename = end($segments);
        return in_array($basename, self::EXCLUDED_FILES, true);
    }
}
