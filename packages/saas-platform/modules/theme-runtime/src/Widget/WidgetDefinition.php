<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

/**
 * Phase 3A — the canonical WidgetDefinition contract.
 *
 * ONE authority for what a widget/section-package definition must look like
 * before it may appear in the Builder. It does NOT replace BlockRegistry
 * (the registry remains the store of definitions); it is the normalizer/
 * validator every definition passes through at registration/adoption time,
 * so unknown categories, missing identity, or unsafe metadata fail closed
 * instead of silently reaching the editor.
 *
 * Non-negotiables enforced here:
 *   - categories are the registry's own canonical slugs (never free text);
 *   - labels are localization KEYS — Persian/English display text lives in
 *     the label maps, never as the canonical identifier;
 *   - `requires` (module ownership) is a plain module slug — never a class
 *     name, never a callback;
 *   - unknown top-level keys are preserved verbatim (lossless round trip,
 *     same discipline as DocumentEnvelope) but never interpreted.
 */
final class WidgetDefinition
{
    /** Categories a definition may declare (BlockRegistry canonical slugs). */
    public const CATEGORIES = [
        'basic', 'layout', 'content', 'media', 'commerce', 'blog', 'forms',
        'navigation', 'social', 'marketing', 'dynamic', 'module', 'utility',
        'solar-industry',
    ];

    /** Safe capability flags a definition may declare. */
    public const CAPABILITIES = [
        'is_container', 'lockedStructure', 'allowCustomCss', 'tokenizable',
        'resizable', 'reorderable', 'duplicatable',
    ];

    /**
     * Normalize and validate a raw definition array.
     *
     * @return array the normalized definition (safe keys only, unknown keys preserved)
     *
     * @throws \InvalidArgumentException on any fail-closed violation
     */
    public static function normalize(string $type, array $definition): array
    {
        if ($type === '' || !preg_match('/^[a-z0-9_:\-]+$/', $type)) {
            throw new \InvalidArgumentException("Widget type '{$type}' is not a safe identifier.");
        }
        if (!isset($definition['name']) || !is_string($definition['name']) || $definition['name'] === '') {
            throw new \InvalidArgumentException("Widget '{$type}' must declare a name label.");
        }
        $category = $definition['category'] ?? null;
        if (!is_string($category) || !in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException(
                "Widget '{$type}' declares unknown category '" . ($category ?? 'null') . "'."
            );
        }

        $normalized = $definition;

        // Schema must be an array of field definitions (shape validated by
        // InspectorFieldRegistry at use time — this is the structural check).
        if (isset($normalized['schema']) && !is_array($normalized['schema'])) {
            throw new \InvalidArgumentException("Widget '{$type}' schema must be an array.");
        }
        $normalized['schema'] = $normalized['schema'] ?? [];

        // Capabilities: whitelist keys, coerce known booleans.
        $capabilities = $normalized['capabilities'] ?? [];
        if (!is_array($capabilities)) {
            throw new \InvalidArgumentException("Widget '{$type}' capabilities must be an array.");
        }
        foreach ($capabilities as $key => $value) {
            if (!in_array($key, self::CAPABILITIES, true)) {
                throw new \InvalidArgumentException("Widget '{$type}' declares unknown capability '{$key}'.");
            }
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("Widget '{$type}' capability '{$key}' must be boolean.");
            }
        }

        // Module ownership: a safe slug, never a class name or callback.
        if (isset($normalized['requires'])) {
            if (!is_string($normalized['requires']) || !preg_match('/^[a-z0-9_\-]+$/', $normalized['requires'])) {
                throw new \InvalidArgumentException(
                    "Widget '{$type}' requires must be a plain module slug (got unsafe value)."
                );
            }
        }

        // Allowed parents/children: lists of type identifiers or ['*'].
        foreach (['allowedParents', 'allowedChildren'] as $listKey) {
            if (isset($normalized[$listKey])) {
                if (!is_array($normalized[$listKey]) || $normalized[$listKey] === []) {
                    throw new \InvalidArgumentException("Widget '{$type}' {$listKey} must be a non-empty array when declared.");
                }
                foreach ($normalized[$listKey] as $t) {
                    if (!is_string($t) || !preg_match('/^[a-z0-9_:\-*]+$/', $t)) {
                        throw new \InvalidArgumentException("Widget '{$type}' {$listKey} contains an unsafe type entry.");
                    }
                }
            }
        }

        // Default props must be an array; default style too.
        if (isset($normalized['defaultProps']) && !is_array($normalized['defaultProps'])) {
            throw new \InvalidArgumentException("Widget '{$type}' defaultProps must be an array.");
        }
        if (isset($normalized['defaultStyle']) && !is_array($normalized['defaultStyle'])) {
            throw new \InvalidArgumentException("Widget '{$type}' defaultStyle must be an array.");
        }

        return $normalized;
    }

    /**
     * Whether `$candidate` may be nested inside a definition declaring
     * `$list` (allowedParents/allowedChildren semantics). `['*']` allows all.
     */
    public static function allowsType(array $list, string $candidate): bool
    {
        if (in_array('*', $list, true)) {
            return true;
        }
        foreach ($list as $entry) {
            if ($entry === $candidate) {
                return true;
            }
            // Prefix wildcard: 'foodmart_*' style family matching stays a
            // HOST capability concern — here only exact or '*' match.
        }
        return false;
    }
}
