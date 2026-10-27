import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Phase 0.6 (ADR-065): the Canvas icon picker's model, loaded with the real
// catalog the PHP renderer uses.
const base = new URL('../../packages/saas-platform/modules/theme-runtime/resources/', import.meta.url);
const source = fs.readFileSync(new URL('js/canvas-icons.js', base), 'utf8');
const catalog = JSON.parse(fs.readFileSync(new URL('icons/catalog.json', base), 'utf8'));
const context = vm.createContext({ window: {}, Object, String, Array, Math });
vm.runInContext(source, context);
const I = context.window.CanvasIcons.load(catalog);
const names = (list) => list.map((i) => i.name);

test('the five picker categories are in catalog order with real counts', () => {
  const cats = I.categories();
  assert.deepEqual(cats.map((c) => c.key), ['commerce', 'navigation', 'communication', 'tech', 'ui']);
  assert.equal(cats.reduce((n, c) => n + c.count, 0), Object.keys(catalog.icons).length);
  assert.ok(cats.every((c) => c.count >= 10 && /[؀-ۿ]/.test(c.label)));
});

test('a value is an icon name, a legacy emoji/text, or empty', () => {
  assert.equal(I.kind('truck'), 'icon');
  assert.equal(I.kind('  Truck '), 'icon');
  assert.equal(I.kind('🚚'), 'legacy');
  assert.equal(I.kind('★'), 'legacy');
  assert.equal(I.kind('not-an-icon'), 'legacy');
  assert.equal(I.kind(''), 'empty');
  assert.equal(I.kind(null), 'empty');
  assert.equal(I.kind('constructor'), 'legacy', 'prototype keys are not icons');
  assert.equal(I.name(' Truck '), 'truck');
});

test('search matches Persian labels and keywords, Arabic letter forms and English names', () => {
  assert.equal(I.search('ارسال')[0].name, 'truck');
  assert.ok(names(I.search('تلفن')).includes('phone'));
  assert.ok(names(I.search('امنيت')).includes('lock'), 'Arabic ي finds Persian ی');
  assert.ok(names(I.search('cart')).includes('shopping-cart'));
  assert.ok(names(I.search('shopping cart')).includes('shopping-cart'));
  assert.ok(names(I.search('سبد خرید')).includes('shopping-cart'));
  assert.equal(I.search('xyzzy-nothing').length, 0);
});

test('exact matches rank before prefix and substring matches', () => {
  const r = names(I.search('star'));
  assert.equal(r[0], 'star');
  assert.ok(names(I.search('پشتیبانی')).indexOf('headset') < 2);
});

test('an empty query lists a category in catalog order; a category filters the search', () => {
  const commerce = I.search('', 'commerce');
  assert.equal(commerce[0].name, Object.keys(catalog.icons).find((n) => catalog.icons[n].category === 'commerce'));
  assert.ok(commerce.every((i) => i.category === 'commerce'));
  assert.equal(I.search('').length, Object.keys(catalog.icons).length);
  assert.ok(names(I.search('ارسال', 'tech')).every((n) => catalog.icons[n].category === 'tech'));
});

test('svg() draws only catalog icons with the renderer markup', () => {
  const svg = I.svg('truck');
  assert.match(svg, /^<svg class="tb-icon-svg" [^>]*viewBox="0 0 24 24"[^>]*stroke="currentColor"[^>]*aria-hidden="true">/);
  assert.ok(svg.endsWith('</svg>'));
  assert.ok(svg.includes(catalog.icons.truck.body));
  assert.equal(I.svg('🚚'), '');
  assert.equal(I.svg('<script>'), '');
});

test('previewHtml escapes legacy values and describe() speaks Persian', () => {
  assert.equal(I.previewHtml('<img src=x onerror=alert(1)>'), '<span class="tbip-legacy">&lt;img src=x onerror=alert(1)&gt;</span>');
  assert.equal(I.previewHtml('🚚'), '<span class="tbip-legacy">🚚</span>');
  assert.equal(I.previewHtml(''), '');
  assert.equal(I.describe('truck'), 'ارسال');
  assert.equal(I.describe('🚚'), 'ایموجی/متن (قدیمی)');
  assert.equal(I.describe(''), 'بدون آیکون');
});
