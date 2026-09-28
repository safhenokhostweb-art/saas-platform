<?php

namespace SaaSPlatform\ThemeRuntime\Canvas;

use DomainException;
use SaaSPlatform\ThemeRuntime\Layout\DocumentValidator;
use SaaSPlatform\ThemeRuntime\Layout\LayoutContract;
use SaaSPlatform\ThemeRuntime\Layout\NodeCapabilities;

/**
 * Canonical, renderer-independent structural operations for Canvas documents.
 *
 * The service mutates canonical page-node arrays only. Capability metadata is
 * supplied by the host from its trusted registry; capability-like keys in the
 * submitted document are never consulted. Every operation validates the final
 * document through DocumentValidator before returning it.
 */
final class CanvasTreeOperations
{
    /** @var callable(array):array */
    private $metadataForNode;

    public function __construct(?callable $metadataForNode = null)
    {
        $this->metadataForNode = $metadataForNode ?? static fn (array $node): array => [];
    }

    public function insertNode(array $page, array $node, string $targetParentId, int $targetIndex): array
    {
        $this->assertNodeIsInsertable($page, $node);
        $parentPath = $this->requirePath($page, $targetParentId);
        $this->assertUnlockedPath($page, $parentPath);
        $this->assertPlacement($page, $node, null, $parentPath, $targetIndex);

        $parent =& $this->nodeReference($page, $parentPath);
        $parent['children'] ??= [];
        array_splice($parent['children'], $targetIndex, 0, [$node]);

        return $this->validated($page);
    }

    public function moveNode(array $page, string $nodeId, string $targetParentId, int $targetIndex): array
    {
        $nodePath = $this->requirePath($page, $nodeId);
        $parentPath = $this->requirePath($page, $targetParentId);
        if ($nodePath === []) {
            throw new DomainException('The page root cannot be moved.');
        }

        $this->assertUnlockedPath($page, $nodePath);
        $this->assertUnlockedPath($page, $parentPath);
        $node = NodeCapabilities::nodeAtPath($page, $nodePath);
        if ($node === null) {
            throw new DomainException("Node '{$nodeId}' does not exist.");
        }
        $nodeCaps = $this->capabilities($node);
        if (empty($nodeCaps['canMove'])) {
            throw new DomainException("Node '{$nodeId}' cannot be moved.");
        }

        $this->assertPlacement($page, $node, $nodePath, $parentPath, $targetIndex);

        $sourceParentPath = array_slice($nodePath, 0, -1);
        $sourceIndex = $nodePath[array_key_last($nodePath)];
        $sourceParent =& $this->nodeReference($page, $sourceParentPath);
        [$moving] = array_splice($sourceParent['children'], $sourceIndex, 1);

        // Removing an earlier sibling shifts the insertion index left.
        if ($sourceParentPath === $parentPath && $sourceIndex < $targetIndex) {
            $targetIndex--;
        }
        $targetParent =& $this->nodeReference($page, $parentPath);
        $targetParent['children'] ??= [];
        array_splice($targetParent['children'], $targetIndex, 0, [$moving]);

        return $this->validated($page);
    }

    /** @return array{page:array,deleted:array,parent_id:string,index:int} */
    public function deleteNode(array $page, string $nodeId): array
    {
        $path = $this->requirePath($page, $nodeId);
        if ($path === []) {
            throw new DomainException('The page root cannot be deleted.');
        }
        $this->assertUnlockedPath($page, $path);
        $node = NodeCapabilities::nodeAtPath($page, $path);
        if ($node === null || empty($this->capabilities($node)['canDelete'])) {
            throw new DomainException("Node '{$nodeId}' cannot be deleted.");
        }

        $parentPath = array_slice($path, 0, -1);
        $index = $path[array_key_last($path)];
        $parent =& $this->nodeReference($page, $parentPath);
        [$deleted] = array_splice($parent['children'], $index, 1);

        return [
            'page' => $this->validated($page),
            'deleted' => $deleted,
            'parent_id' => (string) ($parent['id'] ?? 'page'),
            'index' => $index,
        ];
    }

    public function duplicateNode(
        array $page,
        string $nodeId,
        string $targetParentId,
        int $targetIndex,
        callable $newId
    ): array {
        $path = $this->requirePath($page, $nodeId);
        $node = NodeCapabilities::nodeAtPath($page, $path);
        if ($path === [] || $node === null || empty($this->capabilities($node)['canDuplicate'])) {
            throw new DomainException("Node '{$nodeId}' cannot be duplicated.");
        }
        $copy = $this->withFreshIds($node, $newId);

        return $this->insertNode($page, $copy, $targetParentId, $targetIndex);
    }

    /**
     * Replays untrusted client operation descriptions against the trusted
     * stored tree, then proves their structural result equals the submitted
     * tree. This prevents a forged target/index/capability flag from bypassing
     * server-side placement rules.
     */
    public function validateTransition(array $beforePage, array $afterPage, array $operations): array
    {
        if ($this->structure($beforePage) === $this->structure($afterPage)) {
            return ['ok' => true, 'errors' => []];
        }
        if ($operations === []) {
            try {
                $reconciled = $this->reconcileImplicitTransition($beforePage, $afterPage);
                return $this->structure($reconciled) === $this->structure($afterPage)
                    ? ['ok' => true, 'errors' => []]
                    : ['ok' => false, 'errors' => ['Submitted topology cannot be reconciled through canonical operations.']];
            } catch (DomainException $e) {
                return ['ok' => false, 'errors' => [$e->getMessage()]];
            }
        }

        $working = $beforePage;
        try {
            foreach ($operations as $operation) {
                if (!is_array($operation)) {
                    throw new DomainException('Structural operation must be an object.');
                }
                $kind = $operation['operation'] ?? null;
                $nodeId = $operation['node_id'] ?? null;
                $parentId = $operation['target_parent_id'] ?? 'page';
                $index = $operation['target_index'] ?? null;
                if (!is_string($kind) || !is_string($nodeId)) {
                    throw new DomainException('Structural operation is missing its operation or node id.');
                }

                if ($kind === 'move') {
                    if (!is_string($parentId) || !is_int($index)) throw new DomainException('Move target is invalid.');
                    $working = $this->moveNode($working, $nodeId, $parentId, $index);
                } elseif ($kind === 'delete') {
                    $working = $this->deleteNode($working, $nodeId)['page'];
                } elseif ($kind === 'insert') {
                    $node = $operation['node'] ?? null;
                    if (!is_array($node) || !is_string($parentId) || !is_int($index)) throw new DomainException('Insert payload is invalid.');
                    $canonicalNode = LayoutContract::fromLegacyBlocks([$node])['children'][0] ?? null;
                    if (!is_array($canonicalNode)) throw new DomainException('Insert node cannot be adapted to the canonical layout.');
                    $working = $this->insertNode($working, $canonicalNode, $parentId, $index);
                } elseif ($kind === 'duplicate') {
                    $copy = $operation['node'] ?? null;
                    $sourcePath = $this->requirePath($working, $nodeId);
                    $source = NodeCapabilities::nodeAtPath($working, $sourcePath);
                    $canonicalCopy = is_array($copy) ? (LayoutContract::fromLegacyBlocks([$copy])['children'][0] ?? null) : null;
                    if (!is_array($canonicalCopy) || $source === null || !$this->sameContentExceptIds($source, $canonicalCopy)) {
                        throw new DomainException('Duplicate payload does not match its source node.');
                    }
                    if (!is_string($parentId) || !is_int($index)) throw new DomainException('Duplicate target is invalid.');
                    $working = $this->insertNode($working, $canonicalCopy, $parentId, $index);
                } else {
                    throw new DomainException("Unknown structural operation '{$kind}'.");
                }
            }
        } catch (DomainException $e) {
            return ['ok' => false, 'errors' => [$e->getMessage()]];
        }

        if ($this->structure($working) !== $this->structure($afterPage)) {
            return ['ok' => false, 'errors' => ['Structural operation result does not match the submitted document.']];
        }

        return ['ok' => true, 'errors' => []];
    }

    /** Backward-compatible full-document save validation for older clients. */
    private function reconcileImplicitTransition(array $working, array $after): array
    {
        $beforeIndex = $this->indexTree($working);
        $afterIndex = $this->indexTree($after);

        // Delete only the highest missing roots; deleting one removes its
        // whole subtree and still passes the same locked/capability guards.
        $missing = array_filter($beforeIndex, static fn (array $entry, string $id): bool => $id !== 'page' && !isset($afterIndex[$id]), ARRAY_FILTER_USE_BOTH);
        uasort($missing, static fn (array $a, array $b): int => $a['depth'] <=> $b['depth']);
        foreach ($missing as $id => $entry) {
            if ($entry['parent_id'] !== null && isset($missing[$entry['parent_id']])) continue;
            $working = $this->deleteNode($working, $id)['page'];
        }

        // Insert only highest new roots; their descendants ride with them.
        $new = array_filter($afterIndex, static fn (array $entry, string $id): bool => $id !== 'page' && !isset($beforeIndex[$id]), ARRAY_FILTER_USE_BOTH);
        uasort($new, static fn (array $a, array $b): int => $a['depth'] <=> $b['depth'] ?: $a['index'] <=> $b['index']);
        foreach ($new as $id => $entry) {
            if ($entry['parent_id'] !== null && isset($new[$entry['parent_id']])) continue;
            $parentPath = $this->requirePath($working, (string) $entry['parent_id']);
            $parent = NodeCapabilities::nodeAtPath($working, $parentPath);
            $safeIndex = min($entry['index'], count($parent['children'] ?? []));
            $working = $this->insertNode($working, $entry['node'], (string) $entry['parent_id'], $safeIndex);
        }

        // Project every final sibling sequence through moveNode. This also
        // validates cross-parent moves and reorders from legacy clients that
        // do not yet submit an explicit operation log.
        $walk = function (array $targetParent) use (&$walk, &$working): void {
            $parentId = (string) ($targetParent['id'] ?? 'page');
            foreach (array_values($targetParent['children'] ?? []) as $desired => $targetChild) {
                $path = $this->requirePath($working, (string) $targetChild['id']);
                $current = NodeCapabilities::nodeAtPath($working, $path);
                $currentParentPath = array_slice($path, 0, -1);
                $currentParent = NodeCapabilities::nodeAtPath($working, $currentParentPath);
                $currentParentId = (string) ($currentParent['id'] ?? 'page');
                $currentIndex = $path[array_key_last($path)];
                if ($currentParentId !== $parentId || $currentIndex !== $desired) {
                    $requestedIndex = $currentParentId === $parentId && $currentIndex < $desired ? $desired + 1 : $desired;
                    $working = $this->moveNode($working, (string) $targetChild['id'], $parentId, $requestedIndex);
                }
                $walk($targetChild);
            }
        };
        $walk($after);

        return $working;
    }

    /** @return array<string,array{node:array,parent_id:?string,index:int,depth:int}> */
    private function indexTree(array $node, ?string $parentId = null, int $index = 0, int $depth = 0, array &$map = []): array
    {
        $id = (string) ($node['id'] ?? '');
        $map[$id] = ['node' => $node, 'parent_id' => $parentId, 'index' => $index, 'depth' => $depth];
        foreach (array_values($node['children'] ?? []) as $childIndex => $child) {
            if (is_array($child)) $this->indexTree($child, $id, $childIndex, $depth + 1, $map);
        }
        return $map;
    }

    private function assertNodeIsInsertable(array $page, array $node): void
    {
        $validation = DocumentValidator::validateTree($node);
        if (!$validation['ok']) {
            throw new DomainException('Inserted node is invalid: '.implode(' ', $validation['errors']));
        }
        foreach ($this->ids($node) as $id) {
            if ($this->findPath($page, $id) !== null) {
                throw new DomainException("Duplicate node id '{$id}'.");
            }
        }
    }

    private function assertPlacement(array $page, array $node, ?array $nodePath, array $parentPath, int $index): void
    {
        if ($nodePath !== null && ($nodePath === $parentPath || NodeCapabilities::isPrefix($nodePath, $parentPath))) {
            throw new DomainException('A node cannot be moved into itself or one of its descendants.');
        }
        $parent = NodeCapabilities::nodeAtPath($page, $parentPath);
        if ($parent === null) throw new DomainException('Target parent does not exist.');
        $children = $parent['children'] ?? [];
        if ($index < 0 || $index > count($children)) throw new DomainException('Target index is out of bounds.');

        $parentCaps = $this->capabilities($parent);
        if (!NodeCapabilities::acceptsChild($parentCaps, (string) ($node['kind'] ?? ''))) {
            throw new DomainException('Target parent does not accept this child kind.');
        }
        $nodeCaps = $this->capabilities($node);
        $allowedParents = $nodeCaps['allowedParentKinds'] ?? null;
        if (is_array($allowedParents) && !in_array($parent['kind'] ?? '', $allowedParents, true)) {
            throw new DomainException('Node capability rejects the target parent kind.');
        }
        $sameParent = $nodePath !== null && array_slice($nodePath, 0, -1) === $parentPath;
        $max = $parentCaps['maxChildren'] ?? null;
        if (is_int($max) && !$sameParent && count($children) >= $max) {
            throw new DomainException('Target parent has reached maxChildren.');
        }

        $targetDepth = count($parentPath) + 1;
        if ($targetDepth + $this->subtreeDepth($node) > DocumentValidator::MAX_DEPTH) {
            throw new DomainException('Move would exceed the maximum nesting depth.');
        }
    }

    private function assertUnlockedPath(array $page, array $path): void
    {
        for ($i = 0; $i <= count($path); $i++) {
            $node = NodeCapabilities::nodeAtPath($page, array_slice($path, 0, $i));
            if ($node !== null && !empty($this->capabilities($node)['lockedStructure'])) {
                throw new DomainException("Locked structure '{$node['id']}' cannot be mutated.");
            }
        }
    }

    private function capabilities(array $node): array
    {
        return NodeCapabilities::forNode($node, ($this->metadataForNode)($node));
    }

    private function requirePath(array $page, string $id): array
    {
        $path = $this->findPath($page, $id);
        if ($path === null) throw new DomainException("Node '{$id}' does not exist.");
        return $path;
    }

    private function findPath(array $node, string $id, array $path = []): ?array
    {
        if (($node['id'] ?? null) === $id) return $path;
        foreach (($node['children'] ?? []) as $index => $child) {
            if (!is_array($child)) continue;
            $found = $this->findPath($child, $id, [...$path, $index]);
            if ($found !== null) return $found;
        }
        return null;
    }

    private function &nodeReference(array &$page, array $path): array
    {
        $node =& $page;
        foreach ($path as $index) $node =& $node['children'][$index];
        return $node;
    }

    private function validated(array $page): array
    {
        $result = DocumentValidator::validateTree($page);
        if (!$result['ok']) throw new DomainException(implode(' ', $result['errors']));
        return $page;
    }

    private function subtreeDepth(array $node): int
    {
        $max = 0;
        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child)) $max = max($max, 1 + $this->subtreeDepth($child));
        }
        return $max;
    }

    private function ids(array $node): array
    {
        $ids = [(string) ($node['id'] ?? '')];
        foreach (($node['children'] ?? []) as $child) if (is_array($child)) $ids = [...$ids, ...$this->ids($child)];
        return $ids;
    }

    private function withFreshIds(array $node, callable $newId): array
    {
        $node['id'] = (string) $newId($node);
        if (isset($node['children']) && is_array($node['children'])) {
            $node['children'] = array_map(fn (array $child): array => $this->withFreshIds($child, $newId), $node['children']);
        }
        return $node;
    }

    private function structure(array $node): array
    {
        return [
            'id' => $node['id'] ?? null,
            'type' => $node['type'] ?? null,
            'kind' => $node['kind'] ?? null,
            'children' => array_map(fn (array $child): array => $this->structure($child), array_values(array_filter($node['children'] ?? [], 'is_array'))),
        ];
    }

    private function sameContentExceptIds(array $source, array $copy): bool
    {
        $strip = function (array $node) use (&$strip): array {
            unset($node['id']);
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = array_map(fn (array $child): array => $strip($child), $node['children']);
            }
            return $node;
        };
        return $strip($source) === $strip($copy);
    }
}
