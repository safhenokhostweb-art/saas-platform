import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Phase 3 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-068) — the
 * live behaviour of the commerce widgets on the real renderer output,
 * served on a stub origin whose cart endpoints answer like customer-site's:
 *
 *   - mini-cart drawer: the quantity stepper repaints the line total,
 *     subtotal and badge before the host answers; rapid steps send one
 *     update with the last quantity; «−» at one and the trash button ask
 *     inline (Esc cancels and returns focus); a refused update restores the
 *     host's cart;
 *   - product cards: discount / «پرامتیاز» / «جدید» overlays on the image;
 *   - countdown: a Jalali end date ticks in Persian digits, and a past one
 *     shows the expiry text;
 *   - FAQ: an exclusive accordion.
 */

const ORIGIN = 'http://tb-phase3.test';
const WIDTHS = [
  { name: 'desktop', width: 1366 },
  { name: 'tablet', width: 768 },
  { name: 'mobile', width: 390 },
] as const;

function renderDocument(): string {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use SaaSPlatform\\ThemeRuntime\\Support\\JalaliDate;
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$r->setContext(['site_endpoints' => [
    'cart' => ['page' => '/shop/cart', 'mini' => '/shop/cart/mini', 'add' => '/shop/cart/add', 'update' => '/shop/cart/update', 'remove' => '/shop/cart/remove', 'count' => 3],
]]);
[$jy, $jm, $jd] = JalaliDate::fromGregorian(...array_map('intval', explode('-', gmdate('Y-n-j', time() + 3 * 86400))));
$future = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
$header = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => ['logo_text' => 'فروشگاه نمونه', 'show_cart' => true]]);
$grid = $r->renderBlock(['type' => 'product_grid', 'id' => 'grid', 'props' => ['columns' => 4]]);
$countdown = $r->renderBlock(['type' => 'countdown', 'id' => 'cd', 'props' => ['label' => 'حراج پاییزه', 'end_date' => $future, 'end_time' => '20:00']]);
$banner = $r->renderBlock(['type' => 'countdown', 'id' => 'cdb', 'props' => ['label' => 'تخفیف شگفت‌انگیز', 'end_date' => $future, 'style' => 'banner']]);
$expired = $r->renderBlock(['type' => 'countdown', 'id' => 'cdx', 'props' => ['label' => 'جشنواره نوروز', 'end_date' => '1400/01/01', 'expired_text' => 'این جشنواره تمام شد']]);
$faq = $r->renderBlock(['type' => 'faq', 'id' => 'faq', 'props' => ['title' => 'سوالات متداول', 'exclusive' => true, 'open_first' => true, 'items' => [
    ['question' => 'ارسال چند روز طول می‌کشد؟', 'answer' => "تهران: ۱ تا ۲ روز کاری\\nشهرستان: ۲ تا ۴ روز کاری"],
    ['question' => 'امکان مرجوعی کالا هست؟', 'answer' => 'تا ۷ روز پس از تحویل، بدون پرسش.'],
    ['question' => 'پرداخت در محل دارید؟', 'answer' => 'بله، در تهران.'],
]]]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="t"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;font-family:Tahoma,sans-serif}</style></head><body>'
    . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
    . '<main><section class="tb-section"><div class="tb-container">' . $grid . '</div></section>'
    . '<section class="tb-section"><div class="tb-container">' . $countdown . '</div></section>'
    . '<section class="tb-section"><div class="tb-container">' . $banner . $expired . '</div></section>'
    . $faq . '</main></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
}

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');
const fa = (n: number) => n.toLocaleString('fa-IR');

type Line = { key: string; title: string; image: null; quantity: number; unit_amount: number; stock: number };

async function serve(page: Page, html: string) {
  const state = {
    lines: [
      { key: '1:0', title: 'کیف چرم دست‌دوز', image: null, quantity: 1, unit_amount: 790000, stock: 5 },
      { key: '3:0', title: 'هدفون بی‌سیم', image: null, quantity: 2, unit_amount: 890000, stock: 9 },
    ] as Line[],
    updates: [] as string[],
    removes: [] as string[],
    delay: 0,
  };
  const mini = () => ({
    count: state.lines.reduce((n, l) => n + l.quantity, 0),
    subtotal_html: fa(state.lines.reduce((s, l) => s + l.unit_amount * l.quantity, 0)) + ' تومان',
    money_suffix_html: '',
    unit: 'تومان',
    items: state.lines.map((l) => ({ key: l.key, title: l.title, image: l.image, quantity: l.quantity, unit_amount: l.unit_amount, total_html: fa(l.unit_amount * l.quantity) + ' تومان' })),
  });
  const field = (body: string, name: string) => body.match(new RegExp(`name="${name}"\\r\\n\\r\\n([^\\r]*)`))?.[1];
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) return route.fulfill({ contentType: 'image/png', body: PNG });
    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    if (url.pathname === '/shop/cart/mini') return route.fulfill({ json: mini() });
    const body = route.request().postData() || '';
    if (url.pathname === '/shop/cart/update') {
      if (state.delay) await new Promise((r) => setTimeout(r, state.delay));
      const key = field(body, 'line_key')!;
      const qty = Number(field(body, 'quantity'));
      state.updates.push(`${key}=${qty}`);
      const line = state.lines.find((l) => l.key === key);
      if (!line || qty > line.stock) return route.fulfill({ status: 422, json: { ok: false, message: 'تعداد درخواستی از موجودی بیشتر است.' } });
      line.quantity = qty;
      return route.fulfill({ json: { ok: true, message: 'سبد به‌روزرسانی شد.', cart: mini() } });
    }
    if (url.pathname === '/shop/cart/remove') {
      const key = field(body, 'line_key')!;
      state.removes.push(key);
      state.lines = state.lines.filter((l) => l.key !== key);
      return route.fulfill({ json: { ok: true, message: 'محصول از سبد حذف شد.', cart: mini() } });
    }
    return route.fulfill({ status: 404, body: '' });
  });
  return state;
}

test('Phase 3 drawer stepper, badges, Jalali countdown and FAQ at every width', async ({ page }) => {
  test.setTimeout(180_000);
  const html = renderDocument();
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    const state = await serve(page, html);
    await page.goto(ORIGIN + '/');

    // ── Badges overlay the image ─────────────────────────────────────
    const card = page.locator('#tb-grid .tb-product-card').first();
    await card.scrollIntoViewIfNeeded();
    await expect(card.locator('.tb-product-ribbons .tb-badge')).toHaveText(['۲۱٪ تخفیف', 'پرامتیاز']);
    const [ribbon, image] = await Promise.all([card.locator('.tb-product-ribbons').boundingBox(), card.locator('.tb-product-card-img').boundingBox()]);
    expect(ribbon!.y).toBeGreaterThanOrEqual(image!.y);
    expect(ribbon!.y + ribbon!.height).toBeLessThanOrEqual(image!.y + image!.height);
    await expect(page.locator('#tb-grid .tb-badge-info', { hasText: 'جدید' })).toHaveCount(1);
    await page.locator('#tb-grid').screenshot({ path: `test-results/phase3-badges-${w.name}.png` });

    // ── Countdown ────────────────────────────────────────────────────
    const cd = page.locator('#tb-cd');
    await cd.scrollIntoViewIfNeeded();
    await expect(cd.locator('.tb-countdown__d')).toHaveText(/^[۰-۹]{2,}$/);
    const first = await cd.locator('.tb-countdown__s').textContent();
    await expect.poll(async () => cd.locator('.tb-countdown__s').textContent(), { timeout: 3000 }).not.toBe(first);
    await expect(cd.locator('.tb-countdown__end')).toContainText(/^پایان: [۰-۹]+ \S+ ۱۴۰[۵-۶]، ساعت ۲۰:۰۰$/);
    await expect(page.locator('#tb-cdx .tb-countdown__expired')).toBeVisible();
    await expect(page.locator('#tb-cdx .tb-countdown__expired')).toHaveText('این جشنواره تمام شد');
    await expect(page.locator('#tb-cdx .tb-countdown__timer')).toBeHidden();
    await expect(page.locator('#tb-cdb')).toHaveClass(/tb-countdown--banner/);
    await cd.screenshot({ path: `test-results/phase3-countdown-${w.name}.png` });
    await page.locator('#tb-cdb').screenshot({ path: `test-results/phase3-countdown-banner-${w.name}.png` });

    // ── FAQ: exclusive accordion ─────────────────────────────────────
    const items = page.locator('#tb-faq details.tb-faq-item');
    await expect(items.nth(0)).toHaveAttribute('open', '');
    await items.nth(1).locator('summary').click();
    await expect(items.nth(1)).toHaveAttribute('open', '');
    await expect(items.nth(0)).not.toHaveAttribute('open', '');
    await items.nth(2).locator('summary').focus();
    await page.keyboard.press('Enter');
    await expect(items.nth(2)).toHaveAttribute('open', '');
    await expect(items.nth(1)).not.toHaveAttribute('open', '');
    await expect(items.nth(0).locator('.tb-faq-answer')).toContainText('شهرستان');
    await page.locator('#tb-faq').screenshot({ path: `test-results/phase3-faq-${w.name}.png` });

    // ── Drawer stepper ───────────────────────────────────────────────
    await page.locator('[data-tb-cart-open]').first().click();
    const drawer = page.locator('.tb-cart-drawer [role="dialog"]');
    await expect(drawer).toBeVisible();
    const bag = page.locator('.tb-cart-drawer__item[data-key="1:0"]');
    const subtotal = page.locator('.tb-cart-drawer__subtotal strong');
    await expect(bag.locator('.tb-qty__value')).toHaveText('۱');
    await expect(subtotal).toHaveText('۲٬۵۷۰٬۰۰۰ تومان');
    await expect(bag.locator('[data-tb-cart-step="-1"]')).toHaveClass(/tb-qty__btn--remove/);

    // Two quick steps: instant totals, then one request with the last quantity.
    state.delay = 600;
    const plus = bag.locator('[data-tb-cart-step="1"]');
    await plus.click();
    await plus.click();
    await expect(bag.locator('.tb-qty__value')).toHaveText('۳');
    await expect(bag.locator('.tb-cart-drawer__price')).toHaveText('۲٬۳۷۰٬۰۰۰ تومان');
    await expect(subtotal).toHaveText('۴٬۱۵۰٬۰۰۰ تومان');
    await expect(page.locator('[data-tb-cart-count]').first()).toHaveText('۵');
    expect(state.updates).toEqual([]);
    await expect.poll(() => state.updates.join(','), { timeout: 4000 }).toBe('1:0=3');
    await expect(bag).not.toHaveClass(/is-saving/, { timeout: 4000 });
    await expect(bag.locator('[data-tb-cart-step="1"]')).toBeFocused();
    state.delay = 0;

    // Above stock: the host refuses, the drawer shows its cart again.
    for (let i = 0; i < 3; i++) await bag.locator('[data-tb-cart-step="1"]').click();
    await expect(bag.locator('.tb-qty__value')).toHaveText('۶');
    await expect(page.locator('.tb-toast--error').last()).toHaveText('تعداد درخواستی از موجودی بیشتر است.');
    await expect(bag.locator('.tb-qty__value')).toHaveText('۳');
    await expect(subtotal).toHaveText('۴٬۱۵۰٬۰۰۰ تومان');
    await drawer.screenshot({ path: `test-results/phase3-drawer-${w.name}.png` });

    // Trash asks inline; Esc cancels and returns focus without closing.
    const phones = page.locator('.tb-cart-drawer__item[data-key="3:0"]');
    await phones.locator('[data-tb-cart-remove]').click();
    const confirm = phones.locator('.tb-cart-drawer__confirm');
    await expect(confirm).toContainText('«هدفون بی‌سیم» از سبد حذف شود؟');
    await expect(confirm.locator('[data-tb-cart-cancel]')).toBeFocused();
    await drawer.screenshot({ path: `test-results/phase3-drawer-confirm-${w.name}.png` });
    await page.keyboard.press('Escape');
    await expect(confirm).toHaveCount(0);
    await expect(drawer).toBeVisible();
    await expect(phones.locator('[data-tb-cart-remove]')).toBeFocused();
    expect(state.removes).toEqual([]);

    // «−» down to one, then once more asks; confirming removes at once.
    await phones.locator('[data-tb-cart-step="-1"]').click();
    await expect(phones.locator('.tb-qty__value')).toHaveText('۱');
    await phones.locator('[data-tb-cart-step="-1"]').click();
    await phones.locator('[data-tb-cart-confirm]').click();
    await expect(phones).toHaveCount(0);
    await expect(subtotal).toHaveText('۲٬۳۷۰٬۰۰۰ تومان');
    await expect.poll(() => state.removes.join(',')).toBe('3:0');
    await page.waitForTimeout(600);
    expect(state.updates.some((u) => u.startsWith('3:0=')), 'a removed line drops its unsent step').toBe(false);
    await expect(page.locator('[data-tb-cart-count]').first()).toHaveText('۳');

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
    await page.unrouteAll({ behavior: 'ignoreErrors' });
  }

  // The refused update is a deliberate 422, which Chromium logs.
  expect(errors.filter((e) => !/status of 422/.test(e))).toEqual([]);
});
