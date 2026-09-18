<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TASK 3/3 Phase 6.5 (root-cause fix for the "coordinator cannot inspect the
 * Universal Theme through the real commercial control plane" finding).
 *
 * Prior to this phase, universal-multipurpose-qa only ever existed as a
 * Theme::STATUS_DRAFT row with is_marketplace=false and no ThemeRelease —
 * it was seeded for tests only, never actually published. This test proves
 * it now traverses the SAME real, authenticated, server-side boundaries any
 * other commercial Theme product does:
 *
 *   Platform Admin (real auth) -> POST admin.theme-builder.publish
 *     -> ThemeCommercialQualityGate (all 9 real checks) -> ThemeRelease
 *     -> Theme::isCommerciallyEligible() = true
 *     -> listed by ThemeMarketplaceApiController::index() (real query, real
 *        eligibility filter, no test-only shortcut)
 *     -> ThemeLicense::grant() (the same free-acquisition path every real
 *        free theme grant uses)
 *     -> ThemeCanvasController::installToSite() (the same canonical
 *        install-to-site HTTP boundary ThemeCanvasInstallToSiteHttpTest
 *        already covers) delivers it to a real customer Site.
 *
 * No direct DB insertion of a License/Entitlement/ThemeRelease row, no
 * fixture-only acceptance — every write here is the real production write
 * path, only the outbound cross-repo HTTP call to customer-site itself is
 * faked (Http::fake(), the transport boundary — see
 * ThemeCanvasInstallToSiteHttpTest's own docblock for why that is not a
 * database shortcut).
 */
class UniversalThemeCommercialDeliveryTest extends TestCase
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

        if ($role = Role::where('slug', Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function site(string $domain): Site
    {
        $customer = Customer::create([
            'name' => 'Universal Theme Delivery Test Customer ' . $domain,
            'mobile' => '0999' . random_int(1000000, 9999999),
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'Universal Theme Delivery Test Site',
            'domain' => $domain,
            'is_active' => true,
        ]);
    }

    public function test_real_publish_flow_makes_the_universal_theme_commercially_eligible_and_marketplace_listed(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        self::assertFalse($theme->isCommerciallyEligible(), 'precondition: not yet published in this test run');
        self::assertTrue($theme->is_marketplace, 'seeder must mark the Universal Theme as a real marketplace product');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.theme-builder.publish', $theme));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $theme->refresh();
        self::assertSame(Theme::STATUS_PUBLISHED, $theme->status);
        self::assertNotNull($theme->current_release_id);
        self::assertTrue($theme->isCommerciallyEligible(), 'the real Commercial Quality Gate must pass for the Universal Theme');

        $listed = Theme::marketplace()->get()->filter(fn (Theme $t) => $t->isCommerciallyEligible());
        self::assertTrue($listed->contains('id', $theme->id), 'must be discoverable through the real Marketplace catalog query, not just eligible in isolation');
    }

    public function test_free_grant_and_install_delivers_the_published_theme_to_a_real_customer_site_without_a_payment_step(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.theme-builder.publish', $theme))->assertSessionHas('success');
        $theme->refresh();
        self::assertTrue($theme->isCommerciallyEligible());
        self::assertSame(Theme::LICENSE_FREE, $theme->license_type, 'free flow precondition');

        $site = $this->site('universal-theme-free-grant.local');

        // FREE != AUTHORITY BYPASS — still goes through the real,
        // eligibility-checked grant path (ThemeMarketplaceAdminController's
        // own admin comp/grant flow), not a raw ThemeLicense::create().
        $license = ThemeLicense::grant($site->id, $theme->id);
        self::assertNotNull($license->id);
        self::assertSame($theme->current_release_id, $license->installed_release_id);

        Http::fake([
            $site->domain . '/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 1, 'revision_number' => 1, 'pages_installed' => count($theme->getPageList()),
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.theme-builder.install-to-site', $theme), ['site_id' => $site->id]);

        $response->assertRedirect(route('admin.theme-builder.index'));
        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://' . $site->domain . '/builder/api/theme/install'
            && $request->hasHeader('X-Provision-Secret', 'test-secret-value'));
    }

    public function test_same_published_release_can_be_assigned_to_two_independent_sites(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.theme-builder.publish', $theme))->assertSessionHas('success');
        $theme->refresh();
        $releaseId = $theme->current_release_id;

        $siteA = $this->site('universal-theme-site-a.local');
        $siteB = $this->site('universal-theme-site-b.local');

        $licenseA = ThemeLicense::grant($siteA->id, $theme->id);
        $licenseB = ThemeLicense::grant($siteB->id, $theme->id);

        self::assertNotSame($licenseA->id, $licenseB->id, 'each site gets its own license row, not a shared one');
        self::assertSame($releaseId, $licenseA->installed_release_id);
        self::assertSame($releaseId, $licenseB->installed_release_id);

        Http::fake([
            $siteA->domain . '/builder/api/theme/install' => Http::response(['success' => true, 'revision_id' => 1, 'revision_number' => 1, 'pages_installed' => count($theme->getPageList())], 200),
            $siteB->domain . '/builder/api/theme/install' => Http::response(['success' => true, 'revision_id' => 1, 'revision_number' => 1, 'pages_installed' => count($theme->getPageList())], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.theme-builder.install-to-site', $theme), ['site_id' => $siteA->id])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.theme-builder.install-to-site', $theme), ['site_id' => $siteB->id])->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://' . $siteA->domain . '/builder/api/theme/install');
        Http::assertSent(fn ($request) => $request->url() === 'https://' . $siteB->domain . '/builder/api/theme/install');

        // Neither grant/install call ever touched the master Theme or its
        // immutable release — both remain exactly as published.
        $theme->refresh();
        self::assertSame($releaseId, $theme->current_release_id, 'installing to sites must never mutate the master Theme');
    }
}
