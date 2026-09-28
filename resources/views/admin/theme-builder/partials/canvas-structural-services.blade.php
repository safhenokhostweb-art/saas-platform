<script>
(function (window) {
    'use strict';
    if (window.CanvasTreeOperations) return;

    const identity = window.CanvasIdentity;
    if (!identity || identity.version !== 1) throw new Error('CanvasIdentity contract is required');

    const clone = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value));
    const structuralKinds = new Set(['section', 'container', 'row', 'column']);

    class CanvasDocumentState {
        constructor(blocks) {
            this.saved = clone(blocks || []);
            this.pendingOperations = [];
            this.selectedNodeId = null;
            this.tree = blocks || [];
        }
        queue(operation) { this.pendingOperations.push(clone(operation)); }
        markSaved(blocks) { this.saved = clone(blocks || []); this.pendingOperations = []; this.reconcile(blocks); }
        select(nodeId, blocks = this.tree) {
            this.tree = blocks || [];
            this.selectedNodeId = identity.reconcileSelection(this.tree, nodeId);
            return this.selectedNodeId;
        }
        reconcile(blocks = this.tree) {
            this.tree = blocks || [];
            this.selectedNodeId = identity.reconcileSelection(this.tree, this.selectedNodeId);
            return this.selectedNodeId;
        }
        path(blocks = this.tree) {
            return this.reconcile(blocks) ? identity.pathForId(this.tree, this.selectedNodeId) : null;
        }
        selectedNode(blocks = this.tree) {
            return this.reconcile(blocks) ? identity.findById(this.tree, this.selectedNodeId) : null;
        }
    }

    class CanvasTreeOperations {
        constructor(schema, options = {}) {
            this.schema = schema || {};
            this.maxDepth = options.maxDepth || 12;
            this.maxNodes = options.maxNodes || 5000;
        }
        kind(node) {
            const explicit = ['section', 'container', 'row', 'column'].includes(node?.type) ? node.type : null;
            if (explicit) return explicit;
            return this.schema[node?.type]?.is_container || Array.isArray(node?.children) ? 'container' : 'widget';
        }
        capabilities(node) {
            const editor = this.schema[node?.type]?.editor || {};
            const kind = this.kind(node);
            const locked = !!editor.lockedStructure;
            return {
                canHaveChildren: structuralKinds.has(kind) && !locked,
                canMove: !locked,
                canDelete: !locked,
                canDuplicate: !locked,
                allowedParentKinds: Array.isArray(editor.allowedParentKinds) ? editor.allowedParentKinds : null,
                allowedChildKinds: Array.isArray(editor.allowedChildKinds) ? editor.allowedChildKinds : null,
                maxChildren: Number.isInteger(editor.maxChildren) && editor.maxChildren > 0 ? editor.maxChildren : null,
                lockedStructure: locked,
            };
        }
        find(blocks, id, parent = null, parentId = 'page', depth = 1) {
            const entry = identity.entryForId(blocks, id);
            if (!entry) return null;
            const resolvedParent = entry.parentId === identity.ROOT_ID ? null : identity.findById(blocks, entry.parentId);
            const siblings = resolvedParent ? (resolvedParent.children || []) : blocks;
            return {
                node: entry.node,
                siblings,
                index: entry.path[entry.path.length - 1],
                parent: resolvedParent,
                parentId: entry.parentId,
                depth: entry.depth + 1,
            };
        }
        parent(blocks, id) {
            if (id === 'page') return { node: { id: 'page', type: 'page' }, children: blocks, depth: 0, kind: 'page' };
            const found = this.find(blocks, id);
            if (!found) return null;
            const kind = this.kind(found.node);
            return { node: found.node, children: Array.isArray(found.node.children) ? found.node.children : [], depth: found.depth, kind };
        }
        contains(node, id) {
            if (node.id === id) return true;
            return (node.children || []).some(child => this.contains(child, id));
        }
        hasLockedAncestor(blocks, id) {
            const walk = (nodes, inherited) => {
                for (const node of nodes) {
                    const locked = inherited || this.capabilities(node).lockedStructure;
                    if (node.id === id) return locked;
                    const child = walk(node.children || [], locked);
                    if (child !== null) return child;
                }
                return null;
            };
            return walk(blocks, false) === true;
        }
        count(node) { return 1 + (node.children || []).reduce((sum, child) => sum + this.count(child), 0); }
        depth(node) { return (node.children || []).reduce((max, child) => Math.max(max, 1 + this.depth(child)), 0); }
        assertPlacement(blocks, node, targetParentId, targetIndex, sameParent = false) {
            const target = this.parent(blocks, targetParentId);
            if (!target) throw new Error('invalid-parent');
            if (this.hasLockedAncestor(blocks, targetParentId)) throw new Error('locked-structure');
            const parentCaps = targetParentId === 'page'
                ? { canHaveChildren: true, allowedChildKinds: ['section', 'container', 'row', 'column', 'widget'], maxChildren: null }
                : this.capabilities(target.node);
            const childKind = this.kind(node);
            if (!parentCaps.canHaveChildren) throw new Error('invalid-parent-capability');
            if (parentCaps.allowedChildKinds && !parentCaps.allowedChildKinds.includes(childKind)) throw new Error('invalid-child-capability');
            const childCaps = this.capabilities(node);
            if (childCaps.allowedParentKinds && !childCaps.allowedParentKinds.includes(target.kind)) throw new Error('invalid-parent-kind');
            if (!Number.isInteger(targetIndex) || targetIndex < 0 || targetIndex > target.children.length) throw new Error('invalid-index');
            if (!sameParent && parentCaps.maxChildren && target.children.length >= parentCaps.maxChildren) throw new Error('max-children');
            if (target.depth + 1 + this.depth(node) > this.maxDepth) throw new Error('max-depth');
            const total = blocks.reduce((sum, child) => sum + this.count(child), 0);
            if (!this.find(blocks, node.id) && total + this.count(node) > this.maxNodes) throw new Error('max-nodes');
            return target;
        }
        insertNode(blocks, node, targetParentId = 'page', targetIndex = null) {
            if (!node || typeof node.id !== 'string' || this.find(blocks, node.id)) throw new Error('duplicate-or-invalid-id');
            const target = this.parent(blocks, targetParentId);
            const index = targetIndex == null ? (target?.children.length ?? 0) : targetIndex;
            this.assertPlacement(blocks, node, targetParentId, index, false);
            if (targetParentId !== 'page' && !Array.isArray(target.node.children)) target.node.children = target.children;
            target.children.splice(index, 0, node);
            return { operation: 'insert', node_id: node.id, target_parent_id: targetParentId, target_index: index, node: clone(node) };
        }
        moveNode(blocks, nodeId, targetParentId = 'page', targetIndex = 0) {
            const source = this.find(blocks, nodeId);
            if (!source) throw new Error('invalid-node');
            if (!this.capabilities(source.node).canMove || this.hasLockedAncestor(blocks, nodeId)) throw new Error('locked-structure');
            if (nodeId === targetParentId || this.contains(source.node, targetParentId)) throw new Error('cycle');
            const sameParent = source.parentId === targetParentId;
            const target = this.assertPlacement(blocks, source.node, targetParentId, targetIndex, sameParent);
            const before = { parentId: source.parentId, index: source.index };
            const requestedIndex = targetIndex;
            const [node] = source.siblings.splice(source.index, 1);
            if (sameParent && source.index < targetIndex) targetIndex--;
            const refreshedTarget = this.parent(blocks, targetParentId);
            if (targetParentId !== 'page' && !Array.isArray(refreshedTarget.node.children)) refreshedTarget.node.children = refreshedTarget.children;
            refreshedTarget.children.splice(targetIndex, 0, node);
            return { operation: 'move', node_id: nodeId, target_parent_id: targetParentId, target_index: requestedIndex, final_index: targetIndex, before };
        }
        deleteNode(blocks, nodeId) {
            const source = this.find(blocks, nodeId);
            if (!source) throw new Error('invalid-node');
            if (!this.capabilities(source.node).canDelete || this.hasLockedAncestor(blocks, nodeId)) throw new Error('locked-structure');
            const [node] = source.siblings.splice(source.index, 1);
            return { operation: 'delete', node_id: nodeId, before: { parentId: source.parentId, index: source.index, node: clone(node) } };
        }
        duplicateNode(blocks, nodeId, targetParentId = null, targetIndex = null, idFactory = null) {
            const source = this.find(blocks, nodeId);
            if (!source) throw new Error('invalid-node');
            if (!this.capabilities(source.node).canDuplicate || this.hasLockedAncestor(blocks, nodeId)) throw new Error('locked-structure');
            const fresh = idFactory || (() => 'blk_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8));
            const renew = node => {
                const copy = clone(node);
                copy.id = fresh();
                if (Array.isArray(copy.children)) copy.children = copy.children.map(renew);
                return copy;
            };
            const copy = renew(source.node);
            const parentId = targetParentId || source.parentId;
            const index = targetIndex == null ? source.index + 1 : targetIndex;
            this.assertPlacement(blocks, copy, parentId, index, false);
            const target = this.parent(blocks, parentId);
            if (parentId !== 'page' && !Array.isArray(target.node.children)) target.node.children = target.children;
            target.children.splice(index, 0, copy);
            return { operation: 'duplicate', node_id: nodeId, target_parent_id: parentId, target_index: index, node: clone(copy), duplicate_id: copy.id };
        }
    }

    class CanvasHistory {
        constructor(apply, limit = 100) { this.apply = apply; this.limit = limit; this.undoStack = []; this.redoStack = []; }
        record(entry) {
            const now = Date.now();
            const previous = this.undoStack[this.undoStack.length - 1];
            if (entry.coalesceKey && previous?.coalesceKey === entry.coalesceKey && now - previous.timestamp < 700) {
                previous.after = clone(entry.after); previous.timestamp = now; previous.selectionAfter = entry.selectionAfter;
            } else {
                this.undoStack.push({ ...entry, before: clone(entry.before), after: clone(entry.after), timestamp: now });
                if (this.undoStack.length > this.limit) this.undoStack.shift();
            }
            this.redoStack = [];
        }
        get canUndo() { return this.undoStack.length > 0; }
        get canRedo() { return this.redoStack.length > 0; }
        undo() { const entry = this.undoStack.pop(); if (!entry) return null; this.apply(entry, 'before'); this.redoStack.push(entry); return entry; }
        redo() { const entry = this.redoStack.pop(); if (!entry) return null; this.apply(entry, 'after'); this.undoStack.push(entry); return entry; }
        recent(limit = 10) { return this.undoStack.slice(-limit).reverse(); }
        clear() { this.undoStack = []; this.redoStack = []; }
    }

    class CanvasNavigator {
        flatten(blocks, expanded = {}, depth = 0, result = []) {
            const flat = identity.flatten(blocks);
            const visible = flat.filter(entry => {
                for (let level = 1; level < entry.path.length; level++) {
                    const ancestor = identity.nodeAtPath(blocks, entry.path.slice(0, level));
                    if (ancestor && expanded[ancestor.id] === false) return false;
                }
                return true;
            }).map(entry => ({
                block: entry.node,
                id: entry.id,
                parentId: entry.parentId,
                depth: entry.depth + depth,
                path: [...entry.path],
            }));
            result.push(...visible);
            return result;
        }
        label(block, labels) {
            // Phase 2C (GLM correction): only label/name are identity-ish
            // custom labels. Codex's version also folded props.title in,
            // which changed the accepted Navigator text contract (content
            // titles like a gallery's heading must not prefix the tree
            // label — the e2e suite pins the bare type label).
            const custom = block?.props?.label || block?.props?.name;
            return custom ? `${labels[block.type] || block.type}: ${custom}` : (labels[block.type] || block.type);
        }
    }

    class CanvasDragDrop {
        static resolve(rect, x, y, direction, canNest, horizontal = false) {
            if (canNest && x > rect.left + rect.width * .22 && x < rect.right - rect.width * .22 && y > rect.top + rect.height * .22 && y < rect.bottom - rect.height * .22) return 'inside';
            if (horizontal) {
                const first = direction === 'rtl' ? x > rect.left + rect.width / 2 : x < rect.left + rect.width / 2;
                return first ? 'before' : 'after';
            }
            return y < rect.top + rect.height / 2 ? 'before' : 'after';
        }
    }

    window.CanvasDocumentState = CanvasDocumentState;
    window.CanvasTreeOperations = CanvasTreeOperations;
    window.CanvasHistory = CanvasHistory;
    window.CanvasNavigator = CanvasNavigator;
    window.CanvasDragDrop = CanvasDragDrop;
}(window));
</script>
