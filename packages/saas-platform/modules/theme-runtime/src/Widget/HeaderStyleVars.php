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
 * InspectorFieldRegistry shadow), weight (100–900) and time (s / ms).
 * Suffix -m = phones (< 768px).
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
    ];

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
            'length' => self::length($value),
            'color' => is_string($value) && HtmlSanitizer::isCssColor($value) ? trim($value) : null,
            'shadow' => self::shadow($value),
            'weight' => is_numeric($value) && (int) $value >= 100 && (int) $value <= 900 && (int) $value % 100 === 0 ? (string) (int) $value : null,
            'time' => is_string($value) && preg_match('/^(?:\d+|\d*\.\d+)m?s$/', trim($value)) === 1 ? trim($value) : null,
            default => null,
        };
    }

    /**
     * `--tb-hdr-a:x;--tb-hdr-b:y` for the given name => value pairs, in
     * VARS order; unknown names, invalid values and defaults are left out.
     *
     * @param array<string, mixed> $values
     */
    public static function declarations(array $values): string
    {
        $out = [];
        foreach (self::VARS as $name => [, $default]) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $css = self::value($name, $values[$name]);
            if ($css !== null && $css !== $default) {
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
