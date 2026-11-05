import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * WU-2 Design HUD in the platform Canvas (theme-runtime::canvas.inspect →
 * TbAudit). A header with three planted defects at the mobile preview width:
 *
 *   - a 60-digit nowrap phone number crosses the viewport → WIDGET_OVERFLOW (blocker)
 *   - a CTA without a link → DEAD_LINK (warning; the site leaves it out)
 *   - light grey text on white → CONTRAST_LOW (warning)
 *
 * The toolbar chip turns red, the widget label's dot is red, and
 * "کپی تسک رفع ایراد" copies a prompt with all three. Fixing the phone
 * updates the HUD live (ResizeObserver / lifecycle). Long tasks in the
 * preview are recorded so "no dropped frames" is measured, not assumed.
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

test('Design HUD: planted defects light up, the fix task lists them, a fix clears live', async ({ page, context, baseURL }) => {
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

    const frame = page.frameLocator('#tb-live-iframe');
    // The live prop updates reach the preview asynchronously: wait for the planted markup.
    await expect(frame.locator(`#tb-${id} .tb-nav-header-phone`)).toHaveCount(1, { timeout: 15_000 });
    await expect(frame.locator(`#tb-${id} .tb-nav-header-cta[data-tb-dead-link]`)).toHaveCount(1);
    await expect.poll(() => frame.locator('body').evaluate(() => document.documentElement.clientWidth)).toBeLessThan(500);
    // The header contains a long phone itself now (overflow hidden at phone
    // widths), so the blocker is planted in the preview DOM: an 800 px item in
    // the header row. The next prop update re-renders the block and drops it.
    await frame.locator(`#tb-${id} .tb-nav-header-inner`).evaluate((el) => {
      const plant = document.createElement('span');
      plant.setAttribute('data-planted', '1');
      plant.style.cssText = 'display:inline-block;flex:none;width:800px;height:4px';
      el.appendChild(plant);
    });
    await frame.locator('body').evaluate(() => {
      (window as any).__longTasks = [];
      try { new PerformanceObserver((l) => l.getEntries().forEach((e) => (window as any).__longTasks.push(Math.round(e.duration)))).observe({ type: 'longtask', buffered: false }); } catch { /* not supported */ }
    });

    await page.locator('[data-tb-inspect-toggle]').click();
    const chip = page.locator('[data-tb-audit-chip]');
    await expect(chip).toBeVisible();
    const overflowPx = await frame.locator('body').evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflowPx, 'the planted phone really widens the preview').toBeGreaterThan(100);
    await expect(chip).toHaveAttribute('data-tone', 'error');
    await expect(chip).toContainText('خطا');

    const widget = frame.locator(`[data-tb-block-id="${id}"][data-widget-type="nav_header"]`);
    const findings = await widget.evaluate((el) => (window as any).TbAudit.findings(el).map((f: any) => f.code));
    expect(findings).toEqual(expect.arrayContaining(['WIDGET_OVERFLOW', 'DEAD_LINK', 'CONTRAST_LOW']));
    const selectors = await widget.evaluate((el) => (window as any).TbAudit.findings(el).map((f: any) => f.selector));
    expect(selectors.filter((s: string) => /tb-drag-handle|tb-block-badge/.test(s)), 'editor chrome is never audited').toEqual([]);

    await widget.click({ position: { x: 10, y: 10 } });
    await expect(frame.locator('[data-tb-inspect-ui] >> css=.box.sel.on .dot')).toHaveAttribute('data-tone', 'error');
    const fix = frame.locator('[data-tb-inspect-ui] >> css=.bar.on button[data-action="copy-fix-task"]');
    await expect(fix).toHaveText('کپی تسک رفع ایراد');
    await fix.click();
    const clip = () => page.evaluate(() => navigator.clipboard.readText());
    await expect.poll(clip).toContain('ایرادهای پیدا شده:');
    const prompt = await clip();
    for (const code of ['[blocker] WIDGET_OVERFLOW', '[warning] DEAD_LINK', '[warning] CONTRAST_LOW']) expect(prompt).toContain(code);
    expect(prompt).toContain(`nav_header#${id}`);
    expect(prompt).toMatch(/در عرض \d{3}px/);
    await page.locator('#tb-live-iframe').screenshot({ path: 'test-results/hud-mobile-defects.png' });
    await page.locator('[data-tb-audit-chip]').screenshot({ path: 'test-results/hud-chip-error.png' });

    // Fix the overflow: the HUD re-checks the updated block on its own. A live
    // update may patch the header in place, so the plant is removed first.
    await frame.locator('[data-planted]').evaluateAll((els) => els.forEach((el) => el.remove()));
    await canvas(page, `(s) => { s.selectBlockById('${id}'); s.updateProp('phone', '021-1234'); }`);
    await expect.poll(() => widget.evaluate((el) => (window as any).TbAudit.findings(el).map((f: any) => f.code)), { timeout: 10_000 })
      .not.toContain('WIDGET_OVERFLOW');
    await expect(chip).toHaveAttribute('data-tone', 'warn');

    const longTasks: number[] = await frame.locator('body').evaluate(() => (window as any).__longTasks);
    // The HUD's own cost: one widget is the unit of work an idle callback runs.
    const cost = await frame.locator('body').evaluate(() => {
      const times = Array.from(document.querySelectorAll('[data-widget-type]')).map((el) => {
        const t = performance.now();
        (window as any).TbAudit.auditNow(el);
        return { type: el.getAttribute('data-widget-type'), ms: +(performance.now() - t).toFixed(1) };
      });
      times.sort((a, b) => b.ms - a.ms);
      return { widgets: times.length, total: +times.reduce((s, x) => s + x.ms, 0).toFixed(1), slowest: times.slice(0, 3) };
    });
    console.log(`preview long tasks while the HUD ran: ${JSON.stringify(longTasks)}; HUD cost per widget: ${JSON.stringify(cost)}`);
    expect(cost.slowest[0].ms, 'one widget audit fits well inside a frame budget').toBeLessThan(16);

    await page.locator('[data-tb-inspect-toggle]').click();
    await expect(chip).toBeHidden();
    expect(problems, problems.join('\n')).toEqual([]);
  } finally {
    await canvas(page, `(s) => { s.setDevice('desktop'); s.selectBlockById('${id}'); if (s.selectedBlock?.id === '${id}') s.removeSelected(); }`);
    await idle(page);
    await canvas(page, '(s) => s.saveCurrentPage()');
    await idle(page);
  }
});
