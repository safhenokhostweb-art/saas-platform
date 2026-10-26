import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * P1 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md §5) — the Canvas save
 * lane. Every /canvas/save response is held for 800 ms so the formerly racy
 * windows are wide open and deterministic:
 *
 *   1. insert (autosave on the wire) → immediate manual save: the manual save
 *      is QUEUED, never sent concurrently, and replays no operation twice
 *      (previously: 422 "Duplicate node id");
 *   2. the Inspector stays editable while saves are queued / in flight;
 *   3. an edit made while a save is in flight is NOT marked saved
 *      (previously: markSaved(this.blocks) silently swallowed it) and
 *      persists on the next save.
 *
 * Any console error, page error, failed request or 4xx/5xx fails the run.
 */

const fx = loadFixtures();
const HOLD_MS = 800;

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

function headingText(blocks: any[], id: string): string | undefined {
  for (const b of blocks) {
    if (b.id === id) return b.props?.text;
    const hit = headingText(b.children ?? [], id);
    if (hit !== undefined) return hit;
  }
  return undefined;
}

test('P1: one save on the wire, queued manual save, mid-flight edits never lost', async ({ page }) => {
  test.setTimeout(180_000);

  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('requestfailed', (r) => {
    const superseded = r.failure()?.errorText === 'net::ERR_ABORTED' && r.url().includes('/canvas/live-preview');
    if (!superseded) problems.push(`requestfailed: ${r.method()} ${r.url()} ${r.failure()?.errorText}`);
  });
  page.on('response', async (r) => {
    if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()} ${(await r.text().catch(() => '')).slice(0, 300)}`);
  });

  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await hydrate(page);

  // Hold every save response and measure how many are ever on the wire.
  let inFlight = 0;
  let maxInFlight = 0;
  let saveRequests = 0;
  await page.route('**/canvas/save', async (route) => {
    if (route.request().method() !== 'PUT') return route.continue();
    saveRequests++;
    inFlight++;
    maxInFlight = Math.max(maxInFlight, inFlight);
    const response = await route.fetch();
    await new Promise((r) => setTimeout(r, HOLD_MS));
    await route.fulfill({ response });
    inFlight--;
  });

  // ── 1. insert → autosave on the wire → immediate manual save ──────────
  await page.locator('input[x-model="librarySearch"]').fill('heading');
  await page.locator('button[data-block-type="heading"]').click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  const headingId = await state(page, (s) => s.selectedBlock?.id as string);
  expect(headingId).toBeTruthy();
  await expect.poll(() => saveRequests).toBe(1);                       // insert autosave sent

  await state(page, (s) => { (window as any).__p1Save = s.saveCurrentPage(); });
  const saveButton = page.getByTestId('canvas-save-button');
  await expect(saveButton).toBeDisabled();
  await expect(saveButton).toHaveText(/در صف ذخیره/);
  await expect(saveButton).toHaveAttribute('aria-busy', 'true');

  // ── 2. the Inspector stays editable while the save is queued ──────────
  const text = page.locator('div[x-show="selectedTab === \'content\'"] #ctrl-text');
  await expect(text).toBeEditable();
  await text.fill('P1 edit made while the save was queued');

  await expect.poll(() => saveRequests).toBe(2);                       // queued manual save started
  expect(inFlight).toBeLessThanOrEqual(1);

  // ── 3. an edit while the manual save is on the wire stays unsaved ──────
  await text.fill('P1 edit made while the save was in flight');
  expect(await page.evaluate(() => (window as any).__p1Save)).toBe(true);
  await idle(page);
  expect(await state(page, (s) => s.isDirty), 'a mid-flight edit must stay dirty').toBe(true);
  await expect(page.getByText('ذخیره نشده').first()).toBeVisible();
  expect(await state(page, (s) => s.documentState.pendingOperations.length), 'the insert operation was acknowledged exactly once').toBe(0);

  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await idle(page);
  expect(await state(page, (s) => s.isDirty)).toBe(false);
  expect(maxInFlight, 'never more than one save on the wire').toBe(1);

  // ── Persisted after reload ─────────────────────────────────────────────
  await page.unroute('**/canvas/save');
  await page.reload();
  await hydrate(page);
  expect(headingText(JSON.parse(await state(page, (s) => JSON.stringify(s.blocks))), headingId))
    .toBe('P1 edit made while the save was in flight');

  // ── Leave the shared fixture page as found ─────────────────────────────
  await page.evaluate((id) => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    s.selectBlockById(id);
    s.removeSelected();
  }, headingId);
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.reload();
  await hydrate(page);
  expect(headingText(JSON.parse(await state(page, (s) => JSON.stringify(s.blocks))), headingId)).toBeUndefined();

  expect(problems, problems.join('\n')).toEqual([]);
});
