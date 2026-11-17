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
