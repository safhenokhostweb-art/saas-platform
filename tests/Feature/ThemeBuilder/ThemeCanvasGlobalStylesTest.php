<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ThemeCanvasGlobalStylesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        if ($role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'Global styles test',
            'slug' => 'global-styles-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [['id' => 'header-kept', 'type' => 'heading', 'props' => ['text' => 'Header']]],
            'footer_blocks' => [['id' => 'footer-kept', 'type' => 'heading', 'props' => ['text' => 'Footer']]],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'blocks' => [['id' => 'page-kept', 'type' => 'heading', 'props' => ['text' => 'Page']]]]],
            'global_styles' => [
                'colors' => [
                    'primary' => '#123456',
                    'surface' => '#f8fafc',
                    'future_nested' => ['mode' => 'kept'],
                ],
                'typography' => ['font_body' => 'Tahoma', 'scale' => 1.2],
                'future_token' => 'keep-me',
                'plugin_metadata' => ['vendor' => 'acme', 'options' => ['dense' => true]],
            ],
            'settings' => ['unrelated' => 'kept'],
        ]);
    }

    public function test_authorized_visual_patch_normalizes_and_preserves_unknown_state(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->patchJson(
            route('admin.theme-builder.canvas.global-styles', $theme),
            ['styles' => [
                'primary_color' => '#0f766e',
                'surface_color' => '#f0fdfa',
                'type_scale' => 1.35,
                'radius_md' => '0.9rem',
            ]]
        );

        $response->assertOk()->assertJson([
            'success' => true,
            'styles' => [
                'primary_color' => '#0f766e',
                'surface_color' => '#f0fdfa',
                'type_scale' => 1.35,
                'radius_md' => '0.9rem',
            ],
        ]);
        $this->assertSame('#0f766e', $response->json('css_variables.--tb-primary'));
        $this->assertSame('#f0fdfa', $response->json('css_variables.--tb-surface'));

        $theme->refresh();
        $this->assertSame('#0f766e', $theme->global_styles['primary_color']);
        $this->assertSame('#f0fdfa', $theme->global_styles['colors']['surface']);
        $this->assertSame('#123456', $theme->global_styles['colors']['primary']);
        $this->assertSame('keep-me', $theme->global_styles['future_token']);
        $this->assertSame(['vendor' => 'acme', 'options' => ['dense' => true]], $theme->global_styles['plugin_metadata']);
        $this->assertSame(['mode' => 'kept'], $theme->global_styles['colors']['future_nested']);
        $this->assertSame('header-kept', $theme->header_blocks[0]['id']);
        $this->assertSame('footer-kept', $theme->footer_blocks[0]['id']);
        $this->assertSame('page-kept', $theme->pages['home']['blocks'][0]['id']);
        $this->assertSame(['unrelated' => 'kept'], $theme->settings);
    }

    public function test_visual_patch_rejects_invalid_or_unmanaged_input_without_writing(): void
    {
        $theme = $this->theme();
        $before = $theme->global_styles;

        $this->actingAs($this->admin())->patchJson(
            route('admin.theme-builder.canvas.global-styles', $theme),
            ['styles' => ['primary_color' => 'red']]
        )->assertStatus(422);

        $this->actingAs($this->admin())->patchJson(
            route('admin.theme-builder.canvas.global-styles', $theme),
            ['styles' => ['future_token' => 'overwrite-attempt']]
        )->assertStatus(422);

        $this->assertEquals($before, $theme->fresh()->global_styles);
    }

    public function test_raw_editor_known_value_is_loaded_by_the_visual_panel(): void
    {
        $theme = $this->theme();
        $raw = $theme->global_styles;
        $raw['primary_color'] = '#7c3aed';
        $raw['raw_only'] = ['kept' => true];

        $this->actingAs($this->admin())->putJson(route('admin.theme-builder.update', $theme), [
            'global_styles' => json_encode($raw),
        ])->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.theme-builder.canvas', $theme))
            ->assertOk()
            ->assertSee('const INITIAL_GLOBAL_STYLES')
            ->assertSee('#7c3aed');

        $this->actingAs($this->admin())->patchJson(
            route('admin.theme-builder.canvas.global-styles', $theme),
            ['styles' => ['secondary_color' => '#0891b2']]
        )->assertOk();

        $theme->refresh();
        $this->assertSame('#7c3aed', $theme->global_styles['primary_color']);
        $this->assertSame(['kept' => true], $theme->global_styles['raw_only']);
    }
}
