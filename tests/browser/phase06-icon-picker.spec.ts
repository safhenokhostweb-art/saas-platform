import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.6 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-065) —
 * the shared SVG icon picker in the platform Canvas:
 *
 *   - a top-level icon field (icon_box.icon) and a list-item icon field
 *     (trust_badges.badges[].icon) both open the same searchable picker;
 *   - Persian search, category filter, keyboard (↓ into the grid, Esc
 *     closes and returns focus), a legacy emoji typed by hand still works;
 *   - the stored value is the icon name and the preview draws the SVG at
 *     three widths.
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

test('Phase 0.6 icon picker: search, categories, keyboard, legacy emoji, save and SVG preview', async ({ page }) => {
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

  // ── Top level: icon_box.icon ──────────────────────────────────────────
  const boxId = await insert(page, 'icon_box', 'آیکون');
  const trigger = panel.getByTestId('tb-icon-trigger-icon');
  await expect(trigger).toContainText('ستاره'); // registry default: star
  await expect(trigger.locator('svg.tb-icon-svg')).toBeVisible();
  await trigger.click();
  const search = panel.getByTestId('tb-icon-search-icon');
  await expect(search).toBeFocused();
  await search.fill('ارسال');
  await expect(panel.getByTestId('tb-icon-option-icon-truck')).toBeVisible();
  await search.fill('امنيت'); // Arabic ي
  await expect(panel.getByTestId('tb-icon-option-icon-lock')).toBeVisible();
  await search.fill('');
  await panel.getByTestId('tb-icon-cat-icon-communication').click();
  await expect(panel.getByTestId('tb-icon-option-icon-phone')).toBeVisible();
  await expect(panel.getByTestId('tb-icon-option-icon-truck')).toHaveCount(0);
  await panel.getByTestId('tb-icon-option-icon-phone').click();
  await expect(search).toHaveCount(0); // closed after a pick
  await expect(trigger).toBeFocused();
  await expect(trigger).toContainText('تلفن');
  expect(await state(page, (s) => s.selectedBlock.props.icon)).toBe('phone');

  // Keyboard: ↓ from the search into the grid, Esc closes back to the trigger.
  await trigger.click();
  await search.press('ArrowDown');
  await expect(panel.locator('.tbip-option').first()).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(search).toHaveCount(0);
  await expect(trigger).toBeFocused();

  // A legacy emoji typed by hand is kept and shown as such.
  await trigger.click();
  await panel.getByTestId('tb-icon-custom-icon').fill('🎁');
  await panel.getByTestId('tb-icon-custom-icon').press('Tab');
  expect(await state(page, (s) => s.selectedBlock.props.icon)).toBe('🎁');
  await expect(trigger).toContainText('ایموجی/متن (قدیمی)');
  await expect(trigger.locator('.tbip-legacy')).toHaveText('🎁');
  await panel.getByTestId('tb-icon-search-icon').fill('هدیه');
  await panel.getByTestId('tb-icon-option-icon-gift').click();
  expect(await state(page, (s) => s.selectedBlock.props.icon)).toBe('gift');
  await panel.locator('#ctrl-title').fill('بسته‌بندی هدیه رایگان');

  // ── List item: trust_badges.badges[0].icon ────────────────────────────
  const badgesId = await insert(page, 'trust_badges', 'اعتماد');
  const itemTrigger = panel.getByTestId('tb-icon-trigger-badges-0-icon');
  await expect(itemTrigger).toContainText('ارسال'); // default truck
  await itemTrigger.click();
  await panel.getByTestId('tb-icon-search-badges-0-icon').fill('پشتیبانی');
  await panel.getByTestId('tb-icon-option-badges-0-icon-headset').click();
  await expect(itemTrigger).toContainText('پشتیبانی');
  await panel.getByTestId('tb-icon-clear-badges-0-icon').isVisible();

  // ── Save → reload → stored names ──────────────────────────────────────
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.reload();
  await hydrate(page);
  const blocks = JSON.parse(await state(page, (s) => JSON.stringify(s.blocks)));
  expect(find(blocks, boxId).props.icon).toBe('gift');
  const badges = find(blocks, badgesId).props.badges;
  expect((typeof badges === 'string' ? JSON.parse(badges) : badges)[0].icon).toBe('headset');

  // ── Preview at three widths: the SVGs, not text ───────────────────────
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  const box = page.locator(`#tb-${boxId}`);
  const trust = page.locator(`#tb-${badgesId}`);
  await expect(box.locator('.tb-icon-box-icon svg.tb-icon-svg-gift')).toBeVisible();
  await expect(trust.locator('.tb-trust-badge').first().locator('.tb-trust-icon svg.tb-icon-svg-headset')).toBeVisible();
  await expect(trust.locator('.tb-trust-icon svg.tb-icon-svg-truck')).toHaveCount(0);
  await expect(trust.locator('.tb-trust-icon svg.tb-icon-svg-shield-check')).toBeVisible();
  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    for (const [label, root] of [['icon-box', box], ['trust-badges', trust]] as const) {
      await root.scrollIntoViewIfNeeded();
      await expect(root).toBeVisible();
      await root.screenshot({ path: `test-results/phase06-${label}-${w.name}.png` });
    }
    const svg = await box.locator('svg.tb-icon-svg').boundingBox();
    expect(svg && svg.width >= 24 && svg.width <= 48, `icon-box SVG sized by its slot at ${w.name}`).toBe(true);
    const bound = await trust.boundingBox();
    expect(bound && bound.width <= w.width, `trust badges fit the ${w.name} viewport`).toBe(true);
  }

  // ── Leave the shared fixture page as found ─────────────────────────────
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  for (const id of [boxId, badgesId]) {
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
