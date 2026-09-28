<?php

namespace SaaSPlatform\ThemeRuntime\Section;

use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Layout\DocumentEnvelope;
use SaaSPlatform\ThemeRuntime\Layout\DocumentValidator;
use SaaSPlatform\ThemeRuntime\Layout\LayoutContract;
use SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer;

/**
 * Phase 3A — the canonical Section / Template-PART package contract.
 *
 * A reusable section (or header/footer composition) is a versioned package
 * carrying a canonical node tree — never arbitrary executable HTML/JS/PHP.
 * This class is the ONE import/export authority:
 *
 *   validate()  → package shape + version gate (fail closed)
 *   import()    → validate → sanitize props → regenerate ALL node ids
 *                 → wrap in a DocumentEnvelope of the package's type
 *                 → structural validation of the envelope
 *
 * Insertion itself always goes through the canonical tree operations
 * (CanvasTreeOperations::insertNode / the editor's insertNode twin) so a
 * section insert is ONE undoable history operation and every child stays
 * independently editable — the package never bypasses composition rules.
 *
 * Unknown/unsupported widget types inside a package fail closed at insert
 * time (the registry must know the type or the insert is rejected) —
 * exactly the accepted Phase 2e invariant, now enforced for packages too.
 */
final class SectionPackage
{
    public const SCHEMA_VERSION = 1;
    /** Package types = the envelope's zone types (minus the runtime 'page' root). */
    public const TYPES = ['section', 'header', 'footer'];

    /**
     * Validate a package payload (array decoded from JSON) without
     * importing it. Returns the normalized package or throws.
     *
     * Expected shape:
     * {
     *   schema_version: 1,
     *   type:           section|header|footer,
     *   name:           string (label key),
     *   category?:      registry category slug,
     *   direction?:     rtl|ltr,          // presentation hint, not authority
     *   locale?:        fa|ar|en|…,
     *   required_modules?: string[],      // module slugs, never classes
     *   nodes:          [ {id,type,props?,style?,responsive?,children?}, … ]
     * }
     */
    public static function validate(array $package): array
    {
        $version = $package['schema_version'] ?? null;
        if ($version !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException(
                'Section package schema_version must be ' . self::SCHEMA_VERSION . '.'
            );
        }
        $type = $package['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Section package type must be one of: ' . implode(',', self::TYPES) . '.');
        }
        if (!isset($package['name']) || !is_string($package['name']) || $package['name'] === '') {
            throw new \InvalidArgumentException('Section package must declare a name.');
        }
        if (!isset($package['nodes']) || !is_array($package['nodes']) || $package['nodes'] === []) {
            throw new \InvalidArgumentException('Section package must declare a non-empty nodes tree.');
        }
        foreach (['required_modules'] as $listKey) {
            if (isset($package[$listKey])) {
                if (!is_array($package[$listKey])) {
                    throw new \InvalidArgumentException("Section package {$listKey} must be an array.");
                }
                foreach ($package[$listKey] as $module) {
                    if (!is_string($module) || !preg_match('/^[a-z0-9_\-]+$/', $module)) {
                        throw new \InvalidArgumentException("Section package {$listKey} entries must be module slugs.");
                    }
                }
            }
        }
        return $package;
    }

    /**
     * Import a package into an insertable block tree:
     * validate → sanitize → regenerate ids → envelope + structural
     * validation → registry availability gate. Returns the legacy-shaped
     * block tree (id/type/props/style/responsive/children) the editor's
     * canonical insertNode consumes.
     *
     * @param array    $package        raw package payload
     * @param array    $availableTypes registry + palette types insertable here
     * @param int|null $maxNodes       structural bound (defaults to validator's)
     *
     * @return array{blocks: array, envelope: array, package: array}
     */
    public static function import(array $package, array $availableTypes, ?int $maxNodes = null): array
    {
        $package = self::validate($package);
        $maxNodes = $maxNodes ?? 2000;

        $count = 0;
        $blocks = self::sanitizeAndRegenerate($package['nodes'], $availableTypes, $count, 0, $maxNodes);
        if ($count === 0) {
            throw new \InvalidArgumentException('Section package produced no insertable nodes.');
        }

        $envelope = DocumentEnvelope::make(
            LayoutContract::fromLegacyBlocks($blocks),
            array_filter([
                'type' => $package['type'] === 'section' ? 'template_part' : $package['type'],
                'direction' => $package['direction'] ?? null,
                'locale' => $package['locale'] ?? null,
            ], fn ($v) => is_string($v) && $v !== '')
        );
        $validation = DocumentValidator::validateEnvelope($envelope);
        if (!$validation['ok']) {
            throw new \InvalidArgumentException(
                'Section package failed canonical validation: ' . implode('; ', $validation['errors'])
            );
        }

        return [
            'blocks'   => $blocks,
            'envelope' => $envelope,
            'package'  => $package,
        ];
    }

    /**
     * Export a block tree (e.g. a saved section composition) as a package.
     * Read-only over already-stored content; strips nothing but unknown
     * runtime keys the package contract does not carry (ids are preserved —
     * import regenerates them).
     */
    public static function export(string $type, string $name, array $blocks, array $meta = []): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Section package export type must be one of: ' . implode(',', self::TYPES) . '.');
        }
        return array_merge([
            'schema_version' => self::SCHEMA_VERSION,
            'type'           => $type,
            'name'           => $name,
            'nodes'          => $blocks,
        ], $meta);
    }

    /**
     * Recursive: sanitize every prop value and regenerate every node id.
     */
    private static function sanitizeAndRegenerate(
        array $nodes,
        array $availableTypes,
        int &$count,
        int $depth,
        int $maxNodes
    ): array {
        if ($depth > 12) {
            throw new \InvalidArgumentException('Section package nesting exceeds the canonical depth bound.');
        }
        $out = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['type'] ?? null) || $node['type'] === '') {
                throw new \InvalidArgumentException('Section package node must carry a type.');
            }
            $type = $node['type'];
            if (!in_array($type, $availableTypes, true) && !in_array('*', $availableTypes, true)) {
                throw new \InvalidArgumentException("Section package references unavailable widget type '{$type}'.");
            }
            if (++$count > $maxNodes) {
                throw new \InvalidArgumentException('Section package exceeds the node count bound.');
            }

            $clean = [
                'id'   => 'blk_' . bin2hex(random_bytes(8)),
                'type' => $type,
            ];
            foreach (['style', 'responsive', 'editor'] as $safeBag) {
                if (isset($node[$safeBag]) && is_array($node[$safeBag])) {
                    $clean[$safeBag] = $node[$safeBag];
                }
            }

            // Props: sanitize strings through the shared sanitizer, reject
            // executable payloads. Unknown prop keys pass through only as
            // scalar/array data — never as callable-ish structures.
            $props = $node['props'] ?? [];
            if (!is_array($props)) {
                throw new \InvalidArgumentException("Section package node '{$type}' props must be an array.");
            }
            $clean['props'] = self::sanitizeProps($props);

            if (isset($node['children'])) {
                if (!is_array($node['children'])) {
                    throw new \InvalidArgumentException("Section package node '{$type}' children must be an array.");
                }
                $clean['children'] = self::sanitizeAndRegenerate(
                    $node['children'],
                    $availableTypes,
                    $count,
                    $depth + 1,
                    $maxNodes
                );
            }
            $out[] = $clean;
        }
        return $out;
    }

    /**
     * Deep prop sanitization: every string value goes through the shared
     * rich-HTML sanitizer (allowlisted tags/attrs only); arrays recurse;
     * scalars pass; everything else (objects, closures cannot survive JSON
     * but be explicit) is rejected.
     */
    public static function sanitizeProps(array $props): array
    {
        $clean = [];
        foreach ($props as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('Section package prop keys must be non-empty strings.');
            }
            if (is_string($value)) {
                $clean[$key] = HtmlSanitizer::sanitizeRichHtml($value);
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitizeProps($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                throw new \InvalidArgumentException("Section package prop '{$key}' carries an unsupported payload type.");
            }
        }
        return $clean;
    }
}
