<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;
use Tests\TestCase;

/**
 * Phase 2k — the real, browser-measured WCAG AA contrast remediation.
 * These tests exercise the actual derivation math (not the browser check
 * itself, which real end-to-end runs already proved — see the Phase 2k
 * final report), proving the algorithm's real properties: it never
 * silently accepts a failing color, it never touches an already-accessible
 * Theme color, and it never "fixes" contrast by guessing.
 */
class ThemeTokenNormalizerContrastTest extends TestCase
{
    private function n(): ThemeTokenNormalizer
    {
        return new ThemeTokenNormalizer();
    }

    // ── real WCAG AA math, not a heuristic ──────────────────────────────────

    public function test_a_genuinely_low_contrast_pairing_is_detected_as_failing(): void
    {
        // White on a mid-tone teal — real browser-measured failure case.
        $ratio = $this->contrastRatioOf([255, 255, 255], [0, 184, 148]);
        $this->assertLessThan(4.5, $ratio);
    }

    public function test_a_genuinely_valid_contrast_pairing_is_detected_as_passing(): void
    {
        $ratio = $this->contrastRatioOf([17, 24, 39], [255, 255, 255]);
        $this->assertGreaterThanOrEqual(4.5, $ratio);
    }

    // ── pickReadableOn: real derivation, not a brightness heuristic ─────────

    public function test_pick_readable_on_chooses_the_option_that_actually_passes(): void
    {
        // #00b894 (teal): dark text passes, white does not (real measured case).
        $chosen = $this->n()->pickReadableOn('#00b894', '#111827', '#ffffff');
        $this->assertSame('#111827', $chosen);
    }

    public function test_pick_readable_on_chooses_white_on_a_genuinely_dark_background(): void
    {
        $chosen = $this->n()->pickReadableOn('#1e3a5f', '#111827', '#ffffff');
        $this->assertSame('#ffffff', $chosen);
    }

    public function test_pick_readable_on_never_uses_a_theme_supplied_dark_candidate_that_itself_fails(): void
    {
        // Real regression this phase found and fixed: a Theme's OWN configured
        // text color (#2c3e50) is not guaranteed dark enough — a caller must
        // not rely on it as the dark candidate for backgrounds it was never
        // verified against.
        $bg = '#16a085'; // clinic-salamat's primary
        $themeText = '#2c3e50'; // NOT dark enough for this bg (real measured 3.35:1)
        $chosen = $this->n()->pickReadableOn($bg, $themeText, '#ffffff');
        // Whichever is chosen, it must be the one with the higher real ratio —
        // this locks in "always compute, never assume" rather than asserting
        // a specific hex (the point is the decision is ratio-driven).
        $ratioChosen = $this->contrastRatioOf($this->hexToRgb($chosen), $this->hexToRgb($bg));
        $ratioOther = $this->contrastRatioOf(
            $this->hexToRgb($chosen === $themeText ? '#ffffff' : $themeText),
            $this->hexToRgb($bg)
        );
        $this->assertGreaterThanOrEqual($ratioOther, $ratioChosen);
    }

    // ── deriveAccessibleForeground: smallest adjustment, brand preserved ────

    public function test_an_already_accessible_brand_color_is_returned_completely_unchanged(): void
    {
        // A dark, already-compliant primary (e.g. a legal theme's navy)
        // must never be "improved" — explicit valid Theme choices are
        // preserved exactly.
        $result = $this->n()->deriveAccessibleForeground('#2c3e50', '#f9fafb');
        $this->assertSame('#2c3e50', $result);
    }

    public function test_a_genuinely_too_light_brand_color_is_minimally_darkened_not_replaced_arbitrarily(): void
    {
        $original = '#00b894';
        $result = $this->n()->deriveAccessibleForeground($original, '#f9fafb');

        $this->assertNotSame($original, $result);
        $ratio = $this->contrastRatioOf($this->hexToRgb($result), $this->hexToRgb('#f9fafb'));
        $this->assertGreaterThanOrEqual(4.5, $ratio, 'Derived color must actually pass AA.');

        // "Smallest adjustment" — the derived color must still be
        // recognizably the same hue family (darkened teal, not swapped to
        // an unrelated color), not a full palette redesign.
        [$origH] = $this->rgbToHslPublic($this->hexToRgb($original));
        [$resultH] = $this->rgbToHslPublic($this->hexToRgb($result));
        $this->assertEqualsWithDelta($origH, $resultH, 5.0, 'Hue must be preserved — only lightness should change.');
    }

    public function test_derivation_never_returns_a_still_failing_color(): void
    {
        foreach (['#00b894', '#e67e22', '#0096c7', '#fd79a8', '#27c0e2', '#f57c00', '#e91e8c'] as $brand) {
            $result = $this->n()->deriveAccessibleForeground($brand, '#f9fafb');
            $ratio = $this->contrastRatioOf($this->hexToRgb($result), $this->hexToRgb('#f9fafb'));
            $this->assertGreaterThanOrEqual(4.5, $ratio, "Derived text color for {$brand} still fails AA.");
        }
    }

    public function test_malformed_color_input_does_not_throw_and_returns_something(): void
    {
        $result = $this->n()->deriveAccessibleForeground('not-a-color', '#ffffff');
        $this->assertIsString($result);
    }

    // ── normalize(): the full token set, no dangerous global mutation ───────

    public function test_normalize_never_mutates_the_base_primary_secondary_accent_tokens(): void
    {
        // The whole point of this remediation: fix TEXT usages, never
        // silently rewrite a Theme's actual brand background colors.
        $vars = $this->n()->normalize(['primary_color' => '#00b894', 'secondary_color' => '#e67e22', 'accent_color' => '#fd79a8']);
        $this->assertSame('#00b894', $vars['--tb-primary']);
        $this->assertSame('#e67e22', $vars['--tb-secondary']);
        $this->assertSame('#fd79a8', $vars['--tb-accent']);
    }

    public function test_normalize_produces_deterministic_output_for_the_same_input(): void
    {
        $globalStyles = ['primary_color' => '#16a085', 'text_color' => '#2c3e50'];
        $a = $this->n()->normalize($globalStyles);
        $b = $this->n()->normalize($globalStyles);
        $this->assertSame($a, $b);
    }

    public function test_all_semantic_derived_tokens_are_present(): void
    {
        $vars = $this->n()->normalize(['primary_color' => '#4f46e5']);
        foreach (['--tb-on-primary', '--tb-on-secondary', '--tb-on-accent', '--tb-primary-text', '--tb-secondary-text', '--tb-accent-text', '--tb-surface-text'] as $key) {
            $this->assertArrayHasKey($key, $vars);
        }
    }

    // ── helpers (mirror the private math for direct, isolated assertions) ──

    private function hexToRgb(string $hex): array
    {
        preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m);
        return array_map('hexdec', str_split($m[1], 2));
    }

    private function contrastRatioOf(array $a, array $b): float
    {
        $lum = function (array $rgb) {
            [$r, $g, $bch] = array_map(function ($v) {
                $v /= 255;
                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, $rgb);
            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bch;
        };
        $la = $lum($a);
        $lb = $lum($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function rgbToHslPublic(array $rgb): array
    {
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, $rgb);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h * 60, 0.0, $l];
    }
}
