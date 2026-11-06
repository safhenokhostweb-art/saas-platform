import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * nav_header with search_mode=icon.
 *
 *   - with host search routes (the storefront) the icon opens the site-forms
 *     search dialog and only that: the local popover stays hidden;
 *   - without them (the Builder preview) the icon opens the local popover,
 *     which stays inside the viewport at every width.
 */

const ORIGIN = 'http://tb-nav-icon-search.test';
const WIDTHS = [
  { name: 'desktop', width: 1366 },
  { name: 'canvas', width: 928 },
  { name: 'mobile', width: 390 },
] as const;

function renderDocument(withRoutes: boolean): string {
  const php = process.env.PHP_BINARY || 'php';
  const context = withRoutes
    ? `['site_endpoints' => ['search' => ['all' => ['action' => '/search'], 'products' => ['action' => '/search', 'params' => ['type' => 'products']]]]]`
    : '[]';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$r->setContext(${context});
$blocks = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => [
    'logo_text' => 'همراه کسب و کار ایرانی', 'menu_align' => 'center', 'show_search' => true, 'search_mode' => 'icon',
    'show_cart' => true, 'show_account' => true, 'cta_text' => 'شروع کنید', 'cta_url' => '/start',
    'nav_links' => [['label' => 'خانه', 'url' => '/'], ['label' => 'پلن ها', 'url' => '/shop'], ['label' => 'مطالب', 'url' => '/blog'], ['label' => 'تماس با ما', 'url' => '/contact']],
]]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;font-family:Tahoma,sans-serif}</style></head><body>' . $blocks . '<main style="height:120vh"></main></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
}

async function serve(page: Page, html: string) {
  await page.route(`${ORIGIN}/**`, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: html }));
  await page.goto(`${ORIGIN}/`);
}

test('the header search icon opens only the search dialog on the storefront', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await serve(page, renderDocument(true));

  const toggle = page.locator('.tb-nav-header-search-toggle');
  const popover = page.locator('.tb-nav-header-search-popover');
  const dialog = page.locator('.tb-search-overlay');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 800 });
    await toggle.click();
    await expect(dialog, `dialog opens at ${w.name}`).toBeVisible();
    await expect(dialog.locator('.tb-search-overlay__input')).toBeFocused();
    await expect(popover, `popover stays closed at ${w.name}`).toBeHidden();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(toggle, 'focus returns to the icon').toBeFocused();
    await expect(popover).toBeHidden();
  }
  expect(errors).toEqual([]);
});

test('without host search the icon opens the popover inside the viewport', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await serve(page, renderDocument(false));

  const toggle = page.locator('.tb-nav-header-search-toggle');
  const popover = page.locator('.tb-nav-header-search-popover');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 800 });
    await toggle.click();
    await expect(popover, `popover opens at ${w.name}`).toBeVisible();
    await expect(page.locator('.tb-search-overlay')).toHaveCount(0);
    const box = await popover.boundingBox();
    expect(box, `popover box at ${w.name}`).not.toBeNull();
    expect(box!.x, `popover left edge at ${w.name}`).toBeGreaterThanOrEqual(0);
    expect(box!.x + box!.width, `popover right edge at ${w.name}`).toBeLessThanOrEqual(w.width);
    await page.keyboard.press('Escape');
    await expect(popover).toBeHidden();
  }
  expect(errors).toEqual([]);
});
