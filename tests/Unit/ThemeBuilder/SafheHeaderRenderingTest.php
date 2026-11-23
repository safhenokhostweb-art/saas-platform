<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use Tests\TestCase;

/**
 * Rendering and architectural validation of the Safhe Nokhost pilot header.
 *
 * Validates:
 * 1. Template structure and compliance with PLATFORM-THEME-SPEC-v1 / ADR-084.
 * 2. Iranian modern aesthetic lockup (Qazvin Ali Qapu pointed arch & Kolah Farangi emblem).
 * 3. 3-column Mega Menu with boxed icons, badges, and promo cards.
 * 4. Mobile drawer capabilities (drawer logo, search, and action CTA).
 * 5. Production HTML generation via canonical BlockRenderer without errors.
 */
class SafheHeaderRenderingTest extends TestCase
{
    private const TEMPLATE_ID = 'lib:safhe-header';

    private function render(array $props, string $id = 'safhe-nav'): string
    {
        return (new BlockRenderer())->renderBlock([
            'type' => 'nav_header',
            'id' => $id,
            'props' => $props,
        ]);
    }

    public function test_template_exists_in_library_and_has_nav_header_block(): void
    {
        $card = null;
        foreach (TemplateLibrary::list() as $item) {
            if ($item['id'] === self::TEMPLATE_ID) {
                $card = $item;
                break;
            }
        }
        $this->assertNotNull($card, 'safhe-header must exist in TemplateLibrary::list()');
        $this->assertSame('header', $card['type']);
        $this->assertSame('corporate', $card['category']);
        $this->assertSame('هدر اختصاصی صفحه نخست با مگامنو', $card['name']);
        $this->assertContains('صفحه نخست', $card['tags']);

        $template = TemplateLibrary::find(self::TEMPLATE_ID);
        $this->assertNotNull($template, 'safhe-header must exist in TemplateLibrary::find()');
        $this->assertSame('header', $template['zone']);
        $this->assertCount(1, $template['blocks']);
        $this->assertSame('nav_header', $template['blocks'][0]['type']);
    }

    public function test_safhe_header_props_contain_qazvin_brand_lockup(): void
    {
        $props = TemplateLibrary::find(self::TEMPLATE_ID)['blocks'][0]['props'];

        $this->assertSame('صفحه', $props['logo_text']);
        $this->assertSame('نخست', $props['logo_text_accent']);
        $this->assertSame('زیرساخت ابری و آژانس توسعه وب', $props['logo_tagline']);
        $this->assertStringContainsString('safhe-icon.svg', $props['logo_image']);
        $this->assertTrue($props['sticky']);
        $this->assertSame('card', $props['mega_layout']);
        $this->assertSame('boxed', $props['mega_icon_style']);
    }

    public function test_safhe_header_megamenu_structure(): void
    {
        $props = TemplateLibrary::find(self::TEMPLATE_ID)['blocks'][0]['props'];
        $links = $props['nav_links'];

        // Find mega menu item (خدمات)
        $services = null;
        foreach ($links as $link) {
            if (($link['label'] ?? '') === 'خدمات') {
                $services = $link;
                break;
            }
        }

        $this->assertNotNull($services, 'Services mega menu item must exist');
        $this->assertCount(3, $services['mega_columns'], 'Must have exactly 3 mega columns');
        $this->assertSame(['طراحی و توسعه وب', 'سئو و رشد دیجیتال', 'زیرساخت و امنیت'], array_column($services['mega_columns'], 'title'));

        // Promo cards
        $this->assertCount(2, $services['mega_promo_cards']);
        $this->assertSame('مشاوره تخصصی', $services['mega_promo_cards'][0]['eyebrow']);
        $this->assertSame('محاسبه آنلاین زمان و هزینه پروژه شما', $services['mega_promo_cards'][0]['title']);
    }

    public function test_safhe_header_renders_valid_html_with_canonical_renderer(): void
    {
        $props = TemplateLibrary::find(self::TEMPLATE_ID)['blocks'][0]['props'];
        $html = $this->render($props, 'safhe-header-nav');

        // Root element and classes
        $this->assertStringContainsString('id="tb-safhe-header-nav"', $html);
        $this->assertStringContainsString('class="tb-nav-header', $html);

        // Logo lockup with Qazvin architecture branding
        $this->assertStringContainsString('tb-nav-header-logo-lockup', $html);
        $this->assertStringContainsString('tb-nav-header-logo-accent', $html);
        $this->assertStringContainsString('صفحه <span class="tb-nav-header-logo-accent">نخست</span>', $html);
        $this->assertStringContainsString('زیرساخت ابری و آژانس توسعه وب', $html);

        // Mega menu components
        $this->assertStringContainsString('tb-mega-menu-panel', $html);
        $this->assertStringContainsString('tb-mega-menu-cols', $html);
        $this->assertStringContainsString('tb-mega-menu-promo', $html);
        $this->assertStringContainsString('tb-mega-badge--primary', $html);
        $this->assertStringContainsString('tb-mega-badge--accent', $html);

        // Action buttons (CTA and CTA2)
        $this->assertStringContainsString('درخواست مشاوره', $html);
        $this->assertStringContainsString('ورود', $html);

        // Drawer components for mobile
        $this->assertStringContainsString('tb-nav-header-menu-panel', $html);
        $this->assertStringContainsString('tb-nav-header-drawer-search', $html);
        $this->assertStringContainsString('tb-nav-header-drawer-ctas', $html);
        $this->assertStringContainsString('تماس فوری با کارشناسان', $html);
    }
}
