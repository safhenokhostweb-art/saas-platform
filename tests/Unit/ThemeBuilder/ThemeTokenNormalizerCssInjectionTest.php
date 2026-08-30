<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;
use Tests\TestCase;

/**
 * Phase 3C — renderCssBlock() is the single canonical convergence point for
 * every consumer (Builder preview, customer runtime, SaaS preview) and is
 * embedded directly inside an unescaped `<style>{!! ... !!}</style>` block.
 * Before this phase, neither a Theme's own token values (primary_color,
 * font_family, ...) nor its authored custom_css were sanitized before being
 * interpolated there, so a crafted value could break out of the `<style>`
 * tag entirely (`</style><script>...`) and execute for every visitor of the
 * live customer site — not just admins.
 */
class ThemeTokenNormalizerCssInjectionTest extends TestCase
{
    private function n(): ThemeTokenNormalizer
    {
        return new ThemeTokenNormalizer();
    }

    public function test_custom_css_cannot_break_out_of_the_style_tag(): void
    {
        $css = $this->n()->renderCssBlock([
            'custom_css' => '} </style><script>alert(document.cookie)</script><style> body{',
        ]);

        $this->assertStringNotContainsString('</style>', $css);
        $this->assertStringNotContainsString('<script>', $css);
    }

    public function test_custom_css_legitimate_child_combinator_selector_survives(): void
    {
        // '>' has no HTML-breakout meaning and is legitimate real CSS
        // (child combinator) — only '<' is stripped, so this must survive.
        $css = $this->n()->renderCssBlock([
            'custom_css' => '.tb-nav > .tb-nav-item { color: red; }',
        ]);

        $this->assertStringContainsString('.tb-nav > .tb-nav-item { color: red; }', $css);
    }

    public function test_a_token_value_cannot_break_out_of_its_own_declaration_or_the_style_tag(): void
    {
        $css = $this->n()->renderCssBlock([
            'primary_color' => 'red; } </style><script>alert(1)</script> :root{--x:red',
        ]);

        $this->assertStringNotContainsString('</style>', $css);
        $this->assertStringNotContainsString('<script>', $css);
        // The declaration itself must still be well-formed CSS: no stray
        // extra "}" or ";" injected mid-value that would prematurely close
        // the :root rule or start a second bogus declaration.
        $this->assertMatchesRegularExpression('/--tb-primary: [^;{}<>]*;/', $css);
    }

    public function test_a_token_value_cannot_open_a_css_comment_to_swallow_surrounding_rules(): void
    {
        $css = $this->n()->renderCssBlock([
            'font_family' => 'Arial /* } :root { --tb-primary: black',
        ]);

        $this->assertStringNotContainsString('/*', $css);
    }

    public function test_ordinary_hex_color_and_font_family_values_are_unaffected(): void
    {
        $css = $this->n()->renderCssBlock([
            'primary_color' => '#3b82f6',
            'font_family' => 'Vazirmatn, Tahoma, sans-serif',
        ]);

        $this->assertStringContainsString('--tb-primary: #3b82f6;', $css);
        $this->assertStringContainsString('--tb-font-heading: Vazirmatn, Tahoma, sans-serif;', $css);
    }

    public function test_ordinary_custom_css_still_renders_verbatim(): void
    {
        $css = $this->n()->renderCssBlock([
            'custom_css' => '.tb-hero { padding: 4rem 0; background: linear-gradient(90deg, #fff, #000); }',
        ]);

        $this->assertStringContainsString('.tb-hero { padding: 4rem 0; background: linear-gradient(90deg, #fff, #000); }', $css);
    }

    public function test_dark_mode_override_values_are_also_sanitized(): void
    {
        $css = $this->n()->renderCssBlock([
            'dark_mode' => [
                'enabled' => true,
                'background' => '#000 } </style><script>alert(1)</script> [data-theme="dark"]{--x:red',
            ],
        ]);

        $this->assertStringNotContainsString('</style>', $css);
        $this->assertStringNotContainsString('<script>', $css);
    }
}
