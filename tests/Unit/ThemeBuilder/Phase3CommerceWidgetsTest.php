<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Support\JalaliDate;
use Tests\TestCase;

/**
 * Phase 3 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-068): the
 * product card's derived badge overlays, the commercial FAQ accordion, the
 * drawer's optional `update` endpoint and the Jalali calendar arithmetic
 * behind the countdown (its rendering lives in CountdownRenderTest).
 */
class Phase3CommerceWidgetsTest extends TestCase
{
    private const CART = ['page' => '/shop/cart', 'mini' => '/shop/cart/mini', 'add' => '/shop/cart/add', 'remove' => '/shop/cart/remove', 'count' => 3];

    private function card(array $p, ?array $endpoints = null): string
    {
        $r = new BlockRenderer();
        if ($endpoints !== null) {
            $r->setContext(['site_endpoints' => $endpoints]);
        }
        return (new \ReflectionMethod($r, 'productCard'))->invoke($r, $p + ['title' => 'T', 'url' => '/p', 'price_html' => 'x']);
    }

    private function ribbons(string $html): array
    {
        preg_match('#<div class="tb-product-ribbons">(.*?)</div>#s', $html, $m);
        preg_match_all('#<span class="tb-badge tb-badge-(\w+) tb-badge-sm"><span class="tb-badge-label">([^<]*)</span></span>#u', $m[1] ?? '', $b, PREG_SET_ORDER);
        return array_map(fn ($x) => $x[1] . ':' . $x[2], $b);
    }

    private function render(string $type, array $props): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    // ── Product badges ────────────────────────────────────────────────

    public function test_badges_are_derived_in_a_fixed_order_and_capped_at_three(): void
    {
        $all = $this->card(['price' => 790000, 'original' => 1000000, 'rating' => 4.7, 'reviews' => 40, 'is_new' => true, 'badge' => 'ارسال رایگان']);
        $this->assertSame(['error:۲۱٪ تخفیف', 'warning:پرامتیاز', 'info:جدید'], $this->ribbons($all), 'the free-text badge is the fourth and is dropped');

        $this->assertSame(['info:جدید', 'primary:ارسال رایگان'], $this->ribbons($this->card(['is_new' => true, 'badge' => 'ارسال رایگان'])));
        $this->assertSame([], $this->ribbons($this->card(['price' => 100, 'original' => 100])), 'no discount, no rating, not new: no overlay');
        $this->assertStringNotContainsString('tb-product-ribbons', $this->card([]));
    }

    public function test_top_rated_needs_both_the_average_and_enough_reviews(): void
    {
        $this->assertSame(['warning:پرامتیاز'], $this->ribbons($this->card(['rating' => BlockRenderer::TOP_RATED_MIN_RATING, 'reviews' => BlockRenderer::TOP_RATED_MIN_REVIEWS])));
        $this->assertSame([], $this->ribbons($this->card(['rating' => 4.4, 'reviews' => 500])));
        $this->assertSame([], $this->ribbons($this->card(['rating' => 5, 'reviews' => BlockRenderer::TOP_RATED_MIN_REVIEWS - 1])));
    }

    public function test_a_free_text_badge_never_duplicates_a_derived_one(): void
    {
        $this->assertSame(['error:۲۱٪ تخفیف'], $this->ribbons($this->card(['price' => 790000, 'original' => 1000000, 'badge' => 'تخفیف'])));
        $this->assertSame(['info:جدید'], $this->ribbons($this->card(['is_new' => true, 'badge' => 'جدید'])));
        $this->assertSame(['primary:جدید'], $this->ribbons($this->card(['badge' => 'جدید'])), 'legacy callers keep their free-text badge');
    }

    public function test_builder_preview_demo_cards_show_the_overlays(): void
    {
        $html = $this->render('product_grid', []);
        $this->assertStringContainsString('<span class="tb-badge-label">پرامتیاز</span>', $html);
        $this->assertStringContainsString('tb-badge-info tb-badge-sm"><span class="tb-badge-label">جدید</span>', $html);
        $this->assertStringContainsString('۲۱٪ تخفیف', $html);
    }

    // ── Cart endpoints ────────────────────────────────────────────────

    public function test_the_update_endpoint_is_optional_and_validated(): void
    {
        $html = $this->card(['product_id' => 7], ['cart' => self::CART + ['update' => '/shop/cart/update']]);
        $this->assertStringContainsString('&quot;update&quot;:&quot;/shop/cart/update&quot;', $html);

        $noScripts = fn (string $h) => (string) preg_replace('#<script\b[^>]*>.*?</script>#s', '', $h);
        $without = $noScripts($this->card(['product_id' => 7], ['cart' => self::CART]));
        $this->assertStringContainsString('data-tb-cart=', $without, 'a host without update still gets the drawer');
        $this->assertStringNotContainsString('update', $without);

        $evil = $noScripts($this->card(['product_id' => 7], ['cart' => self::CART + ['update' => 'javascript:alert(1)']]));
        $this->assertStringNotContainsString('javascript', $evil);
    }

    // ── FAQ ───────────────────────────────────────────────────────────

    public function test_faq_is_a_styled_accordion_with_exclusive_and_open_first(): void
    {
        $items = [['question' => 'ارسال؟', 'answer' => "بله\nبا پست"], ['question' => 'مرجوعی؟', 'answer' => 'تا ۷ روز']];
        $html = $this->render('faq', ['title' => 'سوالات', 'items' => $items, 'exclusive' => true, 'open_first' => true]);

        $this->assertStringContainsString('class="tb-section tb-faq"', $html);
        $this->assertSame(2, substr_count($html, '<details class="tb-faq-item" name="tb-faq-b1"'));
        $this->assertSame(1, substr_count($html, ' open>'), 'only the first is open');
        $this->assertStringContainsString('<span class="tb-faq-question-text">ارسال؟</span><span class="tb-faq-chevron" aria-hidden="true"><svg', $html);
        $this->assertStringContainsString('<div class="tb-faq-answer">بله<br>' . "\n" . 'با پست</div>', $html, 'plain answers keep their line breaks');

        $plain = $this->render('faq', ['items' => $items]);
        $this->assertStringNotContainsString(' name="', $plain);
        $this->assertStringNotContainsString(' open>', $plain);
    }

    public function test_faq_answers_are_sanitized_and_aliases_are_read(): void
    {
        $html = $this->render('faq', ['items' => [
            ['title' => 'ALIAS-Q', 'content' => '<p onclick="x()">ALIAS-A <a href="javascript:x()">l</a></p><script>bad()</script>'],
            ['question' => '<b>Q</b>', 'answer' => 'a < b'],
            ['question' => '', 'answer' => 'orphan'],
            'not-an-item',
        ]]);
        $this->assertStringContainsString('ALIAS-Q', $html);
        $this->assertStringContainsString('ALIAS-A', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script', $html, 'the shared sanitizer drops the element (its text stays inert)');
        $this->assertStringContainsString('&lt;b&gt;Q&lt;/b&gt;', $html);
        $this->assertStringContainsString('a &lt; b', $html);
        $this->assertStringNotContainsString('orphan', $html);
    }

    public function test_an_empty_faq_shows_the_editing_hint(): void
    {
        $html = $this->render('faq', ['title' => 'سوالات', 'items' => []]);
        $this->assertStringContainsString('tb-faq-empty', $html);
        $this->assertStringNotContainsString('tb-faq-list', $html);
        $this->assertStringContainsString('tb-faq-empty', $this->render('faq', ['items' => '{not json']));
    }

    // ── Registry ──────────────────────────────────────────────────────

    public function test_registry_exposes_the_phase3_fields_with_matching_defaults(): void
    {
        $countdown = BlockRegistry::get('countdown');
        $this->assertSame(['end_date', 'end_time', 'label', 'expired_text', 'style', 'show_end_date'], array_keys($countdown['schema']));
        $this->assertSame(['card', 'banner'], array_keys($countdown['schema']['style']['options']));
        $faq = BlockRegistry::get('faq');
        $this->assertSame('boolean', $faq['schema']['exclusive']['type']);
        $this->assertSame('boolean', $faq['schema']['open_first']['type']);
        foreach (['countdown' => $countdown, 'faq' => $faq] as $type => $def) {
            foreach ($def['schema'] as $field => $control) {
                if ($field !== 'items') {
                    $this->assertSame($control['default'], $def['defaultProps'][$field] ?? null, "{$type}.{$field}");
                }
            }
        }
        $this->assertArrayHasKey('bestselling', BlockRegistry::get('module:shop_product_grid')['schema']['source']['options']);
    }

    // ── Jalali arithmetic ─────────────────────────────────────────────

    public function test_jalali_conversion_matches_known_dates_and_round_trips(): void
    {
        $this->assertSame([2026, 3, 21], JalaliDate::toGregorian(1405, 1, 1));
        $this->assertSame([2026, 11, 6], JalaliDate::toGregorian(1405, 8, 15));
        $this->assertSame([1405, 8, 6], JalaliDate::fromGregorian(2026, 10, 28));
        $this->assertSame([2025, 3, 20], JalaliDate::toGregorian(1403, 12, 30));
        $this->assertTrue(JalaliDate::isLeap(1403));
        $this->assertFalse(JalaliDate::isLeap(1404));
        $this->assertFalse(JalaliDate::isValid(1404, 12, 30));
        $this->assertSame('۱۵ آبان ۱۴۰۵', JalaliDate::format(1405, 8, 15));

        for ($t = gmmktime(0, 0, 0, 1, 1, 2000); $t < gmmktime(0, 0, 0, 1, 1, 2040); $t += 86400 * 5) {
            $g = array_map('intval', explode('-', gmdate('Y-n-j', $t)));
            $this->assertSame($g, JalaliDate::toGregorian(...JalaliDate::fromGregorian(...$g)));
        }
    }
}
