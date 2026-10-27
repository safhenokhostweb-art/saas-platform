import { test, expect, type Page, type Locator } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.1 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md) — the
 * schema-driven Canvas repeater, end to end, through the real palette,
 * the real Inspector controls and the real save → reload → preview chain:
 *
 *   - Bento, FAQ and Testimonials each get THEIR OWN item fields (the old
 *     `propKey === 'items'` hijack gave all three the Bento card editor);
 *   - add / edit / reorder / duplicate / remove persist and render;
 *   - an unsafe card link never reaches an href;
 *   - columns.ratios shows exactly one control (the visual picker);
 *   - the preview renders correctly at desktop / tablet / mobile widths.
 *
 * Any console error, page error or failed same-origin request fails the run.
 */

const fx = loadFixtures();
const WIDTHS = [
  { name: 'desktop', width: 1366, bentoCols: 3, testimonialsPerView: '3' },
  { name: 'tablet', width: 768, bentoCols: 3, testimonialsPerView: '2' },
  { name: 'mobile', width: 390, bentoCols: 1, testimonialsPerView: '1' },
] as const;

type Block = { id?: string; type: string; props?: Record<string, any>; children?: Block[] };

function canvasState<T>(page: Page, fn: (state: any) => T): Promise<T> {
  return page.evaluate(`(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]');
    const state = root?._x_dataStack?.[0];
    return (${fn.toString()})(state);
  })()`) as Promise<T>;
}

async function waitForCanvasHydration(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as any;
    return root?._x_dataStack?.some((entry: any) => entry.activeTarget !== undefined && entry.showAddPage === false) ?? false;
  });
}

// Insertion triggers an autosave. A manual save fired while that autosave is
// still in flight replays the same insert operation (a known, pre-existing
// editor race that its own retry recovers from — see the Phase 0.1 report),
// so the flow waits for the real "autosave idle" condition first.
async function waitForAutosaveIdle(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as any;
    const state = root?._x_dataStack?.[0];
    return state && !state.quickSaving && !state.saving;
  });
}

async function insertFromPalette(page: Page, type: string, search: string, categoryLabel: string): Promise<string> {
  await page.locator('input[x-model="librarySearch"]').fill(search);
  const button = page.locator(`button[data-block-type="${type}"]`);
  if (!(await button.isVisible())) {
    await page.locator('button', { hasText: categoryLabel }).first().click();
  }
  await expect(button).toBeVisible();
  await button.click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  const selected = await canvasState(page, (s) => ({ type: s.selectedBlock?.type, id: s.selectedBlock?.id }));
  expect(selected.type, `palette click must insert and select ${type}`).toBe(type);
  await waitForAutosaveIdle(page);
  return selected.id as string;
}

function inspector(page: Page): Locator {
  return page.locator('div[x-show="selectedTab === \'content\'"]');
}

async function selectBlock(page: Page, id: string): Promise<void> {
  await page.evaluate((blockId) => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as any;
    const state = root?._x_dataStack?.[0];
    state.selectedTab = 'content';
    state.selectBlockById(blockId);
  }, id);
}

function findBlock(blocks: Block[], id: string): Block | undefined {
  for (const b of blocks) {
    if (b.id === id) return b;
    const hit = findBlock(b.children ?? [], id);
    if (hit) return hit;
  }
  return undefined;
}

async function blocksNow(page: Page): Promise<Block[]> {
  return JSON.parse(await canvasState(page, (s) => JSON.stringify(s.blocks ?? [])));
}

test('Phase 0.1 schema-driven repeater: author → save → reload → preview at three widths', async ({ page }) => {
  test.setTimeout(240_000);

  const problems: string[] = [];
  page.on('console', (msg) => { if (msg.type() === 'error') problems.push(`console: ${msg.text()}`); });
  page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`));
  const sameOrigin = (url: string) => new URL(url).host === new URL(page.url() || 'http://127.0.0.1:8000').host;
  page.on('requestfailed', (req) => {
    // A live-preview iframe navigation superseded by a newer reload is
    // cancelled by the browser (ERR_ABORTED) — not a failure. So are the
    // local font files that superseded document had started loading
    // (Phase 0.4: same-origin now, they used to come from Google Fonts).
    const superseded = req.failure()?.errorText === 'net::ERR_ABORTED'
      && (req.url().includes('/canvas/live-preview') || req.url().includes('/assets/fonts/vazirmatn/'));
    if (sameOrigin(req.url()) && !superseded) {
      problems.push(`requestfailed: ${req.method()} ${req.url()} ${req.failure()?.errorText}`);
    }
  });
  page.on('response', async (res) => {
    if (res.status() >= 400 && sameOrigin(res.url())) {
      const body = await res.text().catch(() => '');
      problems.push(`http ${res.status()}: ${res.request().method()} ${res.url()} ${body.slice(0, 400)} <= ${(res.request().postData() ?? '').slice(0, 300)}`);
    }
  });

  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));
  await waitForCanvasHydration(page);

  // ── Bento: its own card fields, full item lifecycle ────────────────────
  const bentoId = await insertFromPalette(page, 'bento_grid', 'bento', 'چیدمان');
  const panel = inspector(page);
  const bento = panel.getByTestId('tb-repeater-items');
  await expect(bento).toBeVisible();
  await expect(bento.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(3);
  await expect(panel.getByText('[object Object]')).toHaveCount(0);
  await expect(panel.locator('#rf-items-0-size option')).toHaveText(['استاندارد (۱×۱)', 'عریض (۲×۱)', 'بلند (۱×۲)', 'ویژه (۲×۲)']);

  await panel.getByTestId('tb-repeater-add-items').click();
  await expect(bento.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(4);
  await panel.locator('#rf-items-3-title').fill('کارت تست فاز ۰٫۱');
  await panel.locator('#rf-items-3-size').selectOption('featured');
  await panel.locator('#rf-items-3-link').fill('/shop');
  await panel.getByTestId('tb-repeater-up-items-3').click();          // → index 2
  await panel.getByTestId('tb-repeater-duplicate-items-0').click();   // 5 items
  await expect(bento.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(5);
  await panel.getByTestId('tb-repeater-remove-items-1').click();      // remove the duplicate
  await expect(bento.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(4);
  await panel.locator('#rf-items-0-link').fill('javascript:alert(1)');

  let bentoItems = findBlock(await blocksNow(page), bentoId)!.props!.items as any[];
  expect(bentoItems.map((i) => i.title)).toEqual(['مدیریت هوشمند', 'امنیت پیشرفته', 'کارت تست فاز ۰٫۱', 'سرعت بالا']);
  expect(bentoItems[2]).toMatchObject({ size: 'featured', link: '/shop' });
  expect(typeof bentoItems[2]._id).toBe('string');

  // ── Columns: exactly one ratios control, writing real values ───────────
  const columnsId = await insertFromPalette(page, 'columns', 'ستون', 'چیدمان');
  await expect(panel.getByTestId('tb-column-picker-ratios')).toBeVisible();
  await expect(panel.locator('select#ctrl-ratios')).toHaveCount(0);
  await expect(panel.getByTestId('tb-column-picker-ratios').getByRole('radio')).toHaveCount(4);
  await panel.getByTestId('tb-column-picker-ratios').getByRole('radio', { name: /۳ ستون مساوی/ }).click();
  expect(findBlock(await blocksNow(page), columnsId)!.props!.ratios).toBe('1/3,1/3,1/3');

  // ── FAQ: question/answer fields, never Bento's ─────────────────────────
  const faqId = await insertFromPalette(page, 'faq', 'faq', 'محتوای پویا');
  const faq = panel.getByTestId('tb-repeater-items');
  await expect(faq.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(0);
  await panel.getByTestId('tb-repeater-add-items').click();
  await panel.getByTestId('tb-repeater-add-items').click();
  await expect(faq.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(2);
  await expect(panel.getByText('اندازه کارت')).toHaveCount(0);
  await panel.locator('#rf-items-0-question').fill('ارسال سفارش چقدر طول می‌کشد؟');
  await panel.locator('#rf-items-0-answer').fill('سفارش‌ها در تهران ۲۴ ساعته و در شهرستان‌ها ۲ تا ۴ روز کاری ارسال می‌شوند.');
  // Newly added items open automatically, so the second item's fields are reachable.
  await expect(panel.locator('[data-testid="tb-repeater-item-items-1"] button[aria-expanded]')).toHaveAttribute('aria-expanded', 'true');
  await panel.locator('#rf-items-1-question').fill('امکان مرجوعی کالا وجود دارد؟');
  await panel.locator('#rf-items-1-answer').fill('بله، تا ۷ روز پس از تحویل.');

  // ── Testimonials: name/role/text/rating fields, legacy defaults load ───
  const testimonialsId = await insertFromPalette(page, 'testimonials_slider', 'testimonials', 'محتوای پویا');
  const t = panel.getByTestId('tb-repeater-items');
  await expect(t.locator('[data-testid^="tb-repeater-item-items-"]')).toHaveCount(3);
  await expect(panel.getByText('اندازه کارت')).toHaveCount(0);
  await expect(panel.locator('#rf-items-0-rating')).toHaveAttribute('max', '5');
  await panel.locator('#rf-items-0-name').fill('سارا محمدی');
  await panel.locator('#rf-items-0-rating').fill('4');

  // ── Save → reload → persisted ──────────────────────────────────────────
  await waitForAutosaveIdle(page);
  const savePromise = page.waitForResponse((r) => r.url().includes('/canvas/save') && r.request().method() === 'PUT'
    && (() => { try { return JSON.parse(r.request().postData() ?? '{}').target === 'page'; } catch { return false; } })());
  await canvasState(page, (s) => s.saveCurrentPage());
  expect((await savePromise).status()).toBe(200);

  await page.reload();
  await waitForCanvasHydration(page);
  const reloaded = await blocksNow(page);

  bentoItems = findBlock(reloaded, bentoId)!.props!.items as any[];
  expect(bentoItems).toHaveLength(4);
  expect(bentoItems[2]).toMatchObject({ title: 'کارت تست فاز ۰٫۱', size: 'featured', link: '/shop' });
  expect(findBlock(reloaded, columnsId)!.props!.ratios).toBe('1/3,1/3,1/3');
  const faqItems = findBlock(reloaded, faqId)!.props!.items as any[];
  expect(faqItems.map((i) => i.question)).toEqual(['ارسال سفارش چقدر طول می‌کشد؟', 'امکان مرجوعی کالا وجود دارد؟']);
  const tItems = findBlock(reloaded, testimonialsId)!.props!.items as any[];
  expect(tItems[0]).toMatchObject({ name: 'سارا محمدی', rating: 4 });

  // The reloaded Inspector shows the persisted values through the same controls.
  await selectBlock(page, bentoId);
  await page.locator('[data-testid="tb-repeater-item-items-2"] button[aria-expanded]').click();
  await expect(panel.locator('#rf-items-2-title')).toHaveValue('کارت تست فاز ۰٫۱');
  await expect(panel.locator('#rf-items-2-size')).toHaveValue('featured');

  // ── Preview at three widths ────────────────────────────────────────────
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  const bentoRoot = page.locator(`#tb-${bentoId}`);
  await expect(bentoRoot).toBeVisible();
  const featured = bentoRoot.locator('a.tb-bento-item.featured', { hasText: 'کارت تست فاز ۰٫۱' });
  await expect(featured).toHaveAttribute('href', '/shop');
  await expect(bentoRoot.locator('[href^="javascript" i]')).toHaveCount(0);
  await expect(bentoRoot.locator('a a')).toHaveCount(0);
  expect(await bentoRoot.locator('.tb-bento-item').first().evaluate((el) => el.tagName)).toBe('DIV'); // unsafe link → plain card
  expect(await bentoRoot.evaluate((el) => el.innerHTML.includes('linear-gradient'))).toBe(false);

  await expect(page.locator(`#tb-${faqId} details.tb-faq-item`)).toHaveCount(2);
  await expect(page.locator(`#tb-${faqId} summary`).first()).toHaveText('ارسال سفارش چقدر طول می‌کشد؟');
  const tRoot = page.locator(`#tb-${testimonialsId}`);
  await expect(tRoot.getByText('سارا محمدی')).toBeVisible();
  await expect(tRoot.locator('.tb-testimonial-stars').first()).toHaveText('★★★★☆');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    await bentoRoot.scrollIntoViewIfNeeded();
    const cols = await bentoRoot.locator('.tb-bento-grid').evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
    expect(cols, `bento columns at ${w.name}`).toBe(w.bentoCols);
    const perView = await tRoot.locator('.tb-carousel').evaluate((el) => getComputedStyle(el).getPropertyValue('--tb-items').trim());
    expect(perView, `testimonials per view at ${w.name}`).toBe(w.testimonialsPerView);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(1);
    for (const [label, root] of [['bento', bentoRoot], ['faq', page.locator(`#tb-${faqId}`)], ['testimonials', tRoot]] as const) {
      await root.screenshot({ path: `test-results/phase01-${label}-${w.name}.png` });
    }
  }

  expect(problems, problems.join('\n')).toEqual([]);

  // ── Leave the shared E2E fixture page as it was found ──────────────────
  // Removed through the editor's own delete operation, then saved and
  // re-verified after a reload.
  await page.goto(canvasUrl(fx));
  await waitForCanvasHydration(page);
  for (const id of [bentoId, columnsId, faqId, testimonialsId]) {
    await selectBlock(page, id);
    await canvasState(page, (s) => s.removeSelected());
    await waitForAutosaveIdle(page);
  }
  const cleanupSave = page.waitForResponse((r) => r.url().includes('/canvas/save') && r.request().method() === 'PUT');
  await canvasState(page, (s) => s.saveCurrentPage());
  expect((await cleanupSave).status()).toBe(200);
  await page.reload();
  await waitForCanvasHydration(page);
  const after = await blocksNow(page);
  for (const id of [bentoId, columnsId, faqId, testimonialsId]) {
    expect(findBlock(after, id), `test block ${id} must be cleaned up`).toBeUndefined();
  }
});
