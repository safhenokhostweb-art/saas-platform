import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = process.cwd();

function renderFullSolarHomePage(): string {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\\SaaSPlatform\\ThemeRuntime\\CommercialBlockTypes::register();

$styles = Database\\Seeders\\SolarIndustryThemeSeeder::globalStyles(app(App\\Services\\ThemeBuilder\\ThemeBuilderService::class)->defaultGlobalStyles());
$tokens = (new SaaSPlatform\\ThemeRuntime\\ThemeTokenNormalizer())->renderCssBlock($styles);

$headerProps = SaaSPlatform\\ThemeRuntime\\TemplateLibrary::find('lib:solar-header')['blocks'][0]['props'];
$footerProps = SaaSPlatform\\ThemeRuntime\\TemplateLibrary::find('lib:solar-footer')['blocks'][0]['props'];
$homeBlocks = SaaSPlatform\\ThemeRuntime\\TemplateLibrary::find('lib:solar-page-home')['blocks'];

$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$r->setMode('public');

$header = $r->renderBlock(['type' => 'nav_header', 'id' => 'solar-hdr', 'props' => $headerProps]);
$footer = $r->renderBlock(['type' => 'nav_footer', 'id' => 'solar-ftr', 'props' => $footerProps]);

$body = '';
foreach ($homeBlocks as $i => $block) {
    $body .= $r->renderBlock(['id' => 'blk-' . $i, 'type' => $block['type'], 'props' => $block['props'] ?? []]);
}

echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>قالب صنعت برق — پیش‌نمایش</title><style>'
    . view('theme-runtime::css.fonts')->render() . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . $tokens . 'body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif;background:var(--tb-background);color:var(--tb-text)}</style></head><body>'
    . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
    . '<main>' . $body . '</main>'
    . '<footer role="contentinfo">' . $footer . '</footer>'
    . '</body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: ROOT, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 });
}

test.describe('قالب صنعت برق — Full Homepage & Header Visual Parity', () => {
  let html = '';

  test.beforeAll(() => {
    html = renderFullSolarHomePage();
  });

  test('desktop 1280px: full homepage renders with 1:1 sections and takes screenshot', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.route('http://tb-solar-home.test/**', (route) => {
      route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
    });
    await page.goto('http://tb-solar-home.test/');

    // 1. Verify Header
    await expect(page.locator('#site-header .tb-nav-header-logo-title').first()).toContainText('برند شما');
    await expect(page.locator('#site-header .tb-mega-menu-trigger').first()).toBeVisible();

    // 2. Verify Hero with Stats
    await expect(page.locator('.tb-cx-hero-title')).toContainText('طراحی و اجرای سیستم‌های خورشیدی');
    await expect(page.locator('.tb-cx-stat')).toHaveCount(4);

    // 3. Verify Services (Target Audience Grid)
    await expect(page.locator('.tb-cx--audience .tb-cx-card')).toHaveCount(6);

    // 4. Verify About Us (Zigzag Rows)
    await expect(page.getByRole('heading', { name: 'دربارهٔ ما' })).toBeVisible();

    // 5. Verify Training Courses (Dual Sale Grid on Home)
    await expect(page.getByRole('heading', { name: 'دوره‌های آموزشی تخصصی' })).toBeVisible();
    await expect(page.locator('.tb-cx--products .tb-cx-product')).toHaveCount(3);
    await expect(page.locator('.tb-cx--products .tb-cx-more a')).toContainText('مشاهده تقویم آموزشی');

    // 6. Verify Recent Projects (Project Cards)
    await expect(page.getByRole('heading', { name: 'پروژه‌های اخیر' })).toBeVisible();
    await expect(page.locator('.tb-project-cards-grid .tb-project-card')).toHaveCount(3);
    await expect(page.locator('.tb-project-cards-footer a')).toContainText('مشاهده همه پروژه‌ها');

    // 7. Verify Testimonials (Static 3-Column Grid)
    await expect(page.getByRole('heading', { name: 'نظرات مشتریان و همکاران' })).toBeVisible();
    await expect(page.locator('.tb-testimonials-grid.cols-3 .tb-review-card')).toHaveCount(3);

    // 8. Verify Blog Posts
    await expect(page.getByRole('heading', { name: 'وبلاگ و مقالات تخصصی' })).toBeVisible();
    await expect(page.locator('.tb-blog-grid .tb-blog-card')).toHaveCount(3);

    // 9. Verify Contact Split
    await expect(page.getByRole('heading', { name: 'با ما در تماس باشید' })).toBeVisible();

    // 10. Verify Footer
    await expect(page.locator('footer .tb-nav-footer')).toBeVisible();

    // Open first mega menu to capture desktop mega panel
    const firstMega = page.locator('#site-header .tb-mega-menu-trigger').first();
    await firstMega.hover();
    const panel = page.locator('#site-header .tb-mega-menu-panel').first();
    await expect(panel).toBeVisible();

    // Take screenshot with open mega menu
    await page.screenshot({ path: 'test-results/solar-desktop-1280-megamenu.png', fullPage: false });

    // Close mega menu and take full page screenshot
    await page.mouse.move(0, 500);
    await page.waitForTimeout(300);
    await page.screenshot({ path: 'test-results/solar-desktop-1280-full.png', fullPage: true });
  });

  test('mobile 360px RTL: responsive layout and drawer verification', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.route('http://tb-solar-home.test/**', (route) => {
      route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
    });
    await page.goto('http://tb-solar-home.test/');

    // Verify toggle button is visible on phone
    const toggle = page.locator('#tb-solar-hdr .tb-nav-header-toggle');
    await expect(toggle).toBeVisible();

    // Take mobile homepage screenshot
    await page.screenshot({ path: 'test-results/solar-mobile-360-full.png', fullPage: true });

    // Open mobile drawer
    await toggle.click();
    const drawer = page.locator('#tb-solar-hdr .tb-nav-header-links');
    await expect(drawer).toBeVisible();
    await page.screenshot({ path: 'test-results/solar-mobile-360-drawer.png', fullPage: false });
  });
});
