import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * ADR-082 step 4 — the header's Advanced tab on the real renderer output:
 * the sticky header's scrolled look (colours, own shadow, shrink height,
 * logo scale, threshold, offset, devices, no-shrink), the burger breakpoint
 * (tablet / always / never) and the phone drawer (full, dropdown, end side,
 * colours, burger). The default look is header-pixel-parity.spec.ts.
 */

const ORIGIN = 'http://tb-header-behavior.test';
const ROOT = process.cwd();
const FONTS = path.join(ROOT, 'public/assets/fonts/vazirmatn');
const LOGO = path.join(ROOT, 'packages/saas-platform/modules/theme-runtime/resources/library/media/mobile-store.webp');
const links = [['خانه', '/'], ['فروشگاه', '/shop'], ['وبلاگ', '/blog'], ['تماس', '/contact']].map(([label, url]) => ({ label, url }));

function render(variants: Record<string, Record<string, unknown>>): Record<string, string> {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$out = [];
foreach (json_decode(stream_get_contents(STDIN), true) as $name => $props) {
    $r = new App\\Services\\ThemeBuilder\\BlockRenderer();
    $r->setMode('public');
    $header = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => $props]);
    $out[$name] = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
        . view('theme-runtime::css.fonts')->render() . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
        . 'body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif}</style></head><body>'
        . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
        . '<main style="height:300vh;background:linear-gradient(#f8fafc,#cbd5e1)"><p style="padding:2rem">محتوا</p></main></body></html>';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`;
  return JSON.parse(execFileSync(php, ['-r', source], { cwd: ROOT, encoding: 'utf8', input: JSON.stringify(variants), maxBuffer: 32 * 1024 * 1024 }));
}

async function open(page: Page, html: string, width: number, height = 700): Promise<void> {
  await page.unrouteAll({ behavior: 'ignoreErrors' });
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) return route.abort();
    if (url.pathname.startsWith('/assets/fonts/vazirmatn/')) return route.fulfill({ contentType: 'font/woff2', body: fs.readFileSync(path.join(FONTS, path.basename(url.pathname))) });
    if (url.pathname === '/logo.webp') return route.fulfill({ contentType: 'image/webp', body: fs.readFileSync(LOGO) });
    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
  });
  await page.setViewportSize({ width, height });
  await page.goto(`${ORIGIN}/`);
  await page.evaluate(() => document.fonts.ready);
}

async function scrollTo(page: Page, y: number): Promise<void> {
  await page.evaluate((top) => window.scrollTo(0, top), y);
  await page.waitForTimeout(400); // rAF update + the .25s transitions
}

const banner = (page: Page) => page.locator('#site-header');
const block = (page: Page) => page.locator('#tb-hdr');

test('sticky: threshold, offset, scrolled colours, own shadow, shrink height and logo scale', async ({ page }) => {
  const pages = render({
    sticky: {
      logo_text: 'برند', logo_image: '/logo.webp', logo_height: 44, nav_links: links,
      sticky: true, scroll_threshold: 200, sticky_offset: 16,
      scrolled_bg_color: '#0f172a', scrolled_text_color: '#f8fafc', scrolled_shadow: { x: 0, y: 8, blur: 24, spread: -6, color: 'rgba(15, 23, 42, 0.4)' },
      sticky_shrink_height: 52, sticky_logo_scale: 60,
    },
    noshrink: { logo_text: 'برند', nav_links: links, sticky: true, sticky_shrink: false },
  });
  await open(page, pages.sticky, 1280);
  await expect(banner(page)).toHaveClass(/tb-hdr-sticky/);
  expect(await banner(page).evaluate((el) => getComputedStyle(el).top), 'sticky offset').toBe('16px');

  await scrollTo(page, 120);
  await expect(banner(page), 'below the 200px threshold').not.toHaveClass(/is-scrolled/);
  await scrollTo(page, 320);
  await expect(banner(page)).toHaveClass(/is-scrolled/);
  expect(await block(page).evaluate((el) => [getComputedStyle(el).backgroundColor, getComputedStyle(el).color, getComputedStyle(el).boxShadow])).toEqual([
    'rgb(15, 23, 42)', 'rgb(248, 250, 252)', 'rgba(15, 23, 42, 0.4) 0px 8px 24px -6px',
  ]);
  expect(await banner(page).evaluate((el) => getComputedStyle(el).boxShadow), 'the default banner shadow gives way').toBe('none');
  expect(await page.locator('#tb-hdr .tb-nav-header-inner').evaluate((el) => getComputedStyle(el).minHeight)).toBe('52px');
  expect(Math.round(await page.locator('#tb-hdr .tb-nav-header-logo-img').evaluate((el) => el.getBoundingClientRect().height) * 10) / 10, '44 × 60%').toBe(26.4);
  await page.screenshot({ path: 'test-results/header-behavior-sticky-scrolled.png', clip: { x: 0, y: 0, width: 1280, height: 120 } });

  await open(page, pages.noshrink, 1280);
  await scrollTo(page, 300);
  await expect(banner(page)).toHaveClass(/is-scrolled/);
  await expect(banner(page)).toHaveClass(/tb-hdr-no-shrink/);
  expect(await page.locator('#tb-hdr .tb-nav-header-inner').evaluate((el) => getComputedStyle(el).minHeight), 'no shrink').toBe('68px');
});

test('sticky only from the tablet / desktop width up', async ({ page }) => {
  const pages = render({
    desktop: { logo_text: 'برند', nav_links: links, sticky: true, sticky_devices: 'desktop' },
    tablet: { logo_text: 'برند', nav_links: links, sticky: true, sticky_devices: 'desktop_tablet', sticky_hide_on_scroll: true },
  });
  await open(page, pages.desktop, 1280);
  await expect(banner(page)).toHaveClass(/tb-hdr-sticky/);
  await page.setViewportSize({ width: 900, height: 700 });
  await expect(banner(page), 'not sticky below 1024px').not.toHaveClass(/tb-hdr-sticky/);
  expect(await banner(page).evaluate((el) => getComputedStyle(el).position)).not.toBe('sticky');

  await open(page, pages.tablet, 900);
  await expect(banner(page)).toHaveClass(/tb-hdr-sticky/);
  await expect(banner(page)).toHaveClass(/tb-hdr-smart/);
  await page.setViewportSize({ width: 360, height: 700 });
  await expect(banner(page)).not.toHaveClass(/tb-hdr-sticky/);
  await expect(banner(page)).not.toHaveClass(/tb-hdr-smart/);
});

test('burger breakpoint: tablet, always and never', async ({ page }) => {
  const pages = render({
    tablet: { logo_text: 'برند', nav_links: links, burger_breakpoint: 'tablet', menu_color: '#e11d48', drawer_text: '#0f172a' },
    always: { logo_text: 'برند', nav_links: links, burger_breakpoint: 'always' },
    never: { logo_text: 'برند', nav_links: links, burger_breakpoint: 'never' },
  });
  const toggle = (page: Page) => page.locator('#tb-hdr .tb-nav-header-toggle');
  const menu = (page: Page) => page.locator('#tb-hdr .tb-nav-header-links');

  await open(page, pages.tablet, 900);
  await expect(toggle(page)).toBeVisible();
  await expect(menu(page)).toBeHidden();
  await toggle(page).click();
  expect(await menu(page).evaluate((el) => getComputedStyle(el).position)).toBe('fixed');
  const shop = page.locator('#tb-hdr .tb-nav-header-link', { hasText: 'فروشگاه' });
  expect(await shop.evaluate((el) => getComputedStyle(el).color), 'the drawer keeps its own text colour, not the inline menu colour').toBe('rgb(15, 23, 42)');
  await page.screenshot({ path: 'test-results/header-behavior-burger-tablet-900.png' });
  await page.setViewportSize({ width: 1280, height: 700 });
  await expect(page.locator('#tb-hdr .tb-nav-header-root')).not.toHaveAttribute('data-menu-open', 'true');
  await expect(toggle(page)).toBeHidden();
  await expect(menu(page)).toBeVisible();
  expect(await shop.evaluate((el) => getComputedStyle(el).color), 'inline at desktop: the menu colour').toBe('rgb(225, 29, 72)');

  await open(page, pages.always, 1280);
  await expect(toggle(page)).toBeVisible();
  await expect(menu(page)).toBeHidden();
  await toggle(page).click();
  await expect(menu(page)).toBeVisible();
  expect(await menu(page).evaluate((el) => [getComputedStyle(el).position, Math.round(el.getBoundingClientRect().height)])).toEqual(['fixed', 700]);
  await page.keyboard.press('Escape');
  await expect(page.locator('#tb-hdr .tb-nav-header-root')).toHaveAttribute('data-menu-open', 'false');

  await open(page, pages.never, 360);
  await expect(toggle(page)).toBeHidden();
  await expect(menu(page)).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'the inline menu scrolls inside itself').toBeLessThanOrEqual(0);
  // A row of plain links, not the drawer look: no close button, one line, no dividers.
  await expect(page.locator('#tb-hdr .tb-nav-header-menu-close')).toBeHidden();
  const row = await page.evaluate(() => {
    const items = Array.from(document.querySelectorAll('#tb-hdr .tb-nav-header-menu-panel .tb-nav-header-link')).map((a) => a.getBoundingClientRect());
    return { tops: new Set(items.map((r) => Math.round(r.top))).size, border: getComputedStyle(document.querySelector('#tb-hdr .tb-nav-header-link')!).borderBottomWidth, dir: getComputedStyle(document.querySelector('#tb-hdr .tb-nav-header-menu-panel')!).flexDirection };
  });
  expect(row).toEqual({ tops: 1, border: '0px', dir: 'row' });
  await page.screenshot({ path: 'test-results/header-behavior-burger-never-360.png', clip: { x: 0, y: 0, width: 360, height: 120 } });
});

test('drawer style, side and colours, and the burger button', async ({ page }) => {
  const base = { logo_text: 'برند', nav_links: links };
  const pages = render({
    full: { ...base, drawer_style: 'full', drawer_bg: '#0f172a', drawer_text: '#f8fafc', drawer_divider_color: '#334155', drawer_overlay_color: 'rgba(225, 29, 72, 0.5)' },
    dropdown: { ...base, drawer_style: 'dropdown' },
    end: { ...base, drawer_side: 'end', drawer_width: { size: 80, unit: '%' }, burger_color: '#e11d48', burger_size: 30 },
  });
  const menu = (page: Page) => page.locator('#tb-hdr .tb-nav-header-links');
  const toggle = (page: Page) => page.locator('#tb-hdr .tb-nav-header-toggle');

  await open(page, pages.full, 360);
  await toggle(page).click();
  const full = await menu(page).evaluate((el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return { x: r.x, w: r.width, bg: s.backgroundColor, color: s.color }; });
  expect(full).toEqual({ x: 0, w: 360, bg: 'rgb(15, 23, 42)', color: 'rgb(248, 250, 252)' });
  expect(await page.locator('#tb-hdr .tb-nav-header-link').first().evaluate((el) => getComputedStyle(el).borderBottomColor)).toBe('rgb(51, 65, 85)');
  await page.screenshot({ path: 'test-results/header-behavior-drawer-full.png' });

  await open(page, pages.dropdown, 360);
  await toggle(page).click();
  const drop = await page.evaluate(() => {
    const m = document.querySelector('#tb-hdr .tb-nav-header-links')!.getBoundingClientRect();
    const h = document.querySelector('#tb-hdr .tb-nav-header-root')!.getBoundingClientRect();
    return { position: getComputedStyle(document.querySelector('#tb-hdr .tb-nav-header-links')!).position, top: Math.round(m.top), headerBottom: Math.round(h.bottom), w: Math.round(m.width), headerW: Math.round(h.width) };
  });
  // Directly under the header, as wide as the header (here the host chrome pads it to 328px).
  expect(drop).toEqual({ position: 'absolute', top: drop.headerBottom, headerBottom: drop.headerBottom, w: drop.headerW, headerW: drop.headerW });
  expect(drop.w).toBeGreaterThan(300);
  await page.screenshot({ path: 'test-results/header-behavior-drawer-dropdown.png' });

  await open(page, pages.end, 360);
  expect(await toggle(page).evaluate((el) => [getComputedStyle(el).color, Math.round(el.querySelector('svg')!.getBoundingClientRect().width)])).toEqual(['rgb(225, 29, 72)', 30]);
  await toggle(page).click();
  const end = await menu(page).evaluate((el) => { const r = el.getBoundingClientRect(); return { left: Math.round(r.left), w: Math.round(r.width) }; });
  expect(end, 'RTL end side = the left edge, 80% wide').toEqual({ left: 0, w: 288 });
  await page.screenshot({ path: 'test-results/header-behavior-drawer-end.png' });
});
