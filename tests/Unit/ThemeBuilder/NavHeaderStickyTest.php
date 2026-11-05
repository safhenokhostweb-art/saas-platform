<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * nav_header sticky / hide-on-scroll / overlay: site-header.js initialises
 * `.tb-nav-header[data-tb-header]` only, so the flags must sit on that outer
 * element — on an inner element the header never becomes sticky.
 */
class NavHeaderStickyTest extends TestCase
{
    private const SCRIPT = 'packages/saas-platform/modules/theme-runtime/resources/js/blocks/site-header.js';

    /** @return array<string, string> attributes of the outer .tb-nav-header element */
    private function outerAttributes(array $props): array
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h1', 'props' => $props + ['logo_text' => 'برند']]);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        foreach ($doc->getElementsByTagName('div') as $div) {
            if (in_array('tb-nav-header', explode(' ', $div->getAttribute('class')), true)) {
                $attrs = [];
                foreach ($div->attributes as $a) {
                    $attrs[$a->name] = $a->value;
                }
                $flagged = (new \DOMXPath($doc))->query('//*[@data-tb-header]')->length;
                return $attrs + ['__html' => $html, '__flagged' => $flagged];
            }
        }
        $this->fail('no outer .tb-nav-header element rendered');
    }

    public function test_the_script_selects_the_outer_header_element(): void
    {
        $js = file_get_contents(base_path(self::SCRIPT));
        $this->assertStringContainsString("querySelectorAll('.tb-nav-header[data-tb-header]')", $js);
    }

    public function test_sticky_flags_are_on_the_outer_header_element(): void
    {
        $attrs = $this->outerAttributes(['sticky' => true, 'sticky_hide_on_scroll' => true]);

        $this->assertSame('sticky smart', $attrs['data-tb-header'] ?? null);
        $this->assertSame('auto', $attrs['data-tb-overlay-text'] ?? null);
        $this->assertSame(1, $attrs['__flagged'], 'flags sit on the outer element only');
        $this->assertStringContainsString('data-tb-block-asset="site_header"', $attrs['__html'], 'the sticky script ships with the header');
    }

    public function test_overlay_flag_and_text_colour_are_on_the_outer_header_element(): void
    {
        $attrs = $this->outerAttributes(['transparent_over_hero' => true, 'transparent_text_color' => '#0f172a']);

        $this->assertSame('overlay', $attrs['data-tb-header'] ?? null);
        $this->assertSame('#0f172a', $attrs['data-tb-overlay-text'] ?? null);
    }

    /**
     * With theme-owned chrome the block itself is the banner, so it carries
     * .tb-hdr-sticky. The desktop mega-menu anchor must not out-rank it: a
     * plain `.tb-nav-header { position: relative }` later in the sheet did,
     * and the header stopped sticking at >= 1024px.
     */
    public function test_the_desktop_mega_menu_anchor_cannot_override_sticky(): void
    {
        $css = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));

        $this->assertStringContainsString(':where(.tb-nav-header) { position: relative; }', $css);
        $this->assertDoesNotMatchRegularExpression('/(^|[\s,}])\.tb-nav-header\s*\{[^}]*\bposition\s*:/m', $css, 'no single-class position rule on .tb-nav-header');
        $this->assertStringContainsString('.tb-hdr-sticky { position: sticky; top: 0; z-index: 900;', $css);
    }

    public function test_a_non_sticky_header_carries_no_flags(): void
    {
        $attrs = $this->outerAttributes(['sticky' => false]);

        $this->assertArrayNotHasKey('data-tb-header', $attrs);
        $this->assertSame(0, $attrs['__flagged']);
    }
}
