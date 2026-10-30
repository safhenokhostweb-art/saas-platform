import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Phases 1/2 (ADR-067): the pure helpers of the header and cart scripts.
// Without a document both scripts only expose their helpers.
const base = new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/blocks/', import.meta.url);
function load(file) {
  const context = vm.createContext({ window: {}, Number, Math, String, Array, Object, JSON, parseFloat });
  vm.runInContext(fs.readFileSync(new URL(file, base), 'utf8'), context);
  return context.window;
}
const H = load('site-header.js').TbSiteHeader;
const C = load('cart.js').TbCart;
const plain = (v) => JSON.parse(JSON.stringify(v));

test('luminance reads rgb()/rgba() and treats transparent as unknown', () => {
  assert.equal(H.luminance('rgb(255, 255, 255)'), 1);
  assert.equal(H.luminance('rgb(0, 0, 0)'), 0);
  assert.equal(H.luminance('rgba(0, 0, 0, 0)'), null);
  assert.equal(H.luminance('transparent'), null);
  assert.ok(H.luminance('rgb(79, 70, 229)') < 0.2);
});

test('auto overlay text: light on dark or photo heroes, dark on light ones', () => {
  assert.equal(H.overlayTextFor('rgb(79, 70, 229)', false), '#ffffff');
  assert.equal(H.overlayTextFor('rgb(248, 250, 252)', false), '#111827');
  assert.equal(H.overlayTextFor('rgb(248, 250, 252)', true), '#ffffff');
  assert.equal(H.overlayTextFor('rgba(0, 0, 0, 0)', false), '#ffffff');
});

test('scroll state: shrink past 8px, hide on a down-scroll, reveal on an up-scroll', () => {
  assert.equal(H.scrollState(0, 4, 70, false).scrolled, false);
  assert.equal(H.scrollState(0, 20, 70, false).scrolled, true);
  assert.equal(H.scrollState(200, 260, 70, false).hidden, true);
  assert.equal(H.scrollState(260, 240, 70, true).hidden, false);
  assert.equal(H.scrollState(260, 262, 70, true).hidden, true, 'tiny moves keep the state');
  assert.equal(H.scrollState(90, 50, 70, true).hidden, false, 'never hidden near the top');
  assert.ok(H.HERO.test('tb-hero tb-hero-split') && H.HERO.test('tb-carousel tb-slider-full') && !H.HERO.test('tb-section'));
});

test('cart count labels are Persian', () => {
  assert.equal(C.countLabel(3), '۳ کالا');
  assert.equal(C.countLabel(0), 'سبد خالی است');
  assert.equal(C.countLabel('x'), 'سبد خالی است');
});

test('the mini-cart payload is made safe: text titles, safe images, allowlisted money HTML', () => {
  const out = plain(C.normalizeMini({
    count: '2',
    subtotal_html: '۱٬۲۵۰<span class="currency-faded-zeros" style="opacity:0.4;">۰۰۰۰</span> تومان',
    items: [
      { key: '1:0', title: '<b>کیف</b>', image: 'javascript:alert(1)', quantity: 2, total_html: '۱٬۲۵۰٬۰۰۰ تومان' },
      { key: '2:0', title: 'کفش', image: '/img/a.jpg', quantity: 0, total_html: '<img src=x onerror=alert(1)>' },
      { title: 'no key' },
    ],
  }));
  assert.equal(out.count, 2);
  assert.ok(out.subtotalHtml.includes('currency-faded-zeros'));
  assert.equal(out.items.length, 2);
  assert.equal(out.items[0].title, '<b>کیف</b>', 'titles stay raw text (rendered with textContent)');
  assert.equal(out.items[0].image, '');
  assert.equal(out.items[1].image, '/img/a.jpg');
  assert.equal(out.items[1].quantity, 1);
  assert.equal(out.items[1].totalHtml, '', 'non-money HTML is dropped');
  assert.equal(C.money('<script>x</script>'), '');
  assert.equal(C.money('۸۹۰٬۰۰۰ تومان'), '۸۹۰٬۰۰۰ تومان');
});
