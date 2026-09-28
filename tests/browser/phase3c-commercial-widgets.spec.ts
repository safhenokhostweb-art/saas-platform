import { test, expect } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

const fx = loadFixtures();

type CanvasBlock = {
  id?: string;
  type: string;
  props?: Record<string, unknown>;
  children?: CanvasBlock[];
};

async function waitForCanvasHydration(page: import('@playwright/test').Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    return root?._x_dataStack?.some((entry) => entry.activeTarget !== undefined
      && entry.showAddPage === false) ?? false;
  });
  await expect(page.locator('[x-show="showAddPage"]')).toBeHidden();
}

async function insertSection(page: import('@playwright/test').Page, name: string): Promise<void> {
  await page.getByRole('button', { name: 'بخش‌ها' }).click();
  const modal = page.locator('div[x-show="showSectionLibrary"]');
  await expect(modal).toBeVisible();
  const card = modal.locator('div.cursor-pointer', { hasText: name }).first();
  await expect(card).toBeVisible();
  await card.click();
  await expect(modal).toBeHidden();
}

function flatten(blocks: CanvasBlock[]): CanvasBlock[] {
  return blocks.flatMap((block) => [block, ...flatten(block.children ?? [])]);
}

test('Phase 3C commercial sections insert, edit, save, reload, and render responsively', async ({ page }) => {
  test.setTimeout(180_000);
  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await waitForCanvasHydration(page);

  await insertSection(page, 'ویترین ویژگی‌های تجاری — فارسی');
  await insertSection(page, 'آمار و اعتماد — فارسی');

  const tree = await page.evaluate(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    const state = root?._x_dataStack?.[0] as { blocks?: unknown } | undefined;
    return JSON.stringify(state?.blocks ?? []);
  });
  const flat = flatten(JSON.parse(tree) as CanvasBlock[]);
  expect(flat.filter((node) => node.type === 'features_grid').length).toBeGreaterThanOrEqual(1);
  expect(flat.filter((node) => node.type === 'icon_box').length).toBeGreaterThanOrEqual(3);
  expect(flat.filter((node) => node.type === 'stats_row').length).toBeGreaterThanOrEqual(1);
  expect(flat.filter((node) => node.type === 'stat_item').length).toBeGreaterThanOrEqual(3);
  expect(flat.filter((node) => node.type === 'trust_badges_row').length).toBeGreaterThanOrEqual(1);
  expect(flat.filter((node) => node.type === 'trust_item').length).toBeGreaterThanOrEqual(4);

  await page.evaluate(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    const state = root?._x_dataStack?.[0] as {
      blocks?: CanvasBlock[];
      selectBlockById?: (id: string) => void;
    } | undefined;
    const find = (nodes: CanvasBlock[]): CanvasBlock | undefined => {
      for (const node of nodes) {
        if (node.type === 'trust_badges_row') return node;
        const hit = find(node.children ?? []);
        if (hit) return hit;
      }
      return undefined;
    };
    const trust = find(state?.blocks ?? []);
    if (trust?.id) state?.selectBlockById?.(trust.id);
  });

  const inspector = page.locator('div[x-show="selectedTab === \'content\'"]');
  const gap = inspector.locator('#ctrl-gap');
  await expect(gap).toBeVisible();
  await gap.fill('28');

  const savePromise = page.waitForResponse((response) =>
    response.url().includes('/canvas/save')
      && response.request().method() === 'PUT'
      && (() => { try { return JSON.parse(response.request().postData() ?? '{}').target === 'page'; } catch { return false; } })()
  );
  await page.evaluate(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    const state = root?._x_dataStack?.[0] as { saveCurrentPage?: () => Promise<unknown> } | undefined;
    return state?.saveCurrentPage?.();
  });
  expect((await savePromise).status()).toBe(200);

  await page.reload();
  await waitForCanvasHydration(page);
  const reloaded = await page.evaluate(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    const state = root?._x_dataStack?.[0] as { blocks?: CanvasBlock[] } | undefined;
    return JSON.stringify(state?.blocks ?? []);
  });
  const persistedTrust = flatten(JSON.parse(reloaded) as CanvasBlock[])
    .find((node) => node.type === 'trust_badges_row' && node.props?.gap === 28);
  expect(persistedTrust, 'edited trust row must survive save → reload').toBeTruthy();

  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  await expect(page.locator('.tb-commercial-features-grid').last()).toBeVisible();
  await expect(page.locator('.tb-commercial-stats-row').last()).toBeVisible();
  await expect(page.locator('.tb-trust-badges-row').last()).toBeVisible();
  await expect(page.getByText('تضمین اصالت همه کالاها').last()).toBeVisible();

  for (const [width, columns] of [[1366, 4], [768, 2], [390, 1]] as const) {
    await page.setViewportSize({ width, height: 900 });
    const count = await page.locator('.tb-trust-badges-row').last().evaluate((element) =>
      getComputedStyle(element).gridTemplateColumns.split(' ').length
    );
    expect(count, `trust grid must have ${columns} columns at ${width}px`).toBe(columns);
  }
});
