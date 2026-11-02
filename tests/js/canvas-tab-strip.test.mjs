import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// ADR-076: the Canvas page-tab strip's pure geometry (both editors load the
// same file through theme-runtime::canvas.tab-strip).
const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-tab-strip.js', import.meta.url), 'utf8');
const context = vm.createContext({ Math });
context.globalThis = context;
vm.runInContext(source, context);
const S = context.TbTabStrip;

test('no overflow hides both chevrons', () => {
  assert.deepEqual({ ...S.edges(0, 400, 400, true) }, { overflow: false, start: false, end: false });
  assert.deepEqual({ ...S.edges(0, 401, 400, false) }, { overflow: false, start: false, end: false }, 'sub-pixel rounding is not overflow');
});

test('RTL: scrollLeft runs from 0 at the start to negative at the end', () => {
  assert.deepEqual({ ...S.edges(0, 1000, 400, true) }, { overflow: true, start: false, end: true });
  assert.deepEqual({ ...S.edges(-300, 1000, 400, true) }, { overflow: true, start: true, end: true });
  assert.deepEqual({ ...S.edges(-600, 1000, 400, true) }, { overflow: true, start: true, end: false });
});

test('LTR mirrors RTL', () => {
  assert.deepEqual({ ...S.edges(0, 1000, 400, false) }, { overflow: true, start: false, end: true });
  assert.deepEqual({ ...S.edges(600, 1000, 400, false) }, { overflow: true, start: true, end: false });
});

test('a chevron press moves most of a viewport toward its edge', () => {
  assert.equal(S.step('end', 500, true), -400, 'RTL end is leftward');
  assert.equal(S.step('start', 500, true), 400);
  assert.equal(S.step('end', 500, false), 400);
  assert.equal(S.step('end', 60, false), 120, 'never less than 120px');
});

test('revealDelta scrolls only as far as needed to show the active tab', () => {
  assert.equal(S.revealDelta(100, 180, 0, 400, 36), 0, 'already visible');
  assert.equal(S.revealDelta(-50, 30, 0, 400, 36), -86, 'clipped on the left');
  assert.equal(S.revealDelta(380, 460, 0, 400, 36), 96, 'clipped on the right');
  assert.equal(S.revealDelta(10, 500, 0, 400, 36), -26, 'a tab wider than the strip aligns its left edge');
});

test('the module exposes a versioned API and mounts nothing without a document', () => {
  assert.equal(S.version, 1);
  assert.equal(typeof S.mount, 'function');
  assert.equal(S.mount(null), null);
});
