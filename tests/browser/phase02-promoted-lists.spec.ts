import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.2 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-060) —
 * list fields promoted from customer-site into the canonical registry are
 * authorable in the platform Canvas, and each keeps its declared storage
 * through save → reload → preview:
 *
 *   - process_steps.steps (storage: array): the legacy JSON-string default
 *     loads into the repeater; the first edit persists a real array;
 *   - timeline.items (storage: json): edits persist as a JSON string, the
 *     shape this field has always had.
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

async function insert(page: Page, type: string, search: string): Promise<string> {
  await page.locator('input[x-model="librarySearch"]').fill(search);
  const button = page.locator(`button[data-block-type="${type}"]`);
  if (!(await button.isVisible())) {
    // Expand whichever collapsed category holds it.
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

test('Phase 0.2 promoted lists are authorable and keep their declared storage', async ({ page }) => {
  test.setTimeout(180_000);
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('response', async (r) => {
    if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()} ${(await r.text().catch(() => '')).slice(0, 300)}`);
  });

  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  const panel = page.locator('div[x-show="selectedTab === \'content\'"]');

  // ── process_steps: legacy JSON default → repeater → array on edit ──────
  const stepsId = await insert(page, 'process_steps', 'process');
  expect(typeof (await state(page, (s) => s.selectedBlock.props.steps))).toBe('string'); // legacy default shape
  const steps = panel.getByTestId('tb-repeater-steps');
  await expect(steps.locator('[data-testid^="tb-repeater-item-steps-"]')).toHaveCount(3);
  await expect(panel.locator('#rf-steps-0-number')).toHaveValue('۱');
  await panel.locator('#rf-steps-0-title').fill('ثبت سفارش آنلاین');
  await panel.getByTestId('tb-repeater-add-steps').click();
  await panel.locator('#rf-steps-3-number').fill('۴');
  await panel.locator('#rf-steps-3-title').fill('تحویل درب منزل');

  // ── timeline: storage json stays a JSON string ─────────────────────────
  const timelineId = await insert(page, 'timeline', 'timeline');
  await expect(panel.getByTestId('tb-repeater-items').locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(3);
  await panel.locator('#rf-items-0-title').fill('تأسیس فروشگاه');

  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.reload();
  await hydrate(page);
  const blocks = JSON.parse(await state(page, (s) => JSON.stringify(s.blocks)));

  const savedSteps = find(blocks, stepsId).props.steps;
  expect(Array.isArray(savedSteps), 'storage: array persists a real array').toBe(true);
  expect(savedSteps.map((s: any) => s.title)).toEqual(['ثبت سفارش آنلاین', 'تنظیم سایت', 'راه‌اندازی', 'تحویل درب منزل']);

  const savedTimeline = find(blocks, timelineId).props.items;
  expect(typeof savedTimeline, 'storage: json persists a JSON string').toBe('string');
  expect(JSON.parse(savedTimeline)[0].title).toBe('تأسیس فروشگاه');

  // ── preview renders both ───────────────────────────────────────────────
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  await expect(page.locator(`#tb-${stepsId}`).getByText('تحویل درب منزل')).toBeVisible();
  await expect(page.locator(`#tb-${timelineId}`).getByText('تأسیس فروشگاه')).toBeVisible();

  // ── leave the shared fixture page as found ─────────────────────────────
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  for (const id of [stepsId, timelineId]) {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      s.removeSelected();
    }, id);
    await idle(page);
  }
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
