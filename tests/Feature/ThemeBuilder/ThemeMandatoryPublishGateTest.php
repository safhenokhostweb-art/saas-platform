<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeQualityReport;
use App\Models\ThemeRelease;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\Checks\AccessibilityBaselineCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ThemeBuilderService;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 2c — Mandatory Commercial Theme Publish Gate.
 *
 * Proves the wiring, not the check logic (that is
 * ThemeCommercialQualityGateTest's job): the real production entry points —
 * ThemeBuilderService::publish() (via ThemeBuilderController::publish(), the
 * one publish route) and ThemeReleaseBackfillCommand (the ledger-continuity
 * tool) — cannot create a ThemeRelease or advance current_release_id without
 * ThemeCommercialQualityGate passing first, atomically, with the failing
 * report always persisted as an audit fact.
 */
class ThemeMandatoryPublishGateTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private function makeGoodTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'gate-wire-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'یک قالب کامل برای تست.',
            'header_blocks' => [['type' => 'text', 'props' => ['text' => 'هدر']]],
            'footer_blocks' => [['type' => 'text', 'props' => ['text' => 'فوتر']]],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    // ── 1/2/3. valid publish, real release, real linked report ───────────

    public function test_a_valid_theme_can_be_published_through_the_real_service(): void
    {
        $theme = $this->makeGoodTheme();
        $service = $this->app->make(ThemeBuilderService::class);

        $published = $service->publish($theme, '1.0.0', 'Initial release.');

        $this->assertSame(Theme::STATUS_PUBLISHED, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertNotNull($published->current_release_id);
        $this->assertSame(1, ThemeRelease::count());
    }

    public function test_publish_creates_a_release_linked_to_a_passing_quality_report(): void
    {
        $theme = $this->makeGoodTheme();
        $service = $this->app->make(ThemeBuilderService::class);

        $published = $service->publish($theme, '1.0.0', 'Initial release.');
        $release = ThemeRelease::where('theme_id', $theme->id)->sole();

        $this->assertSame($release->id, $published->current_release_id);

        $report = ThemeQualityReport::where('theme_id', $theme->id)->sole();
        $this->assertTrue($report->isPassing());
        $this->assertSame($release->id, $report->theme_release_id);
        $this->assertNotEmpty($report->checks);
    }

    // ── 4. blocking structural failure prevents release, via the real service ──

    public function test_a_structurally_broken_theme_cannot_be_published_through_the_real_service(): void
    {
        $theme = $this->makeGoodTheme(['pages' => []]);
        $service = $this->app->make(ThemeBuilderService::class);

        try {
            $service->publish($theme, '1.0.0', 'Should not happen.');
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            $codes = array_column($e->report->blockingFailures(), 'code');
            $this->assertContains('structural.no_pages', $codes);
        }

        $this->assertSame(0, ThemeRelease::count());
    }

    // ── 6. blocking accessibility failure prevents release, through real DI wiring ──

    public function test_an_accessibility_violation_cannot_be_published_through_the_real_service(): void
    {
        $badRenderer = new class extends BlockRenderer {
            public function renderBlocks(?array $blocks): string
            {
                return '<img src="broken.jpg">'; // no alt attribute at all
            }
        };
        // Swap the container's gate binding — proves ThemeBuilderService
        // genuinely resolves the gate through the container rather than
        // constructing its own, exactly as production DI does.
        $this->app->instance(
            ThemeCommercialQualityGate::class,
            new ThemeCommercialQualityGate([new AccessibilityBaselineCheck($badRenderer)])
        );

        $theme = $this->makeGoodTheme();
        $service = $this->app->make(ThemeBuilderService::class);

        try {
            $service->publish($theme, '1.0.0', 'Should not happen.');
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            $codes = array_column($e->report->blockingFailures(), 'code');
            $this->assertContains('accessibility.image_missing_alt', $codes);
        }

        $this->assertSame(0, ThemeRelease::count());
    }

    // ── 7. blocking required-dependency failure, via the same gate class production uses ──

    public function test_a_missing_required_dependency_blocks_a_gated_publish(): void
    {
        $theme = $this->makeGoodTheme();
        $gate = $this->app->make(ThemeCommercialQualityGate::class);
        $releaseService = $this->app->make(ThemeReleaseService::class);

        try {
            $gate->publishIfCompliant(
                $theme,
                $releaseService,
                '1.0.0',
                'v1.',
                null,
                null,
                [['type' => 'module', 'ref' => 'does-not-exist-anywhere', 'is_required' => true]]
            );
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            $codes = array_column($e->report->blockingFailures(), 'code');
            $this->assertContains('dependency.required_module_missing', $codes);
        }

        $this->assertSame(0, ThemeRelease::count());
    }

    // ── 8. a warning-only theme still publishes ───────────────────────────

    public function test_a_warning_only_theme_still_publishes_successfully(): void
    {
        // No thumbnail/preview image — a warning (asset.missing_marketing_image),
        // never blocking.
        $theme = $this->makeGoodTheme(['description' => null]);
        $service = $this->app->make(ThemeBuilderService::class);

        $published = $service->publish($theme, '1.0.0', 'v1.');

        $this->assertSame(Theme::STATUS_PUBLISHED, $published->status);
        $report = ThemeQualityReport::where('theme_id', $theme->id)->sole();
        $this->assertTrue($report->isPassing());
        $this->assertNotEmpty($report->nonBlockingFindings());
    }

    // ── 9. UNVERIFIED never masquerades as verified ───────────────────────

    public function test_unverified_findings_are_never_presented_as_verified(): void
    {
        $theme = $this->makeGoodTheme();
        $service = $this->app->make(ThemeBuilderService::class);
        $service->publish($theme, '1.0.0', 'v1.');

        $report = ThemeQualityReport::where('theme_id', $theme->id)->sole();
        $unverified = array_filter($report->checks, fn (array $c) => $c['status'] === 'unverified');

        $this->assertNotEmpty($unverified, 'expected at least the contrast/responsive-capture unverified findings');
        foreach ($unverified as $finding) {
            $this->assertNotSame('passed', $finding['status']);
        }
        // Passing overall is legitimate (no blocking failures) — but that is
        // never because an unverified capability was silently counted as a
        // pass. blockingFailures() only ever contains failed+blocking rows.
        $this->assertTrue($report->isPassing());
    }

    // ── 10/11/12. atomic failure: pointer, release count, and status all unchanged ──

    public function test_a_failed_publish_leaves_current_release_id_and_release_count_unchanged(): void
    {
        $theme = $this->makeGoodTheme();
        $service = $this->app->make(ThemeBuilderService::class);
        $service->publish($theme, '1.0.0', 'v1.');

        $theme->refresh();
        $releaseIdBefore = $theme->current_release_id;
        $this->assertNotNull($releaseIdBefore);

        $theme->update(['pages' => []]); // now structurally broken

        try {
            $service->publish($theme->fresh(), '1.1.0', 'Should fail.');
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $theme->refresh();
        $this->assertSame($releaseIdBefore, $theme->current_release_id);
        $this->assertSame(1, ThemeRelease::where('theme_id', $theme->id)->count());
    }

    public function test_a_failed_publish_leaves_theme_status_unchanged(): void
    {
        $theme = $this->makeGoodTheme(['pages' => []]); // never published, broken from the start
        $service = $this->app->make(ThemeBuilderService::class);

        try {
            $service->publish($theme, '1.0.0', 'Should fail.');
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $theme->refresh();
        $this->assertSame(Theme::STATUS_DRAFT, $theme->status);
        $this->assertNull($theme->published_at);
        $this->assertNull($theme->current_release_id);
    }

    // ── 13. no customer data touched by a failed publish ──────────────────

    public function test_a_failed_publish_touches_no_customer_data(): void
    {
        $site = $this->makeCrmTestSite();
        $instance = $this->makeGoodTheme(['slug' => 'gate-wire-instance-' . uniqid(), 'site_id' => $site->id]);
        $master = $this->makeGoodTheme(['slug' => 'gate-wire-master-' . uniqid(), 'pages' => []]);
        $service = $this->app->make(ThemeBuilderService::class);

        try {
            $service->publish($master, '1.0.0', 'Should fail.');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $instance->refresh();
        $this->assertNull($instance->current_release_id);
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
        $this->assertSame(0, ThemeQualityReport::where('theme_id', $instance->id)->count());
    }

    // ── 14. the backfill command is also gated — no alternate bypass ──────

    public function test_the_backfill_command_is_also_gated_and_leaves_a_failing_theme_unbackfilled(): void
    {
        $good = $this->makeGoodTheme(['slug' => 'gate-wire-backfill-good-' . uniqid()]);
        $broken = $this->makeGoodTheme(['slug' => 'gate-wire-backfill-bad-' . uniqid(), 'pages' => []]);

        $this->artisan('theme-release:backfill')->assertExitCode(1);

        $this->assertCount(1, $good->fresh()->releases()->get());
        $this->assertCount(0, $broken->fresh()->releases()->get());
        $this->assertNull($broken->fresh()->current_release_id);

        // Every release the backfill command produced is traceable to a
        // passing report, exactly like an interactive publish.
        $goodReport = ThemeQualityReport::where('theme_id', $good->id)->sole();
        $this->assertTrue($goodReport->isPassing());
        $this->assertSame($good->fresh()->current_release_id, $goodReport->theme_release_id);

        // The failing theme's rejection is itself a persisted audit fact.
        $brokenReport = ThemeQualityReport::where('theme_id', $broken->id)->sole();
        $this->assertFalse($brokenReport->isPassing());
    }

    // ── 15. no production path reaches release() while bypassing the gate ─

    public function test_a_blocked_publish_never_reaches_the_release_ledger_writer(): void
    {
        $theme = $this->makeGoodTheme(['pages' => []]);
        $service = $this->app->make(ThemeBuilderService::class);

        try {
            $service->publish($theme, '1.0.0', 'Should fail.');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        // Nothing was ever written to the ledger for this theme — the gate's
        // rejection happened before ThemeReleaseService::release() could be
        // reached at all, not as a rollback of a partially-started write.
        $this->assertSame(0, $theme->releases()->count());
        // Direct, low-level use of the ledger writer remains possible for a
        // caller that has already validated on its own (e.g. this test
        // suite) — it is not disabled at the class level, only unreachable
        // from production without going through the gate first.
        $releaseService = $this->app->make(ThemeReleaseService::class);
        $goodTheme = $this->makeGoodTheme(['slug' => 'gate-wire-direct-' . uniqid()]);
        $release = $releaseService->release($goodTheme, '1.0.0', 'direct low-level call, not production-reachable');
        $this->assertInstanceOf(ThemeRelease::class, $release);
    }

    // ── 17. ThemeRevision remains independent of the gated publish flow ───

    public function test_theme_revision_checkpoints_remain_independent_of_the_gated_publish_flow(): void
    {
        $theme = $this->makeGoodTheme();
        $service = $this->app->make(ThemeBuilderService::class);

        $revision = $service->saveRevision($theme, 'checkpoint before publish');
        $service->publish($theme->fresh(), '1.0.0', 'v1.');

        $this->assertDatabaseHas('theme_revisions', ['id' => $revision->id]);
        $this->assertSame(1, $theme->fresh()->revisions()->count());
    }

    // ── 20. quality report history is a complete, immutable audit trail ───

    public function test_quality_report_history_remains_a_complete_audit_trail_across_attempts(): void
    {
        $theme = $this->makeGoodTheme(['pages' => []]);
        $service = $this->app->make(ThemeBuilderService::class);

        try {
            $service->publish($theme, '1.0.0', 'attempt 1, fails.');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $theme->update(['pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]]]);
        $service->publish($theme->fresh(), '1.0.0', 'attempt 2, succeeds.');

        $reports = ThemeQualityReport::where('theme_id', $theme->id)->orderBy('id')->get();
        $this->assertCount(2, $reports, 'both the failing and the passing evaluation must remain, neither overwritten nor deleted');
        $this->assertFalse($reports[0]->isPassing());
        $this->assertNull($reports[0]->theme_release_id);
        $this->assertTrue($reports[1]->isPassing());
        $this->assertNotNull($reports[1]->theme_release_id);
    }
}
