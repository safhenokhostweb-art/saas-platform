import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * ADR-084 — the «قالب صنعت برق» header: the canonical nav_header rendered from
 * the library blueprint (lib:solar-header) with the solar-dark tokens, by the
 * code under test. Desktop: one row that never wraps from 1024px up, five
 * floating-card mega menus (three columns, boxed icons, badges, promo cards)
 * and the pill pointer. Tablet and phone: the RTL side drawer with its logo
 * head, search, accordion and buttons. Screenshots are attached to the report.
 */

const ORIGIN = 'http://tb-solar-header.test';
const ROOT = process.cwd();
const FONTS = path.join(ROOT, 'public/assets/fonts/vazirmatn');
const HDR = '#tb-solar';

function renderPage(): string {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$styles = Database\\Seeders\\SolarIndustryThemeSeeder::globalStyles(app(App\\Services\\ThemeBuilder\\ThemeBuilderService::class)->defaultGlobalStyles());
$tokens = (new SaaSPlatform\\ThemeRuntime\\ThemeTokenNormalizer())->renderCssBlock($styles);
$props = SaaSPlatform\\ThemeRuntime\\TemplateLibrary::find('lib:solar-header')['blocks'][0]['props'];
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$r->setMode('public');
$header = $r->renderBlock(['type' => 'nav_header', 'id' => 'solar', 'props' => $props]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
    . view('theme-runtime::css.fonts')->render() . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . $tokens . 'body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif;background:var(--tb-background);color:var(--tb-text)}</style></head><body>'
    . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
    . '<main><section style="padding:2rem 1rem"><h1 style="margin:0">محتوای صفحه</h1><p>متن نمونه برای دیدن مگامنو روی محتوا.</p></section><div style="height:150vh"></div></main></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: ROOT, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 });
}

let HTML = '';
test.beforeAll(() => { HTML = renderPage(); });

async function open(page: Page, width: number, height = 860): Promise<void> {
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin === ORIGIN && url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: HTML });
    if (url.origin === ORIGIN && url.pathname.startsWith('/assets/fonts/vazirmatn/')) {
      return route.fulfill({ contentType: 'font/woff2', body: fs.readFileSync(path.join(FONTS, path.basename(url.pathname))) });
    }
    if (url.origin === ORIGIN) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<p>page</p>' });
    return route.abort();
  });
  await page.setViewportSize({ width, height });
  await page.goto(`${ORIGIN}/`);
  await page.evaluate(async () => { await document.fonts.ready; });
}

/** Every rendered box of the bar's direct parts, plus overflow facts. */
async function barGeometry(page: Page) {
  return page.locator(`${HDR} .tb-nav-header-inner`).evaluate((inner) => {
    const visible = (el: Element) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden'; };
    const parts = Array.from(inner.children).filter(visible).map((el) => { const r = el.getBoundingClientRect(); return { cls: el.className, top: r.top, bottom: r.bottom, left: r.left, right: r.right }; });
    const links = Array.from(inner.querySelectorAll('.tb-nav-header-menu-panel > .tb-nav-header-link, .tb-nav-header-menu-panel .tb-mega-menu-trigger')).filter(visible)
      .map((el) => ({ text: (el.textContent || '').trim(), height: el.getBoundingClientRect().height, top: el.getBoundingClientRect().top }));
    const actions = Array.from(inner.querySelectorAll('.tb-nav-header-actions > *')).filter(visible).map((el) => ({ cls: el.className, height: el.getBoundingClientRect().height, top: el.getBoundingClientRect().top + el.getBoundingClientRect().height / 2 }));
    const nav = inner.querySelector('.tb-nav-header-links') as HTMLElement | null;
    const panel = inner.querySelector('.tb-nav-header-menu-panel') as HTMLElement | null;
    return {
      parts, links, actions,
      innerOverflow: inner.scrollWidth - inner.clientWidth,
      // The menu's own content against the box the bar gives it (a squeezed menu overflows inside it).
      menuOverflow: nav && panel && visible(nav) ? panel.scrollWidth - nav.getBoundingClientRect().width : 0,
      pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
      innerHeight: inner.getBoundingClientRect().height,
    };
  });
}

for (const width of [1024, 1280, 1440, 1920]) {
  test(`desktop ${width}px: one row, nothing wraps or overflows`, async ({ page }, info) => {
    await open(page, width);
    const g = await barGeometry(page);
    expect(g.pageOverflow, 'no horizontal page scroll').toBeLessThanOrEqual(0);
    expect(g.innerOverflow, 'the bar fits its box').toBeLessThanOrEqual(0);
    expect(g.menuOverflow, 'the menu fits the space between the logo and the actions').toBeLessThanOrEqual(0);
    expect(g.innerHeight, 'header height = min_height 77').toBeLessThanOrEqual(78);
    expect(g.links.map((l) => l.text)).toEqual(['محصولات', 'راهکارها', 'خدمات', 'پروژه‌ها', 'آموزش', 'درباره ما', 'تماس']);
    for (const link of g.links) expect(link.height, `${link.text} stays on one line`).toBeLessThan(44);
    expect(new Set(g.links.map((l) => Math.round(l.top))).size, 'all items share one line').toBe(1);
    // search, cart, account, login, register; the burger is hidden on desktop
    expect(g.actions.length).toBe(5);
    for (const a of g.actions) expect(a.height, a.cls).toBeLessThanOrEqual(42);
    expect(Math.max(...g.actions.map((a) => a.top)) - Math.min(...g.actions.map((a) => a.top)), 'actions share one line (same vertical centre)').toBeLessThanOrEqual(1);
    // logo, menu, actions: three boxes side by side, none overlapping
    const [logo, menu, actions] = g.parts;
    expect(g.parts.length).toBe(3);
    expect(logo.left, 'RTL: the logo is right of the menu').toBeGreaterThanOrEqual(menu.right - 1);
    expect(menu.left, 'the menu is right of the actions').toBeGreaterThanOrEqual(actions.right - 1);
    await expect(page.locator(`${HDR} .tb-nav-header-toggle`)).toBeHidden();
    const tagline = page.locator(`${HDR} .tb-nav-header-logo-slot--desktop .tb-nav-header-logo-tagline`);
    const itemIcon = page.locator(`${HDR} .tb-nav-header-menu-panel .tb-nav-header-link-icon`).first();
    if (width >= 1280) {
      await expect(tagline).toBeVisible();
      await expect(itemIcon).toBeVisible();
    } else {
      await expect(tagline, 'compact bar below the 1280px scale step').toBeHidden();
      await expect(itemIcon, 'compact bar: item icons give way').toBeHidden();
    }
    await info.attach(`bar-${width}.png`, { body: await page.locator('#site-header').screenshot(), contentType: 'image/png' });
  });
}

test('desktop 1280px: every mega menu opens as a centred card with three columns, boxed icons, badges and promo cards', async ({ page }, info) => {
  const problems: string[] = [];
  page.on('pageerror', (e) => problems.push(e.message));
  await open(page, 1280);
  const triggers = page.locator(`${HDR} .tb-mega-menu-trigger`);
  await expect(triggers).toHaveCount(5);
  const expectedPromos = [2, 2, 1, 2, 2];
  for (let i = 0; i < 5; i++) {
    const item = page.locator(`${HDR} .tb-mega-menu-item`).nth(i);
    const panel = item.locator('.tb-mega-menu-panel');
    await triggers.nth(i).hover();
    await expect(panel).toBeVisible();
    await page.waitForTimeout(260); // the card's 200ms entrance
    const g = await panel.evaluate((el) => {
      const r = el.getBoundingClientRect();
      // The card hangs off the header root (inside the header's 1px bottom border), as in the reference.
      const header = (document.querySelector('#tb-solar .tb-nav-header-root') as HTMLElement).getBoundingClientRect();
      const cs = getComputedStyle(el);
      const cols = Array.from(el.querySelectorAll('.tb-mega-menu-col')).map((c) => c.getBoundingClientRect());
      const promos = Array.from(el.querySelectorAll('.tb-mega-menu-promo')).map((c) => c.getBoundingClientRect());
      const box = el.querySelector('.tb-mega-menu-link-icon') as HTMLElement;
      const titles = Array.from(el.querySelectorAll('.tb-mega-menu-col-title')).map((t) => getComputedStyle(t));
      return {
        width: r.width, left: r.left, right: r.right, top: r.top, headerBottom: header.bottom, headerCenter: header.left + header.width / 2,
        radius: cs.borderTopLeftRadius, bg: cs.backgroundColor, cols: cols.map((c) => ({ top: Math.round(c.top), left: c.left, right: c.right, bottom: c.bottom })),
        promos: promos.map((p) => ({ top: p.top, width: p.width })),
        box: box ? [box.getBoundingClientRect().width, box.getBoundingClientRect().height, getComputedStyle(box).borderTopLeftRadius] : null,
        titleBorder: titles.map((t) => t.borderBottomWidth), titleColor: titles.map((t) => t.color),
      };
    });
    expect(g.width, 'card width 920px').toBeCloseTo(920, 0);
    expect(Math.abs((g.left + g.right) / 2 - g.headerCenter), 'centred under the header').toBeLessThan(2);
    expect(g.top - g.headerBottom, 'floats 4px under the header').toBeCloseTo(4, 0);
    expect(g.left).toBeGreaterThanOrEqual(0);
    expect(g.right).toBeLessThanOrEqual(1280);
    expect(g.radius).toBe('16px');
    expect(g.bg, 'panel = --tb-surface #1E1E1E').toBe('rgb(30, 30, 30)');
    expect(g.cols.length).toBe(3);
    expect(new Set(g.cols.map((c) => c.top)).size, 'three columns side by side').toBe(1);
    expect(g.box, 'boxed 42px icons, 10px radius').toEqual([42, 42, '10px']);
    expect(new Set(g.titleBorder)).toEqual(new Set(['1px']));
    expect(new Set(g.titleColor), 'column titles in --tb-primary').toEqual(new Set(['rgb(255, 121, 0)']));
    expect(g.promos.length).toBe(expectedPromos[i]);
    const colsBottom = Math.max(...g.cols.map((c) => c.bottom));
    for (const p of g.promos) expect(p.top, 'promo cards sit under the columns').toBeGreaterThan(colsBottom);
    if (g.promos.length === 2) expect(g.promos[0].top).toBeCloseTo(g.promos[1].top, 0);
    // The pill stays on the trigger while the pointer is over its panel.
    await panel.locator('.tb-mega-menu-col').first().hover();
    await expect(panel).toBeVisible();
    const pill = await triggers.nth(i).evaluate((el) => [getComputedStyle(el).borderTopColor, getComputedStyle(el).backgroundColor, getComputedStyle(el).borderTopLeftRadius]);
    expect(pill[0], 'pill frame').not.toBe('rgba(0, 0, 0, 0)');
    expect(pill[1], 'pill background').not.toBe('rgba(0, 0, 0, 0)');
    expect(pill[2]).toBe('8px');
    await info.attach(`mega-${i + 1}-1280.png`, { body: await page.screenshot({ clip: { x: 0, y: 0, width: 1280, height: 620 } }), contentType: 'image/png' });
    await page.mouse.move(640, 840);
    await expect(panel).toBeHidden();
  }
  // Badges: «جدید», «پرفروش», «پیشنهادی» in the token colour.
  const badges = page.locator(`${HDR} .tb-mega-badge`);
  await expect(badges).toHaveText(['جدید', 'پرفروش', 'پیشنهادی']);
  expect(await badges.first().evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(255, 121, 0)');
  expect(problems).toEqual([]);
});

test('desktop 1280px: the keyboard opens a mega card, Escape closes it, and the plain links have no panel', async ({ page }) => {
  await open(page, 1280);
  const first = page.locator(`${HDR} .tb-mega-menu-trigger`).first();
  const panel = page.locator(`${HDR} .tb-mega-menu-panel`).first();
  await first.focus();
  await expect(panel, 'focus-within opens the card').toBeVisible();
  await expect(first).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('ArrowDown');
  await expect(panel.locator('.tb-mega-menu-link').first(), 'ArrowDown enters the card').toBeFocused();
  await page.keyboard.press('Escape');
  await expect(panel).toBeHidden();
  await expect(first, 'Escape returns focus to the trigger').toBeFocused();
  await expect(first).toHaveAttribute('aria-expanded', 'false');
  const plain = page.locator(`${HDR} .tb-nav-header-menu-panel > a.tb-nav-header-link`);
  await expect(plain).toHaveText(['درباره ما', 'تماس']);
  await expect(page.locator(`${HDR} .tb-nav-header-actions .tb-nav-header-cta`)).toHaveText(['ورود', 'ثبت‌نام']);
  expect(await page.locator(`${HDR} .tb-nav-header-actions .tb-nav-header-cta--second`).evaluate((el) => getComputedStyle(el).backgroundColor), 'outline button').toBe('rgba(0, 0, 0, 0)');
});

test('tablet 768px: the menu is a drawer and the bar keeps its search and buttons', async ({ page }, info) => {
  await open(page, 768);
  await expect(page.locator(`${HDR} .tb-nav-header-links`)).toBeHidden();
  await expect(page.locator(`${HDR} .tb-nav-header-toggle`)).toBeVisible();
  await expect(page.locator(`${HDR} .tb-nav-header-actions .tb-nav-header-search-toggle`)).toBeVisible();
  const g = await barGeometry(page);
  expect(g.pageOverflow).toBeLessThanOrEqual(0);
  expect(g.innerOverflow).toBeLessThanOrEqual(0);
  await page.locator(`${HDR} .tb-nav-header-toggle`).click();
  await expect(page.locator(`${HDR} .tb-nav-header-drawer-head`)).toBeVisible();
  await expect(page.locator(`${HDR} .tb-mega-menu-promos`).first()).toBeHidden();
  await info.attach('drawer-768.png', { body: await page.screenshot(), contentType: 'image/png' });
});

test('phone 360px RTL: the side drawer carries the logo, search, accordion and buttons', async ({ page }, info) => {
  const problems: string[] = [];
  page.on('pageerror', (e) => problems.push(e.message));
  await open(page, 360, 740);
  const bar = await barGeometry(page);
  expect(bar.pageOverflow, 'no horizontal page scroll at 360px').toBeLessThanOrEqual(0);
  expect(bar.innerOverflow).toBeLessThanOrEqual(0);
  expect(bar.innerHeight, 'phone height 60px').toBeCloseTo(60, 0);
  // The bar: logo, cart, account and burger; search and buttons moved into the drawer.
  await expect(page.locator(`${HDR} .tb-nav-header-actions .tb-nav-header-search-wrap`)).toBeHidden();
  await expect(page.locator(`${HDR} .tb-nav-header-actions .tb-nav-header-cta`).first()).toBeHidden();
  await expect(page.locator(`${HDR} .tb-nav-header-actions .tb-nav-header-cta`).last()).toBeHidden();
  await expect(page.locator(`${HDR} .tb-nav-header-actions [aria-label="حساب کاربری"]`)).toBeVisible();
  await info.attach('bar-360.png', { body: await page.screenshot({ clip: { x: 0, y: 0, width: 360, height: 120 } }), contentType: 'image/png' });

  const toggle = page.locator(`${HDR} .tb-nav-header-toggle`);
  await toggle.click();
  const drawer = page.locator(`${HDR} .tb-nav-header-links`);
  await expect(drawer).toBeVisible();
  const box = await drawer.boundingBox();
  expect(box!.x + box!.width, 'RTL: the drawer opens from the right edge').toBeCloseTo(360, 0);
  expect(box!.width).toBeLessThanOrEqual(340);
  expect(await page.evaluate(() => document.body.style.overflow), 'page scroll is locked').toBe('hidden');
  await expect(page.locator(`${HDR} .tb-nav-header-menu-close`)).toBeFocused();
  await expect(page.locator(`${HDR} .tb-nav-header-drawer-head .tb-nav-header-logo-title`)).toHaveText('برند شما');
  await expect(page.locator(`${HDR} .tb-nav-header-drawer-head .tb-nav-header-logo-tagline`)).toBeHidden();
  await expect(page.locator(`${HDR} .tb-nav-header-drawer-search input[type=search]`)).toBeVisible();
  const ctas = page.locator(`${HDR} .tb-nav-header-drawer-ctas .tb-nav-header-cta`);
  await expect(ctas).toHaveText(['ورود', 'ثبت‌نام']);
  await expect(ctas.first()).toBeVisible();

  // Accordion: the first mega item opens its columns; promo cards stay desktop-only.
  const trigger = page.locator(`${HDR} .tb-mega-menu-trigger`).first();
  await trigger.click();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  const panel = page.locator(`${HDR} .tb-mega-menu-panel`).first();
  await expect(panel).toBeVisible();
  await expect(panel.locator('.tb-mega-menu-col-title')).toHaveText(['پنل‌های خورشیدی', 'اینورتر و ذخیره‌سازی', 'تجهیزات جانبی']);
  await expect(panel.locator('.tb-mega-badge').first()).toHaveText('جدید');
  await expect(panel.locator('.tb-mega-menu-promos')).toBeHidden();
  // The columns stack inside the drawer's width; none runs past its edge.
  const cols = await panel.locator('.tb-mega-menu-col').evaluateAll((els) => els.map((el) => { const r = el.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top }; }));
  const drawerBox = (await drawer.boundingBox())!;
  for (const c of cols) {
    expect(c.left, 'column inside the drawer').toBeGreaterThanOrEqual(drawerBox.x - 0.5);
    expect(c.right).toBeLessThanOrEqual(drawerBox.x + drawerBox.width + 0.5);
  }
  expect(cols[1].top, 'stacked, not side by side').toBeGreaterThan(cols[0].top);
  expect(await trigger.evaluate((el) => getComputedStyle(el, '::after').content), 'no underline on the pill pointer').toBe('none');
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), 'the open drawer adds no horizontal scroll').toBeLessThanOrEqual(0);
  await info.attach('drawer-360-accordion.png', { body: await page.screenshot(), contentType: 'image/png' });

  await page.keyboard.press('Escape');
  await page.keyboard.press('Escape');
  await expect(drawer).toBeHidden();
  await expect(toggle, 'focus returns to the burger').toBeFocused();
  expect(problems).toEqual([]);
});
