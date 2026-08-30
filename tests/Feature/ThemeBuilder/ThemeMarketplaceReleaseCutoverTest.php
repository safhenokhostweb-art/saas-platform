<?php

namespace Tests\Feature\ThemeBuilder;

use App\Http\Controllers\Api\ThemeMarketplaceApiController;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1f — Marketplace Cutover to ThemeRelease + Structured Dependencies.
 *
 * Exercises the real ThemeMarketplaceApiController methods directly (with a
 * manually-attached tenant_site request attribute) rather than through the
 * AuthenticateTenantApi middleware's full license-validation stack — that
 * middleware is pre-existing and unrelated to this cutover; no test harness
 * for it exists yet in this repo. This still runs the actual production
 * controller code (formatTheme(), myLicenses(), download(),
 * initiatePurchase()), not a reimplementation of it.
 *
 * Phase 2d (ADR-025): fixtures now go through the real Commercial Quality
 * Gate (publishIfCompliant()), not the low-level ThemeReleaseService::
 * release() directly — every listing/show/purchase/download path exercised
 * here now requires Theme::isCommerciallyEligible(), which a raw, unlinked
 * release no longer satisfies. This is the correct, intended tightening,
 * not a workaround.
 */
class ThemeMarketplaceReleaseCutoverTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeMarketplaceApiController $controller;
    private ThemeReleaseService $releaseService;
    private ThemeCommercialQualityGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->app->make(ThemeMarketplaceApiController::class);
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
        $this->gate = $this->app->make(ThemeCommercialQualityGate::class);
    }

    private function requestForSite($site): Request
    {
        $request = Request::create('/api/theme-marketplace');
        $request->attributes->set('tenant_site', $site);
        return $request;
    }

    private function makeMarketplaceTheme(array $overrides = []): Theme
    {
        $theme = Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'cutover-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
            'is_marketplace' => true,
            'marketplace_price' => 0,
        ], $overrides));

        ThemeMarketplace::create(['theme_id' => $theme->id]);

        return $theme;
    }

    public function test_marketplace_listing_exposes_the_current_release_version(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.2.3', 'v1.2.3.');
        $site = $this->makeCrmTestSite();

        $response = $this->controller->index($this->requestForSite($site));
        $data = $response->getData(true);

        $formatted = collect($data['themes'])->firstWhere('id', $theme->id);
        $this->assertSame('1.2.3', $formatted['current_version']);
        $this->assertSame('released', $formatted['release_status']);
    }

    public function test_changelog_and_release_notes_come_from_the_release(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'Fixed the hero section.', 'Detailed release notes here.');
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertSame('Fixed the hero section.', $data['theme']['changelog']);
        $this->assertSame('Detailed release notes here.', $data['theme']['release_notes']);
        $this->assertNotNull($data['theme']['released_at']);
    }

    public function test_module_dependencies_come_from_structured_rows(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false, 'metadata' => ['description' => 'فرم تماس']],
        ]);
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertCount(1, $data['theme']['dependencies']);
        $this->assertSame('module', $data['theme']['dependencies'][0]['type']);
        $this->assertSame('forms', $data['theme']['dependencies'][0]['identifier']);
        $this->assertSame('فرم تماس', $data['theme']['dependencies'][0]['metadata']['description']);
        $this->assertSame('resolved', $data['theme']['dependency_status']);
    }

    public function test_motion_dependencies_come_from_structured_rows(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'motion_asset', 'ref' => 'medical-hero-intro', 'is_required' => false],
        ]);
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertSame('motion_asset', $data['theme']['dependencies'][0]['type']);
        $this->assertSame('medical-hero-intro', $data['theme']['dependencies'][0]['identifier']);
    }

    public function test_a_future_open_dependency_type_does_not_break_rendering(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'design_system', 'ref' => 'not-built-yet', 'is_required' => false],
        ]);
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertSame('design_system', $data['theme']['dependencies'][0]['type']);
        $this->assertSame('resolved', $data['theme']['dependency_status']);
    }

    public function test_a_null_version_constraint_is_shown_safely(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'version_constraint' => null, 'is_required' => false],
        ]);
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertArrayHasKey('version_constraint', $data['theme']['dependencies'][0]);
        $this->assertNull($data['theme']['dependencies'][0]['version_constraint']);
    }

    public function test_a_release_with_zero_dependency_rows_is_reported_unresolved_not_dependency_free(): void
    {
        $theme = $this->makeMarketplaceTheme(); // no dependencies passed at all
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertSame([], $data['theme']['dependencies']);
        $this->assertSame('unresolved', $data['theme']['dependency_status']);
        $this->assertNotSame('resolved', $data['theme']['dependency_status']);
    }

    public function test_a_theme_with_no_release_yet_is_excluded_from_the_listing(): void
    {
        // Phase 2d (ADR-025): status=PUBLISHED alone (the exact transitional
        // state every one of the 29 seeded themes was in before Phase 1d's
        // backfill) is no longer sufficient to be commercially listed — the
        // listing now requires Theme::isCommerciallyEligible(). A theme with
        // no release at all is correctly absent, not shown with degraded
        // "unreleased"/null fields as it was before this phase.
        $theme = $this->makeMarketplaceTheme();
        $site = $this->makeCrmTestSite();

        $data = $this->controller->index($this->requestForSite($site))->getData(true);
        $formatted = collect($data['themes'])->firstWhere('id', $theme->id);

        $this->assertNull($formatted);
    }

    public function test_legacy_requirements_and_version_notes_remain_readable(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $theme->marketplaceMeta->update(['requirements' => ['forms' => 'توضیح قدیمی'], 'version_notes' => 'یادداشت قدیمی']);
        $this->gate->publishIfCompliant($theme->fresh(), $this->releaseService, '1.0.0', 'v1.');
        $theme = $theme->fresh();
        $site = $this->makeCrmTestSite();

        $data = $this->controller->show($this->requestForSite($site), $theme)->getData(true);

        $this->assertSame(['forms' => 'توضیح قدیمی'], $data['theme']['requirements']);
        $this->assertSame('یادداشت قدیمی', $data['theme']['version_notes']);
    }

    public function test_free_theme_purchase_flow_is_unaffected_by_the_cutover(): void
    {
        $theme = $this->makeMarketplaceTheme(['marketplace_price' => 0]);
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $request = Request::create('/api/theme-marketplace/purchase', 'POST', [
            'redirect_back_url' => 'https://example-site.test/back',
        ]);
        $request->attributes->set('tenant_site', $site);
        $data = $this->controller->initiatePurchase($request, $theme)->getData(true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['free']);
        $this->assertNotEmpty($data['license_key']);
        $this->assertTrue($theme->hasLicenseForSite($site->id));
    }

    /**
     * Phase 3E-A — this endpoint (install()/update()'s only data source on
     * the customer-site side) previously omitted structured dependencies
     * entirely; the reconciliation dependency gate needs them at the exact
     * moment it matters, not just pre-purchase on the listing page.
     */
    public function test_download_transports_the_exact_release_dependency_identity(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => true, 'version_constraint' => '>=2.0', 'metadata' => ['description' => 'فرم تماس']],
        ]);
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id);

        $data = $this->controller->download($this->requestForSite($site), $theme->fresh())->getData(true);

        $this->assertCount(1, $data['dependencies']);
        $this->assertSame('module', $data['dependencies'][0]['type']);
        $this->assertSame('forms', $data['dependencies'][0]['identifier']);
        $this->assertTrue($data['dependencies'][0]['is_required']);
        $this->assertSame('>=2.0', $data['dependencies'][0]['version_constraint']);
        $this->assertSame('فرم تماس', $data['dependencies'][0]['metadata']['description']);
        $this->assertSame('resolved', $data['dependency_status']);
    }

    /**
     * §R — the download response's dependency shape carries only the same
     * 5 safe fields the pre-purchase listing already exposes (formatDependencies()),
     * never the underlying ThemeReleaseDependency row's own id, its
     * theme_release_id foreign key, or timestamps.
     */
    public function test_download_dependency_entries_expose_no_internal_row_fields(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id);

        $data = $this->controller->download($this->requestForSite($site), $theme->fresh())->getData(true);

        $this->assertSame(
            ['type', 'identifier', 'is_required', 'version_constraint', 'metadata'],
            array_keys($data['dependencies'][0])
        );
    }

    public function test_download_reports_unresolved_status_for_zero_dependency_rows_not_dependency_free(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.'); // no dependencies passed
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id);

        $data = $this->controller->download($this->requestForSite($site), $theme->fresh())->getData(true);

        $this->assertSame([], $data['dependencies']);
        $this->assertSame('unresolved', $data['dependency_status']);
    }

    public function test_download_stamps_the_ledger_installed_release(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id);

        $this->controller->download($this->requestForSite($site), $theme->fresh());

        $license = ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)->first();
        $this->assertSame($release->id, $license->installed_release_id);
    }

    public function test_my_licenses_exposes_the_ledger_installed_and_current_version(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->releaseService->release($theme, '2.0.0', 'v2.');
        $site = $this->makeCrmTestSite();
        ThemeLicense::grant($site->id, $theme->id);

        $data = $this->controller->myLicenses($this->requestForSite($site))->getData(true);
        $license = $data['licenses'][0];

        $this->assertSame('2.0.0', $license['installed_version']);
        $this->assertSame('2.0.0', $license['theme']['current_version']);
        $this->assertArrayHasKey('is_outdated_by_ledger', $license);
    }

    public function test_no_customer_data_is_touched_by_a_marketplace_read(): void
    {
        $theme = $this->makeMarketplaceTheme();
        $this->releaseService->release($theme, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $this->controller->index($this->requestForSite($site));
        $this->controller->show($this->requestForSite($site), $theme);

        // A pure read must not create or mutate a license, purchase order, or
        // the site itself.
        $this->assertDatabaseCount('theme_licenses', 0);
        $this->assertDatabaseCount('theme_purchase_orders', 0);
        $this->assertSame($site->title, $site->fresh()->title);
    }
}
