<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

/**
 * Phase 3 — Visual Analysis
 *
 * Extracts:
 *   - Color Palette (primary, secondary, accent, neutrals, background)
 *   - Typography Scale (font families, sizes, weights)
 *   - Button Styles (shape, fill, border, shadow)
 *   - Border Radius system
 *   - Shadow System (soft, hard, elevated)
 *   - Visual Density (tight / standard / spacious)
 */
class VisualAnalysisService
{
    // Named color → hex mappings (CSS named colors most used in designs)
    private const NAMED_COLORS = [
        'white' => '#ffffff', 'black' => '#000000', 'transparent' => 'transparent',
        'red'   => '#ef4444', 'blue'  => '#3b82f6', 'green' => '#22c55e',
        'gray'  => '#6b7280', 'slate' => '#64748b', 'zinc'  => '#71717a',
        'indigo'=> '#6366f1', 'purple'=> '#a855f7', 'pink'  => '#ec4899',
        'amber' => '#f59e0b', 'orange'=> '#f97316', 'teal'  => '#14b8a6',
        'emerald'=> '#10b981','cyan'  => '#06b6d4', 'sky'   => '#0ea5e9',
        'violet'=> '#8b5cf6', 'rose'  => '#f43f5e', 'lime'  => '#84cc16',
        'yellow'=> '#eab308', 'fuchsia'=>'#d946ef',
    ];

    // Brand color signatures for reference sites
    private const BRAND_SIGNATURES = [
        'apple'    => ['#000000', '#ffffff', '#f5f5f7', '#1d1d1f', '#0071e3', '#fbfbfd'],
        'stripe'   => ['#635bff', '#0a2540', '#00d4ff', '#ffffff', '#f6f9fc', '#425466'],
        'shopify'  => ['#96bf48', '#5c6ac4', '#212b36', '#ffffff', '#f4f6f8', '#637381'],
        'digikala' => ['#ef3e56', '#ff6b35', '#212121', '#ffffff', '#f5f5f5', '#757575'],
        'amazon'   => ['#ff9900', '#232f3e', '#146eb4', '#ffffff', '#eaeded', '#37475a'],
        'malltina' => ['#e63946', '#ff6b6b', '#2d3748', '#ffffff', '#f7fafc', '#718096'],
    ];

    // Typography signatures for reference sites
    private const TYPOGRAPHY_SIGNATURES = [
        'apple'    => ['San Francisco', '-apple-system', 'BlinkMacSystemFont', '17px', '400', '600'],
        'stripe'   => ['Söhne', 'Sohne', 'system-ui', '18px', '400', '600'],
        'shopify'  => ['Inter', 'ShopifySans', 'system-ui', '16px', '400', '700'],
        'digikala' => ['IRANSans', 'IRANYekan', 'Tahoma', '14px', '400', '700'],
        'amazon'   => ['Amazon Ember', 'Arial', 'sans-serif', '14px', '400', '700'],
        'malltina' => ['IRANSans', 'Vazirmatn', 'Tahoma', '14px', '400', '700'],
    ];

    /**
     * Analyze visual properties from raw HTML.
     *
     * When domStructure contains headless color data (TB3 Sprint 11),
     * the computed colors from the browser override CSS-regex extraction.
     */
    public function analyze(string $html, array $domStructure = []): array
    {
        $colors     = $this->extractColors($html, $domStructure);
        $typography = $this->extractTypography($html);
        $buttons    = $this->analyzeButtonStyles($html);
        $radius     = $this->extractBorderRadius($html);
        $shadows    = $this->extractShadowSystem($html);
        $density    = $this->measureVisualDensity($html, $domStructure);

        // Headless enrichment: merge computed colors + font from Playwright
        if (!empty($domStructure['headless']['colors'])) {
            $headlessColors = $domStructure['headless']['colors'];
            // Merge headless computed colors into palette (they are more accurate)
            $colors = $this->mergeHeadlessColors($colors, $headlessColors);
        }

        if (!empty($domStructure['headless']['typography']['fonts'])) {
            $fonts = $domStructure['headless']['typography']['fonts'];
            if (!empty($fonts[0])) {
                $typography['primary_font'] = $fonts[0];
                $typography['font_stack']   = implode(', ', $fonts);
            }
        }

        return [
            'color_palette'   => $colors,
            'typography'      => $typography,
            'buttons'         => $buttons,
            'border_radius'   => $radius,
            'shadow_system'   => $shadows,
            'visual_density'  => $density,
            'dark_mode'       => $this->hasDarkMode($html),
            'has_animations'  => $this->detectAnimations($html),
            'style_framework' => $this->detectStyleFramework($html),
            'source_mode'     => !empty($domStructure['headless']) ? 'headless' : 'http',
        ];
    }

    /**
     * Merge browser-computed colors into an existing color palette.
     * Headless colors are more accurate (actual rendered colors vs regex guesses).
     */
    private function mergeHeadlessColors(array $existing, array $headlessColors): array
    {
        if (empty($headlessColors)) return $existing;

        // Use headless primary if it's different from white/black (body background)
        $filtered = array_filter($headlessColors, fn($h) => !in_array($h, ['#ffffff', '#000000', '#ffffffff']));
        if (!empty($filtered)) {
            $primary = array_values($filtered)[0];
            if (!empty($primary)) {
                $existing['primary']       = $primary;
                $existing['primary_source']= 'computed';
            }
        }

        // Add headless colors to swatches that aren't already there
        $existing['headless_computed'] = array_values($headlessColors);

        return $existing;
    }

    /**
     * Analyze from AI vision result (colors already extracted by Claude).
     */
    public function analyzeFromVision(array $visionResult): array
    {
        $rawColors = $visionResult['colors'] ?? [];
        $primary   = $visionResult['primary_color'] ?? ($rawColors[0] ?? '#3b82f6');
        $style     = $visionResult['style'] ?? 'modern';

        return [
            'color_palette' => $this->buildPaletteFromColors($rawColors, $primary),
            'typography'    => $this->guessTypographyFromStyle($style),
            'buttons'       => $this->guessButtonStyle($style),
            'border_radius' => $this->guessRadiusFromStyle($style),
            'shadow_system' => $this->guessShadowFromStyle($style),
            'visual_density'=> $this->guessDensityFromStyle($style),
            'dark_mode'     => false,
            'has_animations'=> $style !== 'minimal',
            'style_framework'=> 'unknown',
        ];
    }

    /**
     * Analyze from prompt description.
     */
    public function analyzeFromPrompt(string $prompt): array
    {
        $lower  = strtolower($prompt);
        $style  = $this->inferStyleFromText($lower);
        $colors = $this->inferColorsFromText($lower);

        return [
            'color_palette'  => $colors,
            'typography'     => $this->guessTypographyFromStyle($style),
            'buttons'        => $this->guessButtonStyle($style),
            'border_radius'  => $this->guessRadiusFromStyle($style),
            'shadow_system'  => $this->guessShadowFromStyle($style),
            'visual_density' => $this->guessDensityFromStyle($style),
            'dark_mode'      => str_contains($lower, 'dark') || str_contains($lower, 'night'),
            'has_animations' => !str_contains($lower, 'minimal') && !str_contains($lower, 'simple'),
            'style_framework'=> 'tailwind',
        ];
    }

    // ── Private: Color Extraction ─────────────────────────────────────────────

    private function extractColors(string $html, array $dom): array
    {
        $hexColors = $this->extractHexColors($html);
        $rgbColors = $this->extractRgbColors($html);

        // Highest priority: meta theme-color (most reliable brand signal)
        $themeColor = $dom['meta']['theme_color'] ?? '';
        if ($themeColor && preg_match('/^#[0-9a-fA-F]{3,6}$/', $themeColor)) {
            array_unshift($hexColors, strtolower($themeColor));
        }

        // Second priority: CSS custom property --color-primary / --primary-color
        $cssVarColors = $this->extractCssCustomPropertyColors($html);
        foreach (array_reverse($cssVarColors) as $c) {
            array_unshift($hexColors, $c);
        }

        // Third priority: link colors (a:link / a:visited / :any-link) — strong brand signal
        if (preg_match_all('/(?:a|:link|:any-link)[^{]*\{[^}]*color:\s*(#[0-9a-fA-F]{6})/i', $html, $m)) {
            foreach ($m[1] as $c) {
                array_unshift($hexColors, strtolower($c));
            }
        }

        // Deduplicate and rank by frequency
        $all         = array_merge($hexColors, $rgbColors);
        $frequencies = array_count_values($all);
        arsort($frequencies);
        $ranked = array_keys($frequencies);

        // Remove near-white, near-black, and very light/dark neutrals from primary candidate
        $meaningful = array_filter($ranked, function ($c) {
            if (in_array($c, ['#ffffff', '#fff', '#000000', '#000', '#f9fafb', '#f3f4f6'])) return false;
            // Exclude near-white (brightness > 240) and near-black (brightness < 20)
            [$r, $g, $b] = $this->hexToRgbLocal($c);
            $brightness = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            if ($brightness > 230 || $brightness < 15) return false;
            // Exclude transparent / invalid
            if ($c === 'transparent' || strlen($c) < 4) return false;
            return true;
        });
        $meaningful = array_values($meaningful);

        return [
            'primary'    => $meaningful[0] ?? '#3b82f6',
            'secondary'  => $meaningful[1] ?? '#8b5cf6',
            'accent'     => $meaningful[2] ?? '#f59e0b',
            'background' => $this->findBackground($hexColors),
            'text'       => $this->findTextColor($hexColors),
            'all'        => array_slice($ranked, 0, 12),
            'count'      => count($ranked),
        ];
    }

    private function extractHexColors(string $html): array
    {
        preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $html, $m);
        return array_unique(array_map('strtolower', array_map(fn ($c) => '#' . $c, $m[1])));
    }

    private function extractRgbColors(string $html): array
    {
        preg_match_all('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $html, $m, PREG_SET_ORDER);
        return array_unique(array_map(fn ($c) => sprintf('#%02x%02x%02x', $c[1], $c[2], $c[3]), $m));
    }

    private function findBackground(array $colors): string
    {
        foreach ($colors as $c) {
            if (in_array($c, ['#ffffff', '#fff', '#f9fafb', '#f8fafc', '#fafafa', '#f5f5f5', '#fdfdfd'])) {
                return $c;
            }
        }
        return '#ffffff';
    }

    private function findTextColor(array $colors): string
    {
        foreach ($colors as $c) {
            if (in_array($c, ['#000000', '#111111', '#111827', '#1f2937', '#212121', '#0f172a', '#1a1a1a'])) {
                return $c;
            }
        }
        return '#111827';
    }

    // ── Private: Typography ───────────────────────────────────────────────────

    private function extractTypography(string $html): array
    {
        // Font family detection
        preg_match_all('/font-family:\s*([^;}"]+)[;}"]/i', $html, $familyMatches);
        $families = array_unique(array_map('trim', $familyMatches[1] ?? []));

        // Font size detection
        preg_match_all('/font-size:\s*([\d.]+(?:px|rem|em))/i', $html, $sizeMatches);
        $sizes = array_unique($sizeMatches[1] ?? []);

        // Font weight detection
        preg_match_all('/font-weight:\s*(\d{3}|bold|normal)/i', $html, $weightMatches);
        $weights = array_unique($weightMatches[1] ?? []);

        // Detect Tailwind typography classes
        $hasTailwindTypo = preg_match('/text-(xs|sm|base|lg|xl|2xl|3xl|4xl|5xl|6xl)/', $html);

        $primaryFont = $this->resolvePrimaryFont($families, $html);

        return [
            'primary_font'   => $primaryFont,
            'scale'          => $hasTailwindTypo ? 'tailwind' : 'custom',
            'base_size'      => $sizes[0] ?? '16px',
            'sizes_detected' => array_slice($sizes, 0, 8),
            'weights'        => array_unique(array_map('intval', array_filter($weights, 'is_numeric'))),
            'line_height'    => $this->detectLineHeight($html),
            'letter_spacing' => $this->detectLetterSpacing($html),
            'families_raw'   => array_slice($families, 0, 5),
        ];
    }

    private function resolvePrimaryFont(array $families, string $html): string
    {
        // Check for common Persian fonts first
        foreach ($families as $f) {
            if (preg_match('/(Vazirmatn|IRANSans|IRANYekan|Tahoma|Arial)/i', $f)) {
                return trim(explode(',', $f)[0], ' "\'');
            }
        }
        // Check for well-known web fonts
        foreach ($families as $f) {
            if (preg_match('/(Inter|Roboto|Poppins|Montserrat|Nunito|Open Sans|Lato)/i', $f)) {
                return trim(explode(',', $f)[0], ' "\'');
            }
        }
        // Detect from Google Fonts link
        if (preg_match('/fonts\.googleapis\.com\/css[^"]*family=([A-Za-z+]+)/', $html, $m)) {
            return str_replace('+', ' ', $m[1]);
        }
        // Detect Vazirmatn (our default)
        if (str_contains($html, 'Vazirmatn')) return 'Vazirmatn';

        return !empty($families) ? trim(explode(',', $families[0])[0], ' "\'') : 'system-ui';
    }

    private function detectLineHeight(string $html): string
    {
        if (preg_match('/line-height:\s*([\d.]+(?:px|rem|em|%)?)/', $html, $m)) {
            return $m[1];
        }
        return '1.6';
    }

    private function detectLetterSpacing(string $html): string
    {
        if (preg_match('/letter-spacing:\s*([\d.-]+(?:px|em|rem))/', $html, $m)) {
            return $m[1];
        }
        return 'normal';
    }

    // ── Private: Buttons ──────────────────────────────────────────────────────

    private function analyzeButtonStyles(string $html): array
    {
        $hasPill    = str_contains($html, 'rounded-full') || preg_match('/border-radius:\s*9999px/', $html);
        $hasSquare  = preg_match('/rounded-none|border-radius:\s*0/', $html);
        $hasShadow  = str_contains($html, 'shadow') || preg_match('/box-shadow:[^;]+/', $html);
        $hasOutline = str_contains($html, 'btn-outline') || str_contains($html, 'border-2') || preg_match('/outline\s+btn|btn.*?border/i', $html);
        $hasGhost   = str_contains($html, 'btn-ghost') || str_contains($html, 'ghost');

        $shape = match (true) {
            $hasPill   => 'pill',
            $hasSquare => 'square',
            default    => 'rounded',
        };

        return [
            'shape'        => $shape,
            'has_shadow'   => $hasShadow,
            'has_outline'  => $hasOutline,
            'has_ghost'    => $hasGhost,
            'has_gradient' => str_contains($html, 'gradient') && str_contains($html, 'btn'),
            'primary_style'=> $hasGhost ? 'ghost' : ($hasOutline ? 'outline' : 'filled'),
            'size_scale'   => $this->detectButtonSizes($html),
        ];
    }

    private function detectButtonSizes(string $html): array
    {
        $sizes = [];
        if (preg_match('/btn-sm|btn-small|py-1\.5/', $html)) $sizes[] = 'sm';
        if (preg_match('/btn-md|btn-base|py-2\.5/', $html))  $sizes[] = 'md';
        if (preg_match('/btn-lg|btn-large|py-3\.5/', $html)) $sizes[] = 'lg';
        if (preg_match('/btn-xl|btn-xlarge|py-4/', $html))   $sizes[] = 'xl';
        return $sizes ?: ['md'];
    }

    // ── Private: Border Radius ────────────────────────────────────────────────

    private function extractBorderRadius(string $html): array
    {
        $hasPill   = str_contains($html, 'rounded-full') || preg_match('/border-radius:\s*50%|9999px/', $html);
        $hasLarge  = str_contains($html, 'rounded-2xl') || str_contains($html, 'rounded-3xl') || preg_match('/border-radius:\s*1[6-9]px|[2-9]\dpx/', $html);
        $hasMedium = str_contains($html, 'rounded-md') || str_contains($html, 'rounded-lg') || preg_match('/border-radius:\s*[8-9]px|1[0-5]px/', $html);
        $hasSmall  = str_contains($html, 'rounded-sm') || str_contains($html, 'rounded') || preg_match('/border-radius:\s*[2-7]px/', $html);
        $hasNone   = str_contains($html, 'rounded-none') || preg_match('/border-radius:\s*0/', $html);

        $scale = match (true) {
            $hasPill  => 'pill',
            $hasLarge => 'xl',
            $hasMedium=> 'md',
            $hasSmall => 'sm',
            $hasNone  => 'none',
            default   => 'md',
        };

        return [
            'scale'   => $scale,
            'card'    => $hasMedium ? '12px' : ($hasLarge ? '20px' : '8px'),
            'button'  => $hasPill ? '9999px' : ($hasMedium ? '8px' : '6px'),
            'input'   => $hasSmall ? '6px' : '8px',
            'badge'   => $hasPill ? '9999px' : '4px',
        ];
    }

    // ── Private: Shadow System ────────────────────────────────────────────────

    private function extractShadowSystem(string $html): array
    {
        $hasHard   = preg_match('/shadow-[a-z]*\s|box-shadow:[^;]+-[^;]+/', $html);
        $hasSoft   = str_contains($html, 'drop-shadow') || preg_match('/box-shadow:[^;]+rgba/', $html);
        $hasNone   = str_contains($html, 'shadow-none');
        $hasEl     = str_contains($html, 'shadow-xl') || str_contains($html, 'shadow-2xl') || str_contains($html, 'shadow-lg');

        $intensity = match (true) {
            $hasEl   => 'elevated',
            $hasSoft => 'soft',
            $hasHard => 'hard',
            $hasNone => 'none',
            default  => 'soft',
        };

        return [
            'intensity'  => $intensity,
            'sm'  => '0 1px 3px rgba(0,0,0,0.06)',
            'md'  => '0 4px 16px rgba(0,0,0,0.08)',
            'lg'  => '0 8px 32px rgba(0,0,0,0.10)',
            'xl'  => '0 16px 48px rgba(0,0,0,0.12)',
            'card'=> $intensity === 'elevated' ? '0 8px 32px rgba(0,0,0,0.10)' : '0 2px 8px rgba(0,0,0,0.06)',
        ];
    }

    // ── Private: Visual Density ───────────────────────────────────────────────

    private function measureVisualDensity(string $html, array $dom): string
    {
        $sectionCount = $dom['sections_count'] ?? 0;
        $cardCount    = ($dom['cards']['count'] ?? 0);

        // Count items per section
        $density = 'standard';
        if ($cardCount > 20 || $sectionCount > 8) $density = 'tight';
        if ($cardCount < 5  || $sectionCount < 4) $density = 'spacious';

        // Check CSS spacing hints
        if (str_contains($html, 'py-20') || str_contains($html, 'py-24') || str_contains($html, 'py-32')) {
            $density = 'spacious';
        } elseif (str_contains($html, 'py-4') || str_contains($html, 'py-6')) {
            $density = 'tight';
        }

        return $density;
    }

    // ── Private: Auxiliary detectors ─────────────────────────────────────────

    private function hasDarkMode(string $html): bool
    {
        return str_contains($html, 'dark:') || str_contains($html, 'prefers-color-scheme: dark')
            || str_contains($html, 'data-theme') || str_contains($html, 'dark-mode');
    }

    private function detectAnimations(string $html): bool
    {
        return str_contains($html, '@keyframes') || str_contains($html, 'transition')
            || str_contains($html, 'animation') || str_contains($html, 'animate-')
            || str_contains($html, 'AOS') || str_contains($html, 'gsap');
    }

    private function detectStyleFramework(string $html): string
    {
        if (str_contains($html, 'tailwind') || str_contains($html, 'tw-')) return 'tailwind';
        if (str_contains($html, 'bootstrap') || preg_match('/\bcol-(xs|sm|md|lg|xl)-\d+\b/', $html)) return 'bootstrap';
        if (str_contains($html, 'bulma')) return 'bulma';
        if (str_contains($html, 'foundation')) return 'foundation';
        if (str_contains($html, 'mui') || str_contains($html, 'material-ui')) return 'material-ui';
        return 'custom';
    }

    // ── Inference from style keyword ──────────────────────────────────────────

    private function guessTypographyFromStyle(string $style): array
    {
        return match ($style) {
            'minimal'   => ['primary_font' => 'Inter', 'scale' => 'tailwind', 'base_size' => '16px', 'weights' => [400, 600], 'line_height' => '1.5'],
            'corporate' => ['primary_font' => 'Inter', 'scale' => 'tailwind', 'base_size' => '16px', 'weights' => [400, 700], 'line_height' => '1.6'],
            'creative'  => ['primary_font' => 'Poppins', 'scale' => 'tailwind', 'base_size' => '17px', 'weights' => [400, 600, 700], 'line_height' => '1.65'],
            default     => ['primary_font' => 'Vazirmatn', 'scale' => 'tailwind', 'base_size' => '16px', 'weights' => [400, 600, 700], 'line_height' => '1.6'],
        };
    }

    private function guessButtonStyle(string $style): array
    {
        return match ($style) {
            'minimal'   => ['shape' => 'rounded', 'has_shadow' => false, 'primary_style' => 'outline'],
            'corporate' => ['shape' => 'rounded', 'has_shadow' => true, 'primary_style' => 'filled'],
            'creative'  => ['shape' => 'pill', 'has_shadow' => true, 'primary_style' => 'filled', 'has_gradient' => true],
            default     => ['shape' => 'rounded', 'has_shadow' => true, 'primary_style' => 'filled'],
        };
    }

    private function guessRadiusFromStyle(string $style): array
    {
        return match ($style) {
            'minimal'   => ['scale' => 'sm', 'card' => '8px', 'button' => '6px'],
            'corporate' => ['scale' => 'md', 'card' => '12px', 'button' => '8px'],
            'creative'  => ['scale' => 'xl', 'card' => '20px', 'button' => '9999px'],
            default     => ['scale' => 'md', 'card' => '12px', 'button' => '8px'],
        };
    }

    private function guessShadowFromStyle(string $style): array
    {
        return match ($style) {
            'minimal'   => ['intensity' => 'none', 'card' => '0 1px 3px rgba(0,0,0,0.05)'],
            'corporate' => ['intensity' => 'soft', 'card' => '0 4px 16px rgba(0,0,0,0.08)'],
            'creative'  => ['intensity' => 'elevated', 'card' => '0 8px 32px rgba(0,0,0,0.12)'],
            default     => ['intensity' => 'soft', 'card' => '0 4px 16px rgba(0,0,0,0.08)'],
        };
    }

    private function guessDensityFromStyle(string $style): string
    {
        return match ($style) {
            'minimal'   => 'spacious',
            'corporate' => 'standard',
            'creative'  => 'standard',
            default     => 'standard',
        };
    }

    private function inferStyleFromText(string $text): string
    {
        if (str_contains($text, 'minimal') || str_contains($text, 'clean') || str_contains($text, 'simple')) return 'minimal';
        if (str_contains($text, 'corporate') || str_contains($text, 'enterprise') || str_contains($text, 'business')) return 'corporate';
        if (str_contains($text, 'creative') || str_contains($text, 'agency') || str_contains($text, 'portfolio')) return 'creative';
        return 'modern';
    }

    private function inferColorsFromText(string $text): array
    {
        // Check for brand/color hints
        foreach (self::NAMED_COLORS as $name => $hex) {
            if (str_contains($text, $name)) {
                return $this->buildPaletteFromColors([$hex], $hex);
            }
        }

        // Check for reference site mentions
        $siteColors = [
            'apple'    => '#0071e3',
            'stripe'   => '#635bff',
            'shopify'  => '#96bf48',
            'digikala' => '#ef3e56',
            'amazon'   => '#ff9900',
            'malltina' => '#e63946',
        ];

        foreach ($siteColors as $site => $hex) {
            if (str_contains($text, $site)) {
                return $this->buildPaletteFromColors(self::BRAND_SIGNATURES[$site] ?? [$hex], $hex);
            }
        }

        return $this->buildPaletteFromColors([], '#3b82f6');
    }

    private function buildPaletteFromColors(array $colors, string $primary): array
    {
        $cleaned = array_values(array_unique(array_filter($colors, fn ($c) => preg_match('/^#[0-9a-fA-F]{3,6}$/', $c))));
        return [
            'primary'    => $primary ?: ($cleaned[0] ?? '#3b82f6'),
            'secondary'  => $cleaned[1] ?? '#8b5cf6',
            'accent'     => $cleaned[2] ?? '#f59e0b',
            'background' => '#ffffff',
            'text'       => '#111827',
            'all'        => $cleaned,
            'count'      => count($cleaned),
        ];
    }

    private function extractCssCustomPropertyColors(string $html): array
    {
        $colors = [];
        // Match CSS custom properties that suggest primary/brand colors
        $patterns = [
            '/--(?:color-primary|primary-color|brand-color|accent-color|link-color)[^:]*:\s*(#[0-9a-fA-F]{3,6})/i',
            '/--(?:color-blue|color-main|color-key|ui-primary|button-color)[^:]*:\s*(#[0-9a-fA-F]{3,6})/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match_all($p, $html, $m)) {
                foreach ($m[1] as $c) {
                    $colors[] = strtolower($c);
                }
            }
        }
        return array_unique($colors);
    }

    private function hexToRgbLocal(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) return [128, 128, 128];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
