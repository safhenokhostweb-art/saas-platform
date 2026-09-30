<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Layout\DirectionProjector;
use SaaSPlatform\ThemeRuntime\Layout\DocumentValidator;
use SaaSPlatform\ThemeRuntime\Module\ModuleFieldContract;
use SaaSPlatform\ThemeRuntime\Query\SafeQueryDefinition;
use SaaSPlatform\ThemeRuntime\Section\SectionPackage;
use SaaSPlatform\ThemeRuntime\Widget\PresentationAdapter;
use SaaSPlatform\ThemeRuntime\Widget\WidgetAcceptanceContract;
use SaaSPlatform\ThemeRuntime\Widget\WidgetDefinition;

/**
 * Phase 3A — pins the new canonical contracts:
 * WidgetDefinition, SectionPackage, SafeQueryDefinition,
 * ModuleFieldContract, PresentationAdapter, plus the registry
 * category/acceptance-contract invariants they build on.
 */
class Phase3AContractsTest extends TestCase
{
    protected function tearDown(): void
    {
        PresentationAdapter::flush();
    }

    // ── WidgetDefinition ────────────────────────────────────────────────

    /** @test */
    public function widget_definition_normalizes_and_fails_closed(): void
    {
        $def = WidgetDefinition::normalize('hero_note', [
            'name' => 'یادداشت قهرمان',
            'category' => 'content',
            'capabilities' => ['tokenizable' => true, 'resizable' => false],
            'schema' => ['text' => ['type' => 'text', 'default' => '']],
        ]);
        $this->assertSame('content', $def['category']);
        $this->assertTrue($def['capabilities']['tokenizable']);
    }

    /** @test */
    public function widget_definition_rejects_unknown_category_and_unsafe_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WidgetDefinition::normalize('x', ['name' => 'X', 'category' => 'super_special']);
    }

    /** @test */
    public function widget_definition_rejects_non_slug_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WidgetDefinition::normalize('Bad Type!', ['name' => 'X', 'category' => 'content']);
    }

    /** @test */
    public function widget_definition_rejects_class_name_requires(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WidgetDefinition::normalize('x', [
            'name' => 'X', 'category' => 'module',
            'requires' => 'App\\Models\\Product',
        ]);
    }

    /** @test */
    public function widget_definition_rejects_unknown_capability(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WidgetDefinition::normalize('x', [
            'name' => 'X', 'category' => 'content',
            'capabilities' => ['executes_arbitrary_code' => true],
        ]);
    }

    /** @test */
    public function allowed_type_matching_is_exact_or_star(): void
    {
        $this->assertTrue(WidgetDefinition::allowsType(['*'], 'anything'));
        $this->assertTrue(WidgetDefinition::allowsType(['heading', 'text'], 'text'));
        $this->assertFalse(WidgetDefinition::allowsType(['heading'], 'text'));
    }

    // ── Registry categories & new basic types ──────────────────────────

    /** @test */
    public function registry_exposes_professional_category_vocabulary(): void
    {
        foreach (['basic', 'layout', 'content', 'media', 'commerce', 'blog', 'forms', 'navigation', 'social', 'marketing', 'dynamic', 'module', 'utility'] as $slug) {
            $this->assertContains($slug, WidgetDefinition::CATEGORIES);
        }
        // The registry's own canonical slugs stay intact.
        $this->assertSame('layout', BlockRegistry::CATEGORY_LAYOUT);
        $this->assertSame('ecommerce', BlockRegistry::CATEGORY_ECOMMERCE);
    }

    /** @test */
    public function new_basic_widgets_are_registered_and_renderable(): void
    {
        foreach (['spacer', 'rich_text', 'icon_box', 'image_box', 'badge', 'list'] as $type) {
            $def = BlockRegistry::all()[$type] ?? null;
            $this->assertNotNull($def, "Missing 3A basic widget: {$type}");
            $this->assertArrayHasKey('schema', $def);
        }
    }

    // ── SectionPackage ──────────────────────────────────────────────────

    private function heroPackage(): array
    {
        return [
            'schema_version' => 1,
            'type' => 'section',
            'name' => 'قهرمان',
            'direction' => 'rtl',
            'locale' => 'fa',
            'nodes' => [[
                'id' => 'orig_1', 'type' => 'section',
                'props' => ['padding' => 'md'],
                'children' => [
                    ['id' => 'orig_2', 'type' => 'heading', 'props' => ['text' => '<b>سلام</b><script>alert(1)</script>']],
                    ['id' => 'orig_3', 'type' => 'button', 'props' => ['text' => 'شروع', 'url' => 'https://example.com']],
                ],
            ]],
        ];
    }

    /** @test */
    public function section_import_regenerates_ids_and_sanitizes_props(): void
    {
        $result = SectionPackage::import($this->heroPackage(), array_keys(BlockRegistry::all()));
        $blocks = $result['blocks'];
        $this->assertCount(1, $blocks);
        $section = $blocks[0];
        $this->assertNotSame('orig_1', $section['id']);
        $this->assertStringStartsWith('blk_', $section['id']);
        $heading = $section['children'][0];
        $this->assertNotSame('orig_2', $heading['id']);
        $this->assertStringNotContainsString('<script>', $heading['props']['text']);
        $this->assertStringContainsString('سلام', $heading['props']['text']);
        // Envelope typed correctly; structurally valid.
        $this->assertSame('template_part', $result['envelope']['type']);
        $this->assertSame('rtl', $result['envelope']['direction']);
    }

    /** @test */
    public function section_import_rejects_unavailable_widget_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SectionPackage::import($this->heroPackage(), ['heading']); // section/button unavailable
    }

    /** @test */
    public function section_import_rejects_bad_version_and_type(): void
    {
        $pkg = $this->heroPackage();
        $pkg['schema_version'] = 99;
        try {
            SectionPackage::validate($pkg);
            $this->fail('version gate');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $pkg2 = $this->heroPackage();
        $pkg2['type'] = 'email';
        $this->expectException(\InvalidArgumentException::class);
        SectionPackage::validate($pkg2);
    }

    /** @test */
    public function section_import_rejects_unsafe_module_slugs(): void
    {
        $pkg = $this->heroPackage();
        $pkg['required_modules'] = ['App\\Models\\Product'];
        $this->expectException(\InvalidArgumentException::class);
        SectionPackage::validate($pkg);
    }

    /** @test */
    public function section_export_round_trips_shape(): void
    {
        $pkg = SectionPackage::export('header', 'هدر ساده', [[
            'id' => 'h1', 'type' => 'nav_header', 'props' => ['logo_text' => 'فروشگاه'],
        ]], ['direction' => 'rtl', 'locale' => 'fa']);
        $this->assertSame(1, $pkg['schema_version']);
        $this->assertSame('header', $pkg['type']);
        $result = SectionPackage::import($pkg, array_keys(BlockRegistry::all()));
        $this->assertSame('nav_header', $result['blocks'][0]['type']);
        $this->assertNotSame('h1', $result['blocks'][0]['id']);
        $this->assertSame('header', $result['envelope']['type']);
    }

    /** @test */
    public function one_representative_section_proves_fa_ar_en_directions(): void
    {
        // Same structural package, three direction/locale envelopes — the
        // architecture proves content may localize while structure stays
        // direction-agnostic (logical start/end semantics, no physical
        // left/right in the tree).
        $available = array_keys(BlockRegistry::all());
        foreach ([['fa', 'rtl'], ['ar', 'rtl'], ['en', 'ltr']] as [$locale, $direction]) {
            $pkg = $this->heroPackage();
            $pkg['locale'] = $locale;
            $pkg['direction'] = $direction;
            $result = SectionPackage::import($pkg, $available);
            $this->assertSame($direction, $result['envelope']['direction'], "{$locale} envelope direction");
            $this->assertSame($locale, $result['envelope']['locale']);
            $this->assertSame([], DocumentValidator::validateEnvelope($result['envelope'])['errors'] ?? ['sentinel']);
            // Projected HTML attributes carry the direction to the DOM.
            $attrs = DirectionProjector::attributes($result['envelope']);
            $this->assertSame($direction, $attrs['dir'] ?? null);
        }
    }

    // ── SafeQueryDefinition ─────────────────────────────────────────────

    /** @test */
    public function query_definition_normalizes_safe_values(): void
    {
        $q = SafeQueryDefinition::validate([
            'source' => 'product',
            'filters' => [['field' => 'price', 'operator' => '<=', 'value' => 1000000]],
            'order' => ['by' => 'created_at', 'direction' => 'desc'],
            'limit' => 8,
            'pagination' => 'offset',
        ]);
        $this->assertSame('product', $q['source']);
        $this->assertCount(1, $q['filters']);
        $this->assertSame('desc', $q['order']['direction']);
    }

    /** @test */
    public function query_definition_rejects_unsafe_fields_operators_sources(): void
    {
        try {
            SafeQueryDefinition::validate(['source' => 'App\\Models\\Product']);
            $this->fail('source gate');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        try {
            SafeQueryDefinition::validate(['source' => 'product', 'filters' => [['field' => 'password', 'operator' => '=', 'value' => 1]]]);
            $this->fail('field gate');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(\InvalidArgumentException::class);
        SafeQueryDefinition::validate(['source' => 'product', 'filters' => [['field' => 'price', 'operator' => 'RAW', 'value' => '1=1']]]);
    }

    /** @test */
    public function query_definition_rejects_out_of_bounds_paging(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SafeQueryDefinition::validate(['source' => 'product', 'limit' => 5000]);
    }

    // ── ModuleFieldContract ─────────────────────────────────────────────

    /** @test */
    public function module_field_paths_are_allowlisted(): void
    {
        $this->assertTrue(ModuleFieldContract::isValidFieldPath('product.price'));
        $this->assertTrue(ModuleFieldContract::isValidFieldPath('product.sale_price'));
        $this->assertTrue(ModuleFieldContract::isValidFieldPath('blog_post.cover'));
        $this->assertFalse(ModuleFieldContract::isValidFieldPath('product.password'));
        $this->assertFalse(ModuleFieldContract::isValidFieldPath('App\\Models\\Product.price'));
        $this->assertSame('number', ModuleFieldContract::fieldType('product.price'));
        $this->assertNull(ModuleFieldContract::fieldType('product.nope'));
    }

    /** @test */
    public function module_schema_normalizes_through_inspector_registry(): void
    {
        $schema = ModuleFieldContract::normalizeModuleSchema('product', [
            'name' => ['label' => 'نام'],
            'price' => ['label' => 'قیمت'],
        ]);
        $this->assertSame('text', $schema['name']['type']);
        $this->assertSame('number', $schema['price']['type']);
        $this->assertSame('product.name', $schema['name']['dynamic']['module'] . '.' . $schema['name']['dynamic']['field']);
    }

    // ── PresentationAdapter ─────────────────────────────────────────────

    /** @test */
    public function presentation_adapter_registers_and_resolves(): void
    {
        PresentationAdapter::register('heading', 'foodmart');
        $this->assertSame('foodmart', PresentationAdapter::presentationFor('heading'));
        $this->assertNull(PresentationAdapter::presentationFor('divider'));
        $this->assertSame(['heading' => 'foodmart'], PresentationAdapter::all());
    }

    /** @test */
    public function presentation_adapter_rejects_unsafe_identifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PresentationAdapter::register('heading', 'App\\Classes\\Evil');
    }

    // ── Widget acceptance contract helper (Step 5) ──────────────────────

    /** @test */
    public function acceptance_contract_matrix_covers_every_canonical_type(): void
    {
        // The acceptance contract governs CANONICAL definitions (the 3A
        // vocabulary). Host-registered types (foodmart_*/sleepshiman_*)
        // are governed by their own verbatim host contracts
        // (FoodMartThemeSeederTest and friends) and extension types by
        // their manifests — the registry's isCanonicalType() is the
        // authority for the split.
        $canonical = array_filter(
            BlockRegistry::all(),
            fn ($def, $type) => BlockRegistry::isCanonicalType((string) $type),
            ARRAY_FILTER_USE_BOTH
        );
        $report = WidgetAcceptanceContract::evaluate($canonical);
        $this->assertSame([], $report['missing'], 'Canonical types missing acceptance-critical definition keys');
        // Every canonical type must declare the pieces the editor pipeline needs.
        foreach ($report['types'] as $type => $row) {
            $this->assertArrayHasKey('category', $row);
            $this->assertArrayHasKey('schema', $row);
            $this->assertArrayHasKey('defaultProps', $row);
        }
    }
}
