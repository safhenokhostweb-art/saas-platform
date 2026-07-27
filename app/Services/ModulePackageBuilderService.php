<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Services\Modules\ModulePackageValidator;
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
 *
 * Phase 17-F — Part 3: the ZIP is validated (ModulePackageValidator)
 * before package_sha256/path/size are persisted. A release that fails
 * validation never gets those columns set, so
 * ModuleDeploymentRequestService's existing "empty package_sha256 = not
 * deployable" check already keeps an invalid package out of every
 * install/upgrade path — no separate re-validation at deploy time needed.
 */
class ModulePackageBuilderService
{
    /** Paths never bundled into a module package. */
    protected const EXCLUDED_DIRS = ['.git', 'node_modules', 'vendor'];
    protected const EXCLUDED_FILES = ['.DS_Store'];

    public function __construct(
        protected ModuleVersionRegistry $registry,
        protected ModulePackageValidator $validator,
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

        // Phase 17-B — Part 4: hard guard, independent of the release-creation
        // guard in ModuleReleaseProducerAdapter. A platform-only module must
        // never be packaged for customer-site deployment even if a release
        // row somehow exists for it (e.g. one created before this phase, or
        // via a future caller that bypasses the producer adapter). Mirrors
        // Module::scopeAssignableToSite()'s "core and paid; internal never"
        // rule at the one point that actually produces a shippable artifact.
        if ($module->type === Module::TYPE_INTERNAL) {
            throw new RuntimeException(
                "Module [{$slug}] is type=internal (platform-only) and must never be packaged "
                . 'for customer-site deployment. Internal modules are excluded from '
                . 'Module::scopeAssignableToSite() and from release/package production by design.'
            );
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

        // Phase 17-F — Part 3: validate before this release is ever allowed
        // to become deployable. Uses a throwaway release snapshot (not yet
        // saved) so validation runs against the exact sha256/path this
        // build produced, without persisting a broken package's identity
        // onto the real row first.
        $candidate = (clone $release);
        $candidate->package_path = $zipPath;
        $candidate->package_sha256 = $sha256;
        $validation = $this->validator->validate($candidate);

        if (!$validation['valid']) {
            unlink($zipPath);
            Log::error('module_package.validation_failed', [
                'slug' => $slug,
                'version' => $version,
                'errors' => $validation['errors'],
            ]);
            throw new RuntimeException(
                "Package for {$slug} v{$version} failed validation: " . implode(' | ', $validation['errors'])
            );
        }

        if (!empty($validation['warnings'])) {
            Log::warning('module_package.validation_warnings', [
                'slug' => $slug,
                'version' => $version,
                'warnings' => $validation['warnings'],
            ]);
        }

        $attributes = [
            'package_sha256' => $sha256,
            'package_path' => $zipPath,
            'package_key' => null,
            'package_size_bytes' => $sizeBytes,
        ];

        // Phase 17-H — Part 2: advance draft/validated → built now that a
        // real artifact exists. Never downgrades an already-published or
        // deprecated release just because --force rebuilt its package.
        if (in_array($release->status, [ModuleRelease::STATUS_DRAFT, ModuleRelease::STATUS_VALIDATED], true)) {
            $attributes['status'] = ModuleRelease::STATUS_BUILT;
        }

        $release->update($attributes);

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
