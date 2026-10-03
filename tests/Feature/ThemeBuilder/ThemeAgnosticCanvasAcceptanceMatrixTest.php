<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use App\Services\ThemeBuilder\BlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\ThemeRuntime\FoodMartBlockTypes;
use SaaSPlatform\ThemeRuntime\Layout\DocumentEnvelope;
use SaaSPlatform\ThemeRuntime\Layout\DocumentValidator;
use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;
use Tests\TestCase;

/**
 * PROJECT SENTINEL — PHASE 7 (cleaned up in Phase 9)
 * Theme-Agnostic Canvas Acceptance Matrix.
 *
 * Proves, through the real Canvas HTTP routes, that Canvas core behavior does
 * not depend on a specific Theme — or on any Theme at all — in three states:
 * zero active Themes, one active Theme, and multiple active Themes. "Active"
 * is the existing authoring state (Theme::STATUS_PUBLISHED,
 * Theme::scopePublished()); every fixture lives ONLY in the isolated
 * saas_platform_test database created by RefreshDatabase. No developer-database
 * row, existing Theme, release, license, assignment, FoodMart reference or
 * SleepShiman asset is read or modified, and no Theme is published/unpublished
 * outside this test database.
 *
 * REGISTRY ISOLATION CONTRACT
 *
 * BlockRegistry's host-registration table is process-global static state (see
 * the shared package's own BlockRegistry docblock and Phase 3E-B / ADR-030).
 * This class therefore never leaves it altered for any later test:
 *
 *   - setUp() runs AFTER the application has booted, so
 *     ThemeRuntimeServiceProvider::boot() has ALREADY registered the shared
 *     package's host type sets. That real boot state is snapshotted into
 *     $bootHostTypes and is what the boot-state assertions below describe.
 *   - The snapshot is exact and lossless: BlockRegistry::all() is the
 *     canonical set merged with the host set (`+`, canonical first), and
 *     registerHostType() refuses any key that already exists canonically, so
 *     filtering all() by isHostRegisteredType() yields the host set exactly.
 *   - Restoring uses only existing public APIs (resetHostTypes() followed by
 *     registerHostType() per snapshotted type). Identical re-registration is
 *     the documented idempotent case, so restoration is byte-exact. No new
 *     production API is required or introduced.
 *   - An intentionally empty host table is never created by wiping state in
 *     setUp()/tearDown() for the whole class. It is scoped to the single test
 *     that needs it, via withHostTypes(), which restores the prior state in a
 *     finally block.
 *
 * SCOPE — WHAT IS DELIBERATELY NOT ASSERTED HERE
 *
 * This matrix asserts Theme-state behavior only. It does not re-assert
 * invariants that are already owned by other suites, so that a failure always
 * names the layer that broke:
 *
 *   - LayoutContract / DirectionProjector vocabularies and locale direction
 *     resolution  -> tests/Unit/Architecture/UniversalLayoutContractTest
 *   - CanvasTreeOperations insert/move/duplicate/delete mechanics
 *     -> tests/Unit/Architecture/CanvasTreeOperationsTest
 *   - registerHostType() collision / idempotency / canonical-override
 *     semantics -> tests/Feature/ThemeBuilder/ThemeRuntimeSharedAuthorityTest
 *
 * A browser launch is deliberately not used either: Tests\TestCase binds
 * PlaywrightExtractionService to unavailable-by-default precisely so no child
 * browser process can ever see an uncommitted RefreshDatabase transaction.
 */
class ThemeAgnosticCanvasAcceptanceMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The REAL host-registration state this application booted with, captured
     * after the service provider ran and before any test touched the registry.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $bootHostTypes = [];

    protected function setUp(): void
    {
        // parent::setUp() boots the application, so ThemeRuntimeServiceProvider
        // has already run its host-type registrations by the time we snapshot.
        parent::setUp();

        $this->bootHostTypes = $this->hostTypeSnapshot();
    }

    protected function tearDown(): void
    {
        // Exact restoration, not a blind reset: a blind resetHostTypes() here
        // would leave the process-global registry EMPTY for every later test
        // in the same PHPUnit process.
        $this->restoreHostTypes($this->bootHostTypes);

        parent::tearDown();
    }

    /**
     * The host-registered subset of BlockRegistry::all(), exactly.
     *
     * @return array<string, array<string, mixed>>
     */
    private function hostTypeSnapshot(): array
    {
        return array_filter(
            BlockRegistry::all(),
            static fn ($type): bool => BlockRegistry::isHostRegisteredType((string) $type),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param array<string, array<string, mixed>> $snapshot
     */
    private function restoreHostTypes(array $snapshot): void
    {
        BlockRegistry::resetHostTypes();

        foreach ($snapshot as $type => $definition) {
            BlockRegistry::registerHostType((string) $type, $definition);
        }
    }

    /**
     * Runs $callback with exactly $definitions registered as host types,
     * restoring the real boot state afterwards even if $callback throws.
     * Pass [] for the intentionally empty-host state.
     *
     * @param array<string, array<string, mixed>> $definitions
     */
    private function withHostTypes(array $definitions, callable $callback): mixed
    {
        $snapshot = $this->hostTypeSnapshot();

        BlockRegistry::resetHostTypes();
        try {
            foreach ($definitions as $type => $definition) {
                BlockRegistry::registerHostType((string) $type, $definition);
            }

            return $callback();
        } finally {
            $this->restoreHostTypes($snapshot);
        }
    }

    // ── Shared fixtures (existing patterns from ThemeCanvasZoneSaveTest /
    //    LegacyThemeRenderingTest) ──────────────────────────────────────

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'), 'is_admin' => true, 'is_active' => true,
        ]);
        if ($role = Role::where('slug', Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }
        return $user;
    }

    /** An isolated, never-published draft fixture — the inert document container. */
    private function themeFixture(array $overrides = []): Theme
    {
        return Theme::create(array_replace_recursive([
            'name' => 'Matrix ' . uniqid(), 'slug' => 'matrix-' . uniqid(),
            'type' => Theme::TYPE_FULL, 'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT, 'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [['id' => 'old-header', 'type' => 'heading', 'props' => ['text' => 'old']]],
            'footer_blocks' => [['id' => 'old-footer', 'type' => 'heading', 'props' => ['text' => 'old']]],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'blocks' => []]],
            'global_styles' => ['colors' => ['primary' => '#123456']],
            'settings' => ['unrelated' => 'kept'],
        ], $overrides));
    }

    /** A neutral document: no FoodMart/SleepShiman marker, no Theme marker, no Extension selection. */
    private function neutralDocument(): array
    {
        return [[
            'id' => 'sec-neutral', 'type' => 'section',
            'props' => ['padding' => 'md'], 'style' => ['bgColor' => '#ffffff'],
            'children' => [[
                'id' => 'col-neutral', 'type' => 'container', 'props' => [],
                'children' => [
                    ['id' => 'w-heading-neutral', 'type' => 'heading', 'props' => ['text' => 'Neutral Canvas Heading'], 'responsive' => ['mobile' => ['fontSize' => 18]]],
                    ['id' => 'w-button-neutral', 'type' => 'button', 'props' => ['label' => 'Neutral Action']],
                ],
            ]],
        ]];
    }

    private function matrixTypeDefinition(string $name): array
    {
        return WidgetConfig::normalize($name, [
            'name' => 'Matrix ' . $name,
            'category' => BlockRegistry::CATEGORY_CONTENT,
            'icon' => 'card',
            'schema' => ['title' => ['type' => 'string', 'label' => 'Title', 'default' => '']],
            'defaultProps' => ['title' => 'Matrix ' . $name],
        ]);
    }

    private function editor(Theme $theme)
    {
        return $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas', $theme));
    }

    private function save(Theme $theme, array $blocks, array $structuralOperations = []): \Illuminate\Testing\TestResponse
    {
        // `blocks` is ALWAYS sent, including an intentionally empty array:
        // the endpoint validates `blocks => present|array`, so omitting the
        // key (or filtering out an empty value) would produce a 422 that
        // looks like a Canvas defect rather than a malformed request.
        $payload = [
            'target' => 'page',
            'page_key' => 'home',
            'blocks' => $blocks,
        ];

        // Structural operations are genuinely optional (`sometimes|array`), so
        // only this key may be dropped when there is nothing to replay.
        if ($structuralOperations !== []) {
            $payload['structural_operations'] = $structuralOperations;
        }

        return $this->actingAs($this->admin())->putJson(route('admin.theme-builder.canvas.save', $theme), $payload);
    }

    /** Render arbitrary blocks through the real preview-ajax endpoint; returns the html string. */
    private function renderViaCanvas(Theme $theme, array $blocks): string
    {
        $response = $this->actingAs($this->admin())->postJson(
            route('admin.theme-builder.canvas.preview-ajax', $theme),
            ['page_key' => 'home', 'blocks' => $blocks]
        );
        $response->assertOk();
        $response->assertJsonStructure(['html', 'css']);
        return $response->json('html');
    }

    // ── REAL APPLICATION BOOT STATE (distinguished from test-local state) ─

    public function test_the_real_application_boot_registers_the_shared_package_host_types(): void
    {
        // Truthful statement about the APPLICATION, not about anything this
        // test did: ThemeRuntimeServiceProvider::boot() registers the shared
        // package's host type sets on every boot, and setUp() snapshotted that
        // state before this test touched the registry.
        $this->assertNotEmpty(
            $this->bootHostTypes,
            'ThemeRuntimeServiceProvider::boot() must register host types; an empty boot state means the shared package registrants are dead code'
        );

        foreach (array_keys(FoodMartBlockTypes::TYPES) as $type) {
            $this->assertArrayHasKey(
                $type,
                $this->bootHostTypes,
                "{$type} must be host-registered at boot"
            );
        }

        // Host types are additive: none of them may shadow a canonical
        // definition, and every one must be reachable through all().
        foreach ($this->bootHostTypes as $type => $definition) {
            $this->assertFalse(BlockRegistry::isCanonicalType($type), "{$type} must stay a host type, never canonical");
            $this->assertSame($definition, BlockRegistry::all()[$type]);
        }
    }

    public function test_teardown_leaves_the_process_global_registry_in_its_real_boot_state(): void
    {
        // The isolation contract this class promises to later tests: whatever
        // an individual test does to the process-global registry, the state it
        // leaves behind is byte-identical to the real application boot state.
        $before = $this->bootHostTypes;

        $this->withHostTypes([], function (): void {
            $this->assertSame([], $this->hostTypeSnapshot());
        });

        $this->assertSame($before, $this->hostTypeSnapshot());
        $this->assertSame(
            array_keys($before),
            array_keys(array_filter(BlockRegistry::all(), static fn ($t): bool => BlockRegistry::isHostRegisteredType((string) $t), ARRAY_FILTER_USE_KEY))
        );
    }

    // ── STATE A — ZERO ACTIVE THEMES ───────────────────────────────────

    public function test_state_a_zero_active_themes_and_editor_route_loads(): void
    {
        $theme = $this->themeFixture();

        $this->assertSame(0, Theme::published()->count(), 'no Theme is active in the isolated test database');
        $this->assertTrue($theme->isDraft());

        $this->editor($theme)->assertOk();
    }

    public function test_state_a_document_validator_accepts_the_neutral_document(): void
    {
        $result = DocumentValidator::validateEnvelope(DocumentEnvelope::fromLegacyBlocks($this->neutralDocument()));
        $this->assertTrue($result['ok'], implode('; ', $result['errors']));
        // 4 authored blocks plus the canonical page root node.
        $this->assertSame(5, $result['node_count']);
    }

    public function test_state_a_structural_save_succeeds_for_a_neutral_document(): void
    {
        $theme = $this->themeFixture();
        $blocks = $this->neutralDocument();

        $this->save($theme, $blocks)->assertOk()->assertJson(['success' => true]);

        $this->assertSame($blocks, $theme->fresh()->pages['home']['blocks']);
    }

    public function test_state_a_structural_save_accepts_an_intentionally_empty_page(): void
    {
        $theme = $this->themeFixture();

        // An empty block array is a valid authoring state, not a missing key.
        $this->save($theme, [])->assertOk()->assertJson(['success' => true]);

        $this->assertSame([], $theme->fresh()->pages['home']['blocks']);
    }

    public function test_state_a_preview_renders_a_neutral_document(): void
    {
        $theme = $this->themeFixture();
        $this->save($theme, $this->neutralDocument())->assertOk();

        $page = $this->actingAs($this->admin())->get(route('admin.theme-builder.canvas.page-preview', [$theme, 'home']));
        $page->assertOk();
        $page->assertSee('Neutral Canvas Heading', false);

        $html = $this->renderViaCanvas($theme, $this->neutralDocument());
        $this->assertStringContainsString('Neutral Canvas Heading', $html);
        $this->assertStringContainsString('data-tb-block-type="heading"', $html);
    }

    public function test_state_a_unknown_block_renders_safe_fallback_and_module_block_renders_placeholder(): void
    {
        $theme = $this->themeFixture();

        // Unknown type: the shared renderer's safe fallback — markup, no exception.
        $html = $this->renderViaCanvas($theme, [
            ['id' => 'w-unknown', 'type' => 'matrix:unregistered_widget', 'props' => ['text' => 'x']],
        ]);
        $this->assertStringContainsString('data-type="matrix:unregistered_widget"', $html);

        // module: block without any host renderer callable: safe placeholder.
        $html = $this->renderViaCanvas($theme, [
            ['id' => 'w-module', 'type' => 'module:blog', 'props' => ['title' => 'Blog block']],
        ]);
        $this->assertStringContainsString('data-module-block="module:blog"', $html);
    }

    // ── STATE B — ONE ACTIVE THEME ─────────────────────────────────────

    public function test_state_b_one_active_theme_editor_loads_and_neutral_blocks_render(): void
    {
        $theme = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);

        $this->assertSame(1, Theme::published()->count());
        $this->assertTrue($theme->isPublished());

        $this->editor($theme)->assertOk();

        $html = $this->renderViaCanvas($theme, $this->neutralDocument());
        $this->assertStringContainsString('Neutral Canvas Heading', $html);
        $this->assertStringContainsString('data-tb-block-type="heading"', $html);
    }

    public function test_state_b_canvas_core_works_with_an_intentionally_empty_host_registry(): void
    {
        $theme = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);

        // The empty host table below is TEST-LOCAL isolation, scoped to this
        // test and restored by withHostTypes(). It is deliberately NOT a claim
        // about the application: the real boot state is non-empty, and
        // test_the_real_application_boot_registers_the_shared_package_host_types
        // asserts that separately.
        $this->withHostTypes([], function () use ($theme): void {
            // Theme-specific block definitions are OPTIONAL: the editor and the
            // neutral render path work with zero host-registered types.
            $this->assertSame([], $this->hostTypeSnapshot());
            $this->editor($theme)->assertOk();
            $neutralWithoutHostTypes = $this->renderViaCanvas($theme, $this->neutralDocument());

            // A Theme-specific type registers through the generic host seam
            // (the same seam every other Theme-specific type uses — never a
            // parallel registry) and is additive, never canonical.
            BlockRegistry::registerHostType('matrix:theme_b_card', $this->matrixTypeDefinition('matrix:theme_b_card'));
            $this->assertTrue(BlockRegistry::isHostRegisteredType('matrix:theme_b_card'));
            $this->assertFalse(BlockRegistry::isCanonicalType('matrix:theme_b_card'));

            // Its own block renders through the same shared, theme-agnostic
            // presentation path — the shared fallback wrapper with its own type marker.
            $own = $this->renderViaCanvas($theme, [
                ['id' => 'w-b-card', 'type' => 'matrix:theme_b_card', 'props' => ['title' => 'B']],
            ]);
            $this->assertStringContainsString('data-type="matrix:theme_b_card"', $own);

            // Neutral rendering is byte-identical before and after the
            // Theme-specific registration.
            $this->assertSame($neutralWithoutHostTypes, $this->renderViaCanvas($theme, $this->neutralDocument()));

            // A document using the Theme's own type saves and reloads.
            $this->save($theme, [['id' => 'w-b-card', 'type' => 'matrix:theme_b_card', 'props' => ['title' => 'B']]])->assertOk();
            $this->assertSame('matrix:theme_b_card', $theme->fresh()->pages['home']['blocks'][0]['type']);
        });
    }

    public function test_state_b_structural_operations_and_save_reload_preserve_the_document(): void
    {
        $theme = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);
        $initial = [['id' => 'sec-one', 'type' => 'section', 'props' => [], 'children' => [
            ['id' => 'w-one', 'type' => 'heading', 'props' => ['text' => 'One']],
        ]]];
        $this->save($theme, $initial)->assertOk();

        // An explicit structural insert operation, replayed server-side.
        $inserted = ['id' => 'sec-two', 'type' => 'section', 'props' => [], 'children' => [
            ['id' => 'w-two', 'type' => 'heading', 'props' => ['text' => 'Two']],
        ]];
        $expected = array_merge([$inserted], $initial);
        $this->save($theme, $expected, [[
            'operation' => 'insert', 'node_id' => 'sec-two',
            'target_parent_id' => 'page', 'target_index' => 0, 'node' => $inserted,
        ]])->assertOk();

        $this->assertSame($expected, $theme->fresh()->pages['home']['blocks']);
    }

    public function test_state_b_disabling_the_theme_does_not_break_canvas_core_in_the_same_lifecycle(): void
    {
        $theme = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);
        $this->assertSame(1, Theme::published()->count());

        // Reduce the active set (authoring state only — no row is deleted,
        // nothing is unpublished outside this isolated fixture).
        $theme->update(['status' => Theme::STATUS_DRAFT]);
        $theme->refresh();
        $this->assertSame(0, Theme::published()->count());

        // Canvas core behavior is unchanged by the disable.
        $this->editor($theme)->assertOk();
        $this->save($theme, $this->neutralDocument())->assertOk();
        $html = $this->renderViaCanvas($theme, $this->neutralDocument());
        $this->assertStringContainsString('Neutral Canvas Heading', $html);
        $this->assertStringContainsString('data-type="matrix:unregistered_widget"', $this->renderViaCanvas($theme, [
            ['id' => 'w-unknown', 'type' => 'matrix:unregistered_widget', 'props' => []],
        ]));
    }

    // ── STATE C — MULTIPLE ACTIVE THEMES ───────────────────────────────

    public function test_state_c_multiple_active_themes_expose_distinct_type_sets_through_the_generic_seam(): void
    {
        $themeA = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);
        $themeB = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);
        $this->assertSame(2, Theme::published()->count());

        $this->withHostTypes([
            'matrix:theme_a_banner' => $this->matrixTypeDefinition('matrix:theme_a_banner'),
            'matrix:theme_b_banner' => $this->matrixTypeDefinition('matrix:theme_b_banner'),
        ], function () use ($themeA, $themeB): void {
            $this->assertTrue(BlockRegistry::isHostRegisteredType('matrix:theme_a_banner'));
            $this->assertTrue(BlockRegistry::isHostRegisteredType('matrix:theme_b_banner'));

            // Each Theme's own block renders with ONLY its own marker — and
            // renders byte-identically on either Theme's canvas: presentation
            // is a function of the type, never of the active Theme.
            $aBlock = [['id' => 'w-a-banner', 'type' => 'matrix:theme_a_banner', 'props' => ['title' => 'A']]];
            $bBlock = [['id' => 'w-b-banner', 'type' => 'matrix:theme_b_banner', 'props' => ['title' => 'B']]];

            $aOnA = $this->renderViaCanvas($themeA, $aBlock);
            $aOnB = $this->renderViaCanvas($themeB, $aBlock);
            $this->assertSame($aOnA, $aOnB, 'Theme A block renders identically on Theme B canvas');
            $this->assertStringContainsString('data-type="matrix:theme_a_banner"', $aOnA);
            $this->assertStringNotContainsString('matrix:theme_b_banner', $aOnA, 'Theme A block does not invoke Theme B presentation logic');

            $bOnB = $this->renderViaCanvas($themeB, $bBlock);
            $bOnA = $this->renderViaCanvas($themeA, $bBlock);
            $this->assertSame($bOnB, $bOnA, 'Theme B block renders identically on Theme A canvas');
            $this->assertStringContainsString('data-type="matrix:theme_b_banner"', $bOnB);
            $this->assertStringNotContainsString('matrix:theme_a_banner', $bOnB, 'Theme B block does not invoke Theme A presentation logic');

            // Each Theme saves a document carrying its own type.
            $this->save($themeA, $aBlock)->assertOk();
            $this->save($themeB, $bBlock)->assertOk();
            $this->assertSame('matrix:theme_a_banner', $themeA->fresh()->pages['home']['blocks'][0]['type']);
            $this->assertSame('matrix:theme_b_banner', $themeB->fresh()->pages['home']['blocks'][0]['type']);
        });
    }

    public function test_state_c_neutral_blocks_render_and_save_identically_across_both_active_themes(): void
    {
        $themeA = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);
        $themeB = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);

        // With the real boot host types (this test changes nothing): the same
        // neutral document renders identically on both Themes' canvases.
        $onA = $this->renderViaCanvas($themeA, $this->neutralDocument());
        $onB = $this->renderViaCanvas($themeB, $this->neutralDocument());
        $this->assertSame($onA, $onB);

        // Adding two Theme-specific registrations changes neither Theme's
        // neutral rendering: the merge is additive and Theme-independent.
        $this->withHostTypes([
            'matrix:theme_a_banner' => $this->matrixTypeDefinition('matrix:theme_a_banner'),
            'matrix:theme_b_banner' => $this->matrixTypeDefinition('matrix:theme_b_banner'),
        ], function () use ($themeA, $themeB, $onA, $onB): void {
            $this->assertSame($onA, $this->renderViaCanvas($themeA, $this->neutralDocument()));
            $this->assertSame($onB, $this->renderViaCanvas($themeB, $this->neutralDocument()));

            $this->save($themeA, $this->neutralDocument())->assertOk();
            $this->save($themeB, $this->neutralDocument())->assertOk();
            $this->assertSame($this->neutralDocument(), $themeA->fresh()->pages['home']['blocks']);
            $this->assertSame($this->neutralDocument(), $themeB->fresh()->pages['home']['blocks']);
        });
    }

    public function test_state_c_reducing_the_active_set_to_one_does_not_break_the_other_or_canvas_core(): void
    {
        $themeA = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);
        $themeB = $this->themeFixture(['status' => Theme::STATUS_PUBLISHED]);

        $this->withHostTypes([
            'matrix:theme_a_banner' => $this->matrixTypeDefinition('matrix:theme_a_banner'),
            'matrix:theme_b_banner' => $this->matrixTypeDefinition('matrix:theme_b_banner'),
        ], function () use ($themeA, $themeB): void {
            // "Removing" from the ACTIVE set = authoring-state change only.
            // Row deletion is out of scope by the task's own constraint.
            $themeA->update(['status' => Theme::STATUS_DRAFT]);
            $this->assertSame(1, Theme::published()->count());
            $this->assertSame($themeB->id, Theme::published()->first()->id);

            // The remaining Theme and Canvas core are unaffected.
            $this->editor($themeB)->assertOk();
            $this->save($themeB, $this->neutralDocument())->assertOk();
            $html = $this->renderViaCanvas($themeB, $this->neutralDocument());
            $this->assertStringContainsString('Neutral Canvas Heading', $html);
            $this->assertStringContainsString('data-type="matrix:theme_b_banner"', $this->renderViaCanvas($themeB, [
                ['id' => 'w-b-banner', 'type' => 'matrix:theme_b_banner', 'props' => ['title' => 'B']],
            ]));
        });
    }
}