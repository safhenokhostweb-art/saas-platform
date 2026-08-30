<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\User;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1a — Theme Product Release Ledger Foundation.
 *
 * Proves theme_releases behaves as a real commercial release ledger,
 * independent of the pre-existing, unrelated ThemeRevision draft/checkpoint
 * tool and untouched by this phase (see ThemeBuilderService::saveRevision()).
 */
class ThemeReleaseServiceTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ThemeReleaseService::class);
    }

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'clinic-premium-test-' . uniqid(),
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

    public function test_a_theme_can_create_its_first_release(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'Initial productized release.');

        $this->assertDatabaseHas('theme_releases', [
            'id' => $release->id,
            'theme_id' => $theme->id,
            'version' => '1.0.0',
            'status' => ThemeRelease::STATUS_PUBLISHED,
        ]);

        // The snapshot must capture the full payload, including `pages` —
        // the pre-existing ThemeRevision::saveRevision() snapshot omits pages
        // entirely; the release ledger must not repeat that gap.
        $this->assertSame(
            $theme->pages['home']['blocks'][0]['settings']['title'],
            $release->snapshot['pages']['home']['blocks'][0]['settings']['title']
        );
        $this->assertSame($theme->header_blocks, $release->snapshot['header_blocks']);
        $this->assertSame($theme->global_styles, $release->snapshot['global_styles']);

        $theme->refresh();
        $this->assertSame($release->id, $theme->current_release_id);
    }

    public function test_releases_are_immutable(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'Initial release.');

        $this->expectException(\RuntimeException::class);
        $release->update(['changelog' => 'tampered']);
    }

    public function test_release_content_cannot_be_changed_via_direct_column_write(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'Initial release.');

        $this->expectException(\RuntimeException::class);
        $release->version = '9.9.9';
        $release->save();
    }

    public function test_releases_cannot_be_deleted(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'Initial release.');

        $this->expectException(\RuntimeException::class);
        $release->delete();
    }

    public function test_a_second_release_does_not_overwrite_the_first(): void
    {
        $theme = $this->makeTheme();
        $first = $this->service->release($theme, '1.0.0', 'Initial release.');

        // Mutate the live draft content before the second release, proving
        // the first release's frozen snapshot does not change alongside it.
        $theme->update(['global_styles' => ['primary_color' => '#111111']]);

        $second = $this->service->release($theme, '1.1.0', 'Color update.');

        $this->assertCount(2, $theme->releases()->get());
        $first->refresh();
        $this->assertSame('#0ea5e9', $first->snapshot['global_styles']['primary_color']);
        $this->assertSame('#111111', $second->snapshot['global_styles']['primary_color']);
        $this->assertSame(ThemeRelease::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(ThemeRelease::STATUS_PUBLISHED, $second->fresh()->status);
    }

    public function test_a_release_version_must_be_strictly_greater_than_the_latest(): void
    {
        $theme = $this->makeTheme();
        $this->service->release($theme, '1.1.0', 'First.');

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($theme, '1.0.0', 'Should be rejected — not monotonic.');
    }

    public function test_current_version_pointer_works_correctly(): void
    {
        $theme = $this->makeTheme();

        $r1 = $this->service->release($theme, '1.0.0', 'v1.');
        $this->assertSame($r1->id, $theme->fresh()->current_release_id);

        $r2 = $this->service->release($theme, '2.0.0', 'v2.');
        $this->assertSame($r2->id, $theme->fresh()->current_release_id);
        $this->assertNotSame($r1->id, $theme->fresh()->current_release_id);
    }

    public function test_rollback_repoints_current_without_mutating_release_content_or_draft_columns(): void
    {
        $theme = $this->makeTheme();
        $r1 = $this->service->release($theme, '1.0.0', 'v1.');
        $theme->update(['global_styles' => ['primary_color' => '#222222']]);
        $r2 = $this->service->release($theme, '1.1.0', 'v2.');

        $rolledBack = $this->service->rollback($theme, '1.0.0');

        $this->assertSame($r1->id, $rolledBack->id);
        $theme->refresh();
        $this->assertSame($r1->id, $theme->current_release_id);

        // Rollback is pointer-only: it must not touch the theme's live draft
        // columns, and it must not create a new release row or mutate the
        // content of either existing one.
        $this->assertCount(2, $theme->releases()->get());
        $this->assertSame('#222222', $theme->fresh()->global_styles['primary_color']);
        $this->assertSame('#0ea5e9', $r1->fresh()->snapshot['global_styles']['primary_color']);
        $this->assertSame('#222222', $r2->fresh()->snapshot['global_styles']['primary_color']);

        // Status bookkeeping: rolled-back-to release is PUBLISHED again, the
        // one it replaced is SUPERSEDED — no row is ever deleted.
        $this->assertSame(ThemeRelease::STATUS_PUBLISHED, $r1->fresh()->status);
        $this->assertSame(ThemeRelease::STATUS_SUPERSEDED, $r2->fresh()->status);
    }

    public function test_rollback_to_a_nonexistent_version_is_rejected(): void
    {
        $theme = $this->makeTheme();
        $this->service->release($theme, '1.0.0', 'v1.');

        $this->expectException(InvalidArgumentException::class);
        $this->service->rollback($theme, '9.9.9');
    }

    public function test_releasing_the_master_theme_does_not_affect_a_customer_instance(): void
    {
        $master = $this->makeTheme(['slug' => 'clinic-premium-master-' . uniqid(), 'site_id' => null]);

        $site = $this->makeCrmTestSite();
        $instance = $this->makeTheme([
            'slug' => 'clinic-premium-instance-' . uniqid(),
            'site_id' => $site->id,
            'global_styles' => ['primary_color' => '#customer-color'],
        ]);
        $instanceRelease = $this->service->release($instance, '1.0.0', 'Customer instance initial release.');

        $this->service->release($master, '1.0.0', 'Master release.');
        $this->service->release($master, '1.1.0', 'Master update.');

        $instance->refresh();
        $this->assertSame($instanceRelease->id, $instance->current_release_id);
        $this->assertSame('#customer-color', $instance->global_styles['primary_color']);
        $this->assertCount(1, $instance->releases()->get());
    }

    public function test_release_is_independent_of_the_unrelated_theme_revision_checkpoint_tool(): void
    {
        $theme = $this->makeTheme();
        $builder = $this->app->make(\App\Services\ThemeBuilder\ThemeBuilderService::class);

        // Exercise the pre-existing, unrelated draft-checkpoint mechanism —
        // it must keep working exactly as before and must not interact with
        // theme_releases at all.
        $revision = $builder->saveRevision($theme, 'manual checkpoint');

        $release = $this->service->release($theme, '1.0.0', 'Real commercial release.');

        $this->assertDatabaseHas('theme_revisions', ['id' => $revision->id]);
        $this->assertDatabaseHas('theme_releases', ['id' => $release->id]);
        // Independent version counters by design — ThemeRevision's is a
        // simple per-theme increment unrelated to release semver.
        $this->assertSame(1, $revision->version);
        $this->assertSame('1.0.0', $release->version);
    }

    public function test_an_invalid_semver_version_is_rejected(): void
    {
        $theme = $this->makeTheme();

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($theme, 'not-a-version', 'Bad version.');
    }

    public function test_published_by_records_the_publishing_user(): void
    {
        $theme = $this->makeTheme();
        $user = User::create([
            'mobile' => '09' . random_int(100000000, 999999999),
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $release = $this->service->release($theme, '1.0.0', 'Initial release.', publishedBy: $user->id);

        $this->assertSame($user->id, $release->published_by);
        $this->assertTrue($release->publisher->is($user));
    }
}
