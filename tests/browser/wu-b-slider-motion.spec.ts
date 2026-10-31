import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * WU-B (docs/theme-builder/CANVAS_WIDGET_LOCAL_VS_PROD_AUDIT_2026-10-30.md
 * §7.1) — the upgraded carousel controller on the real renderer output and
 * the real shared stylesheets, at three widths:
 *
 *   - autoplay advances, and stops while the slider is scrolled out of view;
 *   - the first slide image is an eager, high-priority fetch (LCP);
 *   - the first dot is active from the server; keyboard focus shows a ring;
 *   - the fade slider swipes; the boxed caption title scales (B7);
 *   - prefers-reduced-motion: no autoplay and no slide animation, manual
 *     navigation still works.
 */

const ORIGIN = 'http://tb-wub.test';
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
$slides = [
    ['image' => '/s1.png', 'title' => 'حراج پاییزه', 'subtitle' => 'تا ۴۰٪ تخفیف'],
    ['image' => '/s2.png', 'title' => 'ارسال رایگان'],
    ['image' => '/s3.png', 'title' => 'محصولات تازه'],
];
$full = $r->renderBlock(['type' => 'slider', 'id' => 'full', 'props' => ['slides' => $slides, 'autoplay' => true, 'interval' => 1500]]);
$fade = $r->renderBlock(['type' => 'slider', 'id' => 'fade', 'props' => ['slides' => $slides, 'autoplay' => false, 'transition' => 'fade', 'boxed' => true]]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;font-family:Tahoma,sans-serif}</style></head><body><main>'
    . $full . '<div style="height:40px"></div>' . $fade . '<div id="far" style="height:300vh"></div><p id="end">پایان</p>'
    . '</main></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
}

// One render per worker: booting the app through `php -r` is the slow part.
let renderedDocument: string | null = null;
const documentHtml = () => (renderedDocument ??= renderDocument());

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');

async function serve(page: Page, html: string) {
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin === ORIGIN && url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    return route.fulfill({ contentType: 'image/png', body: PNG });
  });
}

const activeDot = (page: Page, id: string) =>
  page.locator(`#tb-${id} .tb-carousel-dot`).evaluateAll((dots) => dots.findIndex((d) => d.getAttribute('aria-current') === 'true'));

function watchProblems(page: Page) {
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  return problems;
}

test('WU-B carousel: autoplay gates, LCP image, server active state, focus ring, fade swipe, fluid caption', async ({ page }) => {
  test.setTimeout(180_000);
  const problems = watchProblems(page);
  const html = documentHtml();
  expect(html).not.toMatch(/aria-label="اسلایدر (full|fade)"/);
  await serve(page, html);

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 860 });
    await page.goto(`${ORIGIN}/`);

    // B4 + B5: the LCP image and the server-rendered active dot.
    const firstImg = page.locator('#tb-full .tb-carousel-slide').first().locator('img');
    await expect(firstImg).toHaveAttribute('loading', 'eager');
    await expect(firstImg).toHaveAttribute('fetchpriority', 'high');
    await expect(page.locator('#tb-full .tb-carousel-slide').nth(1).locator('img')).toHaveAttribute('loading', 'lazy');
    await expect(page.locator('#tb-fade .tb-carousel-dot').first()).toHaveClass(/is-active/);

    // Autoplay advances while visible.
    await expect.poll(() => activeDot(page, 'full'), { timeout: 5_000 }).not.toBe(0);

    // B2: out of view, the slider holds still.
    await page.locator('#end').scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    const held = await activeDot(page, 'full');
    await page.waitForTimeout(3_400);
    expect(await activeDot(page, 'full'), 'no autoplay while scrolled out of view').toBe(held);
    await page.evaluate(() => window.scrollTo(0, 0));

    // Keyboard focus is visible (the root used to set outline:none). Start
    // from no focus: the full-width slider fills the top of the page, so a
    // "neutral" click would land inside it and give it mouse focus.
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.keyboard.press('Tab');
    await expect(page.locator('#tb-full')).toBeFocused();
    expect(await page.locator('#tb-full').evaluate((el) => getComputedStyle(el).outlineStyle)).toBe('solid');

    // B3: the fade slider swipes (RTL: a rightward swipe shows the next slide).
    const fade = page.locator('#tb-fade');
    await fade.scrollIntoViewIfNeeded();
    const vb = (await fade.locator('.tb-carousel-viewport').boundingBox())!;
    await page.mouse.move(vb.x + vb.width * 0.3, vb.y + vb.height / 2);
    await page.mouse.down();
    await page.mouse.move(vb.x + vb.width * 0.75, vb.y + vb.height / 2 + 4, { steps: 8 });
    await page.mouse.up();
    await page.waitForTimeout(750);
    expect(await activeDot(page, 'fade')).toBe(1);
    expect(await fade.locator('.tb-carousel-slide').nth(1).evaluate((s) => getComputedStyle(s).opacity)).toBe('1');
    expect(await fade.locator('.tb-carousel-slide').nth(0).evaluate((s) => getComputedStyle(s).opacity)).toBe('0');

    // B7: the boxed caption title scales between 1.25rem and 2.25rem.
    const size = await fade.locator('.tb-slide-title').first().evaluate((h) => parseFloat(getComputedStyle(h).fontSize));
    expect(size).toBeGreaterThanOrEqual(20);
    expect(size).toBeLessThanOrEqual(36);
    await page.screenshot({ path: `test-results/wub-slider-${w.name}.png`, fullPage: false });

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
  }

  expect(problems, problems.join('\n')).toEqual([]);
});

test('WU-B carousel: prefers-reduced-motion turns autoplay and slide animation off', async ({ page }) => {
  test.setTimeout(180_000);
  const problems = watchProblems(page);
  await serve(page, documentHtml());
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.setViewportSize({ width: 390, height: 860 });
  await page.goto(`${ORIGIN}/`);

  await page.waitForTimeout(4_000); // more than two autoplay intervals
  expect(await activeDot(page, 'full'), 'no autoplay under reduced motion').toBe(0);
  expect(await page.locator('#tb-full .tb-carousel-track').evaluate((t) => getComputedStyle(t).transitionDuration)).toBe('0s');
  expect(await page.locator('#tb-fade .tb-carousel-slide').first().evaluate((s) => getComputedStyle(s).transitionDuration)).toBe('0s');

  await page.locator('#tb-full .tb-carousel-next').click();
  expect(await activeDot(page, 'full'), 'manual navigation still works').toBe(1);

  expect(problems, problems.join('\n')).toEqual([]);
});
