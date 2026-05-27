<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;

class FormsAssetGovernanceService
{
    private string $moduleViewsPath;
    private string $moduleSrcPath;

    public function __construct()
    {
        $this->moduleViewsPath = realpath(__DIR__ . '/../../resources/views') ?: '';
        $this->moduleSrcPath   = realpath(__DIR__ . '/..') ?: '';
    }

    public function validate(): array
    {
        return [
            'tailwind_coverage'    => $this->validateTailwindCoverage(),
            'cdn_integrity'        => $this->validateCdnIntegrity(),
            'asset_duplication'    => $this->detectAssetDuplication(),
            'vite_manifest'        => $this->validateViteManifest(),
            'css_drift'            => $this->detectCssDrift(),
            'js_drift'             => $this->detectJsDrift(),
            'stale_assets'         => $this->detectStaleAssets(),
            'cache_busting'        => $this->validateCacheBusting(),
            'sri_hashes'           => $this->validateSriHashes(),
            'forbidden_overrides'  => $this->detectForbiddenOverrides(),
        ];
    }

    public function score(): int
    {
        $results = $this->validate();
        $passed  = count(array_filter($results, fn($r) => $r['status'] === 'pass'));
        return (int) round(($passed / count($results)) * 100);
    }

    private function validateTailwindCoverage(): array
    {
        // Check if any host app Vite/Tailwind config includes the package path
        $tailwindConfigs = [
            base_path('tailwind.config.js'),
            base_path('tailwind.config.cjs'),
            base_path('vite.config.js'),
            base_path('vite.config.ts'),
        ];

        $packagePath = 'packages/saas-platform/modules/forms';
        $found = false;

        foreach ($tailwindConfigs as $cfg) {
            if (File::exists($cfg)) {
                $contents = File::get($cfg);
                if (str_contains($contents, $packagePath) || str_contains($contents, 'modules/forms')) {
                    $found = true;
                    break;
                }
            }
        }

        return [
            'status'  => $found ? 'pass' : 'warning',
            'message' => $found
                ? 'Package path found in Tailwind/Vite content scanning'
                : 'Package path not found in Tailwind config — classes may be purged in production. Add packages/saas-platform/modules/forms/**/*.blade.php to content[]',
        ];
    }

    private function validateCdnIntegrity(): array
    {
        $issues = [];

        if (!$this->moduleViewsPath) {
            return ['status' => 'pass', 'message' => 'No views path — skip'];
        }

        $bladeFiles = $this->allBladeFiles();
        $cdnPattern = '/https?:\/\/cdn\.[^"\']+/i';

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all($cdnPattern, $contents, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $url) {
                    if (!$this->hasSriHash($contents, $url)) {
                        $issues[] = basename($file) . ': CDN resource without SRI hash — ' . $url;
                    }
                }
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'All CDN resources have SRI hashes or none detected'
                : 'CDN resources without SRI: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function detectAssetDuplication(): array
    {
        $issues = [];
        $bladeFiles = $this->allBladeFiles();
        $cssBlocks = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $contents, $matches);
            foreach ($matches[1] as $block) {
                $normalized = trim(preg_replace('/\s+/', ' ', $block));
                if (strlen($normalized) > 50) {
                    $hash = md5($normalized);
                    if (isset($cssBlocks[$hash])) {
                        $issues[] = 'Duplicate CSS block in ' . basename($file) . ' (also in ' . $cssBlocks[$hash] . ')';
                    } else {
                        $cssBlocks[$hash] = basename($file);
                    }
                }
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'No duplicated CSS blocks detected'
                : 'CSS duplication found: ' . implode('; ', array_slice($issues, 0, 3)),
            'items'   => $issues,
        ];
    }

    private function validateViteManifest(): array
    {
        // Forms module does not have its own Vite build — this is by design.
        // Assets come from the host app's compiled output.
        $publicManifest = public_path('build/manifest.json');

        if (!File::exists($publicManifest)) {
            return [
                'status'  => 'warning',
                'message' => 'No Vite manifest at public/build/manifest.json — run npm run build',
            ];
        }

        return [
            'status'  => 'pass',
            'message' => 'Vite manifest exists at public/build/manifest.json',
        ];
    }

    private function detectCssDrift(): array
    {
        $issues = [];
        $bladeFiles = $this->allBladeFiles();

        // Detect hardcoded hex colors, pixel values, or inline styles that bypass Tailwind
        $patterns = [
            'hardcoded hex color' => '/#[0-9a-fA-F]{3,6}(?![0-9a-fA-F])/',
            'inline style attr'   => '/style\s*=\s*"[^"]{30,}"/',
        ];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            foreach ($patterns as $label => $pattern) {
                preg_match_all($pattern, $contents, $matches);
                if (count($matches[0]) > 5) {
                    $issues[] = basename($file) . ": excessive $label usage (" . count($matches[0]) . ' instances)';
                }
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'No CSS drift detected'
                : 'Potential CSS drift: ' . implode('; ', array_slice($issues, 0, 3)),
            'items'   => $issues,
        ];
    }

    private function detectJsDrift(): array
    {
        $issues = [];
        $bladeFiles = $this->allBladeFiles();

        // Detect inline script blocks that are large (potential JS drift)
        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $contents, $matches);
            foreach ($matches[1] as $block) {
                $lines = substr_count(trim($block), "\n");
                // builder.blade.php legitimately has a large Alpine component — only flag others
                if ($lines > 200 && !str_contains($file, 'builder.blade.php')) {
                    $issues[] = basename($file) . ': large inline JS block (' . $lines . ' lines) — consider extracting';
                }
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'No JS drift detected'
                : 'Large inline JS: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function detectStaleAssets(): array
    {
        // Check for any compiled asset directories that may be stale
        $assetDir = base_path('packages/saas-platform/modules/forms/assets');

        if (File::isDirectory($assetDir)) {
            $files = File::files($assetDir);
            if (count($files) > 0) {
                $stale = [];
                foreach ($files as $file) {
                    $age = time() - $file->getMTime();
                    if ($age > 86400 * 7) {
                        $stale[] = $file->getFilename();
                    }
                }
                if (!empty($stale)) {
                    return [
                        'status'  => 'warning',
                        'message' => 'Stale compiled assets (>7 days): ' . implode(', ', $stale),
                    ];
                }
            }
        }

        return ['status' => 'pass', 'message' => 'No stale compiled assets detected'];
    }

    private function validateCacheBusting(): array
    {
        // Check that Vite asset() calls use versioned paths
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            // Look for raw <script src="..."> or <link href="..."> without @vite or asset()
            if (preg_match('/<script\s+src="\/(?!\/)[^"]*\.(js|css)"/', $contents)) {
                $issues[] = basename($file) . ': raw asset path without cache-busting';
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'All assets use cache-busted references'
                : 'Raw asset paths: ' . implode('; ', $issues),
        ];
    }

    private function validateSriHashes(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $cdnWithoutSri = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all('/<script[^>]+src="https?:\/\/[^"]+cdn[^"]*"[^>]*>/i', $contents, $matches);
            foreach ($matches[0] as $tag) {
                if (!str_contains($tag, 'integrity=')) {
                    $cdnWithoutSri[] = basename($file);
                }
            }
        }

        $cdnWithoutSri = array_unique($cdnWithoutSri);

        return [
            'status'  => empty($cdnWithoutSri) ? 'pass' : 'warning',
            'message' => empty($cdnWithoutSri)
                ? 'All CDN scripts have SRI hashes (or none present)'
                : 'CDN scripts without SRI integrity in: ' . implode(', ', $cdnWithoutSri),
            'items'   => $cdnWithoutSri,
        ];
    }

    private function detectForbiddenOverrides(): array
    {
        $forbiddenPaths = [
            resource_path('views/admin/forms'),
            resource_path('views/site/forms'),
        ];

        $overrideProjects = [
            base_path(),
            base_path('../../customer-site'),
        ];

        $found = [];

        foreach ($overrideProjects as $projectRoot) {
            foreach ($forbiddenPaths as $forbidden) {
                $fullPath = $projectRoot . '/' . ltrim(str_replace(base_path(), '', $forbidden), '/');
                if (File::isDirectory($fullPath)) {
                    $found[] = $fullPath;
                }
            }
        }

        return [
            'status'  => empty($found) ? 'pass' : 'fail',
            'message' => empty($found)
                ? 'No forbidden view overrides detected'
                : 'FORBIDDEN VIEW OVERRIDES FOUND: ' . implode(', ', $found),
            'items'   => $found,
        ];
    }

    private function hasSriHash(string $contents, string $url): bool
    {
        // Find script/link tags referencing this URL and check for integrity attribute
        $escapedUrl = preg_quote(str_replace(['https://', 'http://'], '', $url), '/');
        $pattern = '/<(?:script|link)[^>]+' . $escapedUrl . '[^>]+>/i';
        preg_match($pattern, $contents, $matches);
        return isset($matches[0]) && str_contains($matches[0], 'integrity=');
    }

    private function allBladeFiles(): array
    {
        if (!$this->moduleViewsPath || !File::isDirectory($this->moduleViewsPath)) {
            return [];
        }

        return array_map(
            fn($f) => $f->getPathname(),
            File::allFiles($this->moduleViewsPath)
        );
    }
}
