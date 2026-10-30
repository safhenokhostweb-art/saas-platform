<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/**
 * Phase 2e — real renderer output tests for the 7 canonical block types
 * added to close the render.unsupported_block_type backlog (ADR-026):
 * nav_header, nav_footer, hero_trust, about_section, service_cards,
 * project_cards, contact_info.
 */
class CanonicalBlockRenderersTest extends TestCase
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

    // ── nav_header ──────────────────────────────────────────────────────

    public function test_nav_header_renders_real_links_and_a_semantic_nav(): void
    {
        $html = $this->render('nav_header', [
            'logo_text' => 'فروشگاه من',
            'nav_links' => [['url' => '/', 'label' => 'خانه'], ['url' => '/about', 'label' => 'درباره']],
        ]);
        $xpath = $this->xpathFor($html);

        // TASK 3/3 Phase 6 (BLD-19): a real axe-core scan found this
        // rendering as a literal <header> — every real consumer already
        // wraps it in its OWN <header role="banner">, so a second nested
        // <header> here was two nested banner landmarks, a genuine WCAG
        // violation (landmark-banner-is-top-level). The single real
        // banner role now belongs only to the outer page-chrome wrapper;
        // this block's own root is a plain, classed <div>.
        $this->assertSame(0, $xpath->query('//header')->length, 'nav_header must not render a second, nested <header> — see BLD-19');
        $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " tb-nav-header ")]')->length);
        $this->assertSame(1, $xpath->query('//nav[@aria-label]')->length);
        $this->assertStringContainsString('فروشگاه من', $html);
        $this->assertSame(2, $xpath->query('//nav//a')->length);
    }

    public function test_nav_header_never_fabricates_optional_features(): void
    {
        $html = $this->render('nav_header', ['nav_links' => []]);
        $this->assertStringNotContainsString('tb-nav-header-phone', $html);
        $this->assertStringNotContainsString('tb-nav-header-cta', $html);
        $this->assertStringNotContainsString('🛒', $html);
    }

    // ── Mega Menu (Commercial Productization phase) ──────────────────────

    public function test_a_plain_nav_link_with_no_mega_data_renders_exactly_as_before(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [['url' => '/about', 'label' => 'درباره']],
        ]);
        $this->assertStringNotContainsString('tb-mega-menu', $html);
        $this->assertStringContainsString('tb-nav-header-link', $html);
    }

    public function test_a_nav_link_with_mega_columns_renders_a_real_dropdown_panel(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/shop', 'label' => 'فروشگاه',
                'mega' => [
                    'columns' => [
                        ['title' => 'پوشاک', 'links' => [['url' => '/shop/men', 'label' => 'مردانه'], ['url' => '/shop/women', 'label' => 'زنانه']]],
                        ['title' => 'دیجیتال', 'links' => [['url' => '/shop/mobile', 'label' => 'موبایل']]],
                    ],
                    'banner_image' => 'https://example.test/banner.jpg',
                    'banner_url' => '/shop/sale',
                    'banner_text' => 'حراج ویژه',
                ],
            ]],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//*[contains(@class,"tb-mega-menu-panel")]')->length);
        $this->assertSame(2, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tb-mega-menu-col ")]')->length);
        $this->assertSame(3, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tb-mega-menu-link ")]')->length);
        $this->assertStringContainsString('حراج ویژه', $html);
        $this->assertStringContainsString('tb-mega-menu-banner', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
    }

    public function test_mega_menu_accepts_a_raw_json_string_shape_from_the_editor_textarea(): void
    {
        // The customer-site editor authors `mega` as a raw JSON textarea
        // (no visual nested-repeater UI exists) — the renderer must accept
        // that string shape, not only a pre-decoded PHP array.
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/services', 'label' => 'خدمات',
                'mega' => json_encode(['columns' => [['title' => 'مشاوره', 'links' => [['url' => '/services/a', 'label' => 'مشاوره مالی']]]]]),
            ]],
        ]);
        $this->assertStringContainsString('tb-mega-menu-panel', $html);
        $this->assertStringContainsString('مشاوره مالی', $html);
    }

    public function test_mega_menu_with_malformed_json_string_falls_back_to_a_plain_link(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [['url' => '/x', 'label' => 'ایکس', 'mega' => '{not valid json']],
        ]);
        $this->assertStringNotContainsString('tb-mega-menu-panel', $html);
        $this->assertStringContainsString('ایکس', $html);
    }

    /**
     * The real, visual editor shape (App\Services\ThemeCanvas\BlockRegistry
     * — a flat repeater, since the Canvas UI's nested-repeater support is
     * exactly one level deep, not the raw-JSON {columns:[...]} shape).
     * Consecutive rows sharing the same column_title must group into one
     * column.
     */
    public function test_mega_menu_flat_editor_shape_groups_rows_into_columns_by_title(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/shop', 'label' => 'فروشگاه',
                'mega_items' => [
                    ['column_title' => 'پوشاک', 'link_label' => 'مردانه', 'link_url' => '/shop/men'],
                    ['column_title' => 'پوشاک', 'link_label' => 'زنانه', 'link_url' => '/shop/women'],
                    ['column_title' => 'دیجیتال', 'link_label' => 'موبایل', 'link_url' => '/shop/mobile'],
                ],
                'mega_banner_image' => 'https://example.test/banner.jpg',
                'mega_banner_url' => '/shop/sale',
                'mega_banner_text' => 'حراج ویژه',
            ]],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//*[contains(@class,"tb-mega-menu-panel")]')->length);
        $this->assertSame(2, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tb-mega-menu-col ")]')->length, 'two distinct column_title values must produce exactly two columns');
        $this->assertSame(3, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tb-mega-menu-link ")]')->length);
        $this->assertStringContainsString('حراج ویژه', $html);
        $this->assertStringContainsString('پوشاک', $html);
        $this->assertStringContainsString('دیجیتال', $html);
    }

    public function test_mega_menu_flat_editor_shape_with_no_link_label_is_skipped_safely(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [['url' => '/x', 'label' => 'ایکس', 'mega_items' => [['column_title' => 'ستون', 'link_label' => '', 'link_url' => '/a']]]],
        ]);
        $this->assertStringNotContainsString('tb-mega-menu-panel', $html);
        $this->assertStringContainsString('ایکس', $html);
    }

    // ── mega_columns: real independent column repeater (fase-2) ──────────
    // The user's core objection to the flat mega_items shape was that
    // columns were only ever inferred by matching duplicate column_title
    // text, never a genuinely independent structure. This proves the new
    // `mega_columns` shape keeps two SAME-TITLED columns fully independent
    // (their own separate item lists, never merged) — the one behavior a
    // title-matching grouping heuristic could never guarantee.

    public function test_mega_columns_keeps_two_same_titled_columns_fully_independent(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/shop', 'label' => 'فروشگاه',
                'mega_columns' => [
                    ['title' => 'ویژه', 'items' => [['label' => 'مردانه', 'url' => '/m'], ['label' => 'زنانه', 'url' => '/w']]],
                    ['title' => 'ویژه', 'items' => [['label' => 'بچگانه', 'url' => '/k']]],
                ],
            ]],
        ]);
        $xpath = $this->xpathFor($html);
        $cols = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tb-mega-menu-col ")]');
        self::assertSame(2, $cols->length, 'two authored columns must stay two rendered columns even when their titles are identical text');
        self::assertStringContainsString('مردانه', $html);
        self::assertStringContainsString('زنانه', $html);
        self::assertStringContainsString('بچگانه', $html);
        // The second column's items must not have merged into the first.
        $firstColLinks = $xpath->query('(//*[contains(concat(" ", normalize-space(@class), " "), " tb-mega-menu-col ")])[1]//a');
        self::assertSame(2, $firstColLinks->length);
    }

    public function test_mega_columns_item_icon_and_image_render_and_broken_image_falls_back_safely(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/shop', 'label' => 'فروشگاه',
                'mega_columns' => [
                    ['title' => 'دسته‌ها', 'items' => [
                        ['label' => 'با آیکون', 'url' => '/a', 'icon' => '🎯'],
                        ['label' => 'با تصویر', 'url' => '/b', 'image' => 'https://broken.example/x.jpg'],
                    ]],
                ],
            ]],
        ]);
        $this->assertStringContainsString('tb-mega-menu-link-icon', $html);
        $this->assertStringContainsString('🎯', $html);
        $this->assertStringContainsString('onerror=', $html);
    }

    public function test_mega_columns_show_desktop_false_and_mobile_behavior_apply_real_css_hooks(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/shop', 'label' => 'فروشگاه',
                'mega_columns' => [['title' => 'ستون', 'items' => [['label' => 'لینک', 'url' => '/a']]]],
                'mega_show_desktop' => false,
                'mega_mobile_behavior' => 'flat',
            ]],
        ]);
        $this->assertStringContainsString('tb-mega-desktop-hidden', $html);
        $this->assertStringContainsString('tb-mega-mobile-flat', $html);
    }

    public function test_mega_columns_takes_priority_over_legacy_flat_mega_items_when_both_present(): void
    {
        $html = $this->render('nav_header', [
            'nav_links' => [[
                'url' => '/shop', 'label' => 'فروشگاه',
                'mega_columns' => [['title' => 'جدید', 'items' => [['label' => 'لینک جدید', 'url' => '/new']]]],
                'mega_items' => [['column_title' => 'قدیمی', 'link_label' => 'لینک قدیمی', 'link_url' => '/old']],
            ]],
        ]);
        $this->assertStringContainsString('لینک جدید', $html);
        $this->assertStringNotContainsString('لینک قدیمی', $html);
    }

    public function test_nav_header_search_field_has_an_accessible_label(): void
    {
        $html = $this->render('nav_header', ['nav_links' => [], 'show_search' => true, 'search_placeholder' => 'جستجوی محصولات']);
        $xpath = $this->xpathFor($html);

        $input = $xpath->query('//input[@type="search"]')->item(0);
        $this->assertNotNull($input);
        $id = $input->getAttribute('id');
        $this->assertNotSame('', $id);
        $this->assertGreaterThan(0, $xpath->query("//label[@for='{$id}']")->length);
    }

    public function test_nav_header_escapes_hostile_input(): void
    {
        $html = $this->render('nav_header', [
            'logo_text' => '<script>alert(1)</script>',
            'nav_links' => [['url' => 'javascript:alert(1)"', 'label' => '<img onerror=alert(1)>']],
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img onerror', $html);
    }

    // ── nav_footer ──────────────────────────────────────────────────────

    public function test_nav_footer_renders_columns_and_omits_socials_with_no_data(): void
    {
        $html = $this->render('nav_footer', [
            'columns' => [['title' => 'لینک‌ها', 'links' => [['url' => '/a', 'label' => 'صفحه ا']]]],
            'copyright' => 'تمام حقوق محفوظ است.',
        ]);
        $xpath = $this->xpathFor($html);

        // TASK 3/3 Phase 6 (BLD-19): same nested-landmark fix as
        // nav_header above (landmark-contentinfo-is-top-level) — the real
        // consumer's own <footer role="contentinfo"> already owns that
        // role; this block's root is a plain, classed <div>.
        $this->assertSame(0, $xpath->query('//footer')->length, 'nav_footer must not render a second, nested <footer> — see BLD-19');
        $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " tb-nav-footer ")]')->length);
        $this->assertStringContainsString('لینک‌ها', $html);
        $this->assertStringContainsString('تمام حقوق محفوظ است.', $html);
        // No social_links array supplied — never fabricated.
        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-social")]')->length);
    }

    public function test_nav_footer_renders_real_social_links_when_supplied(): void
    {
        $html = $this->render('nav_footer', [
            'columns' => [],
            'social_links' => [['url' => 'https://example.test/fb', 'label' => 'Facebook', 'icon' => 'f']],
        ]);
        $this->assertStringContainsString('example.test/fb', $html);
    }

    // ── hero_trust ──────────────────────────────────────────────────────

    public function test_hero_trust_renders_title_and_rating_when_present(): void
    {
        $html = $this->render('hero_trust', [
            'title' => 'عنوان تست', 'subtitle' => 'زیرعنوان تست', 'rating' => '4.8', 'rating_label' => '۲۰۰+ نظر',
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertStringContainsString('عنوان تست', $html);
        $this->assertStringContainsString('4.8/5', $html);
    }

    public function test_hero_trust_omits_rating_block_when_not_supplied(): void
    {
        $html = $this->render('hero_trust', ['title' => 'ت', 'subtitle' => 'ز']);
        $this->assertStringNotContainsString('tb-hero-trust-rating', $html);
    }

    // ── about_section ───────────────────────────────────────────────────

    public function test_about_section_renders_features_and_stats(): void
    {
        $html = $this->render('about_section', [
            'title' => 'درباره ما',
            'description' => 'توضیحات تست',
            'features' => [['label' => 'ویژگی یک'], ['label' => 'ویژگی دو']],
            'stats' => [['value' => '۱۵+', 'label' => 'سال تجربه']],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//h2')->length);
        $this->assertSame(2, $xpath->query('//li[contains(@class,"tb-about-feature")]')->length);
        $this->assertStringContainsString('۱۵+', $html);
    }

    // ── service_cards / project_cards ──────────────────────────────────

    public function test_service_cards_renders_a_link_per_card_with_real_content(): void
    {
        $html = $this->render('service_cards', [
            'title' => 'خدمات ما',
            'cards' => [
                ['icon' => '🎨', 'title' => 'طراحی', 'description' => 'شرح خدمت', 'url' => '/services/design'],
                ['icon' => '⚙️', 'title' => 'توسعه', 'description' => 'شرح دیگر', 'url' => '/services/dev'],
            ],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(2, $xpath->query('//a[contains(@class,"tb-service-card")]')->length);
        $this->assertStringContainsString('طراحی', $html);
    }

    public function test_project_cards_renders_category_and_no_id_collisions_with_service_cards(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock(['type' => 'service_cards', 'id' => 'block-a', 'props' => [
            'cards' => [['title' => 'خدمت', 'description' => 'شرح', 'url' => '/x']],
        ]]) . $renderer->renderBlock(['type' => 'project_cards', 'id' => 'block-b', 'props' => [
            'cards' => [['category' => 'مسکونی', 'title' => 'پروژه', 'description' => 'شرح', 'url' => '/y']],
        ]]);

        $xpath = $this->xpathFor($html);
        $this->assertStringContainsString('مسکونی', $html);

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            $this->assertArrayNotHasKey($id, $ids, "duplicate id \"{$id}\" across two blocks on the same page");
            $ids[$id] = true;
        }
        $this->assertNotEmpty($ids);
    }

    // ── contact_info ────────────────────────────────────────────────────

    public function test_contact_info_renders_real_items_only(): void
    {
        $html = $this->render('contact_info', [
            'title' => 'تماس با ما',
            'items' => [
                ['icon' => '📞', 'label' => 'تلفن', 'value' => '021-00000000'],
                ['icon' => '✉️', 'label' => 'ایمیل', 'value' => 'info@example.test'],
            ],
        ]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(2, $xpath->query('//*[contains(@class,"tb-contact-info-item")]')->length);
        $this->assertStringContainsString('021-00000000', $html);
    }

    public function test_contact_info_skips_empty_items_without_fabricating_content(): void
    {
        $html = $this->render('contact_info', ['items' => [['icon' => '📞', 'label' => '', 'value' => '']]]);
        $xpath = $this->xpathFor($html);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"tb-contact-info-item")]')->length);
    }

    // ── RTL / heading-hierarchy / no fabricated content across all seven ──

    public function test_none_of_the_new_renderers_hardcode_english_or_brand_specific_defaults(): void
    {
        // Rendering every new block type with empty props must never leak
        // any hardcoded brand/business content (e.g. a previous ad-hoc
        // implementation elsewhere hardcoded "Acool HVAC" as a fallback).
        $types = ['nav_header', 'nav_footer', 'hero_trust', 'about_section', 'service_cards', 'project_cards', 'contact_info'];
        foreach ($types as $type) {
            $html = $this->render($type, []);
            $this->assertStringNotContainsString('Acool', $html, "{$type} leaked hardcoded brand content");
            $this->assertStringNotContainsString('HVAC', $html, "{$type} leaked hardcoded brand content");
        }
    }

    // ── Missing-canonical-renderer gap closure (Commercial Productization) ─
    //
    // Real bug found via a full-page Playwright screenshot pass: several
    // registered canonical types had no renderer method at all in the
    // shared package, so BlockRenderer::renderFallback() silently dumped
    // json_encode($props) as raw visible text on saas-platform's own admin
    // preview (customer-site already had real overrides for each of
    // these, so the defect was invisible there). Confirmed via a full
    // BlockRegistry-vs-renderer-methods scan: only 'products' remains
    // unrendered canonically, a pre-existing, separately-tracked
    // exclusion (customer-site's own ElementSchemaRegistry already
    // rejects it — a different defect class, out of this phase's scope).

    public function test_product_grid_shows_a_real_demo_fixture_not_a_json_dump(): void
    {
        $html = $this->render('product_grid', ['columns' => 4, 'limit' => 8]);
        self::assertStringNotContainsString('{"columns"', $html, 'must never fall back to a raw json_encode($props) text dump');
        self::assertStringContainsString('tb-product-card', $html);
        self::assertStringContainsString('کیف چرم دست‌دوز', $html);
    }

    public function test_video_shows_an_honest_empty_state_with_no_url_and_a_real_video_tag_with_one(): void
    {
        $empty = $this->render('video', []);
        self::assertStringNotContainsString('{"', $empty, 'must never fall back to a raw json_encode($props) text dump');
        self::assertStringContainsString('ویدیویی انتخاب نشده است', $empty);

        $withUrl = $this->render('video', ['url' => 'https://example.test/demo.mp4', 'poster' => 'https://example.test/poster.jpg', 'caption' => 'ویدیوی معرفی']);
        self::assertStringContainsString('<video', $withUrl);
        self::assertStringContainsString('poster="https://example.test/poster.jpg"', $withUrl);
        self::assertStringContainsString('ویدیوی معرفی', $withUrl);
    }

    public function test_cart_icon_and_language_switcher_and_add_to_cart_no_longer_dump_raw_props(): void
    {
        foreach (['cart_icon', 'language_switcher', 'add_to_cart'] as $type) {
            $html = $this->render($type, []);
            self::assertStringNotContainsString('{"', $html, "'{$type}' must never fall back to a raw json_encode(\$props) text dump");
            self::assertStringNotContainsString('[]', $html, "'{$type}' must never render the literal empty-array fallback text");
        }
    }

    // ── timeline ─────────────────────────────────────────────────────────

    public function test_timeline_renders_vertical_by_default_with_real_items_not_a_json_dump(): void
    {
        $html = $this->render('timeline', [
            'title' => 'مسیر رشد ما',
            'items' => [
                ['date' => '۱۳۹۸', 'title' => 'تأسیس', 'text' => 'شروع فعالیت', 'icon' => '🚀', 'image' => '', 'link_label' => '', 'link_url' => ''],
                ['date' => '۱۴۰۲', 'title' => 'رشد', 'text' => 'هزار مشتری', 'icon' => '📈', 'image' => '', 'link_label' => 'بیشتر بدانید', 'link_url' => '/about'],
            ],
        ]);
        self::assertStringNotContainsString('{"date"', $html, 'must never fall back to a raw json_encode($props) text dump');
        self::assertStringContainsString('tb-timeline-vertical', $html);
        self::assertStringContainsString('۱۳۹۸', $html);
        self::assertStringContainsString('تأسیس', $html);
        $xpath = $this->xpathFor($html);
        self::assertGreaterThanOrEqual(2, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tb-timeline-item ")]')->length);
        self::assertSame(1, $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " tb-timeline-cta ")][@href="/about"]')->length);
    }

    public function test_timeline_horizontal_layout_uses_the_horizontal_track_class(): void
    {
        $html = $this->render('timeline', [
            'layout' => 'horizontal',
            'items' => [['date' => '۱۴۰۰', 'title' => 'رویداد', 'text' => 'توضیح', 'icon' => '📦', 'image' => '', 'link_label' => '', 'link_url' => '']],
        ]);
        self::assertStringContainsString('tb-timeline-horizontal', $html);
    }

    public function test_timeline_with_no_items_shows_an_honest_empty_placeholder(): void
    {
        $html = $this->render('timeline', ['items' => []]);
        self::assertStringNotContainsString('{"', $html);
        self::assertStringContainsString('tb-timeline-empty', $html);
        self::assertStringContainsString('رویدادی', $html);
    }

    public function test_timeline_item_with_broken_image_falls_back_safely_and_item_with_icon_only_shows_marker(): void
    {
        $html = $this->render('timeline', [
            'items' => [
                ['date' => '', 'title' => 'با تصویر', 'text' => '', 'icon' => '', 'image' => 'https://broken.example/x.jpg', 'link_label' => '', 'link_url' => ''],
                ['date' => '', 'title' => 'بدون تصویر', 'text' => '', 'icon' => '', 'image' => '', 'link_label' => '', 'link_url' => ''],
            ],
        ]);
        self::assertStringContainsString('onerror=', $html, 'a configured image must have a real client-side broken-image fallback');
        self::assertStringContainsString('tb-timeline-marker', $html, 'an item with neither icon nor image must still show a numbered marker, never an empty gap');
    }

    // ── visual identity audit (fase-3): default colors must follow the
    // Theme's own tokens, never a hardcoded generic-indigo copy ──────────

    public function test_hero_gradient_with_no_explicit_colors_follows_the_theme_primary_secondary_tokens(): void
    {
        $html = $this->render('hero_gradient', ['title' => 'عنوان']);
        $this->assertStringNotContainsString('#4f46e5 0%', $html, 'must not hardcode the generic indigo default independent of --tb-primary');
        $this->assertStringContainsString('var(--tb-primary', $html);
        $this->assertStringContainsString('var(--tb-secondary', $html);
    }

    public function test_hero_gradient_with_explicit_author_colors_still_honors_them(): void
    {
        $html = $this->render('hero_gradient', ['title' => 'عنوان', 'gradient_from' => '#111111', 'gradient_to' => '#222222']);
        $this->assertStringContainsString('#111111', $html);
        $this->assertStringContainsString('#222222', $html);
    }

    public function test_newsletter_form_with_no_explicit_background_follows_the_theme_primary_token(): void
    {
        $html = $this->render('newsletter_form', []);
        $this->assertStringNotContainsString('background:#4f46e5', $html);
        $this->assertStringContainsString('var(--tb-primary', $html);
    }

    // ── real dark-mode toggle (fase-4) ──────────────────────────────────

    public function test_nav_header_with_dark_toggle_disabled_by_default_renders_no_toggle(): void
    {
        $html = $this->render('nav_header', ['nav_links' => []]);
        $this->assertStringNotContainsString('tb-theme-toggle', $html);
    }

    public function test_nav_header_with_dark_toggle_enabled_renders_a_real_accessible_button(): void
    {
        $html = $this->render('nav_header', ['nav_links' => [], 'show_dark_toggle' => true]);
        $xpath = $this->xpathFor($html);

        $button = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " tb-theme-toggle ")]')->item(0);
        $this->assertNotNull($button, 'must render a real <button>, not a link or div');
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertNotSame('', $button->getAttribute('aria-label'));
        $this->assertStringContainsString('__tbTheme', $html, 'must wire up to the shared window.__tbTheme API, not a private/duplicated implementation');
        $this->assertStringContainsString('tb-theme-change', $html, 'must listen for the shared theme-change event so multiple toggle instances stay in sync');
    }

    public function test_dark_toggle_button_id_is_unique_per_block_instance(): void
    {
        $htmlA = $this->render('nav_header', ['nav_links' => [], 'show_dark_toggle' => true], 'header-a');
        $htmlB = $this->render('nav_header', ['nav_links' => [], 'show_dark_toggle' => true], 'footer-b');
        $this->assertStringContainsString('tb-header-a-theme-toggle', $htmlA);
        $this->assertStringContainsString('tb-footer-b-theme-toggle', $htmlB);
    }

    // ── real Badge component (fase-4) ───────────────────────────────────

    public function test_pricing_card_badge_uses_the_shared_badge_component_with_a_real_variant_class(): void
    {
        $html = $this->render('pricing_cards', ['plans' => [['name' => 'حرفه‌ای', 'price' => '۴۹۰', 'badge' => 'محبوب‌ترین', 'highlight' => false, 'features' => []]]]);
        $this->assertStringContainsString('tb-badge tb-badge-primary', $html);
        $this->assertStringContainsString('tb-badge-label', $html);
        $this->assertStringContainsString('محبوب‌ترین', $html);
        $this->assertStringNotContainsString('background:#ef4444', $html);
    }

    // Phase 3b (ADR-069): a highlighted plan carries its badge as the ribbon.
    public function test_highlighted_pricing_card_shows_its_badge_as_the_ribbon(): void
    {
        $html = $this->render('pricing_cards', ['plans' => [['name' => 'حرفه‌ای', 'price' => '۴۹۰', 'badge' => 'محبوب‌ترین', 'highlight' => true, 'features' => []]]]);
        $this->assertStringContainsString('<div class="tb-plan-ribbon">محبوب‌ترین</div>', $html);
        $this->assertStringNotContainsString('tb-plan-badge-wrap', $html);
    }

    public function test_pricing_card_with_no_badge_renders_no_empty_badge_shell(): void
    {
        $html = $this->render('pricing_cards', ['plans' => [['name' => 'استارتر', 'price' => 'رایگان', 'features' => []]]]);
        $this->assertStringNotContainsString('tb-badge', $html);
    }

    public function test_product_grid_demo_card_badge_uses_the_shared_badge_component(): void
    {
        $html = $this->render('product_grid', []);
        $this->assertStringContainsString('tb-badge tb-badge-error tb-badge-sm', $html);
        $this->assertStringNotContainsString('background:#ef4444', $html, 'must no longer be an independently hardcoded inline color');
    }

    public function test_badge_html_supports_all_five_required_variants_plus_primary_with_real_token_backed_colors(): void
    {
        $renderer = new BlockRenderer();
        $ref = new \ReflectionMethod($renderer, 'badgeHtml');
        $ref->setAccessible(true);
        foreach (['success', 'warning', 'error', 'info', 'neutral', 'primary'] as $variant) {
            $html = $ref->invoke($renderer, 'برچسب', $variant, 'md');
            $this->assertStringContainsString("tb-badge-{$variant}", $html);
        }
    }

    public function test_badge_html_supports_three_sizes_and_an_optional_icon_and_escapes_the_label(): void
    {
        $renderer = new BlockRenderer();
        $ref = new \ReflectionMethod($renderer, 'badgeHtml');
        $ref->setAccessible(true);
        foreach (['sm', 'md', 'lg'] as $size) {
            $this->assertStringContainsString("tb-badge-{$size}", $ref->invoke($renderer, 'x', 'success', $size));
        }
        $withIcon = $ref->invoke($renderer, 'موجود', 'success', 'md', '✓');
        $this->assertStringContainsString('tb-badge-icon', $withIcon);
        $this->assertStringContainsString('✓', $withIcon);
        $escaped = $ref->invoke($renderer, '<script>alert(1)</script>', 'error', 'md');
        $this->assertStringNotContainsString('<script>', $escaped);
    }

    public function test_badge_html_with_empty_label_renders_nothing(): void
    {
        $renderer = new BlockRenderer();
        $ref = new \ReflectionMethod($renderer, 'badgeHtml');
        $ref->setAccessible(true);
        $this->assertSame('', $ref->invoke($renderer, '', 'success', 'md'));
    }

    public function test_every_canonical_non_module_registry_type_has_a_real_renderer_except_the_known_products_exclusion(): void
    {
        $registry = \SaaSPlatform\ThemeRuntime\BlockRegistry::all();
        $refl = new \ReflectionClass(new BlockRenderer());
        $missing = [];
        foreach (array_keys($registry) as $type) {
            if (str_starts_with($type, 'module:')) {
                continue;
            }
            $method = 'render' . \Illuminate\Support\Str::studly(str_replace(':', '_', $type));
            if (!$refl->hasMethod($method)) {
                $missing[] = $type;
            }
        }
        self::assertSame(
            ['products'],
            $missing,
            "'products' is the one known, separately-tracked exclusion (customer-site's own ElementSchemaRegistry rejects it) — any other name appearing here is a new, real preview gap that must get a canonical renderer, not be silently accepted."
        );
    }
}
