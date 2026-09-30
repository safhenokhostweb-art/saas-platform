<?php

namespace Tests\Feature\ThemeBuilder;

use Tests\Support\FoodMart\FoodMartRealShellHarness;
use Tests\TestCase;

/**
 * Phase 2B — renders the Phase 2A FoodMart blocks through a test-only
 * harness that reproduces customer-site's REAL public page shell (CSS
 * collision profile, wrapper markup, direction) as faithfully as possible
 * without modifying customer-site (read-only in this phase) or writing to
 * any database. Output goes only to storage/app/theme-baselines/foodmart/
 * phase2b/ — Phase 0's original/ and the integrity manifests are never
 * touched.
 */
class FoodMartRealShellRenderTest extends TestCase
{
    private const GOLDEN_REFERENCE = 'C:/laragon/www/saas-platform/_reference-themes/FoodMart-main/FoodMart-main';
    private const MANIFEST_TSV = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/integrity-manifest.tsv';
    private const PHASE2B_DIR = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/phase2b';
    private const CUSTOMER_SITE = 'C:/laragon/www/customer-site';

    private function loadManifest(): array
    {
        $lines = file(self::MANIFEST_TSV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $out = [];
        foreach ($lines as $line) {
            [$path, $sha, $size] = explode("\t", $line);
            $out[$path] = ['sha256' => $sha, 'size' => (int) $size];
        }
        return $out;
    }

    public function test_golden_reference_and_phase0_baseline_are_intact_before_phase2b(): void
    {
        $manifest = $this->loadManifest();
        self::assertCount(70, $manifest);
        foreach ($manifest as $relativePath => $expected) {
            $fullPath = self::GOLDEN_REFERENCE . '/' . $relativePath;
            self::assertSame($expected['sha256'], hash_file('sha256', $fullPath), "golden reference drift: {$relativePath}");
        }

        // Phase 0 baseline: 84 files total (79 PNG + 5 per-viewport
        // technical-baseline.json), counted via a single recursive walk (no
        // overlapping glob patterns), and this test never writes into
        // original/ itself.
        $originalDir = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/original';
        $all = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($originalDir, \FilesystemIterator::SKIP_DOTS));
        $pngCount = 0;
        $jsonCount = 0;
        foreach ($all as $item) {
            if ($item->isDir()) continue;
            if (str_ends_with($item->getFilename(), '.png')) $pngCount++;
            if (str_ends_with($item->getFilename(), '.json')) $jsonCount++;
        }
        self::assertSame(79, $pngCount, 'Phase 0 baseline PNG count must be unchanged');
        self::assertSame(5, $jsonCount, 'Phase 0 baseline per-viewport technical-baseline.json count must be unchanged');
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($dir);
    }

    private function copyVerified(string $src, string $dest): void
    {
        @mkdir(dirname($dest), 0777, true);
        copy($src, $dest);
        self::assertSame(hash_file('sha256', $src), hash_file('sha256', $dest), "byte-identical copy failed: {$src}");
    }

    public function test_real_shell_harness_renders_both_placement_variants(): void
    {
        $this->rmrf(self::PHASE2B_DIR);
        $manifest = $this->loadManifest();

        // Real, live customer-site build assets — READ ONLY, byte-verified
        // copies. customer-site is never written to.
        self::assertFileExists(self::CUSTOMER_SITE . '/public/build/manifest.json', 'customer-site has no built Vite assets — cannot faithfully reproduce the real shell');
        $viteManifest = json_decode(file_get_contents(self::CUSTOMER_SITE . '/public/build/manifest.json'), true);
        $appCssFile = $viteManifest['resources/css/app.css']['file'];
        $appJsFile = $viteManifest['resources/js/app.js']['file'];

        $this->copyVerified(self::CUSTOMER_SITE . "/public/build/{$appCssFile}", self::PHASE2B_DIR . '/assets/app.css');
        $this->copyVerified(self::CUSTOMER_SITE . "/public/build/{$appJsFile}", self::PHASE2B_DIR . '/assets/app.js');
        foreach (['Regular', 'Bold'] as $weight) {
            $this->copyVerified(
                self::CUSTOMER_SITE . "/public/assets/fonts/vazirmatn/Vazirmatn-{$weight}.woff2",
                self::PHASE2B_DIR . "/assets/fonts/vazirmatn/Vazirmatn-{$weight}.woff2"
            );
        }

        // FoodMart's own assets — byte-verified copies from the Golden
        // Reference (never from the already-generated Phase 2A prototype,
        // so this phase's integrity chain traces to the one authoritative
        // source independently).
        $assetPaths = array_values(array_filter(array_keys($manifest), fn ($p) => $p !== 'readme.md' && $p !== 'index.html'));
        foreach ($assetPaths as $relativePath) {
            $this->copyVerified(self::GOLDEN_REFERENCE . '/' . $relativePath, self::PHASE2B_DIR . '/foodmart-assets/' . $relativePath);
        }

        foreach ([FoodMartRealShellHarness::PLACEMENT_CONTENT_ONLY, FoodMartRealShellHarness::PLACEMENT_WRAPPED] as $placement) {
            $html = FoodMartRealShellHarness::build(
                placement: $placement,
                isolateDirection: true,
                applyMinimalSelectOverride: true,
                assetBaseUrl: 'assets'
            );
            self::assertNotSame('', trim($html));
            self::assertStringContainsString('<div dir="ltr" class="foodmart-root"', $html);

            $dir = self::PHASE2B_DIR . '/' . $placement;
            @mkdir($dir, 0777, true);
            file_put_contents("{$dir}/index.html", $html);

            // Platform assets live under assets/ (a prefix I control in my
            // own generated <link>/<script> tags). FoodMart's own
            // css/js/images/style.css must sit at the variant root — bare,
            // unprefixed, exactly matching the relative paths baked
            // byte-exact into FoodMartBlockRenderer's markup (see
            // FoodMartRealShellHarness's own docblock on this).
            $this->copyDirRecursive(self::PHASE2B_DIR . '/assets', "{$dir}/assets");
            foreach (['css', 'js', 'images'] as $sub) {
                $this->copyDirRecursive(self::PHASE2B_DIR . "/foodmart-assets/{$sub}", "{$dir}/{$sub}");
            }
            copy(self::PHASE2B_DIR . '/foodmart-assets/style.css', "{$dir}/style.css");
        }

        self::assertFileExists(self::PHASE2B_DIR . '/content-only/index.html');
        self::assertFileExists(self::PHASE2B_DIR . '/wrapped/index.html');
        self::assertFileExists(self::PHASE2B_DIR . '/content-only/images/logo.png');
        self::assertFileExists(self::PHASE2B_DIR . '/content-only/css/vendor.css');
        self::assertFileExists(self::PHASE2B_DIR . '/content-only/style.css');
    }

    private function copyDirRecursive(string $src, string $dest): void
    {
        @mkdir($dest, 0777, true);
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            $target = $dest . '/' . substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
            } else {
                @mkdir(dirname($target), 0777, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    public function test_no_foodmart_block_type_leaks_after_harness_render(): void
    {
        // Phase 3 (user approved): the shared package provider registers the
        // canonical foodmart_* set boot-time, so the post-reset invariant is
        // "registered == canonical set", not "registered == empty". See
        // FoodMartPrototypeRenderTest::test_no_foodmart_block_type_is_left_registered_after_the_suite_runs().
        foreach (array_keys(\App\Services\ThemeBuilder\FoodMart\FoodMartBlocks::TYPES) as $type) {
            self::assertSame(
                \SaaSPlatform\ThemeRuntime\FoodMartBlockTypes::TYPES[$type],
                \App\Services\ThemeBuilder\BlockRegistry::all()[$type] ?? null,
                "{$type} must remain registered exactly as the canonical shared-package definition"
            );
            self::assertTrue(\App\Services\ThemeBuilder\BlockRegistry::isHostRegisteredType($type), "{$type} must remain a host type, never canonical");
        }
    }
}
