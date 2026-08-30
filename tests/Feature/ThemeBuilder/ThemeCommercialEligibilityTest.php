<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeBundle;
use App\Models\ThemeLicense;
use App\Models\ThemeMarketplace;
use App\Models\ThemeQualityReport;
use App\Models\ThemeRelease;
use App\Models\User;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ThemeBuilderService;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 2d — Close Theme Serving Bypass & Make Release Ledger the Sole
 * Commercial Eligibility Authority (ADR-025).
 *
 * Proves Theme::isCommerciallyEligible() is the one rule every serving,
 * Marketplace, license, bundle, and site-resolution decision now respects —
 * and that Theme.status alone (the pre-Phase-2d bypass ADR-024 §6 recorded)
 * can no longer make a theme servable/purchasable/licensable.
 */
class ThemeCommercialEligibilityTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $releaseService;
    private ThemeCommercialQualityGate $gate;
    private ThemeBuilderService $builderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
        $this->gate = $this->app->make(ThemeCommercialQualityGate::class);
        $this->builderService = $this->app->make(ThemeBuilderService::class);
    }

    private function makeGoodTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'eligibility-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED, // authoring-only — must never grant eligibility by itself
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'یک قالب کامل برای تست.',
            'header_blocks' => [['type' => 'text', 'props' => ['text' => 'هدر']]],
            'footer_blocks' => [['type' => 'text', 'props' => ['text' => 'فوتر']]],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
            'global_styles' => [],
            'settings' => [],
            'is_marketplace' => true,
            'marketplace_price' => 0,
        ], $overrides));
    }

    private function publishEligible(Theme $theme, string $version = '1.0.0'): ThemeRelease
    {
        return $this->gate->publishIfCompliant($theme, $this->releaseService, $version, 'v.');
    }

    // ── 1. status=published + no current release ⇒ not eligible ──────────

    public function test_status_published_with_no_current_release_is_not_commercially_eligible(): void
    {
        $theme = $this->makeGoodTheme();

        $this->assertSame(Theme::STATUS_PUBLISHED, $theme->status);
        $this->assertNull($theme->current_release_id);
        $this->assertFalse($theme->isCommerciallyEligible());
        $this->assertSame(['no_current_release'], $theme->commercialIneligibilityReasons());
    }

    // ── 2. current release + passing report ⇒ eligible ────────────────────

    public function test_a_current_release_with_a_passing_report_is_commercially_eligible(): void
    {
        $theme = $this->makeGoodTheme();
        $this->publishEligible($theme);

        $this->assertTrue($theme->fresh()->isCommerciallyEligible());
        $this->assertSame([], $theme->fresh()->commercialIneligibilityReasons());
    }

    // ── 3. current release not published (superseded/deprecated) ⇒ not eligible ──

    public function test_a_non_published_current_release_is_not_eligible(): void
    {
        $theme = $this->makeGoodTheme();
        $release = $this->publishEligible($theme);
        $release->update(['status' => ThemeRelease::STATUS_DEPRECATED]);

        $this->assertSame(['release_not_published'], $theme->fresh()->commercialIneligibilityReasons());
    }

    // ── 4. release belonging to another theme ⇒ fails integrity ───────────

    public function test_a_current_release_pointer_to_another_themes_release_fails_integrity(): void
    {
        $themeA = $this->makeGoodTheme(['slug' => 'eligibility-a-' . uniqid()]);
        $themeB = $this->makeGoodTheme(['slug' => 'eligibility-b-' . uniqid()]);
        $releaseB = $this->publishEligible($themeB);

        // Simulate a corrupted/mismatched pointer — never producible through
        // normal application flow (ThemeReleaseService always derives the
        // release from $theme->releases()), only through direct tampering.
        $themeA->current_release_id = $releaseB->id;
        $themeA->save();

        $this->assertSame(['release_theme_mismatch'], $themeA->fresh()->commercialIneligibilityReasons());
    }

    // ── 5. release without a passing quality report ⇒ not eligible ────────

    public function test_a_release_with_no_linked_passing_report_is_not_eligible(): void
    {
        $theme = $this->makeGoodTheme();
        // Simulates a pre-Phase-2c backfilled release: created directly via
        // the low-level writer, never evaluated/linked.
        $release = $this->releaseService->release($theme, '1.0.0', 'legacy backfill, no gate linkage');

        $this->assertSame(['no_passing_quality_report'], $theme->fresh()->commercialIneligibilityReasons());
        $this->assertSame(0, ThemeQualityReport::where('theme_release_id', $release->id)->count());
    }

    // ── 6. a theme that fails the gate never becomes servable ─────────────

    public function test_a_theme_that_fails_the_gate_cannot_be_resolved_for_a_site(): void
    {
        $broken = $this->makeGoodTheme(['pages' => []]);
        try {
            $this->publishEligible($broken);
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $this->assertNull($this->builderService->resolveForSite(null));
    }

    // ── 7. catalog reconstruction alone grants no eligibility ─────────────

    public function test_catalog_reconstruction_alone_does_not_grant_commercial_eligibility(): void
    {
        $theme = $this->makeGoodTheme(); // simulates a raw canonical seed row: status=published, no release

        $this->assertFalse($theme->isCommerciallyEligible());
        $this->assertNull($this->builderService->resolveForSite(null));
    }

    // ── 8. the gated release/backfill path grants eligibility ─────────────

    public function test_the_gated_backfill_path_grants_eligibility(): void
    {
        $theme = $this->makeGoodTheme();

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $this->assertTrue($theme->fresh()->isCommerciallyEligible());
    }

    // ── 9. admin authoring/preview works before any release exists ────────

    public function test_authoring_and_preview_work_before_any_commercial_release_exists(): void
    {
        $theme = $this->makeGoodTheme(); // status=published, never released

        $updated = $this->builderService->updateTheme($theme, ['description' => 'ویرایش شده توسط ادمین']);
        $revision = $this->builderService->saveRevision($theme->fresh(), 'پیش‌نمایش قبل از انتشار تجاری');

        $this->assertSame('ویرایش شده توسط ادمین', $updated->description);
        $this->assertNotNull($revision->id);
        $this->assertFalse($theme->fresh()->isCommerciallyEligible());
    }

    // ── 10. Marketplace listing respects eligibility ───────────────────────

    public function test_marketplace_listing_excludes_a_commercially_ineligible_theme(): void
    {
        $eligible = $this->makeGoodTheme(['slug' => 'eligibility-listed-' . uniqid()]);
        $this->publishEligible($eligible);
        ThemeMarketplace::create(['theme_id' => $eligible->id]);

        $ineligible = $this->makeGoodTheme(['slug' => 'eligibility-unlisted-' . uniqid()]); // status=published, no release
        ThemeMarketplace::create(['theme_id' => $ineligible->id]);

        $site = $this->makeCrmTestSite();
        $request = \Illuminate\Http\Request::create('/api/theme-marketplace');
        $request->attributes->set('tenant_site', $site);

        $controller = $this->app->make(\App\Http\Controllers\Api\ThemeMarketplaceApiController::class);
        $data = $controller->index($request)->getData(true);
        $slugs = array_column($data['themes'], 'slug');

        $this->assertContains($eligible->slug, $slugs);
        $this->assertNotContains($ineligible->slug, $slugs);
    }

    // ── 11. purchase respects eligibility ──────────────────────────────────

    public function test_purchase_is_rejected_for_a_commercially_ineligible_theme(): void
    {
        $theme = $this->makeGoodTheme(); // status=published, no release
        $site = $this->makeCrmTestSite();

        $controller = $this->app->make(\App\Http\Controllers\Api\ThemeMarketplaceApiController::class);
        $request = \Illuminate\Http\Request::create('/api/theme-marketplace/' . $theme->id . '/initiate-purchase', 'POST', [
            'redirect_back_url' => 'https://example-site.test/back',
        ]);
        $request->attributes->set('tenant_site', $site);

        $response = $controller->initiatePurchase($request, $theme);
        $data = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertFalse($theme->hasLicenseForSite($site->id));
    }

    // ── 12. license grant respects eligibility (admin manual + bundle) ────

    public function test_admin_manual_license_grant_is_rejected_for_an_ineligible_theme(): void
    {
        $theme = $this->makeGoodTheme();
        $site = $this->makeCrmTestSite();
        $admin = User::create([
            'mobile' => '09' . random_int(100000000, 999999999),
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.theme-marketplace.grant-license'), [
            'site_id' => $site->id,
            'theme_id' => $theme->id,
        ]);

        $response->assertRedirect();
        $this->assertFalse($theme->hasLicenseForSite($site->id));
    }

    public function test_bundle_grant_skips_ineligible_themes_and_grants_only_eligible_ones(): void
    {
        $eligible = $this->makeGoodTheme(['slug' => 'eligibility-bundle-good-' . uniqid()]);
        $this->publishEligible($eligible);
        $ineligible = $this->makeGoodTheme(['slug' => 'eligibility-bundle-bad-' . uniqid()]);

        $bundle = ThemeBundle::create([
            'name' => 'باندل تست',
            'slug' => 'eligibility-bundle-' . uniqid(),
            'price' => 0,
            'theme_ids' => [$eligible->id, $ineligible->id],
            'is_active' => true,
        ]);

        $site = $this->makeCrmTestSite();
        $count = $bundle->grantLicensesToSite($site->id);

        $this->assertSame(1, $count);
        $this->assertTrue($eligible->hasLicenseForSite($site->id));
        $this->assertFalse($ineligible->hasLicenseForSite($site->id));
    }

    // ── 13. download/install respects eligibility ──────────────────────────

    public function test_download_is_rejected_for_a_theme_that_became_ineligible_after_licensing(): void
    {
        $theme = $this->makeGoodTheme();
        $release = $this->publishEligible($theme);
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id);

        // The theme becomes ineligible after the license was already granted.
        $release->update(['status' => ThemeRelease::STATUS_DEPRECATED]);

        $controller = $this->app->make(\App\Http\Controllers\Api\ThemeMarketplaceApiController::class);
        $request = \Illuminate\Http\Request::create('/api/theme-marketplace/' . $theme->id . '/download');
        $request->attributes->set('tenant_site', $site);

        $response = $controller->download($request, $theme->fresh());
        $data = $response->getData(true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    // ── 14. site resolution respects eligibility ───────────────────────────

    public function test_site_resolution_falls_back_when_the_assigned_theme_is_ineligible(): void
    {
        $ineligible = $this->makeGoodTheme(['slug' => 'eligibility-assigned-bad-' . uniqid()]);
        $fallback = $this->makeGoodTheme(['slug' => 'eligibility-assigned-fallback-' . uniqid()]);
        $this->publishEligible($fallback);

        $site = $this->makeCrmTestSite();
        $site->update(['theme_id' => $ineligible->id]);

        $resolved = $this->builderService->resolveForSite($site->id);

        $this->assertNotNull($resolved);
        $this->assertSame($fallback->id, $resolved->id);
    }

    public function test_site_resolution_uses_the_assigned_theme_when_it_is_eligible(): void
    {
        $assigned = $this->makeGoodTheme(['slug' => 'eligibility-assigned-good-' . uniqid()]);
        $this->publishEligible($assigned);

        $site = $this->makeCrmTestSite();
        $site->update(['theme_id' => $assigned->id]);

        $resolved = $this->builderService->resolveForSite($site->id);

        $this->assertNotNull($resolved);
        $this->assertSame($assigned->id, $resolved->id);
    }

    // ── 15/16. rollback semantics ──────────────────────────────────────────

    public function test_rollback_to_a_previously_approved_immutable_release_restores_eligibility(): void
    {
        $theme = $this->makeGoodTheme();
        $v1 = $this->publishEligible($theme, '1.0.0');
        $this->publishEligible($theme->fresh(), '1.1.0');

        $this->assertTrue($theme->fresh()->isCommerciallyEligible());

        $this->releaseService->rollback($theme->fresh(), '1.0.0');

        $rolledBack = $theme->fresh();
        $this->assertSame($v1->id, $rolledBack->current_release_id);
        $this->assertTrue($rolledBack->isCommerciallyEligible());
    }

    public function test_rollback_to_a_release_with_no_linked_report_fails_closed(): void
    {
        $theme = $this->makeGoodTheme();
        // A legacy, pre-gate release with no linked report.
        $legacy = $this->releaseService->release($theme, '1.0.0', 'legacy, unlinked.');
        $this->publishEligible($theme->fresh(), '1.1.0');

        $this->assertTrue($theme->fresh()->isCommerciallyEligible());

        $this->releaseService->rollback($theme->fresh(), '1.0.0');

        $rolledBack = $theme->fresh();
        $this->assertSame($legacy->id, $rolledBack->current_release_id);
        $this->assertFalse($rolledBack->isCommerciallyEligible());
        $this->assertSame(['no_passing_quality_report'], $rolledBack->commercialIneligibilityReasons());
    }

    // ── reconciliation command (Phase 2d bridge for pre-2c releases) ──────

    public function test_the_reconciliation_command_links_a_legacy_release_without_creating_a_new_one(): void
    {
        $theme = $this->makeGoodTheme();
        $legacy = $this->releaseService->release($theme, '1.0.0', 'legacy, unlinked.');

        $this->assertFalse($theme->fresh()->isCommerciallyEligible());

        $this->artisan('theme-release:link-quality-reports')->assertExitCode(0);

        $theme->refresh();
        $this->assertTrue($theme->isCommerciallyEligible());
        $this->assertSame(1, $theme->releases()->count()); // no new release created
        $this->assertSame($legacy->id, $theme->current_release_id);
    }

    public function test_the_reconciliation_command_leaves_a_genuinely_failing_theme_unlinked(): void
    {
        $theme = $this->makeGoodTheme();
        $legacy = $this->releaseService->release($theme, '1.0.0', 'legacy, unlinked.');
        // The theme has since degraded (e.g. edited into a broken state).
        $theme->update(['pages' => []]);

        $this->artisan('theme-release:link-quality-reports')->assertExitCode(1);

        $this->assertFalse($theme->fresh()->isCommerciallyEligible());
        $this->assertSame(0, ThemeQualityReport::where('theme_release_id', $legacy->id)->count());
    }

    // ── 18. no customer data touched by any eligibility decision ──────────

    public function test_no_customer_data_is_touched_by_eligibility_resolution(): void
    {
        $site = $this->makeCrmTestSite();
        $instance = $this->makeGoodTheme(['slug' => 'eligibility-instance-' . uniqid(), 'site_id' => $site->id]);

        $this->builderService->resolveForSite($site->id);

        $instance->refresh();
        $this->assertNull($instance->current_release_id);
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }
}
