<?php

namespace SaaSPlatform\ThemeRuntime;

/**
 * Phase 2h (ADR-029) — the one shared authority turning a Theme's raw
 * `global_styles` JSON into the canonical `--tb-*` CSS variable set both
 * hosts' runtime CSS (theme-runtime-contract.css and each host's own
 * remaining stylesheet) already consumes via `var(--tb-primary, ...)`
 * fallbacks.
 *
 * This is NOT a port of saas-platform's GlobalStyleEngine: it deliberately
 * excludes any host-specific concern (saas-platform's per-site/reseller
 * `DesignSystemService` override, in particular) — a host that has its own
 * platform-level base tokens passes them in as $platformDefaults; a host
 * with none (customer-site today) simply omits it and gets this
 * normalizer's own sensible defaults. Both hosts calling the same
 * normalize() with the same $globalStyles get byte-identical `--tb-*`
 * values — that identity IS the portable token contract.
 */
class ThemeTokenNormalizer
{
    /**
     * @param array $globalStyles   A Theme's raw `global_styles` field.
     * @param array $platformDefaults Optional host platform-level base
     *   variables (e.g. saas-platform's per-site DesignSystemService
     *   output) — merged in as the lowest-priority fallback layer.
     * @return array<string,string> Canonical `--tb-*` => value map.
     */
    public function normalize(array $globalStyles, array $platformDefaults = []): array
    {
        $colors = $globalStyles['colors'] ?? [];
        $typography = $globalStyles['typography'] ?? [];
        $spacing = $globalStyles['spacing'] ?? [];
        $borderRadius = $globalStyles['border_radius'] ?? [];
        $shadows = $globalStyles['shadows'] ?? [];

        $vars = array_merge($platformDefaults, [
            '--tb-primary' => $globalStyles['primary_color'] ?? $colors['primary'] ?? $platformDefaults['--ds-primary'] ?? '#4f46e5',
            '--tb-secondary' => $globalStyles['secondary_color'] ?? $colors['secondary'] ?? $platformDefaults['--ds-secondary'] ?? '#6366f1',
            '--tb-accent' => $globalStyles['accent_color'] ?? $colors['accent'] ?? '#818cf8',
            '--tb-background' => $globalStyles['background_color'] ?? $colors['background'] ?? '#ffffff',
            '--tb-surface' => $colors['surface'] ?? '#f9fafb',
            '--tb-text' => $globalStyles['text_color'] ?? $colors['text'] ?? '#111827',
            // Phase 2k: was #6b7280 (gray-500) — real browser measurement
            // found it fails WCAG AA (as low as 3.94:1) against several
            // canonical themes' actual off-white surfaces; #4b5563
            // (gray-600) clears every real measured case with margin
            // (6.2:1+) while staying visually "muted"/secondary, not a
            // full-strength body-text color.
            '--tb-text-muted' => $colors['text_muted'] ?? '#4b5563',
            '--tb-font-heading' => $globalStyles['font_family'] ?? $typography['font_heading'] ?? 'Vazirmatn, Tahoma, sans-serif',
            '--tb-font-body' => $globalStyles['font_family'] ?? $typography['font_body'] ?? 'Vazirmatn, Tahoma, sans-serif',
            '--tb-scale' => $typography['scale'] ?? '1.25',
            '--tb-radius' => $borderRadius['md'] ?? '0.75rem',
            '--tb-radius-sm' => $borderRadius['sm'] ?? '0.25rem',
            '--tb-radius-md' => $borderRadius['md'] ?? '0.5rem',
            '--tb-radius-lg' => $borderRadius['lg'] ?? '0.75rem',
            '--tb-shadow-sm' => $shadows['sm'] ?? '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            '--tb-shadow-md' => $shadows['md'] ?? '0 4px 6px -1px rgb(0 0 0 / 0.1)',
            '--tb-shadow-lg' => $shadows['lg'] ?? '0 10px 15px -3px rgb(0 0 0 / 0.1)',
            '--tb-container' => '80rem',
        ]);

        if (!empty($spacing['unit'])) {
            $vars['--tb-spacing-unit'] = $spacing['unit'];
        }
        if (isset($spacing['scale'])) {
            $vars['--tb-spacing-scale'] = (string) $spacing['scale'];
        }

        // Phase 2k (ADR-031 addendum / ADR-032) — real, browser-measured WCAG
        // AA contrast failures across 17 canonical themes traced to two
        // recurring shared-CSS patterns, both now resolved by semantic
        // tokens computed here (not per-theme color edits):
        //   1. `--tb-on-primary`/`--tb-on-secondary`/`--tb-on-accent` — the
        //      TEXT color painted on a background actually filled with the
        //      brand color (hero/CTA sections). A Theme's own primary color
        //      is fully author-configurable; white text is only safe when
        //      that color is dark enough.
        //   2. `--tb-primary-text` (and secondary/accent variants) — the
        //      brand color used AS text/foreground on a light surface
        //      (.tb-stat-value, .tb-service-link, nav hover, etc.). A color
        //      that is a perfectly good, vivid BACKGROUND hue (teal/orange/
        //      pink/cyan) is very often too light to serve as body-sized
        //      TEXT at 4.5:1 — this derives a minimally-darkened variant,
        //      touched ONLY when the original genuinely fails, so a Theme
        //      whose primary is already dark enough keeps its exact color.
        // Deliberately NOT $vars['--tb-text'] as the dark candidate — a
        // real browser-measured failure (4 themes, ratios 3.35-4.19) found
        // a Theme's own configured text_color (e.g. #2c3e50, a medium-dark
        // slate chosen for body text on a near-white page) is not
        // guaranteed dark enough to itself pass AA against every brand
        // color. #111827 is a true near-black extreme, maximizing the
        // chance either candidate actually reaches 4.5:1.
        $vars['--tb-on-primary'] = $this->pickReadableOn($vars['--tb-primary'], '#111827');
        $vars['--tb-on-secondary'] = $this->pickReadableOn($vars['--tb-secondary'], '#111827');
        $vars['--tb-on-accent'] = $this->pickReadableOn($vars['--tb-accent'], '#111827');
        $vars['--tb-primary-text'] = $this->deriveAccessibleForeground($vars['--tb-primary'], $vars['--tb-surface']);
        $vars['--tb-secondary-text'] = $this->deriveAccessibleForeground($vars['--tb-secondary'], $vars['--tb-surface']);
        $vars['--tb-accent-text'] = $this->deriveAccessibleForeground($vars['--tb-accent'], $vars['--tb-surface']);
        // `--tb-text` is tuned for content sitting on `--tb-background` (the
        // page body) — a genuinely dark theme sets --tb-text near-white to
        // suit its dark body, but --tb-surface (elevated cards) defaults to
        // a light tone independently of that intent unless a Theme
        // explicitly overrides colors.surface. A card using --tb-surface
        // as its background must not blindly inherit --tb-text (real
        // browser-measured failure: a dark theme's near-white text on a
        // still-light testimonial card, ratio 1.05). --tb-surface-text is
        // the correct one for that case, derived the same way.
        // Deliberately NOT $onLight (--tb-text) as the dark candidate here —
        // for a dark theme that IS near-white, exactly the wrong choice for
        // a light surface. A genuinely dark neutral is the correct default
        // dark candidate regardless of the theme's own body text color.
        $vars['--tb-surface-text'] = $this->pickReadableOn($vars['--tb-surface'], '#111827');

        return $vars;
    }

    /**
     * Picks white or the Theme's own dark text color, whichever reaches
     * WCAG AA (4.5:1) against $bgHex — real contrast-ratio math, not a
     * brightness/luminance-threshold heuristic. If neither reaches AA
     * (mathematically rare — white and near-black are the two extremes),
     * returns whichever achieves the higher ratio as a best effort; this
     * is never silently reported as verified/passing on its own — the real
     * browser contrast check is the actual source of truth.
     */
    public function pickReadableOn(string $bgHex, string $darkText = '#111827', string $lightText = '#ffffff'): string
    {
        $bg = $this->parseHex($bgHex);
        if ($bg === null) {
            return $lightText; // unparseable authored value — safest common default
        }
        $dark = $this->parseHex($darkText) ?? [0x11, 0x18, 0x27];
        $light = $this->parseHex($lightText) ?? [0xff, 0xff, 0xff];

        $ratioDark = $this->contrastRatio($dark, $bg);
        $ratioLight = $this->contrastRatio($light, $bg);

        // Whichever passes AA wins; if both or neither pass, prefer the
        // higher ratio (best effort — the real browser check is the source
        // of truth, this is never self-reported as a verified pass).
        return $ratioDark >= $ratioLight ? $darkText : $lightText;
    }

    /**
     * Returns $candidateHex unchanged if it already reaches $minRatio
     * against $bgHex. Otherwise darkens (or lightens, for an already-dark
     * background) it in HSL space in small steps until it does, capped at
     * pure black/white — the smallest adjustment that reaches reliable
     * WCAG AA, never a full palette redesign.
     */
    public function deriveAccessibleForeground(string $candidateHex, string $bgHex, float $minRatio = 4.5): string
    {
        $candidate = $this->parseHex($candidateHex);
        $bg = $this->parseHex($bgHex);
        if ($candidate === null || $bg === null) {
            return $candidateHex;
        }
        if ($this->contrastRatio($candidate, $bg) >= $minRatio) {
            return $candidateHex; // already accessible — brand color untouched
        }

        [$h, $s, $l] = $this->rgbToHsl($candidate);
        $bgLuminance = $this->relativeLuminance($bg);
        $darkening = $bgLuminance >= 0.5; // light surface → darken text toward black

        for ($step = 1; $step <= 20; $step++) {
            $newL = $darkening ? max(0.0, $l - $step * 0.045) : min(1.0, $l + $step * 0.045);
            $rgb = $this->hslToRgb($h, $s, $newL);
            if ($this->contrastRatio($rgb, $bg) >= $minRatio) {
                return $this->rgbToHex($rgb);
            }
        }

        // Exhausted the hue-preserving range — fall back to the absolute
        // extreme (never silently return a still-failing color).
        return $darkening ? '#000000' : '#ffffff';
    }

    private function parseHex(string $hex): ?array
    {
        if (!preg_match('/^#?([0-9a-f]{6})$/i', trim($hex), $m)) {
            return null;
        }
        return array_map('hexdec', str_split($m[1], 2));
    }

    private function rgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => max(0, min(255, (int) round($c))), $rgb));
    }

    private function relativeLuminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function ($v) {
            $v = $v / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function contrastRatio(array $rgbA, array $rgbB): float
    {
        $lA = $this->relativeLuminance($rgbA);
        $lB = $this->relativeLuminance($rgbB);
        return (max($lA, $lB) + 0.05) / (min($lA, $lB) + 0.05);
    }

    /** @return array{0:float,1:float,2:float} [hue 0-360, saturation 0-1, lightness 0-1] */
    private function rgbToHsl(array $rgb): array
    {
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, $rgb);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h * 60, $s, $l];
    }

    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s == 0.0) {
            $v = $l * 255;
            return [$v, $v, $v];
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hk = $h / 360;
        $hue2rgb = function ($p, $q, $t) {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };
        return [
            $hue2rgb($p, $q, $hk + 1 / 3) * 255,
            $hue2rgb($p, $q, $hk) * 255,
            $hue2rgb($p, $q, $hk - 1 / 3) * 255,
        ];
    }

    /**
     * Renders a full `:root { ... }` block (plus optional dark-mode
     * override and authored custom CSS) — the one shared CSS-emission
     * shape both hosts should use, given the same normalize() output.
     */
    public function renderCssBlock(array $globalStyles, array $platformDefaults = []): string
    {
        $vars = $this->normalize($globalStyles, $platformDefaults);
        $lines = array_map(fn ($k, $v) => "  {$k}: {$v};", array_keys($vars), $vars);
        $css = ":root {\n" . implode("\n", $lines) . "\n}\n";

        $dark = $globalStyles['dark_mode'] ?? [];
        if (!empty($dark['enabled'])) {
            $css .= "\n[data-theme=\"dark\"] {\n";
            $darkVars = [
                '--tb-primary' => $dark['primary'] ?? $vars['--tb-primary'],
                '--tb-background' => $dark['background'] ?? '#111827',
                '--tb-surface' => $dark['surface'] ?? '#1f2937',
                '--tb-text' => $dark['text'] ?? '#f9fafb',
            ];
            foreach ($darkVars as $k => $v) {
                $css .= "  {$k}: {$v};\n";
            }
            $css .= "}\n";
        }

        $custom = $globalStyles['custom_css'] ?? '';
        if (trim($custom) !== '') {
            $css .= "\n/* Theme custom CSS */\n" . $custom . "\n";
        }

        return $css;
    }
}
