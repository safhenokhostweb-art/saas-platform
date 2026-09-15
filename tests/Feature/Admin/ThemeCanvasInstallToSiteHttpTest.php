<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\User;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Builder integrity final gate (2026-09-12) — ADR-052.
 *
 * installToSite() used to write directly into the customer's own MySQL
 * database over a raw cross-server connection (settings + a second,
 * uncoordinated raw `pages` insert), bypassing the customer-site's own
 * ThemeInstanceRevisionService entirely. That created real is_protected
 * Page rows that collided with the same slugs the installed Canvas theme
 * owned, which made the customer's *own* ThemeInstanceRevisionService
 * self-heal/backfill (and any later save/publish) fail with a real
 * ConflictHttpException — a genuine, reproduced production bug.
 *
 * This test asserts the FIXED call graph: installToSite() now makes a
 * single authenticated HTTP call to the customer-site's own canonical
 * /builder/api/theme/install endpoint (the same one PageBuilderController
 * exposes, guarded by the existing X-Provision-Secret middleware), passing
 * through real provenance (source_theme_id/version/release_id) and the
 * theme's real 16-page/header/footer/global-styles/extension-assets data.
 * There is no longer a second, raw-SQL install authority.
 *
 * Http::fake() here fakes only the network transport boundary between the
 * two separate Laravel applications (exactly like any test of an outbound
 * webhook call) — it is not a database shortcut. The customer-site half of
 * this contract (that the endpoint really does create a ThemeInstanceRevision,
 * transfers all pages without collision, and a subsequent save/publish and
 * public render succeed) is proven separately, against a real migrated
 * customer-site database, by
 * customer-site/tests/Feature/ThemeCanvas/ThemeInstallRoundTripTest.php.
 */
class ThemeCanvasInstallToSiteHttpTest extends TestCase
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

    private function site(): Site
    {
        $customer = Customer::create([
            'name' => 'Install HTTP Test Customer',
            'mobile' => '09990000001',
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'Install HTTP Test Site',
            'domain' => 'install-http-test.local',
            'is_active' => true,
        ]);
    }

    public function test_install_to_site_calls_the_canonical_customer_install_endpoint_with_real_provenance_and_pages(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '1.2.3',
            'changelog' => 'Install round-trip test release.',
            'status' => ThemeRelease::STATUS_PUBLISHED,
            'snapshot' => ['pages' => $theme->getPageList()],
            'published_at' => now(),
        ]);
        $theme->forceFill(['current_release_id' => $release->id])->save();

        Http::fake([
            'install-http-test.local/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 1, 'revision_number' => 1, 'pages_installed' => 16,
            ], 200),
        ]);

        $site = $this->site();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.install-to-site', $theme), ['site_id' => $site->id]);

        $response->assertRedirect(route('admin.theme-builder.index'));
        $response->assertSessionHas('success');

        Http::assertSent(function ($request) use ($theme, $release) {
            $body = $request->data();

            return $request->url() === 'https://install-http-test.local/builder/api/theme/install'
                && $request->hasHeader('X-Provision-Secret', 'test-secret-value')
                && $body['theme_id'] === $theme->slug
                && $body['theme_name'] === $theme->name
                && $body['source_theme_id'] === (string) $theme->id
                && $body['source_theme_version'] === '1.2.3'
                && $body['source_release_id'] === $release->id
                && count($body['pages']) === 16
                && !empty($body['header_blocks'])
                && !empty($body['footer_blocks'])
                // THEME-03: Theme-owned selectable variants — the
                // Universal Theme ships 6 structurally distinct presets
                // each, so this is real evidence, not a shape check.
                && count($body['header_variants']) === 6
                && count($body['footer_variants']) === 6
                && array_key_exists('global_styles', $body);
        });
    }

    public function test_install_to_site_surfaces_a_customer_side_page_namespace_collision_as_an_error_not_a_silent_success(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        Http::fake([
            'install-http-test.local/builder/api/theme/install' => Http::response([
                'success' => false,
                'error' => 'page_namespace_collision',
                'message' => 'این عملیات صفحه(های) موجود را با این آدرس‌ها نامرئی می‌کند.',
            ], 409),
        ]);

        $site = $this->site();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.install-to-site', $theme), ['site_id' => $site->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        self::assertStringContainsString('تداخل', session('error'));
    }

    public function test_install_to_site_requires_a_domain_on_the_target_site(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        $customer = Customer::create(['name' => 'No Domain Customer', 'mobile' => '09990000002']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'No Domain Site',
            'domain' => '',
            'is_active' => true,
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.install-to-site', $theme), ['site_id' => $site->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Http::assertNothingSent();
    }
}
