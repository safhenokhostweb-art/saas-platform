<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use SaaSPlatform\ThemeRuntime\Widget\HeaderStyleVars;
use Tests\TestCase;

/**
 * ADR-082 step 2: the header layout lives in theme-runtime-header.css,
 * driven by --tb-hdr-* properties whose fallbacks are HeaderStyleVars'
 * defaults; the renderer prints no per-header <style>; both CSS views ship
 * the sheet; the per-header emitter only lets known, valid, non-default
 * values through. The pixel proof is tests/browser/header-pixel-parity.spec.ts.
 */
class HeaderStyleVarsTest extends TestCase
{
    /** @return array<int, array{string, string}> every var(--tb-hdr-*, fallback) in the sheet */
    private static function usages(string $css): array
    {
        $out = [];
        $offset = 0;
        while (($start = strpos($css, 'var(--tb-hdr-', $offset)) !== false) {
            $depth = 0;
            for ($i = $start + 3; $i < strlen($css); $i++) {
                if ($css[$i] === '(') $depth++;
                if ($css[$i] === ')' && --$depth === 0) break;
            }
            $inner = substr($css, $start + 4, $i - $start - 4);
            $comma = strpos($inner, ',');
            $out[] = $comma === false ? [trim($inner), ''] : [trim(substr($inner, 0, $comma)), trim(substr($inner, $comma + 1))];
            $offset = $start + 4;
        }
        return $out;
    }

    public function test_every_sheet_variable_has_the_registered_default_as_its_fallback(): void
    {
        $css = (string) file_get_contents(ThemeRuntimeServiceProvider::cssHeaderPath());
        // Step 4: the sticky shrink rules (theme-runtime-blocks.css) read two header values too.
        $blocks = (string) file_get_contents(ThemeRuntimeServiceProvider::cssBlocksPath());
        $blockUsages = array_filter(self::usages($blocks), fn ($u) => in_array(substr($u[0], strlen('--tb-hdr-')), HeaderStyleVars::BLOCK_SHEET_VARS, true));
        $this->assertCount(2, $blockUsages, 'shrink-h and logo-scale are read by the block sheet');
        $used = [];
        foreach ([...self::usages($css), ...$blockUsages] as [$property, $fallback]) {
            $name = substr($property, strlen('--tb-hdr-'));
            $this->assertArrayHasKey($name, HeaderStyleVars::VARS, "{$property} is not registered");
            // Step 3: a gated value may also be read without a fallback inside
            // its gated rule (data-tb-hdr-set guarantees it is defined there).
            $allowed = in_array($name, HeaderStyleVars::GATED, true) ? [(string) HeaderStyleVars::VARS[$name][1], ''] : [HeaderStyleVars::VARS[$name][1]];
            $this->assertContains($fallback, $allowed, "{$property} fallback");
            $used[$name] = true;
        }
        foreach (HeaderStyleVars::GATED as $name) {
            $this->assertArrayHasKey($name, HeaderStyleVars::VARS);
            $this->assertStringContainsString('[data-tb-hdr-set~="' . $name . '"]', $css, "{$name} has a gated rule");
        }
        // A rule with no fallback must be gated, or a header without the value would lose the property.
        foreach (HeaderStyleVars::VARS as $name => [, $default]) {
            if ($default === null) {
                $this->assertContains($name, HeaderStyleVars::GATED, "{$name} has no default, so it must be gated");
            }
        }
        $this->assertSame([], array_values(array_diff(array_keys(HeaderStyleVars::VARS), array_keys($used))), 'registered but unused');
        $this->assertGreaterThanOrEqual(60, count(HeaderStyleVars::VARS));
    }

    public function test_the_renderer_prints_no_header_style_and_the_views_ship_the_sheet(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h1', 'props' => ['logo_text' => 'برند', 'show_search' => true, 'search_mode' => 'icon']]);
        $this->assertStringStartsWith('<div id="tb-h1" class="tb-nav-header"', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('.tb-nav-header-root .', $html, 'no header CSS selector in the markup');
        $this->assertStringNotContainsString('@media', $html);

        $header = (string) file_get_contents(ThemeRuntimeServiceProvider::cssHeaderPath());
        $blocks = $this->app['view']->make('theme-runtime::css.blocks')->render();
        $this->assertStringStartsWith(trim((string) file_get_contents(ThemeRuntimeServiceProvider::cssBlocksPath())), trim($blocks));
        $this->assertStringEndsWith(trim($header), trim($blocks), 'the header rules come after every block rule, where the inline <style> was');
        $this->assertSame(trim($header), trim($this->app['view']->make('theme-runtime::css.header')->render()));

        // The rules kept their selectors (specificity) — spot checks of the moved block.
        $this->assertStringContainsString('.tb-nav-header-root .tb-nav-header-inner{display:flex;align-items:center;justify-content:space-between;direction:rtl;', $header);
        $this->assertStringContainsString('@media(min-width:1024px){.tb-nav-header--menu-center .tb-nav-header-root .tb-nav-header-inner{display:grid;', $header);
        $this->assertStringContainsString('.tb-nav-header-root .tb-nav-header-links{display:none;}', $header);
        $this->assertStringContainsString('@keyframes tb-glow-pulse{', $header);
    }

    public function test_the_emitter_lets_only_known_valid_non_default_values_through(): void
    {
        $this->assertSame('', HeaderStyleVars::style('tb-h1', []));
        $this->assertSame('', HeaderStyleVars::style('tb-h1', ['min-h' => '4.25rem']), 'a default emits nothing');
        $this->assertSame(
            '<style data-tb-hdr="tb-h1">#tb-h1{--tb-hdr-min-h:72px;--tb-hdr-logo-weight:600;--tb-hdr-drawer-bg:var(--tb-primary, #4f46e5);--tb-hdr-drawer-shadow:0px 4px 12px -2px rgba(0, 0, 0, 0.2)}</style>',
            HeaderStyleVars::style('tb-h1', [
                'drawer-shadow' => ['x' => 0, 'y' => 4, 'blur' => 12, 'spread' => -2, 'color' => 'rgba(0, 0, 0, 0.2)'],
                'logo-weight' => '600',
                'min-h' => 72,
                'drawer-bg' => 'var(--tb-primary, #4f46e5)',
            ])
        );
        $this->assertSame('--tb-hdr-max-w:60%', HeaderStyleVars::declarations(['max-w' => ['size' => 60, 'unit' => '%']]));

        foreach ([
            ['min-h', '4rem;} body{display:none'],
            ['min-h', 'calc(100vh)'],
            ['min-h', '12pt'],
            ['drawer-bg', 'red;background:url(x)'],
            ['drawer-bg', 'expression(alert(1))'],
            ['logo-weight', 650],
            ['glow-duration', '2s;x'],
            ['drawer-shadow', ['color' => 'red;}']],
            ['unknown-name', '10px'],
        ] as [$name, $value]) {
            $this->assertSame('', HeaderStyleVars::declarations([$name => $value]), "{$name} must be dropped");
        }

        $this->expectException(\InvalidArgumentException::class);
        HeaderStyleVars::style('tb-h1"><script>', ['min-h' => 72]);
    }

    // ── Step 3: Style-tab props → per-device values ───────────────────

    public function test_a_header_without_style_props_emits_nothing(): void
    {
        $this->assertSame(['style' => '', 'set' => [], 'classes' => []], HeaderStyleVars::forHeader('tb-h', ['logo_text' => 'x', 'pointer' => 'underline', 'pointer_animation' => 'grow', 'content_width' => 'boxed', 'border_bottom_width' => 0, 'backdrop_blur' => 0]));
        $html = (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h', 'props' => ['logo_text' => 'x']]);
        $this->assertStringStartsWith('<div id="tb-h" class="tb-nav-header" style=', $html);
        $this->assertStringNotContainsString('data-tb-hdr-set', $html);
    }

    public function test_each_device_writes_only_its_own_value_in_its_own_media_query(): void
    {
        $out = HeaderStyleVars::forHeader('tb-h', [
            'min_height' => 80, 'min_height_mobile' => 64,
            'padding' => ['unit' => 'px', 'block-start' => 10, 'block-end' => 12, 'inline-start' => 24, 'inline-end' => 20],
            'padding_mobile' => ['unit' => 'rem', 'inline-start' => 1],
            'menu_gap' => 20, 'menu_gap_tablet' => 12, 'menu_gap_mobile' => 4,
            'menu_item_padding_x_mobile' => 6,
            'menu_typography' => ['size' => 16, 'weight' => '700', 'fontToken' => 'heading'], 'menu_typography_mobile' => ['size' => 14],
        ]);
        $this->assertSame(
            '<style data-tb-hdr="tb-h">'
            . '#tb-h{--tb-hdr-min-h:80px;--tb-hdr-menu-gap:20px;--tb-hdr-pad-bs:10px;--tb-hdr-pad-be:12px;--tb-hdr-pad-is:24px;--tb-hdr-pad-ie:20px;--tb-hdr-menu-size:16px;--tb-hdr-menu-weight:700;--tb-hdr-menu-font:var(--tb-font-heading, var(--tb-font-body, inherit))}'
            . '@media(max-width:1023.98px){#tb-h{--tb-hdr-menu-gap:12px}}'
            . '@media(max-width:767.98px){#tb-h{--tb-hdr-min-h-m:64px;--tb-hdr-drawer-gap:4px;--tb-hdr-drawer-item-pad-inline:6px;--tb-hdr-pad-is-m:1rem;--tb-hdr-menu-size:14px}}'
            . '</style>',
            $out['style']
        );
        $this->assertSame(['menu-size', 'menu-weight', 'menu-font'], $out['set']);
    }

    public function test_states_effects_and_pointer_become_gated_values_and_classes(): void
    {
        $out = HeaderStyleVars::forHeader('tb-h', [
            'menu_color' => '#1e293b', 'menu_color_hover' => 'var(--tb-primary, #4f46e5)', 'menu_color_active' => '#e11d48',
            'menu_bg_hover' => 'rgba(0, 0, 0, 0.05)',
            'header_shadow' => ['x' => 0, 'y' => 4, 'blur' => 12, 'spread' => -2, 'color' => 'rgba(15, 23, 42, 0.12)'],
            'backdrop_blur' => 12, 'border_bottom_width' => 1, 'border_bottom_color' => '#e2e8f0',
            'pointer' => 'overline', 'pointer_animation' => 'fade', 'pointer_color' => '#e11d48', 'pointer_thickness' => 3,
            'content_width' => 'full',
        ]);
        $this->assertSame(['border-w', 'shadow', 'blur', 'menu-color', 'menu-color-hover', 'menu-color-active', 'menu-bg-hover', 'pointer-color', 'pointer-h'], $out['set']);
        $this->assertSame(['tb-nav-header--pointer-overline', 'tb-nav-header--pointer-anim-fade'], $out['classes']);
        foreach (['--tb-hdr-max-w:100%', '--tb-hdr-shadow:0px 4px 12px -2px rgba(15, 23, 42, 0.12)', '--tb-hdr-blur:12px', '--tb-hdr-border-w:1px', '--tb-hdr-border-c:#e2e8f0', '--tb-hdr-pointer-h:3px'] as $declaration) {
            $this->assertStringContainsString($declaration, $out['style']);
        }
        // The animation only applies to line pointers.
        $this->assertSame(['tb-nav-header--pointer-framed'], HeaderStyleVars::forHeader('tb-h', ['pointer' => 'framed', 'pointer_animation' => 'fade'])['classes']);
        $this->assertSame([], HeaderStyleVars::forHeader('tb-h', ['pointer' => 'bogus', 'pointer_animation' => 'spin'])['classes']);
    }

    public function test_invalid_style_values_are_dropped_and_never_gate_a_rule(): void
    {
        $out = HeaderStyleVars::forHeader('tb-h', [
            'menu_color' => 'red;}body{display:none', 'min_height' => '80px;x', 'menu_typography' => ['size' => 'big', 'fontToken' => 'Comic Sans', 'lineHeight' => 99],
            'header_shadow' => ['color' => 'url(x)'], 'pointer_thickness' => -3,
        ]);
        $this->assertSame(['style' => '', 'set' => [], 'classes' => []], $out);
    }

    public function test_the_renderer_puts_the_scoped_style_gates_and_pointer_on_the_header(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h', 'props' => ['logo_text' => 'x', 'menu_color' => '#1e293b', 'pointer' => 'double', 'min_height' => 90]]);
        $this->assertStringStartsWith('<style data-tb-hdr="tb-h">#tb-h{--tb-hdr-min-h:90px;--tb-hdr-menu-color:#1e293b}</style><div id="tb-h" class="tb-nav-header tb-nav-header--pointer-double" data-tb-hdr-set="menu-color" style=', $html);
        $this->assertStringContainsString('a.setAttribute("aria-current","page")', $html, 'the header marks its current-page item (Active state)');
    }

    // ── Step 4: behaviour and the phone drawer ────────────────────────

    public function test_scrolled_look_drawer_and_burger_become_values_and_classes(): void
    {
        $out = HeaderStyleVars::forHeader('tb-h', [
            'scrolled_bg_color' => '#0f172a', 'scrolled_text_color' => '#f8fafc', 'scrolled_shadow' => ['y' => 8, 'blur' => 20, 'color' => 'rgba(0, 0, 0, 0.3)'],
            'sticky_shrink_height' => 60, 'sticky_logo_scale' => 70,
            'drawer_bg' => '#111827', 'drawer_text' => '#f8fafc', 'drawer_width' => ['size' => 90, 'unit' => '%'], 'drawer_overlay_color' => 'rgba(0, 0, 0, 0.6)', 'drawer_divider_color' => '#334155',
            'burger_color' => '#e11d48', 'burger_size' => 28,
            'drawer_style' => 'side', 'drawer_side' => 'end',
        ]);
        foreach (['--tb-hdr-shrink-h:60px', '--tb-hdr-logo-scale:0.7', '--tb-hdr-drawer-bg:#111827', '--tb-hdr-drawer-text:#f8fafc', '--tb-hdr-drawer-w:90%', '--tb-hdr-backdrop:rgba(0, 0, 0, 0.6)', '--tb-hdr-drawer-divider:#334155', '--tb-hdr-burger-size:28px'] as $d) {
            $this->assertStringContainsString($d, $out['style']);
        }
        $this->assertSame(['scrolled-bg', 'scrolled-text', 'scrolled-shadow', 'burger-color', 'burger-size'], $out['set']);
        $this->assertSame(['tb-nav-header--drawer-end'], $out['classes']);
        $this->assertSame(['tb-nav-header--drawer-full'], HeaderStyleVars::forHeader('tb-h', ['drawer_style' => 'full', 'drawer_side' => 'end'])['classes'], 'the side only applies to a side drawer');
        $this->assertSame(['tb-nav-header--drawer-dropdown'], HeaderStyleVars::forHeader('tb-h', ['drawer_style' => 'dropdown'])['classes']);
        $this->assertSame('', HeaderStyleVars::forHeader('tb-h', ['sticky_logo_scale' => 250, 'drawer_style' => 'sideways'])['style'], 'out-of-range scale dropped');
    }

    public function test_the_renderer_writes_behaviour_attributes_only_when_they_differ_from_the_default(): void
    {
        $render = fn (array $p) => (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h', 'props' => ['logo_text' => 'x'] + $p]);

        $default = $render(['sticky' => true, 'sticky_shrink' => true, 'scroll_threshold' => 8, 'sticky_offset' => 0, 'sticky_devices' => 'all', 'burger_breakpoint' => 'mobile']);
        $this->assertStringContainsString('data-tb-header="sticky" data-tb-overlay-text="auto">', $default);
        $rootTag = substr($default, 0, (int) strpos($default, '>') + 1); // the script reads these names, so check the root tag only
        foreach (['data-tb-scroll-threshold', 'data-tb-sticky-offset', 'data-tb-sticky-on', 'data-tb-burger', 'noshrink'] as $absent) {
            $this->assertStringNotContainsString($absent, $rootTag);
        }

        $custom = $render(['sticky' => true, 'sticky_shrink' => false, 'scroll_threshold' => '120', 'sticky_offset' => 24, 'sticky_devices' => 'desktop_tablet', 'burger_breakpoint' => 'always']);
        $this->assertStringContainsString('data-tb-header="sticky noshrink" data-tb-overlay-text="auto" data-tb-scroll-threshold="120" data-tb-sticky-offset="24" data-tb-sticky-on="tablet" data-tb-burger="always">', $custom);

        $unsafe = $render(['sticky' => true, 'scroll_threshold' => '1"><script>', 'sticky_offset' => -5, 'sticky_devices' => 'phone"', 'burger_breakpoint' => 'x"']);
        $this->assertStringContainsString('data-tb-header="sticky" data-tb-overlay-text="auto">', $unsafe);
        $this->assertStringNotContainsString('<script>"', $unsafe);

        $notSticky = $render(['sticky' => false, 'sticky_offset' => 24, 'sticky_devices' => 'desktop', 'burger_breakpoint' => 'never']);
        $this->assertStringNotContainsString('data-tb-sticky', $notSticky, 'sticky options need sticky');
        $this->assertStringContainsString('data-tb-burger="never"', $notSticky, 'the burger breakpoint is independent');
        $this->assertStringContainsString('root.classList.toggle("tb-nav-header-root--drawer",drawer)', $notSticky);
    }
}
