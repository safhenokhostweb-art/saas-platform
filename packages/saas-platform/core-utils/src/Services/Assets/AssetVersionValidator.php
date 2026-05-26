<?php

namespace SaaSPlatform\CoreUtils\Services\Assets;

use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;

/**
 * Validates frontend asset coherence with the current backend release.
 *
 * Detects stale browsers, CDN invalidation needs,
 * and published module asset drift.
 */
class AssetVersionValidator
{
    public function __construct(private ReleaseManifestManager $manifestManager) {}

    public function validate(): array
    {
        $checks = [];

        $checks[] = $this->checkViteManifest();
        $checks[] = $this->checkPublicBuildExists();
        $checks[] = $this->checkAssetChecksumMatch();
        $checks[] = $this->checkModuleAssets();

        $passed = array_sum(array_column($checks, 'passed'));
        $total  = count($checks);

        return [
            'passed' => $passed === $total,
            'score'  => "{$passed}/{$total}",
            'checks' => $checks,
        ];
    }

    public function currentViteChecksum(): ?string
    {
        foreach ([public_path('build/manifest.json'), public_path('build/.vite/manifest.json')] as $path) {
            if (file_exists($path)) {
                return hash_file('sha256', $path);
            }
        }
        return null;
    }

    public function hasAssetDrift(): bool
    {
        $latest = $this->manifestManager->latest();
        if (!$latest) {
            return false;
        }

        $expected = $latest['asset_checksum'] ?? null;
        $current  = $this->currentViteChecksum();

        return $expected !== null && $expected !== $current;
    }

    private function checkViteManifest(): array
    {
        $paths = [public_path('build/manifest.json'), public_path('build/.vite/manifest.json')];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $manifest = json_decode(file_get_contents($path), true);
                return [
                    'check'  => 'vite_manifest',
                    'passed' => is_array($manifest) && !empty($manifest),
                    'detail' => is_array($manifest) ? count($manifest) . ' asset(s) in manifest' : 'Invalid manifest JSON',
                    'path'   => $path,
                ];
            }
        }

        return ['check' => 'vite_manifest', 'passed' => false, 'detail' => 'Vite manifest not found in public/build/'];
    }

    private function checkPublicBuildExists(): array
    {
        $ok = is_dir(public_path('build'));
        return [
            'check'  => 'public_build_dir',
            'passed' => $ok,
            'detail' => $ok ? 'public/build exists' : 'public/build directory missing — run npm run build',
        ];
    }

    private function checkAssetChecksumMatch(): array
    {
        $latest = $this->manifestManager->latest();

        if (!$latest) {
            return ['check' => 'asset_checksum', 'passed' => true, 'detail' => 'No release manifest to compare'];
        }

        $expected = $latest['asset_checksum'] ?? null;
        $current  = $this->currentViteChecksum();

        if (!$expected) {
            return ['check' => 'asset_checksum', 'passed' => true, 'detail' => 'No checksum in manifest'];
        }

        $match = ($expected === $current);
        return [
            'check'  => 'asset_checksum',
            'passed' => $match,
            'detail' => $match ? 'Assets match release manifest' : 'Asset drift detected — CDN invalidation may be needed',
        ];
    }

    private function checkModuleAssets(): array
    {
        $vendorPath = public_path('vendor');
        $modules    = ['forms', 'tickets'];
        $missing    = [];

        foreach ($modules as $module) {
            $path = "{$vendorPath}/{$module}";
            if (!is_dir($path) && !is_link($path)) {
                $missing[] = $module;
            }
        }

        return [
            'check'  => 'module_assets',
            'passed' => empty($missing),
            'detail' => empty($missing) ? 'Module assets published' : 'Missing: ' . implode(', ', $missing) . ' — run vendor:publish',
        ];
    }
}
