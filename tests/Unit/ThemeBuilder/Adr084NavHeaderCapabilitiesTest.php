<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use SaaSPlatform\ThemeRuntime\Widget\HeaderStyleVars;
use SaaSPlatform\ThemeRuntime\Widget\WidgetSchemaContract;
use Tests\TestCase;

/**
 * ADR-084: the one canonical nav_header gains generic capability props (logo
 * lockup, item icons, pill pointer, mega card, boxed sub-item icons, badges,
 * promo cards, outline and second buttons, boxed action icons, drawer extras,
 * compact bar). Each is opt-in: a header that sets none renders exactly as
 * before. Values are untrusted: text is escaped and capped, links go through
 * the shared link-scheme filter, enumerations fall back to their default.
 */
class Adr084NavHeaderCapabilitiesTest extends TestCase
{
    private function render(array $props, string $id = 'h'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => $id, 'props' => $props]);
    }

    private function megaLink(array $extra = []): array
    {
        return $extra + [
            'label' => 'محصولات',
            'url' => '/shop',
            'mega_columns' => [['title' => 'ستون', 'items' => [['label' => 'پنل', 'url' => '/shop/panel', 'icon' => 'zap']]]],
        ];
    }

    public function test_the_schema_still_meets_the_widget_contract(): void
    {
        $this->assertSame([], WidgetSchemaContract::definitionViolations('nav_header', BlockRegistry::all()['nav_header']));
        $schema = BlockRegistry::all()['nav_header']['schema'];
        $item = $schema['nav_links']['fields']['mega_columns']['fields']['items']['fields'];
        $this->assertSame(['primary', 'accent', 'success', 'warning', 'neutral'], array_keys($item['badge_variant']['options']));
        $this->assertSame(['eyebrow', 'title', 'url', 'icon'], array_keys($schema['nav_links']['fields']['mega_promo_cards']['fields']));
        $this->assertSame('icon', $schema['nav_links']['fields']['icon']['type']);
        $this->assertSame(['full', 'card'], array_keys($schema['mega_layout']['options']));
        $this->assertSame(['bare', 'boxed'], array_keys($schema['mega_icon_style']['options']));
        $this->assertArrayHasKey('pill', $schema['pointer']['options']);
        $this->assertSame(['off', '1280'], array_map('strval', array_keys($schema['compact_below']['options'])), 'on the breakpoint scale (BreakpointScaleTest)');
    }

    public function test_a_header_without_the_new_props_renders_none_of_their_markup(): void
    {
        $html = $this->render(['logo_text' => 'برند', 'nav_links' => [$this->megaLink(), ['label' => 'تماس', 'url' => '/contact']], 'cta_text' => 'تماس', 'cta_url' => '/contact', 'show_search' => true, 'search_mode' => 'icon']);

        foreach (['tb-nav-header-logo-lockup', 'tb-nav-header-logo-icon', 'tb-nav-header-link-icon', 'tb-mega-badge', 'tb-mega-menu-main', 'tb-mega-menu-promo', 'tb-nav-header-cta--outline', 'tb-nav-header-cta--second', 'tb-nav-header-cta-icon', 'tb-nav-header-drawer-logo', 'tb-nav-header-drawer-search', 'tb-nav-header--mega-', 'tb-nav-header--compact', 'tb-nav-header--pointer-pill'] as $marker) {
            $this->assertStringNotContainsString($marker, $html, $marker);
        }
        // The panel keeps its exact structure: columns straight inside it.
        $this->assertMatchesRegularExpression('~<div class="tb-mega-menu-panel" id="[^"]+-panel">\s*<div class="tb-mega-menu-cols">~', $html);
        $this->assertStringContainsString('<a href="/contact" class="tb-btn tb-btn-primary tb-nav-header-cta">تماس</a>', $html);
        $this->assertStringContainsString('<a href="/contact" class="tb-nav-header-link">تماس</a>', $html);
        $this->assertStringContainsString('<button type="button" class="tb-nav-header-menu-close" aria-label="بستن منو">', $html);
        $this->assertStringContainsString('<a href="/shop/panel" class="tb-mega-menu-link"><span class="tb-mega-menu-link-icon">', $html);
    }

    public function test_the_logo_lockup_renders_icon_accent_and_tagline_escaped(): void
    {
        $html = $this->render(['logo_text' => 'برند', 'logo_text_accent' => 'شما<b>', 'logo_tagline' => 'مهندسی "انرژی"', 'logo_icon' => 'sun']);
        $this->assertStringContainsString('<span class="tb-nav-header-logo-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-sun"', $html);
        $this->assertStringContainsString('<span class="tb-nav-header-logo-title">برند <span class="tb-nav-header-logo-accent">شما&lt;b&gt;</span></span>', $html);
        $this->assertStringContainsString('<span class="tb-nav-header-logo-tagline">مهندسی &quot;انرژی&quot;</span>', $html);

        // No name, no lockup: the accent alone never replaces the site logo fallback.
        $this->assertStringNotContainsString('tb-nav-header-logo-lockup', $this->render(['logo_text' => '', 'logo_text_accent' => 'شما']));
        // An image logo keeps the image as the mark; the icon is not added next to it.
        $image = $this->render(['logo_text' => 'برند', 'logo_image' => '/l.png', 'logo_icon' => 'sun', 'logo_tagline' => 'شعار']);
        $this->assertStringNotContainsString('tb-nav-header-logo-icon', $image);
        $this->assertStringContainsString('tb-nav-header-logo-tagline', $image);
    }

    public function test_item_icons_badges_and_their_allowlists(): void
    {
        $html = $this->render(['nav_links' => [
            $this->megaLink(['icon' => 'grid-3x3', 'mega_columns' => [['title' => 'ستون', 'items' => [
                ['label' => 'پنل', 'url' => '/a', 'icon' => 'zap', 'badge' => 'جدید', 'badge_variant' => 'success'],
                ['label' => 'باتری', 'url' => '/b', 'badge' => '<script>x</script>' . str_repeat('ب', 40), 'badge_variant' => 'bogus'],
            ]]]]),
            ['label' => 'تماس', 'url' => '/contact', 'icon' => 'mail'],
        ]]);
        $this->assertMatchesRegularExpression('~class="tb-nav-header-link tb-mega-menu-trigger"[^>]*><span class="tb-nav-header-link-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-grid-3x3"~', $html);
        $this->assertStringContainsString('<a href="/contact" class="tb-nav-header-link"><span class="tb-nav-header-link-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-mail"', $html);
        $this->assertStringContainsString('<span class="tb-mega-menu-link-text"><span>پنل</span><span class="tb-mega-badge tb-mega-badge--success">جدید</span></span>', $html);
        $this->assertStringContainsString('tb-mega-badge tb-mega-badge--primary">&lt;script&gt;x&lt;/script&gt;', $html, 'an unknown variant falls back to primary');
        $this->assertStringNotContainsString('<script>x', $html);
        $this->assertDoesNotMatchRegularExpression('~ب{17}~u', $html, 'the badge is capped at 24 characters');
    }

    public function test_promo_cards_are_capped_at_two_need_a_title_and_never_carry_an_unsafe_url(): void
    {
        $two = $this->render(['nav_links' => [$this->megaLink(['mega_promo_cards' => [
            ['eyebrow' => 'بدون عنوان'],
            ['eyebrow' => '<i>مشاهده</i>', 'title' => 'فروشگاه', 'url' => 'javascript:alert(1)'],
            ['title' => 'جدیدها', 'url' => '/new', 'icon' => ''],
            ['title' => 'سوم', 'url' => '/third'],
        ]])]]);
        $this->assertSame(2, substr_count($two, 'class="tb-mega-menu-promo"'));
        $this->assertStringContainsString('<div class="tb-mega-menu-main"><div class="tb-mega-menu-cols">', $two);
        $this->assertStringContainsString('<div class="tb-mega-menu-promos"><a href="#" class="tb-mega-menu-promo"><span class="tb-mega-menu-promo-eyebrow">&lt;i&gt;مشاهده&lt;/i&gt;</span><span class="tb-mega-menu-promo-title"><span class="tb-mega-menu-promo-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-arrow-left"', $two, 'a missing icon defaults to the arrow; javascript: becomes #');
        $this->assertStringContainsString('<a href="/new" class="tb-mega-menu-promo"><span class="tb-mega-menu-promo-title"><span>جدیدها</span></span></a>', $two, 'an explicitly empty icon renders none');
        $this->assertStringNotContainsString('javascript:', $two);
        $this->assertStringNotContainsString('سوم', $two);

        $one = $this->render(['nav_links' => [$this->megaLink(['mega_promo_cards' => json_encode([['title' => 'همه', 'url' => '/all']])])]]);
        $this->assertStringContainsString('class="tb-mega-menu-promos tb-mega-menu-promos--single"', $one, 'json-stored cards are read too');
    }

    public function test_buttons_take_an_icon_an_outline_look_and_a_second_button_first(): void
    {
        $html = $this->render(['cta_text' => 'ثبت‌نام', 'cta_url' => '/register', 'cta_icon' => 'user-plus', 'cta2_text' => 'ورود', 'cta2_url' => 'javascript:alert(1)', 'cta2_icon' => 'log-in']);
        $second = strpos($html, 'tb-nav-header-cta--second');
        $first = strpos($html, 'href="/register"');
        $this->assertNotFalse($second);
        $this->assertLessThan($first, $second, 'the second button comes before the first one');
        // javascript: is dropped to '#', which carries the shared dead-link mark (Sentinel DEAD_LINK).
        $this->assertStringContainsString('<a href="#" class="tb-btn tb-nav-header-cta--outline tb-nav-header-cta tb-nav-header-cta--second" data-tb-dead-link title="لینک مقصد تنظیم نشده است"><span class="tb-nav-header-cta-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-log-in"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<a href="/register" class="tb-btn tb-btn-primary tb-nav-header-cta"><span class="tb-nav-header-cta-icon" aria-hidden="true"><svg class="tb-icon-svg tb-icon-svg-user-plus"', $html);

        $outline = $this->render(['cta_text' => 'تماس', 'cta_url' => '/contact', 'cta_style' => 'outline', 'cta2_text' => 'ورود', 'cta2_style' => 'primary']);
        $this->assertStringContainsString('class="tb-btn tb-nav-header-cta--outline tb-nav-header-cta"', $outline);
        $this->assertStringContainsString('class="tb-btn tb-btn-primary tb-nav-header-cta tb-nav-header-cta--second"', $outline);
    }

    public function test_the_drawer_carries_the_logo_search_and_buttons_only_when_asked(): void
    {
        $props = ['logo_text' => 'برند', 'nav_links' => [['label' => 'تماس', 'url' => '/contact']], 'show_search' => true, 'search_mode' => 'icon', 'cta_text' => 'ثبت‌نام', 'cta_url' => '/register', 'cta2_text' => 'ورود', 'cta2_url' => '/login'];
        $html = $this->render($props + ['drawer_show_logo' => true, 'drawer_show_search' => true, 'drawer_show_ctas' => true], 'dr');
        $this->assertMatchesRegularExpression('~<div class="tb-nav-header-drawer-head"><a href="/" class="tb-nav-header-logo tb-nav-header-drawer-logo">برند</a><button type="button" class="tb-nav-header-menu-close"~', $html);
        $this->assertStringContainsString('<form class="tb-nav-header-search tb-nav-header-drawer-search tb-nav-header-drawer-only" role="search"', $html);
        $this->assertStringContainsString('id="tb-dr-drawer-search"', $html);
        $this->assertSame(1, substr_count($html, 'id="tb-dr-drawer-search"'));
        $this->assertMatchesRegularExpression('~<div class="tb-nav-header-drawer-ctas tb-nav-header-drawer-only"><a href="/login" [^>]*>ورود</a><a href="/register" class="tb-btn tb-btn-primary tb-nav-header-cta">ثبت‌نام</a></div></div></nav>~', $html);
        $this->assertStringContainsString('class="tb-nav-header tb-nav-header--drawer-search tb-nav-header--drawer-ctas"', $html);
        $this->assertSame(1, substr_count($html, 'data-widget-slot="logo"'), 'the drawer copy of the logo is not a second logo slot');

        // The drawer search needs the header search; "never" has no drawer at all.
        $noSearch = $this->render(['show_search' => false] + $props + ['drawer_show_search' => true]);
        $this->assertStringNotContainsString('tb-nav-header-drawer-search', $noSearch);
        $never = $this->render($props + ['burger_breakpoint' => 'never', 'drawer_show_logo' => true, 'drawer_show_search' => true, 'drawer_show_ctas' => true]);
        $this->assertStringNotContainsString('tb-nav-header-drawer-', $never);
        $this->assertStringNotContainsString('tb-nav-header--drawer-search', $never);
    }

    public function test_style_props_become_classes_and_scoped_values(): void
    {
        $out = HeaderStyleVars::forHeader('tb-h', [
            'pointer' => 'pill', 'menu_item_padding_x' => 13, 'menu_item_radius' => 8,
            'mega_layout' => 'card', 'mega_max_width' => 920, 'mega_icon_style' => 'boxed', 'mega_col_title_divider' => true,
            'mega_bg' => 'var(--tb-surface)', 'mega_col_title_color' => 'var(--tb-primary)', 'action_icon_border' => 'var(--tb-border)',
            'compact_below' => '1280', 'cta_font_size' => 13.5,
        ]);
        $this->assertSame(['tb-nav-header--pointer-pill', 'tb-nav-header--mega-card', 'tb-nav-header--mega-icons-boxed', 'tb-nav-header--mega-divider', 'tb-nav-header--compact-1280'], $out['classes']);
        $this->assertSame(['menu-pad-x', 'mega-bg', 'mega-title-color', 'icon-border', 'cta-size'], $out['set']);
        foreach (['--tb-hdr-pill-pad-x:13px', '--tb-hdr-menu-radius:8px', '--tb-hdr-mega-max-w:920px', '--tb-hdr-mega-bg:var(--tb-surface)', '--tb-hdr-cta-size:13.5px'] as $declaration) {
            $this->assertStringContainsString($declaration, $out['style']);
        }

        $bogus = HeaderStyleVars::forHeader('tb-h', ['mega_layout' => 'grid', 'mega_icon_style' => 'round', 'compact_below' => '1440', 'mega_col_title_divider' => 'no', 'mega_bg' => 'red;}body{x:y', 'pointer' => 'pill-ish']);
        $this->assertSame(['style' => '', 'set' => [], 'classes' => []], $bogus);
    }

    public function test_every_new_rule_lives_in_the_header_sheet_behind_its_markup_or_gate(): void
    {
        $css = (string) file_get_contents(ThemeRuntimeServiceProvider::cssHeaderPath());
        foreach (['.tb-nav-header--mega-card', '.tb-nav-header--mega-icons-boxed', '.tb-nav-header--mega-divider', '.tb-nav-header--pointer-pill', '.tb-nav-header--compact-1280', '.tb-mega-badge--neutral', '.tb-mega-menu-promos--single', '.tb-nav-header-cta--outline', '.tb-nav-header-drawer-ctas'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector);
        }
        // Promo cards are desktop only; the drawer never shows them.
        $this->assertStringContainsString('@media(max-width:1023.98px){.tb-nav-header-root .tb-mega-menu-promos{display:none;}}', $css);
        $this->assertStringContainsString('.tb-nav-header-root.tb-nav-header-root--drawer .tb-mega-menu-promos{display:none;}', $css);
        $start = strpos($css, 'ADR-084');
        $this->assertNotFalse($start);
        $block = substr($css, $start);
        do {
            $block = preg_replace('/var\(--[a-z0-9-]+(?:,\s*[^()]*)?\)/i', 'TOKEN', $block, -1, $count);
        } while ($count > 0);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b/i', $block, 'ADR-084 colours are tokens or the header\'s own values');
    }

    public function test_the_solar_header_is_configuration_of_the_canonical_header_only(): void
    {
        $blocks = TemplateLibrary::find('lib:solar-header')['blocks'];
        $this->assertCount(1, $blocks);
        $this->assertSame('nav_header', $blocks[0]['type']);
        $props = $blocks[0]['props'];
        $megas = array_values(array_filter($props['nav_links'], fn ($l) => isset($l['mega_columns'])));
        $this->assertSame(['محصولات', 'راهکارها', 'خدمات', 'پروژه‌ها', 'آموزش'], array_column($megas, 'label'));
        foreach ($props['nav_links'] as $link) {
            $this->assertNotSame('', $link['icon'] ?? '', $link['label'] . ' has an icon');
        }
        foreach ($megas as $mega) {
            $this->assertCount(3, $mega['mega_columns']);
            $this->assertContains(count($mega['mega_promo_cards']), [1, 2]);
        }
        $this->assertSame(['login', 'register'], [$props['cta2_url']['id'], $props['cta_url']['id']]);
        $this->assertTrue($props['show_cart']);
        $this->assertTrue($props['show_account']);
        $this->assertSame(['card', 'boxed', 'pill', 'tablet'], [$props['mega_layout'], $props['mega_icon_style'], $props['pointer'], $props['burger_breakpoint']]);

        $html = $this->render($props, 'solar');
        $this->assertSame(3, substr_count($html, 'class="tb-mega-badge tb-mega-badge--primary"'));
        $this->assertSame(9, substr_count($html, 'class="tb-mega-menu-promo"'));
    }
}
