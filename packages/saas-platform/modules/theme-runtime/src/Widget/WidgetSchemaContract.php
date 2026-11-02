<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;

/**
 * ADR-076: the schema contract every Canvas widget definition must meet.
 *
 * Both editors build the Inspector from a widget's `schema` alone, so a
 * widget gains a property (alignment, a logo slot, a network list) by
 * declaring a field, never by editing an editor. That only holds while every
 * definition stays inside the vocabulary the editors understand. This class
 * is that vocabulary, checked in bulk over the registry by
 * WidgetSchemaContractTest, so a definition that drifts out of it fails CI
 * instead of rendering a blank control.
 *
 * It also publishes manifest(): per widget its id, category, declared slots
 * and field → control map. Browser checks and the Sentinel verifier read
 * this instead of hardcoding widget lists.
 */
final class WidgetSchemaContract
{
    /** The registry's own category slugs (BlockRegistry::CATEGORY_*). */
    public const CATEGORIES = [
        BlockRegistry::CATEGORY_LAYOUT, BlockRegistry::CATEGORY_CONTENT, BlockRegistry::CATEGORY_DYNAMIC,
        BlockRegistry::CATEGORY_ECOMMERCE, BlockRegistry::CATEGORY_MARKETING, BlockRegistry::CATEGORY_FORMS,
        BlockRegistry::CATEGORY_UTILITY, BlockRegistry::CATEGORY_MODULE,
    ];

    /**
     * Reference-theme packs registered as host types (FoodMartBlockTypes,
     * SleepShimanBlockTypes). Their blocks carry the pack as category and
     * never appear in the palette, so they need no palette icon; every field
     * rule still applies to them.
     */
    public const THEME_PACK_CATEGORIES = ['foodmart', 'sleepshiman'];

    /** Editor presentation hints, per control type, that both editors render. */
    public const UI_HINTS = [
        'select' => ['column_picker', 'segmented'],
    ];

    private const TYPE_PATTERN = '/^[a-z0-9_:\-]+$/';
    private const SLOT_PATTERN = '/^[a-z][a-z0-9\-]*$/';

    /**
     * Every contract violation across `$definitions` (type => definition),
     * as readable "type.field: problem" strings. Empty means compliant.
     *
     * @return list<string>
     */
    public static function violations(array $definitions): array
    {
        $out = [];
        foreach ($definitions as $type => $definition) {
            $type = (string) $type;
            if (!is_array($definition)) {
                $out[] = "{$type}: definition must be an array";
                continue;
            }
            foreach (self::definitionViolations($type, $definition) as $problem) {
                $out[] = $problem;
            }
        }
        return $out;
    }

    /** @return list<string> */
    public static function definitionViolations(string $type, array $definition): array
    {
        $out = [];
        if (!preg_match(self::TYPE_PATTERN, $type)) {
            $out[] = "{$type}: id is not a stable slug";
        }
        if (!is_string($definition['name'] ?? null) || trim($definition['name']) === '') {
            $out[] = "{$type}: missing name";
        }
        $themePack = in_array($definition['category'] ?? null, self::THEME_PACK_CATEGORIES, true);
        if (!$themePack && (!is_string($definition['icon'] ?? null) || $definition['icon'] === '')) {
            $out[] = "{$type}: missing icon";
        }
        if (!$themePack && !in_array($definition['category'] ?? null, self::CATEGORIES, true)) {
            $out[] = "{$type}: unknown category '" . (is_scalar($definition['category'] ?? null) ? $definition['category'] : 'null') . "'";
        }

        $schema = $definition['schema'] ?? null;
        if (!is_array($schema)) {
            return [...$out, "{$type}: schema must be an array"];
        }
        try {
            InspectorFieldRegistry::normalizeDefinition($definition);
        } catch (\InvalidArgumentException $e) {
            $out[] = "{$type}: " . $e->getMessage();
        }
        foreach ($schema as $key => $field) {
            if (!is_array($field)) {
                $out[] = "{$type}.{$key}: field must be an array";
                continue;
            }
            foreach (self::fieldViolations("{$type}.{$key}", $field, true) as $problem) {
                $out[] = $problem;
            }
        }

        if (array_key_exists('slots', $definition)) {
            $slots = $definition['slots'];
            if (!is_array($slots) || !array_is_list($slots) || $slots === []) {
                $out[] = "{$type}: slots must be a non-empty list";
            } else {
                foreach ($slots as $slot) {
                    if (!is_string($slot) || !preg_match(self::SLOT_PATTERN, $slot)) {
                        $out[] = "{$type}: slot '" . (is_scalar($slot) ? $slot : '?') . "' is not a slug";
                    }
                }
            }
        }

        $defaults = $definition['defaultProps'] ?? [];
        if (!is_array($defaults)) {
            $out[] = "{$type}: defaultProps must be an array";
        } else {
            foreach (array_keys($defaults) as $key) {
                if (!array_key_exists($key, $schema)) {
                    $out[] = "{$type}.{$key}: default prop has no schema field";
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function fieldViolations(string $path, array $field, bool $topLevel): array
    {
        $out = [];
        $type = $field['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return ["{$path}: missing type"];
        }
        // A `blocks` field is a container's child list, edited in the
        // children panel rather than through a labelled control.
        if ($type !== 'blocks' && (!is_string($field['label'] ?? null) || trim($field['label']) === '')) {
            $out[] = "{$path}: missing label";
        }
        if (array_key_exists('item_schema', $field)) {
            $out[] = "{$path}: item_schema is not read by any editor; use fields (ADR-060)";
        }
        if (isset($field['ui']) && !in_array($field['ui'], self::UI_HINTS[$type] ?? [], true)) {
            $out[] = "{$path}: ui '" . (is_scalar($field['ui']) ? $field['ui'] : '?') . "' is not rendered for {$type} controls";
        }
        if (array_key_exists('group', $field)) {
            if (!$topLevel) {
                $out[] = "{$path}: group applies to top-level fields only";
            } elseif (!is_string($field['group']) || trim($field['group']) === '') {
                $out[] = "{$path}: group must be a non-empty label";
            }
        }
        if ($type === 'select') {
            $options = $field['options'] ?? null;
            if (!is_array($options) || $options === []) {
                $out[] = "{$path}: select needs options";
            } elseif (array_key_exists('default', $field) && !array_key_exists((string) $field['default'], $options)) {
                $out[] = "{$path}: default '" . (is_scalar($field['default']) ? $field['default'] : '?') . "' is not an option";
            }
        }
        if ($type === 'number' && is_numeric($field['default'] ?? null)) {
            $default = (float) $field['default'];
            if ((isset($field['min']) && $default < (float) $field['min']) || (isset($field['max']) && $default > (float) $field['max'])) {
                $out[] = "{$path}: default is outside min/max";
            }
        }
        if ($type === 'repeater') {
            if (!is_array($field['fields'] ?? null) || $field['fields'] === []) {
                $out[] = "{$path}: repeater needs fields";
            } else {
                foreach ($field['fields'] as $key => $child) {
                    $out = [...$out, ...(is_array($child) ? self::fieldViolations("{$path}[].{$key}", $child, false) : ["{$path}[].{$key}: field must be an array"])];
                }
            }
        }
        return $out;
    }

    /**
     * Per widget: id, category, declared slots and field → control type.
     *
     * @return array<string, array{category:string, slots:list<string>, fields:array<string,string>}>
     */
    public static function manifest(array $definitions): array
    {
        $out = [];
        foreach ($definitions as $type => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $fields = [];
            foreach (($definition['schema'] ?? []) as $key => $field) {
                $fields[(string) $key] = is_array($field) ? (string) ($field['type'] ?? '') : '';
            }
            $out[(string) $type] = [
                'category' => (string) ($definition['category'] ?? ''),
                'slots' => is_array($definition['slots'] ?? null) ? array_values($definition['slots']) : [],
                'fields' => $fields,
            ];
        }
        return $out;
    }
}
