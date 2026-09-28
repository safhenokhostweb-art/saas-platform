import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-identity.js', import.meta.url), 'utf8');
const context = vm.createContext({ window: {}, globalThis: {}, module: { exports: {} }, Set, Object, Array });
vm.runInContext(source, context);
const identity = context.window.CanvasIdentity;

const fixture = () => [
  {
    id: 'section',
    type: 'section',
    children: [
      {
        id: 'row',
        type: 'row',
        children: [
          { id: 'heading', type: 'heading', props: { text: 'Hello' } },
          { id: 'image', type: 'image', props: { src: 'image.jpg' } },
        ],
      },
    ],
  },
  { id: 'footer-note', type: 'text' },
];

test('identity validation accepts stable ids and reports invalid and duplicate ids', () => {
  assert.equal(identity.isValidNodeId('heading'), true);
  assert.equal(identity.isValidNodeId('   '), false);
  assert.equal(identity.validateTreeIdentity(fixture()).valid, true);

  const invalid = [{ id: '', type: 'text' }, { id: 'same' }, { id: 'same' }];
  const result = identity.validateTreeIdentity(invalid);
  assert.equal(result.valid, false);
  assert.deepEqual(Array.from(result.invalid[0]), [0]);
  assert.deepEqual(Array.from(result.duplicates), ['same']);
});

test('lookup and parent traversal work at deep nesting and on empty trees', () => {
  const tree = fixture();
  assert.equal(identity.findById(tree, 'heading')?.props.text, 'Hello');
  assert.equal(identity.parentForId(tree, 'heading')?.id, 'row');
  assert.equal(identity.parentForId(tree, 'section'), null);
  assert.equal(identity.findById([], 'missing'), null);
  assert.equal(identity.pathForId([], 'missing'), null);
  assert.equal(identity.nodeAtPath([], [0]), null);
  assert.deepEqual(Array.from(identity.flatten([])), []);
});

test('pathForId and nodeAtPath are inverse for every node', () => {
  const tree = fixture();
  for (const entry of identity.flatten(tree)) {
    const path = identity.pathForId(tree, entry.id);
    assert.deepEqual(Array.from(path), Array.from(entry.path));
    assert.equal(identity.nodeAtPath(tree, path), entry.node);
  }
});

test('flatten exposes the complete hierarchy without changing legacy nodes', () => {
  const tree = fixture();
  const flat = identity.flatten(tree);
  assert.deepEqual(Array.from(flat, entry => entry.id), ['section', 'row', 'heading', 'image', 'footer-note']);
  assert.deepEqual(Array.from(flat, entry => entry.parentId), ['page', 'section', 'row', 'row', 'page']);
  assert.deepEqual(Array.from(flat, entry => entry.depth), [0, 1, 2, 2, 0]);
  assert.deepEqual(Array.from(flat[2].path), [0, 0, 0]);
  assert.equal(Object.hasOwn(tree[0], 'kind'), false);
});

test('selection reconciliation preserves ids across sibling reorder and rejects missing ids', () => {
  const tree = fixture();
  const siblings = tree[0].children[0].children;
  assert.equal(identity.reconcileSelection(tree, 'heading'), 'heading');

  [siblings[0], siblings[1]] = [siblings[1], siblings[0]];

  assert.equal(identity.reconcileSelection(tree, 'heading'), 'heading');
  assert.deepEqual(Array.from(identity.pathForId(tree, 'heading')), [0, 0, 1]);
  assert.equal(identity.reconcileSelection(tree, 'deleted'), null);
});
