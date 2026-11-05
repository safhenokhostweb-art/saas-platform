<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * ADR-079: turns a library motion SVG plus customer-chosen runtime
 * parameters into the SVG a browser receives.
 *
 * A runtime parameter is NOT the master editable schema (that stays
 * encrypted and never leaves the platform). It is a small, public,
 * customer-facing contract declared per asset version in metadata.json:
 *
 *   text     → replaces the text of every element with data-p="key"
 *              (DOM text node, so markup can never be injected); Latin
 *              digits become Persian digits
 *   color    → overrides one `--asset-*` theme token (light and dark)
 *   number   → optional data-p text (format plain|percent) and, with
 *              `css: true`, CSS variables --p-key / --p-key-rest
 *   boolean  → role `animate` (false = static final frame) or `loop`
 *              (false = every animation runs once)
 *   select   → role `speed` (slow|normal|fast scales every duration
 *              through the SVG's `var(--k)`)
 *
 * Pure and deterministic: the same inputs always produce the same bytes,
 * which is what lets the served URL be cached as immutable.
 */
final class MotionSvgComposer
{
    public const TYPES = ['text', 'color', 'number', 'boolean', 'select'];
    public const GROUPS = ['content', 'style', 'motion'];
    public const SPEED_FACTORS = ['slow' => '1.5', 'normal' => '1', 'fast' => '0.7'];
    public const MAX_PARAMETERS = 40;
    private const SVG_NS = 'http://www.w3.org/2000/svg';
    private const KEY = '/^[a-z][a-z0-9_]{0,39}$/';

    /**
     * Keep only well-formed definitions, in declared order, with only the
     * keys this contract knows. Anything malformed is dropped, never
     * guessed.
     *
     * @param array<int, mixed> $definitions
     * @return list<array<string, mixed>>
     */
    public static function normalizeDefinitions(array $definitions): array
    {
        $out = [];
        $seen = [];
        foreach ($definitions as $def) {
            if (! is_array($def) || count($out) >= self::MAX_PARAMETERS) {
                continue;
            }
            $key = $def['key'] ?? null;
            $type = $def['type'] ?? null;
            $label = $def['label'] ?? null;
            if (! is_string($key) || ! preg_match(self::KEY, $key) || isset($seen[$key])
                || ! in_array($type, self::TYPES, true) || ! is_string($label) || trim($label) === '') {
                continue;
            }
            $clean = [
                'key' => $key,
                'type' => $type,
                'label' => mb_substr(trim($label), 0, 60),
                'group' => in_array($def['group'] ?? null, self::GROUPS, true) ? $def['group'] : 'content',
            ];

            $valid = match ($type) {
                'text' => self::normalizeText($def, $clean),
                'color' => self::normalizeColor($def, $clean),
                'number' => self::normalizeNumber($def, $clean),
                'boolean' => self::normalizeBoolean($def, $clean),
                'select' => self::normalizeSelect($def, $clean),
            };
            if ($valid !== null) {
                $out[] = $valid;
                $seen[$key] = true;
            }
        }

        return $out;
    }

    /**
     * Validate raw input (e.g. query parameters) against definitions.
     * Unknown keys, invalid values and values equal to the default are all
     * dropped, so equal customisations always yield the same canonical set.
     *
     * @param list<array<string, mixed>> $definitions normalized definitions
     * @param array<string, mixed> $input
     * @return array<string, string|int|float|bool> sorted by key
     */
    public static function resolveValues(array $definitions, array $input): array
    {
        $values = [];
        foreach ($definitions as $def) {
            if (! array_key_exists($def['key'], $input)) {
                continue;
            }
            $value = self::coerce($def, $input[$def['key']]);
            if ($value !== null && $value !== $def['default']) {
                $values[$def['key']] = $value;
            }
        }
        ksort($values);

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $definitions normalized definitions
     * @param array<string, mixed> $values output of resolveValues()
     * @param string|null $fontWoff2 raw woff2 bytes to embed as Vazirmatn
     */
    public function compose(string $svg, array $definitions, array $values, ?string $fontWoff2 = null): string
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded || ! $doc->documentElement instanceof DOMElement || $doc->documentElement->localName !== 'svg') {
            throw new InvalidArgumentException('Not a well-formed SVG document.');
        }
        $root = $doc->documentElement;
        $byKey = [];
        foreach ($definitions as $def) {
            $byKey[$def['key']] = $def;
        }

        $this->applyText($doc, $byKey, $values);

        $css = $this->runtimeCss($byKey, $values);
        if ($css !== '') {
            $this->insertStyle($doc, $root, 'asset-runtime', $css, after: true);
        }
        if ($fontWoff2 !== null && $fontWoff2 !== '') {
            $this->insertStyle($doc, $root, 'asset-font', sprintf(
                "@font-face{font-family:'Vazirmatn';src:url(data:font/woff2;base64,%s) format('woff2');font-weight:400 800;font-style:normal;font-display:block}",
                base64_encode($fontWoff2)
            ), after: false);
        }

        return (string) $doc->saveXML($root);
    }

    public static function persianDigits(string $text): string
    {
        return strtr($text, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    // ── definitions ──────────────────────────────────────────────────────

    private static function normalizeText(array $def, array $clean): ?array
    {
        $max = max(1, min(80, (int) ($def['max'] ?? 40)));
        $default = is_string($def['default'] ?? null) ? mb_substr($def['default'], 0, $max) : '';

        return $default === '' ? null : [...$clean, 'default' => $default, 'max' => $max];
    }

    private static function normalizeColor(array $def, array $clean): ?array
    {
        $token = $def['token'] ?? null;
        $default = is_string($def['default'] ?? null) ? strtolower($def['default']) : '';
        if (! is_string($token) || ! preg_match('/^--asset-[a-z]{2,20}$/', $token) || ! preg_match('/^#[0-9a-f]{6}$/', $default)) {
            return null;
        }

        return [...$clean, 'default' => $default, 'token' => $token];
    }

    private static function normalizeNumber(array $def, array $clean): ?array
    {
        foreach (['min', 'max', 'default'] as $k) {
            if (! is_int($def[$k] ?? null) && ! is_float($def[$k] ?? null)) {
                return null;
            }
        }
        if ($def['min'] >= $def['max'] || $def['default'] < $def['min'] || $def['default'] > $def['max']) {
            return null;
        }
        $step = is_int($def['step'] ?? null) || is_float($def['step'] ?? null) ? max(0.01, (float) $def['step']) : 1;

        return [
            ...$clean,
            'default' => $def['default'],
            'min' => $def['min'],
            'max' => $def['max'],
            'step' => $step,
            'format' => in_array($def['format'] ?? null, ['plain', 'percent'], true) ? $def['format'] : 'plain',
            'css' => ($def['css'] ?? false) === true,
        ];
    }

    private static function normalizeBoolean(array $def, array $clean): ?array
    {
        if (! is_bool($def['default'] ?? null)) {
            return null;
        }

        return [...$clean, 'default' => $def['default'], 'role' => in_array($def['role'] ?? null, ['animate', 'loop'], true) ? $def['role'] : null];
    }

    private static function normalizeSelect(array $def, array $clean): ?array
    {
        $options = [];
        foreach ((array) ($def['options'] ?? []) as $value => $label) {
            if (is_string($value) && preg_match('/^[a-z0-9_]{1,20}$/', $value) && is_string($label) && trim($label) !== '') {
                $options[$value] = mb_substr(trim($label), 0, 40);
            }
        }
        $role = ($def['role'] ?? null) === 'speed' ? 'speed' : null;
        if ($role === 'speed') {
            $options = array_intersect_key($options, self::SPEED_FACTORS);
        }
        $default = $def['default'] ?? null;
        if ($options === [] || ! is_string($default) || ! array_key_exists($default, $options)) {
            return null;
        }

        return [...$clean, 'default' => $default, 'options' => $options, 'role' => $role];
    }

    // ── values ───────────────────────────────────────────────────────────

    private static function coerce(array $def, mixed $raw): string|int|float|bool|null
    {
        return match ($def['type']) {
            'text' => self::coerceText($raw, (int) $def['max']),
            'color' => is_string($raw) && preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? strtolower($raw) : null,
            'number' => self::coerceNumber($def, $raw),
            'boolean' => match (true) {
                $raw === true, $raw === 1, $raw === '1', $raw === 'true' => true,
                $raw === false, $raw === 0, $raw === '0', $raw === 'false' => false,
                default => null,
            },
            'select' => is_string($raw) && array_key_exists($raw, $def['options']) ? $raw : null,
        };
    }

    private static function coerceText(mixed $raw, int $max): ?string
    {
        if (! is_string($raw) || ! mb_check_encoding($raw, 'UTF-8')) {
            return null;
        }
        // Drop control/format characters except ZWNJ/ZWJ (Persian needs them).
        $text = preg_replace('/(?![\x{200C}\x{200D}])[\p{Cc}\p{Cf}\p{Co}\p{Cs}]/u', '', $raw) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : self::persianDigits(mb_substr($text, 0, $max));
    }

    private static function coerceNumber(array $def, mixed $raw): int|float|null
    {
        if (! is_int($raw) && ! is_float($raw) && ! (is_string($raw) && is_numeric($raw))) {
            return null;
        }
        $n = (float) $raw;
        if (! is_finite($n) || $n < $def['min'] || $n > $def['max']) {
            return null;
        }
        $n = round($n / $def['step']) * $def['step'];

        return floor($n) === $n ? (int) $n : round($n, 2);
    }

    // ── composition ──────────────────────────────────────────────────────

    /**
     * @param array<string, array<string, mixed>> $byKey
     * @param array<string, mixed> $values
     */
    private function applyText(DOMDocument $doc, array $byKey, array $values): void
    {
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//*[@data-p]') ?: [] as $node) {
            $key = $node instanceof DOMElement ? $node->getAttribute('data-p') : '';
            $def = $byKey[$key] ?? null;
            if ($def === null || ! array_key_exists($key, $values)) {
                continue;
            }
            $text = match ($def['type']) {
                'text' => (string) $values[$key],
                'number' => self::persianDigits(self::formatNumber($values[$key])).($def['format'] === 'percent' ? '٪' : ''),
                default => null,
            };
            if ($text !== null) {
                $node->textContent = $text;
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $byKey
     * @param array<string, mixed> $values
     */
    private function runtimeCss(array $byKey, array $values): string
    {
        $vars = [];
        $rules = [];
        foreach ($byKey as $key => $def) {
            $has = array_key_exists($key, $values);
            $value = $has ? $values[$key] : $def['default'];
            if ($def['type'] === 'color' && $has) {
                $vars[] = $def['token'].':'.$value;
            } elseif ($def['type'] === 'number' && $def['css'] && $has) {
                $vars[] = '--p-'.$key.':'.self::formatNumber($value);
                $vars[] = '--p-'.$key.'-rest:'.self::formatNumber($def['max'] - $value);
            } elseif ($def['type'] === 'select' && $def['role'] === 'speed' && $has) {
                $vars[] = '--k:'.self::SPEED_FACTORS[$value];
            } elseif ($def['type'] === 'boolean' && $value === false && $def['role'] === 'animate') {
                $rules['animate'] = '*{animation:none!important}';
            } elseif ($def['type'] === 'boolean' && $value === false && $def['role'] === 'loop') {
                $rules['loop'] = '*{animation-iteration-count:1!important}';
            }
        }

        // Colour overrides must beat the dark-scheme block too, so they are
        // repeated inside the same media query.
        $css = $vars === [] ? '' : ':root{'.implode(';', $vars).'}';
        $colorVars = array_values(array_filter($vars, fn (string $v): bool => str_starts_with($v, '--asset-')));
        if ($colorVars !== []) {
            $css .= '@media (prefers-color-scheme:dark){:root{'.implode(';', $colorVars).'}}';
        }

        return $css.implode('', $rules);
    }

    private function insertStyle(DOMDocument $doc, DOMElement $root, string $id, string $css, bool $after): void
    {
        $style = $doc->createElementNS(self::SVG_NS, 'style');
        $style->setAttribute('id', $id);
        $style->appendChild($doc->createTextNode($css));

        $styles = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'style') {
                $styles[] = $child;
            }
        }
        if ($after && $styles !== []) {
            $last = end($styles);
            $last->parentNode->insertBefore($style, $last->nextSibling);
        } elseif (! $after && $styles !== []) {
            $root->insertBefore($style, $styles[0]);
        } else {
            $root->insertBefore($style, $root->firstChild);
        }
    }

    private static function formatNumber(int|float $n): string
    {
        return is_int($n) ? (string) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
