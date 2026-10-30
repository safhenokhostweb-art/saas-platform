<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Support\JalaliDate;
use Tests\TestCase;

/**
 * Phase 3b (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-069):
 * Bento v2 dynamic cells, the shared review card and rating breakdown,
 * and the pricing billing switch, popular ribbon and feature checklist.
 */
class Phase3bBentoReviewsPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BlockRenderer::resetCountdownAdapterEmission();
    }

    private function render(string $type, array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => $id, 'props' => $props]);
    }

    private static function noScripts(string $html): string
    {
        return (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#s', '', $html);
    }

    // ── Bento v2 ──────────────────────────────────────────────────────

    public function test_bento_dynamic_cells_render_product_countdown_and_metric(): void
    {
        $html = self::noScripts($this->render('bento_grid', ['items' => [
            ['kind' => 'product', 'title' => 'پیشنهاد ویژه', 'size' => 'half', 'link' => '/x'],
            ['kind' => 'countdown', 'title' => 'حراج', 'end_date' => '۱۴۰۵/۰۹/۰۱', 'end_time' => '20:00'],
            ['kind' => 'metric', 'title' => 'رضایت مشتری', 'metric_value' => '98%', 'icon' => 'users', 'size' => 'full'],
        ]]));

        $this->assertStringContainsString('<div class="tb-bento-item half kind-product"', $html, 'a product cell is never itself a link');
        $this->assertStringContainsString('<p class="tb-bento-eyebrow">پیشنهاد ویژه</p><div class="tb-product-card">', $html);
        $this->assertStringContainsString('class="tb-bento-item small kind-countdown"', $html);
        $this->assertStringContainsString('id="tb-b1-cd1" class="tb-countdown" data-end-date="2026-11-22T20:00:00+03:30"', $html);
        $this->assertStringContainsString('class="tb-bento-item full kind-metric"', $html);
        $this->assertStringContainsString('<p class="tb-bento-metric-value">۹۸٪</p><h3 class="tb-bento-item-title">رضایت مشتری</h3>', $html);
        $this->assertStringNotContainsString('<a href="/x"', $html);
    }

    public function test_bento_content_cells_are_unchanged_and_unknown_kinds_fall_back(): void
    {
        $item = ['title' => 'کارت', 'text' => 'متن', 'icon' => 'zap', 'size' => 'large'];
        $plain = $this->render('bento_grid', ['items' => [$item]]);
        $this->assertSame($plain, $this->render('bento_grid', ['items' => [['kind' => 'content'] + $item]]));
        $this->assertSame($plain, $this->render('bento_grid', ['items' => [['kind' => 'evil"><script>'] + $item]]));
        $this->assertStringContainsString('class="tb-bento-item large"', $plain);
        $this->assertStringContainsString('class="tb-bento-item small"', $this->render('bento_grid', ['items' => [['size' => 'giant'] + $item]]));
    }

    public function test_bento_registry_offers_kinds_and_twelve_column_sizes(): void
    {
        $fields = BlockRegistry::get('bento_grid')['schema']['items']['fields'];
        $this->assertSame(['content', 'product', 'countdown', 'metric'], array_keys($fields['kind']['options']));
        $this->assertSame(['small', 'half', 'large', 'full', 'tall', 'featured'], array_keys($fields['size']['options']));
        $this->assertSame('kind=product', $fields['product_id']['show_if']);
        $this->assertSame('kind=countdown', $fields['end_date']['show_if']);
        $this->assertSame('kind=metric', $fields['metric_value']['show_if']);
    }

    // ── Reviews ───────────────────────────────────────────────────────

    public function test_testimonial_cards_show_verified_jalali_date_and_svg_stars(): void
    {
        $html = $this->render('testimonials_slider', ['mode' => 'static', 'items' => [
            ['name' => 'سارا محمدی', 'role' => 'مدیر', 'company' => 'شرکت', 'text' => 'عالی بود', 'rating' => 4, 'verified' => true, 'date' => '1405/08/02'],
            ['name' => 'علی', 'text' => 'خوب', 'rating' => 5, 'date' => '2026-10-24'],
        ]]);
        $this->assertStringContainsString('<article class="tb-testimonial-card tb-review-card">', $html);
        $this->assertStringContainsString('role="img" aria-label="امتیاز ۴ از ۵"', $html);
        $this->assertSame(4 + 5, substr_count($html, 'tb-star is-on'));
        $this->assertStringContainsString('<time class="tb-review-date" datetime="2026-10-24">۲ آبان ۱۴۰۵</time>', $html, 'Jalali and Gregorian input show the same Persian date');
        $this->assertSame(2, substr_count($html, '۲ آبان ۱۴۰۵'));
        $this->assertSame(1, substr_count($html, 'خریدار تاییدشده'));
        $this->assertStringContainsString('<div class="tb-testimonial-role">مدیر · شرکت</div>', $html);
        $this->assertStringNotContainsString('itemscope', $html, 'authored testimonials review no item: no microdata');
        $this->assertStringNotContainsString('tb-rating-summary', $html, 'the summary is opt-in');
    }

    public function test_rating_summary_is_a_persian_breakdown(): void
    {
        $items = [];
        foreach ([5, 5, 5, 4, 3] as $i => $r) {
            $items[] = ['name' => "م{$i}", 'text' => 't', 'rating' => $r];
        }
        $html = $this->render('testimonials_slider', ['mode' => 'static', 'show_summary' => true, 'items' => $items]);
        $this->assertStringContainsString('<span class="tb-rating-average">۴٫۴</span>', $html);
        $this->assertStringContainsString('<span class="tb-rating-total">از ۵ نظر</span>', $html);
        $this->assertStringContainsString('<span class="tb-rating-row-label">۵ ستاره</span><span class="tb-rating-bar" aria-hidden="true"><span style="width:60%"></span></span><span class="tb-rating-row-pct">۶۰٪<span class="tb-visually-hidden"> (۳ نظر)</span>', $html);
        $this->assertStringContainsString('<span class="tb-rating-row-label">۱ ستاره</span><span class="tb-rating-bar" aria-hidden="true"><span style="width:0%"></span></span>', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
    }

    public function test_review_card_microdata_for_a_reviewed_product(): void
    {
        $r = new BlockRenderer();
        $card = new \ReflectionMethod($r, 'reviewCardHtml');
        $standalone = $card->invoke($r, ['name' => 'رضا', 'text' => 'خوب', 'rating' => 5, 'date' => '2026-10-20', 'item' => ['name' => 'کیف', 'url' => '/shop/bag'], 'schema' => 'standalone']);
        $this->assertStringContainsString('<article class="tb-testimonial-card tb-review-card" itemscope itemtype="https://schema.org/Review">', $standalone);
        $this->assertStringContainsString('<span itemprop="itemReviewed" itemscope itemtype="https://schema.org/Product"><meta itemprop="name" content="کیف"><link itemprop="url" href="/shop/bag"></span>', $standalone);
        $this->assertStringContainsString('<meta itemprop="ratingValue" content="5">', $standalone);
        $this->assertStringContainsString('itemprop="datePublished">', $standalone);
        $this->assertStringContainsString('itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">رضا</span>', $standalone);
        $this->assertStringContainsString('<p class="tb-testimonial-text" itemprop="reviewBody">خوب</p>', $standalone);

        $nested = $card->invoke($r, ['name' => 'رضا', 'rating' => 4, 'schema' => 'nested', 'item' => ['name' => 'کیف']]);
        $this->assertStringContainsString('itemprop="review" itemscope itemtype="https://schema.org/Review"', $nested);
        $this->assertStringNotContainsString('itemReviewed', $nested);

        $noItem = $card->invoke($r, ['name' => 'x', 'rating' => 4, 'schema' => 'standalone']);
        $this->assertStringNotContainsString('itemscope', $noItem, 'a standalone review needs an item');

        $summary = (new \ReflectionMethod($r, 'ratingSummaryHtml'))->invoke($r, [5 => 3, 4 => 1], true);
        $this->assertStringContainsString('<span itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating"><meta itemprop="ratingValue" content="4.8"><meta itemprop="reviewCount" content="4">', $summary);
        $this->assertSame('', (new \ReflectionMethod($r, 'ratingSummaryHtml'))->invoke($r, [], true));
    }

    public function test_review_card_escapes_and_hides_unsafe_avatars(): void
    {
        $r = new BlockRenderer();
        $html = (new \ReflectionMethod($r, 'reviewCardHtml'))->invoke($r, ['name' => '<b>x</b>', 'avatar' => 'javascript:alert(1)', 'text' => '<img src=x onerror=1>', 'date' => '1405/13/40']);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<time', $html, 'an impossible date is left out');
        $this->assertStringNotContainsString('tb-review-avatar', (new \ReflectionMethod($r, 'reviewCardHtml'))->invoke($r, ['name' => 'x', 'show_avatar' => false]));
    }

    // ── Pricing ───────────────────────────────────────────────────────

    public function test_pricing_billing_toggle_ribbon_and_feature_checklist(): void
    {
        $html = $this->render('pricing_cards', ['yearly_discount_label' => '20% تخفیف', 'plans' => [
            ['name' => 'پایه', 'price' => '99', 'period' => '/ ماه', 'price_yearly' => '950', 'features' => [
                ['text' => 'پشتیبانی', 'included' => true],
                ['text' => 'API', 'included' => false, 'tooltip' => 'دسترسی برنامه‌نویسی'],
            ]],
            ['name' => 'حرفه‌ای', 'price' => '199', 'highlight' => true, 'badge' => 'پیشنهاد ما', 'features' => ['legacy']],
        ]]);

        $this->assertStringContainsString('class="tb-pricing-cards tb-section" data-tb-pricing>', $html);
        $this->assertStringContainsString('<button type="button" class="tb-billing-option is-active" data-tb-billing="monthly" aria-pressed="true">ماهانه</button>', $html);
        $this->assertStringContainsString('aria-pressed="false">سالانه<span class="tb-billing-pill">۲۰٪ تخفیف</span></button>', $html);
        $this->assertStringContainsString('<div class="tb-plan-price" data-tb-cycle="monthly">۹۹</div>', $html);
        $this->assertStringContainsString('<div class="tb-plan-price" data-tb-cycle="yearly" hidden>۹۵۰</div><p class="tb-plan-period" data-tb-cycle="yearly" hidden>/ سال</p>', $html);
        $this->assertStringContainsString('<div class="tb-plan-price">۱۹۹</div>', $html, 'no yearly price: the monthly one shows in both modes');
        $this->assertStringContainsString('<div class="tb-plan-ribbon">پیشنهاد ما</div>', $html);
        $this->assertStringContainsString('<li class="tb-plan-feature is-included"><span class="tb-plan-feature-icon"><svg class="tb-icon-svg tb-icon-svg-check"', $html);
        $this->assertStringContainsString('<li class="tb-plan-feature is-excluded"><span class="tb-plan-feature-icon"><svg class="tb-icon-svg tb-icon-svg-x"', $html);
        $this->assertStringContainsString('<span class="tb-visually-hidden"> (در این پلن نیست)</span>', $html);
        $this->assertStringContainsString('aria-label="توضیح «API»" aria-describedby="tb-b1-tip-0-1">', $html);
        $this->assertStringContainsString('<span class="tb-tip-bubble" role="tooltip" id="tb-b1-tip-0-1">دسترسی برنامه‌نویسی</span>', $html);
        $this->assertStringContainsString('<span class="tb-plan-feature-text">legacy</span>', $html, 'legacy string features stay included');
        $this->assertSame(1, substr_count($html, 'data-tb-block-asset="pricing"'));
    }

    public function test_pricing_without_yearly_prices_or_tooltips_ships_no_switch_or_script(): void
    {
        $html = $this->render('pricing_cards', ['plans' => [['name' => 'A', 'price' => '1', 'features' => ['x']]]]);
        $this->assertStringNotContainsString('tb-billing-toggle', $html);
        $this->assertStringNotContainsString('data-tb-pricing', $html);
        $this->assertStringNotContainsString('<script', $html);

        $off = $this->render('pricing_cards', ['billing_toggle' => false, 'plans' => [['name' => 'A', 'price' => '1', 'price_yearly' => '10']]]);
        $this->assertStringNotContainsString('tb-billing-toggle', $off);
        $this->assertStringNotContainsString('data-tb-cycle', $off);
    }

    public function test_pricing_registry_fields(): void
    {
        $schema = BlockRegistry::get('pricing_cards')['schema'];
        $this->assertTrue($schema['billing_toggle']['default']);
        $plan = $schema['plans']['fields'];
        $this->assertArrayHasKey('price_yearly', $plan);
        $this->assertSame(['text', 'included', 'tooltip'], array_keys($plan['features']['fields']));
        $this->assertStringContainsString('price_yearly', BlockRegistry::get('pricing_cards')['defaultProps']['plans']);
    }

    public function test_jalali_parse_reads_both_calendars(): void
    {
        $this->assertSame(['jalali' => [1405, 8, 2], 'gregorian' => [2026, 10, 24]], JalaliDate::parse('۱۴۰۵/۰۸/۰۲'));
        $this->assertSame(['jalali' => [1405, 8, 2], 'gregorian' => [2026, 10, 24]], JalaliDate::parse('2026-10-24 13:05:00'));
        $this->assertNull(JalaliDate::parse('1404/12/30'));
        $this->assertNull(JalaliDate::parse('yesterday'));
    }
}
