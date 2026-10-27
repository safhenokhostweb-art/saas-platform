<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer;
use Tests\TestCase;

/**
 * Phase 0.5 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-064):
 * colours come from theme tokens with the old literal as fallback, author
 * colours go through one grammar (HtmlSanitizer::isCssColor), and the shared
 * renderers print prices, badges and counters in Persian (PersianNumber).
 */
class Phase05TokensAndNumbersTest extends TestCase
{
    private const CSS = 'packages/saas-platform/modules/theme-runtime/resources';

    private function render(string $type, array $props, array $context = []): string
    {
        $renderer = new BlockRenderer();
        if ($context) {
            $renderer->setContext($context);
        }
        return $renderer->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function colours(): array
    {
        return [
            'hex' => ['#0ea5e9', true], 'short hex' => ['#fff', true], 'hex alpha' => ['#0ea5e980', true],
            'rgba' => ['rgba(14, 165, 233, 0.25)', true], 'named' => ['white', true], 'transparent' => ['transparent', true],
            'token' => ['var(--tb-primary)', true], 'token with fallback' => ['var(--tb-primary, #4f46e5)', true],
            'token with alpha' => ['color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent)', true],
            'declaration break-out' => ['red;background:url(x)', false], 'quote' => ['#fff" onclick="x', false],
            'url' => ['url(javascript:alert(1))', false], 'expression' => ['expression(alert(1))', false],
            'unknown mix' => ['color-mix(in srgb, red 40%, blue)', false], 'empty' => ['', false],
        ];
    }

    #[DataProvider('colours')]
    public function test_one_colour_grammar(string $value, bool $valid): void
    {
        $this->assertSame($valid, HtmlSanitizer::isCssColor($value), $value);
    }

    public function test_shared_stylesheets_have_no_bare_brand_tint_or_border_literal(): void
    {
        foreach (['css/theme-runtime-blocks.css', 'css/theme-runtime-contract.css'] as $file) {
            $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(base_path(self::CSS . '/' . $file)));
            $this->assertStringNotContainsString('rgba(99,102,241', $css, "{$file}: indigo tint not tied to --tb-primary");
            $this->assertStringNotContainsString('rgba(14,165,233', $css, "{$file}: sky tint not tied to --tb-secondary");
            $bare = preg_replace('/var\(--[\w-]+,\s*#e5e7eb\)/', '', $css);
            $this->assertStringNotContainsString('#e5e7eb', $bare, "{$file}: border colour not tied to --tb-border");
        }
    }

    public function test_persian_text_has_no_negative_tracking_outside_ltr_rules(): void
    {
        foreach (['css/theme-runtime-blocks.css', 'css/theme-runtime-contract.css', 'js/blocks/header-modern/header-modern.css'] as $file) {
            $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(base_path(self::CSS . '/' . $file)));
            preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);
            foreach ($rules as [, $selector, $body]) {
                if (preg_match('/letter-spacing\s*:\s*-/', $body)) {
                    foreach (array_map('trim', explode(',', $selector)) as $part) {
                        $this->assertStringStartsWith('[dir="ltr"]', $part, "{$file}: {$part}");
                    }
                }
            }
        }
    }

    public function test_brand_colour_defaults_follow_the_theme(): void
    {
        $literal = [];
        foreach (BlockRegistry::all() as $type => $def) {
            foreach ($def['schema'] ?? [] as $key => $field) {
                if (($field['type'] ?? null) === 'color' && in_array(strtolower((string) ($field['default'] ?? '')), ['#4f46e5', '#6366f1', '#7c3aed'], true)) {
                    $literal[] = "{$type}.{$key}";
                }
            }
        }
        // header_modern computes its CTA text contrast from a hex value.
        $this->assertSame(['header_modern_v1.ctaColor'], $literal);
        $this->assertSame('var(--tb-primary, #4f46e5)', BlockRegistry::all()['cta_band']['defaultProps']['background']);
        $this->assertSame('var(--tb-secondary, #7c3aed)', BlockRegistry::all()['hero_gradient']['defaultProps']['gradient_to']);
    }

    public function test_colour_props_accept_tokens_and_alpha_and_refuse_injection(): void
    {
        $section = $this->render('section', ['background' => 'var(--tb-surface, #f9fafb)']);
        $this->assertStringContainsString('style="background-color:var(--tb-surface, #f9fafb)"', $section);
        $this->assertStringNotContainsString('tb-bg-var', $section);
        $this->assertStringContainsString('tb-bg-surface', $this->render('section', ['background' => 'surface']));

        $hero = $this->render('hero', ['title' => 'T', 'background' => 'rgba(14, 165, 233, 0.5)', 'align' => 'x" onclick="y']);
        $this->assertStringContainsString('class="tb-hero tb-bg-custom tb-align-center" style="background:rgba(14, 165, 233, 0.5)"', $hero);
        $this->assertStringNotContainsString('onclick', $hero);

        // No align prop at all: the default, not an undefined-key error.
        $this->assertStringContainsString('class="tb-hero tb-bg-primary tb-align-center"', $this->render('hero', ['title' => 'T']));

        $gradient = $this->render('hero_gradient', ['gradient_from' => 'red;background:url(x)']);
        $this->assertStringContainsString('linear-gradient(135deg,var(--tb-primary, #4f46e5) 0%', $gradient);
        $this->assertStringNotContainsString('url(x)', $gradient);

        $band = $this->render('cta_band', ['background' => 'color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent)']);
        $this->assertStringContainsString('--tb-cta-band-bg:color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent);', $band);
        $this->assertStringContainsString('--tb-cta-band-text:var(--tb-on-primary, #ffffff);', $band);
    }

    public function test_prices_counters_and_badges_print_in_persian(): void
    {
        $grid = $this->render('product_grid', []);
        $this->assertStringContainsString('۱٬۲۵۰٬۰۰۰ تومان', $grid);
        $this->assertDoesNotMatchRegularExpression('/\d{1,3},\d{3}/', strip_tags($grid));

        $counter = $this->render('counter', ['value' => '5000', 'suffix' => '+', 'label' => 'کاربر']);
        $this->assertStringContainsString('data-count="5000">۵٬۰۰۰<span class="tb-stat-suffix">+</span>', $counter);

        $stats = $this->render('stats_row', ['stats' => [['value' => '4.9', 'suffix' => '/5'], ['value' => '1398', 'suffix' => '']]]);
        $this->assertStringContainsString('data-count="4.9">۴٫۹<span class="tb-stat-suffix">/۵</span>', $stats);
        $this->assertStringContainsString('data-count="1398">۱۳۹۸<', $stats); // a year is not grouped

        $this->assertStringContainsString('<span class="tb-cart-icon-badge">۱۲</span>', $this->render('cart_icon', ['count' => 12]));

        $plans = $this->render('pricing_cards', ['plans' => [['name' => 'پایه', 'price' => '150,000', 'features' => []]]]);
        $this->assertStringContainsString('۱۵۰٬۰۰۰', $plans);
    }

    public function test_a_latin_digit_host_can_opt_out(): void
    {
        $counter = $this->render('counter', ['value' => '5000', 'suffix' => '+'], ['digits' => 'latin']);
        $this->assertStringContainsString('data-count="5000">5000<', $counter);
        $this->assertStringContainsString('1,250,000 تومان', $this->render('product_grid', [], ['digits' => 'latin']));
    }
}
