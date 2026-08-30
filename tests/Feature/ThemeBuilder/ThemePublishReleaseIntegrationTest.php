<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1b — Activate Theme Commercial Release Flow.
 *
 * Proves the real publish entry point (ThemeBuilderController::publish() ->
 * ThemeBuilderService::publish()) now creates a real ThemeRelease through
 * ThemeReleaseService (the sole writer), while every pre-existing behavior
 * (status bump, ThemeRevision, draft saves, customer instances) keeps
 * working exactly as before. (Phase 2b: the legacy Theme.version int bump
 * this suite used to also assert on was retired.)
 */
class ThemePublishReleaseIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeBuilderService $themeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeService = $this->app->make(ThemeBuilderService::class);
    }

    private function makeDraftTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'clinic-premium-pub-test-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
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

    public function test_publishing_a_theme_creates_a_theme_release(): void
    {
        $theme = $this->makeDraftTheme();

        $this->themeService->publish($theme);

        $theme->refresh();
        $this->assertSame(Theme::STATUS_PUBLISHED, $theme->status);
        $this->assertCount(1, $theme->releases()->get());
        $release = $theme->releases()->first();
        $this->assertSame('1.0.0', $release->version);
        $this->assertSame(ThemeRelease::STATUS_PUBLISHED, $release->status);
        $this->assertSame($release->id, $theme->current_release_id);
        $this->assertSame(
            $theme->pages['home']['blocks'][0]['settings']['title'],
            $release->snapshot['pages']['home']['blocks'][0]['settings']['title']
        );
    }

    public function test_a_second_publish_creates_a_new_release_and_does_not_overwrite_the_first(): void
    {
        $theme = $this->makeDraftTheme();
        $this->themeService->publish($theme);
        $firstRelease = $theme->fresh()->releases()->first();

        $this->themeService->updateTheme($theme, ['global_styles' => ['primary_color' => '#111111']]);
        $this->themeService->publish($theme->fresh());

        $theme->refresh();
        $releases = $theme->releases()->get();
        $this->assertCount(2, $releases);

        $second = $theme->currentRelease()->first();
        $this->assertSame('1.0.1', $second->version);
        $this->assertNotSame($firstRelease->id, $second->id);

        // Old release content is untouched by the second publish.
        $firstRelease->refresh();
        $this->assertSame('#0ea5e9', $firstRelease->snapshot['global_styles']['primary_color']);
        $this->assertSame('#111111', $second->snapshot['global_styles']['primary_color']);
    }

    public function test_old_releases_remain_immutable_after_a_new_publish(): void
    {
        $theme = $this->makeDraftTheme();
        $this->themeService->publish($theme);
        $firstRelease = $theme->fresh()->releases()->first();

        $this->themeService->publish($theme->fresh());

        $this->expectException(RuntimeException::class);
        $firstRelease->update(['changelog' => 'tampering with history']);
    }

    public function test_current_release_pointer_updates_correctly_across_publishes(): void
    {
        $theme = $this->makeDraftTheme();

        $this->themeService->publish($theme);
        $afterFirst = $theme->fresh()->current_release_id;

        $this->themeService->publish($theme->fresh());
        $afterSecond = $theme->fresh()->current_release_id;

        $this->assertNotSame($afterFirst, $afterSecond);
        $this->assertSame($afterSecond, $theme->fresh()->releases()->orderByDesc('id')->first()->id);
    }

    public function test_draft_saves_do_not_create_releases(): void
    {
        $theme = $this->makeDraftTheme();

        $this->themeService->updateTheme($theme, ['global_styles' => ['primary_color' => '#222222']]);
        $this->themeService->updateTheme($theme, ['global_styles' => ['primary_color' => '#333333']]);

        $this->assertCount(0, $theme->releases()->get());
        $this->assertNull($theme->fresh()->current_release_id);
    }

    public function test_theme_revision_checkpoints_remain_independent_of_publish(): void
    {
        $theme = $this->makeDraftTheme();

        $revisionBefore = $this->themeService->saveRevision($theme, 'checkpoint before publish');
        $this->themeService->publish($theme->fresh());
        $revisionAfter = $this->themeService->saveRevision($theme->fresh(), 'checkpoint after publish');

        // Publishing never writes to theme_revisions, and ThemeRevision's own
        // independent version counter is unaffected by how many releases exist.
        $this->assertSame(1, $revisionBefore->version);
        $this->assertSame(2, $revisionAfter->version);
        $this->assertDatabaseCount('theme_revisions', 2);
        $this->assertCount(1, $theme->fresh()->releases()->get());
    }

    public function test_publishing_the_master_theme_does_not_affect_a_customer_instance(): void
    {
        $master = $this->makeDraftTheme(['slug' => 'clinic-premium-pub-master-' . uniqid()]);
        $site = $this->makeCrmTestSite();
        $instance = $this->makeDraftTheme([
            'slug' => 'clinic-premium-pub-instance-' . uniqid(),
            'site_id' => $site->id,
            'status' => Theme::STATUS_PUBLISHED,
            'global_styles' => ['primary_color' => '#customer-color'],
        ]);
        $this->themeService->publish($instance);
        $instanceReleaseId = $instance->fresh()->current_release_id;

        $this->themeService->publish($master);

        $instance->refresh();
        $this->assertSame($instanceReleaseId, $instance->current_release_id);
        $this->assertSame('#customer-color', $instance->global_styles['primary_color']);
        $this->assertCount(1, $instance->releases()->get());
    }

    public function test_publishing_a_theme_with_no_pages_is_rejected_and_leaves_no_partial_state(): void
    {
        $theme = $this->makeDraftTheme(['pages' => []]);
        $originalStatus = $theme->status;

        try {
            $this->themeService->publish($theme);
            $this->fail('Expected publish() to reject a theme with no pages.');
        } catch (RuntimeException $e) {
            // expected
        }

        $theme->refresh();
        $this->assertSame($originalStatus, $theme->status);
        $this->assertNull($theme->current_release_id);
        $this->assertCount(0, $theme->releases()->get());
    }

    public function test_a_rejected_duplicate_version_leaves_no_partial_publish_state(): void
    {
        $theme = $this->makeDraftTheme();
        $this->themeService->publish($theme, '1.0.0');
        $theme->refresh();
        $statusAfterFirstPublish = $theme->status;
        $pointerAfterFirstPublish = $theme->current_release_id;

        try {
            $this->themeService->publish($theme->fresh(), '1.0.0');
            $this->fail('Expected publish() to reject a duplicate version.');
        } catch (InvalidArgumentException $e) {
            // expected
        }

        $theme->refresh();
        // Status/pointer stay exactly as they were after the first,
        // successful publish — the rejected release leaves no partial state.
        $this->assertSame($statusAfterFirstPublish, $theme->status);
        $this->assertSame($pointerAfterFirstPublish, $theme->current_release_id);
        $this->assertCount(1, $theme->releases()->get());
    }
}
