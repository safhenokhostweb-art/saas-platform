import { test, expect, type Page, type FrameLocator } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * 2026-11-10 (owner, column widget rework) — Undo/Redo for column widths.
 *
 * One undo step must cover a whole typing burst or drag, never a sampled
 * intermediate width. Both surfaces (Inspector inputs and the live iframe)
 * follow undo and redo, and Distribute Evenly / Reset to Ratio each record a
 * discrete step that returns to the custom layout before it.
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
    return s && !s.saving && !s.quickSaving && !s.saveQueue?.busy && s.iframeReady;
  });
}

function preview(page: Page): FrameLocator {
  return page.frameLocator('#tb-live-iframe');
}

function widthsOf(page: Page, blockId: string): Promise<number[] | null> {
  return page.evaluate(`(() => {
    const s = document.querySelector('[x-data="themeCanvas()"]')?._x_dataStack?.[0];
    const block = s?.blocks?.find((b) => b.id === ${JSON.stringify(blockId)});
    return Array.isArray(block?.props?.columns) ? block.props.columns.map((c) => c.width) : null;
  })()`) as Promise<number[] | null>;
}

async function waitForWidths(page: Page, blockId: string, first: number): Promise<void> {
  await page.waitForFunction(({ id, value }) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return Math.abs(Number(block?.props?.columns?.[0]?.width) - value) < 0.3;
  }, { id: blockId, value: first }, { timeout: 10_000 });
}

async function undo(page: Page): Promise<void> {
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.undo();
  });
}

async function redo(page: Page): Promise<void> {
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.redo();
  });
}

function trackPercents(row: ReturnType<FrameLocator['locator']>): Promise<number[]> {
  return row.evaluate((el) => {
    const tracks = getComputedStyle(el).gridTemplateColumns.split(' ').map((value) => parseFloat(value));
    const total = tracks.reduce((sum, value) => sum + value, 0) || 1;
    return tracks.map((value) => Math.round((value / total) * 1000) / 10);
  });
}

async function expectTracks(page: Page, row: ReturnType<FrameLocator['locator']>, blockId: string, first: number): Promise<void> {
  await expect.poll(async () => (await trackPercents(row))[0], { timeout: 10_000 })
    .toBeGreaterThan(first - 2);
  await expect.poll(async () => (await trackPercents(row))[0], { timeout: 10_000 })
    .toBeLessThan(first + 2);
  const props = await widthsOf(page, blockId);
  const snapshot = await row.evaluate((el, id) => ({
    computed: getComputedStyle(el).gridTemplateColumns,
    inline: el.getAttribute('style'),
    children: Array.from(el.children).map((child) => child.className || child.tagName),
    copies: document.querySelectorAll(`[id="${id}"]`).length,
  }), `tb-${blockId}`);
  const raw = snapshot.computed.split(' ').map((value) => parseFloat(value));
  const rawTotal = raw.reduce((sum, value) => sum + value, 0) || 1;
  const percents = raw.map((value) => Math.round((value / rawTotal) * 1000) / 10);
  expect(Math.abs(percents[0] - first) < 2, `percents=${JSON.stringify(percents)} props=${JSON.stringify(props)} view=${JSON.stringify(snapshot)}`).toBe(true);
}

test('columns: undo/redo restores widths on both surfaces, one step per interaction', async ({ page }) => {
  test.setTimeout(300_000);
  const problems: string[] = [];
  let phase = 'init';
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`[${phase}] console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`[${phase}] pageerror: ${e.message}`));
  page.on('response', async (r) => {
    if (r.status() >= 400) problems.push(`[${phase}] http ${r.status()}: ${r.request().method()} ${r.url()} ${(await r.text().catch(() => '')).slice(0, 300)}`);
  });

  await loginAsAdmin(page, fx);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(canvasUrl(fx));
  await hydrate(page);

  phase = 'setup';
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.addBlock('columns');
  });
  await idle(page);
  const columnsId = await state(page, (s) => s.selectedBlock.id as string);
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.addChildBlock('heading');
    s.selectBlockById(s.selectedBlock.id);
    s.updateProp('text', 'ستون راست');
    s.selectBlockById(id);
    s.addChildBlock('text');
    s.selectBlockById(s.selectedBlock.id);
    s.updateProp('content', 'ستون چپ');
    s.selectBlockById(id);
    s.selectedTab = 'content';
  }, columnsId);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  // A reload clears history: every step below is authored by this test.
  await page.reload();
  await hydrate(page);
  await idle(page);
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.selectedTab = 'content';
  }, columnsId);
  await idle(page);

  const panel = page.getByTestId('tb-column-widths');
  await expect(panel).toBeVisible();
  const input0 = page.getByTestId('tb-col-width-input-0');
  const input1 = page.getByTestId('tb-col-width-input-1');
  const row = preview(page).locator(`#tb-${columnsId}`);
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');

  // 1) A typing burst is one undo step, even across a pause between two
  //    values: undo goes to the layout before the first keystroke (50/50).
  phase = 'typing-coalesce';
  await input0.click();
  await input0.fill('80');
  await waitForWidths(page, columnsId, 80);
  await input0.fill('60');
  await waitForWidths(page, columnsId, 60);
  await undo(page);
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return !block?.props?.columns;
  }, columnsId, { timeout: 10_000 });
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');
  await expectTracks(page, row, columnsId, 50);

  // Redo restores the final typed widths on both surfaces.
  await redo(page);
  await waitForWidths(page, columnsId, 60);
  await expect(input0).toHaveValue('60');
  await expect(input1).toHaveValue('40');
  await expectTracks(page, row, columnsId, 60);

  // 2) Distribute Evenly is its own step: undo returns to the custom 60/40,
  //    not to the pre-typing layout.
  phase = 'even-step';
  await panel.getByTestId('tb-col-widths-even').click();
  await waitForWidths(page, columnsId, 50);
  await undo(page);
  await waitForWidths(page, columnsId, 60);
  await expect(input0).toHaveValue('60');
  await expect(input1).toHaveValue('40');
  await expectTracks(page, row, columnsId, 60);

  // 3) Reset to Ratio is undoable and restores the previous manual widths;
  //    redo clears them again and the preview returns to the ratio tracks.
  phase = 'reset-step';
  await panel.getByTestId('tb-col-widths-reset').click();
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return !block?.props?.columns;
  }, columnsId, { timeout: 10_000 });
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');
  await expectTracks(page, row, columnsId, 50);
  await undo(page);
  await waitForWidths(page, columnsId, 60);
  await expect(input0).toHaveValue('60');
  await expect(input1).toHaveValue('40');
  await expectTracks(page, row, columnsId, 60);
  await redo(page);
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return !block?.props?.columns;
  }, columnsId, { timeout: 10_000 });
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');
  await expectTracks(page, row, columnsId, 50);

  // 4) A drag is one discrete step; undo returns to the widths before it.
  phase = 'drag-step';
  await input0.click();
  await input0.fill('70');
  await waitForWidths(page, columnsId, 70);
  const handle = row.locator(':scope > .tb-col-splitters .tb-col-resizer').first();
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(150);
  await handle.scrollIntoViewIfNeeded();
  const box = await handle.boundingBox();
  expect(box, 'splitter has geometry').not.toBeNull();
  await page.mouse.move(box!.x + box!.width / 2, box!.y + box!.height / 2);
  await page.mouse.down();
  await page.mouse.move(box!.x + box!.width / 2 + 90, box!.y + box!.height / 2, { steps: 8 });
  await page.mouse.up();
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return Math.abs(Number(block?.props?.columns?.[0]?.width) - 70) > 0.5;
  }, columnsId, { timeout: 10_000 });
  const dragged = (await widthsOf(page, columnsId))!;
  expect(Math.round((dragged[0] + dragged[1]) * 10) / 10).toBe(100);
  await undo(page);
  await waitForWidths(page, columnsId, 70);
  await expect(input0).toHaveValue('70');
  await expect(input1).toHaveValue('30');
  await expectTracks(page, row, columnsId, 70);

  // And the typing step below it still returns to the ratio layout.
  await undo(page);
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return !block?.props?.columns;
  }, columnsId, { timeout: 10_000 });
  await expect(input0).toHaveValue('50');
  await expectTracks(page, row, columnsId, 50);

  // 5) Leave the shared fixture page as found.
  phase = 'cleanup';
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.removeSelected();
  }, columnsId);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
