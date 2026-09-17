<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\User;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SP-01 (TASK 2-SEC) regression coverage.
 *
 * syncPagesForSite() used to open its own independent raw cross-server MySQL
 * connection and insert `pages` rows directly (provisionThemePagesIntoDb()),
 * bypassing ThemeInstanceRevisionService/CanvasReservedSlugs/revision history
 * — a second, independent Theme-install/page-provision authority, in
 * contradiction of ADR-052 (which had already closed this exact defect for
 * installToSite() alone). This test asserts syncPagesForSite() now shares the
 * SAME canonical authority as installToSite(): a single authenticated HTTP
 * call to the customer-site's own /builder/api/theme/install endpoint, and
 * that no local database connection to any other server is ever opened by
 * this action (RAW_THEME_PAGE_INSERT_PATHS_FROM_SYNC = 0).
 *
 * Http::fake() here fakes only the network transport boundary between the two
 * separate Laravel applications, exactly as in
 * ThemeCanvasInstallToSiteHttpTest — not a database shortcut.
 */
class ThemeCanvasSyncPagesHttpTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0913' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        if ($role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function site(): Site
    {
        $customer = Customer::create([
            'name' => 'Sync HTTP Test Customer',
            'mobile' => '09990000011',
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'Sync HTTP Test Site',
            'domain' => 'sync-http-test.local',
            'is_active' => true,
        ]);
    }

    public function test_sync_pages_for_site_calls_the_same_canonical_install_endpoint_as_install_to_site(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '2.0.0',
            'changelog' => 'Sync round-trip test release.',
            'status' => ThemeRelease::STATUS_PUBLISHED,
            'snapshot' => ['pages' => $theme->getPageList()],
            'published_at' => now(),
        ]);
        $theme->forceFill(['current_release_id' => $release->id])->save();

        Http::fake([
            'sync-http-test.local/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 2, 'revision_number' => 2, 'pages_installed' => 21,
            ], 200),
        ]);

        $site = $this->site();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.sync-pages', $theme), ['site_id' => $site->id]);

        $response->assertRedirect(route('admin.theme-builder.index'));
        $response->assertSessionHas('success');

        // RAW_THEME_PAGE_INSERT_PATHS_FROM_SYNC = 0: the only page data that
        // moved anywhere is the outbound HTTP call below — never a local
        // `pages` insert, and never a second DB connection.
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($theme, $release) {
            $body = $request->data();

            return $request->url() === 'https://sync-http-test.local/builder/api/theme/install'
                && $request->hasHeader('X-Provision-Secret', 'test-secret-value')
                && $body['theme_id'] === $theme->slug
                && $body['source_theme_version'] === '2.0.0'
                && $body['source_release_id'] === $release->id
                && count($body['pages']) === 21;
        });
    }

    public function test_sync_pages_for_site_surfaces_a_customer_side_page_namespace_collision_as_an_error_not_a_silent_success(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        Http::fake([
            'sync-http-test.local/builder/api/theme/install' => Http::response([
                'success' => false,
                'error' => 'page_namespace_collision',
                'message' => 'این عملیات صفحه(های) موجود را با این آدرس‌ها نامرئی می‌کند.',
            ], 409),
        ]);

        $site = $this->site();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.sync-pages', $theme), ['site_id' => $site->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        self::assertStringContainsString('تداخل', session('error'));
    }

    public function test_sync_pages_for_site_never_opens_a_second_database_connection(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        Http::fake([
            'sync-http-test.local/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 3, 'revision_number' => 1, 'pages_installed' => 21,
            ], 200),
        ]);

        $site = $this->site();

        $connectionsBefore = array_keys(DB::getConnections());

        $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.sync-pages', $theme), ['site_id' => $site->id]);

        $connectionsAfter = array_keys(DB::getConnections());

        self::assertSame(
            $connectionsBefore,
            $connectionsAfter,
            'syncPagesForSite() must not open any additional DB connection (e.g. a raw cross-server one) — it must delegate exclusively to the canonical HTTP install authority.'
        );
    }
}
