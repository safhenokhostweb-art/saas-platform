<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use DOMXPath;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

class ScrollToTopWidgetTest extends TestCase
{
    private function xpathFor(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function render(array $props = [], string $id = 'stt1', bool $editorMode = false): string
    {
        $renderer = new BlockRenderer();
        if ($editorMode) {
            $renderer->setContext(['editor_mode' => true]);
        }
        return $renderer->renderBlock(['type' => 'scroll_to_top', 'id' => $id, 'props' => $props]);
    }

    public function test_scroll_to_top_is_registered_with_comprehensive_schema(): void
    {
        $all = BlockRegistry::all();
        $this->assertArrayHasKey('scroll_to_top', $all);

        $def = $all['scroll_to_top'];
        $this->assertSame(BlockRegistry::CATEGORY_UTILITY, $def['category']);
        $this->assertSame('arrow-up', $def['icon']);
        $this->assertSame(BlockRegistry::EMPTY_POLICY_OPTIONAL, BlockRegistry::emptyOutputPolicy('scroll_to_top'));

        $schema = $def['schema'];
        $this->assertArrayHasKey('icon_type', $schema);
        $this->assertArrayHasKey('icon', $schema);
        $this->assertArrayHasKey('custom_icon_class', $schema);
        $this->assertArrayHasKey('custom_icon_image', $schema);
        $this->assertArrayHasKey('show_label', $schema);
        $this->assertArrayHasKey('label', $schema);
        $this->assertArrayHasKey('orientation', $schema);
        $this->assertArrayHasKey('position', $schema);
        $this->assertArrayHasKey('offset_x', $schema);
        $this->assertArrayHasKey('offset_y', $schema);
        $this->assertArrayHasKey('size', $schema);
        $this->assertArrayHasKey('border_radius', $schema);
        $this->assertArrayHasKey('scroll_threshold', $schema);
        $this->assertArrayHasKey('z_index', $schema);
        $this->assertArrayHasKey('bg_color', $schema);
        $this->assertArrayHasKey('text_color', $schema);
        $this->assertArrayHasKey('hover_bg_color', $schema);
        $this->assertArrayHasKey('hover_text_color', $schema);
        $this->assertArrayHasKey('glow_effect', $schema);
        $this->assertArrayHasKey('glow_color', $schema);
        $this->assertArrayHasKey('hover_lift', $schema);
    }

    public function test_scroll_to_top_renders_with_defaults(): void
    {
        $html = $this->render();
        $xpath = $this->xpathFor($html);

        $btn = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " tb-scroll-to-top ")]');
        $this->assertSame(1, $btn->length, 'Button must exist with class tb-scroll-to-top');

        $button = $btn->item(0);
        $this->assertTrue(str_contains($button->getAttribute('class'), 'tb-scroll-to-top--bottom-right'));
        $this->assertTrue(str_contains($button->getAttribute('class'), 'tb-scroll-to-top--md'));
        $this->assertTrue(str_contains($button->getAttribute('class'), 'tb-scroll-to-top--glow'));
        $this->assertTrue(str_contains($button->getAttribute('class'), 'tb-scroll-to-top--lift'));

        $style = $button->getAttribute('style');
        $this->assertTrue(str_contains($style, '--tb-stt-bg:'));
        $this->assertTrue(str_contains($style, '--tb-stt-color:'));
        $this->assertTrue(str_contains($style, '--tb-stt-glow:'));
        $this->assertTrue(str_contains($style, '--tb-stt-offset-x:24px'));
        $this->assertTrue(str_contains($style, '--tb-stt-offset-y:24px'));

        $this->assertSame('300', $button->getAttribute('data-tb-threshold'));
        $this->assertTrue(str_contains($html, 'data-tb-block-asset="scroll_to_top"'));
    }

    public function test_scroll_to_top_custom_label_and_orientation(): void
    {
        $html = $this->render([
            'show_label' => true,
            'label' => 'برو به بالا',
            'orientation' => 'vertical',
            'position' => 'bottom-left',
            'size' => 'lg',
        ]);
        $xpath = $this->xpathFor($html);

        $btn = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " tb-scroll-to-top ")]')->item(0);
        $this->assertTrue(str_contains($btn->getAttribute('class'), 'tb-scroll-to-top--vertical'));
        $this->assertTrue(str_contains($btn->getAttribute('class'), 'tb-scroll-to-top--bottom-left'));
        $this->assertTrue(str_contains($btn->getAttribute('class'), 'tb-scroll-to-top--lg'));

        $labels = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " tb-stt-label ")]');
        $this->assertSame(1, $labels->length);
        $this->assertSame('برو به بالا', trim($labels->item(0)->textContent));
    }

    public function test_scroll_to_top_custom_icon_types(): void
    {
        // Custom fontawesome icon
        $faHtml = $this->render([
            'icon_type' => 'custom_class',
            'custom_icon_class' => 'fas fa-arrow-up',
        ]);
        $this->assertTrue(str_contains($faHtml, 'fas fa-arrow-up'));

        // Custom uploaded image
        $imgHtml = $this->render([
            'icon_type' => 'image',
            'custom_icon_image' => 'https://example.com/arrow.png',
        ]);
        $this->assertTrue(str_contains($imgHtml, 'src="https://example.com/arrow.png"'));
        $this->assertTrue(str_contains($imgHtml, 'class="tb-stt-custom-img"'));
    }

    public function test_scroll_to_top_editor_mode_is_marked(): void
    {
        $html = $this->render([], 'editor-block', true);
        $this->assertTrue(str_contains($html, 'is-editor-mode'));
    }

    public function test_scroll_to_top_is_registered_in_palette_utility_category(): void
    {
        $byCategory = BlockRegistry::byCategory();
        $this->assertArrayHasKey('utility', $byCategory);
        $this->assertArrayHasKey('scroll_to_top', $byCategory['utility']);

        $def = $byCategory['utility']['scroll_to_top'];
        $this->assertStringContainsString('دکمه هدایت به بالا', $def['name']);
        $this->assertSame('arrow-up', $def['icon']);
        $this->assertNotEmpty($def['description'] ?? null);
        $this->assertContains('scroll', $def['tags'] ?? []);
    }
}
