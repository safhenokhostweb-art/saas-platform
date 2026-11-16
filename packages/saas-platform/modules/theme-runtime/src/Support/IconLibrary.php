<?php

namespace SaaSPlatform\ThemeRuntime\Support;

/**
 * Phase 0.6 (ADR-065) — the theme runtime's SVG icon set.
 *
 * A curated subset of Lucide (ISC, resources/icons/LICENSE-lucide.txt)
 * vendored into resources/icons/catalog.json: name, category, Persian label,
 * search keywords and the SVG body. The catalog is the single source for
 * the renderer (inline SVG, no request, no font) and for both Canvas
 * editors' icon picker (canvas-icons.js). Only the icons in the catalog
 * ship, so the set is tree-shaken by construction.
 *
 * An icon prop that is not a catalog name — a legacy emoji or a short
 * text — renders as escaped text, exactly as before.
 */
final class IconLibrary
{
    public const CSS_CLASS = 'tb-icon-svg';

    /** @var array{source:string,license:string,categories:list<array{key:string,label:string}>,icons:array<string,array{category:string,label:string,keywords:list<string>,body:string}>}|null */
    private static ?array $catalog = null;

    public static function catalogPath(): string
    {
        return __DIR__ . '/../../resources/icons/catalog.json';
    }

    /** @return array{source:string,license:string,categories:list<array{key:string,label:string}>,icons:array<string,array{category:string,label:string,keywords:list<string>,body:string}>} */
    public static function catalog(): array
    {
        if (self::$catalog === null) {
            $decoded = json_decode((string) file_get_contents(self::catalogPath()), true);
            self::$catalog = is_array($decoded) && is_array($decoded['icons'] ?? null)
                ? $decoded
                : ['source' => '', 'license' => '', 'categories' => [], 'icons' => []];
        }

        return self::$catalog;
    }

    /** True when $value names a catalog icon (case and surrounding space ignored). */
    public static function has(mixed $value): bool
    {
        return self::name($value) !== null;
    }

    /** The catalog name $value refers to, or null for empty/legacy/unknown values. */
    public static function name(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $name = strtolower(trim($value));
        if ($name === 'fas fa-sun' || $name === 'fa-sun') {
            return 'sun-solar';
        }

        return $name !== '' && isset(self::catalog()['icons'][$name]) ? $name : null;
    }

    public static function label(string $name): string
    {
        return self::catalog()['icons'][self::name($name) ?? '']['label'] ?? '';
    }

    /**
     * Inline SVG markup for a catalog icon, or '' when $name is not one.
     * Decorative by default (aria-hidden); pass $label for a meaningful,
     * stand-alone icon (role="img" + aria-label).
     */
    public static function svg(string $name, ?string $label = null, string $extraClass = ''): string
    {
        $key = self::name($name);
        if ($key === null) {
            return '';
        }
        $class = trim(self::CSS_CLASS . ' ' . self::CSS_CLASS . '-' . $key . ' ' . $extraClass);
        $a11y = $label !== null && $label !== ''
            ? 'role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            : 'aria-hidden="true"';

        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" ' . $a11y . '>'
            . self::catalog()['icons'][$key]['body']
            . '</svg>';
    }

    /**
     * An icon prop as HTML: a catalog name → SVG; anything else (a legacy
     * emoji or text) → the value escaped exactly as the renderers always
     * printed it, so stored content keeps rendering unchanged.
     */
    public static function html(mixed $value): string
    {
        if (self::has($value)) {
            return self::svg((string) $value);
        }

        return is_scalar($value) ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') : '';
    }
}
