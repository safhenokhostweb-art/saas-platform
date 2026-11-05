import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * WU-1 Inspect Mode in the platform Canvas (theme-runtime::canvas.inspect):
 *
 *   - the toolbar button and Ctrl+I (from the editor chrome and from inside
 *     the preview) toggle the same state; it survives a preview reload;
 *   - hovering a widget labels it with its data-widget-type, clicking it
 *     opens the action bar, and "کپی شناسه" / "کپی اسکیما" / "کپی پرامپت AI"
 *     put the real values on the clipboard;
 *   - Ctrl+I inside an inline-edited field stays italic (no toggle);
 *   - turning Inspect on moves nothing (outline only).
 *
 * The header block is removed again, so the shared fixture page is left as found.
 */

const fx = loadFixtures();

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

test('Inspect Mode: toggle, label, action bar and clipboard copies', async ({ page, context, baseURL }) => {
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
  const button = page.locator('button[data-block-type="nav_header"]');
  if (!(await button.isVisible())) {
    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      for (const k of Object.keys(s.expandedCats)) s.expandedCats[k] = true;
    });
  }
  await button.click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  await idle(page);
  const id = await page.evaluate(() => (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0].selectedBlock.id as string);

  try {
    const toggle = page.locator('[data-tb-inspect-toggle]');
    const frame = page.frameLocator('#tb-live-iframe');
    // The header root repeats the wrapper hook (live selection); the outer one is the widget.
    const widget = frame.locator(`[data-tb-block-id="${id}"][data-widget-type="nav_header"]`).first();
    await expect(widget).toHaveCount(1);
    // Document coordinates inside the preview, after the editor's smooth scroll settles.
    const place = async () => {
      let last = '';
      for (let i = 0; i < 20; i++) {
        const now = JSON.stringify(await widget.evaluate((el) => {
          const r = el.getBoundingClientRect();
          return { x: r.left + window.scrollX, y: r.top + window.scrollY, w: r.width, h: r.height };
        }));
        if (now === last) return JSON.parse(now);
        last = now;
        await page.waitForTimeout(150);
      }
      return JSON.parse(last);
    };
    const before = await place();

    // Toolbar button.
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(1);
    const after = await place();
    expect(after, 'outline only: the widget does not move or resize').toEqual(before);

    // Hover label, then select.
    await widget.hover({ position: { x: 20, y: 10 } });
    const ui = frame.locator('[data-tb-inspect-ui] >> css=.box.hover.on .type');
    await expect(ui).toHaveText('nav_header');
    await expect(frame.locator('[data-tb-inspect-ui] >> css=.box.hover.on .name')).not.toHaveText('');
    await widget.click({ position: { x: 20, y: 10 } });
    const bar = frame.locator('[data-tb-inspect-ui] >> css=.bar.on');
    await expect(bar.locator('button[data-action]')).toHaveText(['کپی شناسه', 'کپی اسکیما', 'کپی پرامپت AI', '×']);
    await expect(frame.locator('[data-tb-inspect-ui] >> css=.box.sel.on .type')).toHaveText('nav_header');
    // The editor's own selection still runs.
    expect(await page.evaluate(() => (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0].selectedBlock?.id)).toBe(id);

    const clip = () => page.evaluate(() => navigator.clipboard.readText());
    await bar.locator('button[data-action="copy-type"]').click();
    await expect.poll(clip).toBe('nav_header');
    await bar.locator('button[data-action="copy-schema"]').click();
    await expect.poll(async () => { try { return JSON.parse(await clip()).type; } catch { return null; } }).toBe('nav_header');
    const schema = JSON.parse(await clip());
    expect(Object.keys(schema.schema)).toEqual(expect.arrayContaining(['logo_text', 'menu_align', 'social_links']));
    await bar.locator('button[data-action="copy-prompt"]').click();
    await expect.poll(clip).toContain(`شناسه ویجت: nav_header#${id}`);
    expect(await clip()).toContain('renderNavHeader()');
    const toastEl = frame.locator('[data-tb-inspect-ui] >> css=.toast.on');
    await expect(toastEl).toContainText('کپی شد');
    // The preview iframe is as tall as its content: the toast must sit by the bar, not at the frame's bottom.
    const [toastBox, barBox] = [await toastEl.boundingBox(), await bar.boundingBox()];
    expect(toastBox && barBox && Math.abs(toastBox.y - barBox.y) < 120, 'toast next to the action bar').toBe(true);

    await bar.screenshot({ path: 'test-results/inspect-action-bar.png' });
    await page.locator('#tb-live-iframe').screenshot({ path: 'test-results/inspect-canvas-1440.png' });

    // Ctrl+I from inside the preview turns it off and the toolbar follows.
    await widget.click({ position: { x: 20, y: 10 } });
    await page.keyboard.press('Control+KeyI');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(0);

    // Ctrl+I from the editor chrome turns it on again.
    await page.evaluate(() => { (document.activeElement as HTMLElement | null)?.blur(); window.focus(); });
    await page.keyboard.press('Control+KeyI');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(1);

    // A text field keeps Ctrl+I.
    await page.locator('input[x-model="librarySearch"]').focus();
    await page.keyboard.press('Control+KeyI');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');

    // The state survives a preview reload.
    await page.evaluate(() => (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0].reloadIframe());
    await idle(page);
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(1);

    await toggle.click();
    await expect(frame.locator('html[data-tb-inspect]')).toHaveCount(0);
    expect(problems, problems.join('\n')).toEqual([]);
  } finally {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
    await page.evaluate(() => (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0].saveCurrentPage());
    await idle(page);
  }
});
