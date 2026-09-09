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

class ThemeCanvasZoneSaveTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $extensions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extensions = $this->app->make(ExtensionReleaseService::class);
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
            'password' => Hash::make('password'), 'is_admin' => true, 'is_active' => true,
        ]);
        if ($role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }
        return $user;
    }

    private function theme(array $overrides = []): Theme
    {
        return Theme::create(array_replace_recursive([
            'name' => 'Canvas zone test', 'slug' => 'canvas-zone-' . uniqid(),
            'type' => Theme::TYPE_FULL, 'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT, 'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [['id' => 'old-header', 'type' => 'heading', 'props' => ['text' => 'old']]],
            'footer_blocks' => [['id' => 'old-footer', 'type' => 'heading', 'props' => ['text' => 'old']]],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'blocks' => [['id' => 'old-page', 'type' => 'heading', 'props' => ['text' => 'old']]]]],
            'global_styles' => ['colors' => ['primary' => '#123456']],
            'settings' => ['unrelated' => 'kept'],
        ], $overrides));
    }

    private function save(Theme $theme, string $target, array $blocks, array $selections = [])
    {
        return $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), array_filter([
            'target' => $target,
            'page_key' => $target === 'page' ? 'home' : null,
            'blocks' => $blocks,
            'extension_selections' => $selections,
        ], fn ($value) => $value !== null));
    }

    public function test_page_header_and_footer_saves_are_isolated_and_nested_trees_survive(): void
    {
        $theme = $this->theme();
        $header = [['id' => 'header-container', 'type' => 'container', 'props' => [], 'children' => [['id' => 'header-child', 'type' => 'heading', 'props' => ['text' => 'Header']]]]];
        $footer = [['id' => 'footer-container', 'type' => 'container', 'props' => [], 'children' => [['id' => 'footer-child', 'type' => 'heading', 'props' => ['text' => 'Footer']]]]];

        $this->save($theme, 'header', [])->assertOk();
        $this->save($theme, 'header', $header)->assertOk();
        $theme->refresh();
        $this->assertSame($header, $theme->header_blocks);
        $this->assertSame('old-footer', $theme->footer_blocks[0]['id']);
        $this->assertSame('old-page', $theme->pages['home']['blocks'][0]['id']);
        $this->assertSame('#123456', $theme->global_styles['colors']['primary']);

        $this->save($theme, 'footer', $footer)->assertOk();
        $theme->refresh();
        $this->assertSame($header, $theme->header_blocks);
        $this->assertSame($footer, $theme->footer_blocks);

        $this->save($theme, 'page', [['id' => 'page-new', 'type' => 'heading', 'props' => ['text' => 'Page']]])->assertOk();
        $theme->refresh();
        $this->assertSame($header, $theme->header_blocks);
        $this->assertSame($footer, $theme->footer_blocks);
        $this->assertSame('page-new', $theme->pages['home']['blocks'][0]['id']);
    }

    public function test_header_and_footer_accept_selected_extension_blocks_and_reject_unselected_ones(): void
    {
        $extension = $this->extensions->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensions->release($extension, '1.0.0', 1, ['elements' => [[
            'type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content', 'controls' => [],
        ]]]);
        $selection = ['acme/gallery-pro' => ['version' => '1.0.0']];
        $theme = $this->theme();
        $header = [['id' => 'header-ext', 'type' => 'ext:acme:gallery', 'props' => []]];
        $footer = [['id' => 'footer-ext', 'type' => 'ext:acme:gallery', 'props' => []]];

        $this->save($theme, 'header', $header, $selection)->assertOk();
        $this->save($theme, 'footer', $footer, $selection)->assertOk();
        $theme->refresh();
        $this->assertSame($header, $theme->header_blocks);
        $this->assertSame($footer, $theme->footer_blocks);

        $unselected = $this->theme();
        $this->save($unselected, 'header', $header)->assertStatus(422);
        $this->save($unselected, 'footer', $footer)->assertStatus(422);
    }

    public function test_invalid_target_fails_closed_and_zone_previews_render_the_selected_tree(): void
    {
        $theme = $this->theme();
        $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), [
            'target' => 'unknown', 'blocks' => [],
        ])->assertStatus(422);

        $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas.page-preview', [$theme, 'home']) . '?target=header')
            ->assertOk()->assertSee('old');
        $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas.page-preview', [$theme, 'home']) . '?target=footer')
            ->assertOk()->assertSee('old');
    }
}
