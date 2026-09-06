<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\Theme;
use App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase 3G-B-6A (§7, ADR-048) — the declarative Canvas/preview asset
 * emission seam: ExtensionCanvasPaletteAdapter::assetsUsedIn() reads ONLY
 * the selected release's already-validated manifest, never customer/
 * provider code, and never a second asset registry.
 *
 * Phase 3G-B-6B — asset paths here are namespaced under each release's own
 * `/vendor/{vendor}/{package}/{version}/` prefix (ExtensionManifest::
 * assertAssetsNamespacedUnderRelease()), and every release() call below
 * supplies real $assetSources — release() now materializes declared assets
 * to the REAL filesystem (public/vendor/...), which — unlike the DB —
 * RefreshDatabase does not reset between tests, so each test uses its own
 * uniqid()-suffixed package slug to guarantee a fresh, never-colliding
 * physical path. See ExtensionAssetDistributionTest for the
 * collision/materialization guarantees themselves.
 */
class ExtensionCanvasAssetEmissionTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ExtensionReleaseService::class);
    }

    /**
     * release() now materializes real files under public_path() — unlike
     * the DB, RefreshDatabase never resets that, so this suite's own
     * fixture vendor namespaces (acme/foo — never a real product) are
     * removed after every test to avoid permanently accumulating test
     * artifacts in the real public/vendor/ directory. The staging root
     * (3G-B-6C) is fixture-only space too and is cleared the same way.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('vendor/acme'));
        File::deleteDirectory(public_path('vendor/foo'));
        File::deleteDirectory(ExtensionAssetMaterializer::stagingRoot());
        parent::tearDown();
    }

    private function theme(array $settings = []): Theme
    {
        return Theme::create([
            'name' => 'قالب تست دارایی افزونه',
            'slug' => 'ext-asset-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => $settings,
        ]);
    }

    private function uniquePackage(string $base): string
    {
        return $base . '-' . substr(uniqid(), -8);
    }

    private function tempAssetFile(string $contents, string $extension): string
    {
        $path = tempnam(ExtensionAssetMaterializer::stagingRoot(), 'ext-asset-') . '.' . $extension;
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_used_element_assets_are_emitted(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'Gallery',
                'category' => 'content',
                'has_assets' => true,
                'assets' => [
                    'css' => ["/vendor/acme/{$package}/1.0.0/gallery.css"],
                    'js' => ["/vendor/acme/{$package}/1.0.0/gallery.js"],
                ],
            ]],
        ], null, [
            "/vendor/acme/{$package}/1.0.0/gallery.css" => $this->tempAssetFile('body{}', 'css'),
            "/vendor/acme/{$package}/1.0.0/gallery.js" => $this->tempAssetFile(';', 'js'),
        ]);
        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(["/vendor/acme/{$package}/1.0.0/gallery.css"], $assets['css']);
        $this->assertSame(["/vendor/acme/{$package}/1.0.0/gallery.js"], $assets['js']);
    }

    public function test_unused_element_assets_are_not_emitted(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [
                [
                    'type' => 'ext:acme:gallery',
                    'label' => 'Gallery',
                    'category' => 'content',
                    'has_assets' => true,
                    'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/gallery.css"]],
                ],
                [
                    'type' => 'ext:acme:carousel',
                    'label' => 'Carousel',
                    'category' => 'content',
                    'has_assets' => true,
                    'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/carousel.css"]],
                ],
            ],
        ], null, [
            "/vendor/acme/{$package}/1.0.0/gallery.css" => $this->tempAssetFile('a{}', 'css'),
            "/vendor/acme/{$package}/1.0.0/carousel.css" => $this->tempAssetFile('b{}', 'css'),
        ]);
        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        // Only the gallery block is actually used on the page.
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(["/vendor/acme/{$package}/1.0.0/gallery.css"], $assets['css']);
        $this->assertNotContains("/vendor/acme/{$package}/1.0.0/carousel.css", $assets['css']);
    }

    public function test_same_asset_required_by_multiple_elements_emitted_once(): void
    {
        $package = $this->uniquePackage('suite');
        $ext = $this->service->createExtension('acme', $package, 'Suite');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [
                [
                    'type' => 'ext:acme:widget_a',
                    'label' => 'A',
                    'category' => 'content',
                    'has_assets' => true,
                    'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/shared.css"]],
                ],
                [
                    'type' => 'ext:acme:widget_b',
                    'label' => 'B',
                    'category' => 'content',
                    'has_assets' => true,
                    'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/shared.css"]],
                ],
            ],
        ], null, [
            "/vendor/acme/{$package}/1.0.0/shared.css" => $this->tempAssetFile('shared{}', 'css'),
        ]);
        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:widget_a', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:acme:widget_b', 'props' => []],
        ];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(["/vendor/acme/{$package}/1.0.0/shared.css"], $assets['css']);
    }

    public function test_duplicate_block_of_the_same_type_does_not_duplicate_emitted_asset(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'Gallery',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/gallery.css"]],
            ]],
        ], null, [
            "/vendor/acme/{$package}/1.0.0/gallery.css" => $this->tempAssetFile('a{}', 'css'),
        ]);
        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:acme:gallery', 'props' => []],
        ];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(["/vendor/acme/{$package}/1.0.0/gallery.css"], $assets['css']);
    }

    public function test_two_extensions_stay_isolated(): void
    {
        $galleryPackage = $this->uniquePackage('gallery-pro');
        $formsPackage = $this->uniquePackage('forms-pro');

        $gallery = $this->service->createExtension('acme', $galleryPackage, 'Gallery Pro');
        $this->service->release($gallery, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'Gallery',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$galleryPackage}/1.0.0/gallery.css"]],
            ]],
        ], null, [
            "/vendor/acme/{$galleryPackage}/1.0.0/gallery.css" => $this->tempAssetFile('a{}', 'css'),
        ]);
        $forms = $this->service->createExtension('foo', $formsPackage, 'Forms Pro');
        $this->service->release($forms, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:foo:contact_form',
                'label' => 'Contact Form',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['js' => ["/vendor/foo/{$formsPackage}/1.0.0/contact-form.js"]],
            ]],
        ], null, [
            "/vendor/foo/{$formsPackage}/1.0.0/contact-form.js" => $this->tempAssetFile(';', 'js'),
        ]);
        $theme = $this->theme(['extension_selections' => [
            "acme/{$galleryPackage}" => ['version' => '1.0.0'],
            "foo/{$formsPackage}" => ['version' => '1.0.0'],
        ]]);

        // Only the gallery block is on this page — forms-pro's asset must not leak in.
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(["/vendor/acme/{$galleryPackage}/1.0.0/gallery.css"], $assets['css']);
        $this->assertSame([], $assets['js']);

        // Both used on the page — both isolated assets present, no cross-mixing.
        $bothBlocks = [
            ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:foo:contact_form', 'props' => []],
        ];
        $bothAssets = ExtensionCanvasPaletteAdapter::assetsUsedIn($bothBlocks, $theme);

        $this->assertSame(["/vendor/acme/{$galleryPackage}/1.0.0/gallery.css"], $bothAssets['css']);
        $this->assertSame(["/vendor/foo/{$formsPackage}/1.0.0/contact-form.js"], $bothAssets['js']);
    }

    public function test_no_assets_declared_yields_empty_arrays(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content']],
        ]);
        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(['css' => [], 'js' => []], $assets);
    }

    public function test_no_extension_provider_execution_is_required_or_possible(): void
    {
        // Structural proof: nothing in the shared contract carries an
        // executable reference — assertValidAssets() only ever accepts
        // JSON-safe path strings, and assetsUsedIn() only ever reads
        // ExtensionManifest DTOs parsed from the DB row, never a PHP class.
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $release = $this->service->release($ext, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'Gallery',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/gallery.css"]],
            ]],
        ], null, [
            "/vendor/acme/{$package}/1.0.0/gallery.css" => $this->tempAssetFile('a{}', 'css'),
        ]);

        $this->assertIsArray($release->manifest['elements'][0]['assets']['css']);
        $this->assertArrayNotHasKey('rendererClass', $release->manifest['elements'][0]);
        $this->assertArrayNotHasKey('renderer_class', $release->manifest['elements'][0]);
    }
}
