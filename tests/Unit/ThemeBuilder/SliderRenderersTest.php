<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/**
 * Universal Multipurpose Slider Overhaul — real renderer output tests for
 * the shared carousel engine (BlockRenderer::renderCarousel(), promoted
 * from a customer-site-only override to the canonical shared package so
 * saas-platform's own admin preview shows real slider behavior too) and
 * every slider-shaped canonical type built or fixed on top of it: the
 * Image Slider (`slider`), Testimonial Slider (`testimonials_slider` —
 * fixes a real schema/renderer `columns` mismatch found in audit), Logo
 * Slider (`client_logos`), and the two brand-new demo-fixture-rendered
 * types, Product Slider (`product_slider`) and Category Slider
 * (`category_slider`).
 */
class SliderRenderersTest extends TestCase
{
    private function xpathFor(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function render(string $type, array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => $id, 'props' => $props]);
    }

    // ── Carousel engine (via 'slider', the simplest real caller) ─────────

    public function test_carousel_renders_arrows_dots_and_keyboard_carousel_markup_for_multiple_slides(): void
    {
        $html = $this->render('slider', [
            'slides' => [
                ['image' => '', 'title' => 'اول'],
                ['image' => '', 'title' => 'دوم'],
            ],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//*[contains(@class,"tb-carousel-prev")]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(@class,"tb-carousel-next")]')->length);
        $this->assertSame(2, $xpath->query('//button[contains(@class,"tb-carousel-dot")]')->length);
        $this->assertSame(1, $xpath->query('//*[@role="region" and @aria-roledescription="carousel" and @aria-label]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(@class,"tb-carousel-dots") and @role="group"]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-carousel-dots") and @role="tablist"]')->length);
        $this->assertStringContainsString('tabindex="0"', $html, 'carousel root must be keyboard-focusable for arrow-key navigation');
    }

    public function test_carousel_collapses_a_single_slide_to_a_static_wrapper_with_no_controls(): void
    {
        $html = $this->render('slider', ['slides' => [['image' => '', 'title' => 'تنها اسلاید']]]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//*[contains(@class,"tb-carousel-single")]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-carousel-prev")]')->length, 'a single slide needs no navigation controls');
    }

    public function test_carousel_returns_empty_string_for_no_slides(): void
    {
        self::assertSame('', $this->render('slider', ['slides' => []]));
    }

    // ── Image Slider (`slider`) ───────────────────────────────────────────

    public function test_image_slider_falls_back_safely_when_a_slide_has_no_image(): void
    {
        $html = $this->render('slider', [
            'slides' => [
                ['image' => '', 'title' => 'بدون تصویر'],
                ['image' => 'https://example.test/real.jpg', 'title' => 'با تصویر'],
            ],
        ]);

        // Never a raw <img> with an empty src, and never a bare broken-image icon.
        self::assertStringNotContainsString('src=""', $html);
        self::assertStringContainsString('tb-img-fallback', $html);
        // The client-side onerror swap is present for the real image too.
        self::assertStringContainsString('onerror=', $html);
    }

    public function test_image_slider_honors_fade_transition_and_boxed_layout(): void
    {
        $html = $this->render('slider', [
            'slides' => [
                ['image' => '', 'title' => 'یک'],
                ['image' => '', 'title' => 'دو'],
            ],
            'transition' => 'fade',
            'boxed' => true,
        ]);

        // WU-B: the layout moved from inline styles to the shared sheet, so
        // the contract is the class in the markup plus the rule in the sheet.
        $sheet = (string) file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
        self::assertMatchesRegularExpression('/class="tb-carousel tb-carousel--fade[ "]/', $html);
        self::assertStringContainsString('.tb-carousel--fade .tb-carousel-track { display: grid;', $sheet, 'fade mode must stack slides via grid, not translateX');
        self::assertStringContainsString('tb-slider-boxed', $html);
        self::assertStringStartsWith('<div class="tb-slider-box">', $html);
        self::assertStringContainsString('.tb-slider-box { max-width: 72rem;', $sheet);
    }

    public function test_image_slider_renders_cta_button_when_slide_has_both_link_and_cta_text(): void
    {
        $html = $this->render('slider', [
            'slides' => [
                ['title' => 'یک', 'link' => '/shop', 'cta_text' => 'مشاهده فروشگاه'],
            ],
        ]);
        self::assertStringContainsString('مشاهده فروشگاه', $html);
        self::assertStringContainsString('tb-slide-cta', $html);
    }

    // ── Testimonial Slider (`testimonials_slider`) ────────────────────────

    public function test_testimonials_slider_carousel_mode_honors_columns_via_items_per_view(): void
    {
        $html = $this->render('testimonials_slider', [
            'mode' => 'carousel',
            'columns' => '2',
            'items' => [
                ['name' => 'الف', 'text' => 'خوب بود'],
                ['name' => 'ب', 'text' => 'عالی بود'],
                ['name' => 'ج', 'text' => 'راضی بودم'],
            ],
        ]);

        // The real regression this closes: the old carousel override always
        // rendered one card per slide regardless of `columns`.
        self::assertStringContainsString('--tb-items-desktop:2', $html);
    }

    /**
     * Real bug found via a full-page Playwright screenshot pass at tablet/
     * mobile widths: tablet and mobile both kept showing the DESKTOP item
     * count. Root cause: `--tb-items` was set inline on the carousel root
     * (`style="...--tb-items:{$desktop}"`) — an inline declaration always
     * wins over every rule in the accompanying <style> block, including
     * the `@media` overrides, regardless of which query actually matches.
     * The fix moves the desktop default into the <style> block itself (a
     * plain `#tb-{id}{--tb-items:var(--tb-items-desktop);}` rule) so it has
     * the SAME specificity as the two media-query overrides and source
     * order decides correctly. This asserts the specific inline-vs-block
     * placement that caused the bug, not just the presence of the values.
     */
    public function test_carousel_items_per_view_default_is_not_set_inline_on_the_root(): void
    {
        $html = $this->render('testimonials_slider', [
            'mode' => 'carousel',
            'columns' => '2',
            'items' => [
                ['name' => 'الف', 'text' => 'خوب بود'],
                ['name' => 'ب', 'text' => 'عالی بود'],
            ],
        ]);

        $xpath = $this->xpathFor($html);
        $root = $xpath->query('//*[contains(@class,"tb-carousel") and contains(@class,"tb-testimonials-slider")]')->item(0);
        self::assertNotNull($root);
        $inlineStyle = $root->getAttribute('style');

        self::assertStringNotContainsString('--tb-items:', $inlineStyle, '--tb-items itself must never be set inline — only -desktop/-tablet/-mobile may be, or the @media overrides below can never win');
        self::assertStringContainsString('--tb-items-desktop:', $inlineStyle);
        self::assertStringContainsString('#tb-', $html);
        self::assertMatchesRegularExpression('/#tb-[\w-]+\{--tb-items:var\(--tb-items-desktop\);\}/', $html, 'the desktop default must be assigned inside the <style> block, at the same specificity as the media-query overrides');
    }

    public function test_testimonials_slider_static_mode_still_honors_columns(): void
    {
        $html = $this->render('testimonials_slider', [
            'mode' => 'static',
            'columns' => '2',
            'items' => [['name' => 'الف', 'text' => 'متن']],
        ]);
        self::assertStringContainsString('cols-2', $html);
    }

    public function test_testimonials_slider_falls_back_safely_when_avatar_missing(): void
    {
        $html = $this->render('testimonials_slider', [
            'items' => [['name' => 'رضا احمدی', 'text' => 'متن نظر']],
        ]);
        self::assertStringContainsString('tb-testimonial-avatar', $html);
        self::assertStringContainsString('ر', $html); // initials fallback
    }

    public function test_testimonials_slider_ignores_malformed_non_array_item(): void
    {
        // A malformed items entry must be skipped, never crash the renderer.
        $html = $this->render('testimonials_slider', [
            'items' => ['not-an-object', ['name' => 'واقعی', 'text' => 'متن']],
        ]);
        self::assertStringContainsString('واقعی', $html);
    }

    // ── Logo/Partner Slider (`client_logos`) ──────────────────────────────

    public function test_client_logos_legacy_text_only_content_still_renders_unchanged(): void
    {
        $html = $this->render('client_logos', [
            'logos' => [['name' => 'برند قدیمی']],
        ]);
        self::assertStringContainsString('برند قدیمی', $html);
        self::assertStringContainsString('tb-logos-marquee', $html);
    }

    public function test_client_logos_with_real_image_renders_img_with_fallback(): void
    {
        $html = $this->render('client_logos', [
            'logos' => [['name' => 'برند تصویری', 'image' => 'https://example.test/logo.png', 'url' => 'https://example.test']],
            'mode' => 'carousel',
        ]);
        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('https://example.test/logo.png', $html);
        self::assertStringContainsString('tb-logos-carousel', $html);
    }

    /**
     * Real bug found via a full-page Playwright screenshot pass: unlike
     * every other slider's image (all routed through imageWithFallback()),
     * a logo `<img>` had no onerror handler at all — a genuinely broken
     * URL would show a raw broken-image icon on a real site.
     */
    public function test_client_logos_with_a_broken_image_url_has_a_client_side_fallback_swap(): void
    {
        $html = $this->render('client_logos', [
            'logos' => [['name' => 'لوگوی شکسته', 'image' => 'https://broken.invalid/x.png']],
        ]);
        self::assertStringContainsString('onerror=', $html);
        self::assertStringContainsString('لوگوی شکسته', $html, 'the name must still be present as the hidden fallback badge');
    }

    public function test_client_logos_static_mode_renders_grid(): void
    {
        $html = $this->render('client_logos', [
            'logos' => [['name' => 'یک'], ['name' => 'دو']],
            'mode' => 'static',
        ]);
        self::assertStringContainsString('tb-logos-static', $html);
    }

    // ── Product Slider (`product_slider`) — canonical demo fixture ───────

    public function test_product_slider_shows_a_real_demo_fixture_not_an_empty_div(): void
    {
        $html = $this->render('product_slider', []);
        // Closes the real preview/runtime parity gap found in audit: the
        // shared package previously had NO renderer at all for this type.
        self::assertNotSame('', trim($html));
        self::assertStringContainsString('کیف چرم دست‌دوز', $html);
        self::assertStringContainsString('tb-product-card', $html);
    }

    // ── Category Slider (`category_slider`) — new type, demo fixture ────

    public function test_category_slider_shows_a_real_demo_fixture(): void
    {
        $html = $this->render('category_slider', []);
        self::assertStringContainsString('tb-category-card', $html);
        self::assertStringContainsString('پوشاک', $html);
    }

    public function test_category_slider_grid_layout_skips_the_carousel(): void
    {
        $html = $this->render('category_slider', ['layout' => 'grid']);
        self::assertStringContainsString('tb-grid-cols-', $html);
        self::assertStringNotContainsString('tb-carousel-dot', $html);
    }

    public function test_slider_hides_controls_when_hide_controls_is_true(): void
    {
        $html = $this->render('slider', [
            'hide_controls' => true,
            'slides' => [
                ['image' => '', 'title' => 'اول'],
                ['image' => '', 'title' => 'دوم'],
            ],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-carousel-prev")]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-carousel-next")]')->length);
        $this->assertSame(0, $xpath->query('//button[contains(@class,"tb-carousel-dot")]')->length);
        $this->assertStringContainsString('tb-carousel--no-controls', $html);
    }

    public function test_slider_hides_controls_when_show_slider_buttons_is_false(): void
    {
        $html = $this->render('slider', [
            'show_slider_buttons' => false,
            'slides' => [
                ['image' => '', 'title' => 'اول'],
                ['image' => '', 'title' => 'دوم'],
            ],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-carousel-prev")]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-carousel-next")]')->length);
        $this->assertSame(0, $xpath->query('//button[contains(@class,"tb-carousel-dot")]')->length);
        $this->assertStringContainsString('tb-carousel--no-controls', $html);
    }

    public function test_slider_renders_rich_overlays_with_badges_descriptions_and_actions(): void
    {
        $html = $this->render('slider', [
            'overlay_style' => 'glass',
            'content_align' => 'center',
            'slides' => [
                [
                    'image' => 'https://example.test/solar.jpg',
                    'badge' => 'پیشرو در صنعت',
                    'title' => 'انرژی خورشیدی پایدار',
                    'highlight' => 'پایدار',
                    'subtitle' => 'راهکارهای نوین برق تجاری',
                    'description' => "خط اول توضیحات\nخط دوم توضیحات",
                    'cta_text' => 'مشاوره رایگان',
                    'link' => '/contact',
                    'secondary_cta_text' => 'کاتالوگ محصولات',
                    'secondary_link' => '/catalog',
                ],
            ],
        ]);

        $xpath = $this->xpathFor($html);
        $this->assertStringContainsString('tb-slide-overlay--glass', $html);
        $this->assertStringContainsString('tb-slide-caption--align-center', $html);
        $this->assertStringContainsString('tb-slide-caption--glass', $html);
        $this->assertStringContainsString('پیشرو در صنعت', $html);
        $this->assertStringContainsString('tb-slide-badge', $html);
        $this->assertStringContainsString('tb-slide-title-highlight', $html);
        $this->assertStringContainsString('راهکارهای نوین برق تجاری', $html);
        $this->assertStringContainsString('tb-slide-description', $html);
        $this->assertStringContainsString('خط اول توضیحات<br />', $html);
        $this->assertStringContainsString('tb-slide-actions', $html);
        $this->assertStringContainsString('tb-slide-cta', $html);
        $this->assertStringContainsString('مشاوره رایگان', $html);
        $this->assertStringContainsString('tb-slide-cta-sec', $html);
        $this->assertStringContainsString('کاتالوگ محصولات', $html);
    }

    public function test_slider_supports_per_slide_custom_alignment(): void
    {
        $html = $this->render('slider', [
            'content_align' => 'right',
            'slides' => [
                [
                    'title' => 'راست‌چین',
                    'align' => 'right',
                ],
                [
                    'title' => 'چپ‌چین',
                    'align' => 'left',
                ],
            ],
        ]);

        $this->assertStringContainsString('tb-slide-caption--align-right', $html);
        $this->assertStringContainsString('tb-slide-caption--align-left', $html);
    }
}
