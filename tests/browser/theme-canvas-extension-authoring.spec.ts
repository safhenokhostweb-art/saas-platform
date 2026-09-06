import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadFixtures, loginAsAdmin, canvasUrl, PALETTE_PANEL, TREE_PANEL, type E2EFixtures } from './helpers';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Phase 3G-B-6 Theme Canvas Extension-authoring E2E coverage.
 *
 * Requires fixtures seeded via:
 *   php artisan e2e:seed-canvas-fixtures > tests/browser/fixtures/e2e-fixtures.json
 * and a dev server (php artisan serve) + Redis running against the same DB.
 *
 * Tests run serially (test.describe.configure({ mode: 'serial' })) because
 * later steps (nested authoring, XSS check, failure UX) deliberately build on
 * state the earlier steps saved to the seeded Theme.
 */

const SCREENSHOT_DIR = path.join(__dirname, '__screenshots__');
fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

let fx: E2EFixtures;

test.beforeAll(() => {
  fx = loadFixtures();
});

test.describe.configure({ mode: 'serial' });

test.describe('Theme Canvas — commercial Extension authoring', () => {
  test('A. Palette exposes the selected extension element alongside canonical blocks', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await expect(page.locator('body')).toBeVisible();

    const palette = page.locator(PALETTE_PANEL);

    // Canonical built-in block (layout category, expanded by default).
    await expect(palette.getByText('بخش قهرمان (Hero)', { exact: true })).toBeVisible();

    // Extension leaf element (extension category, expanded by default per
    // Alpine's `expandedCats` initial state).
    await expect(palette.getByText('گالری E2E', { exact: true })).toBeVisible();

    // Extension container element also present.
    await expect(palette.getByText('گروه کارت E2E', { exact: true })).toBeVisible();

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01-palette.png'), fullPage: true });
  });

  test('B. Extensions modal shows the current version selection', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));

    await page.getByRole('button', { name: 'افزونه‌ها' }).click();
    const modal = page.locator('h3:has-text("افزونه‌های این قالب")').locator('xpath=..');
    await expect(modal).toBeVisible();

    // .first(): the identifier/version also appear a second time inside the
    // "add new" <select> options further down in the same modal.
    await expect(modal.getByText(fx.extension_identifier).first()).toBeVisible();
    await expect(modal.getByText(fx.release_version, { exact: true }).first()).toBeVisible();

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02-extensions-modal.png') });

    // Close the modal without changing anything.
    await modal.getByRole('button', { name: 'بستن' }).click();
  });

  test('C. Author the extension leaf element: add, edit text/toggle/select/number, save, reload', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));

    // Add the extension's leaf block by clicking it in the palette (scoped
    // to the palette panel — the same label also appears, hidden, in the
    // "add child" modal's block grid).
    await page.locator(PALETTE_PANEL).getByRole('button', { name: /گالری E2E/ }).click();

    // It should now be selected and its props panel visible with our 6
    // control types. Edit at least text / toggle / select / number.
    await expect(page.locator('#ctrl-title')).toBeVisible();

    await page.locator('#ctrl-title').fill('عنوان تست نویسنده');
    await page.locator('#ctrl-limit').fill('9');
    // The real checkbox is visually `sr-only` (a custom toggle switch is
    // drawn on top of it via CSS), so we click the wrapping <label> instead
    // — real browsers forward a label click to its associated form control
    // regardless of what's drawn on top, which is the same thing a real
    // user clicking the visible switch would trigger.
    await page.locator('label:has(#ctrl-featured)').click();
    await expect(page.locator('#ctrl-featured')).toBeChecked();
    await page.locator('#ctrl-layout').selectOption('list');
    await page.locator('#ctrl-tint').fill('#ff0000');

    // Explicit save via the toolbar Save button.
    await page.getByRole('button', { name: 'ذخیره', exact: true }).click();
    await expect(page.getByText('ذخیره شد', { exact: false })).toBeVisible({ timeout: 10_000 });

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03-authored-leaf-block.png'), fullPage: true });

    // Reload the whole Canvas page and verify the block + edited props
    // persisted (re-select it from the structure tree).
    await page.reload();
    await page.getByRole('button', { name: 'ساختار' }).click(); // switch left panel to the Structure tab
    await page.locator(TREE_PANEL).getByText('گالری E2E', { exact: true }).first().click();

    await expect(page.locator('#ctrl-title')).toHaveValue('عنوان تست نویسنده');
    await expect(page.locator('#ctrl-limit')).toHaveValue('9');
    await expect(page.locator('#ctrl-featured')).toBeChecked();
    await expect(page.locator('#ctrl-layout')).toHaveValue('list');
  });

  test('D. Nested authoring: extension container + built-in child block, save, reload', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));

    // Add the extension's container block (scoped to the palette panel —
    // same duplicate-label concern as test C above).
    await page.locator(PALETTE_PANEL).getByRole('button', { name: /گروه کارت E2E/ }).click();

    // Its props panel shows a "Children (0)" section with an "افزودن" button.
    // Scoped with :visible — the (hidden) Extensions-modal "add" button
    // shares the same label and would otherwise make this a strict-mode
    // ambiguity.
    await page.locator('button:has-text("افزودن"):visible').click();

    // Add-child modal: pick the built-in "heading" block ("عنوان"). Note:
    // getByRole('button', {name:...}) would need the emoji-prefixed
    // accessible name ("🔠 عنوان") — getByText matches just the label span's
    // own text content, which is exactly "عنوان".
    const addChildModal = page.locator('h3:has-text("افزودن بلوک فرزند به")').locator('xpath=..');
    await addChildModal.getByText('عنوان', { exact: true }).click();

    // Save and reload.
    await page.getByRole('button', { name: 'ذخیره', exact: true }).click();
    await expect(page.getByText('ذخیره شد', { exact: false })).toBeVisible({ timeout: 10_000 });

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04-nested-tree-before-reload.png'), fullPage: true });

    await page.reload();
    await page.getByRole('button', { name: 'ساختار' }).click();

    // The structure tree should show both the container and its nested
    // heading child underneath it.
    const tree = page.locator(TREE_PANEL);
    await expect(tree.getByText('گروه کارت E2E', { exact: true }).first()).toBeVisible();
    await expect(tree.getByText('عنوان', { exact: true }).first()).toBeVisible();

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05-nested-tree-after-reload.png'), fullPage: true });
  });

  test('E. XSS: script payload in a text control is neutralized, never executed', async ({ page }) => {
    let dialogFired = false;
    page.on('dialog', async (dialog) => {
      dialogFired = true;
      await dialog.dismiss();
    });

    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));

    await page.getByRole('button', { name: 'ساختار' }).click();
    await page.locator(TREE_PANEL).getByText('گالری E2E', { exact: true }).first().click();

    const payload = '<script>alert(1)</script>';
    await page.locator('#ctrl-title').fill(payload);
    await page.getByRole('button', { name: 'ذخیره', exact: true }).click();
    await expect(page.getByText('ذخیره شد', { exact: false })).toBeVisible({ timeout: 10_000 });

    await page.reload();

    // Check the rendered iframe's DOM (not just visually) for zero <script>
    // elements carrying our payload, and confirm no dialog (alert) fired.
    const iframe = page.frameLocator('#tb-live-iframe');
    await expect(iframe.locator('script:has-text("alert(1)")')).toHaveCount(0);
    expect(dialogFired).toBe(false);
  });

  test('F. Publish path: exercise the visible Publish action from the Canvas', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));

    await page.getByRole('button', { name: 'انتشار', exact: true }).click();

    // Visible-UI check only: report whatever toast surfaces (success or a
    // clear validation error), never fabricate a backend assertion here.
    // publishTheme() saves then publishes, so the toast may read either the
    // save step's or the publish step's message depending on timing — both
    // are legitimate "a visible, non-crashing result appeared" evidence.
    await expect(page.getByRole('button', { name: 'انتشار', exact: true })).toBeEnabled({ timeout: 15_000 });
    const toast = page.locator('div.fixed.bottom-6');
    const toastText = (await toast.count()) > 0 && (await toast.isVisible()) ? await toast.innerText() : '(no toast visible at capture time)';
    console.log('[publish result toast]', toastText);
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06-publish-result.png'), fullPage: true });
  });

  test('G. Failure UX: removing an in-use extension selection surfaces a real error, not a blank page/crash', async ({ page }) => {
    page.on('dialog', (dialog) => dialog.accept()); // the removal flow uses window.confirm()

    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));

    await page.getByRole('button', { name: 'افزونه‌ها' }).click();
    const modal = page.locator('h3:has-text("افزونه‌های این قالب")').locator('xpath=..');
    await expect(modal).toBeVisible();

    // Remove the only selection — the Theme still has ext:e2etest:* blocks
    // on the home page from the earlier authoring tests, so the server-side
    // validateBlocksAgainstSelections() gate must reject this save.
    await modal.getByRole('button', { name: 'حذف' }).first().click();

    // Expect a visible, readable error toast — not a blank page or a raw
    // unhandled exception. The toast is styled red (bg-red-600) for the
    // 'error' type regardless of the exact server-supplied message text
    // (validateBlocksAgainstSelections()'s Persian message does not
    // necessarily contain the literal word "خطا"), so match on that instead
    // of guessing the wording, and log the actual text for the report.
    const errorToast = page.locator('div.fixed.bottom-6.bg-red-600');
    await expect(errorToast).toBeVisible({ timeout: 10_000 });
    console.log('[failure-UX toast text]', await errorToast.innerText());

    // Page must still be alive/interactive, not crashed.
    await expect(page.getByRole('button', { name: 'افزونه‌ها' })).toBeVisible();

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07-failure-ux-remove-in-use-extension.png'), fullPage: true });
  });
});

test.describe('Theme Canvas — responsive smoke checks', () => {
  test('Desktop viewport (1280px)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08-desktop-1280.png'), fullPage: false });
  });

  test('Tablet viewport (768px) — check toolbar/page-tabs overlap', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await page.waitForTimeout(500);

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '09-tablet-768.png'), fullPage: false });

    // Toolbar bar (h-14) contains Back/Theme-name/Page-tabs/Actions all in
    // ONE flex row. Check whether the trailing action buttons (e.g. Publish)
    // are pushed outside the 768px viewport / overlapping the page-tabs
    // region, which is the known prior defect this check targets.
    const toolbar = page.locator('div.h-14.bg-white.border-b').first();
    const publishBtn = page.getByRole('button', { name: 'انتشار', exact: true });
    const pageTabsRegion = page.locator('div.flex.items-center.gap-1\\.5.overflow-x-auto.flex-1').first();

    const toolbarBox = await toolbar.boundingBox();
    const publishBox = await publishBtn.boundingBox();
    const tabsBox = await pageTabsRegion.boundingBox();

    let overlapReport = 'COULD NOT MEASURE (one or more elements not found)';
    if (toolbarBox && publishBox && tabsBox) {
      const publishWithinViewport = publishBox.x + publishBox.width <= 768 + 1;
      const publishOverlapsTabs = !(publishBox.x >= tabsBox.x + tabsBox.width || publishBox.x + publishBox.width <= tabsBox.x);
      overlapReport = JSON.stringify({
        toolbarBox, publishBox, tabsBox, publishWithinViewport, publishOverlapsTabs,
      });
    }
    // Surface the measurement in the test output/report rather than asserting
    // a specific pass/fail — the task asks us to report what we observe.
    console.log('[768px overlap measurement]', overlapReport);
  });

  test('Mobile viewport (390px) — smoke check only', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '10-mobile-390.png'), fullPage: false });
  });
});
