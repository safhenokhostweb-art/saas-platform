import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * ADR-076 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md §16) in the
 * platform Canvas:
 *
 *   - the page-tab strip: with 30 pages the toolbar stays inside the
 *     viewport, chevrons appear only toward hidden tabs and reach both ends,
 *     and the mouse wheel scrolls the strip;
 *   - the header Inspector is built from the schema: group sections, the
 *     segmented menu-placement control, the logo image and quick-access
 *     icons, and the preview carries data-widget-type / data-widget-slot.
 *
 * The 30 pages are client-side only (never saved). The header block is
 * removed again, so the shared fixture page is left as found.
 */

const fx = loadFixtures();

function state<T>(page: Page, fn: (s: any) => T): Promise<T> {
  return page.evaluate(`(() => {
    const s = document.querySelector('[x-data="themeCanvas()"]')?._x_dataStack?.[0];
    return (${fn.toString()})(s);
  })()`) as Promise<T>;
}

async function hydrate(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as any;
    return root?._x_dataStack?.some((e: any) => e.activeTarget !== undefined && e.showAddPage === false) ?? false;
  });
}

async function idle(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    return s && !s.saving && !s.quickSaving && !s.saveQueue?.busy;
  });
}

function watchProblems(page: Page): string[] {
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('response', (r) => { if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()}`); });
  return problems;
}

test('ADR-076 page-tab strip scrolls instead of overflowing the toolbar', async ({ page }) => {
  test.setTimeout(180_000);
  const problems = watchProblems(page);
  await loginAsAdmin(page, fx);

  for (const width of [1280, 1024]) {
    await page.setViewportSize({ width, height: 860 });
    await page.goto(canvasUrl(fx));
    await hydrate(page);
    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      for (let i = 1; i <= 30; i++) s.pages[`adr076-p${i}`] = { title: `صفحهٔ آزمایشی شمارهٔ ${i}`, blocks: [] };
    });
    const strip = page.getByTestId('tb-page-tabs');
    const start = page.getByTestId('tb-page-tabs-start');
    const end = page.getByTestId('tb-page-tabs-end');
    await expect(page.locator('[data-canvas-page-tab="adr076-p30"]')).toHaveCount(1);
    await expect(end).toBeVisible();
    await expect(start).toBeHidden();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `no page-level horizontal overflow at ${width}px`).toBeLessThanOrEqual(1);
    const box = await strip.boundingBox();
    expect(box && box.x >= 0 && box.x + box.width <= width + 1, `strip inside the viewport at ${width}px`).toBe(true);
    await strip.screenshot({ path: `test-results/adr076-tabs-start-${width}.png` });
    await page.getByTestId('tb-page-tabs-group').locator('xpath=..').screenshot({ path: `test-results/adr076-toolbar-${width}.png` });
    expect(box!.width, `the strip keeps a usable width at ${width}px`).toBeGreaterThanOrEqual(280);

    for (let i = 0; i < 40 && await end.isVisible(); i++) {
      await end.click();
      await page.waitForTimeout(250);
    }
    await expect(end).toBeHidden();
    await expect(start).toBeVisible();
    const viewport = await page.locator('[data-tbts-viewport]').first().boundingBox();
    const last = await page.locator('[data-canvas-page-tab="adr076-p30"]').boundingBox();
    expect(viewport && last && last.x >= viewport.x - 1 && last.x + last.width <= viewport.x + viewport.width + 1, 'the last tab is reachable').toBe(true);
    await strip.screenshot({ path: `test-results/adr076-tabs-end-${width}.png` });

    // A vertical wheel over the strip scrolls it back to the start.
    await page.locator('[data-tbts-viewport]').first().hover();
    for (let i = 0; i < 12 && await start.isVisible(); i++) {
      await page.mouse.wheel(0, -600);
      await page.waitForTimeout(150);
    }
    await expect(start).toBeHidden();
    await expect(end).toBeVisible();
  }

  expect(problems, problems.join('\n')).toEqual([]);
});

test('ADR-076 header Inspector: schema groups, segmented placement, logo and quick-access in the preview', async ({ page }) => {
  test.setTimeout(240_000);
  const problems = watchProblems(page);
  await loginAsAdmin(page, fx);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(canvasUrl(fx));
  await hydrate(page);

  await page.locator('input[x-model="librarySearch"]').fill('هدر سایت');
  const button = page.locator('button[data-block-type="nav_header"]');
  if (!(await button.isVisible())) {
    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      for (const k of Object.keys(s.expandedCats)) s.expandedCats[k] = true;
    });
  }
  await button.click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  await idle(page);
  const id = await state(page, (s) => s.selectedBlock.id as string);

  try {
    const panel = page.locator('div[x-show="selectedTab === \'content\'"]');
    await expect(panel.getByTestId('tb-field-group-logo_image')).toHaveText('لوگو و برند');
    await expect(panel.getByTestId('tb-field-group-menu_align')).toHaveText('منو');
    await expect(panel.getByTestId('tb-field-group-social_links')).toHaveText('دسترسی سریع و اکشن‌ها');
    await expect(panel.getByTestId('tb-field-group-sticky')).toHaveText('رفتار و رنگ');
    await expect(panel.locator('[data-testid^="tb-field-group-"]')).toHaveCount(4);

    const centre = panel.getByTestId('tb-segmented-menu_align-center');
    await expect(panel.getByTestId('tb-segmented-menu_align-end')).toHaveAttribute('aria-checked', 'true');
    await centre.click();
    await expect(centre).toHaveAttribute('aria-checked', 'true');
    expect(await state(page, (s) => s.selectedBlock.props.menu_align)).toBe('center');
    await panel.getByTestId('tb-segmented-social_style-brand').click();

    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      // A package-served library image (ADR-075), so the spec needs no fixture media.
      s.updateProp('logo_image', '/theme-runtime/library/mobile-store.webp');
      s.updateProp('logo_height', 44);
      s.updateProp('logo_text', 'فروشگاه نمونه');
      s.updateProp('nav_links', [{ label: 'خانه', url: '/' }, { label: 'محصولات', url: '/shop' }, { label: 'تماس', url: '/contact' }]);
      s.updateProp('social_links', [
        { url: 'https://eitaa.com/shop', label: '', network: 'auto' },
        { url: 'https://ble.ir/shop', label: '', network: 'auto' },
        { url: 'https://rubika.ir/shop', label: '', network: 'auto' },
        { url: 'https://splus.ir/shop', label: '', network: 'auto' },
        { url: 'https://gap.im/shop', label: '', network: 'auto' },
      ]);
      s.updateProp('cta_text', 'مشاوره رایگان');
    });
    await idle(page);
    expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
    await idle(page);

    // The editor iframe renders in editor mode: the wrapper carries the hook.
    const frame = page.frameLocator('#tb-live-iframe');
    await expect(frame.locator(`[data-tb-block-id="${id}"][data-widget-type="nav_header"]`)).toHaveCount(1);
    await expect(frame.locator(`#tb-${id}.tb-nav-header--menu-center`)).toHaveCount(1);

    await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
    const header = page.locator(`#tb-${id}`);
    await expect(header).toHaveClass(/tb-nav-header--menu-center/);
    for (const slot of ['logo', 'menu', 'actions', 'quick-access']) {
      await expect(header.locator(`[data-widget-slot="${slot}"]`)).toHaveCount(1);
    }
    const logo = header.locator('img.tb-nav-header-logo-img');
    await expect(logo).toHaveAttribute('alt', 'فروشگاه نمونه');
    await expect.poll(() => logo.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true);
    expect(Math.round((await logo.boundingBox())!.height), 'logo height from the schema control').toBe(44);
    for (const network of ['eitaa', 'bale', 'rubika', 'soroush', 'gap']) {
      await expect(header.locator(`.tb-social-icon--${network} svg.tb-icon-svg-mark-${network}`)).toBeVisible();
    }
    const fill = await header.locator('.tb-social-icon--eitaa').evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(fill, 'brand style fills Eitaa with its colour').toBe('rgb(234, 90, 36)');

    // Centred menu: the links sit in the middle of the bar at desktop width.
    const bar = await header.locator('.tb-nav-header-inner').boundingBox();
    const menu = await header.locator('[data-widget-slot="menu"]').boundingBox();
    expect(bar && menu && Math.abs((menu.x + menu.width / 2) - (bar.x + bar.width / 2)) < 40, 'menu centred').toBe(true);

    for (const width of [1440, 1024, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow, `no horizontal overflow at ${width}px`).toBeLessThanOrEqual(1);
      const visible = await header.locator('[data-widget-slot="quick-access"]').isVisible();
      expect(visible, `quick-access ${width < 768 ? 'hidden' : 'shown'} at ${width}px`).toBe(width >= 768);
      await header.screenshot({ path: `test-results/adr076-header-${width}.png` });
    }
  } finally {
    await page.goto(canvasUrl(fx));
    await hydrate(page);
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
    expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  }

  expect(problems, problems.join('\n')).toEqual([]);
});
