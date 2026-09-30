<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part 5 (7-part local audit task, 2026-09-19) — regression coverage for
 * the HTTP 500 reported opening Theme ID=56 (an old, pre-refactor company
 * test theme) in the Theme Builder.
 *
 * Local root-cause reproduction: Theme ID=56 does not exist in this local
 * database (it is a real Production row), so it was reproduced by
 * constructing an equivalent legacy-shaped fixture directly via a raw
 * DB insert (bypassing Theme's own $attributes JSON defaults, which only
 * apply to newly-constructed models, exactly like a genuinely old row
 * hydrated from the database would be). Two concrete malformed shapes
 * both threw an uncaught TypeError from the shared
 * packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php,
 * consumed by both saas-platform's own preview/canvas and customer-site's
 * real tenant renderer:
 *
 *   - A block list entry that is not an array at all (a bare string) —
 *     BlockRenderer::renderBlock(): Argument #1 ($block) must be of type
 *     array, string given.
 *   - A block whose `props` is a scalar instead of an array — e.g.
 *     BlockRenderer::renderNavHeader(): Argument #1 ($props) must be of
 *     type array, string given.
 *
 * The fix (BlockRenderer::renderBlocks()/renderBlock()) skips/normalizes
 * only the one malformed entry, visibly (an HTML comment marks what was
 * skipped — never a silent drop), so the rest of the theme still renders
 * instead of the whole page throwing a 500. This proves both the admin
 * Canvas view and the header/footer preview route now open cleanly for a
 * theme carrying this exact class of legacy data.
 */
class LegacyThemeRenderingTest extends TestCase
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

    /** A local stand-in for the real Theme ID=56 row's legacy shape. */
    private function legacyTheme(): int
    {
        return DB::table('themes')->insertGetId([
            'name' => 'قالب تست قدیمی شرکت',
            'slug' => 'old-company-test-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'category' => 'corporate',
            'source' => Theme::SOURCE_MANUAL,
            'status' => Theme::STATUS_DRAFT,
            'site_id' => null,
            // A bare string entry (not a block object) and a block whose
            // props is a scalar — the exact two shapes proven to crash
            // BlockRenderer before the Part 5 fix.
            'header_blocks' => json_encode(['a-corrupted-string-entry', ['type' => 'nav_header', 'props' => 'not-an-array']]),
            'footer_blocks' => json_encode([['type' => 'nav_footer', 'props' => []]]),
            'header_variants' => null,
            'footer_variants' => null,
            'pages' => json_encode(['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]]),
            'global_styles' => null,
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_canvas_opens_cleanly_for_a_legacy_theme_with_malformed_block_data(): void
    {
        $id = $this->legacyTheme();

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $id));

        $response->assertOk();
    }

    public function test_header_preview_opens_cleanly_for_a_legacy_theme_with_malformed_block_data(): void
    {
        $id = $this->legacyTheme();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.theme-builder.preview', ['theme_builder' => $id, 'zone' => 'header']));

        $response->assertOk();
        // The corrupted entries must be visibly skipped, not silently
        // dropped or crashed past.
        $response->assertSee('theme block skipped', false);
    }

    public function test_edit_page_opens_cleanly_for_a_legacy_theme_with_malformed_block_data(): void
    {
        $id = $this->legacyTheme();

        $response = $this->actingAs($this->admin())->get(route('admin.theme-builder.edit', $id));

        $response->assertOk();
    }
}
