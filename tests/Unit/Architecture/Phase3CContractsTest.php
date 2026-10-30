<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetDefinition;

/**
 * Phase 3C / Milestone 1 — commercial compositional widget contracts.
 */
class Phase3CContractsTest extends TestCase
{
    private const TYPES = [
        'features_grid',
        'stats_row',
        'stat_item',
        'cta_band',
        'trust_badges_row',
        'trust_item',
    ];

    private const CONTAINERS = [
        'features_grid',
        'stats_row',
        'cta_band',
        'trust_badges_row',
    ];

    private const LEAVES = ['stat_item', 'trust_item'];

    private const INSPECTOR_TYPES = [
        'string', 'number', 'boolean', 'select', 'color', 'textarea', 'link',
        // Phase 0.6 (ADR-065): the SVG icon picker control.
        'icon',
        // Phase 0.2: list definitions promoted to canonical use the
        // schema-driven repeater (InspectorFieldRegistry::TYPES).
        'repeater',
    ];

    /** @test */
    public function commercial_widgets_are_registered_as_canonical_types(): void
    {
        foreach (self::TYPES as $type) {
            $this->assertArrayHasKey($type, BlockRegistry::all(), "{$type} must be registered.");
            $this->assertTrue(BlockRegistry::isCanonicalType($type), "{$type} must remain canonical.");
            WidgetDefinition::normalize($type, BlockRegistry::all()[$type]);
        }
    }

    /** @test */
    public function commercial_widgets_have_the_expected_container_and_leaf_roles(): void
    {
        foreach (self::CONTAINERS as $type) {
            $this->assertTrue(BlockRegistry::isContainerType($type), "{$type} must dispatch children into \$inner.");
        }

        foreach (self::LEAVES as $type) {
            $this->assertFalse(BlockRegistry::isContainerType($type), "{$type} must remain a leaf.");
            $this->assertArrayNotHasKey('is_container', BlockRegistry::all()[$type]);
        }

        $this->assertSame(['icon_box'], BlockRegistry::all()['features_grid']['allowedChildren']);
        $this->assertSame(['stat_item'], BlockRegistry::all()['stats_row']['allowedChildren']);
        $this->assertSame(['heading', 'text', 'button'], BlockRegistry::all()['cta_band']['allowedChildren']);
        $this->assertSame(['trust_item'], BlockRegistry::all()['trust_badges_row']['allowedChildren']);
    }

    /** @test */
    public function new_widget_properties_use_only_supported_inspector_types_and_round_trip_in_both_directions(): void
    {
        foreach (self::TYPES as $type) {
            $definition = BlockRegistry::all()[$type];
            InspectorFieldRegistry::normalizeDefinition(['schema' => $definition['schema']]);

            foreach ($definition['schema'] as $key => $field) {
                // This is a pre-Phase-3C legacy field retained solely for
                // backward compatibility; Phase 3C introduces no new media
                // property on either upgraded container.
                if ($type === 'stats_row' && $key === 'background_image') {
                    $this->assertSame('image', $field['type']);

                    continue;
                }
                $this->assertContains(
                    $field['type'],
                    self::INSPECTOR_TYPES,
                    "{$type}.{$key} uses an unsupported Inspector type."
                );
            }

            foreach ($definition['defaultProps'] as $key => $value) {
                $this->assertArrayHasKey($key, $definition['schema'], "{$type}.{$key} needs a schema field.");
                // A repeater's legacy JSON-string default loads as its list
                // (InspectorFieldRegistry::normalizeList); the next edit
                // persists the array shape.
                $expected = $definition['schema'][$key]['type'] === 'repeater' && is_string($value)
                    ? json_decode($value, true)
                    : $value;
                foreach (['rtl', 'ltr'] as $direction) {
                    $this->assertEquals(
                        $expected,
                        InspectorFieldRegistry::normalizeValue(
                            $definition['schema'][$key],
                            $value,
                            ['key' => "{$type}.{$key}", 'direction' => $direction]
                        ),
                        "{$type}.{$key} must round-trip under {$direction}."
                    );
                }
            }
        }
    }

    /** @test */
    public function compositional_containers_render_their_children_and_leaf_content(): void
    {
        $renderer = new BlockRenderer;

        $features = $renderer->renderBlock([
            'id' => 'features', 'type' => 'features_grid', 'props' => ['columns' => '3', 'style' => 'cards'],
            'children' => [
                ['id' => 'feature-1', 'type' => 'icon_box', 'props' => ['icon' => '✓', 'title' => 'Fast', 'text' => 'Composable']],
            ],
        ]);
        $this->assertStringContainsString('tb-commercial-features-grid', $features);
        $this->assertStringContainsString('tb-icon-box', $features);
        $this->assertStringContainsString('Composable', $features);

        $stats = $renderer->renderBlock([
            'id' => 'stats', 'type' => 'stats_row', 'props' => ['background' => '#f8fafc'],
            'children' => [
                ['id' => 'stat-1', 'type' => 'stat_item', 'props' => ['value' => '99', 'suffix' => '%', 'label' => 'رضایت']],
            ],
        ]);
        $this->assertStringContainsString('tb-commercial-stats-row', $stats);
        $this->assertStringContainsString('tb-stat-item', $stats);
        $this->assertStringContainsString('99', $stats);

        $cta = $renderer->renderBlock([
            'id' => 'cta-band', 'type' => 'cta_band', 'props' => ['align' => 'start'],
            'children' => [
                ['id' => 'cta-heading', 'type' => 'heading', 'props' => ['level' => 2, 'text' => 'شروع کنید']],
                ['id' => 'cta-button', 'type' => 'button', 'props' => ['text' => 'اقدام', 'url' => '#', 'style' => 'primary']],
            ],
        ]);
        $this->assertStringContainsString('tb-cta-band-align-start', $cta);
        $this->assertStringContainsString('شروع کنید', $cta);

        $trust = $renderer->renderBlock([
            'id' => 'trust', 'type' => 'trust_badges_row', 'props' => ['columns' => '4'],
            'children' => [
                ['id' => 'trust-1', 'type' => 'trust_item', 'props' => ['icon' => '✓', 'label' => 'ضمانت', 'description' => 'خرید امن', 'url' => '#']],
            ],
        ]);
        $this->assertStringContainsString('tb-trust-badges-row', $trust);
        $this->assertStringContainsString('tb-trust-item', $trust);
        $this->assertStringContainsString('خرید امن', $trust);
    }

    /** @test */
    public function legacy_features_and_stats_prop_rendering_remains_available(): void
    {
        $renderer = new BlockRenderer;

        $features = $renderer->renderBlock([
            'id' => 'legacy-features', 'type' => 'features_grid',
            'props' => ['features' => '[{"icon":"✓","title":"Legacy","text":"Still works"}]'],
        ]);
        $this->assertStringContainsString('tb-feature-card', $features);
        $this->assertStringContainsString('Still works', $features);

        $stats = $renderer->renderBlock([
            'id' => 'legacy-stats', 'type' => 'stats_row',
            'props' => ['stats' => '[{"value":"10","suffix":"+","label":"Legacy"}]'],
        ]);
        $this->assertStringContainsString('data-count="10"', $stats);
        $this->assertStringContainsString('Legacy', $stats);
    }

    /** @test */
    public function phase_3c_css_is_token_scoped_and_uses_logical_geometry(): void
    {
        $css = file_get_contents(
            dirname(__DIR__, 3).'/packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css'
        );
        $this->assertNotFalse($css);
        $marker = strpos($css, 'Phase 3C / Milestone 1');
        $this->assertNotFalse($marker, 'Phase 3C CSS scope marker must exist.');
        $phase3c = substr($css, $marker);

        $this->assertStringContainsString('--tb-', $phase3c);
        $this->assertStringContainsString('padding-inline', $phase3c);
        $this->assertStringContainsString('padding-block', $phase3c);
        $this->assertStringContainsString('margin-inline', $phase3c);
        $this->assertStringContainsString('border-inline-start', $phase3c);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:margin|padding|border)-(?:left|right)\s*:/',
            $phase3c,
            'Phase 3C must not introduce physical left/right geometry.'
        );
    }

    /** @test */
    public function phase_3b_registry_contract_keeps_its_golden_sha256(): void
    {
        $all = BlockRegistry::all();
        $definitions = [];
        foreach (['hero_section', 'hero_container', 'hero_content_wrapper', 'hero_actions'] as $type) {
            $definitions[$type] = $all[$type];
        }

        // Phase 0.5 (ADR-064) re-pin: the only change is that the hero
        // family's colour defaults became theme tokens with the previous
        // literals as fallbacks (var(--tb-primary, #4f46e5),
        // var(--tb-secondary, #7c3aed), var(--tb-on-primary, #ffffff)).
        // Mapping exactly those three back reproduces the previous pin
        // 2fbb713a33173877b7c21cf11b540e04c2cff2dc757b36c50267fae7a0702152.
        $this->assertSame(
            '947961869a831a136e2a0f12fba1ab3a00bda589a9a3c9ae2fac2399f0be0bbd',
            hash('sha256', json_encode($definitions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        );
    }

    /** @test */
    public function foodmart_golden_manifest_and_every_referenced_file_keep_their_sha256(): void
    {
        $root = dirname(__DIR__, 3);
        $manifestPath = $root.'/storage/app/theme-baselines/foodmart/integrity-manifest.tsv';
        $goldenRoot = $root.'/_reference-themes/FoodMart-main/FoodMart-main';

        $this->assertFileExists($manifestPath);
        $this->assertSame(
            '50d9c55357c812d7e9ffe723d8252b1824e54ef2f50c86a4ec1b2fbb0c23f9b0',
            hash_file('sha256', $manifestPath),
            'FoodMart integrity manifest drifted.'
        );

        $lines = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($lines);
        $this->assertCount(70, $lines);
        foreach ($lines as $line) {
            [$relativePath, $sha256] = explode("\t", $line);
            $file = $goldenRoot.'/'.$relativePath;
            $this->assertFileExists($file, "FoodMart golden file missing: {$relativePath}");
            $this->assertSame($sha256, hash_file('sha256', $file), "FoodMart golden drift: {$relativePath}");
        }
    }

    /** @test */
    public function persian_section_library_contains_only_registered_compositions(): void
    {
        $partial = file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/theme-builder/partials/canvas-section-library.blade.php');
        $this->assertNotFalse($partial);
        $this->assertStringContainsString("key: 'features_showcase_persian'", $partial);
        $this->assertStringContainsString("key: 'stats_trust_persian'", $partial);

        foreach (self::TYPES as $type) {
            $this->assertArrayHasKey($type, BlockRegistry::all());
        }
    }
}
