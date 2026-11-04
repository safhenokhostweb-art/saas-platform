<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use Tests\TestCase;

/**
 * Phase 3D — Hero Motion vector motion-graphic hero block.
 * Covers registry membership, renderer output, SVG well-formedness,
 * the 4-phase animation class set and public-mode sanitization.
 */
class HeroMotionRenderTest extends TestCase
{
    private function render(array $props, string $id = 'hm1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => 'hero_motion', 'id' => $id, 'props' => $props]);
    }

    private function xpathFor(string $html): \DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $this->assertTrue($ok, 'hero_motion output must parse as HTML');
        return new \DOMXPath($dom);
    }

    public function test_registry_registers_hero_motion(): void
    {
        $this->assertTrue(\SaaSPlatform\ThemeRuntime\BlockRegistry::isCanonicalType('hero_motion'));
        $def = \SaaSPlatform\ThemeRuntime\BlockRegistry::all()['hero_motion'] ?? null;
        $this->assertIsArray($def);
        $this->assertSame('قهرمان موشن (Hero Motion)', $def['name']);
        $this->assertSame(\SaaSPlatform\ThemeRuntime\BlockRegistry::CATEGORY_LAYOUT, $def['category']);
        $this->assertFalse($def['is_container']);
    }

    public function test_renders_svg_with_16_by_9_viewbox(): void
    {
        $html = $this->render([]);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('viewBox="0 0 1600 900"', $html);
        $this->assertStringContainsString('direction:rtl', $html, 'hero motion must be RTL by construction');
        $this->assertStringContainsString('tb-hero-motion-wrap', $html);
        $this->assertStringContainsString('tb-hero-motion', $html);
    }

    public function test_renders_default_title_subtitle_and_cta(): void
    {
        $html = $this->render([]);
        $this->assertStringContainsString('طراحی سایت حرفه‌ای و اختصاصی در صفحه نخست', $html);
        $this->assertStringContainsString('مشاهده پلن‌ها و شروع', $html);
    }

    public function test_renders_custom_props_escaped(): void
    {
        $html = $this->render([
            'title' => 'تست <script>alert(1)</script> عنوان',
            'subtitle' => 'زیرعنوان سفارشی',
            'button_text' => 'دکمه تست',
            'button_url' => 'https://example.com',
            'brand_title' => 'نشان تست',
            'brand_sub' => 'زیر نشان',
            'motion_id' => 'hero_motion',
        ]);
        $this->assertStringContainsString('تست &lt;script&gt;alert(1)&lt;/script&gt; عنوان', $html);
        $this->assertStringContainsString('زیرعنوان سفارشی', $html);
        $this->assertStringContainsString('دکمه تست', $html);
        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringContainsString('نشان تست', $html);
        $this->assertStringContainsString('زیر نشان', $html);
        $this->assertStringContainsString('data-tb-motion-id="hero_motion"', $html);
    }

    public function test_zero_config_renders_when_motion_id_null(): void
    {
        $html = $this->render(['motion_id' => null]);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('viewBox="0 0 1600 900"', $html);
        $this->assertStringContainsString('hm-browser', $html);
        $this->assertStringContainsString('hm-title', $html);
        $this->assertStringContainsString('hm-cta', $html);
        $this->assertStringNotContainsString('data-tb-motion-id', $html);
    }

    public function test_emits_all_four_phase_animation_classes(): void
    {
        $html = $this->render([]);
        foreach (['hm-grid', 'hm-particle', 'hm-browser', 'hm-keyword', 'hm-title', 'hm-shield', 'hm-check', 'hm-plan', 'hm-brand', 'hm-cta'] as $cls) {
            $this->assertStringContainsString('class="' . $cls, $html, "missing animation class {$cls}");
        }
    }

    public function test_public_mode_strips_editor_attributes_and_keeps_runtime_structure(): void
    {
        $renderer = new BlockRenderer();
        $renderer->setContext(['editor_mode' => true, 'mode' => BlockRenderer::MODE_PUBLIC]);
        $html = $renderer->renderBlock(['type' => 'hero_motion', 'id' => 'hm-public', 'props' => ['title' => 'عنوان عمومی']]);
        $this->assertStringNotContainsString('data-tb-edit', $html);
        $this->assertStringNotContainsString('data-tb-inline-type', $html);
        $this->assertStringContainsString('data-tb-block-id="hm-public"', $html);
        $this->assertStringContainsString('data-tb-block-type="hero_motion"', $html);
        $this->assertStringContainsString('عنوان عمومی', $html);
    }

    public function test_svg_is_well_formed_xml(): void
    {
        $html = $this->render([]);
        $start = strpos($html, '<svg');
        $end = strpos($html, '</svg>', $start) + strlen('</svg>');
        $this->assertFalse($start === false, 'hero_motion must render an inline <svg>');
        $this->assertFalse($end === strlen('</svg>') - 1, 'hero_motion must close </svg>');
        $svgXml = substr($html, $start, $end - $start);
        $xml = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $xml->loadXML('<?xml version="1.0" encoding="UTF-8"?><root>' . $svgXml . '</root>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $this->assertTrue($ok, 'the inline <svg> must be well-formed XML: ' . implode(' | ', libxml_get_errors()));
    }

    public function test_applies_configured_duration_and_height(): void
    {
        $html = $this->render(['duration' => 14, 'min_height' => 720]);
        $this->assertStringContainsString('--hm-duration:14s', $html);
        $this->assertStringContainsString('--hm-height:720px', $html);
        $this->assertStringContainsString('min-height:720px', $html);
    }
}
