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
}
