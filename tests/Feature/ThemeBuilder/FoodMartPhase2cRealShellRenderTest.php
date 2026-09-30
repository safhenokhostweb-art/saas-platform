<?php

namespace Tests\Feature\ThemeBuilder;

use Tests\Support\FoodMart\FoodMartRealShellHarness;
use Tests\TestCase;

/**
 * Phase 2C test-only artifact builder. It exercises the generic customer
 * runtime contract without seeding, publishing, installing, assigning, or
 * writing to either application's database.
 */
class FoodMartPhase2cRealShellRenderTest extends TestCase
{
    private const GOLDEN_REFERENCE = 'C:/laragon/www/saas-platform/_reference-themes/FoodMart-main/FoodMart-main';
    private const MANIFEST_TSV = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/integrity-manifest.tsv';
    private const PHASE2C_DIR = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/phase2c';
    private const CUSTOMER_SITE = 'C:/laragon/www/customer-site';

    public function test_builds_ltr_theme_owned_chrome_harness_without_touching_phase0(): void
    {
        $manifest = $this->loadManifest();
        self::assertCount(70, $manifest);
        foreach ($manifest as $relativePath => $expected) {
            $source = self::GOLDEN_REFERENCE . '/' . $relativePath;
            self::assertFileExists($source);
            self::assertSame($expected['sha256'], hash_file('sha256', $source), "golden reference drift: {$relativePath}");
        }

        $this->removePhase2cDirectory();
        $this->copyRuntimeAssets();
        $this->copyFoodMartAssets($manifest);

        $html = FoodMartRealShellHarness::build(
            placement: FoodMartRealShellHarness::PLACEMENT_WRAPPED,
            isolateDirection: false,
            applyMinimalSelectOverride: true,
            assetBaseUrl: 'assets',
            documentDirection: 'ltr',
            themeOwnsChrome: true,
        );

        self::assertStringContainsString('<html lang="en" dir="ltr">', $html);
        self::assertStringNotContainsString('class="canvas-site-header tb-site-header"', $html);
        self::assertStringNotContainsString('class="canvas-site-footer tb-site-footer"', $html);
        self::assertStringNotContainsString('id="tb-mobile-menu-btn"', $html);
        self::assertStringContainsString("<header>\r\n", $html);
        self::assertStringContainsString('<footer class="py-5">', $html);
        self::assertStringContainsString('<main id="canvas-main" class="canvas-main" tabindex="-1">', $html);

        file_put_contents(self::PHASE2C_DIR . '/index.html', $html);
        $this->copyDirectory(self::PHASE2C_DIR . '/platform-assets', self::PHASE2C_DIR . '/assets');
        foreach (['css', 'js', 'images'] as $directory) {
            $this->copyDirectory(self::PHASE2C_DIR . "/foodmart-assets/{$directory}", self::PHASE2C_DIR . "/{$directory}");
        }
        copy(self::PHASE2C_DIR . '/foodmart-assets/style.css', self::PHASE2C_DIR . '/style.css');

        self::assertFileExists(self::PHASE2C_DIR . '/index.html');
        self::assertFileDoesNotExist(self::PHASE2C_DIR . '/../original/index.html');
    }

    private function loadManifest(): array
    {
        $result = [];
        foreach (file(self::MANIFEST_TSV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$path, $sha, $size] = explode("\t", $line);
            $result[$path] = ['sha256' => $sha, 'size' => (int) $size];
        }
        return $result;
    }

    private function removePhase2cDirectory(): void
    {
        if (!is_dir(self::PHASE2C_DIR)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::PHASE2C_DIR, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        // Windows can keep an otherwise empty directory open when a prior
        // static preview process still has it as its working directory. The
        // contents above are the actual isolation boundary: tolerate only a
        // locked, proven-empty root and still fail on any undeleted artifact.
        if (! @rmdir(self::PHASE2C_DIR)) {
            $remaining = array_values(array_diff(scandir(self::PHASE2C_DIR) ?: [], ['.', '..']));
            self::assertSame([], $remaining, 'Phase 2C QA directory still contains stale artifacts.');
        }
    }

    private function copyRuntimeAssets(): void
    {
        $manifestPath = self::CUSTOMER_SITE . '/public/build/manifest.json';
        self::assertFileExists($manifestPath);
        $vite = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $this->copyVerified(self::CUSTOMER_SITE . '/public/build/' . $vite['resources/css/app.css']['file'], self::PHASE2C_DIR . '/platform-assets/app.css');
        $this->copyVerified(self::CUSTOMER_SITE . '/public/build/' . $vite['resources/js/app.js']['file'], self::PHASE2C_DIR . '/platform-assets/app.js');
        foreach (['Regular', 'Bold'] as $weight) {
            $this->copyVerified(
                self::CUSTOMER_SITE . "/public/assets/fonts/vazirmatn/Vazirmatn-{$weight}.woff2",
                self::PHASE2C_DIR . "/platform-assets/fonts/vazirmatn/Vazirmatn-{$weight}.woff2",
            );
        }
    }

    private function copyFoodMartAssets(array $manifest): void
    {
        foreach (array_keys($manifest) as $relativePath) {
            if (in_array($relativePath, ['readme.md', 'index.html'], true)) continue;
            $this->copyVerified(
                self::GOLDEN_REFERENCE . '/' . $relativePath,
                self::PHASE2C_DIR . '/foodmart-assets/' . $relativePath,
            );
        }
    }

    private function copyVerified(string $source, string $destination): void
    {
        @mkdir(dirname($destination), 0777, true);
        copy($source, $destination);
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $destination), "copy drift: {$source}");
    }

    private function copyDirectory(string $source, string $destination): void
    {
        @mkdir($destination, 0777, true);
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            $target = $destination . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
            } else {
                @mkdir(dirname($target), 0777, true);
                copy($item->getPathname(), $target);
            }
        }
    }
}
