import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// WU-2: the Design HUD's pure helpers (theme-runtime::canvas.inspect loads
// the file in both editors and the catalog). Without a document the file
// only exports these.
const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-audit.js', import.meta.url), 'utf8');
const context = vm.createContext({ Math, JSON, Object, String, Number, parseInt, parseFloat });
context.globalThis = context;
vm.runInContext(source, context);
const A = context.TbAudit;

test('colours parse from computed styles and hex', () => {
  assert.deepEqual({ ...A.parseColor('rgb(15, 23, 42)') }, { r: 15, g: 23, b: 42, a: 1 });
  assert.deepEqual({ ...A.parseColor('rgba(255, 255, 255, 0.5)') }, { r: 255, g: 255, b: 255, a: 0.5 });
  assert.deepEqual({ ...A.parseColor('rgb(0 0 0 / 25%)') }, { r: 0, g: 0, b: 0, a: 0.25 });
  assert.deepEqual({ ...A.parseColor('#4f46e5') }, { r: 79, g: 70, b: 229, a: 1 });
  assert.deepEqual({ ...A.parseColor('#fff') }, { r: 255, g: 255, b: 255, a: 1 });
  assert.equal(A.parseColor('transparent'), null);
  assert.equal(A.parseColor('color(display-p3 1 0 0)'), null);
});

test('contrast follows WCAG and composites translucent text over its background', () => {
  const white = { r: 255, g: 255, b: 255, a: 1 };
  const black = { r: 0, g: 0, b: 0, a: 1 };
  assert.equal(A.contrastRatio(black, white).toFixed(2), '21.00');
  assert.equal(A.contrastRatio(white, white).toFixed(2), '1.00');
  assert.equal(A.contrastRatio({ r: 118, g: 118, b: 118, a: 1 }, white).toFixed(2), '4.54', '#767676 is the lightest AA grey on white');
  const faded = A.contrastRatio({ r: 0, g: 0, b: 0, a: 0.3 }, white);
  assert.ok(faded > 1 && faded < 3, `30% black on white is low contrast (${faded.toFixed(2)})`);
});

test('large text is 24 px, or 18.66 px bold', () => {
  assert.equal(A.isLargeText(24, '400'), true);
  assert.equal(A.isLargeText(19, '700'), true);
  assert.equal(A.isLargeText(19, '600'), false);
  assert.equal(A.isLargeText(16, '800'), false);
});

test('small targets fail only when crowded (WCAG 2.2 SC 2.5.8 spacing exception)', () => {
  const r = (left, top, width, height) => ({ left, top, width, height, right: left + width, bottom: top + height });
  // Two 20x20 dots 2 px apart (centres 22 px): crowded.
  assert.deepEqual([...A.crowded([r(0, 0, 20, 20), r(22, 0, 20, 20)])], [0, 1]);
  // 4 px apart puts the centres exactly 24 px apart: the circles only touch, which passes.
  assert.deepEqual([...A.crowded([r(0, 0, 20, 20), r(24, 0, 20, 20)])], []);
  // The same dots 30 px apart (centres 50 px): fine.
  assert.deepEqual([...A.crowded([r(0, 0, 20, 20), r(50, 0, 20, 20)])], []);
  // A 20x20 dot 1 px from a 44x44 button: its 24 px circle overlaps the button (the button is not small).
  assert.deepEqual([...A.crowded([r(0, 0, 44, 44), r(45, 12, 20, 20)])], [1]);
  // A small icon inside its own link is not crowded by it.
  assert.deepEqual([...A.crowded([r(0, 0, 40, 40), r(10, 10, 16, 16)], (i, j) => (i === 0 && j === 1) || (i === 1 && j === 0))], []);
});

test('the traffic light: any blocker is red, any warning yellow, info alone stays green', () => {
  assert.equal(A.tone([]), 'ok');
  assert.equal(A.tone([{ severity: 'info' }]), 'ok');
  assert.equal(A.tone([{ severity: 'info' }, { severity: 'warning' }]), 'warn');
  assert.equal(A.tone([{ severity: 'warning' }, { severity: 'blocker' }]), 'error');
});
