import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// WU-3: the «سلامت ویجت» tab's pure helpers (theme-runtime::canvas.health
// loads the file in both editors).
const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-health.js', import.meta.url), 'utf8');
const context = vm.createContext({ Math, JSON, Object, String, Number, Promise });
context.globalThis = context;
vm.runInContext(source, context);
const H = context.TbHealth;

const f = (code, severity) => ({ code, severity, message: code, selector: '', remedy: '' });
const summary = {
  width: 390,
  tone: 'error',
  counts: { blocker: 1, warning: 3, info: 1 },
  widgets: [
    { key: 'heading#a', type: 'heading', blockId: 'a', tone: 'ok', findings: [f('TAP_TARGET_COMFORT', 'info')] },
    { key: 'slider#b', type: 'slider', blockId: 'b', tone: 'warn', findings: [f('TAP_TARGET_SMALL', 'warning'), f('TAP_TARGET_SMALL', 'warning')] },
    { key: 'nav_header#c', type: 'nav_header', blockId: 'c', tone: 'error', findings: [f('WIDGET_OVERFLOW', 'blocker'), f('DEAD_LINK', 'warning')] },
    { key: 'text#d', type: 'text', blockId: 'd', tone: 'ok', findings: [] },
  ],
};

test('rows: red, then amber, then green; page order inside a tone; labels from the host', () => {
  const rows = H.rows(summary, (type) => ({ nav_header: 'هدر سایت', slider: 'اسلایدر' })[type]);
  assert.deepEqual(rows.map((r) => r.key), ['nav_header#c', 'slider#b', 'heading#a', 'text#d']);
  assert.equal(rows[0].label, 'هدر سایت');
  assert.equal(rows[2].label, 'heading', 'no host label falls back to the official type');
  assert.deepEqual([rows[0].blockers, rows[0].warnings, rows[0].infos], [1, 1, 0]);
  assert.deepEqual([rows[2].blockers, rows[2].warnings, rows[2].infos], [0, 0, 1]);
});

test('rows survive a missing summary and a throwing label lookup', () => {
  assert.equal(H.rows(null).length, 0);
  assert.equal(H.rows({ widgets: [{ key: 'x#1', type: 'x', tone: 'ok' }] }, () => { throw new Error('no'); })[0].label, 'x');
});

test('badges count blockers and warnings only; info stays green', () => {
  const rows = H.rows(summary);
  assert.equal(H.badge(rows[0]), '1 خطا · 1 هشدار');
  assert.equal(H.badge(rows[1]), '2 هشدار');
  assert.equal(H.badge(rows[2]), 'سالم');
});

test('headline', () => {
  assert.equal(H.headline(summary), '2 از 4 ویجت ایراد دارد');
  assert.equal(H.headline({ widgets: [summary.widgets[3]] }), 'هر 1 ویجت سالم است');
  assert.equal(H.headline({ widgets: [] }), 'ویجتی برای بررسی نیست');
  assert.equal(H.headline(null), '');
});

test('no external origin (CSP) and one Alpine factory', () => {
  assert.equal(source.includes('https://'), false);
  assert.equal(typeof context.tbHealthPanel, 'function');
  const panel = context.tbHealthPanel();
  assert.equal(panel.active, false);
  panel.setActive(true);
  assert.equal(panel.active, false, 'without an editor host the panel stays off');
  assert.match(panel.note, /آماده نیست/);
});
