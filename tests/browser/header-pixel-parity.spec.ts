import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * ADR-082 step 2 — the header's pixel ratchet. The real renderer output of
 * five nav_header configurations, at 360, 768 and 1280px, plus the states
 * that only exist after an interaction (open drawer, search popover, mega
 * menu, scrolled sticky header), compared byte for byte with screenshots
 * taken before the header's inline <style> moved to the shared CSS, and with
 * the computed style of every element inside each header (which also covers
 * hidden parts a screenshot cannot see).
 *
 * The baselines were recorded on 6b48a301 (before the move); any later
 * header change that alters the default look fails here. Real Vazirmatn
 * fonts and a real logo image are served from disk, so the pages render
 * like a storefront.
 */

const ORIGIN = 'http://tb-header-parity.test';
const WIDTHS = [360, 768, 1280] as const;
const ROOT = process.cwd();
const FONTS = path.join(ROOT, 'public/assets/fonts/vazirmatn');
const LOGO = path.join(ROOT, 'packages/saas-platform/modules/theme-runtime/resources/library/media/mobile-store.webp');

/** variant => full HTML document, rendered by the code under test. */
function renderPages(): Record<string, string> {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$links = [['label' => 'خانه', 'url' => '/'], ['label' => 'فروشگاه', 'url' => '/shop'], ['label' => 'وبلاگ', 'url' => '/blog'], ['label' => 'درباره ما', 'url' => '/about'], ['label' => 'تماس با ما', 'url' => '/contact']];
$social = [['url' => 'https://eitaa.com/shop', 'network' => 'auto', 'label' => ''], ['url' => 'https://ble.ir/shop', 'network' => 'auto', 'label' => ''], ['url' => 'https://t.me/shop', 'network' => 'auto', 'label' => ''], ['url' => 'https://instagram.com/shop', 'network' => 'auto', 'label' => '']];
$variants = [
    'basic' => ['logo_text' => 'فروشگاه نمونه'],
    'full' => ['logo_image' => '/logo.webp', 'logo_text' => 'فروشگاه نمونه', 'logo_height' => 44, 'logo_height_mobile' => 34, 'menu_align' => 'center', 'nav_links' => $links,
        'social_links' => $social, 'social_style' => 'brand', 'phone' => '۰۲۱-۱۲۳۴۵۶۷۸', 'cta_text' => 'مشاوره رایگان', 'cta_url' => '/contact', 'cta_glow_enabled' => true,
        'show_search' => true, 'search_placeholder' => 'جستجوی محصول…', 'show_cart' => true, 'show_account' => true, 'show_dark_toggle' => true],
    'icons' => ['logo_text' => 'برند تیره', 'mobile_logo' => '/logo.webp', 'menu_align' => 'start', 'nav_links' => array_slice($links, 0, 4), 'show_search' => true, 'search_mode' => 'icon',
        'social_links' => array_slice($social, 0, 2), 'social_show_mobile' => true, 'show_cart' => true, 'bg_color' => '#0f172a', 'text_color' => '#f8fafc', 'cta_text' => 'ثبت‌نام', 'cta_url' => '/register'],
    'mega' => ['logo_text' => 'مگا استور', 'show_account' => true, 'nav_links' => [
        ['label' => 'خانه', 'url' => '/'],
        ['label' => 'دسته‌بندی‌ها', 'url' => '/shop', 'mega_columns' => [
            ['title' => 'پوشاک', 'items' => [['label' => 'مردانه', 'url' => '/m', 'icon' => 'shirt'], ['label' => 'زنانه', 'url' => '/w']]],
            ['title' => 'دیجیتال', 'items' => [['label' => 'موبایل', 'url' => '/p', 'icon' => 'smartphone'], ['label' => 'لپ‌تاپ', 'url' => '/l', 'icon' => 'laptop']]],
        ], 'mega_banner_image' => '/logo.webp', 'mega_banner_text' => 'حراج پاییزه', 'mega_banner_cta' => 'خرید کنید'],
        ['label' => 'تماس', 'url' => '/contact'],
    ]],
    'overlay' => ['logo_text' => 'آژانس نمونه', 'nav_links' => array_slice($links, 0, 4), 'sticky' => true, 'transparent_over_hero' => true, 'cta_text' => 'مشاوره', 'cta_url' => '/contact'],
];
$out = [];
foreach ($variants as $name => $props) {
    $r = new App\\Services\\ThemeBuilder\\BlockRenderer();
    $r->setMode('public');
    $header = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => $props]);
    $hero = $name === 'overlay'
        ? $r->renderBlock(['type' => 'hero', 'id' => 'hero', 'props' => ['title' => 'طراحی سایت حرفه‌ای', 'subtitle' => 'سریع، امن و واکنش‌گرا', 'background' => '#1e1b4b']])
        : '<section style="padding:2rem 1rem"><h1 style="margin:0">محتوای صفحه</h1></section>';
    $out[$name] = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
        . view('theme-runtime::css.fonts')->render() . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
        . 'body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif}</style></head><body>'
        . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
        . '<main>' . $hero . '<div style="height:150vh;background:linear-gradient(#f8fafc,#e2e8f0)"></div></main></body></html>';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`;
  return JSON.parse(execFileSync(php, ['-r', source], { cwd: ROOT, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 }));
}

async function serve(page: Page, html: string): Promise<void> {
  await page.unrouteAll({ behavior: 'ignoreErrors' });
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin === ORIGIN && url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    if (url.origin === ORIGIN && url.pathname.startsWith('/assets/fonts/vazirmatn/')) {
      return route.fulfill({ contentType: 'font/woff2', body: fs.readFileSync(path.join(FONTS, path.basename(url.pathname))) });
    }
    if (url.origin === ORIGIN && /\.(webp|png|jpe?g)$/.test(url.pathname)) return route.fulfill({ contentType: 'image/webp', body: fs.readFileSync(LOGO) });
    if (url.origin === ORIGIN) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<p>page</p>' });
    return route.abort();
  });
}

/** Computed style of every element inside the header, in DOM order. */
async function computedStyles(page: Page): Promise<string> {
  return page.evaluate(() => {
    const props = ['display', 'position', 'inset-block-start', 'inset-inline-start', 'inset-inline-end', 'z-index', 'width', 'height', 'min-width', 'min-height', 'max-width',
      'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
      'gap', 'flex-direction', 'flex-grow', 'flex-shrink', 'flex-basis', 'align-items', 'justify-content', 'grid-template-columns',
      'color', 'background-color', 'border-top-width', 'border-bottom-width', 'border-bottom-color', 'border-radius', 'box-shadow',
      'font-size', 'font-weight', 'white-space', 'overflow', 'opacity', 'visibility', 'transform', 'cursor', 'object-fit', 'animation-name'];
    const root = document.querySelector('#site-header');
    if (!root) return '';
    return [root, ...Array.from(root.querySelectorAll('*'))]
      .filter((el) => !['SCRIPT', 'STYLE'].includes(el.tagName))
      .map((el, i) => {
        const cs = getComputedStyle(el);
        return `${i} ${el.tagName.toLowerCase()}.${(el.getAttribute('class') || '').trim().split(/\s+/).join('.')} ` + props.map((p) => `${p}:${cs.getPropertyValue(p)}`).join(';');
      })
      .join('\n');
  });
}

async function settle(page: Page): Promise<void> {
  await page.evaluate(async () => {
    await document.fonts.ready;
    // A lazy image inside a closed mega panel never loads; it loads when shown.
    const pending = Array.from(document.images).filter((img) => !img.complete && img.loading !== 'lazy');
    await Promise.race([
      Promise.all(pending.map((img) => new Promise((r) => { img.onload = img.onerror = r; }))),
      new Promise((r) => setTimeout(r, 5000)),
    ]);
    await Promise.all(Array.from(document.images).filter((img) => img.complete && img.naturalWidth > 0).map((img) => img.decode().catch(() => null)));
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
  });
}

async function check(page: Page, name: string): Promise<void> {
  await settle(page);
  // Images are masked: Chrome resamples a scaled photo at low quality first
  // and refines it later, so its pixels vary under load. Their boxes (size
  // and place) stay in the comparison, and in the computed styles below.
  await expect(page).toHaveScreenshot(`${name}.png`, {
    animations: 'disabled', caret: 'hide', maxDiffPixels: 0, threshold: 0,
    mask: [page.locator('img')], maskColor: '#ff00ff',
  });
  // The CTA glow pulses forever; read the styles with reduced motion (the
  // header's own prefers-reduced-motion rules stop it), so they are stable.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await settle(page);
  expect(await computedStyles(page)).toMatchSnapshot(`${name}.styles.txt`);
  await page.emulateMedia({ reducedMotion: null });
}

const STATES: Record<string, { width: number; act: (page: Page) => Promise<void> }[]> = {
  full: [{ width: 360, act: async (page) => { await page.locator('#tb-hdr .tb-nav-header-toggle').click(); } }],
  icons: [{ width: 1280, act: async (page) => { await page.locator('#tb-hdr .tb-nav-header-search-toggle').click(); } }],
  mega: [{ width: 1280, act: async (page) => { await page.locator('#tb-hdr .tb-mega-menu-trigger').first().hover(); } }],
  overlay: [
    { width: 1280, act: async (page) => { await page.mouse.wheel(0, 500); await page.waitForFunction(() => document.querySelector('#site-header')?.classList.contains('is-scrolled')); } },
    { width: 360, act: async (page) => { await page.mouse.wheel(0, 500); await page.waitForFunction(() => document.querySelector('#site-header')?.classList.contains('is-scrolled')); } },
  ],
};

test('a header\'s own --tb-hdr-* values (HeaderStyleVars) drive its layout per device', async ({ page }) => {
  test.setTimeout(120_000);
  const pages = renderPages();
  const php = process.env.PHP_BINARY || 'php';
  const scoped = execFileSync(php, ['-r', `
require 'vendor/autoload.php';
echo SaaSPlatform\\ThemeRuntime\\Widget\\HeaderStyleVars::style('tb-hdr', ['min-h' => 96, 'gap' => '40px', 'min-h-m' => '72px', 'drawer-w' => '300px', 'backdrop' => 'rgba(255, 0, 0, 0.5)']);
`], { cwd: ROOT, encoding: 'utf8' });
  expect(scoped).toBe('<style data-tb-hdr="tb-hdr">#tb-hdr{--tb-hdr-gap:40px;--tb-hdr-min-h:96px;--tb-hdr-min-h-m:72px;--tb-hdr-drawer-w:300px;--tb-hdr-backdrop:rgba(255, 0, 0, 0.5)}</style>');
  await serve(page, pages.full.replace('</head>', `${scoped}</head>`));

  await page.setViewportSize({ width: 1280, height: 760 });
  await page.goto(`${ORIGIN}/`);
  await settle(page);
  const inner = page.locator('#tb-hdr .tb-nav-header-inner');
  expect(await inner.evaluate((el) => [getComputedStyle(el).minHeight, getComputedStyle(el).columnGap])).toEqual(['96px', '40px']);

  await page.setViewportSize({ width: 360, height: 700 });
  await page.goto(`${ORIGIN}/`);
  await settle(page);
  expect(await inner.evaluate((el) => getComputedStyle(el).minHeight), 'the phone value').toBe('72px');
  await page.locator('#tb-hdr .tb-nav-header-toggle').click();
  expect(await page.locator('#tb-hdr .tb-nav-header-links').evaluate((el) => getComputedStyle(el).width)).toBe('300px');
  expect(await page.locator('#tb-hdr .tb-nav-header-overlay').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgba(255, 0, 0, 0.5)');
});

test('nav_header looks exactly as before: 5 configurations × 360/768/1280 and interaction states', async ({ page }) => {
  test.setTimeout(900_000);
  const problems: string[] = [];
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  const pages = renderPages();
  expect(Object.keys(pages)).toEqual(['basic', 'full', 'icons', 'mega', 'overlay']);

  for (const [variant, html] of Object.entries(pages)) {
    await serve(page, html);
    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: width === 1280 ? 760 : 700 });
      await page.goto(`${ORIGIN}/`);
      await check(page, `${variant}-${width}`);
    }
    for (const [i, state] of (STATES[variant] ?? []).entries()) {
      await page.setViewportSize({ width: state.width, height: state.width === 1280 ? 760 : 700 });
      await page.goto(`${ORIGIN}/`);
      await settle(page);
      await state.act(page);
      await check(page, `${variant}-${state.width}-state${i + 1}`);
    }
  }
  expect(problems, problems.join('\n')).toEqual([]);
});
