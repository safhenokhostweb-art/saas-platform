<?php

namespace Tests\Feature\Onboarding;

use App\Models\Theme;
use App\Services\Onboarding\SiteOnboardingService;
use Database\Seeders\SolarIndustryThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production starts with an empty master catalog (clean-slate purge,
 * 2026-11-01), so `theme-release:backfill` alone has nothing to release and
 * the solar profile showed only «بدون قالب». The supported path is a
 * single-theme install followed by the publish gate. A full catalog rebuild
 * would bring back the purged legacy themes.
 */
class ProfileThemeInstallTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = SolarIndustryThemeSeeder::SLUG;

    private function solarProfile(): array
    {
        return app(SiteOnboardingService::class)->wizardOptions()['profiles']['solar_energy'];
    }

    public function test_empty_catalog_reports_not_installed_with_the_fix_commands(): void
    {
        $this->assertSame(0, Theme::whereNull('site_id')->count());

        $profile = $this->solarProfile();

        $this->assertSame('unavailable', $profile['theme_status']);
        $this->assertSame('not_installed', $profile['theme_reason']);
        $this->assertSame([
            'php artisan theme-catalog:rebuild --slug=' . self::SLUG,
            'php artisan theme-release:backfill --slug=' . self::SLUG,
        ], $profile['theme_fix']);
    }

    public function test_single_theme_install_then_publish_gate_makes_the_profile_theme_available(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])->assertSuccessful();

        // Only the requested theme: the purged legacy catalog is not resurrected.
        $this->assertSame([self::SLUG], Theme::whereNull('site_id')->pluck('slug')->all());

        $profile = $this->solarProfile();
        $this->assertSame('unavailable', $profile['theme_status']);
        $this->assertSame('no_current_release', $profile['theme_reason']);
        $this->assertSame(['php artisan theme-release:backfill --slug=' . self::SLUG], $profile['theme_fix']);

        $this->artisan('theme-release:backfill', ['--slug' => self::SLUG])->assertSuccessful();

        $theme = Theme::whereNull('site_id')->where('slug', self::SLUG)->firstOrFail();
        $this->assertTrue($theme->fresh()->isCommerciallyEligible(), json_encode($theme->fresh()->commercialIneligibilityReasons()));

        $profile = $this->solarProfile();
        $this->assertSame('available', $profile['theme_status']);
        $this->assertSame($theme->id, $profile['theme_id']);
        $this->assertNull($profile['theme_reason']);
        $this->assertContains(self::SLUG, array_column(app(SiteOnboardingService::class)->wizardOptions()['themes'], 'slug'));
    }

    public function test_install_is_idempotent(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])->assertSuccessful();
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])->assertSuccessful();

        $this->assertSame(1, Theme::whereNull('site_id')->where('slug', self::SLUG)->count());
    }

    /** Owner rule 2026-11-17: a single-theme install never lists it in the tenant marketplace. */
    public function test_a_single_theme_install_is_unlisted_and_a_reinstall_keeps_the_admins_listing(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])
            ->expectsOutputToContain('is not listed in the tenant marketplace')
            ->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', self::SLUG)->firstOrFail();
        $this->assertFalse((bool) $theme->is_marketplace);

        $theme->update(['is_marketplace' => true, 'marketplace_price' => 2_500_000]);
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])->assertSuccessful();

        $theme->refresh();
        $this->assertTrue((bool) $theme->is_marketplace);
        $this->assertSame(2_500_000, $theme->marketplace_price);
    }

    /**
     * 2026-11-17 production state: theme 61 holds the 5 pages of 1.0.0 and
     * release 1.0.0 with the same 5 pages. The 1.1.0 rollout reseeds the
     * existing theme in place (no delete) and publishes 1.1.0 through the
     * Mandatory Publish Gate, exactly the steps the admin runs after deploy.
     */
    public function test_an_installed_five_page_theme_is_updated_in_place_to_the_full_24_page_release(): void
    {
        // Recreate production: theme with the 1.0.0 content (5 pages), then release 1.0.0 cut from it.
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', self::SLUG)->firstOrFail();
        $theme->update(['pages' => array_intersect_key($theme->pages, array_flip(['home', 'about', 'products', 'courses', 'contact']))]);
        $this->artisan('theme-release:backfill', ['--slug' => self::SLUG])->assertSuccessful();
        $theme->refresh();
        $themeId = $theme->id;
        $this->assertCount(5, $theme->getPageList());
        $this->assertSame('1.0.0', $theme->currentRelease->version);
        $this->assertCount(5, $theme->currentRelease->snapshot['pages']);
        $this->assertSame($themeId, $this->solarProfile()['theme_id']);

        // Step 1 (after deploy): reseed the existing theme in place.
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG])->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', self::SLUG)->firstOrFail();
        $this->assertSame($themeId, $theme->id, 'same theme row, nothing deleted');
        $this->assertCount(24, $theme->getPageList(), 'the wizard card now counts 24');
        $this->assertSame('1.0.0', $theme->currentRelease->version, 'installs keep 1.0.0 until it is published');

        // Step 2: publish 1.1.0 (Theme Builder «انتشار» does the same call).
        app(\App\Services\ThemeBuilder\ThemeBuilderService::class)->publish($theme, '1.1.0', 'Solar 1.1.0: all 24 pages');

        $theme->refresh();
        $this->assertTrue($theme->isCommerciallyEligible(), json_encode($theme->commercialIneligibilityReasons()));
        $this->assertSame('1.1.0', $theme->currentRelease->version);
        $this->assertCount(24, $theme->currentRelease->snapshot['pages'], 'new installs and upgrades get all 24 pages');
        $this->assertSame($themeId, $this->solarProfile()['theme_id']);
    }

    public function test_unknown_slug_and_fresh_with_slug_are_refused(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => 'no-such-theme'])->assertFailed();
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SLUG, '--fresh' => true])->assertFailed();

        $this->assertSame(0, Theme::whereNull('site_id')->count());
    }

    public function test_wizard_page_renders_the_reason_and_commands(): void
    {
        $admin = \App\Models\User::create(['mobile' => '09120000009', 'password' => bcrypt('x'), 'is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.onboarding.create'))
            ->assertOk()
            ->assertSee('not_installed', false)
            ->assertSee('theme-catalog:rebuild --slug=' . self::SLUG, false)
            ->assertSee('themeReasonLabel', false);
    }
}
