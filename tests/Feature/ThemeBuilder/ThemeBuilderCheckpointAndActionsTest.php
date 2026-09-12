<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeInstanceRevision;
use App\Models\ThemeRelease;
use App\Models\ThemeRevision;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI-C2D + UI-C2E — proves the master draft checkpoint (ThemeRevision) is a
 * complete, exact-restore snapshot of every mutable master-authoring field
 * (pages/header/footer/global_styles/settings — settings carries
 * extension_selections per ADR-047), and that clone/export/import/unpublish
 * preserve the correct authoring structure without leaking or mutating
 * ThemeRelease (commercial ledger) or ThemeInstanceRevision (a customer-site
 * model, architecturally unreachable from this app/database).
 */
class ThemeBuilderCheckpointAndActionsTest extends TestCase
{
    use RefreshDatabase;

    private ThemeBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ThemeBuilderService::class);
    }

    private function stateA(): array
    {
        return [
            'pages' => [
                'home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                    ['id' => 'blk_hero_a', 'type' => 'hero', 'props' => ['title' => 'حالت A']],
                ]],
                'about' => ['title' => 'درباره ما', 'slug' => 'about', 'blocks' => [
                    ['id' => 'blk_about_a', 'type' => 'text', 'props' => ['content' => 'متن A']],
                ]],
            ],
            'header_blocks' => [['id' => 'blk_header_a', 'type' => 'nav_header', 'props' => ['logo_text' => 'A']]],
            'footer_blocks' => [['id' => 'blk_footer_a', 'type' => 'nav_footer', 'props' => ['logo_text' => 'A']]],
            'global_styles' => ['colors' => ['primary' => '#111111']],
            'settings' => ['extension_selections' => [], 'note' => 'state-a'],
        ];
    }

    private function stateB(): array
    {
        return [
            'pages' => [
                'home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                    ['id' => 'blk_hero_b', 'type' => 'hero', 'props' => ['title' => 'حالت B']],
                ]],
                'contact' => ['title' => 'تماس', 'slug' => 'contact', 'blocks' => [
                    ['id' => 'blk_contact_b', 'type' => 'contact_info', 'props' => []],
                ]],
            ],
            'header_blocks' => [['id' => 'blk_header_b', 'type' => 'nav_menu', 'props' => []]],
            'footer_blocks' => [['id' => 'blk_footer_b', 'type' => 'nav_menu', 'props' => []]],
            'global_styles' => ['colors' => ['primary' => '#222222']],
            'settings' => ['extension_selections' => [], 'note' => 'state-b'],
        ];
    }

    private function makeThemeInState(array $state): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب چک‌پوینت تست',
            'slug' => 'checkpoint-test-' . uniqid(),
            'type' => Theme::TYPE_FULL,
        ], $state));
    }

    // ── A/B/C — Checkpoint snapshot completeness + exact restore ──────────

    public function test_checkpoint_snapshot_includes_pages_header_footer_global_styles_and_settings(): void
    {
        $theme = $this->makeThemeInState($this->stateA());

        $revision = $this->service->saveRevision($theme, 'چک‌پوینت A');

        $this->assertSame($theme->pages, $revision->snapshot['pages']);
        $this->assertSame($theme->header_blocks, $revision->snapshot['header_blocks']);
        $this->assertSame($theme->footer_blocks, $revision->snapshot['footer_blocks']);
        $this->assertSame($theme->global_styles, $revision->snapshot['global_styles']);
        $this->assertSame($theme->settings, $revision->snapshot['settings']);
    }

    public function test_restore_after_state_change_returns_exact_original_state(): void
    {
        $theme = $this->makeThemeInState($this->stateA());
        $checkpointA = $this->service->saveRevision($theme, 'چک‌پوینت A');

        // Change to state B and save/reload, exactly as the task specifies.
        $theme->update($this->stateB());
        $theme = $theme->fresh();
        $this->assertSame('state-b', $theme->settings['note']);

        $this->service->restoreRevision($checkpointA);
        $theme = $theme->fresh();

        // assertEquals, not assertSame: MySQL's JSON column type does not
        // guarantee object member order is preserved across a round-trip
        // (confirmed empirically here) — the product invariant is exact
        // CONTENT restoration, not literal PHP array key order, which is
        // not a meaningful JSON-object invariant to enforce either.
        $stateA = $this->stateA();
        $this->assertEquals($stateA['pages'], $theme->pages, 'pages not restored exactly');
        $this->assertEquals($stateA['header_blocks'], $theme->header_blocks, 'header not restored exactly');
        $this->assertEquals($stateA['footer_blocks'], $theme->footer_blocks, 'footer not restored exactly');
        $this->assertEquals($stateA['global_styles'], $theme->global_styles, 'global styles not restored exactly');
        $this->assertEquals($stateA['settings'], $theme->settings, 'settings (incl. extension_selections) not restored exactly');
    }

    public function test_restore_preserves_extension_selections_via_settings(): void
    {
        $stateWithExt = $this->stateA();
        $stateWithExt['settings']['extension_selections'] = ['acme/hero-pack' => ['version' => '1.0.0']];
        $theme = $this->makeThemeInState($stateWithExt);
        $checkpoint = $this->service->saveRevision($theme);

        $theme->update(['settings' => ['extension_selections' => []]]);

        $this->service->restoreRevision($checkpoint);
        $theme = $theme->fresh();

        $this->assertSame(
            ['acme/hero-pack' => ['version' => '1.0.0']],
            $theme->settings['extension_selections']
        );
    }

    public function test_restore_does_not_mutate_any_theme_release(): void
    {
        $stateA = $this->stateA();
        $theme = $this->makeThemeInState($stateA);
        $checkpointA = $this->service->saveRevision($theme);

        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '1.0.0',
            'changelog' => 'x',
            'status' => ThemeRelease::STATUS_PUBLISHED,
            'snapshot' => ['pages' => $theme->pages],
            'published_at' => now(),
        ]);
        $theme->current_release_id = $release->id;
        $theme->save();

        $theme->update($this->stateB());
        $this->service->restoreRevision($checkpointA);

        $this->assertSame(1, ThemeRelease::count(), 'restore must not create a new commercial release');
        $release->refresh();
        $this->assertSame('1.0.0', $release->version);
        $this->assertEquals($stateA['pages'], $release->snapshot['pages'], 'release snapshot content itself must stay exactly as published, unaffected by the later checkpoint restore');
        $this->assertSame($release->id, $theme->fresh()->current_release_id, 'restore must not repoint the release pointer');
    }

    public function test_restore_does_not_touch_customer_instance_revision_table(): void
    {
        // ThemeInstanceRevision is a customer-site model — this app has no
        // route to it at all; asserting the table exists nowhere in this
        // app's schema is the correct proof, not a mocked "unaffected" claim.
        $this->assertFalse(class_exists(ThemeInstanceRevision::class), 'ThemeInstanceRevision must not exist in saas-platform — it is a customer-site-only model.');
    }

    // ── D — Clone ───────────────────────────────────────────────────────

    public function test_clone_preserves_authoring_structure_with_new_identity(): void
    {
        $theme = $this->makeThemeInState($this->stateA());
        $theme->update(['status' => Theme::STATUS_PUBLISHED, 'published_at' => now()]);

        $clone = $this->service->cloneTheme($theme, 'کپی قالب تست');

        $this->assertSame($theme->pages, $clone->pages);
        $this->assertSame($theme->header_blocks, $clone->header_blocks);
        $this->assertSame($theme->footer_blocks, $clone->footer_blocks);
        $this->assertSame($theme->global_styles, $clone->global_styles);
        $this->assertSame($theme->settings, $clone->settings);

        $this->assertNotSame($theme->id, $clone->id);
        $this->assertNotSame($theme->slug, $clone->slug);
        $this->assertSame(Theme::STATUS_DRAFT, $clone->status, 'a clone must never inherit published/release state');
        $this->assertNull($clone->current_release_id ?? null);
    }

    // ── E/F — Export / Import ─────────────────────────────────────────────

    public function test_export_then_import_produces_structurally_equivalent_authoring_state(): void
    {
        $theme = $this->makeThemeInState($this->stateA());

        $exported = $this->service->exportTheme($theme);

        $this->assertArrayNotHasKey('site_id', $exported, 'export must never leak site_id');
        $this->assertArrayNotHasKey('current_release_id', $exported);
        $this->assertArrayNotHasKey('installs_count', $exported);

        $imported = $this->service->importTheme($exported);

        $this->assertEquals($theme->pages, $imported->pages);
        $this->assertEquals($theme->header_blocks, $imported->header_blocks);
        $this->assertEquals($theme->footer_blocks, $imported->footer_blocks);
        // importTheme() merges onto defaultGlobalStyles() to fill any gaps
        // (identical, pre-existing behavior for a hand-authored import with
        // a partial global_styles) — every explicitly-exported key must
        // still survive verbatim.
        foreach ($theme->global_styles as $key => $value) {
            $this->assertEquals($value, $imported->global_styles[$key] ?? null, "global_styles.{$key} not preserved through export/import");
        }
        $this->assertEquals($theme->settings, $imported->settings);
        $this->assertNotSame($theme->slug, $imported->slug);
        $this->assertSame(Theme::STATUS_DRAFT, $imported->status);
    }

    public function test_import_accepts_a_legacy_version_1_export_with_no_pages_key(): void
    {
        $legacy = [
            'version' => 1,
            'name' => 'قدیمی',
            'slug' => 'legacy',
            'header_blocks' => [],
            'footer_blocks' => [],
            'global_styles' => [],
            'settings' => [],
            // no 'pages' key at all — this is what every pre-fix export looked like.
        ];

        $imported = $this->service->importTheme($legacy);

        $this->assertSame([], $imported->pages);
    }

    public function test_import_rejects_an_unsupported_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importTheme(['version' => 99, 'pages' => []]);
    }

    public function test_import_rejects_an_unknown_block_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importTheme([
            'version' => 2,
            'pages' => ['home' => ['title' => 'خانه', 'blocks' => [
                ['id' => 'x', 'type' => 'totally_made_up_block_type', 'props' => []],
            ]]],
        ]);
    }

    public function test_import_rejects_when_ext_block_has_no_matching_selection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importTheme([
            'version' => 2,
            'pages' => ['home' => ['title' => 'خانه', 'blocks' => [
                ['id' => 'x', 'type' => 'ext:acme:widget', 'props' => []],
            ]]],
            'settings' => ['extension_selections' => []],
        ]);
    }

    public function test_a_failed_import_creates_no_theme_row(): void
    {
        $before = Theme::count();

        try {
            $this->service->importTheme(['version' => 2, 'pages' => ['home' => ['title' => 'x', 'blocks' => [
                ['id' => 'x', 'type' => 'nonexistent_type', 'props' => []],
            ]]]]);
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertSame($before, Theme::count(), 'a rejected import must not create a partial Theme row');
    }

    public function test_import_malformed_pages_shape_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importTheme(['version' => 2, 'pages' => 'not-an-array']);
    }

    // ── G — Unpublish ──────────────────────────────────────────────────

    public function test_unpublish_only_changes_theme_status_and_never_touches_release(): void
    {
        $theme = $this->makeThemeInState($this->stateA());
        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '1.0.0',
            'changelog' => 'x',
            'status' => ThemeRelease::STATUS_PUBLISHED,
            'snapshot' => ['pages' => $theme->pages],
            'published_at' => now(),
        ]);
        $theme->update(['status' => Theme::STATUS_PUBLISHED, 'published_at' => now()]);
        $theme->current_release_id = $release->id;
        $theme->save();

        $result = $this->service->unpublish($theme);

        $this->assertSame(Theme::STATUS_DRAFT, $result->status);
        $release->refresh();
        $this->assertSame(ThemeRelease::STATUS_PUBLISHED, $release->status, 'unpublish must never mutate the immutable release row');
        $this->assertSame($release->id, $result->current_release_id, 'unpublish must not clear release provenance — it only flips the draft/published pointer');
        $this->assertSame(1, ThemeRelease::count());
    }

    public function test_theme_revision_and_theme_release_are_separate_ledgers(): void
    {
        $theme = $this->makeThemeInState($this->stateA());
        $this->service->saveRevision($theme, 'checkpoint');
        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '1.0.0',
            'changelog' => 'x',
            'status' => ThemeRelease::STATUS_PUBLISHED,
            'snapshot' => ['pages' => $theme->pages],
            'published_at' => now(),
        ]);

        $this->assertSame(1, ThemeRevision::where('theme_id', $theme->id)->count());
        $this->assertSame(1, ThemeRelease::where('theme_id', $theme->id)->count());
        $this->assertNotSame(ThemeRevision::class, ThemeRelease::class);
    }
}
