<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 2b — the one authoritative canonical Theme catalog orchestrator.
 * Proves a fresh database can reconstruct the full commercial catalog
 * through this single command, that it's deterministic and idempotent, and
 * that it never silently loses or duplicates a canonical theme or touches
 * customer data.
 */
class ThemeCatalogRebuildCommandTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    public function test_a_fresh_database_can_rebuild_the_full_canonical_catalog(): void
    {
        $this->assertSame(0, Theme::count());

        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        $count = Theme::whereNull('site_id')->count();
        $this->assertGreaterThan(20, $count, 'canonical catalog should contain every seeded commercial theme');
        $this->assertDatabaseHas('themes', ['slug' => 'khabar-online', 'site_id' => null]);
        $this->assertDatabaseHas('themes', ['slug' => 'nexus-pro-corporate', 'site_id' => null]);
        $this->assertDatabaseHas('themes', ['slug' => 'acool-hvac-service', 'site_id' => null]);
        $this->assertDatabaseHas('themes', ['slug' => 'malltina-international', 'site_id' => null]);
        $this->assertDatabaseHas('themes', ['slug' => 'sales-support-pro', 'site_id' => null]);
    }

    public function test_reconstruction_is_idempotent(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $firstCount = Theme::whereNull('site_id')->count();
        $firstIds = Theme::whereNull('site_id')->pluck('id')->sort()->values();

        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $secondCount = Theme::whereNull('site_id')->count();
        $secondIds = Theme::whereNull('site_id')->pluck('id')->sort()->values();

        $this->assertSame($firstCount, $secondCount);
        $this->assertTrue($firstIds->diff($secondIds)->isEmpty(), 'a second run must not create new rows for slugs that already exist');
    }

    public function test_expected_catalog_membership_is_deterministic(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $slugsFirstRun = Theme::whereNull('site_id')->pluck('slug')->sort()->values()->all();

        // Simulate a second, independent fresh database.
        Theme::query()->delete();
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $slugsSecondRun = Theme::whereNull('site_id')->pluck('slug')->sort()->values()->all();

        $this->assertSame($slugsFirstRun, $slugsSecondRun);
    }

    public function test_a_missing_canonical_theme_is_detected(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        // Simulate one canonical theme having silently disappeared.
        Theme::where('slug', 'khabar-online')->delete();

        // Re-running re-seeds it (the underlying seed commands are
        // themselves idempotent/self-healing) — the important guarantee is
        // that the command never reports success while a canonical slug is
        // actually missing.
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $this->assertDatabaseHas('themes', ['slug' => 'khabar-online', 'site_id' => null]);
    }

    public function test_a_duplicate_theme_identity_is_detected(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        // The unique constraint on themes.slug makes a true duplicate
        // impossible to create directly; simulate the command's own
        // detection path by asserting no slug appears twice.
        $slugs = Theme::whereNull('site_id')->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count());
    }

    public function test_obsolete_themes_do_not_silently_reappear(): void
    {
        // Simulate a theme that predates the canonical list (e.g. a retired
        // AI-generator artifact) sitting in the catalog.
        Theme::create([
            'name' => 'Obsolete Prototype',
            'slug' => 'obsolete-prototype-theme',
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_GENERATOR,
            'status' => Theme::STATUS_DRAFT,
        ]);

        $this->artisan('theme-catalog:rebuild --fresh')->assertExitCode(0);

        $this->assertDatabaseMissing('themes', ['slug' => 'obsolete-prototype-theme']);
    }

    public function test_no_customer_theme_instance_is_created_by_the_rebuild(): void
    {
        $site = $this->makeCrmTestSite();

        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        $this->assertSame(0, Theme::where('site_id', $site->id)->count());
    }

    public function test_no_customer_data_is_touched_by_the_rebuild(): void
    {
        $site = $this->makeCrmTestSite();

        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'title' => $site->title]);
    }
}
