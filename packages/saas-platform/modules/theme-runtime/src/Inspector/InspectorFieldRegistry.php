<?php

namespace SaaSPlatform\ThemeRuntime\Inspector;

use InvalidArgumentException;
use SaaSPlatform\ThemeRuntime\Layout\LayoutContract;
use SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer;

/**
 * Canonical, host-neutral schema-to-control contract for Canvas inspectors.
 * It describes safe editor metadata and persistence values; hosts remain
 * responsible for rendering their own UI and resolving tenant-owned data.
 */
final class InspectorFieldRegistry
{
    public const TYPES = [
        'text', 'textarea', 'rich_text', 'number', 'range', 'toggle',
        'select', 'color', 'link', 'media', 'icon', 'repeater', 'array',
        'spacing', 'dimensions', 'typography', 'alignment', 'responsive',
        'module_entity',
    ];

    private const ALIASES = [
        'string' => 'text',
        'boolean' => 'toggle',
        'image' => 'media',
        'blocks' => 'repeater',
        // Existing hosts render this through their motion-library adapter.
        // The shared contract treats the persisted selection as opaque text.
        'motion' => 'text',
    ];

    private const PANELS = ['content', 'style', 'advanced', 'responsive'];
    private const UNITS = ['px', 'rem', 'em', '%', 'vw', 'vh'];
    private const LINK_TARGETS = ['_self', '_blank'];

    /** @return array<string,mixed> */
    public static function normalizeDefinition(array $definition): array
    {
        $normalized = $definition;
        $normalized['schema'] = [];
        foreach (($definition['schema'] ?? []) as $key => $field) {
            if (!is_string($key) || !is_array($field)) {
                throw new InvalidArgumentException('Inspector schema fields must be named objects.');
            }
            $normalized['schema'][$key] = self::normalizeField($key, $field);
        }

        $editor = is_array($definition['editor'] ?? null) ? $definition['editor'] : [];
        $normalized['editor'] = [
            'allowCustomCss' => ($editor['allowCustomCss'] ?? true) === true,
            'lockedStructure' => ($editor['lockedStructure'] ?? false) === true,
            // Structural capabilities remain declarative registry metadata.
            // They are exposed for target previews in Canvas, then resolved
            // independently from the trusted registry at the save boundary.
            'canEdit' => ($editor['canEdit'] ?? true) === true,
            'allowedParentKinds' => self::normalizeKindList($editor['allowedParentKinds'] ?? null),
            'allowedChildKinds' => self::normalizeKindList($editor['allowedChildKinds'] ?? null),
            'maxChildren' => is_int($editor['maxChildren'] ?? null) && $editor['maxChildren'] > 0
                ? $editor['maxChildren'] : null,
            'uiDirection' => in_array($editor['uiDirection'] ?? null, ['rtl', 'ltr'], true)
                ? $editor['uiDirection'] : null,
        ];

        return $normalized;
    }

    private static function normalizeKindList(mixed $value): ?array
    {
        if (!is_array($value)) return null;
        $kinds = array_values(array_unique(array_filter(
            $value,
            static fn ($kind): bool => is_string($kind) && in_array($kind, LayoutContract::NODE_KINDS, true)
        )));
        return $kinds === [] ? null : $kinds;
    }

    /** @return array<string,mixed> */
    public static function normalizeField(string $key, array $field): array
    {
        $rawType = $field['control'] ?? $field['type'] ?? null;
        if (!is_string($rawType) || $rawType === '') {
            throw new InvalidArgumentException("Inspector field '{$key}' has no type.");
        }

        $type = self::ALIASES[$rawType] ?? $rawType;
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown inspector field type '{$rawType}' for '{$key}'.");
        }

        $panel = $field['panel'] ?? self::defaultPanel($type);
        if (!in_array($panel, self::PANELS, true)) {
            throw new InvalidArgumentException("Invalid inspector panel '{$panel}' for '{$key}'.");
        }

        $normalized = $field;
        $normalized['control'] = $type;
        $normalized['legacy_type'] = $rawType;
        $normalized['panel'] = $panel;
        $normalized['responsive'] = ($field['responsive'] ?? false) === true;
        $normalized['tokenizable'] = ($field['tokenizable'] ?? in_array($type, ['color', 'spacing', 'typography'], true)) === true;
        $normalized['safe'] = true;

        if ($type === 'select' && !is_array($field['options'] ?? null)) {
            throw new InvalidArgumentException("Select field '{$key}' requires options.");
        }
        if ($type === 'repeater') {
            $normalized['maxDepth'] = min(3, max(1, (int) ($field['maxDepth'] ?? 2)));
            $normalized['stableIdentity'] = true;
            $normalized['fields'] = self::normalizeNestedFields($field['fields'] ?? []);
        }
        if ($type === 'module_entity') {
            $providers = $field['providers'] ?? ($field['provider'] ?? null);
            $providers = is_string($providers) ? [$providers] : $providers;
            if (!is_array($providers) || $providers === [] || array_filter($providers, 'is_string') !== $providers) {
                throw new InvalidArgumentException("Module entity field '{$key}' requires trusted provider ids.");
            }
            $normalized['providers'] = array_values(array_unique($providers));
            unset($normalized['provider']);
        }

        return $normalized;
    }

    public static function normalizeValue(array $field, mixed $value, array $context = []): mixed
    {
        $field = self::normalizeField((string) ($context['key'] ?? 'value'), $field);

        if (is_array($value) && ($value['mode'] ?? null) === 'global') {
            if (!$field['tokenizable']) {
                throw new InvalidArgumentException('This field does not accept global tokens.');
            }
            $token = $value['token'] ?? null;
            if (!is_string($token) || preg_match('/^global\.[A-Za-z0-9._-]{1,96}$/', $token) !== 1) {
                throw new InvalidArgumentException('Invalid global token reference.');
            }
            return ['mode' => 'global', 'token' => $token];
        }

        return match ($field['control']) {
            'text', 'textarea', 'icon' => is_scalar($value) ? (string) $value : '',
            'rich_text' => HtmlSanitizer::sanitizeRichHtml(is_scalar($value) ? (string) $value : ''),
            'number', 'range' => self::normalizeNumber($value, $field),
            'toggle' => filter_var($value, FILTER_VALIDATE_BOOL),
            'select' => self::normalizeSelect($value, $field),
            'color' => self::normalizeColor($value),
            'link' => self::normalizeLink($value),
            'media' => self::normalizeMedia($value),
            'repeater', 'array' => self::normalizeList($value),
            'spacing' => self::normalizeSpacing($value),
            'dimensions' => self::normalizeDimensions($value),
            'typography' => self::normalizeTypography($value),
            'alignment' => self::normalizeAlignment($value, (string) ($context['direction'] ?? LayoutContract::DEFAULT_DIRECTION)),
            'responsive' => self::normalizeResponsive($value),
            'module_entity' => self::normalizeModuleEntity($value, $field),
        };
    }

    /** Add stable identity only to a newly-created item; legacy items remain untouched. */
    public static function newRepeaterItem(array $fields, ?string $id = null): array
    {
        $item = ['_id' => $id ?? 'item_'.bin2hex(random_bytes(8))];
        foreach (self::normalizeNestedFields($fields) as $key => $field) {
            $item[$key] = $field['default'] ?? self::emptyValue($field['control']);
        }
        return $item;
    }

    public static function compatibilityItemId(array $item, int $index): string
    {
        if (is_string($item['_id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/', $item['_id']) === 1) {
            return $item['_id'];
        }
        return 'legacy_'.substr(hash('sha256', $index.'|'.json_encode($item, JSON_UNESCAPED_UNICODE)), 0, 16);
    }

    private static function normalizeNestedFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            throw new InvalidArgumentException('Repeater fields must be an object.');
        }
        $result = [];
        foreach ($fields as $key => $field) {
            if (!is_string($key) || !is_array($field)) {
                throw new InvalidArgumentException('Repeater child fields must be named objects.');
            }
            $result[$key] = self::normalizeField($key, $field);
        }
        return $result;
    }

    private static function defaultPanel(string $type): string
    {
        return in_array($type, ['color', 'spacing', 'dimensions', 'typography', 'alignment'], true) ? 'style' : 'content';
    }

    private static function normalizeNumber(mixed $value, array $field): int|float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Numeric inspector value required.');
        }
        $number = (float) $value;
        if (isset($field['min'])) $number = max((float) $field['min'], $number);
        if (isset($field['max'])) $number = min((float) $field['max'], $number);
        return floor($number) === $number ? (int) $number : $number;
    }

    private static function normalizeSelect(mixed $value, array $field): mixed
    {
        $options = $field['options'];
        if (!array_key_exists((string) $value, $options) && !in_array($value, array_keys($options), true)) {
            throw new InvalidArgumentException('Select value is not allowlisted.');
        }
        return $value;
    }

    private static function normalizeColor(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^(#[0-9a-f]{3,8}|(?:rgb|hsl)a?\([^;{}]+\)|var\(--[A-Za-z0-9_-]+\))$/i', trim($value)) !== 1) {
            throw new InvalidArgumentException('Invalid color value.');
        }
        return trim($value);
    }

    private static function normalizeLink(mixed $value): array|string
    {
        if (is_string($value)) {
            if (!HtmlSanitizer::isValidUrl($value)) throw new InvalidArgumentException('Unsafe link URL.');
            return $value;
        }
        if (!is_array($value)) throw new InvalidArgumentException('Link must be a URL or structured link.');
        if (($value['type'] ?? null) === 'internal_page') {
            $id = $value['page_id'] ?? null;
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) throw new InvalidArgumentException('Invalid internal page reference.');
            return array_filter(['type' => 'internal_page', 'page_id' => (int) $id, 'page_title' => is_string($value['page_title'] ?? null) ? $value['page_title'] : null], fn ($v) => $v !== null);
        }
        $url = is_string($value['url'] ?? null) ? trim($value['url']) : '';
        if (!HtmlSanitizer::isValidUrl($url)) throw new InvalidArgumentException('Unsafe link URL.');
        $target = $value['target'] ?? '_self';
        if (!in_array($target, self::LINK_TARGETS, true)) throw new InvalidArgumentException('Invalid link target.');
        return [
            'url' => $url,
            'target' => $target,
            'nofollow' => ($value['nofollow'] ?? false) === true,
            'sponsored' => ($value['sponsored'] ?? false) === true,
            'ariaLabel' => is_string($value['ariaLabel'] ?? null) && trim($value['ariaLabel']) !== '' ? trim($value['ariaLabel']) : null,
        ];
    }

    private static function normalizeMedia(mixed $value): array|string
    {
        if (is_string($value)) {
            if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\.\.\/|\/etc\/|\/var\/)/i', trim($value)) === 1 || !HtmlSanitizer::isValidUrl($value)) {
                throw new InvalidArgumentException('Unsafe media reference.');
            }
            return $value;
        }
        if (!is_array($value) || !is_string($value['media_id'] ?? null) || trim($value['media_id']) === '') {
            throw new InvalidArgumentException('Media requires a media_id.');
        }
        $kind = $value['kind'] ?? 'image';
        if (!in_array($kind, ['image', 'gallery', 'video'], true)) throw new InvalidArgumentException('Invalid media kind.');
        $result = ['media_id' => trim($value['media_id']), 'kind' => $kind];
        foreach (['alt', 'title'] as $key) if (is_string($value[$key] ?? null)) $result[$key] = $value[$key];
        if (is_array($value['focalPoint'] ?? null)) {
            $result['focalPoint'] = ['x' => min(100, max(0, (float) ($value['focalPoint']['x'] ?? 50))), 'y' => min(100, max(0, (float) ($value['focalPoint']['y'] ?? 50)))];
        }
        if (isset($value['responsive'])) $result['responsive'] = self::normalizeResponsive($value['responsive']);
        return $result;
    }

    private static function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }
        if (!is_array($value) || !array_is_list($value)) throw new InvalidArgumentException('List value must be an array.');
        return $value;
    }

    private static function normalizeSpacing(mixed $value): array
    {
        if (!is_array($value)) throw new InvalidArgumentException('Spacing must be structured.');
        // Physical side names are never accepted as canonical input: logical
        // inline-*/block-* is the storage vocabulary, and physical→logical
        // conversion needs direction context (an explicit compat concern).
        foreach (['left', 'right', 'top', 'bottom'] as $physical) {
            if (array_key_exists($physical, $value)) {
                throw new InvalidArgumentException("Physical spacing key '{$physical}' is not canonical — use inline-start/inline-end/block-start/block-end.");
            }
        }
        $unit = $value['unit'] ?? 'px';
        if (!in_array($unit, self::UNITS, true)) throw new InvalidArgumentException('Invalid spacing unit.');
        $result = ['unit' => $unit, 'linked' => ($value['linked'] ?? false) === true];
        foreach (['inline-start', 'inline-end', 'block-start', 'block-end'] as $side) {
            $v = $value[$side] ?? 0;
            if (!is_numeric($v)) throw new InvalidArgumentException("Invalid spacing side '{$side}'.");
            $result[$side] = (float) $v;
        }
        return $result;
    }

    private static function normalizeDimensions(mixed $value): array
    {
        if (!is_array($value)) throw new InvalidArgumentException('Dimensions must be structured.');
        $unit = $value['unit'] ?? 'px';
        if (!in_array($unit, self::UNITS, true)) throw new InvalidArgumentException('Invalid dimensions unit.');
        return ['width' => is_numeric($value['width'] ?? null) ? (float) $value['width'] : null, 'height' => is_numeric($value['height'] ?? null) ? (float) $value['height'] : null, 'unit' => $unit];
    }

    private static function normalizeTypography(mixed $value): array
    {
        if (!is_array($value)) throw new InvalidArgumentException('Typography must be structured.');
        $result = [];
        foreach (['fontFamily', 'fontToken', 'style', 'transform', 'decoration'] as $key) if (is_string($value[$key] ?? null)) $result[$key] = $value[$key];
        foreach (['size', 'weight', 'lineHeight', 'letterSpacing'] as $key) if (is_numeric($value[$key] ?? null)) $result[$key] = (float) $value[$key];
        if (isset($value['responsive'])) $result['responsive'] = self::normalizeResponsive($value['responsive']);
        return $result;
    }

    private static function normalizeAlignment(mixed $value, string $direction): string
    {
        if (!is_string($value)) throw new InvalidArgumentException('Alignment must be a string.');
        $logical = LayoutContract::normalizeAlignment($value, $direction);
        if (!in_array($logical, LayoutContract::ALIGNMENTS, true)) throw new InvalidArgumentException('Invalid alignment.');
        return $logical;
    }

    private static function normalizeResponsive(mixed $value): array
    {
        if (!is_array($value) || !LayoutContract::isResponsiveShape($value)) throw new InvalidArgumentException('Invalid responsive override.');
        return $value;
    }

    private static function normalizeModuleEntity(mixed $value, array $field): array
    {
        if (!is_array($value) || !is_string($value['provider'] ?? null) || !in_array($value['provider'], $field['providers'], true)) {
            throw new InvalidArgumentException('Untrusted module entity provider.');
        }
        $id = $value['id'] ?? null;
        if (!is_string($id) && !is_int($id)) throw new InvalidArgumentException('Invalid module entity id.');
        return ['provider' => $value['provider'], 'id' => $id];
    }

    private static function emptyValue(string $type): mixed
    {
        return match ($type) {
            'toggle' => false,
            'number', 'range' => 0,
            'repeater', 'array', 'responsive' => [],
            default => '',
        };
    }
}
