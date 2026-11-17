<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Setting;
use App\Models\Theme;
use App\Services\Onboarding\SiteOnboardingService;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeCatalog\ThemeRegistry;
use App\Services\ThemeCatalog\ThemeRegistrySync;
use Database\Seeders\SolarIndustryThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Post-deploy `themes:sync-registry`: every active registry theme ends up
 * installed and released with no manual command, and nothing purged or
 * deleted is ever brought back.
 */
class ThemeRegistrySyncTest extends TestCase
{
    use RefreshDatabase;

    private const SOLAR = SolarIndustryThemeSeeder::SLUG;

    /**
     * Removes a master theme row the way an admin purge leaves it: gone.
     * The real removal goes through the clean-slate purger, which also deletes
     * its releases. The sync only looks at whether the theme row exists.
     */
    private function removeMaster(string $slug): void
    {
        Schema::withoutForeignKeyConstraints(
            fn () => DB::table('themes')->whereNull('site_id')->where('slug', $slug)->delete()
        );
    }

    private function masterSlugs(): array
    {
        return Theme::whereNull('site_id')->orderBy('slug')->pluck('slug')->all();
    }

    public function test_the_active_registry_is_solar_only_and_never_the_purged_legacy_catalog(): void
    {
        $this->assertSame([self::SOLAR], ThemeRegistry::autoSyncSlugs());

        foreach (ThemeRegistry::SOURCES as $source) {
            if (count($source['expected_slugs']) > 1) {
                $this->assertFalse($source['auto_sync'], "{$source['label']} seeds several themes and must not auto-sync");
            }
        }
        $this->assertFalse(ThemeRegistry::sourceFor('khabar-online')['auto_sync']);
    }

    public function test_empty_catalog_is_installed_released_and_offered_in_the_wizard(): void
    {
        $this->artisan('themes:sync-registry')->assertSuccessful();

        $this->assertSame([self::SOLAR], $this->masterSlugs());
        $theme = Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail();
        $this->assertTrue($theme->isCommerciallyEligible(), json_encode($theme->commercialIneligibilityReasons()));
        $this->assertSame('1.0.0', $theme->currentRelease->version);
        $this->assertSame([self::SOLAR], app(ThemeRegistrySync::class)->tracked());

        $profile = app(SiteOnboardingService::class)->wizardOptions()['profiles']['solar_energy'];
        $this->assertSame('available', $profile['theme_status']);
        $this->assertSame($theme->id, $profile['theme_id']);
    }

    /**
     * Owner rule 2026-11-17: no master theme reaches the tenant marketplace
     * by default. solar-industry had been seeded listed at price 0, and
     * safhenokhost.ir took it free from «قالب‌ها».
     */
    public function test_a_newly_synced_theme_is_not_listed_in_the_tenant_marketplace(): void
    {
        $this->artisan('themes:sync-registry')->assertSuccessful();

        $theme = Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail();
        $this->assertFalse((bool) $theme->is_marketplace);
        // GET /api/theme-marketplace lists exactly Theme::marketplace() ∩ eligible.
        $this->assertSame([], Theme::marketplace()->get()->filter->isCommerciallyEligible()->pluck('slug')->all());
        // …while the onboarding wizard still offers it to new solar sites.
        $this->assertContains(self::SOLAR, app(SiteOnboardingService::class)->eligibleThemes()->pluck('slug')->all());
    }

    public function test_a_later_sync_keeps_the_admins_listing_and_price(): void
    {
        $this->artisan('themes:sync-registry')->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail();
        $theme->update(['is_marketplace' => true, 'marketplace_price' => 4_900_000]); // «فروشگاه قالب ← Listing»

        app(ThemeRegistrySync::class)->sync();

        $theme->refresh();
        $this->assertTrue((bool) $theme->is_marketplace);
        $this->assertSame(4_900_000, $theme->marketplace_price);
        $this->assertSame([self::SOLAR], Theme::marketplace()->pluck('slug')->all());
    }

    public function test_install_defaults_unlist_whatever_a_source_seeded(): void
    {
        $this->assertFalse(ThemeRegistry::LIST_IN_MARKETPLACE_ON_INSTALL);
        $theme = Theme::create(['name' => 'Future', 'slug' => 'future-theme', 'status' => Theme::STATUS_PUBLISHED, 'is_marketplace' => true, 'marketplace_price' => 0]);

        ThemeRegistry::applyInstallDefaults($theme);

        $this->assertFalse((bool) $theme->fresh()->is_marketplace);
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->artisan('themes:sync-registry')->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail();
        $releases = $theme->releases()->count();
        $updatedAt = $theme->updated_at;

        $results = app(ThemeRegistrySync::class)->sync();

        $this->assertSame(ThemeRegistrySync::UP_TO_DATE, $results[0]['action']);
        $this->assertSame($releases, $theme->releases()->count());
        $this->assertEquals($updatedAt, $theme->fresh()->updated_at);
    }

    public function test_a_synced_theme_that_was_deleted_is_not_recreated(): void
    {
        $this->artisan('themes:sync-registry')->assertSuccessful();
        $this->removeMaster(self::SOLAR);

        $results = app(ThemeRegistrySync::class)->sync();

        $this->assertSame(ThemeRegistrySync::KEPT_REMOVED, $results[0]['action']);
        $this->assertSame([], $this->masterSlugs());
    }

    public function test_a_manually_installed_theme_is_adopted_without_being_reseeded(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SOLAR])->assertSuccessful();
        $this->artisan('theme-release:backfill', ['--slug' => self::SOLAR])->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail();
        $theme->update(['name' => 'ویرایش‌شده توسط ادمین']);

        $results = app(ThemeRegistrySync::class)->sync();

        $this->assertSame(ThemeRegistrySync::UP_TO_DATE, $results[0]['action']);
        $this->assertSame('ویرایش‌شده توسط ادمین', $theme->fresh()->name);
        $this->assertSame([self::SOLAR], app(ThemeRegistrySync::class)->tracked());

        // Adopted: deleting it now is respected as well.
        $this->removeMaster(self::SOLAR);
        $this->assertSame(ThemeRegistrySync::KEPT_REMOVED, app(ThemeRegistrySync::class)->sync()[0]['action']);
    }

    public function test_an_installed_theme_without_a_release_is_released(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => self::SOLAR])->assertSuccessful();

        $results = app(ThemeRegistrySync::class)->sync();

        $this->assertSame(ThemeRegistrySync::RELEASED, $results[0]['action']);
        $this->assertTrue(Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail()->isCommerciallyEligible());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('themes:sync-registry', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame([], $this->masterSlugs());
        $this->assertNull(Setting::where('key', ThemeRegistrySync::TRACKING_KEY)->first());
    }

    public function test_a_publish_gate_failure_is_reported_without_failing_the_deploy_unless_strict(): void
    {
        $this->mock(ThemeCommercialQualityGate::class)
            ->shouldReceive('publishIfCompliant')
            ->andThrow(new RuntimeException('gate unavailable'));

        $this->artisan('themes:sync-registry')->assertSuccessful();
        $results = app(ThemeRegistrySync::class)->sync();
        $this->assertSame(ThemeRegistrySync::FAILED, $results[0]['action']);

        $this->artisan('themes:sync-registry', ['--strict' => true])->assertFailed();
        $this->assertFalse(Theme::whereNull('site_id')->where('slug', self::SOLAR)->firstOrFail()->isCommerciallyEligible());
    }
}
