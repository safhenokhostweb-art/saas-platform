import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

const fx = loadFixtures();
const VISUAL_PRIMARY = '#0f766e';
const RAW_PRIMARY = '#1d4ed8';

type ThemeState = {
  current_release_id: number | null;
  global_styles: Record<string, unknown>;
  pages: Record<string, unknown>;
  header_blocks: unknown[];
  footer_blocks: unknown[];
  release: null | { id: number; snapshot: Record<string, unknown> };
};

function themeState(): ThemeState {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::findOrFail(${fx.theme_id});
$release = $theme->currentRelease()->first();
echo json_encode([
  'current_release_id' => $theme->current_release_id,
  'global_styles' => $theme->global_styles,
  'pages' => $theme->pages,
  'header_blocks' => $theme->header_blocks,
  'footer_blocks' => $theme->footer_blocks,
  'release' => $release ? ['id' => $release->id, 'snapshot' => $release->snapshot] : null,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
`;

  return JSON.parse(execFileSync(php, ['-r', source], {
    cwd: process.cwd(),
    encoding: 'utf8',
  }).trim()) as ThemeState;
}

async function waitForCanvas(page: import('@playwright/test').Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    return root?._x_dataStack?.some((state) => state.showGlobalStylesPanel === false) ?? false;
  });
}

test.describe.serial('Theme Canvas — visual Global Styles', () => {
  test('interoperates with raw JSON, preserves unknown keys, refreshes preview, and publishes an exact snapshot', async ({ page }) => {
    test.setTimeout(180_000);
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await loginAsAdmin(page, fx);

    // CASE C + setup for CASE B: author the existing raw JSON contract first.
    await page.goto(`/admin/theme-builder/${fx.theme_id}/edit`);
    await page.locator('[data-tab="styles"]').click();
    const rawEditor = page.locator('#json-styles');
    const parsedRaw = JSON.parse(await rawEditor.inputValue() || '{}');
    // Empty PHP arrays serialize as [] in the legacy raw editor. Convert
    // that empty representation to an object before assigning style keys.
    const raw: Record<string, unknown> = Array.isArray(parsedRaw) ? {} : parsedRaw;
    raw.primary_color = RAW_PRIMARY;
    raw.future_token_ui_c2c = 'scalar-kept';
    raw.plugin_metadata_ui_c2c = { vendor: 'e2e', nested: { keep: true } };
    await rawEditor.fill(JSON.stringify(raw, null, 2));
    const rawSave = page.waitForResponse((response) =>
      response.url().endsWith(`/admin/theme-builder/${fx.theme_id}`)
        && response.request().method() === 'POST'
    );
    await page.locator('#theme-form button[type="submit"]').click();
    expect((await rawSave).status()).toBeLessThan(400);

    const before = themeState();
    const prerequisiteReleaseId = before.current_release_id;
    expect(prerequisiteReleaseId).not.toBeNull();

    await page.goto(canvasUrl(fx));
    await waitForCanvas(page);
    await page.getByTestId('global-styles-entry').click();
    const dialog = page.getByTestId('global-styles-dialog');
    await expect(dialog).toBeVisible();
    await expect(page.locator('#global-style-primary-color')).toHaveValue(RAW_PRIMARY);

    await page.locator('#global-style-primary-color').fill(VISUAL_PRIMARY);
    await expect(page.getByTestId('global-styles-dirty')).toBeVisible();
    const saveResponsePromise = page.waitForResponse((response) =>
      response.url().endsWith('/canvas/global-styles')
        && response.request().method() === 'PATCH'
    );
    await page.getByTestId('global-styles-save').click();
    const saveResponse = await saveResponsePromise;
    expect(saveResponse.status(), await saveResponse.text()).toBe(200);
    const saveJson = await saveResponse.json();
    expect(saveJson.css_variables['--tb-primary']).toBe(VISUAL_PRIMARY);
    expect(saveJson.global_styles.future_token_ui_c2c).toBe('scalar-kept');
    expect(saveJson.global_styles.plugin_metadata_ui_c2c).toEqual({ vendor: 'e2e', nested: { keep: true } });
    await expect(page.getByTestId('global-styles-dirty')).toBeHidden();

    // The existing live-preview endpoint/iframe must consume the normalized token.
    await expect.poll(async () => page.locator('#tb-live-iframe').evaluate((iframe: HTMLIFrameElement) =>
      getComputedStyle(iframe.contentDocument!.documentElement).getPropertyValue('--tb-primary').trim()
    )).toBe(VISUAL_PRIMARY);

    await page.reload();
    await waitForCanvas(page);
    await page.getByTestId('global-styles-entry').click();
    await expect(page.locator('#global-style-primary-color')).toHaveValue(VISUAL_PRIMARY);
    await page.getByRole('button', { name: 'بستن پنل استایل‌های سراسری' }).click();

    const afterSave = themeState();
    expect(afterSave.global_styles.primary_color).toBe(VISUAL_PRIMARY);
    expect(afterSave.global_styles.future_token_ui_c2c).toBe('scalar-kept');
    expect(afterSave.global_styles.plugin_metadata_ui_c2c).toEqual({ vendor: 'e2e', nested: { keep: true } });
    expect(afterSave.pages).toEqual(before.pages);
    expect(afterSave.header_blocks).toEqual(before.header_blocks);
    expect(afterSave.footer_blocks).toEqual(before.footer_blocks);

    // Real Canvas Publish — never a direct ThemeReleaseService call.
    const publishResponsePromise = page.waitForResponse((response) =>
      response.url().endsWith(`/admin/theme-builder/${fx.theme_id}/publish`)
        && response.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'انتشار', exact: true }).click();
    const publishResponse = await publishResponsePromise;
    expect(publishResponse.status(), await publishResponse.text()).toBe(200);

    const published = themeState();
    expect(published.current_release_id).not.toBe(prerequisiteReleaseId);
    expect(published.release?.id).toBe(published.current_release_id);
    const snapshotStyles = published.release?.snapshot.global_styles as Record<string, unknown>;
    expect(snapshotStyles.primary_color).toBe(VISUAL_PRIMARY);
    expect(snapshotStyles.future_token_ui_c2c).toBe('scalar-kept');
    expect(snapshotStyles.plugin_metadata_ui_c2c).toEqual({ vendor: 'e2e', nested: { keep: true } });

    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
  });

  for (const width of [1366, 768, 390] as const) {
    test(`keeps the Global Styles panel pointer- and keyboard-accessible at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await loginAsAdmin(page, fx);
      await page.goto(canvasUrl(fx));
      await waitForCanvas(page);

      const entry = page.getByTestId('global-styles-entry');
      await entry.scrollIntoViewIfNeeded();
      await entry.click();
      const dialog = page.getByTestId('global-styles-dialog');
      await expect(dialog).toBeVisible();
      await expect(page.locator('#global-style-primary-color')).toBeFocused();

      const controlsBox = await page.getByTestId('global-styles-controls').boundingBox();
      const save = page.getByTestId('global-styles-save');
      const saveBox = await save.boundingBox();
      expect(controlsBox).not.toBeNull();
      expect(saveBox).not.toBeNull();
      expect(saveBox!.x).toBeGreaterThanOrEqual(0);
      expect(saveBox!.x + saveBox!.width).toBeLessThanOrEqual(width);
      expect(saveBox!.y + saveBox!.height).toBeLessThanOrEqual(900);

      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
    });
  }
});
