<?php

namespace Tests\Feature\ThemeBuilder;

use App\Helpers\AdminMenu;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase UI-C1 — proves the canonical Theme entry path: a newly-created
 * Theme must land in canonical Canvas, never the raw JSON editor. The raw
 * editor remains fully functional and reachable, but is no longer presented
 * as an isolated alternative Theme Builder — it now links into Canvas and
 * is labelled as an advanced/JSON path on the theme list.
 */
class ThemeBuilderCanonicalEntryTest extends TestCase
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

        $role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'قالب تست مسیر ورودی',
            'slug' => 'canonical-entry-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => [],
        ]);
    }

    /** A. Creating a Theme redirects to canonical Canvas. */
    public function test_store_redirects_to_canonical_canvas_not_raw_editor(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.theme-builder.store'), [
            'name' => 'تم جدید تست',
            'site_id' => '',
        ]);

        $theme = Theme::where('name', 'تم جدید تست')->firstOrFail();

        $response->assertRedirect(route('admin.theme-builder.canvas', $theme));
        $response->assertSessionHas('success');
        $this->assertStringNotContainsString(
            'هدر، فوتر و استایل‌ها را ویرایش',
            session('success')
        );
    }

    /** B. Raw editor still works and is still reachable. */
    public function test_raw_editor_route_still_reachable(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.edit', $theme));

        $response->assertOk();
    }

    /** C. Raw editor contains an obvious link to Canvas. */
    public function test_raw_editor_links_into_canvas(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.edit', $theme));

        $response->assertOk();
        $response->assertSee('ورود به قالب‌ساز');
        $response->assertSee(route('admin.theme-builder.canvas', $theme), false);
    }

    /** D. Theme list identifies raw editor as advanced/JSON rather than the primary Builder. */
    public function test_theme_list_labels_raw_editor_as_advanced_json(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.index'));

        $response->assertOk();
        $response->assertSee('ویرایش پیشرفته (JSON)');
        $response->assertDontSee('>هدر، فوتر و استایل‌ها<', false);
    }

    /** E. ThemeProfile menu still reaches the same route but has the clarified label. */
    public function test_theme_manager_menu_label_clarified_route_unchanged(): void
    {
        $this->assertSame('پروفایل‌های رنگی پلتفرم', AdminMenu::MENU_KEYS['theme_manager']);
        $this->assertSame('admin.theme-manager.index', AdminMenu::MENU_ROUTES['theme_manager']);
    }

    /** F. No existing Theme creation/publish/release behavior was removed. */
    public function test_update_and_publish_still_function_from_raw_editor(): void
    {
        $theme = $this->theme();
        $admin = $this->admin();

        $updateResponse = $this->actingAs($admin)->putJson(route('admin.theme-builder.update', $theme), [
            'global_styles' => json_encode(['colors' => ['primary' => '#123456']]),
        ]);
        $updateResponse->assertOk()->assertJson(['success' => true]);

        $publishResponse = $this->actingAs($admin)->post(route('admin.theme-builder.publish', $theme));
        $publishResponse->assertRedirect(route('admin.theme-builder.edit', $theme));
    }
}
