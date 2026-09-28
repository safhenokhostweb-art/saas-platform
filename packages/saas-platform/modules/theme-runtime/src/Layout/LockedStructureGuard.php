<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/**
 * Server-side comparison guard for trusted lockedStructure metadata.
 *
 * A locked node's PROPS remain editable (structure fidelity, not content
 * freeze), but its structural representation — its own type, its children's
 * ids/types/nesting, recursively — must not change between the stored and
 * incoming trees. Metadata comes ONLY from the trusted callable the host
 * supplies (e.g. BlockRegistry definitions); document-declared
 * lockedStructure is never honored here.
 */
final class LockedStructureGuard
{
    /** @param callable(string):array $metadataForType */
    public static function validate(array $before, array $after, callable $metadataForType): array
    {
        $old = self::index($before);
        $new = self::index($after);
        $errors = [];
        foreach ($old as $id => $entry) {
            $metadata = $metadataForType((string) ($entry['node']['type'] ?? ''));
            if (($metadata['lockedStructure'] ?? false) !== true) continue;
            if (!isset($new[$id])) { $errors[] = "locked node '{$id}' cannot be deleted"; continue; }
            if ($entry['path'] !== $new[$id]['path']) $errors[] = "locked node '{$id}' cannot be moved";
            if (($entry['node']['type'] ?? null) !== ($new[$id]['node']['type'] ?? null)) $errors[] = "locked node '{$id}' type cannot change";
            if (self::structure($entry['node']) !== self::structure($new[$id]['node'])) $errors[] = "locked node '{$id}' children cannot change";
        }
        return ['ok' => $errors === [], 'errors' => $errors];
    }

    private static function index(array $nodes, array $path = []): array
    {
        $result = [];
        foreach (array_values($nodes) as $i => $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null)) continue;
            $nodePath = [...$path, $i];
            $result[$node['id']] = ['path' => $nodePath, 'node' => $node];
            $children = $node['children'] ?? $node['blocks'] ?? [];
            if (is_array($children)) $result += self::index($children, [...$nodePath, 'children']);
        }
        return $result;
    }

    /**
     * Structural signature of a node: its id/type plus the recursive
     * signature of every child — deliberately EXCLUDES props/style/responsive
     * and every other content key, so prop edits never trip the guard while
     * any structural mutation (id swap, type change, nesting change, reorder)
     * of a locked subtree does.
     */
    private static function structure(array $node): array
    {
        $children = $node['children'] ?? $node['blocks'] ?? [];

        return [
            'id' => $node['id'] ?? null,
            'type' => $node['type'] ?? null,
            'children' => is_array($children)
                ? array_values(array_map(
                    static fn ($child) => is_array($child) ? self::structure($child) : null,
                    $children
                ))
                : [],
        ];
    }
}
