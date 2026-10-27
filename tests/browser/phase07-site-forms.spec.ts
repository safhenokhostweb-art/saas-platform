import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Phase 0.7 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-066) —
 * the live behaviour of the shared newsletter and search blocks
 * (theme-runtime blocks/site-forms.js), independent of any tenant licence:
 * the real renderer output (with host endpoints) and the real stylesheets
 * are served on a stub origin whose newsletter and suggest endpoints answer
 * like customer-site's.
 *
 *   - search: a page field opens the search dialog (centred modal on
 *     desktop, full screen on phones), debounced suggestions with Persian
 *     prices, scope buttons, «مشاهدهٔ همهٔ نتایج», Esc returns focus, the
 *     header search folds into an icon on phones;
 *   - newsletter: client validation, one request per submit, Persian
 *     success / duplicate / throttle toasts.
 */

const ORIGIN = 'http://tb-site-forms.test';
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
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$r->setContext(['site_endpoints' => [
    'newsletter' => '/newsletter/subscribe',
    'search' => [
        'all' => ['action' => '/search'],
        'products' => ['action' => '/search', 'params' => ['type' => 'products']],
        'posts' => ['action' => '/search', 'params' => ['type' => 'posts']],
    ],
    'suggest' => '/api/search/suggest',
]]);
$blocks = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => ['logo_text' => 'فروشگاه نمونه', 'show_search' => true, 'search_placeholder' => 'جستجوی محصول…', 'nav_links' => [['label' => 'خانه', 'url' => '/']]]])
    . '<main style="padding:2rem 1rem">'
    . $r->renderBlock(['type' => 'search_bar', 'id' => 'sb', 'props' => ['placeholder' => 'دنبال چه می‌گردید؟']])
    . '<div style="height:2rem"></div>'
    . $r->renderBlock(['type' => 'newsletter_form', 'id' => 'nl', 'props' => ['title' => 'از تخفیف‌ها باخبر شوید']])
    . '</main>';
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="test-token"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;font-family:Tahoma,sans-serif}</style></head><body>' . $blocks . '</body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
}

const SUGGESTIONS = [
  { type: 'product', title: 'تشک طبی رویال', url: '/shop/royal', image: null, price: 12500000 },
  { type: 'product', title: 'تشک فنری کلاسیک', url: '/shop/classic', image: null, price: 8900000 },
  { type: 'post', title: 'راهنمای خرید تشک', url: '/blog/guide', image: null },
];

async function serve(page: Page, html: string) {
  const calls = { suggest: [] as string[], subscribe: [] as Record<string, string>[] };
  const seen = new Set<string>();
  await page.route(`${ORIGIN}/**`, async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    if (url.pathname === '/api/search/suggest') {
      calls.suggest.push(url.searchParams.get('q') || '');
      const q = url.searchParams.get('q') || '';
      return route.fulfill({ json: { items: q.includes('تشک') ? SUGGESTIONS : [] } });
    }
    if (url.pathname === '/newsletter/subscribe') {
      const body = Object.fromEntries(new URLSearchParams(
        // multipart → read the named fields
        [...(route.request().postData() || '').matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g)].map((m) => [m[1], m[2]]),
      ));
      body.csrf = route.request().headers()['x-csrf-token'] || '';
      calls.subscribe.push(body);
      if (body.contact === 'limit@example.com') return route.fulfill({ status: 429, json: { message: 'Too Many Attempts.' } });
      if (seen.has(body.contact)) return route.fulfill({ json: { ok: true, status: 'already', message: 'این ایمیل یا شماره قبلاً در خبرنامه ثبت شده است.' } });
      seen.add(body.contact);
      return route.fulfill({ status: 201, json: { ok: true, status: 'subscribed', message: 'عضویت شما در خبرنامه ثبت شد. سپاس!' } });
    }
    return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: `<p>results ${url.search}</p>` });
  });
  return calls;
}

test('Phase 0.7 search dialog and newsletter work at every width', async ({ page }) => {
  test.setTimeout(120_000);
  const problems: string[] = [];
  page.on('console', (m) => {
    // The throttle step answers 429 on purpose; Chromium logs every 4xx.
    if (m.type() === 'error' && !/status of 429/.test(m.text())) problems.push(`console: ${m.text()}`);
  });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));

  const html = renderDocument();
  expect(html.match(/data-tb-block-asset="site_forms"/g)?.length).toBe(1);
  const calls = await serve(page, html);
  await page.goto(`${ORIGIN}/`);

  const dialog = page.locator('.tb-search-overlay');
  const panel = dialog.locator('[role="dialog"]');
  const input = dialog.locator('.tb-search-overlay__input');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 860 });
    await page.goto(`${ORIGIN}/`);

    // ── Search: page field → dialog ───────────────────────────────────
    await page.locator('#tb-sb-search').click();
    await expect(dialog).toBeVisible();
    await expect(input).toBeFocused();
    await expect(dialog.locator('.tb-search-overlay__scope')).toHaveText(['همه', 'محصولات', 'مطالب']);
    const box = await panel.boundingBox();
    if (w.name === 'mobile') {
      expect(Math.round(box!.width), 'full screen on phones').toBe(w.width);
      expect(Math.round(box!.height)).toBe(860);
    } else {
      expect(box!.width).toBeLessThanOrEqual(640);
      expect(box!.y).toBeGreaterThan(40);
    }

    const before = calls.suggest.length;
    await input.pressSequentially('تشک', { delay: 30 });
    const items = dialog.locator('.tb-search-overlay__item');
    await expect(items).toHaveCount(3);
    expect(calls.suggest.length - before, 'typing is debounced into one request').toBe(1);
    await expect(items.first()).toContainText('تشک طبی رویال');
    await expect(items.first()).toContainText('۱۲٬۵۰۰٬۰۰۰ تومان');
    await expect(dialog.locator('.tb-search-overlay__status')).toHaveText('۳ پیشنهاد');
    await dialog.locator('[data-tb-scope="posts"]').click();
    await expect(items).toHaveCount(1);
    await expect(items.first()).toContainText('مطلب');
    const all = dialog.locator('.tb-search-overlay__all');
    await expect(all).toHaveText('مشاهدهٔ همهٔ نتایج برای «تشک»');
    expect(await all.getAttribute('href')).toBe(`${ORIGIN}/search?type=posts&q=%D8%AA%D8%B4%DA%A9`);
    await input.press('ArrowDown');
    await expect(items.first()).toBeFocused();
    await page.screenshot({ path: `test-results/phase07-search-${w.name}.png` });
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(page.locator('#tb-sb-search')).toBeFocused();
    await expect(dialog, 'focus returning to the field does not reopen').toBeHidden();

    // Enter in the dialog goes to the results page with the scope.
    await page.locator('#tb-sb-search').click();
    await input.fill('بالش');
    await expect(dialog.locator('.tb-search-overlay__status')).toHaveText('نتیجه‌ای برای «بالش» پیدا نشد.');
    await Promise.all([page.waitForURL(/\/search\?q=/), input.press('Enter')]);
    expect(new URL(page.url()).searchParams.get('q')).toBe('بالش');
    await page.goto(`${ORIGIN}/`);

    // ── Header: field on wide screens, icon on phones ──────────────────
    const headerField = page.locator('#tb-hdr-search');
    const headerToggle = page.locator('.tb-nav-header-search-toggle');
    if (w.name === 'mobile') {
      await expect(headerField).toBeHidden();
      await expect(headerToggle).toBeVisible();
      await headerToggle.click();
    } else {
      await expect(headerToggle).toBeHidden();
      await headerField.click();
    }
    await expect(dialog).toBeVisible();
    await expect(input).toHaveAttribute('placeholder', 'جستجوی محصول…');
    await dialog.locator('.tb-search-overlay__close').click();
    await expect(dialog).toBeHidden();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
  }

  // ── Newsletter ───────────────────────────────────────────────────────
  await page.setViewportSize({ width: 390, height: 860 });
  await page.goto(`${ORIGIN}/`);
  const form = page.locator('#tb-nl form[data-tb-newsletter]');
  const field = form.locator('[name="contact"]');
  const status = form.locator('.tb-newsletter-status');
  const toast = page.locator('.tb-toast');

  await form.locator('button[type="submit"]').click();
  await expect(status).toHaveText('لطفاً ایمیل یا شماره موبایل خود را وارد کنید.');
  await expect(field).toHaveAttribute('aria-invalid', 'true');
  await expect(toast.last()).toHaveClass(/tb-toast--error/);
  expect(calls.subscribe).toHaveLength(0);

  await field.fill('name@');
  await page.waitForTimeout(1100);
  await field.press('Enter');
  await expect(status).toContainText('معتبر نیست');
  expect(calls.subscribe).toHaveLength(0);

  await field.fill('۰۹۱۲ ۳۴۵ ۶۷۸۹');
  await expect(status, 'a fixed value clears the error as you type').toHaveText('');
  await page.waitForTimeout(1100);
  await form.locator('button[type="submit"]').dblclick();
  await expect(toast.last()).toHaveText('عضویت شما در خبرنامه ثبت شد. سپاس!');
  await expect(toast.last()).toHaveClass(/tb-toast--success/);
  expect(calls.subscribe, 'a double click sends once').toHaveLength(1);
  expect(calls.subscribe[0]).toMatchObject({ contact: '09123456789', source: 'nl', website: '', csrf: 'test-token' });
  await expect(field).toHaveValue('');
  await page.screenshot({ path: 'test-results/phase07-newsletter-mobile.png' });

  await page.waitForTimeout(1100);
  await field.fill('+98 912 345 6789');
  await field.press('Enter');
  await expect(toast.last()).toHaveText('این ایمیل یا شماره قبلاً در خبرنامه ثبت شده است.');

  await page.waitForTimeout(1100);
  await field.fill('limit@example.com');
  await field.press('Enter');
  await expect(toast.last()).toHaveText('تعداد درخواست‌ها زیاد است؛ لطفاً یک دقیقهٔ دیگر دوباره تلاش کنید.');
  await expect(field).toHaveValue('limit@example.com');

  expect(problems, problems.join('\n')).toEqual([]);
});
