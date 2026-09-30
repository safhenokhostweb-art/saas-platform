<?php

namespace Tests\Feature\ThemeBuilder;

use Tests\Support\FoodMart\FoodMartRealShellHarness;
use Tests\TestCase;

class FoodMartPhase2dRealShellRenderTest extends TestCase
{
    private const GOLDEN = 'C:/laragon/www/saas-platform/_reference-themes/FoodMart-main/FoodMart-main';
    private const INTEGRITY = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/integrity-manifest.tsv';
    private const PHASE2D = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/phase2d';
    private const RUNTIME = self::PHASE2D.'/runtime';
    private const CUSTOMER = 'C:/laragon/www/customer-site';

    public function test_builds_isolated_shell_with_local_ordered_theme_assets(): void
    {
        $manifest = $this->goldenManifest();
        $this->removeDirectory(self::RUNTIME);

        foreach ($manifest as $path => $sha256) {
            $source = self::GOLDEN.'/'.$path;
            self::assertSame($sha256, hash_file('sha256', $source), "Golden drift: {$path}");
            if (! in_array($path, ['index.html', 'readme.md'], true)) {
                $this->copyVerified($source, self::RUNTIME.'/'.$path);
            }
        }

        $vite = json_decode(file_get_contents(self::CUSTOMER.'/public/build/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->copyVerified(
            self::CUSTOMER.'/public/build/'.$vite['resources/js/app.js']['file'],
            self::RUNTIME.'/assets/app.js',
        );
        foreach (['Regular', 'Bold'] as $weight) {
            $this->copyVerified(
                self::CUSTOMER."/public/assets/fonts/vazirmatn/Vazirmatn-{$weight}.woff2",
                self::RUNTIME."/assets/fonts/vazirmatn/Vazirmatn-{$weight}.woff2",
            );
        }

        $vendorFiles = [
            'swiper-bundle.min.css', 'swiper-bundle.min.js',
            'bootstrap-5.3.0-alpha3.min.css', 'bootstrap.bundle.min.js',
            'google-fonts-local.css',
        ];
        foreach ($vendorFiles as $file) {
            $this->copyVerified(self::PHASE2D.'/vendor/'.$file, self::RUNTIME.'/theme-assets/'.$file);
        }
        foreach (glob(self::PHASE2D.'/vendor/fonts/*') as $font) {
            $this->copyVerified($font, self::RUNTIME.'/theme-assets/fonts/'.basename($font));
        }

        $html = FoodMartRealShellHarness::build(
            placement: FoodMartRealShellHarness::PLACEMENT_WRAPPED,
            isolateDirection: false,
            applyMinimalSelectOverride: false,
            assetBaseUrl: 'assets',
            documentDirection: 'ltr',
            themeOwnsChrome: true,
            loadPlatformPresentationCss: false,
            localizedThemeAssetBaseUrl: 'theme-assets',
        );
        file_put_contents(self::RUNTIME.'/index.html', $html);

        self::assertStringNotContainsString('assets/app.css', $html);
        self::assertStringContainsString('assets/app.js', $html);
        self::assertStringNotContainsString('https://cdn.jsdelivr.net', $html);
        self::assertStringNotContainsString('https://fonts.googleapis.com', $html);
        self::assertStringNotContainsString('https://fonts.gstatic.com', $html);
        self::assertStringContainsString('theme-assets/bootstrap-5.3.0-alpha3.min.css', $html);

        $localized = [];
        foreach ([
            ['css', 'theme-assets/swiper-bundle.min.css'],
            ['css', 'theme-assets/bootstrap-5.3.0-alpha3.min.css'],
            ['css', 'css/vendor.css'],
            ['css', 'style.css'],
            ['css', 'theme-assets/google-fonts-local.css'],
            ['js', 'js/jquery-1.11.0.min.js'],
            ['js', 'theme-assets/swiper-bundle.min.js'],
            ['js', 'theme-assets/bootstrap.bundle.min.js'],
            ['js', 'js/plugins.js'],
            ['js', 'js/script.js'],
        ] as [$kind, $path]) {
            $localized[] = ['kind' => $kind, 'path' => $path, 'sha256' => hash_file('sha256', self::RUNTIME.'/'.$path)];
        }
        file_put_contents(
            self::PHASE2D.'/localized-theme-assets.json',
            json_encode(['version' => 1, 'localized' => true, 'assets' => $localized], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function goldenManifest(): array
    {
        $result = [];
        foreach (file(self::INTEGRITY, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$path, $sha] = explode("\t", $line);
            $result[$path] = $sha;
        }
        self::assertCount(70, $result);

        return $result;
    }

    private function copyVerified(string $source, string $destination): void
    {
        @mkdir(dirname($destination), 0777, true);
        copy($source, $destination);
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $destination));
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) return;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($directory);
    }
}
