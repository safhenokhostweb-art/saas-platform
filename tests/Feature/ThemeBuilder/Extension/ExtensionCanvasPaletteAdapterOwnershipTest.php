<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\Theme;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3G-B-6 FINAL CLOSURE + 3G-B-6A (§5-§8, §11-§18) — proves that a
 * manually-seeded/legacy-invalid DB state containing a cross-product
 * `ext:*` ownership collision (bypassing ExtensionReleaseService/ADR-046
 * entirely, exactly like ExtensionReleaseOwnershipInvariantTest's own
 * defense-in-depth scenarios) cannot become newly selectable for NEW SaaS
 * Canvas authoring, while already-persisted historical content using that
 * same type is preserved — at the INSTANCE, not type, level: one historical
 * ambiguous block must never blanket-authorize unlimited new instances of
 * the same type.
 *
 * Reuses the ONE canonical ExtensionElementOwnershipResolver at the palette
 * (authorablePaletteFor) and save-boundary (validateBlocksAgainstSelections)
 * seams, and the Canvas client's own existing stable block `id` for
 * historical-instance identity — no ownership logic or identity scheme is
 * reimplemented here.
 */
class ExtensionCanvasPaletteAdapterOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ExtensionReleaseService::class);
    }

    private function theme(array $settings = []): Theme
    {
        return Theme::create([
            'name' => 'قالب تست مالکیت افزونه',
            'slug' => 'ext-ownership-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => $settings,
        ]);
    }

    /** Bypasses ExtensionReleaseService/ADR-046 entirely — direct DB insert, mirroring ExtensionReleaseOwnershipInvariantTest's own legacy-collision fixture. */
    private function seedDirectDbCollisionRelease(int $extensionId, string $productIdentity, string $type): void
    {
        DB::table('extension_releases')->insert([
            'extension_id' => $extensionId,
            'version' => '1.0.0',
            'sdk_contract_version' => 1,
            'manifest' => json_encode([
                'product_identity' => $productIdentity,
                'package_version' => '1.0.0',
                'sdk_contract_version' => 1,
                'elements' => [['type' => $type, 'label' => 'L', 'category' => 'content']],
            ]),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAmbiguousCollisionType(string $type): array
    {
        $one = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->service->createExtension('acme', 'widgets-plus', 'Widgets Plus');
        $this->seedDirectDbCollisionRelease($one->id, 'acme/gallery-pro', $type);
        $this->seedDirectDbCollisionRelease($two->id, 'acme/widgets-plus', $type);

        return ['acme/gallery-pro' => ['version' => '1.0.0']];
    }

    // ── palette exposure (§16, §F) ───────────────────────────────────────────

    public function test_ambiguous_ownership_type_is_excluded_from_authorable_palette(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        $theme = $this->theme(['extension_selections' => $selections]);

        $authorable = ExtensionCanvasPaletteAdapter::authorablePaletteFor($theme);
        $this->assertArrayNotHasKey('ext:acme:legacy_collision', $authorable);

        // The unfiltered, rendering-support palette must still resolve it —
        // historical content using this type must keep rendering correctly.
        $raw = ExtensionCanvasPaletteAdapter::paletteFor($theme);
        $this->assertArrayHasKey('ext:acme:legacy_collision', $raw);
    }

    public function test_unambiguous_ownership_is_unaffected_by_the_filter(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content']],
        ]);

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $authorable = ExtensionCanvasPaletteAdapter::authorablePaletteFor($theme);
        $this->assertArrayHasKey('ext:acme:gallery', $authorable);
        $this->assertSame(ExtensionCanvasPaletteAdapter::paletteFor($theme), $authorable);
    }

    // ── save boundary: instance-aware historical exemption (§11-§18) ────────

    public function test_ambiguous_ownership_blocks_new_insertion_with_no_prior_instances(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []]];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ext:acme:legacy_collision', $result['error']);
    }

    /** §18.A — prior ambiguous instance count 1, submitted the exact same instance only → save succeeds. */
    public function test_a_single_historical_instance_resubmitted_unchanged_is_preserved(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []]];
        $previousInstances = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision']];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, $previousInstances);

        $this->assertTrue($result['ok']);
    }

    /**
     * §18.B / §11-§12 — the actual gap this closure fixes: a prior ambiguous
     * instance exists, and the submission keeps it AND adds a second NEW
     * instance of the same type. The TYPE has historical precedent, but the
     * second INSTANCE does not — must be rejected fail-closed, proving the
     * old type-level exemption (which would have allowed this) is gone.
     */
    public function test_b_duplicate_new_instance_of_a_historical_ambiguous_type_is_rejected(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []], // historical, preserved
            ['id' => 'b2', 'type' => 'ext:acme:legacy_collision', 'props' => []], // NEW second instance, same type
        ];
        $previousInstances = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision']];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, $previousInstances);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ext:acme:legacy_collision', $result['error']);
    }

    /** §18.C — the same stable id, present at a different location in the submitted tree, is still recognized as the same historical instance (a "move"). */
    public function test_c_historical_instance_moved_to_a_different_location_keeps_its_identity(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        // Previously at the top level; submitted now nested inside a section — same id, moved.
        $blocks = [[
            'id' => 'outer',
            'type' => 'section',
            'props' => ['blocks' => []],
            'children' => [
                ['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []],
            ],
        ]];
        $previousInstances = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision']];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, $previousInstances);

        $this->assertTrue($result['ok']);
    }

    /** §18.D — a duplicated/cloned block regenerates its id client-side (canvas.blade.php's _regenIds()), so it is indistinguishable from any other new instance and must be ownership-gated. */
    public function test_d_historical_instance_duplicated_with_a_new_id_is_rejected(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []],
            ['id' => 'blk_clone_1', 'type' => 'ext:acme:legacy_collision', 'props' => []], // clone — new id
        ];
        $previousInstances = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision']];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, $previousInstances);

        $this->assertFalse($result['ok']);
    }

    /** §18.E — a genuinely new instance of a cleanly, uniquely owned type is accepted regardless of prior instances. */
    public function test_e_new_instance_of_a_canonically_owned_type_is_accepted(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content']],
        ]);
        $selections = ['acme/gallery-pro' => ['version' => '1.0.0']];

        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:acme:gallery', 'props' => []], // new second instance, unambiguous type
        ];
        $previousInstances = [['id' => 'b1', 'type' => 'ext:acme:gallery']];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, $previousInstances);

        $this->assertTrue($result['ok']);
    }

    /** §18.G — a save attempt that fails the ownership gate proves nothing new was authorized; non-deletion of the pre-existing row itself is proven at the HTTP layer (ThemeCanvasExtensionAuthoringTest's "no partial write" pattern) since this unit-level call never touches the database. */
    public function test_g_rejected_save_reports_failure_without_mutating_anything(): void
    {
        $selections = $this->seedAmbiguousCollisionType('ext:acme:legacy_collision');
        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:legacy_collision', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:acme:legacy_collision', 'props' => []],
        ];
        $previousInstances = [['id' => 'b1', 'type' => 'ext:acme:legacy_collision']];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections, $previousInstances);

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    /**
     * "Wrong owner" and bare "unresolved" (for a type actually present in
     * the selected release) are structurally unreachable here, not merely
     * untested: `extension_releases.extension_id` is `ON DELETE RESTRICT`
     * against `extensions` (proven by attempting exactly this orphan setup —
     * MySQL rejects the delete), and the claiming product's own release row
     * always contributes itself as ownership evidence to
     * ExtensionElementOwnershipResolver's scan. The only failure mode a
     * type present in $available can ever hit is AMBIGUOUS (2+ owners,
     * covered above) or a clean RESOLVED match to the claimant — never a
     * resolution to a *different* singular owner while the claimant's own
     * valid evidence still exists.
     */
}
