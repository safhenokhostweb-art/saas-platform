<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

/**
 * TB3 Sprint 11.5 — Playwright Snapshot Service
 *
 * Generates three structured snapshots from a Playwright extraction payload:
 *
 *   DomSnapshot   — Structural representation of the rendered page
 *   CssSnapshot   — Design tokens extracted from computed styles and CSS variables
 *   LayoutSnapshot — Section grid map (section → columns → content density)
 *
 * These snapshots are attached to the ReverseEngineeringJob::meta and passed
 * into the Phase 3–5 services for richer analysis.
 */
class PlaywrightSnapshotService
{
    /**
     * Generate all three snapshots from a Playwright result payload.
     *
     * @param array $playwright Full output from PlaywrightExtractionService::extract()
     * @param array $domStructure Phase 1 DomExtractionService output (post-merge)
     * @return array ['dom' => ..., 'css' => ..., 'layout' => ...]
     */
    public function generateAll(array $playwright, array $domStructure): array
    {
        return [
            'dom'    => $this->generateDomSnapshot($playwright, $domStructure),
            'css'    => $this->generateCssSnapshot($playwright, $domStructure),
            'layout' => $this->generateLayoutSnapshot($playwright, $domStructure),
        ];
    }

    // ── DOM Snapshot ──────────────────────────────────────────────────────────

    /**
     * Structural page snapshot — summarises every detected section, component,
     * and key content element discovered by Playwright.
     */
    public function generateDomSnapshot(array $playwright, array $domStructure): array
    {
        $c   = $playwright['components'] ?? [];
        $aria = $playwright['aria_landmarks'] ?? [];
        $fw  = $playwright['framework'] ?? [];
        $perf= $playwright['performance'] ?? [];

        $sections = $c['sections_detected'] ?? $domStructure['landmarks']
            ? array_column(
                array_filter($domStructure['landmarks'] ?? [], fn($l) => ($l['count'] ?? 0) > 0),
                'type'
            )
            : [];

        return [
            'version'    => '11.5',
            'url'        => $playwright['url'] ?? $domStructure['url'] ?? '',
            'title'      => $c['meta']['title'] ?? $domStructure['title'] ?? '',
            'language'   => $c['meta']['language'] ?? '',
            'is_rtl'     => $c['meta']['is_rtl'] ?? false,

            'framework' => [
                'nextjs'   => $fw['is_nextjs']  ?? false,
                'react'    => $fw['is_react']   ?? false,
                'vue'      => $fw['is_vue']     ?? false,
                'angular'  => $fw['is_angular'] ?? false,
                'nuxt'     => $fw['is_nuxt']    ?? false,
                'hashed_css'=> $fw['has_hashed_css'] ?? false,
                'css_stack'  => array_keys(array_filter($fw['css_frameworks'] ?? [])),
            ],

            'sections_detected'   => $sections,
            'section_count'       => count($sections),
            'aria_landmarks'      => $this->buildAriaMap($aria),

            'header' => [
                'detected'   => count($c['nav']['items'] ?? []) > 0 || ($domStructure['header']['detected'] ?? false),
                'nav_items'  => count($c['nav']['items'] ?? []),
                'nav_labels' => array_slice($c['nav']['items'] ?? [], 0, 12),
                'is_sticky'  => $c['nav']['is_sticky']     ?? false,
                'has_search' => $c['nav']['has_search']    ?? false,
                'has_cart'   => $c['nav']['has_cart']      ?? false,
                'has_mega'   => $c['mega_menu']['detected'] ?? false,
                'nav_depth'  => $c['nav']['depth']         ?? 1,
            ],

            'hero' => [
                'detected'    => !empty($c['hero']['headline']) || ($domStructure['hero']['detected'] ?? false),
                'headline'    => $c['hero']['headline']    ?? $domStructure['hero']['headline'] ?? '',
                'subheadline' => $c['hero']['subheadline'] ?? '',
                'cta_buttons' => $c['hero']['cta_buttons'] ?? [],
                'layout'      => $c['hero']['layout']      ?? 'centered',
                'has_slider'  => $c['hero']['has_slider']  ?? false,
                'has_video'   => $c['hero']['has_video']   ?? false,
                'has_gradient'=> $c['hero']['has_gradient'] ?? false,
            ],

            'products' => [
                'detected'     => ($c['product_grid']['count'] ?? 0) >= 2,
                'count'        => $c['product_grid']['count']    ?? 0,
                'columns'      => $c['product_grid']['columns']  ?? 0,
                'has_price'    => $c['product_grid']['has_price'] ?? false,
                'has_badge'    => $c['product_grid']['has_badge'] ?? false,
                'has_rating'   => $c['product_grid']['has_rating'] ?? false,
                'has_cart_btn' => $c['product_grid']['has_cart_btn'] ?? false,
                'price_samples'=> $c['product_grid']['price_samples'] ?? [],
            ],

            'cards' => [
                'total' => $c['cards']['total'] ?? 0,
                'types' => $c['cards']['types'] ?? [],
                'has_hover' => $c['cards']['has_hover'] ?? false,
            ],

            'footer' => [
                'detected'       => count($c['footer']['links'] ?? []) > 0
                                    || ($domStructure['footer']['detected'] ?? false),
                'columns'        => $c['footer']['columns']      ?? 4,
                'link_count'     => count($c['footer']['links']  ?? []),
                'has_social'     => $c['footer']['has_social']   ?? false,
                'has_newsletter' => $c['footer']['has_newsletter'] ?? false,
                'has_sitemap'    => $c['footer']['has_sitemap']  ?? false,
            ],

            'search' => [
                'found'        => $c['search']['found']       ?? false,
                'prominent'    => $c['search']['is_prominent'] ?? false,
                'placeholder'  => $c['search']['placeholder'] ?? '',
            ],

            'filters' => [
                'found' => $c['filters']['found'] ?? false,
                'count' => $c['filters']['count'] ?? 0,
                'type'  => $c['filters']['type']  ?? '',
            ],

            'sliders'         => $c['sliders'] ?? [],
            'heading_structure'=> array_slice($c['heading_structure'] ?? [], 0, 15),

            'performance' => [
                'html_size_kb'   => round(strlen($playwright['rendered_html'] ?? '') / 1024, 1),
                'nav_ms'         => $perf['navigation_ms']    ?? 0,
                'fcp_ms'         => $perf['fcp_ms']           ?? 0,
                'js_bundles'     => $perf['js_bundles']       ?? 0,
                'total_resources'=> $perf['total_resources']  ?? 0,
            ],

            'generated_at' => now()->toISOString(),
        ];
    }

    // ── CSS Snapshot ──────────────────────────────────────────────────────────

    /**
     * Design token snapshot — colors, typography, spacing, radius — all from
     * computed styles and CSS custom properties extracted by Playwright.
     */
    public function generateCssSnapshot(array $playwright, array $domStructure): array
    {
        $c       = $playwright['components']   ?? [];
        $cssVars = $playwright['css_variables'] ?? [];
        $fw      = $playwright['framework']    ?? [];

        $colors    = $c['colors']     ?? [];
        $typo      = $c['typography'] ?? [];

        // Primary color: first non-white/black from color palette or CSS vars
        $primaryColor = $this->extractPrimaryColor($colors, $cssVars);

        // Background color inference
        $bgColor = $this->extractBackgroundColor($cssVars);

        // Typography tokens
        $primaryFont = $this->extractPrimaryFont($typo['fonts'] ?? [], $cssVars);

        // Radius detection from CSS vars
        $radius = $this->detectRadius($cssVars);

        // Shadow detection from CSS vars
        $shadow = $this->detectShadow($cssVars);

        // Animation detection
        $hasAnimations = !empty($c['sliders'])
            || ($c['cards']['has_hover'] ?? false)
            || $this->hasCssAnimations($cssVars);

        return [
            'version' => '11.5',

            'color_palette' => [
                'primary'    => $primaryColor,
                'background' => $bgColor,
                'all_colors' => $colors,
                'count'      => count($colors),
                'scheme'     => $this->detectColorScheme($primaryColor, $bgColor),
            ],

            'typography' => [
                'primary_font'  => $primaryFont,
                'all_fonts'     => $typo['fonts']       ?? [],
                'base_size'     => $typo['base_size']   ?? '16px',
                'weights'       => $typo['weights']     ?? [],
                'line_heights'  => $typo['line_heights'] ?? [],
                'is_rtl_font'   => $this->isRtlFont($primaryFont),
            ],

            'spacing' => [
                'scale'   => $this->detectSpacingScale($cssVars),
                'density' => $this->detectDensity($cssVars),
            ],

            'border_radius' => [
                'scale'    => $radius,
                'raw_vars' => $this->extractRadiusVars($cssVars),
            ],

            'shadow' => [
                'style'    => $shadow,
                'raw_vars' => $this->extractShadowVars($cssVars),
            ],

            'animations' => [
                'has_animations'  => $hasAnimations,
                'has_transitions' => $c['cards']['has_hover'] ?? false,
                'slider_libs'     => $c['sliders'] ?? [],
            ],

            'css_variables' => array_slice($cssVars, 0, 50, true),

            'style_framework' => $this->detectStyleFramework($fw, $cssVars),

            'generated_at' => now()->toISOString(),
        ];
    }

    // ── Layout Snapshot ───────────────────────────────────────────────────────

    /**
     * Grid layout snapshot — maps each detected section to its column count,
     * layout type, content width, and visual density.
     */
    public function generateLayoutSnapshot(array $playwright, array $domStructure): array
    {
        $c    = $playwright['components'] ?? [];
        $fw   = $playwright['framework']  ?? [];

        $sections  = $c['sections_detected'] ?? [];
        $productCols = (int) ($c['product_grid']['columns'] ?? 0);
        $filterFound = $c['filters']['found'] ?? false;

        // Build column signature from real data
        $colSig = [];
        if (in_array('header', $sections))      $colSig['header']     = 1;
        if (in_array('hero', $sections))        $colSig['hero']       = 1;
        if (in_array('features', $sections))    $colSig['features']   = 3;
        if (in_array('categories', $sections))  $colSig['categories'] = 5;
        if ($productCols > 0) {
            $colSig['products'] = $productCols;
        } elseif (in_array('products', $sections)) {
            $colSig['products'] = 4;
        }
        if ($filterFound) $colSig['sidebar'] = 1;
        if (in_array('blog', $sections))        $colSig['blog']       = 3;
        if (in_array('footer', $sections))      $colSig['footer']     = 4;

        // Layout style detection
        $layoutStyle = 'standard';
        if ($fw['is_nextjs'] ?? false) {
            $layoutStyle = 'standard'; // Next.js commerce = standard
        }

        // Detect sidebar layout
        if ($filterFound || ($c['filters']['count'] ?? 0) > 3) {
            $layoutStyle = 'sidebar';
        }

        // Card count influences density
        $density = 'standard';
        $totalCards = (int) ($c['cards']['total'] ?? 0);
        if ($totalCards > 30) $density = 'tight';
        elseif ($totalCards < 5) $density = 'spacious';

        // Hero layout
        $heroLayout = $c['hero']['layout'] ?? 'centered';

        // Build section flow map
        $sectionFlow = [];
        $orderedSections = ['header', 'hero', 'categories', 'features', 'products', 'blog', 'testimonials', 'pricing', 'faq', 'cta', 'footer'];
        foreach ($orderedSections as $sec) {
            if (in_array($sec, $sections)) {
                $sectionFlow[] = [
                    'type'    => $sec,
                    'columns' => $colSig[$sec] ?? 1,
                    'density' => $density,
                ];
            }
        }

        // Responsive breakpoints (always present in headless mode)
        $responsiveBreakpoints = [
            'mobile'  => 390,
            'tablet'  => 768,
            'desktop' => 1440,
        ];

        return [
            'version'          => '11.5',
            'section_flow'     => $sectionFlow,
            'section_count'    => count($sections),
            'column_signature' => $colSig,
            'layout_style'     => $layoutStyle,
            'hero_layout'      => $heroLayout,
            'density'          => $density,
            'has_sidebar'      => $filterFound,
            'has_product_grid' => $productCols > 0,
            'product_columns'  => $productCols,
            'has_sliders'      => count($c['sliders'] ?? []) > 0,
            'responsive_breakpoints' => $responsiveBreakpoints,
            'is_full_bleed'    => false,
            'container_width'  => 1280,
            'generated_at'     => now()->toISOString(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildAriaMap(array $aria): array
    {
        $map = [];
        foreach (['banner', 'main', 'navigation', 'contentinfo', 'complementary', 'search'] as $role) {
            $map[$role] = ($aria[$role]['found'] ?? false);
        }
        return $map;
    }

    private function extractPrimaryColor(array $colors, array $cssVars): string
    {
        // CSS variable for primary color takes precedence
        foreach (['--primary', '--primary-color', '--brand-color', '--theme-primary', '--color-primary', '--accent', '--accent-color'] as $var) {
            if (!empty($cssVars[$var]) && str_starts_with(trim($cssVars[$var]), '#')) {
                return strtolower(trim($cssVars[$var]));
            }
        }

        // From Playwright computed colors — skip white/near-white, pick most saturated
        $candidate = '#3b82f6'; // Default blue
        $bestSaturation = 0.0;

        foreach ($colors as $hex) {
            $hex = strtolower($hex);
            $rgb = $this->hexToRgb($hex);
            if (!$rgb) continue;
            [$r, $g, $b] = $rgb;
            $brightness = (0.299 * $r + 0.587 * $g + 0.114 * $b);
            if ($brightness > 240) continue; // skip white

            $max = max($r, $g, $b) / 255;
            $min = min($r, $g, $b) / 255;
            $saturation = $max > 0 ? (($max - $min) / $max) : 0;

            if ($saturation > $bestSaturation) {
                $bestSaturation = $saturation;
                $candidate = $hex;
            }
        }

        return $candidate;
    }

    private function extractBackgroundColor(array $cssVars): string
    {
        foreach (['--bg', '--background', '--bg-color', '--background-color', '--page-bg', '--body-bg'] as $var) {
            if (!empty($cssVars[$var]) && str_starts_with(trim($cssVars[$var]), '#')) {
                return strtolower(trim($cssVars[$var]));
            }
        }
        return '#ffffff';
    }

    private function extractPrimaryFont(array $fonts, array $cssVars): string
    {
        foreach (['--font-family', '--primary-font', '--base-font', '--font', '--body-font'] as $var) {
            if (!empty($cssVars[$var])) {
                $f = trim($cssVars[$var], '"\'');
                if (strlen($f) > 2) return $f;
            }
        }
        return $fonts[0] ?? 'system-ui';
    }

    private function detectRadius(array $cssVars): string
    {
        foreach (['--radius', '--border-radius', '--rounded', '--corner-radius'] as $var) {
            if (!empty($cssVars[$var])) {
                $v = trim($cssVars[$var]);
                if ($v === '0' || $v === '0px') return 'none';
                if (preg_match('/^(\d+)px$/', $v, $m)) {
                    $px = (int) $m[1];
                    if ($px <= 2) return 'sm';
                    if ($px <= 6) return 'md';
                    if ($px <= 12) return 'xl';
                    return 'pill';
                }
                if (str_contains($v, 'rem')) return 'md';
            }
        }
        return 'md';
    }

    private function detectShadow(array $cssVars): string
    {
        foreach (['--shadow', '--box-shadow', '--card-shadow', '--elevation'] as $var) {
            if (!empty($cssVars[$var])) {
                $v = strtolower($cssVars[$var]);
                if ($v === 'none' || $v === '0') return 'none';
                if (str_contains($v, 'rgba')) return 'soft';
                return 'standard';
            }
        }
        return 'none';
    }

    private function detectSpacingScale(array $cssVars): string
    {
        foreach (['--spacing', '--space', '--gap', '--padding'] as $var) {
            if (!empty($cssVars[$var])) {
                $v = trim($cssVars[$var]);
                if (preg_match('/^(\d+(?:\.\d+)?)/', $v, $m)) {
                    $val = (float) $m[1];
                    if ($val <= 4) return 'tight';
                    if ($val <= 8) return 'standard';
                    return 'spacious';
                }
            }
        }
        return 'standard';
    }

    private function detectDensity(array $cssVars): string
    {
        foreach (['--density', '--spacing-density'] as $var) {
            if (!empty($cssVars[$var])) {
                $v = strtolower(trim($cssVars[$var]));
                if (in_array($v, ['tight', 'compact'])) return 'tight';
                if (in_array($v, ['spacious', 'loose'])) return 'spacious';
            }
        }
        return 'standard';
    }

    private function hasCssAnimations(array $cssVars): bool
    {
        foreach (['--animation', '--transition', '--duration', '--ease'] as $var) {
            if (!empty($cssVars[$var]) && trim($cssVars[$var]) !== 'none') {
                return true;
            }
        }
        return false;
    }

    private function extractRadiusVars(array $cssVars): array
    {
        return array_filter($cssVars, fn($v, $k) =>
            str_contains(strtolower($k), 'radius') || str_contains(strtolower($k), 'rounded'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function extractShadowVars(array $cssVars): array
    {
        return array_filter($cssVars, fn($v, $k) =>
            str_contains(strtolower($k), 'shadow') || str_contains(strtolower($k), 'elevation'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function detectColorScheme(string $primary, string $bg): string
    {
        if (empty($primary)) return 'unknown';
        [$r, $g, $b] = $this->hexToRgb($primary) ?? [0, 0, 0];

        $bgBrightness = 255; // default white
        if ($bgRgb = $this->hexToRgb($bg)) {
            $bgBrightness = (0.299 * $bgRgb[0] + 0.587 * $bgRgb[1] + 0.114 * $bgRgb[2]);
        }

        $isDark = $bgBrightness < 50;

        // Detect dominant hue
        $max = max($r, $g, $b);
        if ($max === 0) return 'neutral';

        if ($r > $g && $r > $b) return $isDark ? 'dark-red' : 'red';
        if ($g > $r && $g > $b) return $isDark ? 'dark-green' : 'green';
        if ($b > $r && $b > $g) return $isDark ? 'dark-blue' : 'blue';
        if ($r > 200 && $g > 150 && $b < 80) return 'orange';
        if ($r > 180 && $g < 100 && $b > 180) return 'purple';
        if ($r < 100 && $g > 180 && $b > 180) return 'teal';

        return 'neutral';
    }

    private function detectStyleFramework(array $fw, array $cssVars): string
    {
        $css = $fw['css_frameworks'] ?? [];
        if ($css['tailwind'] ?? false)  return 'tailwind';
        if ($css['material'] ?? false)  return 'material-ui';
        if ($css['antd']     ?? false)  return 'ant-design';
        if ($css['bootstrap'] ?? false) return 'bootstrap';
        if ($css['chakra']   ?? false)  return 'chakra-ui';
        // CSS var clue
        if (isset($cssVars['--chakra-colors-primary'])) return 'chakra-ui';
        if (isset($cssVars['--ant-primary-color']))     return 'ant-design';
        return 'custom';
    }

    private function isRtlFont(string $font): bool
    {
        $rtlFonts = ['iransans', 'iran sans', 'vazirmatn', 'vazir', 'shabnam', 'sahel', 'samim', 'tanha', 'yekan', 'dana', 'beiruti'];
        $lower = strtolower($font);
        foreach ($rtlFonts as $rtl) {
            if (str_contains($lower, $rtl)) return true;
        }
        return false;
    }

    private function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) return null;
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
