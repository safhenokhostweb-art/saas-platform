<?php

namespace App\Services\ThemeBuilder\Extension;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;

/**
 * Phase 3G-B-6B (ADR-048) — the ONE authority that turns a validated
 * ExtensionManifest's declarative `assets` paths into real, web-servable
 * files under this SaaS instance's own `public/` — the smallest new
 * "release asset bundle" contract this codebase needed (audited: no
 * existing generic package/artifact mechanism was reusable — ModuleRelease's
 * `package_path`/`package_sha256` columns exist only to SFTP a whole
 * module's source tree to a remote customer VPS, never to publish
 * browser-servable static files from this app's own webroot; Motion
 * Graphics deliberately never touches physical files in this codebase at
 * all).
 *
 * $assetSources is the smallest possible "bundle": a plain
 * {declaredPath => absolute local source file path} map — never a second
 * asset registry, never a package manager, never customer/provider code.
 * Every path materialize() writes to is `public_path($declaredPath)`,
 * which ExtensionManifest::assertAssetsNamespacedUnderRelease() has already
 * structurally forced under this exact release's own
 * `/vendor/{vendor}/{package}/{version}/` prefix — so two different
 * releases (of the same or different products) can never even attempt to
 * write the same physical file. The idempotent-hash / hard-collision check
 * below is defense-in-depth for that already-structurally-impossible case,
 * not the primary guarantee.
 *
 * Phase 3G-B-6C — two safety closures on top of B-6B:
 *
 * 1. Source confinement: every $assetSources value must resolve (via
 *    realpath(), never a raw string-prefix check) to a regular, readable
 *    file strictly inside the ONE trusted staging root this class defines
 *    (stagingRoot()) — `storage_path('app/extension-asset-staging')`,
 *    chosen because it is the smallest addition consistent with the one
 *    existing precedent for a locally-staged commercial artifact
 *    (ModulePackageBuilderService's own `storage_path('app/modules/
 *    packages')`, audited in ADR-048). Nothing outside that root — no
 *    arbitrary system/app file, no symlink resolving outside it, no
 *    directory — can ever be published, regardless of what path string a
 *    caller supplies. Extension publication has no HTTP/API caller yet
 *    (audited: SeedE2ECanvasFixtures/tests only) — this confinement exists
 *    proactively, before any upload UI is ever built on top of it.
 *
 * 2. Orphan cleanup: materialize() now returns the list of paths it
 *    ACTUALLY newly created (never an already-idempotent pre-existing
 *    file, never another release's file) so ExtensionReleaseService::
 *    release() can remove exactly those files — and nothing else — if the
 *    release row itself fails to publish afterward. No partially-published
 *    release ever leaves orphaned files behind it; no failed attempt ever
 *    deletes a file it did not itself create this call.
 *
 * Called by ExtensionReleaseService::release() BEFORE the release row is
 * ever created — a materialization failure throws and no release row is
 * written (fail closed, no partially-published release ever exists).
 */
final class ExtensionAssetMaterializer
{
    /** The ONE trusted root every asset source must resolve inside. Created on first use, mirroring ModulePackageBuilderService's own storage_path() convention. */
    public static function stagingRoot(): string
    {
        $root = storage_path('app/extension-asset-staging');

        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        return $root;
    }

    /**
     * @param array<string, string> $assetSources declared path => absolute local source file path,
     *   which MUST resolve inside self::stagingRoot(). Entries not
     *   referenced by any declared asset are ignored — a caller may supply
     *   a whole package's files without the manifest needing to declare
     *   every one of them as a Canvas-consumable asset.
     * @return string[] declared paths this call ACTUALLY newly created (never an idempotent pre-existing match)
     */
    public function materialize(ExtensionManifest $manifest, array $assetSources): array
    {
        $newlyCreated = [];

        foreach ($manifest->elements as $element) {
            foreach (['css', 'js'] as $family) {
                foreach ($element->assets[$family] as $declaredPath) {
                    if ($this->materializeOne($declaredPath, $assetSources)) {
                        $newlyCreated[] = $declaredPath;
                    }
                }
            }
        }

        return $newlyCreated;
    }

    /**
     * Removes exactly the given previously-newly-created declared paths —
     * never a broader directory sweep (§7: cleanup must stay namespace
     * -confined and never recursively delete a vendor/package directory).
     * Never throws: a cleanup failure is logged through the existing
     * application logging convention and the caller's original release
     * failure is always what propagates, never masked by a cleanup error.
     *
     * @param string[] $declaredPaths
     */
    public function cleanupNewlyMaterialized(array $declaredPaths): void
    {
        foreach ($declaredPaths as $declaredPath) {
            $targetPath = public_path(ltrim($declaredPath, '/'));

            try {
                if (is_file($targetPath)) {
                    unlink($targetPath);
                }
            } catch (\Throwable $e) {
                Log::warning(
                    'ExtensionAssetMaterializer: failed to clean up an orphaned asset after a failed release publication.',
                    ['path' => $declaredPath, 'error' => $e->getMessage()]
                );
            }
        }
    }

    /** @return bool true if this call newly wrote the file; false if an identical file already existed (idempotent no-op) */
    private function materializeOne(string $declaredPath, array $assetSources): bool
    {
        if (!array_key_exists($declaredPath, $assetSources)) {
            throw new InvalidArgumentException(
                "Declared asset \"{$declaredPath}\" has no provided source file — publication rejected."
            );
        }

        $sourcePath = $this->assertConfinedToStagingRoot($declaredPath, $assetSources[$declaredPath]);
        $targetPath = public_path(ltrim($declaredPath, '/'));
        $newHash = hash_file('sha256', $sourcePath);

        if (is_file($targetPath)) {
            if (hash_file('sha256', $targetPath) === $newHash) {
                return false; // already materialized identically — safe retry/idempotent no-op, never "newly created"
            }

            throw new RuntimeException(
                "Asset path \"{$declaredPath}\" already exists with different content — refusing to silently "
                . 'rewrite an immutable release\'s asset truth.'
            );
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Could not create directory \"{$targetDir}\" for asset \"{$declaredPath}\".");
        }

        $tempPath = $targetDir . '/.materializing-' . bin2hex(random_bytes(8)) . '-' . basename($targetPath);
        if (!copy($sourcePath, $tempPath)) {
            throw new RuntimeException("Failed to stage asset \"{$declaredPath}\" for materialization.");
        }

        if (!rename($tempPath, $targetPath)) {
            // Lost a race with a concurrent identical materialize() call —
            // the only way a target can now exist is if this exact path was
            // just written, since two releases can never share a path.
            @unlink($tempPath);

            if (is_file($targetPath) && hash_file('sha256', $targetPath) === $newHash) {
                return true;
            }

            throw new RuntimeException("Failed to atomically publish asset \"{$declaredPath}\".");
        }

        return true;
    }

    /**
     * Phase 3G-B-6C (§1-§2) — realpath()-based confinement, never a raw
     * string-prefix check on the unresolved input: resolves BOTH the
     * staging root and the candidate source through realpath() first (so a
     * symlink inside the staging root that points outside it, or a `../`
     * traversal segment, is fully collapsed before comparison), then
     * requires the resolved source to sit strictly inside the resolved
     * root. Rejects a missing source, a directory, and anything outside
     * the root — an arbitrary system/app file can never be published
     * regardless of the string a caller supplies.
     */
    private function assertConfinedToStagingRoot(string $declaredPath, string $sourcePath): string
    {
        $root = realpath(self::stagingRoot());
        if ($root === false) {
            throw new RuntimeException('The Extension asset staging root does not exist or is not accessible.');
        }

        $resolvedSource = realpath($sourcePath);
        if ($resolvedSource === false) {
            throw new InvalidArgumentException(
                "Source file for declared asset \"{$declaredPath}\" does not exist or is not readable: \"{$sourcePath}\"."
            );
        }

        if (!is_file($resolvedSource) || !is_readable($resolvedSource)) {
            throw new InvalidArgumentException(
                "Source file for declared asset \"{$declaredPath}\" does not exist or is not readable: \"{$sourcePath}\"."
            );
        }

        $rootWithSeparator = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolvedSource, $rootWithSeparator)) {
            throw new InvalidArgumentException(
                "Source file for declared asset \"{$declaredPath}\" is outside the trusted Extension asset "
                . 'staging root — arbitrary local filesystem sources are forbidden (ADR-048).'
            );
        }

        return $resolvedSource;
    }
}
