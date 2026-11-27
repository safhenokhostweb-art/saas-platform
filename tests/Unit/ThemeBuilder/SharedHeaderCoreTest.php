<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Header\HeaderDataResolver;
use SaaSPlatform\ThemeRuntime\Header\SharedHeaderSchema;
use Tests\TestCase;

class SharedHeaderCoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BlockRegistry::resetHostTypes();
    }

    public function test_all_header_blocks_have_identical_unified_schema_keys(): void
    {
        $all = BlockRegistry::all();
        $this->assertArrayHasKey('nav_header', $all);
        $this->assertArrayHasKey('header_glass_nimbus', $all);
        $this->assertArrayHasKey('header_modern_v1', $all);

        $navHeaderSchema = $all['nav_header']['schema'];
        $nimbusSchema = $all['header_glass_nimbus']['schema'];
        $modernSchema = $all['header_modern_v1']['schema'];

        $this->assertSame(
            array_keys($navHeaderSchema),
            array_keys($nimbusSchema),
            'nav_header and header_glass_nimbus must share 100% identical schema keys'
        );

        $this->assertSame(
            array_keys($navHeaderSchema),
            array_keys($modernSchema),
            'nav_header and header_modern_v1 must share 100% identical schema keys'
        );

        // Required 3-way menu source fields
        $this->assertArrayHasKey('menu_source', $navHeaderSchema);
        $this->assertArrayHasKey('selected_menu_id', $navHeaderSchema);
        $this->assertArrayHasKey('selected_page_ids', $navHeaderSchema);
        $this->assertArrayHasKey('nav_links', $navHeaderSchema);

        // Required icon fields are of type 'icon'
        $this->assertSame('icon', $navHeaderSchema['logo_icon']['type']);
        $this->assertSame('icon', $navHeaderSchema['cta_icon']['type']);
        $this->assertSame('icon', $navHeaderSchema['cta2_icon']['type']);
    }

    public function test_header_icon_bounding_box_lock_is_rendered(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->headerIconHtml('search');

        $this->assertStringContainsString('class="tb-header-icon-slot"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_resolver_normalizes_legacy_camel_case_props_with_zero_regression(): void
    {
        $legacyProps = [
            'brandName' => 'لوکاپ برند',
            'homeUrl' => '/home',
            'ctaText' => 'ثبت سفارش',
            'ctaUrl' => '/order',
            'bgColor' => '#123456',
            'textColor' => '#ffffff',
            'ctaColor' => '#ff5500',
            'phone' => '09120000000',
            'menuItems' => [
                ['label' => 'بخش اول', 'url' => '/p1'],
                ['label' => 'بخش دوم', 'url' => '/p2'],
            ],
        ];

        $resolved = HeaderDataResolver::resolve($legacyProps, []);

        $this->assertSame('لوکاپ برند', $resolved['brand']['name']);
        $this->assertSame('/home', $resolved['brand']['url']);
        $this->assertSame('ثبت سفارش', $resolved['actions']['cta']['text']);
        $this->assertSame('/order', $resolved['actions']['cta']['url']);
        $this->assertSame('#ff5500', $resolved['actions']['cta']['color_start']);
        $this->assertSame('09120000000', $resolved['actions']['phone']);
        $this->assertSame('#123456', $resolved['style']['bg_color']);
        $this->assertSame('#ffffff', $resolved['style']['text_color']);
        $this->assertCount(2, $resolved['navigation']['items']);
        $this->assertSame('بخش اول', $resolved['navigation']['items'][0]['label']);
    }

    public function test_resolver_handles_pages_module_menu_source(): void
    {
        $props = [
            'menu_source' => 'pages_module',
            'selected_page_ids' => [101, 102],
        ];

        $context = [
            'published_pages' => [
                ['id' => 101, 'title' => 'صفحه خدمات ویژه', 'slug' => 'vip-services'],
                ['id' => 102, 'title' => 'صفحه درباره ما', 'slug' => 'about-us'],
                ['id' => 103, 'title' => 'صفحه منتشرنشده', 'slug' => 'draft-page'],
            ],
        ];

        $resolved = HeaderDataResolver::resolve($props, $context);

        $this->assertSame('pages_module', $resolved['navigation']['source']);
        $items = $resolved['navigation']['items'];
        $this->assertCount(2, $items);
        $this->assertSame('صفحه خدمات ویژه', $items[0]['label']);
        $this->assertStringContainsString('vip-services', $items[0]['url']);
        $this->assertSame('صفحه درباره ما', $items[1]['label']);
        $this->assertStringContainsString('about-us', $items[1]['url']);
    }

    public function test_resolver_handles_menu_module_source(): void
    {
        $props = [
            'menu_source' => 'menu_module',
            'selected_menu_id' => 'custom-header-menu',
        ];

        $context = [
            'resolved_menus' => [
                'custom-header-menu' => [
                    ['title' => 'منوی ماژول ۱', 'url' => '/m1', 'icon' => 'home'],
                    ['title' => 'منوی ماژول ۲', 'url' => '/m2', 'children' => [
                        ['title' => 'زیرمنوی ۱', 'url' => '/m2-sub1'],
                    ]],
                ],
            ],
        ];

        $resolved = HeaderDataResolver::resolve($props, $context);

        $this->assertSame('menu_module', $resolved['navigation']['source']);
        $items = $resolved['navigation']['items'];
        $this->assertCount(2, $items);
        $this->assertSame('منوی ماژول ۱', $items[0]['label']);
        $this->assertSame('/m1', $items[0]['url']);
        $this->assertSame('home', $items[0]['icon']);
        $this->assertCount(1, $items[1]['children']);
        $this->assertSame('زیرمنوی ۱', $items[1]['children'][0]['label']);
    }
}
