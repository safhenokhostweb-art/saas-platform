<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\User;
use App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3H-B — closes the B-6 Extension ownership/selection integrity gap
 * in ThemeBuilderController::update(), the raw JSON editor that remains the
 * ONLY authoring path for header_blocks/footer_blocks/global_styles. Proves
 * it now reuses the exact same save-boundary authority
 * (ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections()) that
 * ThemeCanvasController::save() already uses — never a second validator —
 * and that global_styles/settings edits are unaffected by it.
 */
class ThemeBuilderControllerExtensionAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $extensionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extensionService = $this->app->make(ExtensionReleaseService::class);
    }

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

        $role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'elements' => [[
                'type' => 'ext:acme:gallery',
                'label' => 'گالری اکمی',
                'category' => 'content',
                'is_container' => false,
                'controls' => [],
            ]],
        ], $overrides);
    }

    private function theme(array $settings = [], array $headerBlocks = [], array $footerBlocks = []): Theme
    {
        return Theme::create([
            'name' => 'قالب تست ادیتور خام',
            'slug' => 'raw-editor-http-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => $settings,
        ]);
    }

    public function test_update_accepts_header_block_covered_by_selection(): void
    {
        $ext = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($ext, '1.0.0', 1, $this->manifest());
        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'header_blocks' => json_encode([['id' => 'h1', 'type' => 'ext:acme:gallery', 'props' => []]]),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $theme->refresh();
        $this->assertSame('ext:acme:gallery', $theme->header_blocks[0]['type']);
    }

    public function test_update_rejects_new_ext_header_block_not_covered_by_any_selection(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'header_blocks' => json_encode([['id' => 'h1', 'type' => 'ext:acme:gallery', 'props' => []]]),
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('error', $response->json());

        // No partial write: header_blocks untouched.
        $theme->refresh();
        $this->assertSame([], $theme->header_blocks);
    }

    public function test_update_rejects_new_ext_footer_block_not_covered_by_any_selection(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'footer_blocks' => json_encode([['id' => 'f1', 'type' => 'ext:acme:gallery', 'props' => []]]),
        ]);

        $response->assertStatus(422);
        $theme->refresh();
        $this->assertSame([], $theme->footer_blocks);
    }

    public function test_update_preserves_historical_header_instance_but_rejects_a_new_second_instance(): void
    {
        $one = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->extensionService->createExtension('acme', 'widgets-plus', 'Widgets Plus');

        foreach ([$one, $two] as $extension) {
            \Illuminate\Support\Facades\DB::table('extension_releases')->insert([
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

        $theme = $this->theme(
            ['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]],
            [['id' => 'historical-1', 'type' => 'ext:acme:legacy_collision', 'props' => []]]
        );

        // Re-saving the historical header content unchanged, plus a new footer edit, must not be blocked.
        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'header_blocks' => json_encode([['id' => 'historical-1', 'type' => 'ext:acme:legacy_collision', 'props' => []]]),
        ]);
        $response->assertOk();

        // Adding a NEW second instance of the same ambiguous type is rejected.
        $response2 = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'header_blocks' => json_encode([
                ['id' => 'historical-1', 'type' => 'ext:acme:legacy_collision', 'props' => []],
                ['id' => 'new-2', 'type' => 'ext:acme:legacy_collision', 'props' => []],
            ]),
        ]);
        $response2->assertStatus(422);

        $theme->refresh();
        $this->assertCount(1, $theme->header_blocks);
    }

    public function test_update_of_global_styles_alone_is_not_subject_to_block_ownership_validation(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'global_styles' => json_encode(['primary_color' => '#123456']),
        ]);

        $response->assertOk();
        $theme->refresh();
        $this->assertSame('#123456', $theme->global_styles['primary_color']);
    }
}
