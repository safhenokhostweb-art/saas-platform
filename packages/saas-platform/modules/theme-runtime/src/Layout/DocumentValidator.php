<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/**
 * Phase 2A — fail-closed canonical document validation.
 *
 * Every canonical document entering the editor/renderer boundary passes
 * exactly this validator. It rejects (never repairs, never silently
 * drops):
 *   - unsupported schema version (DocumentEnvelope::SCHEMA_VERSION)
 *   - invalid/empty/duplicate node ids
 *   - unknown node kinds
 *   - widgets carrying children
 *   - self-parenting and cyclic parent/child representations (a node may
 *     not appear twice in one document; a child path may not escape its
 *     parent's subtree)
 *   - nesting deeper than MAX_DEPTH
 *   - documents with more than MAX_NODES
 *   - invalid direction / alignment enums
 *
 * Security posture: document JSON is UNTRUSTED. Capability metadata
 * (lockedStructure, allowedChildKinds, …) read from a document is ignored
 * as authority — capabilities are computed only from trusted host/registry
 * metadata via NodeCapabilities::forNode(). Raw PHP class names, callable
 * strings, and callback-like keys are rejected outright (a document
 * describes composition, never executable behavior).
 */
final class DocumentValidator
{
    /** Bounded depth: page → section → container → row → column → widget + slack. */
    public const MAX_DEPTH = 12;

    /** Bounded node count per document. */
    public const MAX_NODES = 5000;

    /** Node ids must match this (same vocabulary the editor generates). */
    public const ID_PATTERN = '/^[A-Za-z0-9_\-]{1,64}$/';

    /**
     * Validate a full envelope (DocumentEnvelope::make output).
     *
     * @return array{ok:bool, errors:string[], node_count:int, max_depth:int}
     */
    public static function validateEnvelope(array $document): array
    {
        $errors = [];

        if (!DocumentEnvelope::isValid($document)) {
            $errors[] = 'document is not a valid envelope (version/type/direction/page)';

            return ['ok' => false, 'errors' => $errors, 'node_count' => 0, 'max_depth' => 0];
        }

        $page = $document['page'];
        if (($page['kind'] ?? null) !== 'page') {
            $errors[] = "envelope page root must be kind 'page'";
        }

        $result = self::validateTree($page);
        $errors = array_merge($errors, $result['errors']);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'node_count' => $result['node_count'],
            'max_depth' => $result['max_depth'],
        ];
    }

    /**
     * Validate a bare node tree (page node or node list) without an
     * envelope.
     *
     * @return array{ok:bool, errors:string[], node_count:int, max_depth:int}
     */
    public static function validateTree(array $tree): array
    {
        $errors = [];
        $seenIds = [];
        $nodeCount = 0;
        $maxDepth = 0;

        $nodes = array_is_list($tree)
            ? $tree
            : [$tree];

        self::walk($nodes, 1, $seenIds, $errors, $nodeCount, $maxDepth);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'node_count' => $nodeCount,
            'max_depth' => $maxDepth,
        ];
    }

    /**
     * Alignment is always validated in its LOGICAL vocabulary; physical
     * left/right is import-compat only and must go through
     * LayoutContract::normalizeAlignment() at the boundary — it is not a
     * valid canonical stored value.
     */
    public static function isValidAlignment(mixed $value): bool
    {
        return is_string($value) && in_array($value, LayoutContract::ALIGNMENTS, true);
    }

    private static function walk(array $nodes, int $depth, array &$seenIds, array &$errors, int &$nodeCount, int &$maxDepth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $errors[] = "nesting depth exceeds the maximum of " . self::MAX_DEPTH . " at depth {$depth}";

            return;
        }
        $maxDepth = max($maxDepth, $depth - 1);

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                $errors[] = 'node is not an object';

                continue;
            }

            $nodeCount++;
            if ($nodeCount > self::MAX_NODES) {
                $errors[] = 'document exceeds the maximum node count of ' . self::MAX_NODES;

                return;
            }

            // ── id ──────────────────────────────────────────────────────
            $id = $node['id'] ?? null;
            if (!is_string($id) || $id === '') {
                $errors[] = 'node is missing a non-empty string id';

                continue;
            }
            if (preg_match(self::ID_PATTERN, $id) !== 1) {
                $errors[] = "node id '{$id}' is not a valid id (allowed: letters, digits, _-, max 64 chars)";

                continue;
            }
            if (isset($seenIds[$id])) {
                $errors[] = "duplicate node id '{$id}' — a node may appear only once (cycle/self-parent protection)";

                // A duplicated subtree may itself contain duplicates (the only
                // way a cycle is representable) — keep walking its children
                // even though this node's own body was already validated at
                // its first occurrence.
                $dupeChildren = $node['children'] ?? [];
                if (is_array($dupeChildren) && $dupeChildren !== []) {
                    self::walk($dupeChildren, $depth + 1, $seenIds, $errors, $nodeCount, $maxDepth);
                }

                continue;
            }
            $seenIds[$id] = true;

            // ── kind ────────────────────────────────────────────────────
            $kind = $node['kind'] ?? null;
            if (!in_array($kind, LayoutContract::NODE_KINDS, true)) {
                $errors[] = "node '{$id}' has unknown kind '" . (is_scalar($kind) ? (string) $kind : gettype($kind)) . "'";

                continue;
            }

            // ── type ────────────────────────────────────────────────────
            $type = $node['type'] ?? null;
            // The page root is composition-only: it carries no render type.
            if ($kind !== 'page') {
                if (!is_string($type) || $type === '') {
                    $errors[] = "node '{$id}' is missing a type";
                } elseif (preg_match('/[<>\;\{\}]|\.\.|::|\$|\\\\/', $type) === 1) {
                    // No PHP/class/callback vocabulary may ride in a type string.
                    $errors[] = "node '{$id}' type contains forbidden characters";
                }
            }

            // ── executable-looking keys are never accepted ──────────────
            foreach (['renderer', 'rendererClass', 'callback', 'closure', 'class', 'php'] as $forbidden) {
                if (array_key_exists($forbidden, $node)) {
                    $errors[] = "node '{$id}' carries forbidden executable-like key '{$forbidden}'";
                }
            }

            // ── leaf rule ───────────────────────────────────────────────
            if ($kind === 'widget' && array_key_exists('children', $node)) {
                $errors[] = "widget '{$id}' may not carry children";
            }

            // ── alignment vocabulary (logical only) ─────────────────────
            foreach (['alignment', 'justify'] as $alignmentKey) {
                if (array_key_exists($alignmentKey, $node['props'] ?? [])
                    && !self::isValidAlignment($node['props'][$alignmentKey])) {
                    $errors[] = "node '{$id}' prop '{$alignmentKey}' must be one of start|center|end (store logical values only)";
                }
            }

            // ── responsive shape ────────────────────────────────────────
            if (array_key_exists('responsive', $node) && !LayoutContract::isResponsiveShape($node['responsive'])) {
                $errors[] = "node '{$id}' responsive must be an object of desktop|tablet|mobile overrides";
            }

            // ── children (cycle-safe: uniqueness already guards; recurse) ─
            $children = $node['children'] ?? [];
            if (!is_array($children)) {
                $errors[] = "node '{$id}' children must be an array";

                continue;
            }
            if ($children !== []) {
                self::walk($children, $depth + 1, $seenIds, $errors, $nodeCount, $maxDepth);
            }
        }
    }
}
