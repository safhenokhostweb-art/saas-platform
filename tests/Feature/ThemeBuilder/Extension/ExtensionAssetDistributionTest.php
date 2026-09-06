<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\Theme;
use App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use Tests\TestCase;

/**
 * Phase 3G-B-6B (ADR-048) — the physical asset distribution/materialization
 * closure: ExtensionReleaseService::release() now REQUIRES a real, readable
 * local source file for every `assets.css`/`assets.js` path a manifest
 * declares, materializes it to `public_path()` BEFORE the release row is
 * ever created (fail closed — no partially-published release), and
 * ExtensionManifest structurally forces every declared path under this
 * exact release's own `/vendor/{vendor}/{package}/{version}/` prefix, so
 * two releases (of the same or different products) can never physically
 * overwrite each other's asset truth.
 */
class ExtensionAssetDistributionTest extends TestCase
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
     * fixture vendor namespace (acme — never a real product) is removed
     * after every test to avoid permanently accumulating test artifacts in
     * the real public/vendor/ directory.
     */
    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory(public_path('vendor/acme'));
        \Illuminate\Support\Facades\File::deleteDirectory(ExtensionAssetMaterializer::stagingRoot());
        parent::tearDown();
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

    private function theme(array $settings = []): Theme
    {
        return Theme::create([
            'name' => 'قالب تست توزیع دارایی',
            'slug' => 'ext-dist-theme-' . uniqid(),
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

    // ── declared + provided succeeds ─────────────────────────────────────────

    public function test_release_with_declared_css_and_provided_css_succeeds(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $release = $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => $this->tempAssetFile('.a{color:red}', 'css'),
        ]);

        $this->assertNotNull($release->id);
        $this->assertFileExists(public_path(ltrim("{$prefix}gallery.css", '/')));
    }

    public function test_release_with_declared_js_and_provided_js_succeeds(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $release = $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['js' => ["{$prefix}gallery.js"]]]],
        ], null, [
            "{$prefix}gallery.js" => $this->tempAssetFile('console.log(1);', 'js'),
        ]);

        $this->assertNotNull($release->id);
        $this->assertFileExists(public_path(ltrim("{$prefix}gallery.js", '/')));
    }

    // ── missing / invalid source ──────────────────────────────────────────────

    public function test_declared_missing_file_rejects_publication(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            // no entry provided for the declared path
        ]);
    }

    public function test_declared_file_with_unreadable_source_path_rejects_publication(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => sys_get_temp_dir() . '/this-file-does-not-exist-' . uniqid() . '.css',
        ]);
    }

    public function test_failed_materialization_leaves_no_published_release(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        try {
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, []);
            $this->fail('expected publication to be rejected');
        } catch (InvalidArgumentException) {
            // expected
        }

        $ext->refresh();
        $this->assertSame(0, $ext->releases()->count());
        $this->assertNull($ext->current_release_id);
        $this->assertFileDoesNotExist(public_path(ltrim("{$prefix}gallery.css", '/')));
    }

    // ── namespace isolation ──────────────────────────────────────────────────

    public function test_product_a_cannot_publish_into_product_bs_namespace(): void
    {
        $packageB = $this->uniquePackage('widgets-plus');
        $packageA = $this->uniquePackage('gallery-pro');
        $extA = $this->service->createExtension('acme', $packageA, 'Gallery Pro');

        // ExtensionReleaseService::buildValidatedManifest() catches the raw
        // ExtensionSdkException and rewraps it as InvalidArgumentException —
        // the same single validation path every other manifest rejection
        // in this service already goes through.
        $this->expectException(InvalidArgumentException::class);
        $this->service->release($extA, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => [
                'css' => ["/vendor/acme/{$packageB}/1.0.0/gallery.css"], // product B's own namespace
            ]]],
        ], null, [
            "/vendor/acme/{$packageB}/1.0.0/gallery.css" => $this->tempAssetFile('a{}', 'css'),
        ]);
    }

    // ── release-to-release isolation (the critical correctness proof) ───────

    public function test_release_1_and_release_2_cannot_overwrite_each_other(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefixV1 = "/vendor/acme/{$package}/1.0.0/";
        $prefixV2 = "/vendor/acme/{$package}/2.0.0/";

        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefixV1}gallery.css"]]]],
        ], null, [
            "{$prefixV1}gallery.css" => $this->tempAssetFile('.v1{color:red}', 'css'),
        ]);

        $this->service->release($ext->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefixV2}gallery.css"]]]],
        ], null, [
            "{$prefixV2}gallery.css" => $this->tempAssetFile('.v2{color:blue}', 'css'),
        ]);

        // Both files exist independently; publishing 2.0.0 never touched 1.0.0's file.
        $this->assertSame('.v1{color:red}', file_get_contents(public_path(ltrim("{$prefixV1}gallery.css", '/'))));
        $this->assertSame('.v2{color:blue}', file_get_contents(public_path(ltrim("{$prefixV2}gallery.css", '/'))));
    }

    public function test_same_literal_path_with_different_content_across_releases_is_refused_at_the_materializer_level(): void
    {
        // Defense-in-depth: even if two manifests somehow declared the exact
        // same path (structurally prevented above at the manifest layer),
        // the materializer itself must never silently overwrite.
        $materializer = new ExtensionAssetMaterializer();
        $package = $this->uniquePackage('gallery-pro');
        $manifest = ExtensionManifest::fromArray([
            'product_identity' => "acme/{$package}",
            'package_version' => '1.0.0',
            'sdk_contract_version' => 1,
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'G',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/gallery.css"]],
            ]],
        ]);

        $materializer->materialize($manifest, [
            "/vendor/acme/{$package}/1.0.0/gallery.css" => $this->tempAssetFile('.original{}', 'css'),
        ]);

        $this->expectException(RuntimeException::class);
        $materializer->materialize($manifest, [
            "/vendor/acme/{$package}/1.0.0/gallery.css" => $this->tempAssetFile('.different{}', 'css'),
        ]);
    }

    // ── retry / idempotency ───────────────────────────────────────────────────

    public function test_repeated_materialization_with_identical_content_is_safe_and_idempotent(): void
    {
        $materializer = new ExtensionAssetMaterializer();
        $package = $this->uniquePackage('gallery-pro');
        $source = $this->tempAssetFile('.stable{}', 'css');
        $manifest = ExtensionManifest::fromArray([
            'product_identity' => "acme/{$package}",
            'package_version' => '1.0.0',
            'sdk_contract_version' => 1,
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'G',
                'category' => 'content',
                'has_assets' => true,
                'assets' => ['css' => ["/vendor/acme/{$package}/1.0.0/gallery.css"]],
            ]],
        ]);

        $materializer->materialize($manifest, ["/vendor/acme/{$package}/1.0.0/gallery.css" => $source]);
        // Second call, identical source content — must not throw.
        $materializer->materialize($manifest, ["/vendor/acme/{$package}/1.0.0/gallery.css" => $source]);

        $this->assertSame('.stable{}', file_get_contents(public_path("vendor/acme/{$package}/1.0.0/gallery.css")));
    }

    // ── exact release / current_release_id independence ─────────────────────

    public function test_theme_pinned_to_old_version_keeps_resolving_its_own_physical_asset_after_a_newer_release(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefixV1 = "/vendor/acme/{$package}/1.0.0/";
        $prefixV2 = "/vendor/acme/{$package}/2.0.0/";

        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefixV1}gallery.css"]]]],
        ], null, [
            "{$prefixV1}gallery.css" => $this->tempAssetFile('.v1{}', 'css'),
        ]);

        $theme = $this->theme(['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]]);

        $this->service->release($ext->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefixV2}gallery.css"]]]],
        ], null, [
            "{$prefixV2}gallery.css" => $this->tempAssetFile('.v2{}', 'css'),
        ]);

        $this->assertSame('2.0.0', $ext->fresh()->currentRelease->version);

        // The Theme's own recorded selection (never current_release_id) still resolves to 1.0.0's path.
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]];
        $assets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        $this->assertSame(["{$prefixV1}gallery.css"], $assets['css']);
        $this->assertSame('.v1{}', file_get_contents(public_path(ltrim("{$prefixV1}gallery.css", '/'))));
    }

    // ── 3G-B-6C §10: source confinement ──────────────────────────────────────

    public function test_source_inside_staging_root_succeeds(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $release = $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => $this->tempAssetFile('.a{}', 'css'),
        ]);

        $this->assertNotNull($release->id);
    }

    public function test_source_outside_staging_root_is_rejected(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        // A real, existing, readable file — just not inside the trusted root.
        $outsideSource = tempnam(sys_get_temp_dir(), 'outside-staging-') . '.css';
        file_put_contents($outsideSource, 'body{}');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, [
                "{$prefix}gallery.css" => $outsideSource,
            ]);
        } finally {
            @unlink($outsideSource);
        }
    }

    public function test_dot_dot_escape_after_canonicalization_is_rejected(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        // A real file that exists OUTSIDE the staging root, referenced via a
        // "staging-root/../.." traversal string — realpath() must collapse
        // this before the confinement check, not be fooled by the substring.
        $outsideDir = dirname(ExtensionAssetMaterializer::stagingRoot()); // storage/app
        $outsideFile = $outsideDir . '/traversal-target-' . uniqid() . '.css';
        file_put_contents($outsideFile, 'body{}');
        $traversalPath = ExtensionAssetMaterializer::stagingRoot() . '/../' . basename($outsideFile);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, [
                "{$prefix}gallery.css" => $traversalPath,
            ]);
        } finally {
            @unlink($outsideFile);
        }
    }

    public function test_symlink_escaping_staging_root_is_rejected(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $outsideFile = sys_get_temp_dir() . '/symlink-target-' . uniqid() . '.css';
        file_put_contents($outsideFile, 'body{}');
        $symlinkPath = ExtensionAssetMaterializer::stagingRoot() . '/escape-link-' . uniqid() . '.css';

        if (!@symlink($outsideFile, $symlinkPath)) {
            $this->markTestSkipped('This environment/user does not permit creating symlinks.');
        }

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, [
                "{$prefix}gallery.css" => $symlinkPath,
            ]);
        } finally {
            @unlink($symlinkPath);
            @unlink($outsideFile);
        }
    }

    public function test_directory_source_is_rejected(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $dirSource = ExtensionAssetMaterializer::stagingRoot() . '/a-directory-' . uniqid();
        mkdir($dirSource);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, [
                "{$prefix}gallery.css" => $dirSource,
            ]);
        } finally {
            @rmdir($dirSource);
        }
    }

    public function test_missing_source_is_rejected(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => ExtensionAssetMaterializer::stagingRoot() . '/never-existed-' . uniqid() . '.css',
        ]);
    }

    public function test_arbitrary_system_file_cannot_be_published(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        // A real, readable file that definitely exists on any PHP install,
        // definitely outside the staging root — stands in for "arbitrary
        // system/app file", platform-portable (works on Windows and POSIX).
        $systemFile = (new \ReflectionClass(\PHPUnit\Framework\TestCase::class))->getFileName();

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => $systemFile,
        ]);
    }

    // ── 3G-B-6C §11: failure-cleanup ──────────────────────────────────────────

    public function test_a_newly_materialized_file_is_removed_when_the_db_release_fails(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";
        $targetPath = public_path(ltrim("{$prefix}gallery.css", '/'));

        // Force a deterministic failure that happens AFTER materialization:
        // ADR-046's ownership conflict check runs inside the DB transaction,
        // strictly after the asset file is already written to public/. Pre
        // -claiming the element type under a different product guarantees
        // this release's own publication fails there.
        $otherExt = $this->service->createExtension('acme', $this->uniquePackage('owner'), 'Owner');
        $this->service->release($otherExt, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:shared_widget', 'label' => 'W', 'category' => 'content']],
        ]);

        try {
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:shared_widget', 'label' => 'W', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, [
                "{$prefix}gallery.css" => $this->tempAssetFile('.new{}', 'css'),
            ]);
            $this->fail('expected the ownership conflict to reject this release');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('already owned', $e->getMessage());
        }

        $this->assertFileDoesNotExist($targetPath);
        $ext->refresh();
        $this->assertSame(0, $ext->releases()->count());
    }

    public function test_b_a_pre_existing_idempotent_file_is_not_deleted_when_a_later_attempt_fails(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";
        $targetPath = public_path(ltrim("{$prefix}gallery.css", '/'));

        // A real prior successful release already materialized this file.
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => $this->tempAssetFile('.stable{}', 'css'),
        ]);
        $this->assertFileExists($targetPath);

        // A second, independent attempt to publish the exact same version
        // fails at assertVersionNotAlreadyReleased() — this must never
        // touch the file the first, successful release already owns.
        try {
            $this->service->release($ext->fresh(), '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
            ], null, [
                "{$prefix}gallery.css" => $this->tempAssetFile('.stable{}', 'css'),
            ]);
            $this->fail('expected the duplicate version to be rejected');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertFileExists($targetPath);
        $this->assertSame('.stable{}', file_get_contents($targetPath));
    }

    public function test_c_multiple_newly_created_files_are_all_removed_on_a_failed_release(): void
    {
        $package = $this->uniquePackage('suite');
        $ext = $this->service->createExtension('acme', $package, 'Suite');
        $prefix = "/vendor/acme/{$package}/1.0.0/";
        $cssPath = public_path(ltrim("{$prefix}widget.css", '/'));
        $jsPath = public_path(ltrim("{$prefix}widget.js", '/'));

        $otherExt = $this->service->createExtension('acme', $this->uniquePackage('owner'), 'Owner');
        $this->service->release($otherExt, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:shared_widget', 'label' => 'W', 'category' => 'content']],
        ]);

        try {
            $this->service->release($ext, '1.0.0', 1, [
                'elements' => [['type' => 'ext:acme:shared_widget', 'label' => 'W', 'category' => 'content', 'has_assets' => true, 'assets' => [
                    'css' => ["{$prefix}widget.css"],
                    'js' => ["{$prefix}widget.js"],
                ]]],
            ], null, [
                "{$prefix}widget.css" => $this->tempAssetFile('.a{}', 'css'),
                "{$prefix}widget.js" => $this->tempAssetFile(';', 'js'),
            ]);
            $this->fail('expected the ownership conflict to reject this release');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertFileDoesNotExist($cssPath);
        $this->assertFileDoesNotExist($jsPath);
    }

    public function test_d_a_successful_release_keeps_its_materialized_files(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefix = "/vendor/acme/{$package}/1.0.0/";

        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefix}gallery.css"]]]],
        ], null, [
            "{$prefix}gallery.css" => $this->tempAssetFile('.a{}', 'css'),
        ]);

        $this->assertFileExists(public_path(ltrim("{$prefix}gallery.css", '/')));
    }

    public function test_e_release_1_files_are_untouched_by_a_later_failed_release_2(): void
    {
        $package = $this->uniquePackage('gallery-pro');
        $ext = $this->service->createExtension('acme', $package, 'Gallery Pro');
        $prefixV1 = "/vendor/acme/{$package}/1.0.0/";
        $prefixV2 = "/vendor/acme/{$package}/2.0.0/";

        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'G', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefixV1}gallery.css"]]]],
        ], null, [
            "{$prefixV1}gallery.css" => $this->tempAssetFile('.v1{}', 'css'),
        ]);

        $otherExt = $this->service->createExtension('acme', $this->uniquePackage('owner'), 'Owner');
        $this->service->release($otherExt, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:conflicting_type', 'label' => 'C', 'category' => 'content']],
        ]);

        try {
            $this->service->release($ext->fresh(), '2.0.0', 1, [
                'elements' => [['type' => 'ext:acme:conflicting_type', 'label' => 'C', 'category' => 'content', 'has_assets' => true, 'assets' => ['css' => ["{$prefixV2}gallery.css"]]]],
            ], null, [
                "{$prefixV2}gallery.css" => $this->tempAssetFile('.v2{}', 'css'),
            ]);
            $this->fail('expected the ownership conflict to reject release 2.0.0');
        } catch (InvalidArgumentException) {
            // expected
        }

        // Release 1's file is completely untouched; release 2's never persisted.
        $this->assertFileExists(public_path(ltrim("{$prefixV1}gallery.css", '/')));
        $this->assertSame('.v1{}', file_get_contents(public_path(ltrim("{$prefixV1}gallery.css", '/'))));
        $this->assertFileDoesNotExist(public_path(ltrim("{$prefixV2}gallery.css", '/')));
        $this->assertSame('1.0.0', $ext->fresh()->currentRelease->version);
    }
}
