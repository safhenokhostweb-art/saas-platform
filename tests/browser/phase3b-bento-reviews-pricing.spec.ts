import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Phase 3b (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-069) on the
 * real renderer output, served on a stub origin:
 *
 *   - Bento v2: 12 columns on desktop, 2 on tablets, 1 on phones, with each
 *     size spanning the right share; product, countdown and metric cells;
 *   - review cards (SVG stars, verified badge, Jalali date) and the Persian
 *     rating breakdown;
 *   - pricing: the monthly / yearly switch, the popular ribbon, check/x
 *     features and tooltips (hover, Esc dismissal, tap).
 */

const ORIGIN = 'http://tb-phase3b.test';
const WIDTHS = [
  { name: 'desktop', width: 1366, cols: 12 },
  { name: 'tablet', width: 768, cols: 2 },
  { name: 'mobile', width: 390, cols: 1 },
] as const;

function renderDocument(): string {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use SaaSPlatform\\ThemeRuntime\\Support\\JalaliDate;
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
[$jy, $jm, $jd] = JalaliDate::fromGregorian(...array_map('intval', explode('-', gmdate('Y-n-j', time() + 5 * 86400))));
$bento = $r->renderBlock(['type' => 'bento_grid', 'id' => 'bento', 'props' => ['title' => 'همه چیز در یک نگاه', 'items' => [
    ['kind' => 'product', 'title' => 'پیشنهاد امروز', 'size' => 'tall', 'bg_color' => '#eef2ff', 'text_color' => '#1e1b4b'],
    ['kind' => 'countdown', 'title' => 'حراج پاییزه', 'text' => 'تا پایان جشنواره', 'icon' => 'timer', 'end_date' => sprintf('%04d/%02d/%02d', $jy, $jm, $jd), 'size' => 'large', 'bg_color' => '#4f46e5', 'text_color' => '#ffffff'],
    ['kind' => 'metric', 'title' => 'مشتری راضی', 'metric_value' => '12000+', 'icon' => 'users', 'size' => 'half', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
    ['kind' => 'metric', 'title' => 'رضایت', 'metric_value' => '98%', 'icon' => 'badge-check', 'size' => 'half', 'bg_color' => '#fef9c3', 'text_color' => '#713f12'],
    ['title' => 'ارسال سریع', 'text' => 'به سراسر ایران', 'icon' => 'truck', 'size' => 'full', 'bg_color' => '#f8fafc', 'text_color' => '#0f172a'],
]]]);
$reviews = $r->renderBlock(['type' => 'testimonials_slider', 'id' => 'rev', 'props' => ['title' => 'نظر خریداران', 'mode' => 'static', 'columns' => '3', 'show_summary' => true, 'items' => [
    ['name' => 'سارا محمدی', 'role' => 'خریدار کیف', 'text' => 'کیفیت دوخت عالی و ارسال سریع بود.', 'rating' => 5, 'verified' => true, 'date' => '1405/08/02'],
    ['name' => 'علی رضایی', 'text' => 'رنگ کمی با تصویر فرق داشت ولی راضی‌ام.', 'rating' => 4, 'verified' => true, 'date' => '1405/07/28'],
    ['name' => 'مینا', 'text' => 'بسته‌بندی می‌توانست بهتر باشد.', 'rating' => 3, 'date' => '2026-10-10'],
]]]);
$pricing = $r->renderBlock(['type' => 'pricing_cards', 'id' => 'price', 'props' => ['title' => 'پلن‌ها', 'yearly_discount_label' => '۲۰٪ تخفیف', 'plans' => [
    ['name' => 'پایه', 'price' => '۹۹ هزار', 'period' => '/ ماه', 'price_yearly' => '۹۵۰ هزار', 'features' => [['text' => 'یک فروشگاه', 'included' => true], ['text' => 'دسترسی API', 'included' => false, 'tooltip' => 'اتصال نرم‌افزارهای دیگر به فروشگاه از راه رابط برنامه‌نویسی']], 'cta_text' => 'شروع'],
    ['name' => 'حرفه‌ای', 'price' => '۱۹۹ هزار', 'period' => '/ ماه', 'price_yearly' => '۱٬۹۰۰ هزار', 'highlight' => true, 'features' => [['text' => 'سه فروشگاه', 'included' => true], ['text' => 'دسترسی API', 'included' => true, 'tooltip' => 'اتصال نرم‌افزارهای دیگر به فروشگاه از راه رابط برنامه‌نویسی']], 'cta_text' => 'انتخاب'],
    ['name' => 'سازمانی', 'price' => 'تماس بگیرید', 'features' => ['همه امکانات'], 'cta_text' => 'تماس'],
]]]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;font-family:Tahoma,sans-serif}</style></head><body><main>' . $bento . $reviews . $pricing . '</main></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
}

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');

async function serve(page: Page, html: string) {
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) return route.fulfill({ contentType: 'image/png', body: PNG });
    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    return route.fulfill({ status: 404, body: '' });
  });
}

test('Phase 3b bento grid, reviews and pricing at every width', async ({ page }) => {
  test.setTimeout(180_000);
  const html = renderDocument();
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await serve(page, html);

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    await page.goto(ORIGIN + '/');

    // ── Bento: the column count and each size's share of the row ─────
    const grid = page.locator('#tb-bento .tb-bento-grid');
    await grid.scrollIntoViewIfNeeded();
    const cols = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
    expect(cols, `bento columns at ${w.name}`).toBe(w.cols);
    const gridBox = (await grid.boundingBox())!;
    const share = async (sel: string) => ((await page.locator(`#tb-bento ${sel}`).first().boundingBox())!.width + 16) / (gridBox.width + 16);
    const expected: Record<string, Record<string, number>> = {
      desktop: { '.tall': 4 / 12, '.large': 8 / 12, '.half': 6 / 12, '.full': 1 },
      tablet: { '.tall': 1 / 2, '.large': 1, '.half': 1 / 2, '.full': 1 },
      mobile: { '.tall': 1, '.large': 1, '.half': 1, '.full': 1 },
    };
    for (const [sel, frac] of Object.entries(expected[w.name])) {
      expect(await share(`.tb-bento-item${sel}`), `${sel} share at ${w.name}`).toBeCloseTo(frac, 1);
    }
    await expect(page.locator('#tb-bento .kind-product .tb-product-card')).toBeVisible();
    const [cell, card] = await Promise.all([page.locator('#tb-bento .kind-product').boundingBox(), page.locator('#tb-bento .kind-product .tb-product-card').boundingBox()]);
    expect(card!.y + card!.height, `the product card is not clipped at ${w.name}`).toBeLessThanOrEqual(cell!.y + cell!.height);
    await expect(page.locator('#tb-bento .kind-metric .tb-bento-metric-value').first()).toHaveText('۱۲٬۰۰۰+');
    const cd = page.locator('#tb-bento .kind-countdown .tb-countdown');
    const s0 = await cd.locator('.tb-countdown__s').textContent();
    await expect.poll(() => cd.locator('.tb-countdown__s').textContent(), { timeout: 3000 }).not.toBe(s0);
    await expect(cd.locator('.tb-countdown__d')).toHaveText(/^[۰-۹]{2}$/);
    await page.locator('#tb-bento').screenshot({ path: `test-results/phase3b-bento-${w.name}.png` });

    // ── Reviews ──────────────────────────────────────────────────────
    const rev = page.locator('#tb-rev');
    await rev.scrollIntoViewIfNeeded();
    await expect(rev.locator('.tb-rating-average')).toHaveText('۴٫۰');
    await expect(rev.locator('.tb-rating-row').first()).toContainText('۵ ستاره');
    const bar5 = await rev.locator('.tb-rating-bar > span').first().evaluate((el) => el.getBoundingClientRect().width / el.parentElement!.getBoundingClientRect().width);
    expect(bar5).toBeCloseTo(1 / 3, 1);
    await expect(rev.locator('.tb-review-verified')).toHaveCount(2);
    await expect(rev.locator('.tb-review-date').first()).toHaveText('۲ آبان ۱۴۰۵');
    await expect(rev.locator('.tb-review-stars').nth(1)).toHaveAttribute('aria-label', 'امتیاز ۵ از ۵');
    await rev.screenshot({ path: `test-results/phase3b-reviews-${w.name}.png` });

    // ── Pricing ──────────────────────────────────────────────────────
    const price = page.locator('#tb-price');
    await price.scrollIntoViewIfNeeded();
    const basic = price.locator('.tb-pricing-card').first();
    await expect(basic.locator('.tb-plan-price:visible')).toHaveText('۹۹ هزار');
    await expect(price.locator('.tb-plan-ribbon')).toHaveText('محبوب‌ترین');
    await price.locator('[data-tb-billing="yearly"]').click();
    await expect(price.locator('[data-tb-billing="yearly"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(price.locator('[data-tb-billing="monthly"]')).toHaveAttribute('aria-pressed', 'false');
    await expect(basic.locator('.tb-plan-price:visible')).toHaveText('۹۵۰ هزار');
    await expect(basic.locator('.tb-plan-period:visible')).toHaveText('/ سال');
    await expect(price.locator('.tb-pricing-card').nth(2).locator('.tb-plan-price:visible')).toHaveText('تماس بگیرید');
    await price.screenshot({ path: `test-results/phase3b-pricing-yearly-${w.name}.png` });
    await price.locator('[data-tb-billing="monthly"]').click();
    await expect(basic.locator('.tb-plan-price:visible')).toHaveText('۹۹ هزار');

    const tip = basic.locator('.tb-tip');
    const bubble = tip.locator('[role="tooltip"]');
    await expect(bubble).toBeHidden();
    if (w.name === 'desktop') {
      await tip.locator('.tb-tip-trigger').hover();
      await expect(bubble).toBeVisible();
      await page.keyboard.press('Escape');
      await expect(bubble).toBeHidden(); // dismissed while still hovered (WCAG 1.4.13)
      await page.mouse.move(0, 0);
      await tip.locator('.tb-tip-trigger').hover();
      await expect(bubble).toBeVisible();
      await page.mouse.move(0, 0);
    } else {
      await tip.locator('.tb-tip-trigger').click(); // a tap toggles it
      await expect(bubble).toBeVisible();
      const [b, card] = await Promise.all([bubble.boundingBox(), basic.boundingBox()]);
      expect(b!.x).toBeGreaterThanOrEqual(card!.x - 1);
      expect(b!.x + b!.width).toBeLessThanOrEqual(card!.x + card!.width + 1);
      await page.locator('#tb-price .tb-pricing-header').click();
      await expect(bubble).toBeHidden();
    }
    await expect(basic.locator('.tb-plan-feature.is-excluded .tb-icon-svg-x')).toHaveCount(1);
    await page.locator('#tb-price .tb-pricing-card').nth(1).screenshot({ path: `test-results/phase3b-pricing-popular-${w.name}.png` });

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
  }

  expect(errors).toEqual([]);
});
