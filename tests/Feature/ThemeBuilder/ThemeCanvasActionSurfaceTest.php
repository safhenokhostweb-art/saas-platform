<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Role;
use App\Models\Theme;
use App\Models\ThemeRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UI-C2D + UI-C2E — proves the canonical Canvas view itself renders the new
 * action surface (checkpoint/history, clone, export, import, unpublish) and
 * that each of the underlying JSON endpoints it calls behaves correctly
 * through real HTTP, real routing/middleware, and a real database — not
 * merely the service-layer unit coverage in
 * ThemeBuilderCheckpointAndActionsTest.
 */
class ThemeCanvasActionSurfaceTest extends TestCase
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

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'قالب سطح عملیات تست',
            'slug' => 'action-surface-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['id' => 'blk_1', 'type' => 'hero', 'props' => ['title' => 'سلام']],
            ]]],
            'header_blocks' => [['id' => 'blk_h', 'type' => 'nav_header', 'props' => []]],
            'footer_blocks' => [['id' => 'blk_f', 'type' => 'nav_footer', 'props' => []]],
            'global_styles' => ['colors' => ['primary' => '#333333']],
            'settings' => ['extension_selections' => []],
        ]);
    }

    public function test_canvas_view_renders_the_new_action_surface(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $theme));

        $response->assertOk();
        $response->assertSee('tb-more-actions-toggle', false);
        $response->assertSee('tb-action-checkpoints', false);
        $response->assertSee('tb-action-clone', false);
        $response->assertSee('tb-action-export', false);
        $response->assertSee('tb-action-import', false);
        $response->assertSee('tb-action-unpublish', false);
        $response->assertSee('tb-checkpoints-modal', false);
        $response->assertSee('tb-clone-modal', false);
        // No raw JSON textarea/editor is introduced by this surface.
        $response->assertDontSee('json-header', false);
    }

    public function test_list_revisions_endpoint_returns_checkpoints_newest_first(): void
    {
        $theme = $this->theme();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.theme-builder.revision', $theme), ['label' => 'اول']);
        $this->actingAs($admin)->postJson(route('admin.theme-builder.revision', $theme), ['label' => 'دوم']);

        $response = $this->actingAs($admin)->getJson(route('admin.theme-builder.revisions', $theme));

        $response->assertOk();
        $response->assertJsonPath('revisions.0.label', 'دوم');
        $response->assertJsonPath('revisions.1.label', 'اول');
    }

    public function test_restore_revision_json_branch_used_by_canvas(): void
    {
        $theme = $this->theme();
        $admin = $this->admin();

        $checkpoint = $this->actingAs($admin)
            ->postJson(route('admin.theme-builder.revision', $theme))
            ->json('revision');

        $theme->update(['header_blocks' => [['id' => 'blk_new', 'type' => 'nav_menu', 'props' => []]]]);

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson(route('admin.theme-builder.restore-revision', $checkpoint['id']));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame([['id' => 'blk_h', 'type' => 'nav_header', 'props' => []]], $theme->fresh()->header_blocks);
    }

    public function test_clone_json_branch_returns_canvas_url_for_the_new_theme(): void
    {
        $theme = $this->theme();
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson(route('admin.theme-builder.clone', $theme), ['name' => 'کپی از طریق کانواس']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $newThemeId = $response->json('theme.id');
        $this->assertNotSame($theme->id, $newThemeId);
        $response->assertJsonPath('canvas_url', route('admin.theme-builder.canvas', $newThemeId));
    }

    public function test_import_json_branch_rejects_invalid_theme_with_422(): void
    {
        $admin = $this->admin();
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'bad-theme.json',
            json_encode(['version' => 2, 'pages' => ['home' => ['title' => 'x', 'blocks' => [
                ['id' => 'x', 'type' => 'not_a_real_block_type', 'props' => []],
            ]]]])
        );

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.theme-builder.import'), ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertSame(0, Theme::where('name', '!=', null)->count());
    }

    public function test_import_json_branch_creates_theme_and_returns_canvas_url(): void
    {
        $admin = $this->admin();
        $payload = [
            'version' => 2,
            'name' => 'وارد شده از طریق کانواس',
            'pages' => ['home' => ['title' => 'خانه', 'blocks' => []]],
            'header_blocks' => [],
            'footer_blocks' => [],
            'global_styles' => [],
            'settings' => [],
        ];
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('theme.json', json_encode($payload));

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.theme-builder.import'), ['file' => $file]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNotEmpty($response->json('canvas_url'));
    }

    public function test_unpublish_via_canvas_menu_endpoint_flips_status_only(): void
    {
        $theme = $this->theme();
        $theme->update(['status' => Theme::STATUS_PUBLISHED, 'published_at' => now()]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson(route('admin.theme-builder.unpublish', $theme));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame(Theme::STATUS_DRAFT, $theme->fresh()->status);
    }
}
