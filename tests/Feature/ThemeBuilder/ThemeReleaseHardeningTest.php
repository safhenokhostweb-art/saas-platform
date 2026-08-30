<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 1c — Theme Release Hardening & Production Readiness Review.
 *
 * Covers gaps found during the hardening audit that Phase 1a/1b's tests did
 * not: ledger permanence against a real hard delete of the master Theme row,
 * restoring a release's content back into the live draft, full snapshot
 * field coverage, and the documented (not silently assumed) boundary of the
 * immutability guard.
 */
class ThemeReleaseHardeningTest extends TestCase
{
    use RefreshDatabase;

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
            'slug' => 'clinic-premium-hardening-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [['type' => 'header_basic']],
            'footer_blocks' => [['type' => 'footer_basic', 'settings' => ['copyright' => '© تست']]],
            'pages' => [
                'home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => [
                    ['type' => 'hero', 'settings' => ['title' => 'خوش آمدید']],
                ]],
            ],
            'global_styles' => ['primary_color' => '#0ea5e9'],
            'settings' => ['supported_modules' => ['forms'], 'meta' => ['note' => 'test-settings']],
        ], $overrides));
    }

    public function test_a_theme_with_real_releases_cannot_be_hard_deleted(): void
    {
        $theme = $this->makeTheme();
        $this->service->release($theme, '1.0.0', 'Initial release.');

        $this->expectException(QueryException::class);
        $theme->delete();
    }

    public function test_a_theme_with_no_releases_can_still_be_deleted(): void
    {
        $theme = $this->makeTheme();

        $theme->delete();

        $this->assertDatabaseMissing('themes', ['id' => $theme->id]);
    }

    public function test_snapshot_captures_every_content_field_not_just_the_ones_checked_before(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'Full snapshot check.');

        $this->assertSame($theme->footer_blocks, $release->snapshot['footer_blocks']);
        // assertEquals, not assertSame: MySQL's JSON column type does not
        // guarantee preserving associative-key insertion order on read-back,
        // so a strict === comparison on the whole nested array is comparing
        // an incidental storage artifact, not the data this test cares about.
        $this->assertEquals($theme->settings, $release->snapshot['settings']);
        $this->assertSame(
            'test-settings',
            $release->snapshot['settings']['meta']['note']
        );
    }

    public function test_restore_to_draft_copies_release_content_back_into_the_live_theme_without_moving_the_pointer(): void
    {
        $theme = $this->makeTheme();
        $v1 = $this->service->release($theme, '1.0.0', 'v1.');

        $theme->update(['global_styles' => ['primary_color' => '#changed'], 'footer_blocks' => []]);
        $v2 = $this->service->release($theme, '1.1.0', 'v2.');

        $restored = $this->service->restoreToDraft($v1);

        $this->assertSame('#0ea5e9', $restored->global_styles['primary_color']);
        $this->assertNotEmpty($restored->footer_blocks);

        // restoreToDraft must not touch which release is current, nor create
        // or mutate any release row — it is draft-side only.
        $this->assertSame($v2->id, $restored->current_release_id);
        $this->assertCount(2, $restored->releases()->get());
        $this->assertSame('#changed', $v2->fresh()->snapshot['global_styles']['primary_color']);
        $this->assertSame(ThemeRelease::STATUS_PUBLISHED, $v2->fresh()->status);
    }

    public function test_restore_to_draft_does_not_require_the_restored_release_to_be_current(): void
    {
        $theme = $this->makeTheme();
        $v1 = $this->service->release($theme, '1.0.0', 'v1.');
        $this->service->release($theme->fresh(), '1.1.0', 'v2.');
        $this->service->release($theme->fresh(), '1.2.0', 'v3.');

        // Restoring an old, non-current, already-superseded release's content
        // into draft must still work — it's a content operation, not a
        // "which release is authoritative" operation.
        $restored = $this->service->restoreToDraft($v1->fresh());

        $this->assertSame($theme->pages['home']['blocks'][0]['settings']['title'] ?? null, $restored->pages['home']['blocks'][0]['settings']['title']);
        $this->assertCount(3, $restored->releases()->get());
    }

    public function test_immutability_guard_boundary_is_explicit_bulk_writes_bypass_it(): void
    {
        // Documents the real, known boundary of the event-based guard (see
        // ThemeRelease::booted() docblock): a bulk query-builder write never
        // instantiates a model, so no `updating` event fires. This is not a
        // bug to fix here — it is why the rule "never write to theme_releases
        // via anything but ThemeReleaseService" must hold by convention, and
        // this test exists so that boundary is a proven fact, not folklore.
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'Original changelog.');

        ThemeRelease::where('id', $release->id)->update(['changelog' => 'bypassed the guard']);

        $this->assertDatabaseHas('theme_releases', ['id' => $release->id, 'changelog' => 'bypassed the guard']);
    }

    public function test_customer_instance_releases_are_isolated_from_master_hard_delete_protection(): void
    {
        $master = $this->makeTheme(['slug' => 'clinic-premium-hardening-master-' . uniqid()]);
        $this->service->release($master, '1.0.0', 'Master release.');

        // A completely unrelated, never-released draft theme must remain
        // freely deletable — the restrictOnDelete change must not become an
        // accidental blanket "themes can never be deleted" regression.
        $unrelatedDraft = $this->makeTheme(['slug' => 'unrelated-draft-' . uniqid(), 'status' => Theme::STATUS_DRAFT]);
        $unrelatedDraft->delete();

        $this->assertDatabaseMissing('themes', ['id' => $unrelatedDraft->id]);
        $this->assertDatabaseHas('themes', ['id' => $master->id]);
    }

    public function test_published_at_is_recorded_and_frozen(): void
    {
        $theme = $this->makeTheme();

        $before = now()->subSecond();
        $release = $this->service->release($theme, '1.0.0', 'Initial release.');
        $after = now()->addSecond();

        $this->assertNotNull($release->published_at);
        $this->assertTrue($release->published_at->between($before, $after));

        $this->expectException(RuntimeException::class);
        $release->update(['published_at' => now()->addDay()]);
    }

    // Phase 2b: the legacy "rollback never mutates the legacy Theme.version
    // pointer" test that used to live here was removed along with the
    // column itself — there is no longer any legacy version field for
    // rollback() to leave untouched.

    public function test_readiness_gate_is_enforced_by_the_release_ledger_itself_not_only_the_publish_flow(): void
    {
        // Calling ThemeReleaseService::release() directly (bypassing
        // ThemeBuilderService::publish() entirely) must still be blocked for
        // a theme with no authored pages — the gate lives in the sole writer,
        // not in one caller of it.
        $theme = $this->makeTheme(['pages' => []]);

        $this->expectException(RuntimeException::class);
        $this->service->release($theme, '1.0.0', 'Should be rejected — not release-ready.');
    }

    public function test_compatibility_shape_is_validated(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', compatibility: ['platform' => '>=1.0.0']);
        $this->assertSame(['platform' => '>=1.0.0'], $release->compatibility);

        $this->expectException(InvalidArgumentException::class);
        $this->service->release(
            $this->makeTheme(),
            '1.0.0',
            'Malformed compatibility.',
            compatibility: ['platform' => 123]
        );
    }

    public function test_dependencies_shape_is_validated(): void
    {
        $theme = $this->makeTheme();
        $dependencies = [
            ['type' => 'motion_asset', 'ref' => 'medical-hero-intro', 'version_constraint' => null, 'is_required' => false],
        ];

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: $dependencies);
        // assertEquals, not assertSame: MySQL JSON columns don't preserve
        // associative-key order on read-back (see the snapshot test above).
        $this->assertEquals($dependencies, $release->dependencies);

        $this->expectException(InvalidArgumentException::class);
        $this->service->release(
            $this->makeTheme(),
            '1.0.0',
            'Malformed dependency — missing required key.',
            dependencies: [['type' => 'module', 'ref' => 'forms']] // missing is_required
        );
    }

    // Phase 2b: the legacy Theme.version divergence test that used to live
    // here was removed along with the column itself — there is no longer
    // any legacy version field that could diverge from the ledger.

    public function test_dependency_type_is_not_restricted_to_a_fixed_whitelist(): void
    {
        // The dependency_type enum is deliberately open (per
        // THEME_PRODUCTIZATION_ARCHITECTURE.md) so a future product class
        // (e.g. a design system) needs no code change here to be referenced.
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'design_system', 'ref' => 'some-future-product', 'is_required' => false],
        ]);

        $this->assertSame('design_system', $release->dependencies[0]['type']);
    }
}
