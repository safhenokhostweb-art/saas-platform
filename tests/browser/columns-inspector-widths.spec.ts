import { test, expect, type Page, type FrameLocator } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * 2026-11-10 (owner, column widget rework) — Inspector column widths.
 *
 * The Inspector's percentage inputs and the Canvas splitter are two views of
 * the same persisted `props.columns[i].width`. This spec authors a two-cell
 * row, edits a number, drags the splitter, then uses Distribute Evenly and
 * Reset to Ratio, checking after each step that the Inspector, the live
 * iframe and the stored props all agree and total 100%.
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

function widthsOf(page: Page, blockId: string): Promise<number[]> {
  return page.evaluate(`(() => {
    const s = document.querySelector('[x-data="themeCanvas()"]')?._x_dataStack?.[0];
    const block = s?.blocks?.find((b) => b.id === ${JSON.stringify(blockId)});
    return Array.isArray(block?.props?.columns) ? block.props.columns.map((c) => c.width) : null;
  })()`) as Promise<number[] | null>;
}

function inputValues(page: Page): Promise<number[]> {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-testid^="tb-col-width-input-"]'))
      .map((el) => Number((el as HTMLInputElement).value))
  );
}

function trackPercents(row: ReturnType<FrameLocator['locator']>): Promise<number[]> {
  return row.evaluate((el) => {
    const tracks = getComputedStyle(el).gridTemplateColumns.split(' ').map((value) => parseFloat(value));
    const total = tracks.reduce((sum, value) => sum + value, 0) || 1;
    return tracks.map((value) => Math.round((value / total) * 1000) / 10);
  });
}

test('columns: Inspector widths edit, mirror a drag, distribute and reset', async ({ page }) => {
  test.setTimeout(240_000);
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

  // A two-cell row, authored through the palette like an editor would.
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
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.reloadIframe();
  });
  await idle(page);

  const panel = page.getByTestId('tb-column-widths');
  await expect(panel).toBeVisible();
  const input0 = page.getByTestId('tb-col-width-input-0');
  const input1 = page.getByTestId('tb-col-width-input-1');
  // No manual widths yet: the Inspector shows the ratio layout.
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');
  const row = preview(page).locator(`#tb-${columnsId}`);

  // 1) Typing a number updates the stored prop and the live iframe, and the
  //    other column absorbs the remainder so the total stays 100.
  phase = 'typing';
  await input0.fill('70');
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return Math.abs(Number(block?.props?.columns?.[0]?.width) - 70) < 0.2;
  }, columnsId, { timeout: 10_000 });
  const typed = await widthsOf(page, columnsId);
  expect(typed).not.toBeNull();
  expect(Math.round(typed![0] * 10) / 10).toBe(70);
  expect(Math.round((typed![0] + typed![1]) * 10) / 10).toBe(100);
  await expect(input1).toHaveValue('30');
  expect(await state(page, (s) => s.columnWidthTotal())).toBe(100);
  await expect.poll(async () => (await trackPercents(row))[0]).toBeGreaterThan(66);

  // 2) A Canvas drag is reflected in the Inspector inputs (live two-way sync).
  phase = 'drag-mirror';
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
  // The drag may have landed anywhere in [10,90]; wait for it to move away
  // from the pre-drag value by more than 0.5, then read the result from the
  // in-memory block (the authoritative source, not the just-dispatch message).
  await page.waitForFunction(
    (id) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
      const block = s?.blocks?.find((b: any) => b.id === id);
      return Array.isArray(block?.props?.columns) && Math.abs(block.props.columns[0].width - 50) > 0.5;
    },
    columnsId,
    { timeout: 10_000 }
  );
  const dragged = (await widthsOf(page, columnsId))!;
  expect(dragged, `drag should produce two-column widths; got ${JSON.stringify(dragged)}`).not.toBeNull();
  expect(Math.abs(dragged[0] - 50)).toBeGreaterThan(5);
  expect(dragged[0]).toBeGreaterThanOrEqual(10);
  expect(dragged[1]).toBeGreaterThanOrEqual(10);
  expect(Math.round((dragged[0] + dragged[1]) * 10) / 10).toBe(100);
  const mirrored = await inputValues(page);
  expect(mirrored).toHaveLength(2);
  expect(Math.abs(mirrored[0] - dragged[0])).toBeLessThan(0.2);
  expect(Math.abs(mirrored[1] - dragged[1])).toBeLessThan(0.2);

  // 3) Distribute Evenly balances the row regardless of the current widths.
  phase = 'even';
  await panel.getByTestId('tb-col-widths-even').click();
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    const widths = block?.props?.columns;
    return Array.isArray(widths) && widths.every((c: any) => Math.abs(Number(c.width) - 50) < 0.2);
  }, columnsId, { timeout: 10_000 });
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');

  // 4) Reset to Ratio drops the manual widths and returns to the picked ratio.
  phase = 'reset';
  await input0.fill('70');
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return Math.abs(Number(block?.props?.columns?.[0]?.width) - 70) < 0.2;
  }, columnsId, { timeout: 10_000 });
  await expect.poll(async () => {
    const row_ = preview(page).locator(`#tb-${columnsId}`);
    return (await trackPercents(row_))[0];
  }, { timeout: 10_000 }).toBeGreaterThan(66);
  await panel.getByTestId('tb-col-widths-reset').click();
  // Reset publishes an explicit 50/50 setColumnWidths live update so the
  // Inspector and preview agree immediately; the persisted props.columns
  // stays cleared so the next save / reload falls back to the ratio tracks.
  await page.waitForFunction((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
    const block = s?.blocks?.find((b: any) => b.id === id);
    return !block?.props?.columns;
  }, columnsId, { timeout: 10_000 });
  await expect(input0).toHaveValue('50');
  await expect(input1).toHaveValue('50');
  expect(await state(page, (s) => s.columnWidthTotal())).toBe(100);
  await expect.poll(async () => {
    const row_ = preview(page).locator(`#tb-${columnsId}`);
    return (await trackPercents(row_))[0];
  }).toBeLessThan(51);

  // 5) Save and reload: the reset persisted, so the ratio layout renders again.
  phase = 'persist';
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  const preSaveStored = await state(page, (s) => {
    const block = s.blocks.find((b: any) => b.type === 'columns');
    return { id: block.id, columns: block.props?.columns ?? null };
  });
  // After Reset the block must be saved WITHOUT manual columns, so the
  // pre-save snapshot already shows props.columns cleared. (If a stale
  // committed width like [54.1,45.9] survives here, the reset did not
  // actually clear props.columns on the current in-memory block.)
  expect(preSaveStored.columns).toBeNull();
  await page.reload();
  await hydrate(page);
  await idle(page);
  const stored = await state(page, (s) => {
    const block = s.blocks.find((b: any) => b.type === 'columns');
    return { id: block.id, columns: block.props?.columns ?? null };
  });
  expect(stored.columns).toBeNull();
  const reloaded = preview(page).locator(`#tb-${stored.id}`);
  const reloadedPercents = await trackPercents(reloaded);
  expect(Math.max(...reloadedPercents.map((value) => Math.abs(value - 50)))).toBeLessThan(2);

  // 6) Leave the shared fixture page as found.
  phase = 'cleanup';
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.removeSelected();
  }, stored.id);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
