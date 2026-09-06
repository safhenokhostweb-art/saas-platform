<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\Theme;
use App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use App\Services\ThemeBuilder\Extension\ExtensionThemeRuntimeAssetProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Producer-side half of the cross-repo Extension-asset contract: proves
 * ExtensionThemeRuntimeAssetProjector::project() — the exact array embedded
 * as `extension_assets` in the JSON ThemeCanvasController::installToSite()
 * writes into a customer site's own `settings` row — resolves every
 * manifest-declared, saas-platform-local path into an ABSOLUTE URL rooted
 * at this app's own APP_URL. customer-site's ThemeExtensionAssetMap (the
 * consumer half of this same contract) asserts it accepts exactly this
 * shape: {type: {css: string[], js: string[]}}, absolute http(s) URLs only.
 */
class ExtensionThemeRuntimeAssetProjectorTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ExtensionReleaseService::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('vendor/acme'));
        File::deleteDirectory(public_path('vendor/other'));
        File::deleteDirectory(ExtensionAssetMaterializer::stagingRoot());
        parent::tearDown();
    }

    private function theme(array $settings = []): Theme
    {
        return Theme::create([
            'name' => 'قالب تست پروجکشن دارایی',
            'slug' => 'ext-projector-theme-'.uniqid(),
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
        return $base.'-'.substr(uniqid(), -8);
    }

    private function tempAssetFile(string $contents, string $extension): string
    {
        $path = tempnam(ExtensionAssetMaterializer::stagingRoot(), 'ext-asset-').'.'.$extension;
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_declared_paths_are_resolved_to_absolute_app_urls(): void
    {
        $package = $this->uniquePackage('gallery-suite');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Suite');
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

        $projected = ExtensionThemeRuntimeAssetProjector::project($theme);

        $expectedBase = rtrim((string) config('app.url'), '/');
        $this->assertSame(
            ["{$expectedBase}/vendor/acme/{$package}/1.0.0/gallery.css"],
            $projected['ext:acme:gallery']['css']
        );
        $this->assertSame(
            ["{$expectedBase}/vendor/acme/{$package}/1.0.0/gallery.js"],
            $projected['ext:acme:gallery']['js']
        );

        // Every URL this projector ever emits must be absolute http(s) — the
        // exact shape ThemeExtensionAssetMap (customer-site) requires.
        foreach ([$projected['ext:acme:gallery']['css'], $projected['ext:acme:gallery']['js']] as $urls) {
            foreach ($urls as $url) {
                $this->assertMatchesRegularExpression('#^https?://#', $url);
            }
        }
    }

    public function test_element_with_no_declared_assets_is_absent_from_projection(): void
    {
        $package = $this->uniquePackage('plain-suite');
        $ext = $this->service->createExtension('acme', $package, 'Plain Suite');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:plain_card',
                'label' => 'Plain Card',
                'category' => 'content',
            ]],
        ]);

        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        $this->assertSame([], ExtensionThemeRuntimeAssetProjector::project($theme));
    }

    public function test_theme_with_no_extension_selection_projects_empty(): void
    {
        $theme = $this->theme();

        $this->assertSame([], ExtensionThemeRuntimeAssetProjector::project($theme));
    }

    /**
     * Phase 3H-B — proves the projector reads the Theme's own pinned
     * selection, never Extension::current_release_id: publishing a NEW
     * release (which always repoints current_release_id, see
     * ExtensionReleaseService::release()) must not change what a Theme
     * still pinned to the older version projects.
     */
    public function test_pinned_selection_survives_a_newer_release_becoming_current(): void
    {
        $package = $this->uniquePackage('pin-suite');
        $ext = $this->service->createExtension('acme', $package, 'Pin Suite');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:widget',
                'label' => 'Widget',
                'category' => 'content',
                'has_assets' => true,
                'assets' => [
                    'css' => ["/vendor/acme/{$package}/1.0.0/widget.css"],
                    'js' => [],
                ],
            ]],
        ], null, [
            "/vendor/acme/{$package}/1.0.0/widget.css" => $this->tempAssetFile('body{}', 'css'),
        ]);

        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        // Publish 2.0.0 — this always repoints Extension::current_release_id
        // (ExtensionReleaseService::release() line ~168), but the Theme
        // above never changed its own selection.
        $this->service->release($ext, '2.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:widget',
                'label' => 'Widget',
                'category' => 'content',
                'has_assets' => true,
                'assets' => [
                    'css' => ["/vendor/acme/{$package}/2.0.0/widget.css"],
                    'js' => [],
                ],
            ]],
        ], null, [
            "/vendor/acme/{$package}/2.0.0/widget.css" => $this->tempAssetFile('body{}', 'css'),
        ]);

        $this->assertSame('2.0.0', $ext->fresh()->currentRelease->version);

        $expectedBase = rtrim((string) config('app.url'), '/');
        $projected = ExtensionThemeRuntimeAssetProjector::project($theme->fresh());

        $this->assertSame(
            ["{$expectedBase}/vendor/acme/{$package}/1.0.0/widget.css"],
            $projected['ext:acme:widget']['css']
        );
    }

    /** Phase 3H-B — a deliberate selection change to the new version is what actually moves the projected URL. */
    public function test_changing_the_selection_to_the_new_version_changes_the_projected_url(): void
    {
        $package = $this->uniquePackage('pin-suite2');
        $ext = $this->service->createExtension('acme', $package, 'Pin Suite 2');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:widget',
                'label' => 'Widget',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/widget.css"], 'js' => []],
            ]],
        ], null, ["/vendor/acme/{$package}/1.0.0/widget.css" => $this->tempAssetFile('body{}', 'css')]);
        $this->service->release($ext, '2.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:widget',
                'label' => 'Widget',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$package}/2.0.0/widget.css"], 'js' => []],
            ]],
        ], null, ["/vendor/acme/{$package}/2.0.0/widget.css" => $this->tempAssetFile('body{}', 'css')]);

        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '2.0.0']]]);

        $expectedBase = rtrim((string) config('app.url'), '/');
        $projected = ExtensionThemeRuntimeAssetProjector::project($theme);

        $this->assertSame(
            ["{$expectedBase}/vendor/acme/{$package}/2.0.0/widget.css"],
            $projected['ext:acme:widget']['css']
        );
    }

    /** Phase 3H-B — two independently-selected products never mix assets. */
    public function test_two_products_with_distinct_selections_remain_independent(): void
    {
        $packageA = $this->uniquePackage('indep-a');
        $extA = $this->service->createExtension('acme', $packageA, 'Indep A');
        $this->service->release($extA, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:acme:widget_a',
                'label' => 'A',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$packageA}/1.0.0/a.css"], 'js' => []],
            ]],
        ], null, ["/vendor/acme/{$packageA}/1.0.0/a.css" => $this->tempAssetFile('body{}', 'css')]);

        $packageB = $this->uniquePackage('indep-b');
        $extB = $this->service->createExtension('other', $packageB, 'Indep B');
        $this->service->release($extB, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:other:widget_b',
                'label' => 'B',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/other/{$packageB}/1.0.0/b.css"], 'js' => []],
            ]],
        ], null, ["/vendor/other/{$packageB}/1.0.0/b.css" => $this->tempAssetFile('body{}', 'css')]);

        $theme = $this->theme(['extension_selections' => [
            "acme/{$packageA}" => ['version' => '1.0.0'],
            "other/{$packageB}" => ['version' => '1.0.0'],
        ]]);

        $projected = ExtensionThemeRuntimeAssetProjector::project($theme);
        $expectedBase = rtrim((string) config('app.url'), '/');

        $this->assertSame(["{$expectedBase}/vendor/acme/{$packageA}/1.0.0/a.css"], $projected['ext:acme:widget_a']['css']);
        $this->assertSame(["{$expectedBase}/vendor/other/{$packageB}/1.0.0/b.css"], $projected['ext:other:widget_b']['css']);
        $this->assertCount(2, $projected);
    }
}
