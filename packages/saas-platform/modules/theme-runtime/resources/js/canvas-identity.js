(function (root, factory) {
    'use strict';

    const contract = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = contract;
    }

    if (root) {
        root.CanvasIdentity = contract;
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    const ROOT_ID = 'page';

    function isNode(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function isValidNodeId(id) {
        return typeof id === 'string' && id.trim().length > 0;
    }

    function childrenOf(node) {
        return isNode(node) && Array.isArray(node.children) ? node.children : [];
    }

    function flatten(tree) {
        if (!Array.isArray(tree)) return [];

        const result = [];
        const visited = new Set();

        function walk(nodes, parentId, depth, prefix) {
            for (let index = 0; index < nodes.length; index++) {
                const node = nodes[index];
                if (!isNode(node) || visited.has(node)) continue;

                visited.add(node);
                const path = [...prefix, index];
                result.push({ node, id: node.id, parentId, depth, path });
                walk(childrenOf(node), node.id, depth + 1, path);
            }
        }

        walk(tree, ROOT_ID, 0, []);
        return result;
    }

    function entryForId(tree, id) {
        if (!isValidNodeId(id)) return null;
        return flatten(tree).find(entry => entry.id === id) ?? null;
    }

    function findById(tree, id) {
        return entryForId(tree, id)?.node ?? null;
    }

    function pathForId(tree, id) {
        const path = entryForId(tree, id)?.path;
        return path ? [...path] : null;
    }

    function nodeAtPath(tree, path) {
        if (!Array.isArray(tree) || !Array.isArray(path) || path.length === 0) return null;

        let nodes = tree;
        let node = null;

        for (const index of path) {
            if (!Number.isInteger(index) || index < 0 || index >= nodes.length) return null;
            node = nodes[index];
            if (!isNode(node)) return null;
            nodes = childrenOf(node);
        }

        return node;
    }

    function parentForId(tree, id) {
        const entry = entryForId(tree, id);
        if (!entry || entry.parentId === ROOT_ID) return null;
        return findById(tree, entry.parentId);
    }

    function reconcileSelection(tree, selectedNodeId) {
        return isValidNodeId(selectedNodeId) && findById(tree, selectedNodeId)
            ? selectedNodeId
            : null;
    }

    function validateTreeIdentity(tree) {
        const invalid = [];
        const duplicates = [];
        const ids = new Set();

        for (const entry of flatten(tree)) {
            if (!isValidNodeId(entry.id)) {
                invalid.push([...entry.path]);
                continue;
            }
            if (ids.has(entry.id)) duplicates.push(entry.id);
            else ids.add(entry.id);
        }

        return Object.freeze({
            valid: invalid.length === 0 && duplicates.length === 0,
            invalid,
            duplicates,
        });
    }

    return Object.freeze({
        version: 1,
        ROOT_ID,
        isValidNodeId,
        validateTreeIdentity,
        findById,
        pathForId,
        nodeAtPath,
        parentForId,
        flatten,
        entryForId,
        reconcileSelection,
    });
}));
