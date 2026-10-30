import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Phase 0.7 (ADR-066): the pure helpers of the live newsletter + search
// script. Without a document the script only exposes window.TbSiteForms.
const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/blocks/site-forms.js', import.meta.url), 'utf8');
const context = vm.createContext({ window: {}, URL, Number, Math, String, Array, Object, setTimeout, clearTimeout });
vm.runInContext(source, context);
const F = context.window.TbSiteForms;
const plain = (v) => JSON.parse(JSON.stringify(v));

test('contacts normalise like the server: email lower-cased, mobile to 09…', () => {
  assert.deepEqual(plain(F.normalizeContact('  Ali@Example.COM ')), { channel: 'email', value: 'ali@example.com' });
  assert.deepEqual(plain(F.normalizeContact('۰۹۱۲ ۳۴۵ ۶۷۸۹')), { channel: 'mobile', value: '09123456789' });
  assert.deepEqual(plain(F.normalizeContact('+98 912-345-6789')), { channel: 'mobile', value: '09123456789' });
  assert.deepEqual(plain(F.normalizeContact('٩١٢٣٤٥٦٧٨٩')), { channel: 'mobile', value: '09123456789' });
  for (const bad of ['', '   ', 'ali@', 'ali@example', '0812345678', '091234567890', 'سلام']) {
    assert.equal(F.normalizeContact(bad), null, bad);
  }
});

test('every response maps to a Persian message', () => {
  assert.equal(F.newsletterMessage(201, { ok: true, status: 'subscribed', message: 'ثبت شد' }).text, 'ثبت شد');
  assert.equal(F.newsletterMessage(200, { ok: true, status: 'already' }).text, F.MESSAGES.already);
  assert.equal(F.newsletterMessage(200, { ok: true, status: 'already' }).already, true);
  assert.equal(F.newsletterMessage(422, { errors: { contact: ['نامعتبر'] } }).text, 'نامعتبر');
  assert.equal(F.newsletterMessage(422, {}).text, F.MESSAGES.invalid);
  assert.equal(F.newsletterMessage(429, {}).text, F.MESSAGES.throttled);
  assert.equal(F.newsletterMessage(419, {}).text, F.MESSAGES.expired);
  assert.equal(F.newsletterMessage(0, null).text, F.MESSAGES.network);
  assert.equal(F.newsletterMessage(500, {}).ok, false);
  for (const m of Object.values(F.MESSAGES)) assert.match(m, /[؀-ۿ]/);
});

test('prices print as Persian Toman with the Persian thousands separator', () => {
  assert.equal(F.formatToman(1250000), '۱٬۲۵۰٬۰۰۰ تومان');
  assert.equal(F.formatToman('890000'), '۸۹۰٬۰۰۰ تومان');
  assert.equal(F.formatToman(0), '');
  assert.equal(F.formatToman('x'), '');
});

test('suggestions are filtered by scope and to safe URLs', () => {
  const items = [
    { type: 'product', title: 'تشک', url: '/shop/a', price: 100 },
    { type: 'post', title: 'راهنما', url: 'https://x.test/blog/b' },
    { type: 'post', title: 'بد', url: 'javascript:alert(1)' },
    { type: 'product', title: 42, url: '/shop/c' },
    null,
  ];
  assert.deepEqual(plain(F.filterSuggestions(items, 'all')).map((i) => i.title), ['تشک', 'راهنما']);
  assert.deepEqual(plain(F.filterSuggestions(items, 'products')).map((i) => i.title), ['تشک']);
  assert.deepEqual(plain(F.filterSuggestions(items, 'posts')).map((i) => i.title), ['راهنما']);
  assert.equal(F.filterSuggestions(null, 'all').length, 0);
});

test('the results URL carries the query and the scope parameters', () => {
  const base = 'https://shop.test/';
  assert.equal(F.resultsUrl({ action: '/search', params: { type: 'products' }, param: 'q' }, ' تشک طبی ', base),
    'https://shop.test/search?type=products&q=%D8%AA%D8%B4%DA%A9+%D8%B7%D8%A8%DB%8C');
  assert.equal(F.resultsUrl({ action: '/shop' }, 'a', base), 'https://shop.test/shop?q=a');
  assert.equal(F.resultsUrl(null, 'a', base), '');
});

test('debounce runs once after the last call', async () => {
  let calls = 0;
  const d = F.debounce(() => { calls++; }, 20);
  d(); d(); d();
  await new Promise((r) => setTimeout(r, 60));
  assert.equal(calls, 1);
  d(); d.cancel();
  await new Promise((r) => setTimeout(r, 40));
  assert.equal(calls, 1);
});
