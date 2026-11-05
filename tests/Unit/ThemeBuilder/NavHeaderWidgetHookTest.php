<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\BlockRenderer;
use Tests\TestCase;

/**
 * ADR-076: one block, one data-widget-type. The nav_header root keeps its
 * own ids for live selection (data-tb-block-id, data-canvas-block-id,
 * data-tb-block-type) and the sticky flags (data-tb-header), but the widget
 * hook is the block wrapper's alone. A second hook on the root made Inspect,
 * the Design HUD and the Sentinel suite see two widgets for one block.
 */
class NavHeaderWidgetHookTest extends TestCase
{
    private function hooks(string $html): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        $out = [];
        foreach ((new \DOMXPath($doc))->query('//*[@data-widget-type]') as $el) {
            $out[] = $el->nodeName . '.' . $el->getAttribute('class') . '=' . $el->getAttribute('data-widget-type');
        }
        return $out;
    }

    public static function modes(): array
    {
        return [
            'public' => [fn (BlockRenderer $r) => $r->setMode('public')],
            'editor' => [fn (BlockRenderer $r) => $r->setMode('editor')->setContext(['editor_mode' => true])],
        ];
    }

    /** @dataProvider modes */
    public function test_the_header_has_exactly_one_widget_hook_on_its_wrapper(\Closure $mode): void
    {
        $block = ['type' => 'nav_header', 'id' => 'hdr1', 'props' => ['logo_text' => 'برند', 'sticky' => true, 'sticky_mode' => 'smart']];
        $html = $mode(new BlockRenderer())->renderBlock($block);

        $hooks = $this->hooks($html);
        $this->assertCount(1, $hooks, implode(' | ', $hooks));
        $this->assertStringStartsWith('div.', $hooks[0]);
        $this->assertStringNotContainsString('tb-nav-header', $hooks[0], 'the hook is the wrapper, not the header root');

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        $root = (new \DOMXPath($doc))->query('//div[@id="tb-hdr1"]')->item(0);
        $this->assertNotNull($root);
        $this->assertSame('hdr1', $root->getAttribute('data-tb-block-id'), 'live selection keeps the root id');
        $this->assertSame('nav_header', $root->getAttribute('data-tb-block-type'));
        $this->assertFalse($root->hasAttribute('data-widget-type'));
    }
}
