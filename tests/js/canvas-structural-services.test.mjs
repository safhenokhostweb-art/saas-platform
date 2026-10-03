import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const identitySource = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-identity.js', import.meta.url), 'utf8');
const source = fs.readFileSync(new URL('../../resources/views/admin/theme-builder/partials/canvas-structural-services.blade.php', import.meta.url), 'utf8')
  .replace(/^<script>\s*/, '').replace(/\s*<\/script>\s*$/, '');
const context = vm.createContext({ window: {}, globalThis: {}, module: { exports: {} }, console, JSON, Date, Math, Set, Object, Array, Error });
vm.runInContext(identitySource, context);
vm.runInContext(source, context);
const { CanvasDocumentState, CanvasTreeOperations, CanvasHistory, CanvasNavigator, CanvasDragDrop } = context.window;

const schemas = {
  section: { is_container: true, editor: {} },
  container: { is_container: true, editor: {} },
  column: { is_container: true, editor: {} },
  locked: { is_container: true, editor: { lockedStructure: true } },
  heading: { is_container: false, editor: {} },
  image: { is_container: false, editor: {} },
};
const fixture = () => [{ id: 'section', type: 'section', children: [
  { id: 'c1', type: 'column', children: [
    { id: 'a', type: 'heading', props: { text: 'A' }, responsive: { mobile: { size: 12 } }, dataBinding: { provider: 'products' }, modulePresentation: { card: 'compact' } },
    { id: 'b', type: 'image', props: { src: 'b.jpg' } },
  ] },
  { id: 'c2', type: 'column', children: [] },
] }];

test('canonical move reorders, crosses columns, nests, and preserves all data', () => {
  const blocks = fixture();
  const ops = new CanvasTreeOperations(schemas);
  ops.moveNode(blocks, 'b', 'c1', 0);
  assert.deepEqual(blocks[0].children[0].children.map(n => n.id), ['b', 'a']);
  ops.moveNode(blocks, 'a', 'c2', 0);
  assert.deepEqual(blocks[0].children[1].children[0].responsive, { mobile: { size: 12 } });
  assert.deepEqual(blocks[0].children[1].children[0].dataBinding, { provider: 'products' });
  assert.deepEqual(blocks[0].children[1].children[0].modulePresentation, { card: 'compact' });
});

test('cycle, leaf parent, maxChildren, and locked structure reject', () => {
  const blocks = fixture();
  const ops = new CanvasTreeOperations({ ...schemas, column: { is_container: true, editor: { maxChildren: 2 } } });
  assert.throws(() => ops.moveNode(blocks, 'section', 'c1', 0), /cycle/);
  assert.throws(() => ops.moveNode(blocks, 'a', 'b', 0), /invalid-parent-capability/);
  ops.insertNode(blocks, { id: 'x', type: 'heading' }, 'c2', 0);
  ops.insertNode(blocks, { id: 'y', type: 'heading' }, 'c2', 1);
  assert.throws(() => ops.moveNode(blocks, 'a', 'c2', 2), /max-children/);
  const locked = [{ id: 'lock', type: 'locked', children: [{ id: 'inside', type: 'heading' }] }];
  assert.throws(() => ops.deleteNode(locked, 'inside'), /locked-structure/);
});

test('duplicate recursively regenerates ids and keeps responsive/binding data', () => {
  const blocks = fixture();
  const ids = ['copy-a'];
  const op = new CanvasTreeOperations(schemas).duplicateNode(blocks, 'a', 'c2', 0, () => ids.shift());
  assert.equal(op.duplicate_id, 'copy-a');
  assert.equal(blocks[0].children[1].children[0].id, 'copy-a');
  assert.deepEqual(blocks[0].children[1].children[0].dataBinding, { provider: 'products' });
});

test('multi-step history undoes/redoes five operations and invalidates redo', () => {
  const state = { value: 0 };
  const history = new CanvasHistory((entry, side) => { state.value = entry[side]; });
  for (let i = 1; i <= 5; i++) { history.record({ type: 'value', targetId: 'a', before: i - 1, after: i }); state.value = i; }
  for (let i = 4; i >= 0; i--) { history.undo(); assert.equal(state.value, i); }
  for (let i = 1; i <= 5; i++) { history.redo(); assert.equal(state.value, i); }
  history.undo();
  history.record({ type: 'value', targetId: 'a', before: 4, after: 9 });
  assert.equal(history.canRedo, false);
});

test('history coalesces high-frequency edits', () => {
  const history = new CanvasHistory(() => {});
  history.record({ type: 'value', targetId: 'a', before: 'a', after: 'ab', coalesceKey: 'prop:a:title' });
  history.record({ type: 'value', targetId: 'a', before: 'ab', after: 'abc', coalesceKey: 'prop:a:title' });
  assert.equal(history.undoStack.length, 1);
  assert.equal(history.undoStack[0].before, 'a');
  assert.equal(history.undoStack[0].after, 'abc');
});

test('drop geometry projects horizontal before/after naturally in RTL and LTR', () => {
  const rect = { left: 0, right: 100, top: 0, bottom: 40, width: 100, height: 40 };
  assert.equal(CanvasDragDrop.resolve(rect, 10, 5, 'ltr', false, true), 'before');
  assert.equal(CanvasDragDrop.resolve(rect, 90, 5, 'ltr', false, true), 'after');
  assert.equal(CanvasDragDrop.resolve(rect, 90, 5, 'rtl', false, true), 'before');
  assert.equal(CanvasDragDrop.resolve(rect, 10, 5, 'rtl', false, true), 'after');
  assert.equal(CanvasDragDrop.resolve(rect, 50, 20, 'rtl', true, false), 'inside');
});

test('navigator exposes hierarchy and human-readable labels', () => {
  const navigator = new CanvasNavigator();
  const flat = navigator.flatten(fixture(), {});
  assert.deepEqual(Array.from(flat, n => n.depth), [0, 1, 2, 2, 1]);
  assert.equal(navigator.label(fixture()[0].children[0].children[0], { heading: 'Heading' }), 'Heading');
});

test('document selection is id-primary and derives its path after reorder', () => {
  const blocks = fixture();
  const state = new CanvasDocumentState(blocks);
  state.select('a');
  assert.deepEqual(Array.from(state.path(blocks)), [0, 0, 0]);

  new CanvasTreeOperations(schemas).moveNode(blocks, 'a', 'c1', 2);

  assert.equal(state.reconcile(blocks), 'a');
  assert.deepEqual(Array.from(state.path(blocks)), [0, 0, 1]);
  new CanvasTreeOperations(schemas).deleteNode(blocks, 'a');
  assert.equal(state.reconcile(blocks), null);
  assert.equal(state.selectedNodeId, null);
});

test('structural services are DOM-free and every host page includes the identity contract first', () => {
  // Phase 6.1-A: the services partial is pure logic — no DOM access.
  assert.equal(/document\./.test(source), false);

  // Every view that loads canvas-structural-services must load
  // theme-runtime::canvas.identity before it. canvas-live-preview.blade.php
  // shipped without the include and threw 'CanvasIdentity contract is
  // required' at runtime; this pins the include-order dependency.
  //
  // Phase 6.1-B: hosts may instead include the package's own
  // canvas.editor-bootstrap, which loads runtime -> identity -> host
  // structural services in the contract-mandated order, so either marker
  // before the services include satisfies the dependency.
  const viewsDir = fileURLToPath(new URL('../../resources/views/admin/theme-builder/', import.meta.url));
  const stack = [viewsDir];
  const offending = [];
  while (stack.length) {
    const dir = stack.pop();
    for (const name of fs.readdirSync(dir)) {
      const full = path.join(dir, name);
      if (fs.statSync(full).isDirectory()) { stack.push(full); continue; }
      if (!name.endsWith('.blade.php')) continue;
      const content = fs.readFileSync(full, 'utf8');
      if (!content.includes('canvas-structural-services')) continue;
      const identityPos = content.indexOf('theme-runtime::canvas.identity');
      const bootstrapPos = content.indexOf('theme-runtime::canvas.editor-bootstrap');
      const servicesPos = content.indexOf('canvas-structural-services');
      if (bootstrapPos !== -1 && bootstrapPos < servicesPos) continue;
      if (identityPos === -1 || identityPos > servicesPos) offending.push(full);
    }
  }
  assert.deepEqual(offending, []);
});

// ---------------------------------------------------------------------------
// Canvas tree-limit resolution contract
//
// Priority: (1) explicit constructor options, (2) window.CANVAS_TREE_LIMITS
// emitted by the server (the PHP DocumentValidator constants), (3) fail-safe
// literals (maxDepth: 12, maxNodes: 5000) only when runtime config is missing
// or invalid.
//
// The vm context's `window` is mutable, and CanvasTreeOperations reads
// CANVAS_TREE_LIMITS at construction time — so each test sets the value
// before constructing and deletes it afterwards to avoid test bleed.
// ---------------------------------------------------------------------------

const setLimits = (limits) => {
    if (limits === undefined) delete context.window.CANVAS_TREE_LIMITS;
    else context.window.CANVAS_TREE_LIMITS = limits;
};

test('tree limits: explicit constructor options override server values', () => {
    setLimits({ maxDepth: 5, maxNodes: 100 });
    const ops = new CanvasTreeOperations(schemas, { maxDepth: 8, maxNodes: 200 });
    assert.equal(ops.maxDepth, 8);
    assert.equal(ops.maxNodes, 200);
    setLimits();
});

test('tree limits: server values are used when explicit options are absent', () => {
    setLimits({ maxDepth: 7, maxNodes: 300 });
    const ops = new CanvasTreeOperations(schemas);
    assert.equal(ops.maxDepth, 7);
    assert.equal(ops.maxNodes, 300);
    setLimits();
});

test('tree limits: missing server values fall through to fail-safe literals', () => {
    setLimits();
    const ops = new CanvasTreeOperations(schemas);
    assert.equal(ops.maxDepth, 12);
    assert.equal(ops.maxNodes, 5000);
});

test('tree limits: numeric-string server values are normalized to positive integers', () => {
    setLimits({ maxDepth: '10', maxNodes: '500' });
    const ops = new CanvasTreeOperations(schemas);
    assert.equal(ops.maxDepth, 10);
    assert.equal(ops.maxNodes, 500);
    setLimits();
});

test('tree limits: non-integer and non-numeric server values do not become active limits', () => {
    const invalid = [0, -1, NaN, 'abc', 1.5, null, undefined, '', '1.5', -3];
    for (const v of invalid) {
        setLimits({ maxDepth: v, maxNodes: v });
        const ops = new CanvasTreeOperations(schemas);
        assert.equal(ops.maxDepth, 12, `maxDepth fail-safe for value: ${String(v)}`);
        assert.equal(ops.maxNodes, 5000, `maxNodes fail-safe for value: ${String(v)}`);
    }
    setLimits();
});

test('tree limits: non-object CANVAS_TREE_LIMITS falls through to fail-safe', () => {
    setLimits('not-an-object');
    const ops = new CanvasTreeOperations(schemas);
    assert.equal(ops.maxDepth, 12);
    assert.equal(ops.maxNodes, 5000);

    setLimits(null);
    const ops2 = new CanvasTreeOperations(schemas);
    assert.equal(ops2.maxDepth, 12);
    assert.equal(ops2.maxNodes, 5000);

    setLimits(42);
    const ops3 = new CanvasTreeOperations(schemas);
    assert.equal(ops3.maxDepth, 12);
    assert.equal(ops3.maxNodes, 5000);
    setLimits();
});

test('tree limits: invalid explicit options fall through to server values', () => {
    setLimits({ maxDepth: 7, maxNodes: 300 });
    const ops = new CanvasTreeOperations(schemas, { maxDepth: -5, maxNodes: 'abc' });
    assert.equal(ops.maxDepth, 7);
    assert.equal(ops.maxNodes, 300);
    setLimits();
});

test('tree limits: resolved limits are enforced by assertPlacement (existing behavior unchanged)', () => {
    const ops = new CanvasTreeOperations(schemas, { maxDepth: 3, maxNodes: 5 });
    assert.equal(ops.maxDepth, 3);
    assert.equal(ops.maxNodes, 5);

    const deep = [
        { id: 'c1', type: 'container', children: [
            { id: 'c2', type: 'container', children: [
                { id: 'c3', type: 'container', children: [] }
            ]}
        ]}
    ];
    // c3 is at depth 3; inserting a child yields 3+1+0 = 4 > 3 -> max-depth
    assert.throws(() => ops.insertNode(deep, { id: 'too-deep', type: 'heading' }, 'c3', 0), /max-depth/);
    // c2 is at depth 2; 2+1+0 = 3 is not > 3 -> succeeds
    ops.insertNode(deep, { id: 'ok', type: 'heading' }, 'c2', 0);

    // maxNodes: three sections, then two more reach the cap of 5
    const flat = [
        { id: 'a', type: 'section' },
        { id: 'b', type: 'section' },
        { id: 'c', type: 'section' },
    ];
    ops.insertNode(flat, { id: 'd', type: 'section' }, 'page', null);
    ops.insertNode(flat, { id: 'e', type: 'section' }, 'page', null);
    // five nodes; one more would be 6 > 5 -> max-nodes
    assert.throws(() => ops.insertNode(flat, { id: 'too-many', type: 'section' }, 'page', null), /max-nodes/);
});
