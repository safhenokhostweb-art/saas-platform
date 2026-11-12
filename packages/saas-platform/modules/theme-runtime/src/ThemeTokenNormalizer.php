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
     * WU-C commercial tokens: `colors.<key>` (and `dark_mode.<key>`) =>
     * CSS variable. Opt-in only — unlike every token above, none of these
     * has a default. The shared block sheet and BlockRenderer read each one
     * as `var(--tb-<token>, <legacy literal>)`, each consumer keeping its own
     * old value as the fallback, so a Theme that does not define a key
     * renders exactly as it did before the token existed. media_bg and
     * on_overlay are the slide background and caption colour WU-B's
     * `.tb-slide*` rules already read.
     */
    public const COMMERCIAL_TOKENS = [
        'overlay' => '--tb-overlay',
        'backdrop' => '--tb-backdrop',
        'star' => '--tb-star',
        'border_strong' => '--tb-border-strong',
        'media_placeholder' => '--tb-media-placeholder',
        'media_bg' => '--tb-media-bg',
        'on_overlay' => '--tb-on-overlay',
        // PLATFORM-THEME-SPEC-v1 (ADR-083): interactive-state and form
        // surface colours a commercial preset may author.
        'primary_hover' => '--tb-primary-hover',
        'input_bg' => '--tb-input-bg',
    ];

    /**
     * Rating stars are meaningful graphics, so an authored star colour must
     * reach WCAG 1.4.11's 3:1 non-text minimum against the surface. (The
     * legacy #f59e0b fallback itself measures about 2:1 on #f9fafb; it is
     * kept only because the zero-diff rule forbids changing an undefined
     * token's rendering.)
     */
    private const STAR_MIN_CONTRAST = 3.0;

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
            // Commercial Productization fase-2: semantic status colors were
            // completely absent from the token set — every "success"/
            // "warning"/"error" badge or state across both hosts was an
            // independently hardcoded hex with no theme-level authority.
            // Same author-configurable + accessibility-derived treatment as
            // primary/secondary/accent (see the on-*/*-text block below).
            '--tb-success' => $globalStyles['success_color'] ?? $colors['success'] ?? '#16a34a',
            '--tb-warning' => $globalStyles['warning_color'] ?? $colors['warning'] ?? '#d97706',
            '--tb-error' => $globalStyles['error_color'] ?? $colors['error'] ?? '#dc2626',
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
            // Commercial Productization fase-5: a real, confirmed gap —
            // every card/table/divider border across both hosts was an
            // independently hardcoded #e5e7eb, with no theme-level
            // authority and (critically) no dark-mode counterpart, so a
            // light gray border stayed invisible-thin on a dark surface
            // even after every other real dark-mode fix this phase made.
            '--tb-border' => $colors['border'] ?? '#e5e7eb',
            '--tb-font-heading' => $globalStyles['font_family'] ?? $typography['font_heading'] ?? 'Vazirmatn, Tahoma, sans-serif',
            '--tb-font-body' => $globalStyles['font_family'] ?? $typography['font_body'] ?? 'Vazirmatn, Tahoma, sans-serif',
            '--tb-scale' => $typography['scale'] ?? '1.25',
            '--tb-radius' => $borderRadius['md'] ?? '0.75rem',
            // Canvas overhaul unit 3 (ADR-073): one radius scale (md used to
            // default to 0.5rem while --tb-radius, its own alias, was 0.75rem)
            // and soft two-layer elevation. Authored values still win.
            '--tb-radius-sm' => $borderRadius['sm'] ?? '0.5rem',
            '--tb-radius-md' => $borderRadius['md'] ?? '0.75rem',
            '--tb-radius-lg' => $borderRadius['lg'] ?? '1.125rem',
            '--tb-shadow-sm' => $shadows['sm'] ?? '0 1px 2px rgb(15 23 42 / 0.04), 0 2px 6px -1px rgb(15 23 42 / 0.06)',
            '--tb-shadow-md' => $shadows['md'] ?? '0 2px 4px rgb(15 23 42 / 0.04), 0 8px 20px -6px rgb(15 23 42 / 0.10)',
            '--tb-shadow-lg' => $shadows['lg'] ?? '0 4px 10px -2px rgb(15 23 42 / 0.05), 0 22px 44px -14px rgb(15 23 42 / 0.20)',
            '--tb-container' => '80rem',
        ]);

        if (!empty($spacing['unit'])) {
            $vars['--tb-spacing-unit'] = $spacing['unit'];
        }
        if (isset($spacing['scale'])) {
            $vars['--tb-spacing-scale'] = (string) $spacing['scale'];
        }

        foreach (self::COMMERCIAL_TOKENS as $key => $token) {
            $value = $this->authoredValue($colors[$key] ?? null);
            if ($value !== null) {
                $vars[$token] = $value;
            }
        }
        if (isset($vars['--tb-star'])) {
            $vars['--tb-star'] = $this->accessibleStar($vars['--tb-star'], $vars['--tb-surface']);
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
        foreach (['primary', 'secondary', 'accent', 'success', 'warning', 'error'] as $key) {
            $vars["--tb-on-{$key}"] = $this->pickReadableOn($vars["--tb-{$key}"], '#111827');
            $vars["--tb-{$key}-text"] = $this->deriveAccessibleForeground($vars["--tb-{$key}"], $vars['--tb-surface']);
        }
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

    /**
     * deriveAccessibleForeground() measures the unrounded HSL step, so the
     * hex it returns can land a hair under the ratio (#f59e0b on #f9fafb
     * gives 2.996:1). One more derivation from that hex takes the next step.
     */
    private function accessibleStar(string $star, string $surface): string
    {
        $star = $this->deriveAccessibleForeground($star, $surface, self::STAR_MIN_CONTRAST);
        $rgb = $this->parseHex($star);
        $bg = $this->parseHex($surface);
        if ($rgb !== null && $bg !== null && $this->contrastRatio($rgb, $bg) < self::STAR_MIN_CONTRAST) {
            $star = $this->deriveAccessibleForeground($star, $surface, self::STAR_MIN_CONTRAST);
        }

        return $star;
    }

    /** An authored opt-in token value, or null when the Theme leaves it undefined. */
    private function authoredValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
     *
     * This is the single canonical convergence point for every consumer
     * (Builder preview, customer runtime, SaaS preview) — it is embedded
     * directly inside a `<style>{!! ... !!}</style>` block with no further
     * escaping downstream, so it is also the one place that must guarantee
     * every value it emits is safe there, regardless of how the value
     * reached global_styles (authored in the Builder, imported from a
     * file, or carried over from an older ThemeRelease).
     */
    public function renderCssBlock(array $globalStyles, array $platformDefaults = []): string
    {
        $vars = $this->normalize($globalStyles, $platformDefaults);
        $lines = array_map(fn ($k, $v) => "  {$k}: {$this->sanitizeCssValue($v)};", array_keys($vars), $vars);
        $css = ":root {\n" . implode("\n", $lines) . "\n}\n";

        $dark = $globalStyles['dark_mode'] ?? [];
        if (!empty($dark['enabled'])) {
            $css .= "\n[data-theme=\"dark\"] {\n";
            $darkSurface = $dark['surface'] ?? '#1f2937';
            $darkVars = [
                '--tb-primary' => $dark['primary'] ?? $vars['--tb-primary'],
                '--tb-secondary' => $dark['secondary'] ?? $vars['--tb-secondary'],
                '--tb-accent' => $dark['accent'] ?? $vars['--tb-accent'],
                '--tb-success' => $dark['success'] ?? $vars['--tb-success'],
                '--tb-warning' => $dark['warning'] ?? $vars['--tb-warning'],
                '--tb-error' => $dark['error'] ?? $vars['--tb-error'],
                '--tb-background' => $dark['background'] ?? '#111827',
                '--tb-surface' => $darkSurface,
                '--tb-text' => $dark['text'] ?? '#f9fafb',
                // Phase 2k's WCAG-AA text-on-muted-surface fix used
                // #4b5563 tuned for a LIGHT surface — a dark theme's own
                // surface is dark, so that same fixed value would itself
                // fail contrast. Real measured default for a dark surface.
                '--tb-text-muted' => $dark['text_muted'] ?? '#9ca3af',
                '--tb-border' => $dark['border'] ?? '#334155',
            ];
            // Same accessibility-derivation the light-mode block above
            // uses, recomputed against the DARK surface — a color that is
            // readable on a light card is not guaranteed readable on a dark
            // one (e.g. a light pastel accent), so this must be its own
            // computation, never copied from the light-mode vars.
            foreach (['primary', 'secondary', 'accent', 'success', 'warning', 'error'] as $key) {
                $darkVars["--tb-on-{$key}"] = $this->pickReadableOn($darkVars["--tb-{$key}"], '#111827');
                $darkVars["--tb-{$key}-text"] = $this->deriveAccessibleForeground($darkVars["--tb-{$key}"], $darkSurface);
            }
            $darkVars['--tb-surface-text'] = $this->pickReadableOn($darkSurface, '#111827');
            // Commercial tokens stay opt-in in dark mode too: only an
            // explicit dark_mode.<key> overrides. The star is the exception
            // — a light-mode star readable on a light card is re-derived
            // against the dark surface, like the *-text tokens above.
            foreach (self::COMMERCIAL_TOKENS as $key => $token) {
                $value = $this->authoredValue($dark[$key] ?? null);
                if ($value !== null) {
                    $darkVars[$token] = $value;
                }
            }
            $darkStar = $darkVars['--tb-star'] ?? $this->authoredValue($globalStyles['colors']['star'] ?? null);
            if ($darkStar !== null) {
                $darkVars['--tb-star'] = $this->accessibleStar($darkStar, $darkSurface);
            }
            foreach ($darkVars as $k => $v) {
                $css .= "  {$k}: {$this->sanitizeCssValue($v)};\n";
            }
            $css .= "}\n";
        }

        $custom = $globalStyles['custom_css'] ?? '';
        if (trim((string) $custom) !== '') {
            $css .= "\n/* Theme custom CSS */\n" . $this->sanitizeCustomCss((string) $custom) . "\n";
        }

        return $css;
    }

    /**
     * A token value (color, font-family, radius, shadow, ...) is meant to
     * be a short scalar CSS value, never a full declaration or a tag —
     * strip the characters that would let one break out of its own
     * `--var: value;` line: `;`/`{`/`}` end or open a new declaration/rule,
     * `<`/`>` and CR/LF have no legitimate use in such a value and are the
     * mechanism for breaking out of the surrounding `<style>` tag or
     * corrupting the block's line structure, `/*`/`*\/` opens/closes a
     * comment that could swallow or expose surrounding rules.
     */
    private function sanitizeCssValue(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return str_replace(
            ['<', '>', ';', '{', '}', "\r", "\n", '/*', '*/'],
            '',
            $value
        );
    }

    /**
     * Author-supplied custom_css is legitimately full CSS syntax (including
     * a literal `>` for child combinators), so it is not escaped the way a
     * single token value is — it is only prevented from ending the
     * surrounding `<style>` tag. A literal `<` has no legitimate use inside
     * a CSS value/selector, so stripping it closes `</style><script>...`
     * (and every other tag-open/close sequence) without breaking any real
     * stylesheet.
     */
    private function sanitizeCustomCss(string $css): string
    {
        return str_replace('<', '', $css);
    }
}
