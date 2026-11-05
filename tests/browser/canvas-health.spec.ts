import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * WU-3 «سلامت ویجت» in the platform Canvas (theme-runtime::canvas.health).
 * A header with planted defects at the mobile preview width, Inspect OFF:
 *
 *   - opening the tab starts the preview's checks; the header row is red and
 *     first, its details list WIDGET_OVERFLOW / DEAD_LINK / CONTRAST_LOW;
 *   - «کپی پرامپت اصلاحی» copies the fix prompt with those codes and the id;
 *   - the row selects its block in the editor;
 *   - Inspect on → off while the tab is open does not stop the checks, and a
 *     preview reload keeps them running;
 *   - a fix turns the row amber live; closing the tab stops the checks.
 *
 * The header block is removed again, so the shared fixture page is left as found.
 */

const fx = loadFixtures();

function canvas<T>(page: Page, fn: string): Promise<T> {
  return page.evaluate(`(() => { const s = document.querySelector('[x-data="themeCanvas()"]')._x_dataStack[0]; return (${fn})(s); })()`) as Promise<T>;
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

test('Widget health tab: red row first, details, fix prompt, select, survives reload and Inspect, stops on close', async ({ page, context, baseURL }) => {
  test.setTimeout(240_000);
  const problems: string[] = [];
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: new URL(canvasUrl(fx), baseURL || 'http://127.0.0.1:8000').origin });

  await loginAsAdmin(page, fx);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(canvasUrl(fx));
  await hydrate(page);

  await page.locator('input[x-model="librarySearch"]').fill('هدر سایت');
  const add = page.locator('button[data-block-type="nav_header"]');
  if (!(await add.isVisible())) await canvas(page, `(s) => { for (const k of Object.keys(s.expandedCats)) s.expandedCats[k] = true; }`);
  await add.click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  await idle(page);
  const id = await canvas<string>(page, '(s) => s.selectedBlock.id');
  const frame = page.frameLocator('#tb-live-iframe');
  const running = () => frame.locator('body').evaluate(() => (window as any).TbAudit.isRunning() as boolean);

  try {
    await canvas(page, `(s) => {
      s.updateProp('logo_text', 'برند');
      s.updateProp('nav_links', [{ label: 'خانه', url: '/' }]);
      s.updateProp('phone', '${'۸'.repeat(150)}');
      s.updateProp('cta_text', 'ثبت سفارش');
      s.updateProp('cta_url', '');
      s.updateProp('text_color', '#d4d4d8');
      s.updateProp('bg_color', '#ffffff');
    }`);
    await idle(page);
    await canvas(page, `(s) => s.setDevice('mobile')`);
    await expect(frame.locator(`#tb-${id} .tb-nav-header-phone`)).toHaveCount(1, { timeout: 15_000 });
    await expect.poll(() => frame.locator('body').evaluate(() => document.documentElement.clientWidth)).toBeLessThan(500);
    // The header contains a long phone itself (overflow hidden at phone
    // widths), so the blocker is planted in the preview DOM: an 800 px item in
    // the header row. The next prop update re-renders the block and drops it.
    await frame.locator(`#tb-${id} .tb-nav-header-inner`).evaluate((el) => {
      const plant = document.createElement('span');
      plant.setAttribute('data-planted', '1');
      plant.style.cssText = 'display:inline-block;flex:none;width:800px;height:4px';
      el.appendChild(plant);
    });
    expect(await running(), 'nothing audits before the tab opens').toBe(false);
    await canvas(page, '(s) => s.selectBlockById(null)');

    // Open the tab with Inspect OFF.
    await expect(page.locator('[data-tb-inspect-toggle]')).toHaveAttribute('aria-pressed', 'false');
    await page.locator('[data-testid="tb-canvas-health-tab"]').click();
    const panel = page.locator('[data-tb-health]');
    const row = panel.locator(`[data-tb-health-row="nav_header#${id}"]`);
    await expect(row).toHaveAttribute('data-tone', 'error', { timeout: 15_000 });
    await expect(panel.locator('[data-tb-health-row]').first()).toHaveAttribute('data-tb-health-row', `nav_header#${id}`);
    await expect(row.locator('.tbh-badge')).toContainText('خطا');
    await expect(row.locator('.tbh-name')).not.toHaveText('');
    await expect(panel.locator('[data-tb-health-headline]')).toContainText('ایراد دارد');
    expect(await running()).toBe(true);
    await expect(page.locator('[data-tb-audit-chip]'), 'the toolbar chip stays an Inspect affordance').toBeHidden();

    await row.getByRole('button', { name: 'جزئیات' }).click();
    const codes = await row.locator('.tbh-findings code').allTextContents();
    expect(codes).toEqual(expect.arrayContaining(['WIDGET_OVERFLOW', 'DEAD_LINK', 'CONTRAST_LOW']));

    const clip = () => page.evaluate(() => navigator.clipboard.readText());
    await row.locator('[data-tb-health-copy]').click();
    await expect.poll(clip).toContain(`nav_header#${id}`);
    const prompt = await clip();
    for (const code of ['[blocker] WIDGET_OVERFLOW', '[warning] DEAD_LINK', '[warning] CONTRAST_LOW']) expect(prompt).toContain(code);
    expect(prompt).toMatch(/در عرض \d{3}px/);
    await expect(row.locator('[data-tb-health-copy]')).toHaveText('کپی شد ✓');
    await panel.screenshot({ path: 'test-results/health-tab-mobile.png' });

    // The row selects its block.
    await row.locator('.tbh-main').click();
    await expect.poll(() => canvas<string | null>(page, '(s) => s.selectedBlock?.id ?? null')).toBe(id);

    // Inspect on → off does not stop the tab's checks.
    await page.locator('[data-tb-inspect-toggle]').click();
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(1);
    await page.locator('[data-tb-inspect-toggle]').click();
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(0);
    expect(await running(), 'the tab still holds the checks').toBe(true);

    // A fix turns the row amber live (dead CTA and contrast remain). A live
    // update may patch the header in place, so the plant is removed first; the
    // prop update's Canvas lifecycle event re-queues the widget.
    await frame.locator('[data-planted]').evaluateAll((els) => els.forEach((el) => el.remove()));
    await canvas(page, `(s) => { s.selectBlockById('${id}'); s.updateProp('phone', '021-1234'); }`);
    await expect(row).toHaveAttribute('data-tone', 'warn', { timeout: 15_000 });

    // A preview reload keeps the checks running and the row listed. The reload
    // renders the saved draft, so save the live edits first (the finally block
    // removes the header and saves again).
    await idle(page);
    await canvas(page, '(s) => s.saveCurrentPage()');
    await idle(page);
    await canvas(page, '(s) => s.reloadIframe()');
    await idle(page);
    await expect.poll(running, { timeout: 15_000 }).toBe(true);
    await expect(row).toHaveAttribute('data-tone', 'warn', { timeout: 15_000 });

    // Closing the tab stops the checks.
    await page.locator('button', { hasText: 'بلوک‌ها' }).first().click();
    await expect.poll(running).toBe(false);
    expect(problems, problems.join('\n')).toEqual([]);
  } finally {
    await canvas(page, `(s) => { s.setDevice('desktop'); s.selectBlockById('${id}'); if (s.selectedBlock?.id === '${id}') s.removeSelected(); }`);
    await idle(page);
    await canvas(page, '(s) => s.saveCurrentPage()');
    await idle(page);
  }
});
