<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

/**
 * Phase 2 — Layout Reconstruction
 *
 * Extracts:
 *   - Columns (how many per section)
 *   - Containers (max-width, padding)
 *   - Spacing system (margin/padding scale)
 *   - Section hierarchy (nesting depth)
 *   - Responsive structure (breakpoints detected)
 *   - Component relationships (what follows what)
 */
class LayoutReconstructionService
{
    // Common container max-widths mapped to TB3 size tokens
    private const CONTAINER_MAP = [
        '1920' => 'full',
        '1536' => '8xl',
        '1280' => '7xl',
        '1200' => '6xl',
        '1024' => '5xl',
        '960'  => '4xl',
        '768'  => '3xl',
    ];

    // Reference column patterns for known sites
    private const SITE_COLUMN_SIGNATURES = [
        'apple'    => ['hero' => 1, 'features' => 3, 'products' => 4, 'cta' => 1],
        'stripe'   => ['hero' => 2, 'features' => 3, 'pricing' => 3, 'cta' => 1],
        'shopify'  => ['hero' => 1, 'features' => 3, 'pricing' => 3, 'blog' => 3],
        'digikala' => ['hero' => 1, 'products' => 4, 'categories' => 6, 'blog' => 4],
        'amazon'   => ['hero' => 1, 'products' => 5, 'categories' => 7, 'deals' => 2],
        'malltina' => ['hero' => 1, 'products' => 4, 'categories' => 5, 'deals' => 3],
    ];

    /**
     * Reconstruct layout from DOM structure produced by DomExtractionService.
     *
     * @param array $domStructure Result of DomExtractionService::extract()
     * @param string $html        Original HTML (for CSS analysis)
     * @return array LayoutMap
     */
    public function reconstruct(array $domStructure, string $html = ''): array
    {
        $sections        = $this->buildSectionList($domStructure);
        $hierarchy       = $this->buildHierarchy($sections, $domStructure);
        $spacingSystem   = $this->inferSpacingSystem($html);
        $containerConfig = $this->inferContainerConfig($html);
        $responsive      = $this->detectResponsiveStructure($html);
        $relationships   = $this->buildComponentRelationships($sections);

        return [
            'sections'         => $sections,
            'hierarchy'        => $hierarchy,
            'spacing_system'   => $spacingSystem,
            'container'        => $containerConfig,
            'responsive'       => $responsive,
            'relationships'    => $relationships,
            'column_signature' => $this->buildColumnSignature($sections),
            'layout_style'     => $this->classifyLayoutStyle($domStructure, $html),
            'section_count'    => count($sections),
            'is_single_page'   => count($sections) >= 5,
        ];
    }

    /**
     * Build a simplified layout map when only vision/prompt data is available.
     */
    public function reconstructFromSignals(array $domStructure): array
    {
        $sections = $this->buildSectionList($domStructure);
        return [
            'sections'         => $sections,
            'hierarchy'        => $this->buildHierarchy($sections, $domStructure),
            'spacing_system'   => $this->defaultSpacingSystem(),
            'container'        => ['max_width' => '1280px', 'padding' => '1rem', 'size_token' => '7xl'],
            'responsive'       => ['has_mobile_menu' => true, 'breakpoints' => ['sm', 'md', 'lg', 'xl']],
            'relationships'    => $this->buildComponentRelationships($sections),
            'column_signature' => $this->buildColumnSignature($sections),
            'layout_style'     => 'modern',
            'section_count'    => count($sections),
            'is_single_page'   => true,
        ];
    }

    // ── Private methods ───────────────────────────────────────────────────────

    private function buildSectionList(array $dom): array
    {
        $sections = [];
        $order    = 0;

        // Always include header first
        if ($dom['header']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('header', $order++, 1, $dom['header']);
        }

        // Hero
        if ($dom['hero']['detected'] ?? false) {
            $layout = $dom['hero']['layout'] ?? 'standard';
            $cols   = $layout === 'split' ? 2 : 1;
            $sections[] = $this->sectionEntry('hero', $order++, $cols, $dom['hero']);
        }

        // Features
        if ($dom['features']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('features', $order++, 3, $dom['features']);
        }

        // CTA (first occurrence after hero)
        if ($dom['cta_sections']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('cta', $order++, 1, []);
        }

        // Pricing
        if ($dom['pricing']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('pricing', $order++, $dom['pricing']['tiers'] ?? 3, $dom['pricing']);
        }

        // Testimonials
        if ($dom['testimonials']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('testimonials', $order++, 1, $dom['testimonials']);
        }

        // Blog
        if ($dom['blog']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('blog', $order++, 3, $dom['blog']);
        }

        // FAQ
        if ($dom['faq']['detected'] ?? false) {
            $sections[] = $this->sectionEntry('faq', $order++, 1, $dom['faq']);
        }

        // Footer
        if ($dom['footer']['detected'] ?? false) {
            $cols = min($dom['footer']['columns'] ?? 4, 5);
            $sections[] = $this->sectionEntry('footer', $order++, $cols, $dom['footer']);
        }

        return $sections;
    }

    private function sectionEntry(string $type, int $order, int $cols, array $data): array
    {
        return [
            'type'    => $type,
            'order'   => $order,
            'columns' => $cols,
            'data'    => $data,
            'block_type' => $this->sectionToBlockType($type),
            'nested'  => false,
        ];
    }

    private function buildHierarchy(array $sections, array $dom): array
    {
        $depth    = $dom['depth_map'] ?? [];
        $h1Count  = count(array_filter($depth, fn ($h) => $h['level'] === 1));
        $h2Count  = count(array_filter($depth, fn ($h) => $h['level'] === 2));
        $h3Count  = count(array_filter($depth, fn ($h) => $h['level'] === 3));

        $flow = array_map(fn ($s) => $s['type'], $sections);

        return [
            'heading_counts' => ['h1' => $h1Count, 'h2' => $h2Count, 'h3' => $h3Count],
            'section_flow'   => $flow,
            'max_depth'      => 3,
            'uses_sections'  => count($sections) > 3,
        ];
    }

    private function inferSpacingSystem(string $html): array
    {
        if (empty($html)) return $this->defaultSpacingSystem();

        // Detect Tailwind spacing classes
        $hasTailwind = (bool) preg_match('/\bpy-\d+\b|\bpx-\d+\b|\bmt-\d+\b|\bpt-\d+\b/', $html);

        // Detect Bootstrap spacing
        $hasBootstrap = (bool) preg_match('/\bpy-\d\b|\bpx-\d\b|\bmt-\d\b/', $html);

        // Detect custom CSS variables
        $hasCustomProps = str_contains($html, '--space-') || str_contains($html, '--padding-') || str_contains($html, '--margin-');

        if ($hasTailwind) {
            return ['system' => 'tailwind', 'scale' => '4px', 'section_padding' => 'py-16 md:py-24', 'container_padding' => 'px-4 md:px-8'];
        }
        if ($hasBootstrap) {
            return ['system' => 'bootstrap', 'scale' => '4px', 'section_padding' => 'py-5', 'container_padding' => 'px-3'];
        }
        if ($hasCustomProps) {
            return ['system' => 'custom', 'scale' => 'var(--space)', 'section_padding' => 'var(--section-py)', 'container_padding' => 'var(--px)'];
        }

        return $this->defaultSpacingSystem();
    }

    private function defaultSpacingSystem(): array
    {
        return ['system' => 'standard', 'scale' => '8px', 'section_padding' => '80px 0', 'container_padding' => '0 24px'];
    }

    private function inferContainerConfig(string $html): array
    {
        // Look for max-width declarations
        foreach (self::CONTAINER_MAP as $px => $token) {
            if (str_contains($html, "max-width:{$px}px") || str_contains($html, "max-width: {$px}px") || str_contains($html, "max-w-[{$px}px]")) {
                return ['max_width' => "{$px}px", 'size_token' => $token, 'padding' => '24px'];
            }
        }

        // Tailwind container class detection
        if (str_contains($html, 'max-w-7xl')) return ['max_width' => '1280px', 'size_token' => '7xl', 'padding' => '1rem'];
        if (str_contains($html, 'max-w-6xl')) return ['max_width' => '1152px', 'size_token' => '6xl', 'padding' => '1rem'];
        if (str_contains($html, 'max-w-5xl')) return ['max_width' => '1024px', 'size_token' => '5xl', 'padding' => '1rem'];
        if (str_contains($html, 'container'))  return ['max_width' => '1200px', 'size_token' => '6xl', 'padding' => '24px'];

        return ['max_width' => '1280px', 'size_token' => '7xl', 'padding' => '24px'];
    }

    private function detectResponsiveStructure(string $html): array
    {
        $hasMobileMenu  = str_contains($html, 'mobile-menu') || str_contains($html, 'hamburger') || str_contains($html, 'navbar-toggle');
        $hasMediaQuery  = str_contains($html, '@media') || str_contains($html, 'md:') || str_contains($html, 'lg:');
        $hasFlexWrap    = str_contains($html, 'flex-wrap') || str_contains($html, 'flex-col');

        $breakpoints = [];
        if ($hasMediaQuery) {
            foreach (['sm', 'md', 'lg', 'xl', '2xl'] as $bp) {
                if (str_contains($html, "{$bp}:")) $breakpoints[] = $bp;
            }
            if (empty($breakpoints)) $breakpoints = ['md', 'lg'];
        }

        return [
            'has_mobile_menu' => $hasMobileMenu,
            'has_media_queries' => $hasMediaQuery,
            'has_flex_wrap'   => $hasFlexWrap,
            'breakpoints'     => $breakpoints ?: ['md', 'lg'],
            'is_mobile_first' => str_contains($html, 'sm:') || str_contains($html, '@media (min-width'),
        ];
    }

    private function buildComponentRelationships(array $sections): array
    {
        $flow = array_map(fn ($s) => $s['type'], $sections);

        $relationships = [];
        for ($i = 0; $i < count($flow) - 1; $i++) {
            $relationships[] = [
                'from' => $flow[$i],
                'to'   => $flow[$i + 1],
                'type' => 'follows',
            ];
        }

        return [
            'flow'        => $flow,
            'edges'       => $relationships,
            'entry_point' => $flow[0] ?? 'header',
            'exit_point'  => $flow[count($flow) - 1] ?? 'footer',
        ];
    }

    private function buildColumnSignature(array $sections): array
    {
        $sig = [];
        foreach ($sections as $section) {
            $sig[$section['type']] = $section['columns'];
        }
        return $sig;
    }

    private function classifyLayoutStyle(array $dom, string $html): string
    {
        $hasBento    = str_contains($html, 'bento') || str_contains($html, 'masonry');
        $hasFullBleed = str_contains($html, 'full-width') || str_contains($html, 'w-full') || str_contains($html, 'overflow-hidden');
        $hasSplit    = ($dom['hero']['layout'] ?? '') === 'split';

        if ($hasBento)    return 'bento';
        if ($hasSplit)    return 'editorial';
        if ($hasFullBleed) return 'full-bleed';

        return 'standard';
    }

    private function sectionToBlockType(string $type): string
    {
        return match ($type) {
            'header'       => 'nav_header',
            'hero'         => 'hero',
            'features'     => 'features_grid',
            'cta'          => 'cta',
            'pricing'      => 'pricing_cards',
            'testimonials' => 'testimonials_slider',
            'blog'         => 'card_grid',
            'faq'          => 'faq',
            'footer'       => 'nav_footer',
            default        => 'text',
        };
    }
}
