import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.2c (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-062) —
 * the controls the harmonized canonical definitions give the platform
 * Canvas, which customer-site now authors with too:
 *
 *   - section.padding is a spacing-scale select (was `spacing`: no
 *     control at all) and the structural `blocks` field has no row;
 *   - heading.level is an H1–H6 select;
 *   - video gains poster/autoplay/loop/controls, and its URL is a media
 *     field that never previews as an <img>;
 *   - cta.background is a colour control the renderer actually applies;
 *   - social_icons.links is a real list and `style` picks the variant.
 *
 * Everything survives save → reload → preview at three widths.
 */

const fx = loadFixtures();
const WIDTHS = [
  { name: 'desktop', width: 1366 },
  { name: 'tablet', width: 768 },
  { name: 'mobile', width: 390 },
] as const;

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

async function insert(page: Page, type: string, search: string): Promise<string> {
  await page.locator('input[x-model="librarySearch"]').fill(search);
  const button = page.locator(`button[data-block-type="${type}"]`);
  if (!(await button.isVisible())) {
    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      for (const k of Object.keys(s.expandedCats)) s.expandedCats[k] = true;
    });
  }
  await button.click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  await idle(page);
  return state(page, (s) => s.selectedBlock.id as string);
}

function find(blocks: any[], id: string): any {
  for (const b of blocks) {
    if (b.id === id) return b;
    const hit = find(b.children ?? [], id);
    if (hit) return hit;
  }
  return undefined;
}

test('Phase 0.2c harmonized controls author, save, reload and render at every width', async ({ page }) => {
  test.setTimeout(240_000);
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('response', async (r) => {
    if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()} ${(await r.text().catch(() => '')).slice(0, 300)}`);
  });

  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  const panel = page.locator('div[x-show="selectedTab === \'content\'"]');

  // ── Heading: H1–H6 select ─────────────────────────────────────────────
  const headingId = await insert(page, 'heading', 'عنوان');
  const level = panel.locator('#ctrl-level');
  await expect(level.locator('option')).toHaveCount(6);
  await expect(level).toHaveValue('2');
  await level.selectOption('1');
  await panel.locator('#ctrl-text').fill('عنوان اصلی فروشگاه');

  // ── Video: promoted fields, media URL without an <img> preview ────────
  const videoId = await insert(page, 'video', 'ویدیو');
  for (const key of ['url', 'poster', 'caption', 'autoplay', 'loop', 'controls']) {
    await expect(panel.locator(`label[for="ctrl-${key}"]`), key).toBeVisible();
  }
  const videoUrl = panel.locator('#ctrl-url');
  await expect(videoUrl).toHaveAttribute('placeholder', 'آدرس URL ویدیو');
  await videoUrl.fill('/storage/theme-media/1/hero-intro.webm');
  await expect(videoUrl.locator('xpath=ancestor::div[2]').locator('img')).toBeHidden();
  await panel.locator('#ctrl-loop').locator('xpath=ancestor::label[1]').click();

  // ── CTA: colour control the renderer applies ──────────────────────────
  const ctaId = await insert(page, 'cta', 'CTA');
  await expect(panel.locator('#ctrl-background')).toHaveAttribute('type', 'color');
  await panel.locator('#ctrl-background').locator('xpath=following-sibling::input[1]').fill('#0ea5e9');
  await panel.locator('#ctrl-title').fill('همین امروز شروع کنید');

  // ── Social icons: real link list + style ──────────────────────────────
  const socialId = await insert(page, 'social_icons', 'شبکه');
  await panel.getByTestId('tb-repeater-add-links').click();
  await panel.locator('#rf-links-0-label').fill('اینستاگرام');
  await panel.locator('#rf-links-0-url').fill('https://instagram.com/acme');
  await panel.locator('#ctrl-style').selectOption('buttons');

  // ── Section: spacing select, container switch, no `blocks` row ────────
  const sectionId = await insert(page, 'section', 'Section');
  const padding = panel.locator('#ctrl-padding');
  await expect(padding).toBeVisible();
  await expect(padding.locator('option')).toHaveText(['بدون فاصله', 'خیلی کم', 'کم', 'متوسط', 'زیاد', 'خیلی زیاد']);
  await expect(padding).toHaveValue('md');
  await padding.selectOption('xl');
  await expect(panel.locator('label[for="ctrl-blocks"]')).toHaveCount(0);
  await expect(panel.locator('label[for="ctrl-container"]')).toHaveText('محتوا در عرض استاندارد');

  // ── Save → reload → stored shapes ─────────────────────────────────────
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.reload();
  await hydrate(page);
  const blocks = JSON.parse(await state(page, (s) => JSON.stringify(s.blocks)));

  expect(find(blocks, sectionId).props.padding).toBe('xl');
  expect(String(find(blocks, headingId).props.level)).toBe('1');
  expect(find(blocks, videoId).props).toMatchObject({ url: '/storage/theme-media/1/hero-intro.webm', loop: true });
  expect(find(blocks, ctaId).props.background).toBe('#0ea5e9');
  expect(find(blocks, socialId).props).toMatchObject({ style: 'buttons', links: [{ label: 'اینستاگرام', url: 'https://instagram.com/acme' }] });

  // ── Preview at three widths ───────────────────────────────────────────
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  const section = page.locator(`#tb-${sectionId}`);
  const heading = page.locator(`h1#tb-${headingId}`);
  const video = page.locator(`#tb-${videoId} video`);
  const cta = page.locator(`#tb-${ctaId}`);
  const social = page.locator(`#tb-${socialId}`);
  await expect(section).toHaveClass(/tb-padding-xl/);
  await expect(heading).toHaveText('عنوان اصلی فروشگاه');
  await expect(video).toHaveAttribute('src', '/storage/theme-media/1/hero-intro.webm');
  await expect(video).toHaveAttribute('loop', '');
  await expect(cta).toHaveClass(/tb-bg-custom/);
  await expect(cta).toHaveCSS('background-color', 'rgb(14, 165, 233)');
  await expect(social.locator('a.tb-btn.tb-btn-secondary[href="https://instagram.com/acme"]')).toHaveText('اینستاگرام');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    for (const [label, root] of [['heading', heading], ['cta', cta], ['social', social]] as const) {
      await root.scrollIntoViewIfNeeded();
      await expect(root).toBeVisible();
      await root.screenshot({ path: `test-results/phase02c-${label}-${w.name}.png` });
    }
    const box = await cta.boundingBox();
    expect(box && box.width <= w.width, `CTA fits the ${w.name} viewport`).toBe(true);
  }

  // ── Leave the shared fixture page as found ─────────────────────────────
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  for (const id of [sectionId, headingId, videoId, ctaId, socialId]) {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
  }
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
