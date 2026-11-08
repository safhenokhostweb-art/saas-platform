<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

use InvalidArgumentException;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer;

/**
 * ADR-082 step 2 — the nav_header's CSS custom properties.
 *
 * resources/css/theme-runtime-header.css reads every design value of the
 * header from a --tb-hdr-<name> property whose fallback is the value below.
 * A header with its own values gets one scoped rule on its root,
 * `#tb-<id>{--tb-hdr-<name>:<value>;…}`, built here. Only known names are
 * emitted, every value is checked against the grammar of its kind, and a
 * value equal to the default is dropped, so a default header emits
 * nothing. HeaderStyleVarsTest keeps DEFAULTS equal to the stylesheet's
 * fallbacks.
 *
 * Kinds: length (a number with an allowlisted unit), color (the renderer's
 * colour grammar, incl. var(--tb-*) swatches), shadow (a structured
 * InspectorFieldRegistry shadow), weight (100–900), time (s / ms), number
 * (a unitless line height, 0–5) and font (a theme font token).
 * Suffix -m = phones (< 768px). A null default marks a gated value: its
 * rule has no fallback and applies only through data-tb-hdr-set (step 3).
 */
final class HeaderStyleVars
{
    /** name => [kind, default (the stylesheet fallback)] */
    public const VARS = [
        'gap' => ['length', 'clamp(.65rem,2vw,1.5rem)'],
        'max-w' => ['length', '80rem'],
        'min-h' => ['length', '4.25rem'],
        'pad-block' => ['length', '.5rem'],
        'pad-inline' => ['length', '1rem'],
        'logo-gap' => ['length', '.55rem'],
        'logo-weight' => ['weight', '800'],
        'logo-max-w' => ['length', 'min(14rem,35vw)'],
        'menu-gap' => ['length', 'clamp(.75rem,1.7vw,1.5rem)'],
        'actions-gap' => ['length', '.55rem'],
        'search-gap' => ['length', '.35rem'],
        'popover-offset' => ['length', '.55rem'],
        'popover-w' => ['length', 'min(22rem,calc(100vw - 2rem))'],
        'popover-pad' => ['length', '.65rem'],
        'popover-bg' => ['color', 'var(--tb-surface,#fff)'],
        'popover-text' => ['color', 'var(--tb-text,#172033)'],
        'popover-border' => ['color', 'var(--tb-border,#d1d5db)'],
        'popover-radius' => ['length', '.5rem'],
        'popover-shadow' => ['shadow', '0 12px 32px rgb(15 23 42 / 18%)'],
        'search-w' => ['length', 'min(12rem,32vw)'],
        'search-h' => ['length', '2.5rem'],
        'search-pad-block' => ['length', '.5rem'],
        'search-pad-inline' => ['length', '.75rem'],
        'search-border' => ['color', 'var(--tb-border,#d1d5db)'],
        'search-radius' => ['length', '.35rem'],
        'search-bg' => ['color', 'var(--tb-surface,#fff)'],
        'icon-size' => ['length', '2.5rem'],
        'icon-pad' => ['length', '.45rem'],
        'icon-radius' => ['length', '.375rem'],
        'icon-bg' => ['color', 'transparent'],
        'icon-bg-hover' => ['color', 'color-mix(in srgb,currentColor 9%,transparent)'],
        'phone-h' => ['length', '2.5rem'],
        'phone-pad-inline' => ['length', '.35rem'],
        'phone-weight' => ['weight', '700'],
        'social-icon-h' => ['length', '1.2rem'],
        'social-chip-bg' => ['color', 'var(--tb-surface,#fff)'],
        'social-chip-border' => ['color', 'var(--tb-border,#d1d5db)'],
        'cta-h' => ['length', '2.5rem'],
        'cta-pad-block' => ['length', '.55rem'],
        'cta-pad-inline' => ['length', '1rem'],
        'cta-radius' => ['length', '.375rem'],
        'glow-duration' => ['time', '2.2s'],
        'min-h-m' => ['length', '3.75rem'],
        'gap-m' => ['length', '.5rem'],
        'pad-inline-m' => ['length', '.75rem'],
        'drawer-w' => ['length', 'min(22rem,88vw)'],
        'drawer-pad' => ['length', '1rem'],
        'drawer-bg' => ['color', 'var(--tb-surface,#fff)'],
        'drawer-text' => ['color', 'var(--tb-text,#172033)'],
        'drawer-shadow' => ['shadow', '-12px 0 32px rgb(15 23 42 / 18%)'],
        'drawer-gap' => ['length', '.5rem'],
        'drawer-item-h' => ['length', '2.75rem'],
        'drawer-item-pad-block' => ['length', '.6rem'],
        'drawer-item-pad-inline' => ['length', '.5rem'],
        'drawer-divider' => ['color', 'var(--tb-border,#e5e7eb)'],
        'backdrop' => ['color', 'rgb(15 23 42 / 52%)'],
        'actions-gap-m' => ['length', '.3rem'],
        'search-w-m' => ['length', 'min(9rem,32vw)'],
        'popover-offset-m' => ['length', '.25rem'],
        'popover-inset-m' => ['length', '.75rem'],
        'phone-max-w-m' => ['length', '7rem'],
        'phone-size-m' => ['length', '.8rem'],
        'cta-pad-inline-m' => ['length', '.65rem'],
        'cta-size-m' => ['length', '.82rem'],
        // ADR-082 step 3: one padding value per logical side, per device.
        'pad-bs' => ['length', 'var(--tb-hdr-pad-block, .5rem)'],
        'pad-be' => ['length', 'var(--tb-hdr-pad-block, .5rem)'],
        'pad-is' => ['length', 'var(--tb-hdr-pad-inline, 1rem)'],
        'pad-ie' => ['length', 'var(--tb-hdr-pad-inline, 1rem)'],
        'pad-bs-m' => ['length', 'var(--tb-hdr-pad-bs, var(--tb-hdr-pad-block, .5rem))'],
        'pad-be-m' => ['length', 'var(--tb-hdr-pad-be, var(--tb-hdr-pad-block, .5rem))'],
        'pad-is-m' => ['length', 'var(--tb-hdr-pad-inline-m, .75rem)'],
        'pad-ie-m' => ['length', 'var(--tb-hdr-pad-inline-m, .75rem)'],
        // Gated values (default null): their rules apply only while the
        // header lists them in data-tb-hdr-set (see GATED).
        'border-w' => ['length', null],
        'border-c' => ['color', 'var(--tb-border,#e5e7eb)'],
        'shadow' => ['shadow', null],
        'blur' => ['length', null],
        'menu-size' => ['length', '.95rem'],
        'menu-weight' => ['weight', '600'],
        'menu-lh' => ['number', null],
        'menu-ls' => ['length', null],
        'menu-font' => ['font', null],
        'menu-pad-y' => ['length', '.4rem'],
        'menu-pad-x' => ['length', '0'],
        'menu-color' => ['color', null],
        'menu-color-hover' => ['color', null],
        'menu-color-active' => ['color', null],
        'menu-bg' => ['color', null],
        'menu-bg-hover' => ['color', 'color-mix(in srgb,currentColor 10%,transparent)'],
        'menu-bg-active' => ['color', null],
        'pointer-color' => ['color', 'currentColor'],
        'pointer-h' => ['length', '2px'],
        // ADR-082 step 4: scrolled look, sticky shrink (theme-runtime-blocks.css), burger, drawer side.
        'shrink-h' => ['length', '3.5rem'],
        'logo-scale' => ['number', '0.8'],
        'scrolled-bg' => ['color', null],
        'scrolled-text' => ['color', null],
        'scrolled-shadow' => ['shadow', null],
        'burger-color' => ['color', null],
        'burger-size' => ['length', null],
        'drawer-shadow-end' => ['shadow', '12px 0 32px rgb(15 23 42 / 18%)'],
    ];

    /** Variables read by theme-runtime-blocks.css (the sticky shrink) rather than the header sheet. */
    public const BLOCK_SHEET_VARS = ['shrink-h', 'logo-scale'];

    /**
     * ADR-082 step 3: values whose rules replace something the default
     * cascade sets elsewhere, so they apply only while the header lists the
     * name in data-tb-hdr-set. A header that sets none renders as before.
     */
    public const GATED = [
        'border-w', 'shadow', 'blur', 'menu-size', 'menu-weight', 'menu-lh', 'menu-ls', 'menu-font',
        'menu-pad-y', 'menu-pad-x', 'menu-color', 'menu-color-hover', 'menu-color-active',
        'menu-bg', 'menu-bg-hover', 'menu-bg-active', 'pointer-color', 'pointer-h',
        'scrolled-bg', 'scrolled-text', 'scrolled-shadow', 'burger-color', 'burger-size',
    ];

    /** ADR-082 step 4: phone drawer style and side; side + start is the default and has no class. */
    public const DRAWER_STYLES = ['side', 'full', 'dropdown'];
    public const DRAWER_SIDES = ['start', 'end'];

    /** Pointer styles and animations; the defaults (underline, grow) have no class. */
    public const POINTERS = ['underline', 'overline', 'double', 'framed', 'background', 'text', 'none'];
    public const POINTER_ANIMATIONS = ['grow', 'fade', 'slide', 'none'];

    /**
     * Style-tab field => the variable each device writes. A field without a
     * mobile entry writes its base variable inside the phone media query.
     * `padding` is a logical spacing value: one variable per side.
     */
    private const FIELDS = [
        'min_height' => ['min-h', 'min-h-m'],
        'items_gap' => ['gap', 'gap-m'],
        'menu_gap' => ['menu-gap', 'drawer-gap'],
        'menu_item_padding_x' => ['menu-pad-x', 'drawer-item-pad-inline'],
        'menu_item_padding_y' => ['menu-pad-y', 'drawer-item-pad-block'],
        'border_bottom_width' => ['border-w', null],
        'border_bottom_color' => ['border-c', null],
        'header_shadow' => ['shadow', null],
        'backdrop_blur' => ['blur', null],
        'menu_color' => ['menu-color', null],
        'menu_color_hover' => ['menu-color-hover', null],
        'menu_color_active' => ['menu-color-active', null],
        'menu_bg' => ['menu-bg', null],
        'menu_bg_hover' => ['menu-bg-hover', null],
        'menu_bg_active' => ['menu-bg-active', null],
        'pointer_color' => ['pointer-color', null],
        'pointer_thickness' => ['pointer-h', null],
        // step 4
        'sticky_shrink_height' => ['shrink-h', null],
        'scrolled_bg_color' => ['scrolled-bg', null],
        'scrolled_text_color' => ['scrolled-text', null],
        'scrolled_shadow' => ['scrolled-shadow', null],
        'drawer_bg' => ['drawer-bg', null],
        'drawer_text' => ['drawer-text', null],
        'drawer_width' => ['drawer-w', null],
        'drawer_overlay_color' => ['backdrop', null],
        'drawer_divider_color' => ['drawer-divider', null],
        'burger_color' => ['burger-color', null],
        'burger_size' => ['burger-size', null],
    ];

    private const PADDING_SIDES = ['block-start' => 'bs', 'block-end' => 'be', 'inline-start' => 'is', 'inline-end' => 'ie'];
    private const TYPOGRAPHY = ['size' => 'menu-size', 'weight' => 'menu-weight', 'lineHeight' => 'menu-lh', 'letterSpacing' => 'menu-ls', 'fontToken' => 'menu-font'];
    private const FONT_TOKENS = ['body' => 'var(--tb-font-body, inherit)', 'heading' => 'var(--tb-font-heading, var(--tb-font-body, inherit))'];
    private const SIGNED_LENGTHS = ['menu-ls'];
    private const TABLET_QUERY = '@media(max-width:1023.98px)';
    private const MOBILE_QUERY = '@media(max-width:767.98px)';

    private const LENGTH = '/^-?(?:\d+|\d*\.\d+)(?:px|rem|em|%|vw|vh)$|^0$/';

    public static function property(string $name): string
    {
        if (!isset(self::VARS[$name])) {
            throw new InvalidArgumentException("Unknown header variable '{$name}'.");
        }
        return '--tb-hdr-' . $name;
    }

    /**
     * A value in its emitted CSS form, or null when it does not fit the
     * variable's kind (dropped, never emitted).
     */
    public static function value(string $name, mixed $value): ?string
    {
        $kind = self::VARS[$name][0] ?? null;
        return match ($kind) {
            // Only letter-spacing may go below zero; a negative size, gap,
            // padding or thickness is never a design value.
            'length' => ($css = self::length($value)) !== null && ($css[0] !== '-' || in_array($name, self::SIGNED_LENGTHS, true)) ? $css : null,
            'color' => is_string($value) && HtmlSanitizer::isCssColor($value) ? trim($value) : null,
            'shadow' => self::shadow($value),
            'weight' => is_numeric($value) && (int) $value >= 100 && (int) $value <= 900 && (int) $value % 100 === 0 ? (string) (int) $value : null,
            'time' => is_string($value) && preg_match('/^(?:\d+|\d*\.\d+)m?s$/', trim($value)) === 1 ? trim($value) : null,
            'number' => is_numeric($value) && (float) $value > 0 && (float) $value <= 5 ? (string) (0 + $value) : null,
            'font' => is_string($value) ? (self::FONT_TOKENS[$value] ?? null) : null,
            default => null,
        };
    }

    /**
     * ADR-082 step 3: a header's Style-tab props as CSS.
     *
     * Each device writes only its own value (key, key_tablet, key_mobile):
     * desktop on the root, tablet and phone in their media queries. A device
     * without its own value inherits from the stylesheet, which already
     * chains phone → desktop where the design does. Returns the scoped
     * `<style>` ('' when nothing is set), the gated names for
     * data-tb-hdr-set, and the pointer modifier classes.
     *
     * @return array{style: string, set: list<string>, classes: list<string>}
     */
    public static function forHeader(string $rootId, array $props): array
    {
        $devices = ['desktop' => [], 'tablet' => [], 'mobile' => []];
        $suffix = InspectorFieldRegistry::DEVICE_SUFFIXES;

        foreach (self::FIELDS as $field => [$base, $mobile]) {
            foreach ($devices as $device => $_) {
                $raw = $props[$field . $suffix[$device]] ?? null;
                if ($raw === null || $raw === '' || $raw === []) {
                    continue;
                }
                if (in_array($base, ['border-w', 'blur'], true) && (float) (is_array($raw) ? ($raw['size'] ?? 0) : $raw) <= 0) {
                    continue; // 0 = off: no border / no blur layer
                }
                $devices[$device][$device === 'mobile' && $mobile !== null ? $mobile : $base] = $raw;
            }
        }

        foreach ($devices as $device => $_) {
            $padding = $props['padding' . $suffix[$device]] ?? null;
            if (is_array($padding)) {
                $unit = in_array($padding['unit'] ?? 'px', InspectorFieldRegistry::UNITS, true) ? ($padding['unit'] ?? 'px') : 'px';
                foreach (self::PADDING_SIDES as $side => $short) {
                    if (isset($padding[$side]) && is_numeric($padding[$side])) {
                        $devices[$device]['pad-' . $short . ($device === 'mobile' ? '-m' : '')] = ['size' => 0 + $padding[$side], 'unit' => $unit];
                    }
                }
            }
            $typography = $props['menu_typography' . $suffix[$device]] ?? null;
            if (is_array($typography)) {
                foreach (self::TYPOGRAPHY as $sub => $name) {
                    if (isset($typography[$sub]) && $typography[$sub] !== '') {
                        $devices[$device][$name] = $typography[$sub];
                    }
                }
            }
        }

        // Logo size once scrolled: a percentage of its height.
        if (isset($props['sticky_logo_scale']) && is_numeric($props['sticky_logo_scale'])) {
            $pct = (float) $props['sticky_logo_scale'];
            if ($pct >= 30 && $pct <= 100) {
                $devices['desktop']['logo-scale'] = round($pct / 100, 3);
            }
        }

        // Content width is desktop only: full width, or a boxed maximum.
        if (($props['content_width'] ?? 'boxed') === 'full') {
            $devices['desktop']['max-w'] = '100%';
        } elseif (isset($props['content_max_width']) && $props['content_max_width'] !== '') {
            $devices['desktop']['max-w'] = $props['content_max_width'];
        }

        $set = [];
        $css = '';
        foreach ($devices as $device => $values) {
            $declarations = self::declarations($values, $device !== 'desktop');
            if ($declarations === '') {
                continue;
            }
            foreach (array_keys($values) as $name) {
                if (in_array($name, self::GATED, true) && self::value($name, $values[$name]) !== null) {
                    $set[$name] = true;
                }
            }
            $rule = '#' . $rootId . '{' . $declarations . '}';
            $css .= match ($device) {
                'tablet' => self::TABLET_QUERY . '{' . $rule . '}',
                'mobile' => self::MOBILE_QUERY . '{' . $rule . '}',
                default => $rule,
            };
        }

        $classes = [];
        $pointer = $props['pointer'] ?? 'underline';
        if (in_array($pointer, self::POINTERS, true) && $pointer !== 'underline') {
            $classes[] = 'tb-nav-header--pointer-' . $pointer;
        }
        $animation = $props['pointer_animation'] ?? 'grow';
        if (in_array($animation, self::POINTER_ANIMATIONS, true) && $animation !== 'grow' && in_array($pointer, ['underline', 'overline', 'double'], true)) {
            $classes[] = 'tb-nav-header--pointer-anim-' . $animation;
        }

        $drawer = $props['drawer_style'] ?? 'side';
        if (in_array($drawer, self::DRAWER_STYLES, true) && $drawer !== 'side') {
            $classes[] = 'tb-nav-header--drawer-' . $drawer;
        } elseif (($props['drawer_side'] ?? 'start') === 'end') {
            $classes[] = 'tb-nav-header--drawer-end';
        }

        if ($css !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $rootId) !== 1) {
            throw new InvalidArgumentException('Header root id must be a plain DOM id.');
        }

        return [
            'style' => $css === '' ? '' : '<style data-tb-hdr="' . $rootId . '">' . $css . '</style>',
            'set' => array_values(array_intersect(self::GATED, array_keys($set))),
            'classes' => $classes,
        ];
    }

    /**
     * `--tb-hdr-a:x;--tb-hdr-b:y` for the given name => value pairs, in
     * VARS order; unknown names, invalid values and defaults are left out.
     *
     * A tablet / phone value is kept even when it equals the default: it
     * overrides a wider device's value there.
     *
     * @param array<string, mixed> $values
     */
    public static function declarations(array $values, bool $keepDefaults = false): string
    {
        $out = [];
        foreach (self::VARS as $name => [, $default]) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $css = self::value($name, $values[$name]);
            if ($css !== null && ($keepDefaults || $css !== $default)) {
                $out[] = self::property($name) . ':' . $css;
            }
        }
        return implode(';', $out);
    }

    /**
     * The header's own scoped rule, `<style data-tb-hdr="…">#tb-…{…}</style>`,
     * or '' when it has nothing to change.
     *
     * @param array<string, mixed> $values
     */
    public static function style(string $rootId, array $values): string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $rootId) !== 1) {
            throw new InvalidArgumentException('Header root id must be a plain DOM id.');
        }
        $declarations = self::declarations($values);
        return $declarations === '' ? '' : '<style data-tb-hdr="' . $rootId . '">#' . $rootId . '{' . $declarations . '}</style>';
    }

    private static function length(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = $value . 'px';
        }
        if (is_array($value) && isset($value['size'], $value['unit'])) {
            $value = $value['size'] . $value['unit'];
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return preg_match(self::LENGTH, $value) === 1 ? $value : null;
    }

    private static function shadow(mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }
        try {
            $s = InspectorFieldRegistry::normalizeShadow($value);
        } catch (InvalidArgumentException) {
            return null;
        }
        if ($s === null) {
            return 'none';
        }
        return ($s['inset'] ? 'inset ' : '') . "{$s['x']}px {$s['y']}px {$s['blur']}px {$s['spread']}px {$s['color']}";
    }
}
