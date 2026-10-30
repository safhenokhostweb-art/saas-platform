<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\ThemeRevision;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1d — Existing Theme Commercial Release Backfill.
 *
 * Proves `theme-release:backfill` is idempotent, scoped to the master
 * catalog only, honest about what it writes (no fabricated history), and
 * fail-safe (one theme's failure never stops the rest).
 */
class ThemeReleaseBackfillCommandTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'backfill-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [['type' => 'header_basic']],
            'footer_blocks' => [['type' => 'footer_basic']],
            'pages' => [
                'home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => [
                    ['type' => 'hero', 'settings' => ['title' => 'خوش آمدید']],
                ]],
            ],
            'global_styles' => ['primary_color' => '#0ea5e9'],
            'settings' => ['supported_modules' => ['forms']],
        ], $overrides));
    }

    public function test_a_zero_release_theme_gets_v1_0_0(): void
    {
        $theme = $this->makeTheme();

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $theme->refresh();
        $this->assertCount(1, $theme->releases()->get());
        $release = $theme->releases()->first();
        $this->assertSame('1.0.0', $release->version);
        $this->assertSame('Initial commercial release', $release->changelog);
        $this->assertSame(
            'Initial commercial release created during Theme Productization migration.',
            $release->release_notes
        );
        $this->assertSame(ThemeRelease::STATUS_PUBLISHED, $release->status);
        $this->assertNull($release->compatibility);
        $this->assertNull($release->dependencies);
        $this->assertNull($release->published_by);
        $this->assertNotNull($release->published_at);
    }

    public function test_current_release_id_is_set_correctly_after_backfill(): void
    {
        $theme = $this->makeTheme();

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $theme->refresh();
        $release = $theme->releases()->first();
        $this->assertSame($release->id, $theme->current_release_id);
    }

    public function test_initial_release_snapshot_matches_the_current_master_theme(): void
    {
        $theme = $this->makeTheme([
            'header_blocks' => [['type' => 'header_special']],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
        ]);

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $release = $theme->fresh()->releases()->first();
        $this->assertSame($theme->header_blocks, $release->snapshot['header_blocks']);
        $this->assertEquals($theme->pages, $release->snapshot['pages']);
        $this->assertEquals($theme->global_styles, $release->snapshot['global_styles']);
        $this->assertEquals($theme->settings, $release->snapshot['settings']);
    }

    public function test_a_second_backfill_run_is_a_no_op(): void
    {
        $theme = $this->makeTheme();

        $this->artisan('theme-release:backfill')->assertExitCode(0);
        $firstRelease = $theme->fresh()->releases()->first();

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $theme->refresh();
        $this->assertCount(1, $theme->releases()->get());
        $this->assertSame($firstRelease->id, $theme->releases()->first()->id);
        $this->assertSame($firstRelease->id, $theme->current_release_id);
    }

    public function test_existing_release_history_is_never_overwritten_by_backfill(): void
    {
        $theme = $this->makeTheme();
        $service = $this->app->make(ThemeReleaseService::class);
        // Theme already went through a real publish before backfill ever ran.
        $existing = $service->release($theme, '2.3.1', 'A real, already-published release.');

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $theme->refresh();
        $this->assertCount(1, $theme->releases()->get());
        $this->assertSame($existing->id, $theme->releases()->first()->id);
        $this->assertSame('2.3.1', $theme->releases()->first()->version);
        $this->assertSame($existing->id, $theme->current_release_id);
    }

    public function test_theme_revision_history_is_untouched_by_backfill(): void
    {
        $theme = $this->makeTheme();
        $theme->revisions()->create([
            'version' => 1,
            'label' => 'manual checkpoint before backfill',
            'snapshot' => ['header_blocks' => [], 'footer_blocks' => [], 'global_styles' => [], 'settings' => []],
        ]);

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $this->assertDatabaseCount('theme_revisions', 1);
        $this->assertSame(1, $theme->fresh()->revisions()->first()->version);
    }

    public function test_customer_instance_themes_are_never_touched_by_backfill(): void
    {
        $site = $this->makeCrmTestSite();
        $instance = $this->makeTheme([
            'slug' => 'backfill-customer-instance-' . uniqid(),
            'site_id' => $site->id,
        ]);
        $master = $this->makeTheme(['slug' => 'backfill-master-' . uniqid()]);

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $instance->refresh();
        $this->assertNull($instance->current_release_id);
        $this->assertCount(0, $instance->releases()->get());

        // The master theme in the same run is still processed normally.
        $master->refresh();
        $this->assertCount(1, $master->releases()->get());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $theme = $this->makeTheme();

        $this->artisan('theme-release:backfill --dry-run')->assertExitCode(0);

        $theme->refresh();
        $this->assertCount(0, $theme->releases()->get());
        $this->assertNull($theme->current_release_id);
    }

    public function test_a_failing_theme_does_not_stop_the_rest_of_the_batch(): void
    {
        $good1 = $this->makeTheme(['slug' => 'backfill-good-1-' . uniqid()]);
        $badNoPages = $this->makeTheme(['slug' => 'backfill-bad-' . uniqid(), 'pages' => []]);
        $good2 = $this->makeTheme(['slug' => 'backfill-good-2-' . uniqid()]);

        $this->artisan('theme-release:backfill')->assertExitCode(1);

        $this->assertCount(1, $good1->fresh()->releases()->get());
        $this->assertCount(1, $good2->fresh()->releases()->get());
        $this->assertCount(0, $badNoPages->fresh()->releases()->get());
        $this->assertNull($badNoPages->fresh()->current_release_id);
    }

    public function test_all_catalog_themes_can_be_processed_in_one_run(): void
    {
        $themes = collect(range(1, 12))->map(
            fn ($i) => $this->makeTheme(['slug' => "backfill-catalog-{$i}-" . uniqid()])
        );

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        foreach ($themes as $theme) {
            $theme->refresh();
            $this->assertCount(1, $theme->releases()->get());
            $this->assertSame('1.0.0', $theme->releases()->first()->version);
            $this->assertSame($theme->releases()->first()->id, $theme->current_release_id);
        }
    }

    public function test_slug_targets_exact_master_theme_only(): void
    {
        $target = $this->makeTheme(['slug' => 'ariana-multiplus']);
        $other = $this->makeTheme(['slug' => 'other-theme-' . uniqid()]);

        $this->artisan('theme-release:backfill --slug=ariana-multiplus')->assertExitCode(0);

        $target->refresh();
        $this->assertCount(1, $target->releases()->get());
        $this->assertSame('1.0.0', $target->releases()->first()->version);

        $other->refresh();
        $this->assertCount(0, $other->releases()->get());
    }

    public function test_missing_slug_fails_safely(): void
    {
        $this->artisan('theme-release:backfill --slug=nonexistent-slug')->assertExitCode(1);
    }

    public function test_slug_excludes_customer_owned_theme_even_with_matching_slug(): void
    {
        $site = $this->makeCrmTestSite();
        $customerInstance = $this->makeTheme([
            'slug' => 'ariana-multiplus',
            'site_id' => $site->id,
        ]);

        $this->artisan('theme-release:backfill --slug=ariana-multiplus')->assertExitCode(1);

        $customerInstance->refresh();
        $this->assertNull($customerInstance->current_release_id);
        $this->assertCount(0, $customerInstance->releases()->get());
    }

    public function test_slug_excludes_theme_with_existing_release(): void
    {
        $service = $this->app->make(ThemeReleaseService::class);
        $theme = $this->makeTheme(['slug' => 'ariana-multiplus']);
        $service->release($theme, '2.0.0', 'Already published');

        $this->artisan('theme-release:backfill --slug=ariana-multiplus')->assertExitCode(1);

        $theme->refresh();
        $this->assertCount(1, $theme->releases()->get());
        $this->assertSame('2.0.0', $theme->releases()->first()->version);
    }

    public function test_dry_run_with_slug_writes_nothing(): void
    {
        $theme = $this->makeTheme(['slug' => 'ariana-multiplus']);

        $this->artisan('theme-release:backfill --slug=ariana-multiplus --dry-run')->assertExitCode(0);

        $theme->refresh();
        $this->assertCount(0, $theme->releases()->get());
        $this->assertNull($theme->current_release_id);
    }

    public function test_backward_compatibility_without_slug_preserves_existing_behavior(): void
    {
        $theme1 = $this->makeTheme(['slug' => 'theme-one-' . uniqid()]);
        $theme2 = $this->makeTheme(['slug' => 'theme-two-' . uniqid()]);

        $this->artisan('theme-release:backfill')->assertExitCode(0);

        $theme1->refresh();
        $theme2->refresh();
        $this->assertCount(1, $theme1->releases()->get());
        $this->assertCount(1, $theme2->releases()->get());
    }

    public function test_slug_invokes_quality_gate_only_for_targeted_theme(): void
    {
        $target = $this->makeTheme(['slug' => 'ariana-multiplus']);
        $failing = $this->makeTheme(['slug' => 'failing-theme-' . uniqid(), 'pages' => []]);

        // Without --slug, the batch would fail due to failing-theme
        // With --slug=ariana-multiplus, only the target is processed and should succeed
        $this->artisan('theme-release:backfill --slug=ariana-multiplus')->assertExitCode(0);

        $target->refresh();
        $this->assertCount(1, $target->releases()->get());

        $failing->refresh();
        $this->assertCount(0, $failing->releases()->get());
    }
}
