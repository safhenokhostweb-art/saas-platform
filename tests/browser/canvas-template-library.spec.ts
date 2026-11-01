import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Canvas overhaul Unit 5 (ADR-075): one-click template insertion in the
 * real platform editor. A header template lands in the Header zone (not
 * the page body) and replaces it after confirmation; a section template
 * lands on the page; both show up in the canvas iframe immediately, with
 * the library's images actually loaded. The shared E2E fixture theme's
 * zones are restored afterwards.
 */
const fx = loadFixtures();
const php = process.env.PHP_BINARY || 'php';

function themeZones(themeId: number, restore?: string): string {
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::findOrFail(${themeId});
$restore = ${restore ? `json_decode(base64_decode('${Buffer.from(restore).toString('base64')}'), true)` : 'null'};
if ($restore) { $theme->forceFill($restore)->save(); }
echo json_encode(['pages' => $theme->pages, 'header_blocks' => $theme->header_blocks, 'footer_blocks' => $theme->footer_blocks], JSON_UNESCAPED_UNICODE);
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8' }).trim();
}

async function insertFromLibrary(page: Page, id: string): Promise<void> {
  await page.getByTestId('tb-open-templates').click();
  const card = page.getByTestId(`tpl-card-${id}`);
  await expect(card).toBeVisible({ timeout: 15_000 });
  await card.click();
  await expect(page.getByText('قالب درج شد')).toBeVisible({ timeout: 30_000 });
}

async function libraryImagesLoaded(page: Page): Promise<number> {
  const frame = page.frameLocator('#tb-live-iframe');
  await frame.locator('body').waitFor();
  return frame.locator('body').evaluate(async () => {
    const imgs = [...document.querySelectorAll('img')].filter(i => i.src.includes('/theme-runtime/library/'));
    await Promise.all(imgs.map(i => i.complete ? null : new Promise(r => { i.onload = i.onerror = r; })));
    return imgs.filter(i => i.naturalWidth > 0).length;
  });
}

test.describe('Canvas template library (Unit 5)', () => {
  let original = '';

  test.beforeAll(() => { original = themeZones(fx.theme_id); });
  test.afterAll(() => { if (original) themeZones(fx.theme_id, original); });

  test('a header template replaces the Header zone and a hero lands on the page, both rendered at once', async ({ page }) => {
    test.setTimeout(120_000);
    page.on('dialog', dialog => dialog.accept());
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await page.waitForLoadState('networkidle');

    await insertFromLibrary(page, 'lib:header-mega-store');
    const frame = page.frameLocator('#tb-live-iframe');
    await expect(frame.getByText('جشنواره فناوری؛ تا ۳۰٪ تخفیف').first()).toBeAttached({ timeout: 30_000 });
    await expect(frame.locator('a[href="/shop"]').first()).toBeAttached();
    const header = JSON.parse(themeZones(fx.theme_id));
    expect(header.header_blocks.map((b: { type: string }) => b.type)).toEqual(['nav_header']);
    expect(JSON.stringify(header.header_blocks)).toContain('/theme-runtime/library/tech-products.webp');

    await insertFromLibrary(page, 'lib:hero-split-office');
    await expect(frame.getByText('کسب‌وکار خود را با اطمینان آنلاین کنید')).toBeVisible({ timeout: 30_000 });
    expect(await libraryImagesLoaded(page)).toBeGreaterThan(0);
    const after = JSON.parse(themeZones(fx.theme_id));
    expect(JSON.stringify(after.pages)).toContain('hero_split');
    expect(after.header_blocks.map((b: { type: string }) => b.type)).toEqual(['nav_header']);
  });
});
