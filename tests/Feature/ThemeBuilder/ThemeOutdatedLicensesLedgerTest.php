<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\User;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1i/2b — SaaS Admin Theme License Ledger Migration.
 *
 * Proves Theme::outdatedLicensesCountByLedger() — the canonical aggregate
 * the admin "N outdated" badge calls — is the sole outdated-state authority.
 * Phase 1i introduced it with a legacy Theme.version fallback (ADR-021/022);
 * Phase 2b retired that fallback entirely (no active-customer compatibility
 * reason remains) — a license isOutdatedByLedger() can't answer for is
 * simply not counted, never fabricated as outdated.
 */
class ThemeOutdatedLicensesLedgerTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $releaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
    }

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'ledger-outdated-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    // ── 1/2. ledger-current excluded, ledger-outdated included ───────────

    public function test_a_ledger_current_license_is_excluded_from_the_outdated_count(): void
    {
        $theme = $this->makeTheme();
        $release = $this->releaseService->release($theme, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id); // installed_release_id = $release->id, current

        $this->assertSame(0, $theme->fresh()->outdatedLicensesCountByLedger());
    }

    public function test_a_ledger_outdated_license_is_included_in_the_outdated_count(): void
    {
        $theme = $this->makeTheme();
        $site = $this->makeCrmTestSite();
        $this->releaseService->release($theme, '1.0.0', 'v1.');
        ThemeLicense::grant($site->id, $theme->id); // installed at v1.0.0
        $this->releaseService->release($theme->fresh(), '1.1.0', 'v2.'); // theme moves ahead

        $this->assertSame(1, $theme->fresh()->outdatedLicensesCountByLedger());
    }

    // ── 3. an unresolvable ledger state is never fabricated as outdated ──

    public function test_a_license_the_ledger_cannot_answer_for_is_not_counted_as_outdated(): void
    {
        // A license granted before any release existed: installed_release_id
        // stays null forever unless re-granted, so the ledger genuinely has
        // no answer for it. Phase 2b: there is no legacy fallback left to
        // consult either — an unresolvable state must never be fabricated
        // as "outdated".
        $theme = $this->makeTheme();
        $site = $this->makeCrmTestSite();
        $license = ThemeLicense::grant($site->id, $theme->id); // no release exists yet
        $this->assertNull($license->installed_release_id);

        $this->assertNull($license->fresh()->isOutdatedByLedger());
        $this->assertSame(0, $theme->fresh()->outdatedLicensesCountByLedger());
    }

    public function test_unknown_state_on_both_sides_is_never_counted_as_outdated(): void
    {
        $theme = $this->makeTheme();
        $site = $this->makeCrmTestSite();
        // A license with genuinely no ledger evidence at all.
        $license = ThemeLicense::create([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'license_key' => 'NO-EVIDENCE-KEY',
            'is_permanent' => true,
            'purchased_at' => now(),
            'installed_release_id' => null,
        ]);

        $this->assertNull($license->isOutdatedByLedger());
        $this->assertSame(0, $theme->fresh()->outdatedLicensesCountByLedger());
    }

    // ── 6. multiple licenses aggregate correctly ─────────────────────────

    public function test_multiple_licenses_aggregate_correctly(): void
    {
        $theme = $this->makeTheme();
        $this->releaseService->release($theme, '1.0.0', 'v1.');

        $siteGrantedBeforeV2 = $this->makeCrmTestSite();
        ThemeLicense::grant($siteGrantedBeforeV2->id, $theme->id); // installed at v1.0.0

        $this->releaseService->release($theme->fresh(), '1.1.0', 'v2.');

        // A second license, also pinned to v1.0.0 (as if granted before the
        // second release existed) — both this and the one above are now
        // behind once v1.1.0 exists.
        $siteStillOld = $this->makeCrmTestSite();
        ThemeLicense::create([
            'site_id' => $siteStillOld->id,
            'theme_id' => $theme->id,
            'license_key' => 'OLD-INSTALL-KEY',
            'is_permanent' => true,
            'purchased_at' => now(),
            'installed_release_id' => $theme->fresh()->releases()->where('version', '1.0.0')->first()->id,
        ]);

        $siteFresh = $this->makeCrmTestSite();
        ThemeLicense::grant($siteFresh->id, $theme->id); // current, granted after v1.1.0

        // Two licenses pinned to v1.0.0 while the theme is now at v1.1.0 are
        // both outdated; only the one granted after v1.1.0 is current.
        $this->assertSame(2, $theme->fresh()->outdatedLicensesCountByLedger());
    }

    // ── 7. unrelated theme licenses never affect the count ───────────────

    public function test_unrelated_theme_licenses_do_not_affect_the_aggregate(): void
    {
        $themeA = $this->makeTheme(['slug' => 'ledger-theme-a-' . uniqid()]);
        $themeB = $this->makeTheme(['slug' => 'ledger-theme-b-' . uniqid()]);
        $this->releaseService->release($themeA, '1.0.0', 'v1.');
        $this->releaseService->release($themeB, '1.0.0', 'v1.');

        $siteA = $this->makeCrmTestSite();
        $siteB = $this->makeCrmTestSite();
        ThemeLicense::grant($siteA->id, $themeA->id);
        ThemeLicense::grant($siteB->id, $themeB->id);

        // Only theme B moves forward, making only its own license outdated.
        $this->releaseService->release($themeB->fresh(), '2.0.0', 'v2.');

        $this->assertSame(0, $themeA->fresh()->outdatedLicensesCountByLedger());
        $this->assertSame(1, $themeB->fresh()->outdatedLicensesCountByLedger());
    }

    // ── 8. Admin Marketplace badge uses the ledger-aware aggregate ───────

    public function test_admin_marketplace_index_badge_uses_the_ledger_aware_count(): void
    {
        $admin = User::create([
            'mobile' => '09' . random_int(100000000, 999999999),
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $theme = $this->makeTheme(['is_marketplace' => true]);
        \App\Models\ThemeMarketplace::create(['theme_id' => $theme->id]);
        $this->releaseService->release($theme, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id); // current — no outdated badge expected

        $response = $this->actingAs($admin)->get(route('admin.theme-marketplace.index'));

        $response->assertOk();
        $response->assertDontSee('آپدیت', false);
    }

    // ── 9. existing purchase/grant flow unchanged ────────────────────────

    public function test_grant_flow_is_unaffected_by_the_new_aggregate(): void
    {
        $theme = $this->makeTheme();
        $this->releaseService->release($theme, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $license = ThemeLicense::grant($site->id, $theme->id);

        $this->assertTrue($theme->hasLicenseForSite($site->id));
        $this->assertNotNull($license->license_key);
        $this->assertTrue($license->is_permanent);
    }
}
