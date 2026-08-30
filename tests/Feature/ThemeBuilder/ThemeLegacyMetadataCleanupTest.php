<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1j/2b — Legacy Theme Metadata Cleanup.
 *
 * Phase 1j removed the one field proven fully dead (supported_modules_json)
 * and kept the version-authority pair (Theme.version/installed_theme_version/
 * isOutdated()) as an API-compatibility fallback under ADR-022. Phase 2b's
 * ADR amendment retired that whole pair — the business owner confirmed there
 * are no active customers/production Theme fleet, so the compatibility
 * reason no longer applies. This file now proves the version-authority
 * cleanup left the system consistent: no internal decision can be
 * corrupted by a legacy field that no longer exists, purchase eligibility
 * is unaffected, and the fields still deliberately retained (requirements/
 * version_notes — see ThemeMarketplaceApiController) remain readable.
 */
class ThemeLegacyMetadataCleanupTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $releaseService;
    private ThemeCommercialQualityGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
        $this->gate = $this->app->make(ThemeCommercialQualityGate::class);
    }

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'legacy-cleanup-theme-' . uniqid(),
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
    }

    // ── supported_modules_json is genuinely gone (Phase 1j) ──────────────

    public function test_supported_modules_json_column_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('theme_marketplace', 'supported_modules_json'));
    }

    public function test_theme_marketplace_can_still_be_created_without_the_dropped_column(): void
    {
        $theme = $this->makeTheme();

        $meta = ThemeMarketplace::create([
            'theme_id' => $theme->id,
            'requirements' => ['forms' => 'فرم تماس'],
            'version_notes' => 'یادداشت',
        ]);

        $this->assertDatabaseHas('theme_marketplace', ['id' => $meta->id, 'theme_id' => $theme->id]);
    }

    public function test_supported_modules_json_is_no_longer_fillable(): void
    {
        $this->assertNotContains('supported_modules_json', (new ThemeMarketplace())->getFillable());
    }

    // ── Theme.version / installed_theme_version / isOutdated() are genuinely gone (Phase 2b) ──

    public function test_theme_version_column_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('themes', 'version'));
    }

    public function test_installed_theme_version_column_no_longer_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('theme_licenses', 'installed_theme_version'));
    }

    public function test_theme_license_no_longer_exposes_isoutdated(): void
    {
        $this->assertFalse(method_exists(ThemeLicense::class, 'isOutdated'));
    }

    public function test_theme_no_longer_exposes_the_legacy_outdated_licenses_count(): void
    {
        $this->assertFalse(method_exists(Theme::class, 'outdatedLicensesCount'));
    }

    // ── the still-retained deprecated fields (requirements/version_notes) work ──

    public function test_deliberately_retained_requirements_and_version_notes_remain_readable(): void
    {
        $theme = $this->makeTheme();
        ThemeMarketplace::create([
            'theme_id' => $theme->id,
            'requirements' => ['forms' => 'فرم تماس'],
            'version_notes' => 'یادداشت نسخه',
        ]);
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $controller = $this->app->make(\App\Http\Controllers\Api\ThemeMarketplaceApiController::class);
        $request = \Illuminate\Http\Request::create('/api/theme-marketplace/' . $theme->id);
        $request->attributes->set('tenant_site', $site);

        $data = $controller->show($request, $theme)->getData(true);

        $this->assertSame(['forms' => 'فرم تماس'], $data['theme']['requirements']);
        $this->assertSame('یادداشت نسخه', $data['theme']['version_notes']);
        // Retained fields never override the ledger-authoritative ones.
        $this->assertSame('1.0.0', $data['theme']['current_version']);
    }

    public function test_purchase_eligibility_is_unaffected_by_ledger_state(): void
    {
        $theme = $this->makeTheme(['marketplace_price' => 0]);
        ThemeMarketplace::create(['theme_id' => $theme->id]);
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $controller = $this->app->make(\App\Http\Controllers\Api\ThemeMarketplaceApiController::class);
        $request = \Illuminate\Http\Request::create('/api/theme-marketplace/purchase', 'POST', [
            'redirect_back_url' => 'https://example-site.test/back',
        ]);
        $request->attributes->set('tenant_site', $site);

        $data = $controller->initiatePurchase($request, $theme)->getData(true);

        $this->assertTrue($data['success']);
        $this->assertTrue($theme->hasLicenseForSite($site->id));
    }

    // ── grant/download write the sole (ledger) version reference ────────

    public function test_grant_writes_the_ledger_installed_release_only(): void
    {
        $theme = $this->makeTheme();
        $release = $this->releaseService->release($theme, '1.0.0', 'v1.');
        $site = $this->makeCrmTestSite();

        $license = ThemeLicense::grant($site->id, $theme->id);

        $this->assertSame($release->id, $license->installed_release_id);
        $this->assertFalse(array_key_exists('installed_theme_version', $license->getAttributes()));
    }

    // ── ThemeRelease history is untouched by this cleanup ────────────────

    public function test_theme_release_history_is_untouched_by_the_cleanup(): void
    {
        $theme = $this->makeTheme();
        $release = $this->releaseService->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);

        $this->assertDatabaseHas('theme_releases', ['id' => $release->id, 'version' => '1.0.0']);
        $this->assertCount(1, $release->structuredDependencies()->get());
    }
}
