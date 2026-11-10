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
const { CanvasDocumentState, CanvasTreeOperations, CanvasHistory, CanvasNavigator, CanvasDragDrop, CanvasSaveQueue, CanvasListPath } = context.window;

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

test('history honours a wider per-entry coalesce window', () => {
  const realNow = Date.now;
  let now = 1_000_000;
  Date.now = () => now;
  try {
    const history = new CanvasHistory(() => {});
    const record = () => history.record({
      type: 'value', targetId: 'a', path: ['props', 'columns'],
      before: ['old'], after: ['new'], coalesceKey: 'column-widths:a:0', coalesceWindow: 5000,
    });
    record();
    now += 4000;
    record();
    assert.equal(history.undoStack.length, 1, 'a pause inside the window still merges');
    now += 4000;
    record();
    assert.equal(history.undoStack.length, 1, 'the window slides with the latest edit');
    now += 6000;
    record();
    assert.equal(history.undoStack.length, 2, 'a new interaction after the window is its own step');
    // Entries without an explicit window keep the 700ms default.
    now += 1000;
    history.record({ type: 'value', targetId: 'b', before: 'x', after: 'y', coalesceKey: 'prop:b:title' });
    now += 800;
    history.record({ type: 'value', targetId: 'b', before: 'y', after: 'z', coalesceKey: 'prop:b:title' });
    assert.equal(history.undoStack.length, 4);
  } finally {
    Date.now = realNow;
  }
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

// ── P1: single serial save lane ─────────────────────────────────────────

const deferred = () => {
  let resolve, reject;
  const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
  return { promise, resolve, reject };
};
const tick = () => new Promise(r => setTimeout(r, 0));

test('save queue keeps one request on the wire and coalesces requests made during it', async () => {
  const runs = [];
  let live = 'v1';
  const queue = new CanvasSaveQueue(() => {
    const d = deferred();
    runs.push({ captured: live, d });
    return d.promise;
  });

  const first = queue.request();
  await tick();
  assert.equal(runs.length, 1);
  assert.equal(queue.busy, true);
  assert.equal(queue.waiting, false);

  live = 'v2';                       // edit made while the first save is in flight
  const second = queue.request();
  live = 'v3';                       // another edit before the follow-up starts
  const third = queue.request();
  assert.equal(second, third, 'requests made during a save share one follow-up');
  assert.equal(queue.waiting, true);
  await tick();
  assert.equal(runs.length, 1, 'the follow-up never overlaps the in-flight save');

  runs[0].d.resolve({ ok: true, n: 1 });
  assert.deepEqual(await first, { ok: true, n: 1 });
  await tick();
  assert.equal(runs.length, 2);
  assert.equal(runs[1].captured, 'v3', 'the follow-up captures every edit made before it started');

  runs[1].d.resolve({ ok: true, n: 2 });
  assert.deepEqual(await second, { ok: true, n: 2 });
  await tick();
  assert.equal(queue.busy, false);
  assert.equal(runs.length, 2);
});

test('a failed save never stalls the queue', async () => {
  const runs = [];
  const queue = new CanvasSaveQueue(() => { const d = deferred(); runs.push(d); return d.promise; });

  const first = queue.request();
  await tick();
  const second = queue.request();
  runs[0].reject(new Error('network'));
  await assert.rejects(first, /network/);
  await tick();
  assert.equal(runs.length, 2);
  runs[1].resolve({ ok: true });
  assert.deepEqual(await second, { ok: true });
  assert.equal(queue.busy, false);
});

test('a save acknowledges only the snapshot and operations it sent', () => {
  const blocks = fixture();
  const state = new CanvasDocumentState(blocks);
  const ops = new CanvasTreeOperations(schemas);
  state.queue(ops.insertNode(blocks, { id: 'x', type: 'heading' }, 'c2', 0));
  const sent = [...state.pendingOperations];
  const snapshot = JSON.parse(JSON.stringify(blocks));

  state.queue(ops.insertNode(blocks, { id: 'y', type: 'heading' }, 'c2', 1)); // mid-flight edit
  state.markSavedSnapshot(snapshot, sent, blocks);

  assert.equal(state.pendingOperations.length, 1);
  assert.equal(state.pendingOperations[0].node_id, 'y', 'the mid-flight operation stays pending');
  assert.deepEqual(state.saved[0].children[1].children.map(n => n.id), ['x'], 'saved base is what was sent, not the live tree');
});

// ── Phase 0.2b: nested repeater addressing ─────────────────────────────
// Values built inside the vm realm carry its prototypes; compare plain data.
const plain = (v) => JSON.parse(JSON.stringify(v));
const plansDef = { type: 'repeater', storage: 'array', fields: {
  name: { type: 'string' },
  features: { type: 'repeater', fields: { text: { type: 'string' } } },
  legacy: { type: 'repeater', storage: 'json', fields: { label: { type: 'string' } } },
} };
const megaDef = { type: 'repeater', fields: {
  label: { type: 'string' },
  mega_columns: { type: 'repeater', fields: { title: { type: 'string' }, items: { type: 'repeater', fields: { label: { type: 'string' }, url: { type: 'link' } } } } },
} };

test('list path reads nested lists, legacy JSON strings and scalar items', () => {
  const plans = JSON.stringify([{ name: 'A', features: ['one', 'two'] }, { name: 'B', features: [{ _id: 'f1', text: 'x' }], legacy: '[{"label":"L"}]' }]);
  assert.deepEqual(plain(CanvasListPath.itemsAt(plans, plansDef, []).map(p => p.name)), ['A', 'B']);
  assert.deepEqual(plain(CanvasListPath.itemsAt(plans, plansDef, [0, 'features'])), [{ text: 'one' }, { text: 'two' }], 'scalars are presented as {firstField}');
  assert.deepEqual(plain(CanvasListPath.itemsAt(plans, plansDef, [1, 'legacy'])), [{ label: 'L' }]);
  assert.deepEqual(plain(CanvasListPath.itemsAt(plans, plansDef, [9, 'features'])), [], 'a stale index reads as empty');
  assert.equal(CanvasListPath.defAt(megaDef, [0, 'mega_columns', 2, 'items']).fields.url.type, 'link');
});

test('list path writes copy only the ancestors and keep every sibling untouched', () => {
  const columns = [{ _id: 'c1', title: 'Shop', items: [{ _id: 'i1', label: 'Tea', url: '/tea' }], extra: { keep: true } }, { _id: 'c2', title: 'Help', items: [] }];
  const root = [{ _id: 'n1', label: 'Menu', mega_columns: columns, unknown: 'kept' }, { _id: 'n2', label: 'Other' }];
  const next = CanvasListPath.replaceAt(root, megaDef, [0, 'mega_columns', 0, 'items'], [{ _id: 'i1', label: 'Tea', url: '/tea' }, { _id: 'i2', label: 'Coffee', url: '/coffee' }]);
  assert.notEqual(next, root);
  assert.equal(next[1], root[1], 'a sibling branch keeps its identity');
  assert.equal(next[0].mega_columns[1], columns[1], 'a sibling column keeps its identity');
  assert.equal(next[0].unknown, 'kept');
  assert.deepEqual(plain(next[0].mega_columns[0].extra), { keep: true });
  assert.deepEqual(plain(next[0].mega_columns[0].items.map(i => i.label)), ['Tea', 'Coffee']);
  assert.equal(root[0].mega_columns[0].items.length, 1, 'the input is never mutated');
  assert.equal(CanvasListPath.replaceAt(root, megaDef, [5, 'mega_columns'], []), null, 'a stale path is refused, not guessed');
});

test('list path keeps a nested json-storage list a JSON string', () => {
  const root = [{ name: 'B', legacy: '[{"label":"L"}]' }];
  const next = CanvasListPath.replaceAt(root, plansDef, [0, 'legacy'], [{ label: 'L' }, { label: 'M' }]);
  assert.equal(typeof next[0].legacy, 'string');
  assert.deepEqual(JSON.parse(next[0].legacy).map(x => x.label), ['L', 'M']);
});

test('duplicating an item regenerates every nested identity', () => {
  let n = 0;
  const copy = CanvasListPath.cloneWithFreshIds({ _id: 'p', features: [{ _id: 'a', text: 'x' }, { text: 'legacy' }], link: { url: '/x' } }, () => `new${++n}`);
  assert.equal(copy._id, 'new2');
  assert.deepEqual(plain(copy.features), [{ _id: 'new1', text: 'x' }, { text: 'legacy' }], 'legacy items are not given ids');
  assert.deepEqual(plain(copy.link), { url: '/x' });
});
