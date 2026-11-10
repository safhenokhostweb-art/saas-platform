import { test, expect, type Page, type FrameLocator } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * 2026-11-10 (owner, column widget rework) — Canvas column resizing and
 * equal-height columns.
 *
 * A real `columns` block is authored with two widgets of different content
 * length, the splitter between them is dragged with the mouse, the new
 * percentages are saved, and the reloaded Canvas + live preview must show
 * the same widths with both cells at the same height. Below 768px the row
 * stacks and the handles are gone.
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

test('columns: drag resizes, widths persist, cells stay equal height, mobile stacks', async ({ page }) => {
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

  // Author the row the way an editor does: one palette insert, then one
  // child insert per cell, each through the canonical tree operations and
  // the normal autosave. A short heading beside a long text block gives
  // the two cells unequal natural heights.
  phase = 'setup-columns';
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.addBlock('columns');
  });
  await idle(page);
  const columnsId = await state(page, (s) => s.selectedBlock.id as string);

  phase = 'setup-children';
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.addChildBlock('heading');
    s.selectBlockById(s.selectedBlock.id);
    s.updateProp('text', 'کوتاه');
  }, columnsId);
  await idle(page);

  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.addChildBlock('text');
    s.selectBlockById(s.selectedBlock.id);
    s.updateProp('content', Array.from(
      { length: 8 },
      (_, i) => `خط توضیح شماره ${i + 1} برای سنجش ارتفاع کارت‌های کنار هم`
    ).join('\n'));
    s.selectBlockById(id);
  }, columnsId);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.reloadIframe();
  });
  await idle(page);

  const row = preview(page).locator(`#tb-${columnsId}`);
  await expect(row).toHaveAttribute('data-tb-resizable', '1');
  await expect(row.locator(':scope > .tb-col-splitters .tb-col-resizer')).toHaveCount(1);

  // Equal height before any drag: grid stretch + flex cells.
  const cells = row.locator(':scope > .tb-canvas-block, :scope > .tb-style-wrap > .tb-canvas-block');
  await expect(cells).toHaveCount(2);
  const heights = async () => {
    const boxes = await cells.evaluateAll((els) => els.map((el) => el.getBoundingClientRect().height));
    return boxes.map((h) => Math.round(h * 10) / 10);
  };
  const before = await heights();
  expect(Math.abs(before[0] - before[1]), `cells start equal height (${before})`).toBeLessThan(2);
  phase = 'drag';

  // Drag the splitter ~120px toward the narrower side.
  const handle = row.locator(':scope > .tb-col-splitters .tb-col-resizer').first();
  // The row is appended at the end of a tall iframe; bring it into the window.
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(150);
  await handle.scrollIntoViewIfNeeded();
  const box = await handle.boundingBox();
  expect(box, 'splitter has geometry').not.toBeNull();
  await page.mouse.move(box!.x + box!.width / 2, box!.y + box!.height / 2);
  await page.mouse.down();
  await page.mouse.move(box!.x + box!.width / 2 + 120, box!.y + box!.height / 2, { steps: 8 });
  await page.mouse.up();

  await page.waitForFunction(
    (id) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)?._x_dataStack?.[0];
      const node = s?.blocks?.find((b: any) => b.id === id);
      return Array.isArray(node?.props?.columns) && node.props.columns.length === 2;
    },
    columnsId,
    { timeout: 10_000 }
  );

  // Read the widths from the in-memory block (authoritative), not from a
  // just-dispatch message that may itself be stale.
  const dragged = await state(page, (s) => s.blocks.find((b: any) => b.id === s.selectedBlock.id).props?.columns);
  const widths = dragged?.map((c: any) => c.width) ?? null;
  expect(widths, `drag should produce two-column widths; got ${JSON.stringify(dragged)}`).not.toBeNull();
  expect(widths!.length).toBe(2);
  // RTL: dragging the boundary to the right narrows the first (right-hand)
  // column, so the assertion is on the change, not on a fixed direction.
  expect(Math.abs(widths![0] - 50)).toBeGreaterThan(5);
  expect(widths![0]).toBeGreaterThanOrEqual(10);
  expect(widths![1]).toBeGreaterThanOrEqual(10);
  expect(Math.round((widths![0] + widths![1]) * 10) / 10).toBe(100);

  const afterDrag = await heights();
  expect(Math.abs(afterDrag[0] - afterDrag[1]), `cells remain equal height (${afterDrag})`).toBeLessThan(2);

  // Save → reload → the stored widths render again.
  phase = 'persist-save';
  // Snapshot the in-memory block *before* we save, and make it available to
  // the final cleanup so teardown uses the canonical reloaded block id (after
  // reload the selection may point at a different block than before).
  const preSaveBlock = JSON.parse(JSON.stringify(await state(page, (s) => s.blocks.find((b: any) => b.type === 'columns'))));
  const preSaveWidths = preSaveBlock.props?.columns ?? null;
  expect(preSaveWidths, `pre-save columns should still be ${JSON.stringify(widths)}, got ${JSON.stringify(preSaveWidths)}`).not.toBeNull();
  if (preSaveWidths) {
    expect(preSaveWidths.map((c: any) => c.width), `pre-save columns should equal the drag result ${JSON.stringify(widths)}`).toEqual(widths);
  }
  await idle(page);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  // After a successful save the document is refreshed from the server; the
  // reloaded columns block must carry the same stored widths as we just saved.
  await page.reload();
  await hydrate(page);
  await idle(page);

  const stored = JSON.parse(JSON.stringify(await state(page, (s) => s.blocks.find((b: any) => b.type === 'columns'))));
  // Reload the canonical saved block id (in case the selection changed across
  // the reload) so cleanup removes the right block.
  const savedId = stored.id;
  expect(stored.props?.columns, `reloaded columns should equal ${JSON.stringify(widths)}, got ${JSON.stringify(stored.props?.columns)}`).not.toBeNull();
  if (stored.props?.columns) {
    expect(stored.props.columns.map((c: any) => c.width)).toEqual(widths);
  }

  const reloadedRow = preview(page).locator(`#tb-${stored.id}`);
  const tracks = await reloadedRow.evaluate((el) =>
    getComputedStyle(el).gridTemplateColumns.split(' ').map((value) => parseFloat(value))
  );
  expect(tracks).toHaveLength(2);
  // The narrowed column keeps its saved proportion after reload.
  expect(tracks[0]).toBeLessThan(tracks[1]);
  const reloadedHeights = await reloadedRow.locator(':scope > .tb-canvas-block, :scope > .tb-style-wrap > .tb-canvas-block').evaluateAll(
    (els) => els.map((el) => Math.round(el.getBoundingClientRect().height * 10) / 10)
  );
  expect(Math.abs(reloadedHeights[0] - reloadedHeights[1]), `reloaded cells equal height (${reloadedHeights})`).toBeLessThan(2);

  // Mobile: handles disappear and the row stacks into one full-width track.
  phase = 'mobile';
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.setDevice('mobile');
  });
  await idle(page);
  await expect(reloadedRow.locator(':scope > .tb-col-splitters')).toBeHidden();
  const stacked = await reloadedRow.evaluate((el) => {
    const style = getComputedStyle(el);
    return { columns: style.gridTemplateColumns, first: el.getBoundingClientRect().width };
  });
  expect(stacked.columns.split(' ')).toHaveLength(1);
  const cellWidth = await reloadedRow.locator(':scope > .tb-canvas-block, :scope > .tb-style-wrap > .tb-canvas-block').first().evaluate((el) => el.getBoundingClientRect().width);
  expect(Math.abs(cellWidth - stacked.first)).toBeLessThan(2);

  // Leave the shared fixture page as found (restore desktop + remove the block we added).
  phase = 'cleanup';
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.setDevice('desktop');
  });
  await idle(page);
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.removeSelected();
  }, savedId);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
