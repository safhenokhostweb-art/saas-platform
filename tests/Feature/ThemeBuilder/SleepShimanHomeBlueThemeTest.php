<?php

namespace Tests\Feature\ThemeBuilder;

use Database\Seeders\SleepShimanHomeBlueThemeSeeder;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\SleepShimanBlockTypes;
use Tests\TestCase;

final class SleepShimanHomeBlueThemeTest extends TestCase
{
    public function test_theme_exposes_twenty_two_authorable_sections_with_global_chrome(): void
    {
        $definitions = SleepShimanBlockTypes::definitions();
        $pages = SleepShimanHomeBlueThemeSeeder::pages();

        self::assertCount(22, $definitions);
        self::assertCount(19, $pages['home']['blocks']);
        self::assertArrayHasKey('sleepshiman_header', $definitions);
        self::assertArrayHasKey('sleepshiman_navigation', $definitions);
        self::assertArrayHasKey('sleepshiman_footer', $definitions);
        foreach ($definitions as $type => $definition) {
            self::assertNotEmpty($definition['schema'], $type.' must expose Canvas controls.');
            self::assertArrayHasKey('defaultProps', $definition);
        }
    }

    public function test_manual_content_renders_escaped_with_logical_repeater_targets(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock([
            'id' => 'topics', 'type' => 'sleepshiman_topic_cards',
            'props' => ['title' => '<script>alert(1)</script>', 'items' => [[
                'title' => 'خواب راحت', 'description' => '<b>unsafe</b>', 'image' => 'images/blue-sleep.webp',
                'image_alt' => 'خواب آرام', 'url' => 'javascript:alert(1)',
            ]]],
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>unsafe</b>', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('data-tb-logical-group="items"', $html);
        self::assertStringContainsString('src="images/blue-sleep.webp"', $html);
    }

    public function test_product_binding_uses_real_domain_identity_while_manual_mode_uses_safe_link(): void
    {
        $renderer = (new BlockRenderer())->setContext(['theme_bindings' => ['products' => [
            'status' => 'resolved', 'source' => 'products', 'empty' => 'hide', 'items' => [[
                'id' => 41, 'title' => 'تشک واقعی', 'url' => '/shop/real', 'image_url' => '/storage/product.webp',
                'category' => 'تشک', 'price_html' => '۹۰۰٬۰۰۰ تومان', 'regular_price_html' => '۱٬۰۰۰٬۰۰۰ تومان',
                'discount_percent' => 10, 'in_stock' => true, 'requires_variant' => false, 'min_order_qty' => 1,
                'cart_url' => '/shop/cart', 'csrf_token' => 'token',
            ]],
        ]]]) ;
        $bound = $renderer->renderBlock(['id' => 'products', 'type' => 'sleepshiman_mattresses', 'props' => ['title' => 'محصولات', 'items' => []]]);
        $manual = (new BlockRenderer())->renderBlock(['id' => 'manual', 'type' => 'sleepshiman_mattresses', 'props' => [
            'title' => 'دستی', 'items' => [SleepShimanBlockTypes::productDefaults()[0]],
        ]]);

        self::assertStringContainsString('name="product_id" value="41"', $bound);
        self::assertStringContainsString('action="/shop/cart"', $bound);
        self::assertStringNotContainsString('name="product_id"', $manual);
        self::assertStringContainsString('href="/shop"', $manual);
    }

    public function test_empty_dynamic_source_can_fall_back_to_customer_manual_content(): void
    {
        $block = ['id' => 'products', 'type' => 'sleepshiman_mattresses', 'props' => [
            'title' => 'پشتیبان دستی', 'items' => [SleepShimanBlockTypes::productDefaults()[0]],
        ]];
        $renderer = (new BlockRenderer())->setContext(['theme_bindings' => ['products' => [
            'status' => 'resolved', 'source' => 'products', 'empty' => 'manual', 'items' => [],
        ]]]);

        $html = $renderer->renderBlock($block);
        self::assertStringContainsString('پشتیبان دستی', $html);
        self::assertStringContainsString('تشک دو نفره اسمالینا نخی', $html);
        self::assertStringNotContainsString('name="product_id"', $html);
    }

    public function test_featured_product_has_real_manual_contract_dynamic_render_and_manual_fallback(): void
    {
        $definition = SleepShimanBlockTypes::definitions()['sleepshiman_featured_product'];
        self::assertSame('items', $definition['dataBinding']['manual_prop']);
        self::assertSame('repeater', $definition['schema']['items']['type']);

        $manual = (new BlockRenderer())->renderBlock(['id' => 'featured-manual', 'type' => 'sleepshiman_featured_product', 'props' => $definition['defaultProps']]);
        self::assertStringContainsString('data-ss-content-source="manual"', $manual);
        self::assertStringContainsString('بالشت دست‌بافت نخ‌دوز', $manual);
        self::assertStringContainsString('images/pillow-green.webp', $manual);
        self::assertStringContainsString('جنس: نخ کامل', $manual);

        $dynamicRenderer = (new BlockRenderer())->setContext(['theme_bindings' => ['featured-dynamic' => [
            'status' => 'resolved', 'source' => 'products', 'empty' => 'manual', 'items' => [[
                'id' => 77, 'title' => 'محصول واقعی پویا', 'url' => '/shop/dynamic', 'image_url' => '/storage/dynamic.webp',
                'category' => 'بالش', 'price_html' => '۹۰۰٬۰۰۰ تومان', 'regular_price_html' => '۱٬۰۰۰٬۰۰۰ تومان',
                'discount_percent' => 10, 'in_stock' => true, 'requires_variant' => false, 'cart_url' => '/shop/cart',
                'csrf_token' => 'token', 'min_order_qty' => 1,
            ]],
        ]]]);
        $dynamic = $dynamicRenderer->renderBlock(['id' => 'featured-dynamic', 'type' => 'sleepshiman_featured_product', 'props' => $definition['defaultProps']]);
        self::assertStringContainsString('data-ss-content-source="dynamic"', $dynamic);
        self::assertStringContainsString('محصول واقعی پویا', $dynamic);
        self::assertStringContainsString('href="/shop/dynamic"', $dynamic);
        self::assertStringContainsString('src="/storage/dynamic.webp"', $dynamic);

        $fallbackRenderer = (new BlockRenderer())->setContext(['theme_bindings' => ['featured-fallback' => [
            'status' => 'resolved', 'source' => 'products', 'empty' => 'manual', 'items' => [],
        ]]]);
        $fallback = $fallbackRenderer->renderBlock(['id' => 'featured-fallback', 'type' => 'sleepshiman_featured_product', 'props' => $definition['defaultProps']]);
        self::assertStringContainsString('data-ss-content-source="manual"', $fallback);
        self::assertStringContainsString('بالشت دست‌بافت نخ‌دوز', $fallback);
    }

    public function test_handoff_specific_mixed_layouts_votes_and_trust_marks_render(): void
    {
        $definitions = SleepShimanBlockTypes::definitions();
        $renderer = new BlockRenderer();
        $bedding = $renderer->renderBlock(['id' => 'beds', 'type' => 'sleepshiman_bedding_products', 'props' => $definitions['sleepshiman_bedding_products']['defaultProps']]);
        $blankets = $renderer->renderBlock(['id' => 'blankets', 'type' => 'sleepshiman_blanket_products', 'props' => $definitions['sleepshiman_blanket_products']['defaultProps']]);
        $articles = $renderer->renderBlock(['id' => 'articles', 'type' => 'sleepshiman_articles', 'props' => $definitions['sleepshiman_articles']['defaultProps']]);
        $reviews = $renderer->renderBlock(['id' => 'reviews', 'type' => 'sleepshiman_reviews', 'props' => $definitions['sleepshiman_reviews']['defaultProps']]);
        $footer = $renderer->renderBlock(['id' => 'footer', 'type' => 'sleepshiman_footer', 'props' => $definitions['sleepshiman_footer']['defaultProps']]);

        self::assertSame(2, substr_count($bedding, 'class="ss-product"'));
        self::assertSame(4, substr_count($blankets, 'class="ss-product"'));
        self::assertStringContainsString('images/comforter.webp', $bedding.$blankets);
        self::assertStringContainsString('ss-article-layout', $articles);
        self::assertStringContainsString('ss-article-feature', $articles);
        self::assertStringContainsString('مفید · ۱۱', $reviews);
        self::assertStringContainsString('نامفید · ۰', $reviews);
        self::assertSame(4, substr_count($footer, 'data-tb-logical-group="trust_marks"'));
        self::assertStringContainsString('images/logo-enamad.png', $footer);
    }

    public function test_slider_sections_render_real_scoped_controls(): void
    {
        $html = (new BlockRenderer())->renderBlock([
            'id' => 'professional-mattresses',
            'type' => 'sleepshiman_mattresses',
            'props' => SleepShimanBlockTypes::definitions()['sleepshiman_mattresses']['defaultProps'],
        ]);

        self::assertStringContainsString('id="ss-carousel-professional-mattresses"', $html);
        self::assertStringContainsString('data-ss-carousel', $html);
        self::assertSame(2, substr_count($html, 'data-ss-scroll="#ss-carousel-professional-mattresses"'));
        self::assertStringContainsString('aria-label="کنترل اسلایدر"', $html);
    }

    public function test_asset_manifest_is_local_complete_and_hash_pinned(): void
    {
        $manifest = SleepShimanHomeBlueThemeSeeder::themeAssetsManifest();
        self::assertSame(1, $manifest['version']);
        self::assertGreaterThanOrEqual(24, count($manifest['assets']));
        foreach ($manifest['assets'] as $asset) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $asset['sha256']);
            self::assertStringContainsString('/theme-assets/sleepshiman-home-blue/1.0.0/', $asset['source']);
            self::assertFileExists(public_path('theme-assets/sleepshiman-home-blue/1.0.0/'.$asset['path']));
            if (str_starts_with($asset['path'], 'images/')) {
                self::assertGreaterThan(0, $asset['width'] ?? 0);
                self::assertGreaterThan(0, $asset['height'] ?? 0);
                self::assertNotSame('', $asset['alt'] ?? '');
            }
        }
    }
}
