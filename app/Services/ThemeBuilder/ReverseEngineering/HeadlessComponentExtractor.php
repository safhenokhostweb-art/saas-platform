<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

/**
 * TB3 Sprint 11 — Headless Component Extractor
 *
 * Converts a Playwright extraction payload into the same DomStructure
 * format expected by phases 2–7 of the RE pipeline.
 *
 * Key advantage over HTTP extraction:
 *   - Real post-JS DOM (React/Next/Vue fully rendered)
 *   - Computed styles (real colors, fonts, border-radii from CSS)
 *   - Product grid column counts via getBoundingClientRect
 *   - Mega-menu detection after JS hydration
 */
class HeadlessComponentExtractor
{
    /**
     * Build a DomStructure array from a Playwright result payload.
     * The output shape matches DomExtractionService::extract() exactly.
     */
    public function buildDomStructure(array $playwright): array
    {
        $c   = $playwright['components'] ?? [];
        $fw  = $playwright['framework']  ?? [];
        $url = $playwright['url']        ?? '';

        return [
            'url'           => $url,
            'title'         => $c['meta']['title']       ?? '',
            'meta'          => $this->buildMeta($c),
            'render_mode'   => $this->buildRenderMode($playwright),
            'landmarks'     => $this->buildLandmarks($c),
            'header'        => $this->buildHeader($c),
            'mega_menu'     => $this->buildMegaMenu($c),
            'hero'          => $this->buildHero($c),
            'grid_layouts'  => $this->buildGridLayouts($c),
            'cards'         => $this->buildCards($c),
            'features'      => $this->buildFeatures($c),
            'cta_sections'  => $this->buildCta($c),
            'pricing'       => $this->buildPricing($c),
            'testimonials'  => $this->buildTestimonials($c),
            'faq'           => $this->buildFaq($c),
            'blog'          => $this->buildBlog($c),
            'footer'        => $this->buildFooter($c),
            'sections_count'=> count($c['sections_detected'] ?? []),
            'depth_map'     => $this->buildDepthMap($c),
            'extracted_at'  => now()->toISOString(),
            'source'        => 'headless',
            // Headless-only enrichment
            'headless'      => [
                'colors'      => $c['colors']      ?? [],
                'typography'  => $c['typography']  ?? [],
                'sliders'     => $c['sliders']     ?? [],
                'search'      => $c['search']      ?? [],
                'filters'     => $c['filters']     ?? [],
                'product_grid'=> $c['product_grid'] ?? [],
                'screenshot'  => !empty($playwright['screenshot_base64']),
                'html_size'   => strlen($playwright['rendered_html'] ?? ''),
            ],
        ];
    }

    // ── Build helpers ──────────────────────────────────────────────────────────

    private function buildMeta(array $c): array
    {
        $m = $c['meta'] ?? [];
        return [
            'description' => $m['description'] ?? '',
            'keywords'    => '',
            'og_title'    => $m['og_title']    ?? '',
            'og_image'    => $m['og_image']    ?? '',
            'theme_color' => ($c['colors'][0] ?? ''),
            'viewport'    => 'width=device-width, initial-scale=1',
            'is_rtl'      => $m['is_rtl'] ?? false,
        ];
    }

    private function buildRenderMode(array $playwright): array
    {
        $fw = $playwright['framework'] ?? [];
        return [
            'mode'            => 'headless',
            'is_spa_shell'    => false,
            'is_nextjs'       => $fw['is_nextjs'] ?? false,
            'is_react'        => $fw['is_react']  ?? false,
            'html_size'       => strlen($playwright['rendered_html'] ?? ''),
            'has_minified_css'=> ($fw['is_nextjs'] ?? false) || ($fw['is_react'] ?? false),
            'was_headless'    => true,
        ];
    }

    private function buildLandmarks(array $c): array
    {
        $sections = $c['sections_detected'] ?? [];
        $map = [
            'header'       => in_array('header', $sections)       ? 85 : 10,
            'hero'         => in_array('hero', $sections)         ? 85 : 10,
            'pricing'      => in_array('pricing', $sections)      ? 80 : 10,
            'testimonials' => in_array('testimonials', $sections)  ? 80 : 10,
            'faq'          => in_array('faq', $sections)          ? 80 : 10,
            'blog'         => in_array('blog', $sections)         ? 80 : 10,
            'cta'          => in_array('cta', $sections)          ? 80 : 10,
            'footer'       => in_array('footer', $sections)       ? 85 : 10,
        ];

        $landmarks = [];
        foreach ($map as $type => $confidence) {
            $landmarks[] = [
                'type'       => $type,
                'selector'   => null,
                'confidence' => $confidence,
                'count'      => $confidence > 50 ? 1 : 0,
            ];
        }
        return $landmarks;
    }

    private function buildHeader(array $c): array
    {
        $nav = $c['nav'] ?? [];
        return [
            'detected'      => count($nav['items'] ?? []) > 0,
            'has_logo'      => true,
            'nav_items'     => count($nav['items'] ?? []),
            'nav_labels'    => $nav['items'] ?? [],
            'is_sticky'     => $nav['is_sticky']    ?? false,
            'has_mega_menu' => $nav['has_mega_menu'] ?? false,
            'has_search'    => $nav['has_search']   ?? false,
            'has_cta'       => $nav['has_cart']     ?? false,
            'has_cart'      => $nav['has_cart']     ?? false,
        ];
    }

    private function buildMegaMenu(array $c): array
    {
        $mm = $c['mega_menu'] ?? [];
        return [
            'detected'  => $mm['detected']   ?? false,
            'columns'   => $mm['columns']    ?? 0,
            'has_icons' => $mm['has_icons']  ?? false,
            'has_images'=> false,
            'sub_items' => $mm['sub_items']  ?? [],
        ];
    }

    private function buildHero(array $c): array
    {
        $hero = $c['hero'] ?? [];
        $detected = !empty($hero['headline']);
        if (!$detected && in_array('hero', $c['sections_detected'] ?? [])) {
            $detected = true;
        }
        return [
            'detected'         => $detected,
            'headline'         => $hero['headline']    ?? '',
            'subheadline'      => $hero['subheadline'] ?? '',
            'has_video_bg'     => $hero['has_video']   ?? false,
            'has_bg_image'     => $hero['has_image']   ?? false,
            'has_cta'          => count($hero['cta_buttons'] ?? []) > 0,
            'cta_labels'       => $hero['cta_buttons'] ?? [],
            'layout'           => $hero['layout']      ?? 'centered',
            'has_gradient'     => $hero['has_gradient'] ?? false,
            'has_social_proof' => false,
        ];
    }

    private function buildGridLayouts(array $c): array
    {
        $grid  = $c['product_grid'] ?? [];
        $cols  = (int) ($grid['columns'] ?? 0);
        $types = [];

        if ($cols >= 2) {
            $colType = match (true) {
                $cols >= 4 => 'col-4',
                $cols === 3 => 'col-3',
                default     => 'col-2',
            };
            $types[] = ['type' => $colType, 'count' => $grid['count'] ?? 0, 'is_responsive' => true];
        }

        return [
            'detected_types'  => $types,
            'total_containers'=> $grid['count'] ?? 0,
            'primary_layout'  => $cols >= 4 ? 'col-4' : ($cols >= 3 ? 'col-3' : ($cols >= 2 ? 'col-2' : 'col-1')),
        ];
    }

    private function buildCards(array $c): array
    {
        $cards = $c['cards'] ?? [];
        return [
            'count'             => (int) ($cards['total'] ?? 0),
            'types'             => $cards['types']    ?? [],
            'has_hover_effects' => $cards['has_hover'] ?? false,
            'has_badges'        => $c['product_grid']['has_badge'] ?? false,
        ];
    }

    private function buildFeatures(array $c): array
    {
        $detected = in_array('features', $c['sections_detected'] ?? []);
        return [
            'detected'   => $detected,
            'item_count' => $detected ? 6 : 0,
            'layout'     => 'grid-3',
            'has_icons'  => true,
            'has_images' => false,
            'style'      => 'cards',
        ];
    }

    private function buildCta(array $c): array
    {
        $detected = in_array('cta', $c['sections_detected'] ?? [])
            || count($c['hero']['cta_buttons'] ?? []) > 0;
        return [
            'detected'       => $detected,
            'count'          => $detected ? 1 : 0,
            'has_email_form' => false,
            'style'          => 'standard',
        ];
    }

    private function buildPricing(array $c): array
    {
        $detected = in_array('pricing', $c['sections_detected'] ?? []);
        return [
            'detected'         => $detected,
            'tiers'            => 3,
            'has_toggle'       => false,
            'has_highlight'    => true,
            'has_feature_list' => true,
        ];
    }

    private function buildTestimonials(array $c): array
    {
        $detected = in_array('testimonials', $c['sections_detected'] ?? []);
        $hasSlider = in_array('swiper', $c['sliders'] ?? []) || in_array('splide', $c['sliders'] ?? []);
        return [
            'detected'   => $detected,
            'count'      => $detected ? 3 : 0,
            'layout'     => $hasSlider ? 'slider' : 'grid',
            'has_rating' => false,
            'has_avatar' => true,
        ];
    }

    private function buildFaq(array $c): array
    {
        $detected = in_array('faq', $c['sections_detected'] ?? []);
        return [
            'detected'       => $detected,
            'items'          => $detected ? 5 : 0,
            'style'          => 'accordion',
            'has_search'     => false,
            'has_categories' => false,
        ];
    }

    private function buildBlog(array $c): array
    {
        $detected = in_array('blog', $c['sections_detected'] ?? []);
        $isBlogCard = in_array('blog', $c['cards']['types'] ?? []);
        return [
            'detected'     => $detected || $isBlogCard,
            'count'        => ($detected || $isBlogCard) ? 3 : 0,
            'grid_cols'    => 3,
            'has_category' => false,
            'has_date'     => true,
            'has_author'   => false,
        ];
    }

    private function buildFooter(array $c): array
    {
        $footer = $c['footer'] ?? [];
        return [
            'detected'         => count($footer['links'] ?? []) > 0,
            'columns'          => (int) ($footer['columns'] ?? 4),
            'has_social'       => $footer['has_social']     ?? false,
            'has_newsletter'   => $footer['has_newsletter'] ?? false,
            'has_logo'         => true,
            'has_copyright'    => true,
            'has_contact_info' => false,
            'link_count'       => count($footer['links'] ?? []),
        ];
    }

    private function buildDepthMap(array $c): array
    {
        $map  = [];
        $meta = $c['meta'] ?? [];

        if (!empty($meta['h1'])) {
            $map[] = ['level' => 1, 'text' => mb_substr($meta['h1'], 0, 80)];
        }
        foreach ($meta['h2_list'] ?? [] as $h2) {
            $map[] = ['level' => 2, 'text' => mb_substr($h2, 0, 80)];
        }
        return array_slice($map, 0, 15);
    }
}
