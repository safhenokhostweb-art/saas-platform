import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.3 / 0.4 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-063):
 *
 *   - the Canvas preview loads Vazirmatn from this host's own files — no
 *     request ever reaches a font CDN — and the faces actually load;
 *   - block CSS comes from the shared package sheet: RTL hero titles have
 *     normal letter-spacing and the bento grid has real `grid-auto-rows`;
 *   - the preview has no horizontal overflow at 1366, 768 and 390.
 */

const fx = loadFixtures();
const WIDTHS = [
  { name: 'desktop', width: 1366 },
  { name: 'tablet', width: 768 },
  { name: 'mobile', width: 390 },
] as const;

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

async function insert(page: Page, type: string, search: string): Promise<string> {
  await page.locator('input[x-model="librarySearch"]').fill(search);
  const button = page.locator(`button[data-block-type="${type}"]`);
  if (!(await button.isVisible())) {
    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      for (const k of Object.keys(s.expandedCats)) s.expandedCats[k] = true;
    });
  }
  await button.click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  await idle(page);
  return state(page, (s) => s.selectedBlock.id as string);
}

test('Phase 0.3/0.4 shared block CSS and local Vazirmatn in the Canvas preview', async ({ page }) => {
  test.setTimeout(240_000);
  const problems: string[] = [];
  const fontCdn: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('request', (r) => { if (/fonts\.(googleapis|gstatic)\.com/.test(r.url())) fontCdn.push(r.url()); });
  page.on('response', async (r) => {
    if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()}`);
  });

  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await hydrate(page);

  const heroSplitId = await insert(page, 'hero_split', 'Split Hero');
  const heroGradientId = await insert(page, 'hero_gradient', 'گرادیان');
  const bentoId = await insert(page, 'bento_grid', 'Bento');
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  // The editor itself (layouts/canvas) loads no font CDN either.
  expect(fontCdn, 'editor requested a font CDN').toEqual([]);

  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => (document as any).fonts.ready);

  const fonts = await page.evaluate(() => {
    const faces = [...(document as any).fonts].filter((f: any) => f.family.replace(/['"]/g, '') === 'Vazirmatn');
    return {
      weights: faces.map((f: any) => `${f.weight}:${f.status}`),
      regular: (document as any).fonts.check('400 16px Vazirmatn'),
      bold: (document as any).fonts.check('700 16px Vazirmatn'),
      body: getComputedStyle(document.body).fontFamily,
    };
  });
  // Exactly the five local faces customer-site ships (no 300 weight).
  expect(fonts.weights.map((w: string) => w.split(':')[0]).sort()).toEqual(['400', '500', '600', '700', '800']);
  expect(fonts.regular && fonts.bold, 'Vazirmatn 400/700 load from local files').toBe(true);
  expect(fonts.body).toContain('Vazirmatn');

  const splitTitle = page.locator(`#tb-${heroSplitId} .tb-hero-split-title`);
  const gradientTitle = page.locator(`#tb-${heroGradientId} .tb-hero-gradient-title`);
  await expect(splitTitle).toHaveCSS('letter-spacing', 'normal');
  await expect(gradientTitle).toHaveCSS('letter-spacing', 'normal');
  await expect(page.locator(`#tb-${bentoId} .tb-bento-grid`)).toHaveCSS('grid-auto-rows', 'minmax(160px, auto)');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
    for (const [label, id] of [['hero-split', heroSplitId], ['hero-gradient', heroGradientId], ['bento', bentoId]] as const) {
      const root = page.locator(`#tb-${id}`);
      await root.scrollIntoViewIfNeeded();
      await root.screenshot({ path: `test-results/phase03-${label}-${w.name}.png` });
    }
  }
  expect(fontCdn, 'preview requested a font CDN').toEqual([]);

  // ── Leave the shared fixture page as found ─────────────────────────────
  await page.setViewportSize({ width: 1366, height: 900 });
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  for (const id of [heroSplitId, heroGradientId, bentoId]) {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
  }
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
