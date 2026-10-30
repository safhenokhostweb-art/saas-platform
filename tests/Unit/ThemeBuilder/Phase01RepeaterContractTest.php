<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use DOMXPath;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use Tests\TestCase;

/**
 * Phase 0.1 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md) — the
 * schema contract the Canvas's generic repeater is driven by, and the
 * Bento / pricing / login URL hardening (C2–C5) behind it.
 */
class Phase01RepeaterContractTest extends TestCase
{
    // Phase 3b (ADR-069): spans on a 12-column grid.
    private const BENTO_SIZES = ['small', 'half', 'large', 'full', 'tall', 'featured'];

    private function render(string $type, array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => $id, 'props' => $props]);
    }

    private function xpathFor(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function normalizedSchema(string $type): array
    {
        return InspectorFieldRegistry::normalizeDefinition(BlockRegistry::get($type))['schema'];
    }

    // ── Registry contract (drives the Canvas repeater) ───────────────────

    public function test_every_canonical_repeater_declares_its_child_fields(): void
    {
        foreach (BlockRegistry::all() as $type => $definition) {
            if (!BlockRegistry::isCanonicalType($type)) {
                continue;
            }
            foreach ($definition['schema'] ?? [] as $key => $field) {
                if (($field['type'] ?? null) !== 'repeater') {
                    continue;
                }
                $this->assertArrayNotHasKey('item_schema', $field, "{$type}.{$key}: child fields belong under `fields`");
                $this->assertNotEmpty($field['fields'] ?? [], "{$type}.{$key}: a repeater without fields renders an empty editor");
                $this->assertContains($field['storage'] ?? 'array', ['array', 'json'], "{$type}.{$key}: unknown storage");
            }
        }
    }

    public function test_no_canonical_select_uses_object_list_options(): void
    {
        $check = function (string $where, array $field): void {
            if (($field['type'] ?? null) !== 'select') {
                return;
            }
            foreach ($field['options'] ?? [] as $value => $label) {
                $this->assertIsNotArray($label, "{$where}: options must be value => label, not objects");
            }
        };

        foreach (BlockRegistry::all() as $type => $definition) {
            if (!BlockRegistry::isCanonicalType($type)) {
                continue;
            }
            foreach ($definition['schema'] ?? [] as $key => $field) {
                $check("{$type}.{$key}", $field);
                foreach ($field['fields'] ?? [] as $childKey => $child) {
                    $check("{$type}.{$key}.{$childKey}", $child);
                }
            }
        }
    }

    public function test_bento_items_contract_matches_the_renderer(): void
    {
        $items = $this->normalizedSchema('bento_grid')['items'];

        $this->assertSame('repeater', $items['control']);
        $this->assertSame('array', $items['storage']);
        $this->assertSame(self::BENTO_SIZES, array_keys($items['fields']['size']['options']));
        $this->assertSame('small', $items['fields']['size']['default']);
        $this->assertSame('link', $items['fields']['link']['control']);
        $this->assertSame('media', $items['fields']['bg_image']['control']);
        $this->assertSame('', $items['fields']['bg_color_from']['default']);
        $this->assertSame('', $items['fields']['bg_color_to']['default']);

        foreach (BlockRegistry::defaultProps('bento_grid')['items'] as $i => $card) {
            $this->assertContains($card['size'], self::BENTO_SIZES, "default card {$i}");
            $this->assertSame('', $card['bg_color_from'], "default card {$i} must not carry a gradient");
            $this->assertSame('', $card['bg_color_to'], "default card {$i} must not carry a gradient");
        }
    }

    public function test_columns_ratios_is_one_column_picker_with_allowlisted_default(): void
    {
        $ratios = $this->normalizedSchema('columns')['ratios'];

        $this->assertSame('select', $ratios['control']);
        $this->assertSame('column_picker', $ratios['ui']);
        $this->assertSame(['1', '1/2,1/2', '1/3,1/3,1/3', '1/4,1/4,1/4,1/4'], array_map('strval', array_keys($ratios['options'])));
        $this->assertArrayHasKey($ratios['default'], $ratios['options']);
        $this->assertSame('1/2,1/2', BlockRegistry::defaultProps('columns')['ratios']);
        $this->assertSame('1/2,1/2', InspectorFieldRegistry::normalizeValue($ratios, '1/2,1/2', ['key' => 'ratios']));
    }

    public function test_faq_and_testimonials_expose_their_real_item_fields(): void
    {
        $faq = $this->normalizedSchema('faq')['items'];
        $this->assertSame('repeater', $faq['control']);
        $this->assertSame(['question', 'answer'], array_keys($faq['fields']));

        $testimonials = $this->normalizedSchema('testimonials_slider')['items'];
        $this->assertSame('repeater', $testimonials['control']);
        $this->assertSame(['name', 'role', 'company', 'avatar', 'text', 'rating', 'verified', 'date'], array_keys($testimonials['fields']));
        $this->assertSame(0, $testimonials['fields']['rating']['min']);
        $this->assertSame(5, $testimonials['fields']['rating']['max']);
        $this->assertIsArray(BlockRegistry::defaultProps('testimonials_slider')['items']);
    }

    // ── C2: Bento links ───────────────────────────────────────────────────

    public function test_bento_card_link_rejects_unsafe_schemes(): void
    {
        foreach (['javascript:alert(1)', ' JavaScript:alert(1)', 'data:text/html,x', 'vbscript:x'] as $unsafe) {
            $html = $this->render('bento_grid', ['items' => [['title' => 'کارت', 'link' => $unsafe]]]);
            $this->assertStringNotContainsStringIgnoringCase('javascript:', $html, $unsafe);
            $this->assertStringNotContainsStringIgnoringCase('vbscript:', $html, $unsafe);
            $this->assertStringNotContainsString('data:text', $html, $unsafe);
            $this->assertSame(0, $this->xpathFor($html)->query('//a[contains(@class,"tb-bento-item")]')->length, "{$unsafe} must not become a link card");
        }
    }

    public function test_bento_card_link_accepts_safe_plain_and_structured_links(): void
    {
        $html = $this->render('bento_grid', ['items' => [
            ['title' => 'الف', 'link' => '/products'],
            ['title' => 'ب', 'link' => ['url' => 'https://example.ir/x', 'target' => '_self']],
            ['title' => 'ج', 'link_url' => '/legacy'],
        ]]);
        $hrefs = array_map(fn ($a) => $a->getAttribute('href'), iterator_to_array($this->xpathFor($html)->query('//a[contains(@class,"tb-bento-item")]')));

        $this->assertSame(['/products', 'https://example.ir/x', '/legacy'], $hrefs);
    }

    public function test_bento_never_nests_an_anchor_inside_the_card_anchor(): void
    {
        $html = $this->render('bento_grid', ['items' => [
            ['title' => 'کارت', 'link' => '/a', 'link_url' => '/b', 'link_label' => 'مشاهده'],
        ]]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//a')->length);
        $this->assertSame(0, $xpath->query('//a//a')->length);
        $this->assertSame(1, $xpath->query('//a/descendant::span[contains(@class,"tb-bento-item-link")]')->length);
    }

    // ── C5: Bento sizes and overlay ────────────────────────────────────────

    public function test_bento_size_is_strictly_allowlisted(): void
    {
        $html = $this->render('bento_grid', ['items' => [
            ['title' => 'a', 'size' => 'featured'],
            ['title' => 'b', 'size' => 'normal'],
            ['title' => 'c', 'size' => 'huge" onclick="x'],
            ['title' => 'd', 'size' => 0],
        ]]);
        $classes = array_map(fn ($n) => $n->getAttribute('class'), iterator_to_array($this->xpathFor($html)->query('//*[contains(concat(" ", @class, " "), " tb-bento-item ")]')));

        $this->assertCount(4, $classes);
        $this->assertStringContainsString('tb-bento-item featured', $classes[0]);
        foreach ([1, 2, 3] as $i) {
            $this->assertStringContainsString('tb-bento-item small', $classes[$i]);
        }
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_bento_overlay_applies_only_over_a_background_image(): void
    {
        $plain = $this->render('bento_grid', ['items' => [[
            'title' => 'روشن', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d',
            'bg_color_from' => 'transparent', 'bg_color_to' => 'rgba(0,0,0,0.7)',
        ]]]);
        $this->assertStringNotContainsString('linear-gradient', $plain);
        $this->assertStringContainsString('background: #f0fdf4;', $plain);

        $photo = $this->render('bento_grid', ['items' => [[
            'title' => 'عکس', 'bg_image' => '/storage/media/card.jpg',
            'bg_color_from' => 'transparent', 'bg_color_to' => 'rgba(0,0,0,0.7)',
        ]]]);
        $this->assertStringContainsString("linear-gradient(to bottom, transparent, rgba(0,0,0,0.7)), url('/storage/media/card.jpg')", $photo);
    }

    public function test_bento_colours_cannot_inject_css_declarations(): void
    {
        $html = $this->render('bento_grid', ['items' => [[
            'title' => 'x', 'bg_color' => 'red;position:fixed;inset:0', 'text_color' => '#fff;background:url(x)',
        ]]]);

        $this->assertStringNotContainsString('position:fixed', $html);
        $this->assertStringNotContainsString('url(x)', $html);
        $this->assertStringContainsString('background: #f8fafc;', $html);
        $this->assertStringContainsString('color:#0f172a;', $html);
    }

    public function test_default_bento_renders_three_cards_without_gradients(): void
    {
        $html = $this->render('bento_grid', BlockRegistry::defaultProps('bento_grid'));

        $this->assertSame(3, $this->xpathFor($html)->query('//*[contains(concat(" ", @class, " "), " tb-bento-item ")]')->length);
        $this->assertStringNotContainsString('linear-gradient', $html);
    }

    // ── C3: other URL props ──────────────────────────────────────────────

    public function test_pricing_cta_and_login_urls_reject_unsafe_schemes(): void
    {
        $pricing = $this->render('pricing_cards', ['plans' => [['name' => 'پایه', 'price' => '۱۰', 'cta_text' => 'خرید', 'cta_url' => 'javascript:alert(1)']]]);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $pricing);
        $this->assertSame('#', $this->xpathFor($pricing)->query('//a[contains(@class,"tb-btn")]')->item(0)->getAttribute('href'));

        $safe = $this->render('pricing_cards', ['plans' => [['name' => 'پایه', 'cta_url' => '/checkout?plan=1']]]);
        $this->assertSame('/checkout?plan=1', $this->xpathFor($safe)->query('//a[contains(@class,"tb-btn")]')->item(0)->getAttribute('href'));

        $login = $this->render('login_register', ['login_url' => 'javascript:alert(1)', 'register_url' => ['url' => '/x']]);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $login);
        $hrefs = array_map(fn ($a) => $a->getAttribute('href'), iterator_to_array($this->xpathFor($login)->query('//a')));
        $this->assertSame(['/login', '/register'], $hrefs);
    }

    // ── Legacy shapes still render ───────────────────────────────────────

    public function test_testimonials_render_both_array_and_legacy_json_items(): void
    {
        $items = [['name' => 'سارا', 'role' => 'مدیر', 'text' => 'عالی', 'rating' => 4]];

        foreach ([$items, json_encode($items, JSON_UNESCAPED_UNICODE)] as $value) {
            $html = $this->render('testimonials_slider', ['mode' => 'static', 'items' => $value]);
            $this->assertStringContainsString('سارا', $html);
            // Phase 3b (ADR-069): SVG stars with a spoken rating.
            $this->assertStringContainsString('aria-label="امتیاز ۴ از ۵"', $html);
            $this->assertSame(4, substr_count($html, 'tb-star is-on'));
        }
    }

    public function test_faq_renders_repeater_items(): void
    {
        $html = $this->render('faq', ['items' => [
            ['_id' => 'item_a', 'question' => 'ارسال چقدر طول می‌کشد؟', 'answer' => '۲ تا ۳ روز کاری'],
            ['question' => '', 'answer' => 'بدون پرسش نمایش داده نمی‌شود'],
        ]]);
        $xpath = $this->xpathFor($html);

        $this->assertSame(1, $xpath->query('//details[contains(@class,"tb-faq-item")]')->length);
        $this->assertSame('ارسال چقدر طول می‌کشد؟', trim($xpath->query('//summary')->item(0)->textContent));
    }
}
