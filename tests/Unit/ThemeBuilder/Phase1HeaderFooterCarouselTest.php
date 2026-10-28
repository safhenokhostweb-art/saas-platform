<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

/**
 * Phases 1 and 2 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-067):
 * the commercial header (smart sticky, transparent-over-hero, full-width
 * mega menu, mini-cart trigger), the high-conversion footer (newsletter,
 * social icons, app badges, trust seals), the RTL-correct carousel and the
 * shared product and blog cards.
 */
class Phase1HeaderFooterCarouselTest extends TestCase
{
    private const CART = ['page' => '/shop/cart', 'mini' => '/shop/cart/mini', 'add' => '/shop/cart/add', 'remove' => '/shop/cart/remove', 'count' => 3];

    private function render(string $type, array $props, ?array $endpoints = null): string
    {
        $r = new BlockRenderer();
        if ($endpoints !== null) {
            $r->setContext(['site_endpoints' => $endpoints]);
        }
        return $r->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    private static function withoutScripts(string $html): string
    {
        return (string) preg_replace('#<script\b[^>]*>.*?</script>#s', '', $html);
    }

    // ── Header ────────────────────────────────────────────────────────

    public function test_header_flags_drive_the_shared_script_and_a_plain_header_stays_as_before(): void
    {
        $plain = $this->render('nav_header', ['logo_text' => 'L']);
        $this->assertStringNotContainsString('data-tb-header', $plain);
        $this->assertStringNotContainsString('data-tb-block-asset="site_header"', $plain);

        $html = $this->render('nav_header', ['logo_text' => 'L', 'sticky' => true, 'sticky_hide_on_scroll' => true, 'transparent_over_hero' => true]);
        $this->assertStringContainsString('data-tb-header="sticky smart overlay" data-tb-overlay-text="auto"', $html);
        $this->assertSame(1, substr_count($html, 'data-tb-block-asset="site_header"'));

        $fixed = $this->render('nav_header', ['transparent_over_hero' => true, 'transparent_text_color' => '#0f172a']);
        $this->assertStringContainsString('data-tb-header="overlay" data-tb-overlay-text="#0f172a"', $fixed);
        $smartAlone = $this->render('nav_header', ['sticky_hide_on_scroll' => true]);
        $this->assertStringNotContainsString('data-tb-header', $smartAlone, 'hide-on-scroll needs sticky');
        $evil = $this->render('nav_header', ['transparent_over_hero' => true, 'transparent_text_color' => 'red;}body{display:none']);
        $this->assertStringContainsString('data-tb-overlay-text="auto"', $evil);
    }

    public function test_mega_menu_is_delegated_full_width_with_a_promo_cta(): void
    {
        $html = $this->render('nav_header', ['nav_links' => [[
            'label' => 'فروشگاه', 'url' => '/shop',
            'mega_columns' => [['title' => 'پوشاک', 'items' => [['label' => 'مردانه', 'url' => '/m', 'icon' => 'shirt']]]],
            'mega_banner_image' => '/promo.jpg', 'mega_banner_text' => 'حراج پاییزه', 'mega_banner_cta' => 'خرید کنید',
        ]]]);
        $body = self::withoutScripts($html);
        $this->assertMatchesRegularExpression('#class="tb-nav-header-link tb-mega-menu-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="(tb-mega-b1-\d+)-panel"#', $body);
        $this->assertMatchesRegularExpression('#<div class="tb-mega-menu-panel" id="tb-mega-b1-\d+-panel">#', $body);
        $this->assertStringNotContainsString('role="menu"', $body);
        $this->assertStringContainsString('<span class="tb-mega-menu-banner-cta">خرید کنید ', $body);
        $this->assertStringNotContainsString('tbMegaInit', $html, 'no per-item inline script any more');
        $this->assertSame(1, substr_count($html, 'data-tb-block-asset="site_header"'));
    }

    public function test_cart_icon_opens_the_drawer_with_host_endpoints_and_stays_a_link_in_the_preview(): void
    {
        $preview = $this->render('nav_header', ['show_cart' => true]);
        $this->assertStringContainsString('<a href="/cart" class="tb-nav-header-icon-link" aria-label="سبد خرید">', $preview);
        $this->assertStringNotContainsString('data-tb-cart', $preview);

        $live = $this->render('nav_header', ['show_cart' => true], ['cart' => self::CART]);
        $this->assertStringContainsString('<a href="/shop/cart" class="tb-nav-header-icon-link tb-cart-trigger" data-tb-cart-open', $live);
        $this->assertStringContainsString('<span class="tb-cart-badge" data-tb-cart-count>۳</span>', $live);
        $this->assertSame(1, substr_count($live, 'data-tb-block-asset="cart"'));
        preg_match('/data-tb-cart="([^"]+)"/', $live, $m);
        $this->assertSame(['page' => '/shop/cart', 'mini' => '/shop/cart/mini', 'add' => '/shop/cart/add', 'remove' => '/shop/cart/remove'], json_decode(html_entity_decode($m[1]), true));

        $broken = $this->render('nav_header', ['show_cart' => true], ['cart' => ['page' => 'javascript:alert(1)'] + self::CART]);
        $this->assertStringNotContainsString('javascript:', $broken);
        $this->assertStringContainsString('href="/cart"', $broken, 'an unsafe endpoint set falls back to the plain link');
    }

    public function test_theme_toggle_uses_catalog_icons(): void
    {
        $html = $this->render('nav_header', ['show_dark_toggle' => true]);
        $this->assertStringContainsString('<span class="tb-theme-toggle-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-monitor"', $html);
        $this->assertDoesNotMatchRegularExpression('/[☀🌙💻🌓]/u', $html);
        $this->assertStringContainsString('icon.innerHTML = ICONS[p]', $html);
    }

    // ── Footer ────────────────────────────────────────────────────────

    public function test_footer_social_links_become_network_icons(): void
    {
        $html = $this->render('nav_footer', ['social_links' => [
            ['url' => 'https://instagram.com/shop'],
            ['url' => 'https://t.me/shop', 'label' => 'کانال تلگرام'],
            ['url' => 'https://x.com/shop'],
            ['url' => 'https://netflix.com/shop', 'label' => 'نتفلیکس'],
            ['url' => 'https://example.com', 'network' => 'aparat'],
            ['url' => 'mailto:hi@example.com'],
            ['url' => 'javascript:alert(1)', 'label' => 'بد'],
        ]]);
        $this->assertStringContainsString('class="tb-social-icon tb-social-icon--instagram" target="_blank" rel="noopener noreferrer" aria-label="اینستاگرام"', $html);
        $this->assertStringContainsString('tb-social-icon--telegram" target="_blank" rel="noopener noreferrer" aria-label="کانال تلگرام"', $html);
        $this->assertStringContainsString('tb-social-icon--x"', $html);
        $this->assertStringContainsString('class="tb-social-icon tb-social-icon--text" target="_blank" rel="noopener noreferrer" aria-label="نتفلیکس" title="نتفلیکس">نتفلیکس</a>', $html, 'netflix.com is not x.com');
        $this->assertStringContainsString('tb-social-icon--aparat"', $html);
        $this->assertStringContainsString('<a href="mailto:hi@example.com" class="tb-social-icon tb-social-icon--email" aria-label="ایمیل"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_footer_conversion_row_newsletter_apps_and_seals(): void
    {
        $this->assertStringNotContainsString('tb-nav-footer-extras', $this->render('nav_footer', ['copyright' => '©']), 'unchanged without the new props');

        $html = $this->render('nav_footer', [
            'newsletter_enabled' => true, 'newsletter_title' => 'خبرنامه',
            'app_links' => [['store' => 'bazaar', 'url' => 'https://cafebazaar.ir/app/x'], ['store' => 'nope', 'url' => 'https://x'], ['store' => 'myket', 'url' => '#']],
            'enamad_id' => "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=123456&amp;Code=AbC12'><img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=123456&amp;Code=AbC12' onerror='alert(1)' code='AbC12'></a>",
            'samandehi_id' => '۹۸۷', 'samandehi_code' => 'xyz9',
        ], ['newsletter' => '/newsletter/subscribe']);
        $this->assertStringContainsString('<p class="tb-nav-footer-newsletter-title">خبرنامه</p>', $html);
        $this->assertStringContainsString('method="post" action="/newsletter/subscribe" data-tb-newsletter', $html);
        $this->assertStringContainsString('<button type="submit" class="tb-newsletter-btn tb-btn">', $html, 'footer button styled by the sheet');
        $this->assertSame(1, substr_count($html, 'tb-app-badge tb-app-badge--'), 'unknown store and empty URL are skipped');
        $this->assertStringContainsString('<small>دریافت از</small><strong>کافه‌بازار</strong>', $html);
        $this->assertStringContainsString('href="https://trustseal.enamad.ir/?id=123456&amp;Code=AbC12" target="_blank" rel="noopener" referrerpolicy="origin"><img src="https://trustseal.enamad.ir/logo.aspx?id=123456&amp;Code=AbC12"', $html);
        $this->assertStringContainsString('href="https://logo.samandehi.ir/Verify.aspx?id=987&amp;p=xyz9"', $html);
        $this->assertStringNotContainsString('alert', $html, 'only the ids survive a pasted snippet');

        $bad = $this->render('nav_footer', ['enamad_id' => '12"><script>', 'enamad_code' => 'x']);
        $this->assertStringNotContainsString('tb-trust-seals', $bad);
    }

    public function test_registry_exposes_the_new_header_and_footer_fields(): void
    {
        $all = BlockRegistry::all();
        foreach (['sticky_hide_on_scroll', 'transparent_over_hero', 'transparent_text_color'] as $k) {
            $this->assertArrayHasKey($k, $all['nav_header']['schema']);
        }
        $this->assertSame('', $all['nav_header']['schema']['transparent_text_color']['default'], "'' = automatic contrast");
        $this->assertArrayHasKey('mega_banner_cta', $all['nav_header']['schema']['nav_links']['fields']);
        foreach (['newsletter_enabled', 'enamad_id', 'enamad_code', 'samandehi_id', 'samandehi_code', 'app_links'] as $k) {
            $this->assertArrayHasKey($k, $all['nav_footer']['schema']);
        }
        $this->assertArrayHasKey('network', $all['nav_footer']['schema']['social_links']['fields']);
    }

    // ── Carousel + cards ──────────────────────────────────────────────

    public function test_carousel_arrows_sit_on_logical_sides_and_point_outward(): void
    {
        $html = $this->render('product_slider', []);
        $this->assertStringContainsString('<button type="button" class="tb-carousel-prev" aria-label="اسلاید قبلی"><svg class="tb-icon-svg tb-icon-svg-chevron-right"', $html);
        $this->assertStringContainsString('<button type="button" class="tb-carousel-next" aria-label="اسلاید بعدی"><svg class="tb-icon-svg tb-icon-svg-chevron-left"', $html);
        $this->assertStringNotContainsString('right:0.25rem', $html);

        $css = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
        $this->assertStringContainsString('.tb-carousel-prev { inset-inline-start: 0.25rem; }', $css);
        $this->assertStringContainsString('[dir="ltr"] .tb-carousel-prev .tb-icon-svg, [dir="ltr"] .tb-carousel-next .tb-icon-svg { transform: scaleX(-1); }', $css);

        $js = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/js/blocks/carousel.js'));
        $this->assertStringContainsString("var nextKey = isRTL ? 'ArrowLeft' : 'ArrowRight';", $js);
        $this->assertStringContainsString('function isForward(dx) { return isRTL ? dx > 0 : dx < 0; }', $js);
        $this->assertStringNotContainsString("(dx > 0 ? stepPrev : stepNext)", $js, 'the reversed RTL swipe is gone');
    }

    public function test_product_card_shows_discount_rating_stock_and_the_right_action(): void
    {
        $r = new BlockRenderer();
        $card = new \ReflectionMethod($r, 'productCard');
        $base = ['title' => 'کیف <چرم>', 'url' => '/p/1', 'price' => 790000, 'original' => 1000000, 'price_html' => '۷۹۰٬۰۰۰ تومان', 'original_html' => '۱٬۰۰۰٬۰۰۰ تومان', 'rating' => 4.5, 'reviews' => 123, 'stock' => 'low', 'stock_label' => 'تنها 3 عدد باقی مانده', 'product_id' => 42, 'badge' => 'جدید'];

        $html = $card->invoke($r, $base);
        $this->assertStringContainsString('tb-badge-error', $html);
        $this->assertStringContainsString('۲۱٪ تخفیف', $html);
        $this->assertStringContainsString('tb-badge-primary', $html);
        $this->assertStringContainsString('<span class="tb-product-price-old">۱٬۰۰۰٬۰۰۰ تومان</span><span class="tb-product-price-now">۷۹۰٬۰۰۰ تومان</span>', $html);
        $this->assertStringContainsString('<span class="tb-product-stock tb-product-stock--low">تنها ۳ عدد باقی مانده</span>', $html);
        $this->assertStringContainsString('role="img" aria-label="امتیاز ۴٫۵ از ۵ از ۱۲۳ نظر"', $html);
        $this->assertSame(4, substr_count($html, 'tb-star is-on'));
        $this->assertSame(1, substr_count($html, 'tb-star is-half'));
        $this->assertStringContainsString('کیف &lt;چرم&gt;', $html);
        $this->assertStringContainsString('<a href="/p/1" class="tb-btn tb-btn-primary tb-product-add">', $html, 'preview: the button is a link');

        $r->setContext(['site_endpoints' => ['cart' => self::CART, 'csrf' => 'tok']]);
        $live = $card->invoke($r, $base);
        $this->assertStringContainsString('<form class="tb-product-add-form" method="post" action="/shop/cart/add" data-tb-cart=', $live);
        $this->assertStringContainsString('<input type="hidden" name="_token" value="tok"><input type="hidden" name="product_id" value="42">', $live);
        $this->assertStringContainsString('data-tb-add-to-cart data-open-cart data-product-id="42" aria-label="افزودن «کیف &lt;چرم&gt;» به سبد خرید"', $live);
        $this->assertStringContainsString('data-tb-block-asset="cart"', $live);

        $this->assertStringContainsString('<span class="tb-btn tb-product-add is-disabled" aria-disabled="true">ناموجود</span>', $card->invoke($r, ['stock' => 'out'] + $base));
        $this->assertStringContainsString('class="tb-btn tb-btn-secondary tb-product-add">انتخاب گزینه‌ها</a>', $card->invoke($r, ['needs_options' => true] + $base));
        $this->assertStringNotContainsString('tb-product-rating', $card->invoke($r, ['rating' => null] + $base));
        $this->assertStringNotContainsString('<form', $card->invoke($r, ['product_id' => '1 OR 1'] + $base));
    }

    public function test_blog_card_and_preview_layouts(): void
    {
        $r = new BlockRenderer();
        $card = (new \ReflectionMethod($r, 'blogCardHtml'))->invoke($r, [
            'title' => 'عنوان', 'url' => '/blog/a', 'category' => 'آموزش', 'category_url' => '/blog/category/t',
            'author' => 'سارا', 'date' => '5 آبان 1405', 'date_iso' => '2026-10-27', 'reading_minutes' => 7, 'excerpt' => 'خلاصه',
        ]);
        $this->assertStringStartsWith('<article class="tb-blog-card">', $card);
        $this->assertStringContainsString('<a class="tb-blog-card-cat" href="/blog/category/t">آموزش</a>', $card);
        $this->assertStringContainsString('<span class="tb-blog-card-avatar tb-blog-card-avatar--initial" aria-hidden="true">س</span>', $card);
        $this->assertStringContainsString('<time datetime="2026-10-27">۵ آبان ۱۴۰۵</time>', $card);
        $this->assertStringContainsString('۷ دقیقه مطالعه', $card);
        $this->assertStringNotContainsString('<a href="/blog/a" class="tb-blog-card"', $card, 'no nested links');

        // No layout prop at all (stored themes): grid, never an undefined-key warning.
        $this->assertSame(3, substr_count($this->render('blog_posts', []), '<article class="tb-blog-card">'));

        foreach (['grid' => 'tb-grid-cols-3', 'list' => 'tb-blog-list', 'slider' => 'tb-carousel'] as $layout => $marker) {
            $html = $this->render('blog_posts', ['layout' => $layout, 'title' => 'وبلاگ']);
            $this->assertStringContainsString($marker, $html, $layout);
            $this->assertSame(3, substr_count($html, '<article class="tb-blog-card">'), $layout);
        }
    }
}
