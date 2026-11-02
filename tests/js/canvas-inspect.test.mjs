import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// WU-1: Inspect Mode's pure helpers (both editors and the catalog load the
// same file through theme-runtime::canvas.inspect). Without a document the
// file only exports these.
const source = fs.readFileSync(new URL('../../packages/saas-platform/modules/theme-runtime/resources/js/canvas-inspect.js', import.meta.url), 'utf8');
const context = vm.createContext({ Math, JSON, Object, String });
context.globalThis = context;
vm.runInContext(source, context);
const I = context.TbInspect;

test('Ctrl+I and Cmd+I toggle, matched on the physical key so a Persian layout works', () => {
  assert.equal(I.isToggleShortcut({ ctrlKey: true, key: 'i', code: 'KeyI' }), true);
  assert.equal(I.isToggleShortcut({ metaKey: true, key: 'I', code: 'KeyI' }), true);
  assert.equal(I.isToggleShortcut({ ctrlKey: true, key: 'ه', code: 'KeyI' }), true, 'Persian layout: the I key types ه');
  assert.equal(I.isToggleShortcut({ ctrlKey: true, shiftKey: true, key: 'I', code: 'KeyI' }), false, 'Ctrl+Shift+I stays devtools');
  assert.equal(I.isToggleShortcut({ ctrlKey: true, altKey: true, key: 'i', code: 'KeyI' }), false);
  assert.equal(I.isToggleShortcut({ key: 'i', code: 'KeyI' }), false);
  assert.equal(I.isToggleShortcut({ ctrlKey: true, key: 'u', code: 'KeyU' }), false);
});

test('text fields and contenteditable keep Ctrl+I (italic)', () => {
  const el = (tagName, extra = {}) => ({ nodeType: 1, tagName, isContentEditable: false, closest: () => null, ...extra });
  assert.equal(I.isEditableTarget(el('INPUT')), true);
  assert.equal(I.isEditableTarget(el('TEXTAREA')), true);
  assert.equal(I.isEditableTarget(el('SELECT')), true);
  assert.equal(I.isEditableTarget(el('DIV', { isContentEditable: true })), true);
  assert.equal(I.isEditableTarget(el('SPAN', { closest: () => ({}) })), true, 'inside an inline-edited widget field');
  assert.equal(I.isEditableTarget(el('DIV')), false);
  assert.equal(I.isEditableTarget(null), false);
});

test('the renderer method follows the generic dispatch (Str::studly)', () => {
  assert.equal(I.rendererMethod('header_modern_v1'), 'renderHeaderModernV1');
  assert.equal(I.rendererMethod('nav_header'), 'renderNavHeader');
  assert.equal(I.rendererMethod('module:shop_wishlist_icon'), null);
  assert.equal(I.rendererMethod(''), null);
});

test('the action bar sits under the widget, above it near the bottom, inside it when neither fits', () => {
  const rect = { left: 100, right: 500, top: 100, bottom: 300 };
  assert.deepEqual({ ...I.barPosition(rect, 200, 40, 1280, 800, true) }, { left: 300, top: 308 }, 'RTL: aligned to the right edge');
  assert.deepEqual({ ...I.barPosition(rect, 200, 40, 1280, 800, false) }, { left: 100, top: 308 });
  assert.deepEqual({ ...I.barPosition({ ...rect, top: 600, bottom: 780 }, 200, 40, 1280, 800, true) }, { left: 300, top: 552 }, 'no room below');
  assert.deepEqual({ ...I.barPosition({ left: 0, right: 1280, top: 0, bottom: 800 }, 200, 40, 1280, 800, true) }, { left: 1072, top: 8 }, 'full-page widget');
  assert.deepEqual({ ...I.barPosition({ left: -50, right: 100, top: 10, bottom: 50 }, 300, 40, 360, 800, false) }, { left: 8, top: 58 }, 'kept inside a 360 px viewport');
});

test('the AI prompt names the widget, its files, its props and its findings', () => {
  const prompt = I.buildPrompt(
    { type: 'nav_header', blockId: 'blk_1', label: 'هدر ناوبری', props: { logo_text: 'برند' } },
    { page: '/', width: 360, findings: [{ severity: 'blocker', code: 'PAGE_HORIZONTAL_OVERFLOW', message: 'ویجت ۲۵px بیرون زده', selector: '.tb-nav-header-logo', remedy: 'flex-shrink بده' }] },
  );
  assert.match(prompt, /^ویجت «هدر ناوبری» \(nav_header\)/);
  assert.match(prompt, /شناسه ویجت: nav_header#blk_1/);
  assert.match(prompt, /src\/BlockRenderer\.php → renderNavHeader\(\)/);
  assert.match(prompt, /src\/BlockRegistry\.php/);
  assert.match(prompt, /theme-runtime-blocks\.css/);
  assert.match(prompt, /صفحه: \/ در عرض 360px/);
  assert.match(prompt, /"logo_text": "برند"/);
  assert.match(prompt, /\[blocker\] PAGE_HORIZONTAL_OVERFLOW: ویجت ۲۵px بیرون زده \(\.tb-nav-header-logo\)/);
  assert.match(prompt, /راه رفع: flex-shrink بده/);
  assert.match(prompt, /PROJECT_CONSTITUTION\.md §16/);

  const bare = I.buildPrompt({ type: 'module:x', props: {} });
  assert.doesNotMatch(bare, /مقادیر فعلی|ایرادهای پیدا شده|→/);
  assert.match(I.buildPrompt({ type: 'faq', files: { host: 'customer-site/app/Services/ThemeCanvas/BlockRegistry.php' } }), /میزبان: customer-site/);
});
