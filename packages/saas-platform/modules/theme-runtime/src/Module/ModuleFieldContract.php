<?php

namespace SaaSPlatform\ThemeRuntime\Module;

use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;

/**
 * Phase 3A — the Dynamic Field contract (Module → Data Schema → Binding).
 *
 * A module exposes SAFE field metadata for binding; a widget schema field
 * references it by dotted path. This class is the ONE vocabulary:
 *   - canonical dotted field names per module (product.*, blog_post.*, …),
 *   - normalizeProductSchema(): turns module field metadata into a safe
 *     Inspector field set via the canonical InspectorFieldRegistry,
 *   - isValidFieldPath(): allowlist gate used by bindings — no arbitrary
 *     property/model access ever passes.
 *
 * No PHP class names, no client-provided resolvers, no cross-tenant reads.
 */
final class ModuleFieldContract
{
    /** Safe, canonical dotted fields per module entity. Values are types. */
    public const FIELDS = [
        'product' => [
            'name'          => 'text',
            'description'   => 'rich_text',
            'price'         => 'number',
            'sale_price'    => 'number',
            'sku'           => 'text',
            'stock'         => 'number',
            'brand'         => 'text',
            'attributes'    => 'array',
            'gallery'       => 'media',
            'video'         => 'media',
            'badges'        => 'array',
            'rating'        => 'number',
            'review_count'  => 'number',
            'shipping_note' => 'text',
        ],
        'blog_post' => [
            'title'        => 'text',
            'excerpt'      => 'text',
            'content'      => 'rich_text',
            'author'       => 'text',
            'published_at' => 'text',
            'cover'        => 'media',
        ],
        'menu' => [
            'name'   => 'text',
            'items'  => 'array',
        ],
    ];

    /** True only for allowlisted module.field paths. */
    public static function isValidFieldPath(string $path): bool
    {
        $parts = explode('.', $path, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$module, $field] = $parts;
        return isset(self::FIELDS[$module][$field]);
    }

    /** The safe type for an allowlisted path, or null. */
    public static function fieldType(string $path): ?string
    {
        if (!self::isValidFieldPath($path)) {
            return null;
        }
        $parts = explode('.', $path, 2);
        return self::FIELDS[$parts[0]][$parts[1]];
    }

    /**
     * Build a safe Inspector field set for a module's data schema: every
     * field becomes a schema entry normalized through the canonical
     * registry (fail-closed types), labeled "bound" so the editor can
     * distinguish bound vs literal values.
     */
    public static function normalizeModuleSchema(string $module, array $fields): array
    {
        $schema = [];
        foreach ($fields as $field => $meta) {
            $path = $module . '.' . $field;
            if (!self::isValidFieldPath($path)) {
                throw new \InvalidArgumentException("Module field '{$path}' is not allowlisted.");
            }
            $type = is_array($meta) ? ($meta['type'] ?? self::fieldType($path)) : self::fieldType($path);
            $schema[$field] = InspectorFieldRegistry::normalizeDefinition([
                'type'  => $type,
                'label' => is_array($meta) ? ($meta['label'] ?? $field) : $field,
                'dynamic' => ['module' => $module, 'field' => $field],
            ]);
        }
        return $schema;
    }
}
