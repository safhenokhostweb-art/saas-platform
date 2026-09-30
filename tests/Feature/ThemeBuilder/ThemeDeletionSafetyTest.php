<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part 6 (7-part local audit task, 2026-09-19) — regression coverage for
 * the HTTP 503 reported deleting the old company test theme from Theme
 * Builder.
 *
 * Root cause, confirmed by real local reproduction: Admin\
 * ThemeBuilderController::destroy() called $theme_builder->delete() with
 * no guard at all. theme_releases.theme_id is a RESTRICT foreign key
 * (database/migrations/2026_08_25_090000_create_theme_releases_table.php)
 * — deleting a Theme that still has a release row throws an uncaught
 * Illuminate\Database\QueryException (SQLSTATE 23000 / MySQL error 1451).
 * Locally this surfaces as a bare 500 (Laravel's default rendering for an
 * uncaught non-HTTP exception); production reported 503, which is a
 * deployment/proxy-layer detail this local checkout cannot reproduce —
 * the real, fixed defect is the same uncaught exception either way.
 *
 * destroy() now checks the actual blocking relations explicitly (current
 * release, site assignment, an issued license, a live Marketplace
 * listing) and returns a clear 409-equivalent flash message instead of
 * ever reaching the database in an unsafe state, and performs a
 * transaction-safe cascade (clearing superseded release history first)
 * when a theme is genuinely safe to delete.
 */
class ThemeDeletionSafetyTest extends TestCase
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

    private function site(array $overrides = []): Site
    {
        $customer = Customer::create([
            'name' => 'Theme Deletion Test Customer',
            'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        return Site::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'Theme Deletion Test Site',
            'db_host' => '127.0.0.1',
            'db_name' => 'test_site_' . uniqid(),
            'db_user' => 'root',
        ], $overrides));
    }

    private function theme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست',
            'slug' => 'deletion-test-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_MANUAL,
            'status' => Theme::STATUS_DRAFT,
            'category' => 'corporate',
            'header_blocks' => [], 'footer_blocks' => [], 'pages' => [], 'global_styles' => [], 'settings' => [],
        ], $overrides));
    }

    public function test_a_safe_test_theme_deletes_successfully(): void
    {
        $theme = $this->theme();
        $themeId = $theme->id;

        $response = $this->actingAs($this->admin())->delete(route('admin.theme-builder.destroy', $theme));

        $response->assertRedirect(route('admin.theme-builder.index'));
        $response->assertSessionHas('success');
        self::assertNull(Theme::find($themeId), 'a theme with no current release, no site assignment, and no license must actually be deleted');
    }

    public function test_a_theme_with_superseded_release_history_still_deletes_safely(): void
    {
        // A theme that was once released but is not commercially active
        // (no current_release_id) must still delete cleanly — the RESTRICT
        // FK would otherwise block it purely because a release ROW exists,
        // regardless of whether it's "active".
        $theme = $this->theme();
        ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '0.9.0',
            'status' => 'superseded',
            'snapshot' => [],
            'changelog' => 'old test release',
        ]);
        $themeId = $theme->id;

        $response = $this->actingAs($this->admin())->delete(route('admin.theme-builder.destroy', $theme));

        $response->assertRedirect(route('admin.theme-builder.index'));
        $response->assertSessionHas('success');
        self::assertNull(Theme::find($themeId));
    }

    public function test_a_theme_with_a_current_release_cannot_be_deleted(): void
    {
        $theme = $this->theme(['status' => Theme::STATUS_PUBLISHED]);
        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '1.0.0',
            'status' => 'published',
            'snapshot' => [],
            'changelog' => 'v1',
        ]);
        $theme->forceFill(['current_release_id' => $release->id])->save();

        $response = $this->actingAs($this->admin())->delete(route('admin.theme-builder.destroy', $theme));

        $response->assertRedirect(route('admin.theme-builder.index'));
        $response->assertSessionHas('error');
        self::assertNotNull(Theme::find($theme->id), 'an active theme must never actually be deleted');
    }

    public function test_a_theme_assigned_to_a_site_cannot_be_deleted(): void
    {
        $theme = $this->theme();
        $site = $this->site(['theme_id' => $theme->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.theme-builder.destroy', $theme));

        $response->assertSessionHas('error');
        self::assertNotNull(Theme::find($theme->id));
        self::assertNotNull(Site::find($site->id), 'the site itself must be untouched');
    }

    public function test_a_theme_with_an_issued_license_cannot_be_deleted(): void
    {
        $theme = $this->theme();
        $site = $this->site();
        ThemeLicense::create([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'license_key' => 'test-license-key-' . uniqid(),
            'is_permanent' => true,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.theme-builder.destroy', $theme));

        $response->assertSessionHas('error');
        self::assertNotNull(Theme::find($theme->id));
    }

    public function test_json_request_for_a_blocked_deletion_gets_a_409_with_a_reason(): void
    {
        $theme = $this->theme(['status' => Theme::STATUS_PUBLISHED]);
        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '1.0.0',
            'status' => 'published',
            'snapshot' => [],
            'changelog' => 'v1',
        ]);
        $theme->forceFill(['current_release_id' => $release->id])->save();

        $response = $this->actingAs($this->admin())
            ->deleteJson(route('admin.theme-builder.destroy', $theme));

        $response->assertStatus(409);
        $response->assertJsonStructure(['error', 'message', 'reasons']);
    }
}
