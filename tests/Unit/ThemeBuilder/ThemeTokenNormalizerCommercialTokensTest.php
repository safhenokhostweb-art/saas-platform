<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;
use Tests\TestCase;

/**
 * WU-C — the commercial tokens are opt-in. Every consumer reads them
 * as `var(--tb-<token>, <legacy literal>)`, so the zero-diff guarantee for
 * existing Themes rests on one property tested here: a Theme that does not
 * define a key gets no declaration for it at all.
 */
class ThemeTokenNormalizerCommercialTokensTest extends TestCase
{
    private function n(): ThemeTokenNormalizer
    {
        return new ThemeTokenNormalizer();
    }

    public function test_the_registry_holds_the_commercial_tokens_the_shared_sheet_reads(): void
    {
        $this->assertSame([
            'overlay' => '--tb-overlay',
            'backdrop' => '--tb-backdrop',
            'star' => '--tb-star',
            'border_strong' => '--tb-border-strong',
            'media_placeholder' => '--tb-media-placeholder',
            'media_bg' => '--tb-media-bg',
            'on_overlay' => '--tb-on-overlay',
            'primary_hover' => '--tb-primary-hover',
            'input_bg' => '--tb-input-bg',
        ], ThemeTokenNormalizer::COMMERCIAL_TOKENS);
    }

    public function test_every_registered_token_has_a_consumer_with_a_fallback(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css')
            . file_get_contents(__DIR__ . '/../../../packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php');

        foreach (ThemeTokenNormalizer::COMMERCIAL_TOKENS as $token) {
            $this->assertMatchesRegularExpression('/var\(' . preg_quote($token, '/') . ',\s*\S/', $source, "{$token} has no var() consumer with a fallback");
        }
    }

    public function test_no_commercial_token_is_emitted_when_the_theme_does_not_define_it(): void
    {
        $palettes = [
            [],
            ['primary_color' => '#e11d48', 'colors' => ['surface' => '#fff7ed', 'border' => '#fed7aa']],
            ['colors' => ['star' => '', 'overlay' => '   ', 'backdrop' => null, 'border_strong' => ['x'], 'media_placeholder' => false, 'primary_hover' => '', 'input_bg' => ' ']],
            ['dark_mode' => ['enabled' => true]],
        ];

        foreach ($palettes as $gs) {
            $css = $this->n()->renderCssBlock($gs);
            foreach (ThemeTokenNormalizer::COMMERCIAL_TOKENS as $token) {
                $this->assertStringNotContainsString($token . ':', $css, json_encode($gs));
            }
        }
    }

    public function test_each_defined_token_is_emitted_under_its_variable(): void
    {
        $vars = $this->n()->normalize(['colors' => [
            'overlay' => 'rgba(0,0,0,.4)',
            'backdrop' => 'rgba(2,6,23,.6)',
            'star' => '#b45309',
            'border_strong' => '#94a3b8',
            'media_placeholder' => 'linear-gradient(135deg,#fef3c7,#fde68a)',
            'media_bg' => '#0b1120',
            'on_overlay' => '#fef9c3',
        ]]);

        $this->assertSame('#0b1120', $vars['--tb-media-bg']);
        $this->assertSame('#fef9c3', $vars['--tb-on-overlay']);

        $this->assertSame('rgba(0,0,0,.4)', $vars['--tb-overlay']);
        $this->assertSame('rgba(2,6,23,.6)', $vars['--tb-backdrop']);
        $this->assertSame('#b45309', $vars['--tb-star']);
        $this->assertSame('#94a3b8', $vars['--tb-border-strong']);
        $this->assertSame('linear-gradient(135deg,#fef3c7,#fde68a)', $vars['--tb-media-placeholder']);
    }

    public function test_dark_mode_emits_only_explicit_overrides(): void
    {
        $css = $this->n()->renderCssBlock([
            'colors' => ['backdrop' => 'rgba(2,6,23,.6)'],
            'dark_mode' => ['enabled' => true, 'border_strong' => '#475569'],
        ]);
        preg_match('/\[data-theme="dark"\] \{(.*?)\n\}/s', $css, $m);
        $dark = $m[1] ?? '';

        $this->assertStringContainsString('--tb-border-strong: #475569;', $dark);
        // The light value cascades from :root; dark mode does not restate it.
        $this->assertStringNotContainsString('--tb-backdrop:', $dark);
        $this->assertStringContainsString('--tb-backdrop: rgba(2,6,23,.6);', $css);
    }

    public function test_a_commercial_token_value_cannot_break_out_of_the_style_tag(): void
    {
        $css = $this->n()->renderCssBlock([
            'colors' => ['media_placeholder' => 'red; } </style><script>alert(1)</script> :root{--x:red'],
            'dark_mode' => ['enabled' => true, 'overlay' => '#000 /* } </style><script>alert(1)</script>'],
        ]);

        $this->assertStringNotContainsString('</style>', $css);
        $this->assertStringNotContainsString('<script>', $css);
        $this->assertStringNotContainsString('/*', $css);
        $this->assertMatchesRegularExpression('/--tb-media-placeholder: [^;{}<>]*;/', $css);
    }
}
