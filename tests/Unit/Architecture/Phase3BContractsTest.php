<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetDefinition;

/**
 * Phase 3B — pins the professional Persian hero family:
 * hero_section / hero_container / hero_content_wrapper / hero_actions.
 *
 * Non-negotiables under test:
 *   - the legacy flat `hero` type stays byte-for-byte authoritative;
 *   - all four new types are canonical structural containers;
 *   - schemas use ONLY the 2B InspectorFieldRegistry vocabulary;
 *   - alignment stays logical (start/center/end) under both directions;
 *   - renderers escape every string/color prop and allowlist every enum;
 *   - the hero_pro_persian library package composes registered
 *     primitives only (no second title/button type, no parallel system).
 */
class Phase3BContractsTest extends TestCase
{
    private const HERO_TYPES = ['hero_section', 'hero_container', 'hero_content_wrapper', 'hero_actions'];

    // ── Registry ────────────────────────────────────────────────────────

    /** @test */
    public function hero_family_is_registered_as_canonical_types(): void
    {
        foreach (self::HERO_TYPES as $type) {
            $this->assertArrayHasKey($type, BlockRegistry::all(), "{$type} must be registered.");
            $this->assertTrue(BlockRegistry::isCanonicalType($type), "{$type} must be canonical (never host-registered).");
            $this->assertSame('layout', BlockRegistry::all()[$type]['category']);
        }
    }

    /** @test */
    public function all_four_hero_types_are_structural_containers(): void
    {
        foreach (self::HERO_TYPES as $type) {
            $this->assertTrue(BlockRegistry::isContainerType($type), "{$type} must declare is_container.");
        }
    }

    /** @test */
    public function legacy_flat_hero_definition_is_untouched(): void
    {
        $hero = BlockRegistry::all()['hero'];
        $this->assertSame('بخش قهرمان (Hero)', $hero['name']);
        $this->assertSame(
            ['title', 'subtitle', 'button_text', 'button_url', 'background', 'background_image', 'align'],
            array_keys($hero['schema'])
        );
        $this->assertArrayNotHasKey('is_container', $hero);
        $this->assertFalse(BlockRegistry::isContainerType('hero'));
        // Both coexist: the family is additive, never a rename or replacement.
        $this->assertTrue(BlockRegistry::isCanonicalType('hero'));
        $this->assertTrue(BlockRegistry::isCanonicalType('hero_section'));
    }

    /** @test */
    public function hero_family_passes_widget_definition_normalize(): void
    {
        foreach (self::HERO_TYPES as $type) {
            $def = WidgetDefinition::normalize($type, BlockRegistry::all()[$type]);
            $this->assertSame('layout', $def['category']);
            $this->assertNotSame('', $def['name']);
        }
    }

    /** @test */
    public function hero_actions_children_are_constrained_to_buttons(): void
    {
        $def = BlockRegistry::all()['hero_actions'];
        $this->assertSame(['button'], $def['allowedChildren']);
        $this->assertTrue(WidgetDefinition::allowsType(['button'], 'button'));
        $this->assertFalse(WidgetDefinition::allowsType(['button'], 'text'));
        $this->assertFalse(WidgetDefinition::allowsType(['button'], 'heading'));
        $this->assertFalse(WidgetDefinition::allowsType(['button'], 'hero_section'));
    }

    // ── Inspector compatibility (2B contract) ───────────────────────────

    /** @test */
    public function hero_schemas_use_only_the_canonical_inspector_vocabulary(): void
    {
        foreach (self::HERO_TYPES as $type) {
            foreach (BlockRegistry::all()[$type]['schema'] as $key => $field) {
                $this->assertIsArray($field, "{$type}.{$key} must be a named field object.");
                // Resolve through the real contract path: aliases (image →
                // media, etc.) fold inside normalizeField and the resolved
                // control must be a canonical InspectorFieldRegistry type.
                $normalized = InspectorFieldRegistry::normalizeField("{$type}.{$key}", $field);
                $this->assertContains(
                    $normalized['control'],
                    InspectorFieldRegistry::TYPES,
                    "{$type}.{$key} resolves to non-canonical inspector type '{$normalized['control']}'."
                );
            }
        }
    }

    /** @test */
    public function hero_schema_enums_are_pinned(): void
    {
        $s = BlockRegistry::all()['hero_section']['schema'];
        $this->assertSame(['color', 'gradient', 'image'], array_keys($s['background_type']['options']));
        $this->assertSame(['start', 'center', 'end'], array_keys($s['v_align']['options']));
        $this->assertSame(['narrow', 'normal', 'wide', 'full'], array_keys($s['content_width']['options']));
        // Logical-only alignment via the rendered select control (values
        // stay start/center/end — never physical left/right in storage).
        $this->assertSame('select', $s['text_align']['type']);
        $this->assertSame(['start', 'center', 'end'], array_keys($s['text_align']['options']));
        $this->assertSame('select', BlockRegistry::all()['hero_actions']['schema']['align']['type']);
        $this->assertSame(['button'], BlockRegistry::all()['hero_actions']['allowedChildren']);
    }

    /** @test */
    public function hero_selects_are_renderable_by_the_inspector_control_vocabulary(): void
    {
        // The Inspector's generic control loop (canvas.blade.php) renders
        // image/string/number/boolean/select/color/textarea/link. Every hero
        // schema field MUST resolve into that set or it would be invisible
        // (non-editable) in the panel.
        $renderable = ['image', 'string', 'number', 'boolean', 'select', 'color', 'textarea', 'link'];
        foreach (self::HERO_TYPES as $type) {
            foreach (BlockRegistry::all()[$type]['schema'] as $key => $field) {
                $this->assertContains(
                    $field['type'],
                    $renderable,
                    "{$type}.{$key} uses '{$field['type']}' which has no Inspector control template."
                );
            }
        }
    }

    /** @test */
    public function hero_default_props_round_trip_through_the_inspector_contract(): void
    {
        foreach (self::HERO_TYPES as $type) {
            $def = BlockRegistry::all()[$type];
            InspectorFieldRegistry::normalizeDefinition(['schema' => $def['schema']]);
            foreach ($def['defaultProps'] as $key => $value) {
                $field = $def['schema'][$key] ?? null;
                $this->assertIsArray($field, "{$type}.{$key} must have a schema field.");
                // Empty media is the legitimate "no selection yet" state; the
                // media contract governs non-empty references (see next test).
                if ($value === '' && ($field['type'] ?? '') === 'image') {
                    $this->assertSame('', $value);
                    continue;
                }
                foreach (['rtl', 'ltr'] as $direction) {
                    $normalized = InspectorFieldRegistry::normalizeValue(
                        $field,
                        $value,
                        ['key' => $type . '.' . $key, 'direction' => $direction]
                    );
                    $this->assertEquals($value, $normalized, "{$type}.{$key} must round-trip under {$direction}.");
                }
            }
        }
    }

    /** @test */
    public function hero_alignment_stays_logical_under_both_directions(): void
    {
        // The select allowlist is itself the logical-only guarantee: the only
        // values that normalize successfully are start/center/end, and the
        // LayoutContract alignment path resolves them per direction for
        // rendering (never storing physical left/right).
        $field = BlockRegistry::all()['hero_section']['schema']['text_align'];
        foreach (['rtl', 'ltr'] as $direction) {
            $this->assertSame(
                'start',
                InspectorFieldRegistry::normalizeValue($field, 'start', ['key' => 'text_align', 'direction' => $direction]),
                "text_align must stay logical under direction={$direction}."
            );
        }
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($field, 'left', ['key' => 'text_align', 'direction' => 'rtl']);
    }

    /** @test */
    public function hero_media_field_accepts_https_and_rejects_filesystem_paths(): void
    {
        $field = BlockRegistry::all()['hero_section']['schema']['bg_image'];
        $url = 'https://cdn.example.test/hero.webp';
        $this->assertSame($url, InspectorFieldRegistry::normalizeValue($field, $url, ['key' => 'bg_image']));
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($field, 'C:\\laragon\\www\\secret.png', ['key' => 'bg_image']);
    }

    /** @test */
    public function hero_select_rejects_unknown_values_fail_closed(): void
    {
        $field = BlockRegistry::all()['hero_section']['schema']['background_type'];
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($field, 'javascript:alert(1)', ['key' => 'background_type']);
    }

    // ── Rendering ───────────────────────────────────────────────────────

    /** @test */
    public function hero_family_renders_nested_composition(): void
    {
        $html = $this->renderHeroSample();
        $this->assertStringContainsString(
            '<section id="tb-h1" class="tb-hero-section tb-hero-align-start tb-hero-vcenter tb-hero-w-normal"',
            $html
        );
        $this->assertStringContainsString('min-height:520px;padding-block:96px;', $html);
        $this->assertStringContainsString('linear-gradient(115deg,#4f46e5,#7c3aed)', $html);
        $this->assertStringContainsString('class="tb-hero-container"', $html);
        $this->assertStringContainsString('class="tb-hero-content"', $html);
        $this->assertStringContainsString('class="tb-hero-actions tb-hero-actions-start"', $html);
        $this->assertStringContainsString('tb-badge tb-badge-accent', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('فروشگاه خود را حرفه‌ای بسازید', $html);
        $this->assertStringContainsString('tb-btn tb-btn-primary', $html);
        $this->assertStringContainsString('tb-btn tb-btn-secondary', $html);
        // Gradient background renders no scrim.
        $this->assertStringNotContainsString('tb-hero-overlay', $html);
    }

    /** @test */
    public function hero_scopes_its_custom_properties_without_touching_primitives(): void
    {
        $html = $this->renderHeroSample();
        $this->assertStringContainsString('--tb-hero-btn-radius:12px;', $html);
        $this->assertStringContainsString('--tb-hero-title-size:3.25rem;', $html);
        $this->assertStringContainsString('--tb-hero-title-color:#ffffff;', $html);
        $this->assertStringContainsString('max-width:1200px;', $html);
        $this->assertStringContainsString('max-width:640px;', $html);
    }

    /** @test */
    public function hero_overlay_only_appears_for_image_backgrounds(): void
    {
        $renderer = new BlockRenderer();
        $image = $renderer->renderBlock([
            'id' => 'i1', 'type' => 'hero_section',
            'props' => ['background_type' => 'image', 'bg_image' => 'https://cdn.example.test/bg.jpg', 'overlay_opacity' => 40],
        ]);
        $this->assertStringContainsString('background-image:url(https://cdn.example.test/bg.jpg)', $image);
        $this->assertStringContainsString('tb-hero-overlay', $image);
        $this->assertStringContainsString('opacity:0.4', $image);

        $flat = $renderer->renderBlock([
            'id' => 'c1', 'type' => 'hero_section',
            'props' => ['background_type' => 'color', 'bg_color' => '#0f172a'],
        ]);
        $this->assertStringContainsString('background-color:#0f172a', $flat);
        $this->assertStringNotContainsString('tb-hero-overlay', $flat);
    }

    /** @test */
    public function hero_renderers_fallback_safely_on_unknown_enums(): void
    {
        $html = (new BlockRenderer())->renderBlock([
            'id' => 'x1', 'type' => 'hero_section',
            'props' => [
                'background_type' => 'javascript:alert(1)',
                'text_align' => '<script>',
                'v_align' => 'bogus',
                'content_width' => 'bogus',
                'bg_color' => '" onmouseover="x',
            ],
        ]);
        $this->assertStringContainsString('tb-hero-align-start', $html);
        $this->assertStringContainsString('tb-hero-vcenter', $html);
        $this->assertStringContainsString('tb-hero-w-normal', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('"onmouseover="x', $html);
    }

    /** @test */
    public function hero_string_props_are_escaped(): void
    {
        $html = (new BlockRenderer())->renderBlock([
            'id' => 'e1', 'type' => 'hero_content_wrapper',
            'props' => ['title_color' => '"><script>alert(1)</script>', 'gap' => 20, 'max_width' => 640, 'title_size' => 3.25],
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    // ── Library package ─────────────────────────────────────────────────

    /** @test */
    public function hero_pro_persian_library_package_uses_only_registered_types(): void
    {
        foreach (['hero_section', 'hero_container', 'hero_content_wrapper', 'badge', 'heading', 'text', 'hero_actions', 'button'] as $type) {
            $this->assertArrayHasKey($type, BlockRegistry::all(), "{$type} used by the package must be registered.");
        }

        $partial = file_get_contents(dirname(__DIR__, 3) . '/resources/views/admin/theme-builder/partials/canvas-section-library.blade.php');
        $this->assertNotFalse($partial, 'Section library partial must exist.');
        $this->assertStringContainsString("key: 'hero_pro_persian'", $partial);
        $this->assertStringContainsString("W('hero_section'", $partial);
        $this->assertStringContainsString("W('hero_container'", $partial);
        $this->assertStringContainsString("W('hero_content_wrapper'", $partial);
        $this->assertStringContainsString("W('hero_actions'", $partial);
        // No parallel vocabulary: the title role is the canonical heading.
        $this->assertStringNotContainsString("W('hero_title'", $partial);
        // Every text string is a replaceable prop (badge/heading/text/button), never template HTML.
        $this->assertStringContainsString("text: 'فروشگاه خود را حرفه‌ای بسازید'", $partial);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function renderHeroSample(): string
    {
        return (new BlockRenderer())->renderBlock([
            'id' => 'h1', 'type' => 'hero_section',
            'props' => [
                'background_type' => 'gradient', 'min_height' => 520, 'padding_v' => 96,
                'text_align' => 'start', 'v_align' => 'center', 'content_width' => 'normal',
            ],
            'children' => [
                ['id' => 'h2', 'type' => 'hero_container', 'props' => [], 'children' => [
                    ['id' => 'h3', 'type' => 'hero_content_wrapper', 'props' => [], 'children' => [
                        ['id' => 'h4', 'type' => 'badge', 'props' => ['text' => 'نشان', 'style' => 'accent']],
                        ['id' => 'h5', 'type' => 'heading', 'props' => ['level' => 1, 'text' => 'فروشگاه خود را حرفه‌ای بسازید']],
                        ['id' => 'h6', 'type' => 'text', 'props' => ['content' => 'زیرعنوان آزمایشی']],
                        ['id' => 'h7', 'type' => 'hero_actions', 'props' => ['gap' => 16, 'radius' => 12, 'align' => 'start'], 'children' => [
                            ['id' => 'h8', 'type' => 'button', 'props' => ['text' => 'شروع کنید', 'url' => '#', 'style' => 'primary']],
                            ['id' => 'h9', 'type' => 'button', 'props' => ['text' => 'مشاهده دمو', 'url' => '#', 'style' => 'secondary']],
                        ]],
                    ]],
                ]],
            ],
        ]);
    }
}
