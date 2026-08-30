<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1d/2b — ThemeLicense version transition (ADR-021 §3/§6, ADR-022
 * amendment).
 *
 * Proves `installed_release_id`/`isOutdatedByLedger()` — the sole
 * commercial version-state authority since Phase 2b retired the legacy
 * Theme.version/installed_theme_version/isOutdated() pair — never fabricates
 * a release reference or an outdated state for a license it has no evidence
 * for, and that the commercial right itself (hasLicenseForSite) is
 * unaffected either way.
 */
class ThemeLicenseReleaseTransitionTest extends TestCase
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
            'slug' => 'license-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [['type' => 'header_basic']],
            'footer_blocks' => [['type' => 'footer_basic']],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    public function test_a_license_granted_after_a_release_exists_tracks_the_ledger(): void
    {
        $theme = $this->makeTheme();
        $release = $this->releaseService->release($theme, '1.0.0', 'Initial release.');
        $site = $this->makeCrmTestSite();

        $license = ThemeLicense::grant($site->id, $theme->id);

        $this->assertSame($release->id, $license->installed_release_id);
        $this->assertTrue($license->installedRelease->is($release));
    }

    public function test_a_license_granted_before_any_release_exists_has_a_null_ledger_reference(): void
    {
        $theme = $this->makeTheme(); // never released
        $site = $this->makeCrmTestSite();

        $license = ThemeLicense::grant($site->id, $theme->id);

        $this->assertNull($license->installed_release_id);
        $this->assertNull($license->installedRelease);
    }

    public function test_a_license_with_no_ledger_reference_reports_unknown_never_a_guess(): void
    {
        $theme = $this->makeTheme();
        $site = $this->makeCrmTestSite();

        // Simulates a license row that predates the release ledger entirely
        // — created with no installed_release_id, exactly as a pre-Phase-1d
        // license would look after Phase 2b's column removal.
        $license = ThemeLicense::create([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'license_key' => 'PRE-LEDGER-TEST-KEY',
            'is_permanent' => true,
            'purchased_at' => now(),
            'installed_release_id' => null,
        ]);

        $this->assertNull($license->isOutdatedByLedger());
    }

    public function test_no_entitlement_is_lost_for_a_license_the_ledger_cannot_answer_for(): void
    {
        $theme = $this->makeTheme();
        $site = $this->makeCrmTestSite();

        $license = ThemeLicense::create([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'license_key' => 'PRE-LEDGER-ENTITLEMENT-KEY',
            'is_permanent' => true,
            'purchased_at' => now(),
        ]);

        // The commercial right (hasLicenseForSite) is unaffected by the
        // license having no ledger reference — still resolvable purely from
        // site_id/theme_id.
        $this->assertTrue($theme->hasLicenseForSite($site->id));
        $this->assertDatabaseHas('theme_licenses', ['id' => $license->id, 'site_id' => $site->id]);
    }

    public function test_ledger_outdated_check_correctly_flags_a_stale_install(): void
    {
        $theme = $this->makeTheme();
        $site = $this->makeCrmTestSite();
        $v1 = $this->releaseService->release($theme, '1.0.0', 'v1.');

        $license = ThemeLicense::grant($site->id, $theme->id);
        $this->assertSame($v1->id, $license->installed_release_id);
        $this->assertFalse($license->isOutdatedByLedger());

        $this->releaseService->release($theme->fresh(), '1.1.0', 'v2.');

        // The license row itself is unchanged by a new master release — it
        // still points at whatever was actually installed at grant time —
        // but the comparison now correctly reports it as behind.
        $this->assertTrue($license->fresh()->isOutdatedByLedger());
        $this->assertTrue($license->fresh()->installedRelease->is($v1));
    }
}
