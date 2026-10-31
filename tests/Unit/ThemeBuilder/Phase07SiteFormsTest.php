<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

/**
 * Phase 0.7 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-066): the
 * newsletter and search blocks render real forms from host endpoints
 * (context `site_endpoints`, see BlockRenderer::siteEndpoints()) and stay
 * inert without them — the Builder preview.
 */
class Phase07SiteFormsTest extends TestCase
{
    private const ENDPOINTS = [
        'newsletter' => '/newsletter/subscribe',
        'csrf' => 'tok"en',
        'search' => [
            'all' => ['action' => '/search'],
            'products' => ['action' => '/search', 'params' => ['type' => 'products']],
            'posts' => ['action' => '/search', 'params' => ['type' => 'posts']],
        ],
        'suggest' => '/api/search/suggest',
    ];

    private function render(string $type, array $props, ?array $endpoints = null): string
    {
        $renderer = new BlockRenderer();
        if ($endpoints !== null) {
            $renderer->setContext(['site_endpoints' => $endpoints]);
        }
        return $renderer->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    public function test_without_endpoints_the_forms_stay_inert_and_load_no_script(): void
    {
        $newsletter = $this->render('newsletter_form', []);
        $this->assertStringContainsString('onsubmit="event.preventDefault();this.reset();"', $newsletter);
        $this->assertStringNotContainsString('data-tb-newsletter', $newsletter);

        foreach ([['search_bar', []], ['nav_header', ['show_search' => true]]] as [$type, $props]) {
            $html = $this->render($type, $props);
            $this->assertStringContainsString('action="#" onsubmit="event.preventDefault();"', $html, $type);
            $this->assertStringNotContainsString('data-tb-search=', $html, $type);
            $this->assertStringNotContainsString('data-tb-block-asset="site_forms"', $html, $type);
        }
    }

    public function test_the_newsletter_form_posts_to_the_host_with_honeypot_and_status(): void
    {
        $html = $this->render('newsletter_form', [], self::ENDPOINTS);
        $this->assertStringContainsString('<form class="tb-newsletter-form" method="post" action="/newsletter/subscribe" data-tb-newsletter novalidate>', $html);
        $this->assertStringContainsString('<input type="hidden" name="_token" value="tok&quot;en">', $html);
        $this->assertStringContainsString('<input type="hidden" name="source" value="b1">', $html);
        $this->assertMatchesRegularExpression('/<input id="tb-b1-newsletter-email" type="text" name="contact" inputmode="email" autocomplete="email" maxlength="190" required/', $html);
        $this->assertStringContainsString('<span class="tb-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></span>', $html);
        $this->assertStringContainsString('<p id="tb-b1-newsletter-status" class="tb-newsletter-status" role="status" aria-live="polite"></p>', $html);
        $this->assertSame(1, substr_count($html, 'data-tb-block-asset="site_forms"'));
    }

    public function test_an_unsafe_newsletter_endpoint_falls_back_to_the_inert_form(): void
    {
        $html = $this->render('newsletter_form', [], ['newsletter' => 'javascript:alert(1)']);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data-tb-newsletter', $html);
    }

    public function test_search_bar_is_a_real_get_form_with_scopes_and_suggestions(): void
    {
        $html = $this->render('search_bar', ['placeholder' => 'جستجوی محصول', 'scope' => 'products'], self::ENDPOINTS);
        $this->assertStringContainsString('method="get" action="/search"', $html);
        $this->assertStringContainsString('data-tb-scope="products"', $html);
        $this->assertStringContainsString('data-tb-suggest="/api/search/suggest"', $html);
        $this->assertStringContainsString('<input type="hidden" name="type" value="products">', $html, 'no-JS fallback keeps the scope');
        $this->assertStringContainsString('type="search" name="q" class="tb-search-bar-input" placeholder="جستجوی محصول"', $html);
        $this->assertStringContainsString('<button type="submit" class="tb-search-bar-btn">جستجو</button>', $html);

        preg_match('/data-tb-search="([^"]*)"/', $html, $m);
        $routes = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertSame(['all', 'products', 'posts'], array_keys($routes));
        $this->assertSame(['action' => '/search', 'params' => ['type' => 'posts'], 'param' => 'q'], $routes['posts']);
    }

    public function test_an_unknown_or_unavailable_scope_falls_back_and_unsafe_routes_are_dropped(): void
    {
        $html = $this->render('search_bar', ['scope' => 'posts'], ['search' => [
            'products' => ['action' => '/shop'],
            'posts' => ['action' => 'javascript:alert(1)'],
            'all' => ['action' => '/shop', 'params' => ['bad key' => 'x', 'ok' => ['nested']]],
        ]]);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('data-tb-scope="all"', $html);
        $this->assertStringContainsString('action="/shop"', $html);
        $this->assertStringNotContainsString('bad key', $html);
        preg_match('/<form [^>]*>/', $html, $form);
        $this->assertStringNotContainsString('data-tb-suggest', $form[0]);

        $scopeless = $this->render('search_bar', ['scope' => '<x>'], self::ENDPOINTS);
        $this->assertStringContainsString('data-tb-scope="all"', $scopeless);
    }

    public function test_nav_header_search_shares_the_dialog_and_folds_into_an_icon_on_phones(): void
    {
        $html = $this->render('nav_header', ['show_search' => true, 'search_scope' => 'posts'], self::ENDPOINTS);
        $this->assertStringContainsString('<form id="tb-b1-search-form" class="tb-nav-header-search" role="search" method="get" action="/search"', $html);
        $this->assertStringContainsString('data-tb-scope="posts"', $html);
        $this->assertStringContainsString('data-tb-search-open="tb-b1-search-form" aria-label="جستجو"><svg class="tb-icon-svg tb-icon-svg-search"', $html);

        $css = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
        $this->assertStringContainsString('@media (max-width: 767.98px) { .tb-nav-header-search { display: none; } .tb-nav-header-search-toggle { display: inline-flex; }', $css);
        $this->assertStringContainsString('.tb-search-overlay__panel { width: 100%; height: 100%;', $css, 'full-screen dialog on phones');
    }

    public function test_the_site_forms_script_is_inlined_once_per_render(): void
    {
        $renderer = new BlockRenderer();
        $renderer->setContext(['site_endpoints' => self::ENDPOINTS]);
        $html = $renderer->renderBlock(['type' => 'search_bar', 'id' => 'a', 'props' => []])
            . $renderer->renderBlock(['type' => 'newsletter_form', 'id' => 'b', 'props' => []]);
        $this->assertSame(1, substr_count($html, 'data-tb-block-asset="site_forms"'));
        $this->assertStringContainsString('TbSiteForms', $html);
    }

    public function test_the_scripts_suggestion_icons_are_the_catalog_bodies(): void
    {
        $js = file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::siteFormsScriptPath());
        foreach (['product' => 'package', 'post' => 'file-text'] as $type => $icon) {
            $body = \SaaSPlatform\ThemeRuntime\Support\IconLibrary::catalog()['icons'][$icon]['body'];
            $this->assertStringContainsString("{$type}: '{$body}',", $js, "{$type} icon drifted from the catalog's {$icon}");
        }
    }

    public function test_the_registry_offers_a_search_scope_on_both_search_blocks(): void
    {
        $all = BlockRegistry::all();
        $this->assertSame(['all', 'products', 'posts'], array_keys($all['search_bar']['schema']['scope']['options']));
        $this->assertSame('all', $all['search_bar']['defaultProps']['scope']);
        $this->assertSame(['all', 'products', 'posts'], array_keys($all['nav_header']['schema']['search_scope']['options']));
    }
}
