import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.5 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-064) — the
 * Canvas colour control:
 *
 *   - a theme swatch stores var(--tb-…, fallback) and the preview paints
 *     the theme's own colour;
 *   - opacity turns a swatch into a color-mix() and a fixed colour into
 *     rgba(); «بی‌رنگ» stores transparent;
 *   - shared renderers print prices in Persian («… تومان»).
 *
 * Everything survives save → reload → preview at three widths.
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

function find(blocks: any[], id: string): any {
  for (const b of blocks) {
    if (b.id === id) return b;
    const hit = find(b.children ?? [], id);
    if (hit) return hit;
  }
  return undefined;
}

const rgb = (hex: string) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

test('Phase 0.5 colour control: swatches, opacity, transparent, and Persian prices', async ({ page }) => {
  test.setTimeout(240_000);
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('response', async (r) => {
    if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()}`);
  });

  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  const panel = page.locator('div[x-show="selectedTab === \'content\'"]');
  const themePrimary: string = await state(page, (s) => (window as any).CanvasColor.palette(s.globalStyles).find((t: any) => t.token === 'primary').value);

  const gridId = await insert(page, 'product_grid', 'محصولات');

  // ── Newsletter background: swatch → opacity → transparent ─────────────
  const newsId = await insert(page, 'newsletter_form', 'Newsletter');
  const value = panel.getByTestId('tb-color-value-background');
  await panel.getByTestId('tb-color-swatch-background-primary').click();
  await expect(value).toHaveValue('var(--tb-primary, #4f46e5)');
  await expect(panel.getByTestId('tb-color-swatch-background-primary')).toHaveAttribute('aria-pressed', 'true');
  await panel.getByTestId('tb-color-alpha-background').fill('40');
  await expect(value).toHaveValue('color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent)');
  await panel.getByTestId('tb-color-transparent-background').click();
  await expect(value).toHaveValue('transparent');
  await expect(panel.getByTestId('tb-color-alpha-background')).toBeDisabled();
  await panel.getByTestId('tb-color-swatch-background-primary').click();
  await panel.getByTestId('tb-color-alpha-background').fill('40');

  // ── Text colour: a fixed colour with opacity becomes rgba() ───────────
  await page.locator('#ctrl-text_color').fill('#0ea5e9');
  await expect(panel.getByTestId('tb-color-value-text_color')).toHaveValue('#0ea5e9');
  await panel.getByTestId('tb-color-alpha-text_color').fill('50');
  await expect(panel.getByTestId('tb-color-value-text_color')).toHaveValue('rgba(14, 165, 233, 0.5)');

  // ── Save → reload → stored values ─────────────────────────────────────
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.reload();
  await hydrate(page);
  const blocks = JSON.parse(await state(page, (s) => JSON.stringify(s.blocks)));
  expect(find(blocks, newsId).props).toMatchObject({
    background: 'color-mix(in srgb, var(--tb-primary, #4f46e5) 40%, transparent)',
    text_color: 'rgba(14, 165, 233, 0.5)',
  });

  // ── Preview: the theme's own primary at 40 %, Persian prices ──────────
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  const news = page.locator(`#tb-${newsId}`);
  const [r, g, b] = rgb(themePrimary);
  await expect(news).toHaveCSS('background-color', new RegExp(`^(rgba\\(${r}, ${g}, ${b}, 0\\.4\\)|color\\(srgb [\\d.]+ [\\d.]+ [\\d.]+ / 0\\.4\\))$`));
  await expect(news).toHaveCSS('color', 'rgba(14, 165, 233, 0.5)');
  const grid = page.locator(`#tb-${gridId}`);
  await expect(grid.getByText('۱٬۲۵۰٬۰۰۰ تومان').first()).toBeVisible();
  expect(await grid.textContent()).not.toMatch(/\d,\d{3}/);

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
    for (const [label, root] of [['newsletter', news], ['prices', grid]] as const) {
      await root.scrollIntoViewIfNeeded();
      await root.screenshot({ path: `test-results/phase05-${label}-${w.name}.png` });
    }
  }

  // ── Leave the shared fixture page as found ─────────────────────────────
  await page.setViewportSize({ width: 1366, height: 900 });
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  for (const id of [newsId, gridId]) {
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
