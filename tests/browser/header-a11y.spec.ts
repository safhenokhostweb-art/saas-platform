import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * ADR-082 step 5 — the header's accessibility on the real renderer output,
 * driven from the keyboard: the phone drawer is a labelled modal dialog
 * while open (focus moves in, Tab stays in, Escape closes and returns focus),
 * every link and button shows a focus ring, the mega menu opens and is
 * walked with the arrow keys, a menu without links renders no burger or
 * drawer, and the sticky banner takes its own z-index. The default look is
 * header-pixel-parity.spec.ts.
 */

const ORIGIN = 'http://tb-header-a11y.test';
const ROOT = process.cwd();
const FONTS = path.join(ROOT, 'public/assets/fonts/vazirmatn');
const links = [['خانه', '/'], ['فروشگاه', '/shop'], ['وبلاگ', '/blog'], ['تماس', '/contact']].map(([label, url]) => ({ label, url }));
const mega = {
  label: 'محصولات', url: '/shop', mega_show_desktop: true, mega_mobile_behavior: 'accordion',
  mega_columns: [{ title: 'دسته‌ها', items: [{ label: 'موبایل', url: '/shop/m' }, { label: 'لپ‌تاپ', url: '/shop/l' }, { label: 'ساعت', url: '/shop/w' }] }],
};

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
        . '<a href="#main" id="skip">پرش به محتوا</a>'
        . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
        . '<main id="main" style="height:300vh;background:#f8fafc"><p style="padding:2rem"><a href="#x" id="after">پس از هدر</a></p></main></body></html>';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`;
  return JSON.parse(execFileSync(php, ['-r', source], { cwd: ROOT, encoding: 'utf8', input: JSON.stringify(variants), maxBuffer: 32 * 1024 * 1024 }));
}

async function open(page: Page, html: string, width: number, height = 760): Promise<void> {
  await page.unrouteAll({ behavior: 'ignoreErrors' });
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) return route.abort();
    if (url.pathname.startsWith('/assets/fonts/vazirmatn/')) return route.fulfill({ contentType: 'font/woff2', body: fs.readFileSync(path.join(FONTS, path.basename(url.pathname))) });
    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
  });
  await page.setViewportSize({ width, height });
  await page.goto(`${ORIGIN}/`);
  await page.evaluate(() => document.fonts.ready);
}

/** Starts keyboard navigation from the skip link, as a keyboard user would. */
async function tabFromStart(page: Page): Promise<void> {
  await page.locator('#skip').focus();
}

/** Presses Tab until the focused element matches the selector (bounded). */
async function tabTo(page: Page, selector: string, max = 25): Promise<void> {
  for (let i = 0; i < max; i++) {
    await page.keyboard.press('Tab');
    if (await page.evaluate((s) => !!document.activeElement?.matches(s), selector)) return;
  }
  throw new Error(`Tab never reached ${selector}`);
}

const active = (page: Page) => page.evaluate(() => {
  const el = document.activeElement as HTMLElement | null;
  if (!el) return null;
  const cs = getComputedStyle(el);
  return { cls: el.className, text: (el.textContent || '').trim(), inPanel: !!el.closest('.tb-nav-header-menu-panel'), outline: `${cs.outlineStyle} ${cs.outlineWidth}`, outlineColor: cs.outlineColor, color: cs.color };
});

test('phone drawer from the keyboard: a labelled modal dialog that keeps and returns focus', async ({ page }) => {
  const html = render({ named: { logo_text: 'برند', nav_links: [...links, mega], menu_aria_label: 'منوی فروشگاه', show_cart: true } });
  await open(page, html.named, 360);
  const panel = page.locator('#tb-hdr-menu-panel');
  const toggle = page.locator('#tb-hdr .tb-nav-header-toggle');
  await expect(page.locator('#tb-hdr nav.tb-nav-header-links')).toHaveAttribute('aria-label', 'منوی فروشگاه');
  await expect(panel).not.toHaveAttribute('role', /.+/);

  await tabFromStart(page);
  await tabTo(page, '.tb-nav-header-toggle');
  const ring = await active(page);
  expect(ring?.outline, 'the burger shows a focus ring').toBe('solid 2px');
  expect(ring?.outlineColor).toBe(ring?.color);

  await page.keyboard.press('Enter');
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(panel).toHaveAttribute('role', 'dialog');
  await expect(panel).toHaveAttribute('aria-modal', 'true');
  await expect(panel).toHaveAttribute('aria-label', 'منوی فروشگاه');
  expect((await active(page))?.cls, 'focus moves to the close button').toContain('tb-nav-header-menu-close');
  // The rest of the header is inert behind the modal drawer (not focusable, not read, not clickable).
  // Everything but the burger, which stays the pointer's way to close (focus is trapped in the drawer).
  await expect(page.locator('#tb-hdr .tb-nav-header-actions > .tb-nav-header-icon-link:not(.tb-nav-header-toggle)').first()).toHaveAttribute('inert', '');
  await expect(toggle).not.toHaveAttribute('inert', /.*/);
  await expect(page.locator('#tb-hdr .tb-nav-header-logo-slot').first()).toHaveAttribute('inert', '');
  await expect(page.locator('#tb-hdr nav.tb-nav-header-links')).not.toHaveAttribute('inert', /.*/);

  // Tab and Shift+Tab never leave the open drawer (the collapsed mega panel is skipped).
  for (let i = 0; i < 12; i++) {
    await page.keyboard.press('Tab');
    expect((await active(page))?.inPanel, `Tab ${i + 1} stays in the drawer`).toBe(true);
  }
  for (let i = 0; i < 12; i++) {
    await page.keyboard.press('Shift+Tab');
    expect((await active(page))?.inPanel, `Shift+Tab ${i + 1} stays in the drawer`).toBe(true);
  }
  await tabTo(page, '.tb-nav-header-menu-panel .tb-nav-header-link');
  const linkRing = await active(page);
  expect(linkRing?.outline, 'a drawer link shows a focus ring').toBe('solid 2px');
  expect(linkRing?.outlineColor).toBe(linkRing?.color);

  await page.keyboard.press('Escape');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(panel).not.toHaveAttribute('role', /.+/);
  await expect(panel).not.toHaveAttribute('aria-modal', /.+/);
  expect((await active(page))?.cls, 'Escape returns focus to the burger').toContain('tb-nav-header-toggle');
  expect(await page.evaluate(() => document.body.style.overflow)).toBe('');
  await expect(page.locator('#tb-hdr .tb-nav-header-overlay')).toHaveAttribute('tabindex', '-1');
  expect(await page.locator('#tb-hdr [inert]').count(), 'nothing stays inert once closed').toBe(0);

  // Pointer: the burger's uncovered part and the backdrop still close the open drawer.
  const box = (await toggle.boundingBox())!;
  await toggle.click();
  await expect(panel).toHaveAttribute('role', 'dialog');
  const visible = await page.evaluate(([x0, y, w]) => {
    for (let x = x0 + 1; x < x0 + w; x += 2) if ((document.elementFromPoint(x, y) as HTMLElement | null)?.closest('.tb-nav-header-toggle')) return x;
    return null;
  }, [box.x, box.y + box.height / 2, box.width]);
  expect(visible, 'part of the burger stays visible beside the drawer').not.toBeNull();
  await page.mouse.click(visible!, box.y + box.height / 2);
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await toggle.click();
  await expect(panel).toHaveAttribute('role', 'dialog');
  await page.mouse.click(10, 500);
  await expect(toggle, 'a backdrop tap closes').toHaveAttribute('aria-expanded', 'false');
  expect(await page.locator('#tb-hdr [inert]').count()).toBe(0);
});

test('desktop: focus rings in the header colour or the set one, the CTA keeps the button ring', async ({ page }) => {
  const html = render({
    plain: { logo_text: 'برند', nav_links: links, text_color: '#0f172a', cta_text: 'شروع', cta_url: '/start', show_search: true, search_mode: 'icon' },
    set: { logo_text: 'برند', nav_links: links, text_color: '#0f172a', cta_text: 'شروع', cta_url: '/start', focus_color: '#f59e0b' },
  });
  await open(page, html.plain, 1280);
  await tabFromStart(page);
  await tabTo(page, '.tb-nav-header-link');
  expect(await active(page)).toMatchObject({ outline: 'solid 2px', outlineColor: 'rgb(15, 23, 42)' });
  await tabTo(page, '.tb-nav-header-search-toggle');
  expect((await active(page))?.outline).toBe('solid 2px');
  await tabTo(page, '.tb-nav-header-cta');
  const cta = await active(page);
  expect(cta?.outline).toBe('solid 2px');
  const sharedRing = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--tb-focus-ring').trim());
  expect(sharedRing, 'the theme contract defines the button ring').not.toBe('');
  expect(cta?.outlineColor, 'the shared button ring, not the header text colour').not.toBe('rgb(15, 23, 42)');

  // A mouse click shows no ring (the pixel-parity look).
  await page.mouse.click(10, 500);
  await page.locator('#tb-hdr .tb-nav-header-search-toggle').click();
  expect(await page.locator('#tb-hdr .tb-nav-header-search-toggle').evaluate((el) => getComputedStyle(el).outlineStyle)).toBe('none');

  await open(page, html.set, 1280);
  await tabFromStart(page);
  await tabTo(page, '.tb-nav-header-link');
  expect((await active(page))?.outlineColor).toBe('rgb(245, 158, 11)');
  await tabTo(page, '.tb-nav-header-cta');
  expect((await active(page))?.outlineColor, 'the set colour reaches the CTA too').toBe('rgb(245, 158, 11)');
});

test('mega menu from the keyboard: expanded on focus, arrows walk the panel, Escape returns', async ({ page }) => {
  const html = render({ mega: { logo_text: 'برند', nav_links: [links[0], mega, links[2]] } });
  await open(page, html.mega, 1280);
  const trigger = page.locator('#tb-hdr .tb-mega-menu-trigger');
  const panel = page.locator('#tb-hdr .tb-mega-menu-panel');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');

  await tabFromStart(page);
  await tabTo(page, '.tb-mega-menu-trigger');
  await expect(trigger, 'a focused trigger reports its open panel').toHaveAttribute('aria-expanded', 'true');
  await expect(panel).toBeVisible();

  await page.keyboard.press('ArrowDown');
  expect((await active(page))?.text).toBe('موبایل');
  await page.keyboard.press('ArrowDown');
  expect((await active(page))?.text).toBe('لپ‌تاپ');
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('ArrowDown');
  expect((await active(page))?.text, 'the last link holds').toBe('ساعت');
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('ArrowUp');
  expect((await active(page))?.cls, 'ArrowUp from the first link returns to the trigger').toContain('tb-mega-menu-trigger');

  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Escape');
  expect((await active(page))?.cls).toContain('tb-mega-menu-trigger');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect(panel).toBeHidden();
  await page.keyboard.press('Tab');
  await expect(trigger, 'leaving the item keeps it closed').toHaveAttribute('aria-expanded', 'false');
});

test('a menu without links renders no burger, drawer or navigation at any width', async ({ page }) => {
  const html = render({
    topbar: { logo_text: 'برند', nav_links: [{ label: null, url: '/' }, { label: '', url: '/shop' }], cta_text: 'شروع کنید', cta_url: '/start', menu_align: 'center', social_links: [{ network: 'bale', url: 'https://ble.ir/x' }] },
  });
  for (const width of [360, 768, 1280]) {
    await open(page, html.topbar, width);
    await expect(page.locator('#tb-hdr .tb-nav-header-toggle'), `${width}: no burger`).toHaveCount(0);
    await expect(page.locator('#tb-hdr nav'), `${width}: no navigation landmark`).toHaveCount(0);
    await expect(page.locator('#tb-hdr .tb-nav-header-overlay')).toHaveCount(0);
    await expect(page.locator('#tb-hdr .tb-nav-header-cta')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), `${width}: no overflow`).toBe(0);
    // Keyboard: from the skip link, Tab goes through the header's own controls and on to the page.
    await tabFromStart(page);
    await tabTo(page, '#after', 8);
  }
});

test('the sticky banner takes its own z-index; the default stays 900', async ({ page }) => {
  const html = render({
    plain: { logo_text: 'برند', nav_links: links, sticky: true },
    raised: { logo_text: 'برند', nav_links: links, sticky: true, z_index: 1200 },
  });
  await open(page, html.plain, 1280);
  expect(await page.locator('#site-header').evaluate((el) => getComputedStyle(el).zIndex)).toBe('900');
  await open(page, html.raised, 1280);
  expect(await page.locator('#site-header').evaluate((el) => getComputedStyle(el).zIndex)).toBe('1200');
  await expect(page.locator('#site-header')).toHaveClass(/tb-hdr-sticky/);
});
