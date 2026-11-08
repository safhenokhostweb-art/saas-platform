import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * ADR-082 step 3 — the nav_header Style tab on the real renderer output:
 * container (height, padding, full width, border, shadow, backdrop blur),
 * menu typography per device, Normal / Hover / Active colours with the
 * current-page item marked, and every pointer style. Defaults are covered
 * by header-pixel-parity.spec.ts (a header without Style props is unchanged).
 * Screenshots go to test-results/ for review.
 */

const ORIGIN = 'http://tb-header-style.test';
const ROOT = process.cwd();
const FONTS = path.join(ROOT, 'public/assets/fonts/vazirmatn');
const LINKS = [['خانه', '/'], ['فروشگاه', '/shop'], ['وبلاگ', '/blog'], ['تماس', '/contact']];

function render(variants: Record<string, Record<string, unknown>>): Record<string, string> {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$variants = json_decode(stream_get_contents(STDIN), true);
$out = [];
foreach ($variants as $name => $props) {
    $r = new App\\Services\\ThemeBuilder\\BlockRenderer();
    $r->setMode('public');
    $header = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => $props]);
    $out[$name] = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
        . view('theme-runtime::css.fonts')->render() . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
        . 'body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif}</style></head><body>'
        . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header" style="position:sticky;top:0;z-index:50">' . $header . '</header>'
        . '<main style="height:150vh;background:repeating-linear-gradient(45deg,#f97316 0 24px,#2563eb 24px 48px)"></main></body></html>';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`;
  return JSON.parse(execFileSync(php, ['-r', source], { cwd: ROOT, encoding: 'utf8', input: JSON.stringify(variants), maxBuffer: 32 * 1024 * 1024 }));
}

async function serve(page: Page, html: string): Promise<void> {
  await page.unrouteAll({ behavior: 'ignoreErrors' });
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) return route.abort();
    if (url.pathname.startsWith('/assets/fonts/vazirmatn/')) return route.fulfill({ contentType: 'font/woff2', body: fs.readFileSync(path.join(FONTS, path.basename(url.pathname))) });
    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
  });
}

async function open(page: Page, html: string, width: number, at = '/'): Promise<void> {
  await serve(page, html);
  await page.setViewportSize({ width, height: 640 });
  await page.goto(ORIGIN + at);
  await page.evaluate(() => document.fonts.ready);
}

const css = (page: Page, selector: string, props: string[], pseudo: string | null = null) =>
  page.locator(selector).first().evaluate((el, [p, ps]) => Object.fromEntries((p as string[]).map((k) => [k, getComputedStyle(el, ps as string | null).getPropertyValue(k)])), [props, pseudo] as const);

const links = LINKS.map(([label, url]) => ({ label, url }));

test('container: height and padding per device, full width, border, shadow and backdrop blur', async ({ page }) => {
  const pages = render({
    container: {
      logo_text: 'برند', nav_links: links,
      min_height: 88, min_height_mobile: 64,
      padding: { unit: 'px', linked: false, 'block-start': 6, 'block-end': 10, 'inline-start': 40, 'inline-end': 32 },
      padding_mobile: { unit: 'px', linked: false, 'inline-start': 12, 'inline-end': 12 },
      content_width: 'full', border_bottom_width: 2, border_bottom_color: '#0ea5e9',
      header_shadow: { x: 0, y: 6, blur: 18, spread: -4, color: 'rgba(15, 23, 42, 0.25)' },
      backdrop_blur: 12, bg_color: 'rgba(255, 255, 255, 0.6)',
    },
  });
  await open(page, pages.container, 1280);
  expect(await css(page, '#tb-hdr .tb-nav-header-inner', ['min-height', 'padding-top', 'padding-bottom', 'padding-right', 'padding-left'])).toEqual({
    'min-height': '88px', 'padding-top': '6px', 'padding-bottom': '10px', 'padding-right': '40px', 'padding-left': '32px', // RTL: inline-start = right
  });
  const widths = await page.evaluate(() => [document.querySelector('#tb-hdr .tb-nav-header-inner')!.getBoundingClientRect().width, document.querySelector('#tb-hdr')!.getBoundingClientRect().width]);
  expect(widths[0], 'full width: the content spans the header').toBe(widths[1]);
  expect(await css(page, '#tb-hdr', ['border-bottom-width', 'border-bottom-color', 'box-shadow'])).toEqual({
    'border-bottom-width': '2px', 'border-bottom-color': 'rgb(14, 165, 233)', 'box-shadow': 'rgba(15, 23, 42, 0.25) 0px 6px 18px -4px',
  });
  expect((await css(page, '#tb-hdr', ['backdrop-filter'], '::before'))['backdrop-filter']).toBe('blur(12px)');
  await page.screenshot({ path: 'test-results/header-style-container-1280.png', clip: { x: 0, y: 0, width: 1280, height: 160 } });

  await open(page, pages.container, 360);
  expect(await css(page, '#tb-hdr .tb-nav-header-inner', ['min-height', 'padding-top', 'padding-right', 'padding-left'])).toEqual({
    'min-height': '64px', 'padding-top': '6px', 'padding-right': '12px', 'padding-left': '12px', // block inherits desktop, inline is the phone's own
  });
  // The blur layer must not trap the fixed drawer (backdrop-filter on the header would).
  await page.locator('#tb-hdr .tb-nav-header-toggle').click();
  const drawer = await page.locator('#tb-hdr .tb-nav-header-links').evaluate((el) => { const r = el.getBoundingClientRect(); return { position: getComputedStyle(el).position, top: r.top, height: Math.round(r.height) }; });
  expect(drawer).toEqual({ position: 'fixed', top: 0, height: 640 });
  await page.screenshot({ path: 'test-results/header-style-container-360-drawer.png' });
});

test('menu typography per device and Normal / Hover / Active colours on the current page', async ({ page }) => {
  const pages = render({
    menu: {
      logo_text: 'برند', nav_links: links,
      menu_typography: { size: 17, weight: '800', letterSpacing: 0.5 }, menu_typography_mobile: { size: 14 },
      menu_gap: 36, menu_item_padding_x: 6, menu_item_padding_y: 10,
      menu_color: '#334155', menu_color_hover: '#2563eb', menu_color_active: '#e11d48', menu_bg_hover: 'rgba(37, 99, 235, 0.08)',
    },
  });
  await open(page, pages.menu, 1280, '/shop');
  const shop = page.locator('#tb-hdr .tb-nav-header-link', { hasText: 'فروشگاه' });
  const blog = page.locator('#tb-hdr .tb-nav-header-link', { hasText: 'وبلاگ' });
  await expect(shop).toHaveAttribute('aria-current', 'page');
  await expect(blog).not.toHaveAttribute('aria-current', 'page');
  expect(await blog.evaluate((el) => { const c = getComputedStyle(el); return [c.fontSize, c.fontWeight, c.letterSpacing, c.color, c.paddingTop, c.paddingLeft, c.opacity]; }))
    .toEqual(['17px', '800', '0.5px', 'rgb(51, 65, 85)', '10px', '6px', '1']);
  expect(await shop.evaluate((el) => [getComputedStyle(el).color, getComputedStyle(el, '::after').transform]), 'active: colour and a full pointer')
    .toEqual(['rgb(225, 29, 72)', 'matrix(1, 0, 0, 1, 0, 0)']);
  expect(await page.locator('#tb-hdr .tb-nav-header-menu-panel').evaluate((el) => getComputedStyle(el).columnGap)).toBe('36px');
  await blog.hover();
  await expect.poll(() => blog.evaluate((el) => [getComputedStyle(el).color, getComputedStyle(el).backgroundColor])).toEqual(['rgb(37, 99, 235)', 'rgba(37, 99, 235, 0.08)']);
  await page.screenshot({ path: 'test-results/header-style-menu-1280-hover.png', clip: { x: 0, y: 0, width: 1280, height: 120 } });

  // Phones: the drawer keeps its own colours; the phone font size applies.
  await open(page, pages.menu, 360, '/shop');
  await page.locator('#tb-hdr .tb-nav-header-toggle').click();
  const drawerLink = await blog.evaluate((el) => [getComputedStyle(el).fontSize, getComputedStyle(el).color]);
  expect(drawerLink[0]).toBe('14px');
  expect(drawerLink[1], 'menu colours are desktop/tablet only').not.toBe('rgb(51, 65, 85)');
  await page.screenshot({ path: 'test-results/header-style-menu-360-drawer.png' });
});

test('every pointer style shows on hover; none and text show no line', async ({ page }) => {
  const kinds = ['underline', 'overline', 'double', 'framed', 'background', 'text', 'none'] as const;
  const pages = render(Object.fromEntries(kinds.map((p) => [p, { logo_text: 'برند', nav_links: links, pointer: p, pointer_color: '#e11d48', pointer_thickness: 3 }])));
  for (const kind of kinds) {
    await open(page, pages[kind], 1280);
    const item = page.locator('#tb-hdr .tb-nav-header-link', { hasText: 'وبلاگ' });
    await item.hover();
    await page.waitForTimeout(350);
    const after = await item.evaluate((el) => { const s = getComputedStyle(el, '::after'); return { content: s.content, opacity: s.opacity, transform: s.transform, height: s.height, bg: s.backgroundColor, borderTop: s.borderTopWidth, borderLeft: s.borderLeftWidth, borderBottom: s.borderBottomWidth, linkHeight: el.getBoundingClientRect().height }; });
    if (kind === 'none' || kind === 'text' || kind === 'background') {
      expect(after.content, kind).toBe('none');
    } else {
      expect(after.content, kind).not.toBe('none');
      expect(after.opacity, kind).toBe('1');
      expect(after.transform, kind).toBe('matrix(1, 0, 0, 1, 0, 0)');
    }
    if (kind === 'underline' || kind === 'overline') expect([after.height, after.bg], kind).toEqual(['3px', 'rgb(225, 29, 72)']);
    // Shape, not just widths: two lines around the label / a frame around it, never a solid bar.
    if (kind === 'double' || kind === 'framed') {
      expect([after.borderTop, after.borderBottom, after.bg], kind).toEqual(['3px', '3px', 'rgba(0, 0, 0, 0)']);
      expect(parseFloat(after.height), `${kind} spans the label`).toBeGreaterThan(after.linkHeight);
    }
    if (kind === 'framed') {
      expect(after.borderLeft, kind).toBe('3px');
      const box = await item.evaluate((el) => [parseFloat(getComputedStyle(el, '::after').width), el.getBoundingClientRect().width]);
      expect(box[0] - box[1], 'the frame clears the label on both sides').toBeGreaterThanOrEqual(15);
    }
    if (kind === 'background') expect(await item.evaluate((el) => getComputedStyle(el).backgroundColor), kind).not.toBe('rgba(0, 0, 0, 0)');
    await page.screenshot({ path: `test-results/header-style-pointer-${kind}.png`, clip: { x: 300, y: 0, width: 680, height: 70 } });
  }
});
