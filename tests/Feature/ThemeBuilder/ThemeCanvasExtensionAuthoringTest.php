<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\User;
use App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3G-B-6 — end-to-end (HTTP, real routes/controller/DB) proof that a
 * SaaS master Theme author can use a commercial Extension element through
 * the canonical Theme Canvas: palette exposure, explicit release selection
 * persisted to Theme.settings.extension_selections, save-boundary
 * validation, nested authoring, settings preservation, and safe rendering.
 *
 * Does NOT re-test ThemeReleaseService/ThemeCommercialQualityGate's own
 * dependency-derivation/publish-gate behavior — that B-5 chain is reused
 * unmodified and already has its own test suite (ThemeExtensionDependency*,
 * ThemeMandatoryPublishGateTest, etc.), re-run as part of this phase's
 * regression, not duplicated here.
 */
class ThemeCanvasExtensionAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $extensionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extensionService = $this->app->make(ExtensionReleaseService::class);
    }

    /**
     * 3G-B-6B: release() now materializes real files under public_path() —
     * unlike the DB, RefreshDatabase never resets that, so this suite's own
     * fixture vendor namespace (acme — never a real product) is removed
     * after every test to avoid permanently accumulating test artifacts in
     * the real public/vendor/ directory.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('vendor/acme'));
        File::deleteDirectory(ExtensionAssetMaterializer::stagingRoot());
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        // Theme Builder / Canvas routes sit behind the 'wizard' middleware
        // (EnsureSuperAdminForWizard), which requires the Super Admin role,
        // not merely is_admin=true.
        $role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'elements' => [
                [
                    'type' => 'ext:acme:gallery',
                    'label' => 'گالری اکمی',
                    'category' => 'content',
                    'is_container' => false,
                    'controls' => ['title' => ['control_type' => 'text', 'default' => '']],
                ],
                [
                    'type' => 'ext:acme:card_group',
                    'label' => 'گروه کارت',
                    'category' => 'content',
                    'is_container' => true,
                    'controls' => [],
                ],
            ],
        ], $overrides);
    }

    private function tempAssetFile(string $contents, string $extension): string
    {
        $path = tempnam(ExtensionAssetMaterializer::stagingRoot(), 'ext-asset-') . '.' . $extension;
        file_put_contents($path, $contents);

        return $path;
    }

    private function theme(array $settings = [], ?array $pages = null): Theme
    {
        return Theme::create([
            'name' => 'قالب تست کانواس افزونه',
            'slug' => 'ext-canvas-http-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => $pages ?? ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => array_merge(['unrelated_flag' => 'kept', 'version' => '2.4.0'], $settings),
        ]);
    }

    // ── A. Palette ───────────────────────────────────────────────────────────

    public function test_canvas_show_exposes_selected_extension_element_in_registry(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $theme));

        $response->assertOk();
        $registry = $response->viewData('registry');
        $this->assertArrayHasKey('ext:acme:gallery', $registry);
        $this->assertArrayHasKey('hero', $registry); // canonical types remain present

        $byCategory = $response->viewData('byCategory');
        $this->assertArrayHasKey('ext:acme:gallery', $byCategory['content']);
    }

    public function test_unselected_extension_is_not_exposed(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());

        $theme = $this->theme(); // no extension_selections

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $theme));

        $response->assertOk();
        $this->assertArrayNotHasKey('ext:acme:gallery', $response->viewData('registry'));
    }

    // ── B. Version selection persistence ────────────────────────────────────

    public function test_save_persists_extension_selection_and_reload_preserves_it(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $theme = $this->theme();

        $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => ['title' => 'سلام']]],
            'extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']],
        ])->assertOk()->assertJson(['success' => true]);

        $theme->refresh();
        $this->assertSame(['acme/gallery-pro' => ['version' => '1.0.0']], $theme->settings['extension_selections']);
        $this->assertSame('ext:acme:gallery', $theme->pages['home']['blocks'][0]['type']);

        // current_release_id changing later must never silently rewrite the recorded selection.
        $this->extensionService->release($ext->fresh(), '1.1.0', 1, $this->manifest());
        $theme->refresh();
        $this->assertSame(['acme/gallery-pro' => ['version' => '1.0.0']], $theme->settings['extension_selections']);
    }

    public function test_unrelated_settings_keys_survive_a_canvas_save(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $theme = $this->theme();

        // Note: 'blocks' is submitted with one harmless built-in block, not
        // an empty array — Laravel's `required` rule (pre-existing, not
        // introduced by 3G-B-6) treats an empty array as absent, which is an
        // unrelated existing quirk of ThemeCanvasController::save()'s
        // request validation, not something this test is proving.
        $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [['id' => 'b1', 'type' => 'text', 'props' => ['content' => 'x']]],
            'extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']],
        ])->assertOk();

        $theme->refresh();
        $this->assertSame('kept', $theme->settings['unrelated_flag']);
        $this->assertSame('2.4.0', $theme->settings['version']);
        $this->assertSame(['acme/gallery-pro' => ['version' => '1.0.0']], $theme->settings['extension_selections']);
    }

    // ── G. Save-boundary failure (§12, §14, §17) ────────────────────────────

    public function test_save_rejects_ext_block_not_covered_by_any_selection(): void
    {
        $theme = $this->theme([], ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
            ['id' => 'existing', 'type' => 'text', 'props' => ['content' => 'قبلی']],
        ]]]);

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]],
            'extension_selections' => [],
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('error', $response->json());
        // No stack trace / SQL / internal path leaked.
        $this->assertStringNotContainsString('.php', $response->json('error'));
        $this->assertStringNotContainsString('SQLSTATE', $response->json('error'));

        // No partial write: the page's prior blocks are untouched.
        $theme->refresh();
        $this->assertSame('text', $theme->pages['home']['blocks'][0]['type']);
    }

    public function test_save_rejects_malformed_extension_selection(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [],
            'extension_selections' => ['nobody/nothing' => ['version' => '1.0.0']],
        ]);

        $response->assertStatus(422);
        $theme->refresh();
        $this->assertArrayNotHasKey('extension_selections', $theme->settings ?? []);
    }

    public function test_deliberate_version_downgrade_rejected_when_new_release_lacks_used_type(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'suite', 'Suite');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $this->extensionService->release($ext->fresh(), '1.1.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content']], // card_group dropped
        ]));

        $theme = $this->theme(['extension_selections' => ['acme/suite' => ['version' => '1.0.0']]]);

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [
                ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
                ['id' => 'b2', 'type' => 'ext:acme:card_group', 'props' => [], 'children' => []],
            ],
            'extension_selections' => ['acme/suite' => ['version' => '1.1.0']],
        ]);

        $response->assertStatus(422);
        $theme->refresh();
        $this->assertSame(['acme/suite' => ['version' => '1.0.0']], $theme->settings['extension_selections']);
    }

    // ── D. Nested authoring (§10, §24) ──────────────────────────────────────

    public function test_mixed_nested_tree_round_trips_exactly(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'suite', 'Suite');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $theme = $this->theme(['extension_selections' => ['acme/suite' => ['version' => '1.0.0']]]);

        // built-in container → ext child ; ext container → built-in child (3-level mixed).
        $tree = [[
            'id' => 'outer',
            'type' => 'section',
            'props' => ['blocks' => []],
            'children' => [[
                'id' => 'mid',
                'type' => 'ext:acme:card_group',
                'props' => [],
                'children' => [[
                    'id' => 'inner',
                    'type' => 'heading',
                    'props' => ['text' => 'سلام دنیا', 'level' => 2],
                ]],
            ]],
        ]];

        $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => $tree,
            'extension_selections' => ['acme/suite' => ['version' => '1.0.0']],
        ])->assertOk();

        $theme->refresh();
        $this->assertSame($tree, $theme->pages['home']['blocks']);
    }

    public function test_extension_container_renders_its_children_in_preview(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'suite', 'Suite');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $theme = $this->theme(['extension_selections' => ['acme/suite' => ['version' => '1.0.0']]]);

        $blocks = [[
            'id' => 'mid',
            'type' => 'ext:acme:card_group',
            'props' => [],
            'children' => [[
                'id' => 'inner',
                'type' => 'heading',
                'props' => ['text' => 'یک عنوان قابل مشاهده', 'level' => 2],
            ]],
        ]];

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.theme-builder.canvas.preview-ajax', $theme),
            ['page_key' => 'home', 'blocks' => $blocks]
        );

        $response->assertOk();
        $this->assertStringContainsString('یک عنوان قابل مشاهده', $response->json('html'));
    }

    // ── I. Ownership-integrity closure (§5-§9) ──────────────────────────────

    public function test_ambiguous_ownership_collision_is_neither_offered_nor_savable_via_the_real_endpoint(): void
    {
        $one = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->extensionService->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        // Legacy/direct-DB cross-product collision — bypasses
        // ExtensionReleaseService/ADR-046 entirely, exactly like
        // ExtensionReleaseOwnershipInvariantTest's own fixtures.
        foreach ([$one, $two] as $extension) {
            DB::table('extension_releases')->insert([
                'extension_id' => $extension->id,
                'version' => '1.0.0',
                'sdk_contract_version' => 1,
                'manifest' => json_encode([
                    'product_identity' => $extension->identifier(),
                    'package_version' => '1.0.0',
                    'sdk_contract_version' => 1,
                    'elements' => [['type' => 'ext:acme:legacy_collision', 'label' => 'L', 'category' => 'content']],
                ]),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        // Not offered in the insertion palette.
        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $theme));
        $response->assertOk();
        $this->assertArrayNotHasKey('ext:acme:legacy_collision', $response->viewData('registry'));

        // Not savable as NEW authoring.
        $saveResponse = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []]],
            'extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']],
        ]);
        $saveResponse->assertStatus(422);

        // No partial write: page remains empty, nothing silently persisted.
        $theme->refresh();
        $this->assertSame([], $theme->pages['home']['blocks']);
    }

    /**
     * Phase 3G-B-6A (§11-§19) — the instance-aware fix, end-to-end: a Theme
     * that already has ONE persisted instance of an ambiguous-ownership
     * type (seeded directly, simulating content authored before the type's
     * ownership became ambiguous — exactly what §17 requires preserving)
     * must keep rendering that instance, must never offer the type in the
     * insertion palette, and must reject any attempt to add a SECOND new
     * instance of it — proving a historical instance never blanket
     * -authorizes new ones of the same type.
     */
    public function test_historical_ambiguous_instance_is_preserved_but_a_new_second_instance_is_rejected(): void
    {
        $one = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->extensionService->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        foreach ([$one, $two] as $extension) {
            DB::table('extension_releases')->insert([
                'extension_id' => $extension->id,
                'version' => '1.0.0',
                'sdk_contract_version' => 1,
                'manifest' => json_encode([
                    'product_identity' => $extension->identifier(),
                    'package_version' => '1.0.0',
                    'sdk_contract_version' => 1,
                    'elements' => [['type' => 'ext:acme:legacy_collision', 'label' => 'L', 'category' => 'content']],
                ]),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Historical: one instance already persisted, as if authored before
        // the collision existed.
        $theme = $this->theme(
            ['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]],
            ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['id' => 'historical-1', 'type' => 'ext:acme:legacy_collision', 'props' => []],
            ]]]
        );

        // Still visible/loadable: renders without error via the live preview.
        $preview = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas.live-preview', $theme) . '?page=home');
        $preview->assertOk();

        // Ambiguous type absent from the insertion palette.
        $show = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $theme));
        $show->assertOk();
        $this->assertArrayNotHasKey('ext:acme:legacy_collision', $show->viewData('registry'));

        // Attempt to add a NEW second instance through the real save endpoint.
        $saveResponse = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'page_key' => 'home',
            'blocks' => [
                ['id' => 'historical-1', 'type' => 'ext:acme:legacy_collision', 'props' => []],
                ['id' => 'new-2', 'type' => 'ext:acme:legacy_collision', 'props' => []],
            ],
            'extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']],
        ]);
        $saveResponse->assertStatus(422);

        // Historical instance untouched, no second instance persisted.
        $theme->refresh();
        $this->assertSame(
            [['id' => 'historical-1', 'type' => 'ext:acme:legacy_collision', 'props' => []]],
            $theme->pages['home']['blocks']
        );
    }

    // ── J. Declarative asset emission (3G-B-6A §7, ADR-048) ─────────────────

    public function test_live_preview_emits_used_extension_css_and_js_tags(): void
    {
        $package = 'gallery-pro-' . uniqid();
        $prefix = "/vendor/acme/{$package}/1.0.0/";
        $ext = $this->extensionService->createExtension('acme', $package, 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest([
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'گالری اکمی',
                'category' => 'content',
                'is_container' => false,
                'has_assets' => true,
                'assets' => ['css' => ["{$prefix}gallery.css"], 'js' => ["{$prefix}gallery.js"]],
            ]],
        ]), null, [
            "{$prefix}gallery.css" => $this->tempAssetFile('body{}', 'css'),
            "{$prefix}gallery.js" => $this->tempAssetFile(';', 'js'),
        ]);
        $theme = $this->theme(
            ['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]],
            ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
            ]]]
        );

        $response = $this->actingAs($this->admin())->get(
            route('admin.theme-builder.canvas.live-preview', $theme) . '?page=home'
        );

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"{$prefix}gallery.css\">", $html);
        $this->assertStringContainsString("<script src=\"{$prefix}gallery.js\" defer></script>", $html);
    }

    public function test_live_preview_does_not_emit_unused_extension_assets(): void
    {
        $package = 'suite-' . uniqid();
        $prefix = "/vendor/acme/{$package}/1.0.0/";
        $ext = $this->extensionService->createExtension('acme', $package, 'Suite');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest([
            'elements' => [
                [
                    'type' => 'ext:acme:gallery',
                    'label' => 'Gallery',
                    'category' => 'content',
                    'has_assets' => true,
                    'assets' => ['css' => ["{$prefix}gallery.css"]],
                ],
                [
                    'type' => 'ext:acme:carousel',
                    'label' => 'Carousel',
                    'category' => 'content',
                    'has_assets' => true,
                    'assets' => ['css' => ["{$prefix}carousel.css"]],
                ],
            ],
        ]), null, [
            "{$prefix}gallery.css" => $this->tempAssetFile('a{}', 'css'),
            "{$prefix}carousel.css" => $this->tempAssetFile('b{}', 'css'),
        ]);
        // Only the gallery block is actually placed on the page.
        $theme = $this->theme(
            ['extension_selections' => ["acme/{$package}" => ['version' => '1.0.0']]],
            ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
            ]]]
        );

        $response = $this->actingAs($this->admin())->get(
            route('admin.theme-builder.canvas.live-preview', $theme) . '?page=home'
        );

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString("{$prefix}gallery.css", $html);
        $this->assertStringNotContainsString("{$prefix}carousel.css", $html);
    }

    // ── H. Security ──────────────────────────────────────────────────────────

    public function test_xss_payload_in_extension_control_is_neutralized_on_render(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $payload = '<script>alert(1)</script>';
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => ['title' => $payload]]];

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.theme-builder.canvas.preview-ajax', $theme),
            ['page_key' => 'home', 'blocks' => $blocks]
        );

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
