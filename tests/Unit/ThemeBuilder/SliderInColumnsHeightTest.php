<?php

namespace Tests\Unit\ThemeBuilder;

use PHPUnit\Framework\TestCase;

/**
 * 2026-11-07 (owner, Canvas Dev HUD): a full-width slider beside a 16:9
 * module:motion in a 2-cell `columns` stood about twice as tall as the
 * motion (614px vs 344px at 1280px). The hero min-height of
 * `.tb-slider-full .tb-slide` is sized from the viewport (48vw, 540px on
 * phones) and ignored the slider's aspect_ratio in a half-width cell.
 * In a cell the slide now follows aspect_ratio, so equal ratios give equal
 * heights. The browser check measured 344.3px for both at 1280px and
 * 192.9px for both at 375px.
 */
class SliderInColumnsHeightTest extends TestCase
{
    private function css(): string
    {
        $css = file_get_contents(__DIR__ . '/../../../packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css');
        $this->assertIsString($css);

        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** @return array<int, array{selector: string, body: string, at: int}> */
    private function rules(string $css, string $selectorNeedle): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_OFFSET_CAPTURE);
        $out = [];
        foreach ($m[1] as $i => [$selector, $at]) {
            if (str_contains($selector, $selectorNeedle)) {
                $out[] = ['selector' => trim($selector), 'body' => $m[2][$i][0], 'at' => $at];
            }
        }

        return $out;
    }

    public function test_a_slider_in_a_columns_or_layout_grid_cell_drops_the_viewport_hero_min_height(): void
    {
        $css = $this->css();
        $reset = array_values(array_filter(
            $this->rules($css, '.tb-slider-full .tb-slide'),
            fn (array $r): bool => str_contains($r['selector'], '.tb-columns .tb-slider-full .tb-slide')
        ));

        $this->assertCount(1, $reset, 'one in-cell reset rule');
        $this->assertStringContainsString('.tb-layout-grid .tb-slider-full .tb-slide', $reset[0]['selector']);
        $this->assertMatchesRegularExpression('/min-height:\s*0\s*;/', $reset[0]['body']);

        // The reset (0,3,0) must out-rank both viewport hero rules (0,2,0)
        // without !important, so neither may gain !important.
        foreach ($this->rules($css, '.tb-slider-full .tb-slide') as $rule) {
            if ($rule['selector'] === '.tb-slider-full .tb-slide') {
                $this->assertStringNotContainsString('!important', $rule['body']);
            }
        }
    }

    public function test_the_caption_scales_with_the_cell_and_clears_arrows_and_progress(): void
    {
        $css = $this->css();
        $this->assertSame(1, preg_match('/@container tb-slot \(max-width: 767\.98px\) \{\s*\.tb-slider-full \.tb-slide-caption \{([^}]*)\}(.*?)\n\}/s', $css, $m), 'slot-scoped slider block');

        $this->assertStringContainsString('padding: clamp(1rem, 5cqi, 2rem) 3.25rem 5.75rem !important;', $m[1]);
        $this->assertStringContainsString('align-self: flex-end;', $m[1]);
        $this->assertStringContainsString('.tb-slider-full .tb-slide-title { font-size: clamp(1.1rem, 5cqi, 2rem) !important; }', $m[2]);
        $this->assertStringContainsString('.tb-slider-full .tb-carousel-thumbs { display: none; }', $m[2]);

        // It must come after the viewport rules it overrides (same specificity).
        $this->assertGreaterThan(
            strpos($css, '.tb-slider-full .tb-slide-caption { width: min(50%,650px);'),
            strpos($css, $m[0])
        );
    }
}
