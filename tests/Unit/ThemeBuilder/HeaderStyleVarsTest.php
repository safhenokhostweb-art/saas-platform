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
        $used = [];
        foreach (self::usages($css) as [$property, $fallback]) {
            $name = substr($property, strlen('--tb-hdr-'));
            $this->assertArrayHasKey($name, HeaderStyleVars::VARS, "{$property} is not registered");
            $this->assertSame(HeaderStyleVars::VARS[$name][1], $fallback, "{$property} fallback");
            $used[$name] = true;
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
}
