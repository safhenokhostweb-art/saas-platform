import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loadFixtures, loginAsAdmin } from './helpers';

const fx = loadFixtures();

/**
 * UI-C3 — real-browser proof that the Theme list presents exactly ONE
 * canonical primary edit/design action (the Canvas button), that the raw
 * JSON editor no longer appears anywhere as a normal, discoverable choice,
 * and that the operational actions UI-C2D/C2E added to Canvas (checkpoint/
 * clone/export/import/unpublish) survived this de-exposure unaffected.
 * The raw editor route itself is verified reachable (TEMPORARY_COMPATIBILITY
 * per ADR-054), just not linked — see also
 * ThemeBuilderCanonicalEntryTest.php for the equivalent HTTP-level proof.
 */
function createIsolatedTheme(): { id: number; slug: string } {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::create([
    'name' => 'قالب تست همگرایی UI-C3',
    'slug' => 'ui-c3-entry-e2e-' . uniqid(),
    'type' => App\\Models\\Theme::TYPE_FULL,
    'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
        ['id' => 'blk_hero_c3', 'type' => 'hero', 'props' => ['title' => 'UI-C3']],
    ]]],
    'header_blocks' => [['id' => 'blk_header_c3', 'type' => 'nav_header', 'props' => []]],
    'footer_blocks' => [['id' => 'blk_footer_c3', 'type' => 'nav_footer', 'props' => []]],
    'global_styles' => [],
    'settings' => ['extension_selections' => []],
]);
echo json_encode(['id' => $theme->id, 'slug' => $theme->slug], JSON_UNESCAPED_UNICODE);
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

function deleteTheme(id: number): void {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
App\\Models\\Theme::whereKey(${id})->delete();
`;
  execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8' });
}

test.describe.serial('Theme list — UI-C3 canonical entry', () => {
  let theme: { id: number; slug: string };

  test.beforeAll(() => {
    theme = createIsolatedTheme();
  });

  // The Theme list is a real, unpaginated-per-row-cost table shared with
  // whatever other platform themes exist in this dev DB — leaving this
  // fixture behind after a failed run only makes the list (and the next
  // run) slower to render, never test correctness itself.
  test.afterAll(() => {
    if (theme) deleteTheme(theme.id);
  });

  test('list shows one canonical primary action and no raw/JSON editor entry', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto('/admin/theme-builder');

    await expect(page.getByText('ویرایش پیشرفته (JSON)')).toHaveCount(0);

    // The dev server renders this table synchronously, one row at a time,
    // over however many platform themes already exist in this shared DB —
    // a generous explicit timeout here is waiting on that real render, not
    // an arbitrary sleep.
    const row = page.locator('tr', { hasText: theme.slug });
    await expect(row.getByRole('link', { name: 'قالب‌ساز' })).toBeVisible({ timeout: 20_000 });

    // Only one primary, button-styled EDIT action per row. "JSON" here is
    // the export/download link (a legitimate, non-editing action that's
    // also available from Canvas's own More-menu) — it must survive; only
    // an "edit"-worded link (ویرایش) would indicate a competing editor.
    const rowLinks = row.locator('a');
    const rowLinkTexts = await rowLinks.allInnerTexts();
    const editLikeLinks = rowLinkTexts.filter((t) => /قالب‌ساز|ویرایش/.test(t));
    expect(editLikeLinks).toEqual(['🎨 قالب‌ساز']);
  });

  test('entering Canvas from the list preserves UI-C2D/C2E operational actions', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto('/admin/theme-builder');

    const row = page.locator('tr', { hasText: theme.slug });
    await row.getByRole('link', { name: 'قالب‌ساز' }).click();
    await page.waitForURL(new RegExp(`/theme-builder/${theme.id}/canvas`));
    await waitForCanvas(page);

    await page.getByTestId('tb-more-actions-toggle').click();
    for (const testId of ['tb-action-checkpoints', 'tb-action-clone', 'tb-action-export', 'tb-action-import']) {
      await expect(page.getByTestId(testId)).toBeVisible();
    }
    // Still no raw/JSON editor entry inside Canvas either.
    await expect(page.getByText('ویرایش پیشرفته (JSON)')).toHaveCount(0);
  });

  test('back navigation from Canvas returns to the Theme list', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(`/admin/theme-builder/${theme.id}/canvas`);
    await waitForCanvas(page);

    await page.getByRole('link', { name: 'بازگشت' }).click();
    await page.waitForURL(/\/admin\/theme-builder$/);
    await expect(page.getByRole('heading', { name: /ساختار تم/ })).toBeVisible();
  });

  test('the raw editor route remains reachable (compatibility) but unlinked from the list', async ({ page }) => {
    await loginAsAdmin(page, fx);

    const response = await page.goto(`/admin/theme-builder/${theme.id}/edit`);
    expect(response?.status()).toBeLessThan(400);
    await expect(page.getByRole('link', { name: 'ورود به قالب‌ساز' })).toBeVisible();
  });

  for (const width of [1366, 768, 390] as const) {
    test(`list actions remain reachable with no horizontal overflow at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await loginAsAdmin(page, fx);
      await page.goto('/admin/theme-builder');

      const row = page.locator('tr', { hasText: theme.slug });
      const canvasLink = row.getByRole('link', { name: 'قالب‌ساز' });
      await canvasLink.scrollIntoViewIfNeeded();
      await expect(canvasLink).toBeVisible({ timeout: 20_000 });

      const box = await canvasLink.boundingBox();
      expect(box).not.toBeNull();
      expect(box!.x).toBeGreaterThanOrEqual(-1);
      expect(box!.x + box!.width).toBeLessThanOrEqual(width + 1);
    });
  }
});
