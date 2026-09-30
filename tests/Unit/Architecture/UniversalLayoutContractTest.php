<?php

namespace Tests\Unit\Architecture;

use SaaSPlatform\ThemeRuntime\Layout\DocumentEnvelope;
use SaaSPlatform\ThemeRuntime\Layout\DirectionProjector;
use SaaSPlatform\ThemeRuntime\Layout\DocumentValidator;
use SaaSPlatform\ThemeRuntime\Layout\LayoutContract;
use SaaSPlatform\ThemeRuntime\Layout\NodeCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2A — canonical layout contract tests. The single authority is the
 * PRODUCTION class in the shared theme-runtime package
 * (SaaSPlatform\ThemeRuntime\Layout\LayoutContract); the Phase 1 test-scope
 * prototype has been deleted and these tests exercise the production
 * contract directly.
 */
class UniversalLayoutContractTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────
    // Single authority (STEP 1)
    // ────────────────────────────────────────────────────────────────────

    public function test_production_contract_is_the_canonical_authority(): void
    {
        $reflection = new \ReflectionClass(LayoutContract::class);

        // Lives in the shared package, not in test scope, and is final —
        // one implementation, no host forks.
        $this->assertSame('SaaSPlatform\ThemeRuntime\Layout\LayoutContract', $reflection->getName());
        $this->assertTrue($reflection->isFinal());
        $this->assertFileExists(
            dirname($reflection->getFileName()) . '/LayoutContract.php'
        );
        $this->assertFileDoesNotExist(__DIR__ . '/LayoutContract.php', 'the Phase 1 test-scope prototype must not remain an independent authority');
    }

    // ────────────────────────────────────────────────────────────────────
    // Layout node contract
    // ────────────────────────────────────────────────────────────────────

    public function test_layout_node_kinds_are_pinned(): void
    {
        $this->assertSame(
            ['page', 'section', 'container', 'row', 'column', 'widget'],
            LayoutContract::NODE_KINDS
        );
        $this->assertSame(['section', 'container', 'row', 'column'], LayoutContract::STRUCTURE_KINDS);
        $this->assertSame(['widget'], LayoutContract::LEAF_KINDS);
    }

    // ────────────────────────────────────────────────────────────────────
    // Direction contract (STEP 5 / STEP 10 items 5-9)
    // ────────────────────────────────────────────────────────────────────

    public function test_direction_values_are_only_rtl_or_ltr(): void
    {
        $this->assertSame(['rtl', 'ltr'], LayoutContract::DIRECTIONS);
        $this->assertSame('rtl', LayoutContract::DEFAULT_DIRECTION);
        $this->assertTrue(LayoutContract::isSupportedDirection('rtl'));
        $this->assertTrue(LayoutContract::isSupportedDirection('ltr'));
        $this->assertFalse(LayoutContract::isSupportedDirection('auto'));
    }

    public function test_fa_resolves_rtl(): void
    {
        $this->assertSame('rtl', LayoutContract::directionForLocale('fa'));
        $this->assertSame('rtl', LayoutContract::directionForLocale('fa_IR'));
    }

    public function test_ar_resolves_rtl(): void
    {
        $this->assertSame('rtl', LayoutContract::directionForLocale('ar'));
        $this->assertSame('rtl', LayoutContract::directionForLocale('ar_EG'));
    }

    public function test_en_resolves_ltr(): void
    {
        $this->assertSame('ltr', LayoutContract::directionForLocale('en'));
        $this->assertSame('ltr', LayoutContract::directionForLocale('en_US'));
    }

    public function test_unknown_locale_fails_closed_to_platform_default(): void
    {
        $this->assertSame('rtl', LayoutContract::directionForLocale('xx'));
    }

    // ────────────────────────────────────────────────────────────────────
    // Logical alignment (STEP 10 items 10)
    // ────────────────────────────────────────────────────────────────────

    public function test_logical_alignment_values_are_start_center_end(): void
    {
        $this->assertSame(['start', 'center', 'end'], LayoutContract::ALIGNMENTS);
    }

    public function test_start_alignment_resolves_per_direction_at_presentation_boundary(): void
    {
        $this->assertSame('right', LayoutContract::resolveAlignment('start', 'rtl'));
        $this->assertSame('left', LayoutContract::resolveAlignment('start', 'ltr'));
        $this->assertSame('left', LayoutContract::resolveAlignment('end', 'rtl'));
        $this->assertSame('right', LayoutContract::resolveAlignment('end', 'ltr'));
        $this->assertSame('center', LayoutContract::resolveAlignment('center', 'rtl'));
    }

    public function test_physical_values_never_persist_as_canonical(): void
    {
        // Normalization converts legacy physical values to logical ones
        // using the direction — the canonical storage vocabulary stays
        // start|center|end.
        $this->assertSame('start', LayoutContract::normalizeAlignment('right', 'rtl'));
        $this->assertSame('start', LayoutContract::normalizeAlignment('left', 'ltr'));
        $this->assertSame('end', LayoutContract::normalizeAlignment('left', 'rtl'));
        foreach (LayoutContract::ALIGNMENTS as $logical) {
            $this->assertSame($logical, LayoutContract::normalizeAlignment($logical, 'rtl'));
        }
    }

    public function test_flex_alignment_is_direction_free(): void
    {
        foreach (LayoutContract::DIRECTIONS as $direction) {
            $this->assertSame('flex-start', LayoutContract::resolveFlexAlign('start', $direction));
            $this->assertSame('center', LayoutContract::resolveFlexAlign('center', $direction));
            $this->assertSame('flex-end', LayoutContract::resolveFlexAlign('end', $direction));
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Responsive contract (STEP 6 / STEP 10 item 11)
    // ────────────────────────────────────────────────────────────────────

    public function test_responsive_cascade_resolves_with_fallback(): void
    {
        $responsive = ['tablet' => ['fontSize' => 18], 'mobile' => []];
        $this->assertSame(18, LayoutContract::responsiveValue($responsive, 'mobile', 'fontSize', 16));
        $this->assertSame(16, LayoutContract::responsiveValue($responsive, 'desktop', 'fontSize', 16));
        $this->assertSame('x', LayoutContract::responsiveValue([], 'mobile', 'anything', 'x'));
    }

    public function test_responsive_shape_validation(): void
    {
        $this->assertTrue(LayoutContract::isResponsiveShape(['desktop' => [], 'tablet' => [], 'mobile' => []]));
        $this->assertTrue(LayoutContract::isResponsiveShape(['mobile' => ['fontSize' => 14]]));
        $this->assertFalse(LayoutContract::isResponsiveShape(['mobile' => 14]));
        $this->assertFalse(LayoutContract::isResponsiveShape(['fourg' => []]));
    }

    // ────────────────────────────────────────────────────────────────────
    // Lossless legacy adapter (STEP 3 / STEP 10 items 2-4, 12)
    // ────────────────────────────────────────────────────────────────────

    public function test_legacy_to_tree_to_legacy_round_trip_is_lossless(): void
    {
        $legacy = [
            [
                'id' => 'blk_1',
                'type' => 'section',
                'props' => ['padding' => 'md'],
                'style' => ['bgColor' => '#fff'],
                'children' => [
                    ['id' => 'blk_2', 'type' => 'heading', 'props' => ['text' => 'سلام']],
                ],
            ],
            ['id' => 'blk_3', 'type' => 'hero', 'props' => ['title' => 'قلب']],
        ];

        $tree = LayoutContract::fromLegacyBlocks($legacy);
        $this->assertSame('page', $tree['kind']);
        $this->assertCount(2, $tree['children']);

        $this->assertSame($legacy, LayoutContract::toLegacyBlocks($tree));
    }

    public function test_unknown_future_keys_survive(): void
    {
        $legacy = [
            [
                'id' => 'blk_9',
                'type' => 'columns',
                'props' => [],
                'responsive' => ['mobile' => ['paddingY' => 8]],
                'dataBinding' => ['source' => 'products'],
                'modulePresentation' => ['capability' => 'products.collection', 'label' => 'کاروسل محصولات'],
                'unknown_future_key' => ['a' => 1],
                'children' => [],
            ],
        ];

        $tree = LayoutContract::fromLegacyBlocks($legacy);
        $this->assertSame(['source' => 'products'], $tree['children'][0]['dataBinding']);
        $this->assertSame(['capability' => 'products.collection', 'label' => 'کاروسل محصولات'], $tree['children'][0]['modulePresentation']);
        $this->assertSame(['a' => 1], $tree['children'][0]['unknown_future_key']);

        $this->assertSame($legacy, LayoutContract::toLegacyBlocks($tree));
    }

    public function test_foodmart_structure_survives_the_adapter(): void
    {
        // A representative FoodMart-shaped tree: host-registered types with
        // repeater props (slides), dataBinding/modulePresentation metadata,
        // nested logical-group items — adapted and returned byte-identical.
        $legacy = [
            [
                'id' => 'blk_fm_hero',
                'type' => 'foodmart_hero',
                'props' => [
                    'slides' => [
                        ['label' => 'New', 'title' => 'Fresh Smoothie & Summer Juice', 'image' => 'images/product-thumb-1.png'],
                    ],
                    'promos' => [['badge' => '20% off', 'title' => 'Fruits & Vegetables']],
                ],
                'dataBinding' => null,
                'unknown_meta' => ['liveStructureUpdate' => 'document'],
            ],
            [
                'id' => 'blk_fm_cat',
                'type' => 'foodmart_categories',
                'props' => ['section_title' => 'Category', 'items' => [['title' => 'Vegetables', 'url' => 'index.html']]],
                'dataBinding' => ['source' => 'product_categories', 'manual_prop' => 'items'],
                'modulePresentation' => ['capability' => 'product_categories.collection', 'label' => 'FoodMart category carousel'],
            ],
            ['id' => 'blk_fm_hdr', 'type' => 'foodmart_header', 'props' => ['logo_image' => '', 'nav_items' => []]],
        ];

        $tree = LayoutContract::fromLegacyBlocks($legacy);
        $this->assertCount(3, $tree['children']);
        $this->assertSame('widget', $tree['children'][0]['kind']);
        $this->assertSame('widget', $tree['children'][1]['kind']);

        $this->assertSame($legacy, LayoutContract::toLegacyBlocks($tree));
    }

    public function test_legacy_container_aliases_adapt_children(): void
    {
        $legacy = [
            [
                'id' => 'blk_1',
                'type' => 'container',
                'props' => ['maxWidth' => '7xl', 'blocks' => []],
                'blocks' => [
                    ['id' => 'blk_2', 'type' => 'text', 'props' => ['content' => 'متن']],
                ],
            ],
        ];

        $tree = LayoutContract::fromLegacyBlocks($legacy);
        $this->assertCount(1, $tree['children'][0]['children']);
        $this->assertSame('widget', $tree['children'][0]['children'][0]['kind']);
    }

    public function test_nested_node_representation(): void
    {
        $tree = LayoutContract::fromLegacyBlocks([
            [
                'id' => 's1', 'type' => 'section',
                'children' => [
                    [
                        'id' => 'c1', 'type' => 'columns',
                        'children' => [
                            ['id' => 'w1', 'type' => 'heading'],
                            ['id' => 'w2', 'type' => 'button'],
                        ],
                    ],
                ],
            ],
        ]);

        $section = $tree['children'][0];
        $column = $section['children'][0];
        $this->assertSame('section', $section['kind']);
        $this->assertSame('container', $column['kind']);   // columns alias
        $this->assertSame('widget', $column['children'][0]['kind']);
        $this->assertCount(2, $column['children']);
    }

    public function test_non_container_nodes_never_gain_children(): void
    {
        $legacy = [['id' => 'b1', 'type' => 'heading', 'props' => ['text' => 'x']]];
        $back = LayoutContract::toLegacyBlocks(LayoutContract::fromLegacyBlocks($legacy));

        $this->assertArrayNotHasKey('children', $back[0]);
        $this->assertSame($legacy, $back);
    }

    // ────────────────────────────────────────────────────────────────────
    // Document envelope (STEP 2 / STEP 10 items 5-6)
    // ────────────────────────────────────────────────────────────────────

    public function test_envelope_defaults_to_rtl_page(): void
    {
        $doc = DocumentEnvelope::make(['id' => 'page', 'kind' => 'page', 'children' => []]);
        $this->assertSame(1, $doc['layout_version']);
        $this->assertSame('page', $doc['type']);
        $this->assertSame('rtl', $doc['direction']);
        $this->assertSame('fa', $doc['locale']);
        $this->assertSame(['desktop', 'tablet', 'mobile'], $doc['responsive']['breakpoints']);
    }

    public function test_rtl_document_explicit(): void
    {
        $doc = DocumentEnvelope::make(['id' => 'page', 'kind' => 'page'], ['direction' => 'rtl', 'locale' => 'fa']);
        $this->assertSame('rtl', $doc['direction']);
        $this->assertSame(['dir' => 'rtl', 'lang' => 'fa'], DirectionProjector::attributes($doc));
    }

    public function test_ltr_document_explicit(): void
    {
        $doc = DocumentEnvelope::make(['id' => 'page', 'kind' => 'page'], ['direction' => 'ltr', 'locale' => 'en']);
        $this->assertSame('ltr', $doc['direction']);
        $this->assertSame(['dir' => 'ltr', 'lang' => 'en'], DirectionProjector::attributes($doc));
        $this->assertSame('ltr', DocumentEnvelope::direction($doc));
    }

    public function test_envelope_zone_types(): void
    {
        foreach (['page', 'header', 'footer', 'template_part'] as $type) {
            $doc = DocumentEnvelope::make(['id' => 'page', 'kind' => 'page'], ['type' => $type]);
            $this->assertSame($type, $doc['type']);
            $this->assertTrue(DocumentEnvelope::isValid($doc));
        }
        // Unknown type fails closed to 'page'.
        $doc = DocumentEnvelope::make(['id' => 'page', 'kind' => 'page'], ['type' => 'megafooter']);
        $this->assertSame('page', $doc['type']);
    }

    public function test_legacy_themes_without_direction_inherit_current_behavior(): void
    {
        // No direction + no locale → projector declines to opine; hosts keep
        // their existing behavior (never a blind default flip).
        $this->assertSame(['dir' => null, 'lang' => null], DirectionProjector::attributes([]));
        // Locale-only documents derive direction from the locale.
        $this->assertSame('rtl', DocumentEnvelope::direction(['locale' => 'ar']));
        $this->assertSame('ltr', DocumentEnvelope::direction(['locale' => 'en']));
        // Hosts that must emit a direction attribute get the platform default.
        $this->assertSame(['dir' => 'rtl', 'lang' => 'fa'], DirectionProjector::attributesWithDefault([]));
    }

    public function test_envelope_wraps_and_unwraps_legacy_blocks(): void
    {
        $legacy = [['id' => 'blk_1', 'type' => 'hero', 'props' => ['title' => 'x']]];
        $doc = DocumentEnvelope::fromLegacyBlocks($legacy, ['direction' => 'ltr', 'locale' => 'en']);
        $this->assertTrue(DocumentEnvelope::isValid($doc));
        $this->assertSame($legacy, DocumentEnvelope::toLegacyBlocks($doc));
    }

    // ────────────────────────────────────────────────────────────────────
    // Fail-closed validation (STEP 9 / STEP 10 items 13-17)
    // ────────────────────────────────────────────────────────────────────

    public function test_valid_document_passes(): void
    {
        $doc = DocumentEnvelope::fromLegacyBlocks([
            ['id' => 'blk_1', 'type' => 'section', 'children' => [
                ['id' => 'blk_2', 'type' => 'heading', 'props' => []],
            ]],
        ]);
        $result = DocumentValidator::validateEnvelope($doc);
        $this->assertTrue($result['ok'], implode('; ', $result['errors']));
        $this->assertSame(3, $result['node_count']);
    }

    public function test_cyclic_representation_rejected_via_duplicate_ids(): void
    {
        // A cycle can only be *represented* by the same node appearing in
        // two places — duplicate ids are the canonical detection.
        $tree = [
            ['id' => 'a', 'kind' => 'section', 'type' => 'section', 'children' => [
                ['id' => 'b', 'kind' => 'container', 'type' => 'container', 'children' => []],
            ]],
            ['id' => 'b', 'kind' => 'container', 'type' => 'container', 'children' => [
                ['id' => 'a', 'kind' => 'section', 'type' => 'section', 'children' => []],
            ]],
        ];

        $result = DocumentValidator::validateTree($tree);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, "duplicate node id 'b'")));
        $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, "duplicate node id 'a'")));
    }

    public function test_self_parent_rejected(): void
    {
        $tree = [['id' => 'a', 'kind' => 'section', 'type' => 'section', 'children' => [
            ['id' => 'a', 'kind' => 'section', 'type' => 'section', 'children' => []],
        ]]];

        $result = DocumentValidator::validateTree($tree);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, 'duplicate node id')));
    }

    public function test_excessive_nesting_rejected(): void
    {
        // Build a chain deeper than MAX_DEPTH (12).
        $node = ['id' => 'leaf', 'kind' => 'widget', 'type' => 'heading'];
        for ($i = DocumentValidator::MAX_DEPTH; $i >= 1; $i--) {
            $node = ['id' => "n{$i}", 'kind' => 'section', 'type' => 'section', 'children' => [$node]];
        }

        $result = DocumentValidator::validateTree([$node]);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, 'nesting depth exceeds')));
    }

    public function test_excessive_node_count_rejected(): void
    {
        $nodes = [];
        for ($i = 0; $i <= DocumentValidator::MAX_NODES + 1; $i++) {
            $nodes[] = ['id' => "n{$i}", 'kind' => 'widget', 'type' => 'heading'];
        }

        $result = DocumentValidator::validateTree($nodes);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, 'node count')));
    }

    public function test_capability_metadata_cannot_escalate_from_untrusted_input(): void
    {
        // A document claiming privileged capabilities is NOT honored: the
        // validator flags executable-looking keys, and capability computation
        // derives only from trusted host metadata + kind.
        $tree = [[
            'id' => 'x1', 'kind' => 'widget', 'type' => 'heading',
            'rendererClass' => 'App\\Evil\\Renderer',
            'callback' => 'system',
            'children' => [],      // widget claiming children
        ]];

        $result = DocumentValidator::validateTree($tree);
        $this->assertFalse($result['ok']);
        $errors = implode("\n", $result['errors']);
        $this->assertStringContainsString('forbidden executable-like key', $errors);
        $this->assertStringContainsString('may not carry children', $errors);

        // Capabilities from the node itself remain the safe kind-derived set:
        // a widget cannot become a container by declaring it.
        $caps = NodeCapabilities::forNode(['id' => 'x1', 'kind' => 'widget']);
        $this->assertFalse($caps['canHaveChildren']);
        $this->assertFalse(NodeCapabilities::acceptsChild($caps, 'widget'));
    }

    public function test_invalid_alignment_and_direction_rejected(): void
    {
        $tree = [['id' => 'a', 'kind' => 'widget', 'type' => 'heading', 'props' => ['alignment' => 'left']]];

        $result = DocumentValidator::validateTree($tree);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, 'start|center|end')));

        $this->assertTrue(DocumentValidator::isValidAlignment('start'));
        $this->assertFalse(DocumentValidator::isValidAlignment('left'));
        $this->assertFalse(DocumentValidator::isValidAlignment('right'));
    }

    public function test_invalid_envelope_shape_fails_closed(): void
    {
        $this->assertFalse(DocumentEnvelope::isValid(null));
        $this->assertFalse(DocumentEnvelope::isValid([]));
        $this->assertFalse(DocumentEnvelope::isValid(['layout_version' => 99, 'direction' => 'rtl', 'page' => []]));
        $this->assertFalse(DocumentEnvelope::isValid(['layout_version' => 1, 'direction' => 'auto', 'page' => []]));
        $this->assertFalse(DocumentEnvelope::isValid(['layout_version' => 1, 'direction' => 'rtl']));

        $result = DocumentValidator::validateEnvelope(['layout_version' => 42, 'direction' => 'rtl', 'page' => []]);
        $this->assertFalse($result['ok']);
    }

    // ────────────────────────────────────────────────────────────────────
    // Node capability contract (STEP 4)
    // ────────────────────────────────────────────────────────────────────

    public function test_capability_sets_by_kind(): void
    {
        $page = NodeCapabilities::forNode(['id' => 'page', 'kind' => 'page']);
        $this->assertTrue($page['canHaveChildren']);
        $this->assertFalse($page['canMove']);
        $this->assertFalse($page['canDuplicate']);
        $this->assertFalse($page['canDelete']);

        $section = NodeCapabilities::forNode(['id' => 's1', 'kind' => 'section']);
        $this->assertTrue($section['canHaveChildren']);
        $this->assertTrue($section['canMove']);
        $this->assertTrue($section['canDuplicate']);
        $this->assertTrue($section['canDelete']);

        $widget = NodeCapabilities::forNode(['id' => 'w1', 'kind' => 'widget']);
        $this->assertFalse($widget['canHaveChildren']);
        $this->assertTrue($widget['canMove']);
    }

    public function test_locked_structure_protects_structure_but_allows_editing(): void
    {
        // Imported fidelity-sensitive structure: locked.
        $locked = NodeCapabilities::forNode(
            ['id' => 'fm_header', 'kind' => 'widget', 'type' => 'foodmart_header'],
            ['lockedStructure' => true]
        );
        $this->assertTrue($locked['lockedStructure']);
        $this->assertFalse($locked['canMove']);
        $this->assertFalse($locked['canDuplicate']);
        $this->assertFalse($locked['canDelete']);
        $this->assertFalse($locked['canHaveChildren']);
        // Structure locked ≠ content frozen:
        $this->assertTrue($locked['canEdit']);

        // Ordinary Canvas-created structure stays flexible.
        $free = NodeCapabilities::forNode(['id' => 'w1', 'kind' => 'widget']);
        $this->assertFalse($free['lockedStructure']);
        $this->assertTrue($free['canMove']);
        $this->assertTrue($free['canEdit']);
    }

    public function test_capability_constraints_are_explicit_not_theme_assumed(): void
    {
        // Any widget may accept a container child ONLY via explicit trusted
        // metadata (capability-driven placement, never theme assumptions).
        $custom = NodeCapabilities::forNode(
            ['id' => 'w_custom', 'kind' => 'container'],
            ['allowedChildKinds' => ['widget', 'row'], 'maxChildren' => 3]
        );
        $this->assertTrue(NodeCapabilities::acceptsChild($custom, 'widget'));
        $this->assertTrue(NodeCapabilities::acceptsChild($custom, 'row'));
        $this->assertFalse(NodeCapabilities::acceptsChild($custom, 'column'));
        $this->assertSame(3, $custom['maxChildren']);
    }

    public function test_move_validation_prevents_cycles_and_self_parent(): void
    {
        $tree = ['id' => 'page', 'kind' => 'page', 'children' => [
            ['id' => 's1', 'kind' => 'section', 'type' => 'section', 'children' => [
                ['id' => 'c1', 'kind' => 'container', 'type' => 'container', 'children' => [
                    ['id' => 'w1', 'kind' => 'widget', 'type' => 'heading'],
                ]],
            ]],
            ['id' => 's2', 'kind' => 'section', 'type' => 'section', 'children' => []],
        ]];

        // Legal: w1 → s2.
        $this->assertTrue(NodeCapabilities::canMoveTo($tree, [0, 0, 0], [1]));
        // Self-parent: s1 → s1.
        $this->assertFalse(NodeCapabilities::canMoveTo($tree, [0], [0]));
        // Into own subtree: s1 → c1 (inside s1).
        $this->assertFalse(NodeCapabilities::canMoveTo($tree, [0], [0, 0]));
        // Widgets cannot accept children: w1 → w1.
        $this->assertFalse(NodeCapabilities::canMoveTo($tree, [0, 0, 0], [0, 0, 0]));
        // Unknown target path.
        $this->assertFalse(NodeCapabilities::canMoveTo($tree, [0, 0, 0], [7]));
    }

    public function test_max_children_constraint_enforced_on_move(): void
    {
        $tree = ['id' => 'page', 'kind' => 'page', 'children' => [
            ['id' => 's1', 'kind' => 'section', 'type' => 'section', 'children' => [
                ['id' => 'w1', 'kind' => 'widget', 'type' => 'heading'],
            ]],
            ['id' => 'w2', 'kind' => 'widget', 'type' => 'button'],
        ]];
        // Trusted maxChildren applies ONLY to s1 (the node it was declared
        // for) — capability metadata is per-node, never global.
        $capsFor = fn (array $node) => NodeCapabilities::forNode(
            $node,
            $node['id'] === 's1' ? ['maxChildren' => 1] : []
        );

        // s1 is already at max: moving w2 in must be rejected.
        $this->assertFalse(NodeCapabilities::canMoveTo($tree, [1], [0], $capsFor));
        // Reordering w1 within s1 (same parent) is unaffected by the max —
        // the move adds no child.
        $this->assertTrue(NodeCapabilities::canMoveTo($tree, [0, 0], [0], $capsFor));
        // Moving w1 out of s1 to the page root is likewise unaffected: the
        // maxChildren constraint binds the TARGET parent, never the source.
        $this->assertTrue(NodeCapabilities::canMoveTo($tree, [0, 0], [], $capsFor));
    }

    // ────────────────────────────────────────────────────────────────────
    // Legacy document compatibility (STEP 3: no destructive normalization)
    // ────────────────────────────────────────────────────────────────────

    public function test_adapter_is_idempotent_on_adapted_trees(): void
    {
        $legacy = [['id' => 'blk_1', 'type' => 'section', 'props' => ['padding' => 'md'], 'children' => [
            ['id' => 'blk_2', 'type' => 'heading'],
        ]]];

        $tree = LayoutContract::fromLegacyBlocks($legacy);
        $back = LayoutContract::toLegacyBlocks($tree);
        $tree2 = LayoutContract::fromLegacyBlocks($back);

        $this->assertSame($legacy, $back);
        $this->assertSame($tree, $tree2);
    }
}
