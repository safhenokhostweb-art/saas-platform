<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/**
 * Phase 2A — the canonical node CAPABILITY contract: the declarative
 * composition metadata future moveNode/drop validation and the navigator
 * will consume.
 *
 * Design rules:
 *  - Capabilities are DERIVED from trusted inputs (layout kind + optional
 *    explicit capability metadata supplied by the host/registry), never
 *    read from untrusted document JSON as authority. A document may not
 *    grant itself capabilities (see DocumentValidator: capability keys in
 *    untrusted input are ignored, not honored).
 *  - Placement is governed by explicit capability/constraint data — never
 *    theme-specific assumptions (no "menu belongs in header" rules here).
 *  - lockedStructure protects fidelity-sensitive imported theme
 *    structures: a locked subtree keeps its structure (no child
 *    add/remove/reorder, no reparenting) while ordinary Canvas-created
 *    structures stay fully flexible. Locked nodes still allow their own
 *    PROPS to be edited (presentation fidelity is about structure, not
 *    content), unless the host also denies canEdit via its metadata.
 *
 * This class computes capability flags only. It implements no UI, no
 * moveNode, and no persistence.
 */
final class NodeCapabilities
{
    /** All capability flags this contract defines. */
    public const FLAGS = [
        'canHaveChildren',
        'canMove',
        'canDuplicate',
        'canDelete',
        'canEdit',
        'allowedParentKinds',
        'allowedChildKinds',
        'maxChildren',
        'lockedStructure',
    ];

    /**
     * Compute the capability set for a node.
     *
     * @param array $node            the canonical node (kind/id/…)
     * @param array $trustedMetadata optional host/registry-supplied metadata:
     *        ['allowedParentKinds' => list<string>|null,
     *         'allowedChildKinds'  => list<string>|null,
     *         'maxChildren'        => positive-int|null,
     *         'lockedStructure'    => bool]
     * @return array<string,mixed> every flag resolved (closed set)
     */
    public static function forNode(array $node, array $trustedMetadata = []): array
    {
        $kind = $node['kind'] ?? '';

        $isPage = $kind === 'page';
        $isStructure = in_array($kind, LayoutContract::STRUCTURE_KINDS, true);
        $isWidget = $kind === 'widget';

        // Trusted structure lock (imported fidelity-sensitive subtree).
        $locked = (bool) ($trustedMetadata['lockedStructure'] ?? false);

        // Child-kind constraints: trusted metadata, else the generic kind
        // rules (anything may contain anything compatible — placement is
        // capability-driven, not theme-assumed).
        $allowedChildKinds = $trustedMetadata['allowedChildKinds'] ?? null;
        $allowedParentKinds = $trustedMetadata['allowedParentKinds'] ?? null;
        $maxChildren = $trustedMetadata['maxChildren'] ?? null;

        $capabilities = [
            // Structure: page/structure kinds may have children; widgets
            // are leaves (LayoutContract also rejects widget children).
            'canHaveChildren' => $isPage || ($isStructure && !$locked),

            // Movement: the page root cannot move/duplicate/delete; locked
            // nodes cannot be reparented/reordered (structure fidelity),
            // but their content remains editable.
            'canMove' => !$isPage && !$locked,
            'canDuplicate' => !$isPage && !$locked,
            'canDelete' => !$isPage && !$locked,

            // Content editing: available on everything, locked or not —
            // lockedStructure protects STRUCTURE, not authoring content.
            'canEdit' => (bool) ($trustedMetadata['canEdit'] ?? true),

            'allowedParentKinds' => is_array($allowedParentKinds) ? $allowedParentKinds : null,
            'allowedChildKinds' => is_array($allowedChildKinds) ? $allowedChildKinds : null,
            'maxChildren' => (is_int($maxChildren) && $maxChildren > 0) ? $maxChildren : null,

            'lockedStructure' => $locked,
        ];

        return $capabilities;
    }

    /**
     * Can a node of $kind accept a child of $childKind, given the trusted
     * capability set? Future moveNode/drop validation calls exactly this —
     * capability data in, boolean out, no theme names anywhere.
     */
    public static function acceptsChild(array $capabilities, string $childKind): bool
    {
        if (empty($capabilities['canHaveChildren'])) {
            return false;
        }

        $allowed = $capabilities['allowedChildKinds'] ?? null;
        if (is_array($allowed)) {
            return in_array($childKind, $allowed, true);
        }

        // Generic rule: widgets are leaves; structure kinds nest freely.
        return in_array($childKind, LayoutContract::NODE_KINDS, true) && $childKind !== 'page';
    }

    /**
     * Can the node at $nodePath be moved to become a child of the node at
     * $targetParentPath inside the tree $tree? Enforces: no move onto
     * itself or into its own subtree (cycle prevention), parent
     * acceptance, max children, and parent-kind constraints. Pure function
     * over the tree — no UI, no persistence.
     */
    public static function canMoveTo(array $tree, array $nodePath, array $targetParentPath, ?callable $capabilitiesFor = null): bool
    {
        $node = self::nodeAtPath($tree, $nodePath);
        $parent = self::nodeAtPath($tree, $targetParentPath);
        if ($node === null || $parent === null) {
            return false;
        }

        // No move onto itself.
        if (self::samePath($nodePath, $targetParentPath)) {
            return false;
        }

        // No move into own subtree (would create a cycle): the target
        // parent must not be a descendant of the moving node — i.e. the
        // node's own path may never be a prefix of the target path.
        if (self::isPrefix($nodePath, $targetParentPath)) {
            return false;
        }

        $parentCaps = $capabilitiesFor
            ? $capabilitiesFor($parent)
            : self::forNode($parent);
        if (!self::acceptsChild($parentCaps, $node['kind'] ?? '')) {
            return false;
        }

        $max = $parentCaps['maxChildren'] ?? null;
        if (is_int($max)) {
            $currentCount = count($parent['children'] ?? []);
            // Moving within the same parent keeps the count; otherwise the
            // move would add one.
            $sameParent = self::samePath(
                self::parentOf($nodePath),
                $targetParentPath
            );
            if (!$sameParent && $currentCount >= $max) {
                return false;
            }
        }

        return true;
    }

    // ── Path helpers (paths are index arrays into children, Phase 1 shape) ──

    public static function nodeAtPath(array $tree, array $path): ?array
    {
        $node = $tree;
        foreach ($path as $index) {
            if (!is_int($index)) {
                return null;
            }
            if (!isset($node['children'][$index]) || !is_array($node['children'][$index])) {
                return null;
            }
            $node = $node['children'][$index];
        }

        return is_array($node) ? $node : null;
    }

    public static function samePath(array $a, array $b): bool
    {
        return $a === $b;
    }

    public static function isPrefix(array $maybePrefix, array $path): bool
    {
        if (count($maybePrefix) >= count($path)) {
            return false;
        }
        foreach ($maybePrefix as $i => $value) {
            if ($path[$i] !== $value) {
                return false;
            }
        }

        return true;
    }

    public static function parentOf(array $path): array
    {
        return array_slice($path, 0, -1);
    }
}
