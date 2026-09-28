import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loadFixtures, loginAsAdmin } from './helpers';

const fx = loadFixtures();

type ThemeSnapshot = {
  id: number;
  slug: string;
  status: string;
  current_release_id: number | null;
  pages: Record<string, { blocks?: Array<Record<string, unknown>> }>;
  header_blocks: Array<Record<string, unknown>>;
  footer_blocks: Array<Record<string, unknown>>;
  global_styles: Record<string, unknown>;
  release_count: number;
};

/** Creates a fresh, uniquely-slugged Theme dedicated to this spec — never
 *  the shared e2e-fixtures theme other spec files also drive, so checkpoint
 *  restore/unpublish here can never corrupt another suite's state. */
function createIsolatedTheme(): { id: number; slug: string } {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::create([
    'name' => 'قالب تست عملیات کانواس UI-C2D-C2E',
    'slug' => 'canvas-actions-e2e-' . uniqid(),
    'type' => App\\Models\\Theme::TYPE_FULL,
    'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
        ['id' => 'blk_hero_e2e', 'type' => 'hero', 'props' => ['title' => 'نسخه اول']],
    ]]],
    'header_blocks' => [['id' => 'blk_header_e2e', 'type' => 'nav_header', 'props' => ['logo_text' => 'E2E-V1']]],
    'footer_blocks' => [['id' => 'blk_footer_e2e', 'type' => 'nav_footer', 'props' => ['logo_text' => 'E2E-V1']]],
    'global_styles' => ['colors' => ['primary' => '#123456']],
    'settings' => ['extension_selections' => []],
]);
echo json_encode(['id' => $theme->id, 'slug' => $theme->slug], JSON_UNESCAPED_UNICODE);
`;
  return JSON.parse(execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8' }).trim());
}

function readTheme(themeId: number): ThemeSnapshot {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::findOrFail(${themeId});
echo json_encode([
    'id' => $theme->id,
    'slug' => $theme->slug,
    'status' => $theme->status,
    'current_release_id' => $theme->current_release_id,
    'pages' => $theme->pages,
    'header_blocks' => $theme->header_blocks,
    'footer_blocks' => $theme->footer_blocks,
    'global_styles' => $theme->global_styles,
    'release_count' => $theme->releases()->count(),
], JSON_UNESCAPED_UNICODE);
`;
  return JSON.parse(execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8' }).trim());
}

async function waitForCanvas(page: import('@playwright/test').Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    return root?._x_dataStack?.some((state) => state.showMoreMenu === false) ?? false;
  });
}

test.describe.serial('Theme Canvas — UI-C2D/C2E action surface (checkpoint, clone, export, import, unpublish)', () => {
  let theme: { id: number; slug: string };

  test.beforeAll(() => {
    theme = createIsolatedTheme();
  });

  test('checkpoint: create, list, and exact restore through the canonical Canvas', async ({ page }) => {
    // login + canvas load + checkpoint modal + two saves + restore reload:
    // the single-worker canvas page load alone costs 15-25s on the dev
    // machine, so the default 60s budget cannot hold the whole flow (the
    // sibling canvas specs budget 180-480s for the same reason).
    test.setTimeout(240_000);
    page.on('dialog', (dialog) => dialog.accept());

    await loginAsAdmin(page, fx);
    await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
    await waitForCanvas(page);

    // Create checkpoint A (current: header logo_text = E2E-V1).
    await page.getByTestId('tb-more-actions-toggle').click();
    await page.getByTestId('tb-action-checkpoints').click();
    await expect(page.getByTestId('tb-checkpoints-modal')).toBeVisible();
    await page.getByTestId('tb-checkpoint-label-input').fill('چک‌پوینت الف');
    const createResp = page.waitForResponse((r) =>
      r.url().includes(`/theme-builder/${theme.id}/revision`) && r.request().method() === 'POST'
    );
    await page.getByTestId('tb-checkpoint-create').click();
    await createResp;
    await expect(page.getByTestId('tb-checkpoint-list')).toContainText('چک‌پوینت الف');
    await page.getByRole('button', { name: 'بستن' }).click();

    // Change state to B: switch to header zone, change the block, save.
    await page.getByRole('button', { name: 'هدر' }).click();
    const saveResp1 = page.waitForResponse((r) =>
      r.url().includes(`/theme-builder/${theme.id}/canvas/save`) && r.request().method() === 'PUT'
    );
    await page.evaluate(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & { _x_dataStack?: Array<Record<string, unknown>> };
      const state = root._x_dataStack![0] as Record<string, unknown> & { headerBlocks: Array<{ props: Record<string, unknown> }>; isDirty: boolean };
      state.headerBlocks[0].props.logo_text = 'STATE-B';
      state.isDirty = true;
    });
    await page.evaluate(() => (window as unknown as { Alpine: { $data: (el: Element) => { saveCurrentPage: () => Promise<boolean> } } }).Alpine
      .$data(document.querySelector('[x-data="themeCanvas()"]')!).saveCurrentPage());
    await saveResp1;

    let state = readTheme(theme.id);
    expect((state.header_blocks[0].props as Record<string, unknown>).logo_text).toBe('STATE-B');

    // Restore checkpoint A — real UI, real confirmation dialog (auto-accepted above).
    await page.getByTestId('tb-more-actions-toggle').click();
    await page.getByTestId('tb-action-checkpoints').click();
    await expect(page.getByTestId('tb-checkpoints-modal')).toBeVisible();
    const restoreResp = page.waitForResponse((r) =>
      /\/admin\/theme-revisions\/\d+\/restore$/.test(r.url()) && r.request().method() === 'POST'
    );
    const reloadPromise = page.waitForEvent('load');
    await page.getByTestId('tb-checkpoint-restore').first().click();
    const restoreResponse = await restoreResp;
    expect(restoreResponse.status()).toBe(200);
    await reloadPromise; // restoreCheckpoint() does window.location.reload() on success

    state = readTheme(theme.id);
    expect((state.header_blocks[0].props as Record<string, unknown>).logo_text).toBe('E2E-V1');
    expect((state.pages.home.blocks ?? [])[0].props).toMatchObject({ title: 'نسخه اول' });
    expect(state.global_styles.colors).toMatchObject({ primary: '#123456' });
  });

  test('clone: creates a distinct theme and opens its own Canvas', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
    await waitForCanvas(page);

    await page.getByTestId('tb-more-actions-toggle').click();
    await page.getByTestId('tb-action-clone').click();
    await expect(page.getByTestId('tb-clone-modal')).toBeVisible();
    await page.getByTestId('tb-clone-name-input').fill('کپی از طریق کانواس E2E');

    // Read the JSON via response.body()/text() ASAP is still racy against the
    // client's own immediate window.location.href navigation on success — the
    // resulting URL itself is the reliable source of the new theme id.
    await page.getByTestId('tb-clone-confirm').click();
    // Plain /theme-builder/\d+/canvas/ already matches the CURRENT page (we
    // started there), so waitForURL must explicitly require a DIFFERENT id.
    await page.waitForURL((url) => {
      const m = url.pathname.match(/\/theme-builder\/(\d+)\/canvas/);
      return !!m && Number(m[1]) !== theme.id;
    }, { timeout: 15_000 });
    await waitForCanvas(page);

    const clonedThemeId = Number(page.url().match(/\/theme-builder\/(\d+)\/canvas/)![1]);
    expect(clonedThemeId).not.toBe(theme.id);

    const cloned = readTheme(clonedThemeId);
    expect(cloned.status).toBe('draft');
    expect((cloned.header_blocks[0].props as Record<string, unknown>).logo_text).toBe('E2E-V1');
    expect(cloned.pages.home).toBeTruthy();
  });

  test('export: downloads a JSON file containing pages/header/footer/global styles', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
    await waitForCanvas(page);

    await page.getByTestId('tb-more-actions-toggle').click();
    const downloadPromise = page.waitForEvent('download');
    await page.getByTestId('tb-action-export').click();
    const download = await downloadPromise;
    const streamPath = await download.path();
    expect(streamPath).toBeTruthy();

    const fs = await import('node:fs');
    const exported = JSON.parse(fs.readFileSync(streamPath!, 'utf8'));
    expect(exported.version).toBeGreaterThanOrEqual(2);
    expect(exported.pages.home).toBeTruthy();
    expect(exported.header_blocks[0].props.logo_text).toBe('E2E-V1');
    expect(exported.footer_blocks.length).toBeGreaterThan(0);
    expect(exported.global_styles.colors.primary).toBe('#123456');
    expect(exported).not.toHaveProperty('site_id');
  });

  test('import: real file upload creates a new theme and opens its Canvas with equivalent state', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
    await waitForCanvas(page);

    const fs = await import('node:fs');
    const os = await import('node:os');
    const path = await import('node:path');
    const exportPayload = {
      version: 2,
      name: 'وارد شده از طریق کانواس E2E',
      pages: { home: { title: 'خانه', slug: '', blocks: [{ id: 'blk_import', type: 'hero', props: { title: 'محتوای وارد شده' } }] } },
      header_blocks: [{ id: 'blk_h_import', type: 'nav_header', props: { logo_text: 'IMPORTED' } }],
      footer_blocks: [],
      global_styles: { colors: { primary: '#abcdef' } },
      settings: { extension_selections: {} },
    };
    const tmpFile = path.join(os.tmpdir(), `theme-import-e2e-${Date.now()}.json`);
    fs.writeFileSync(tmpFile, JSON.stringify(exportPayload));

    await page.getByTestId('tb-more-actions-toggle').click();
    const fileChooserPromise = page.waitForEvent('filechooser');
    await page.getByTestId('tb-action-import').click();
    const fileChooser = await fileChooserPromise;
    // The client navigates (window.location.href) immediately after parsing
    // this response, which can evict the CDP response body before the test
    // reads it — wait for the resulting Canvas URL instead of the response
    // JSON, and recover the new theme id from the URL itself.
    await fileChooser.setFiles(tmpFile);
    // Same as clone: the plain pattern already matches the CURRENT page.
    await page.waitForURL((url) => {
      const m = url.pathname.match(/\/theme-builder\/(\d+)\/canvas/);
      return !!m && Number(m[1]) !== theme.id;
    }, { timeout: 15_000 });
    await waitForCanvas(page);

    const importedThemeId = Number(page.url().match(/\/theme-builder\/(\d+)\/canvas/)![1]);
    expect(importedThemeId).not.toBe(theme.id);

    const imported = readTheme(importedThemeId);
    expect((imported.header_blocks[0].props as Record<string, unknown>).logo_text).toBe('IMPORTED');
    expect(imported.pages.home.blocks?.[0].props).toMatchObject({ title: 'محتوای وارد شده' });
    expect(imported.status).toBe('draft');

    fs.unlinkSync(tmpFile);
  });

  test('unpublish: real UI flips status without mutating the release ledger', async ({ page }) => {
    page.on('dialog', (dialog) => dialog.accept());

    // Reaching "published" through the real Publish button also requires
    // passing the full Commercial Quality Gate (accessibility, color
    // contrast, commercial metadata, etc.) — a separate, already-tested
    // concern (ThemeCommercialQualityGate's own suite) that this minimal
    // fixture theme isn't shaped for. What THIS test verifies is narrower:
    // given an already-published theme with a real release, does the real
    // unpublish UI/endpoint change ONLY the status pointer? So the
    // published precondition is set up directly, exactly like
    // ThemeBuilderCheckpointAndActionsTest's PHPUnit coverage of the same
    // service method does.
    const php = process.env.PHP_BINARY || 'php';
    execFileSync(php, ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::findOrFail(${theme.id});
$release = App\\Models\\ThemeRelease::create([
    'theme_id' => $theme->id,
    'version' => '1.0.0',
    'changelog' => 'E2E fixture release.',
    'status' => App\\Models\\ThemeRelease::STATUS_PUBLISHED,
    'snapshot' => ['pages' => $theme->pages],
    'published_at' => now(),
]);
$theme->update(['status' => App\\Models\\Theme::STATUS_PUBLISHED, 'published_at' => now()]);
$theme->current_release_id = $release->id;
$theme->save();
`], { cwd: process.cwd(), encoding: 'utf8' });

    await loginAsAdmin(page, fx);
    await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
    await waitForCanvas(page);

    const beforeUnpublish = readTheme(theme.id);
    expect(beforeUnpublish.status).toBe('published');
    expect(beforeUnpublish.current_release_id).not.toBeNull();
    const releaseCountBefore = beforeUnpublish.release_count;

    await page.getByTestId('tb-more-actions-toggle').click();
    await expect(page.getByTestId('tb-action-unpublish')).toBeVisible();
    const unpublishResp = page.waitForResponse((r) =>
      r.url().endsWith(`/admin/theme-builder/${theme.id}/unpublish`) && r.request().method() === 'POST'
    );
    await page.getByTestId('tb-action-unpublish').click();
    const unpublishResponse = await unpublishResp;
    expect(unpublishResponse.status()).toBe(200);

    const afterUnpublish = readTheme(theme.id);
    expect(afterUnpublish.status).toBe('draft');
    expect(afterUnpublish.current_release_id).toBe(beforeUnpublish.current_release_id);
    expect(afterUnpublish.release_count).toBe(releaseCountBefore);

    // Menu now offers "انتشار" again via the main toolbar button, not "خروج از انتشار".
    await page.getByTestId('tb-more-actions-toggle').click();
    await expect(page.getByTestId('tb-action-unpublish')).toBeHidden();
  });

  for (const width of [1366, 768, 390] as const) {
    test(`more-actions menu is reachable, non-overflowing, and keyboard-usable at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await loginAsAdmin(page, fx);
      await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
      await waitForCanvas(page);

      const toggle = page.getByTestId('tb-more-actions-toggle');
      await toggle.scrollIntoViewIfNeeded();
      await expect(toggle).toBeVisible();
      await toggle.click();

      const menu = page.getByTestId('tb-more-actions-menu');
      await expect(menu).toBeVisible();
      const menuBox = await menu.boundingBox();
      expect(menuBox).not.toBeNull();
      // Sub-pixel tolerance: Chromium's flex-wrap + gap layout for the
      // toolbar's Actions group can place an item a fraction of a CSS
      // pixel (observed: -0.53px at 390px) outside its container due to
      // floating-point layout rounding — imperceptible and not a real
      // clipping defect. A whole-pixel (or larger) offset would not be.
      expect(menuBox!.x).toBeGreaterThanOrEqual(-1);
      expect(menuBox!.x + menuBox!.width).toBeLessThanOrEqual(width + 1);

      // Every action row is present and individually reachable (no overlap).
      for (const testId of ['tb-action-checkpoints', 'tb-action-clone', 'tb-action-export', 'tb-action-import']) {
        await expect(page.getByTestId(testId)).toBeVisible();
      }

      await page.keyboard.press('Escape');
    });
  }
});
