import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 0.2b (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-061) —
 * nested lists are first-class in the platform Canvas:
 *
 *   - pricing_cards.plans[].features (depth 1, compact rows; legacy scalar
 *     features adopt the {text} shape on first edit), plus the
 *     plans[].highlight boolean switch;
 *   - nav_footer.columns[].links (depth 1, collapsible cards, link input
 *     with the unsafe-URL warning);
 *   - nav_header.nav_links[].mega_columns[].items (depth 2).
 *
 * Add / move / duplicate / remove inside a nested list never disturb the
 * parent's other items, each structural edit is its own undo step, and
 * everything survives save → reload → preview at three widths.
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

async function select(page: Page, id: string): Promise<void> {
  await page.evaluate((blockId) => {
    (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0].selectBlockById(blockId);
  }, id);
}

function find(blocks: any[], id: string): any {
  for (const b of blocks) {
    if (b.id === id) return b;
    const hit = find(b.children ?? [], id);
    if (hit) return hit;
  }
  return undefined;
}

const list = (v: any) => (typeof v === 'string' ? JSON.parse(v) : v);

test('Phase 0.2b nested lists edit, undo, save, reload and render at every width', async ({ page }) => {
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

  // ── Pricing: nested features in plan 2 + the highlight switch ─────────
  const pricingId = await insert(page, 'pricing_cards', 'Pricing');
  await expect(panel.getByTestId('tb-repeater-item-plans-1')).toBeVisible();
  await panel.getByTestId('tb-repeater-toggle-plans-1').click();
  const features = panel.getByTestId('tb-repeater-plans-1-features');
  await expect(features).toBeVisible();
  const before = await features.locator('[data-testid^="tb-repeater-item-plans-1-features-"]').count();
  expect(before, 'plan 2 starts with its default features').toBeGreaterThan(0);
  const plan1Features = await panel.getByTestId('tb-repeater-plans-0-features').locator('[data-testid^="tb-repeater-item-plans-0-features-"]').count();

  await panel.getByTestId('tb-repeater-add-plans-1-features').click();
  await panel.locator(`#rf-plans-1-features-${before}-text`).fill('پشتیبانی تلفنی ویژه');
  await panel.getByTestId(`tb-repeater-up-plans-1-features-${before}`).click();
  await expect(panel.locator(`#rf-plans-1-features-${before - 1}-text`)).toHaveValue('پشتیبانی تلفنی ویژه');

  // The move is one undo step of its own — undo puts the feature back
  // (with its text), redo moves it again.
  await state(page, (s) => s.undo());
  await expect(panel.locator(`#rf-plans-1-features-${before}-text`)).toHaveValue('پشتیبانی تلفنی ویژه');
  await state(page, (s) => s.redo());
  await expect(panel.locator(`#rf-plans-1-features-${before - 1}-text`)).toHaveValue('پشتیبانی تلفنی ویژه');

  await panel.getByTestId('tb-repeater-duplicate-plans-1-features-0').click();
  await expect(features.locator('[data-testid^="tb-repeater-item-plans-1-features-"]')).toHaveCount(before + 2);
  await panel.getByTestId('tb-repeater-remove-plans-1-features-1').click();
  await expect(features.locator('[data-testid^="tb-repeater-item-plans-1-features-"]')).toHaveCount(before + 1);
  await state(page, (s) => s.undo());
  await expect(features.locator('[data-testid^="tb-repeater-item-plans-1-features-"]')).toHaveCount(before + 2);
  await state(page, (s) => s.redo());
  await expect(features.locator('[data-testid^="tb-repeater-item-plans-1-features-"]')).toHaveCount(before + 1);

  // Sibling plan untouched by every nested edit above.
  await expect(panel.getByTestId('tb-repeater-plans-0-features').locator('[data-testid^="tb-repeater-item-plans-0-features-"]')).toHaveCount(plan1Features);

  const highlight0 = panel.locator('#rf-plans-0-highlight');
  const highlight1 = panel.locator('#rf-plans-1-highlight');
  await expect(highlight0).toHaveAttribute('role', 'switch');
  await expect(highlight0).not.toBeChecked();
  await expect(highlight1).toBeChecked();
  // Toggled the way an author does: by clicking the visible switch.
  await highlight0.locator('xpath=ancestor::label[1]').click();
  await expect(highlight0).toBeChecked();
  await expect(highlight0).toHaveAttribute('aria-checked', 'true');
  await highlight1.locator('xpath=ancestor::label[1]').click();
  await expect(highlight1).not.toBeChecked();

  // ── Footer: column links, collapse, unsafe-URL warning ────────────────
  const footerId = await insert(page, 'nav_footer', 'Nav Footer');
  await panel.getByTestId('tb-repeater-add-columns').click();
  await panel.locator('#rf-columns-0-title').fill('فروشگاه');
  await panel.getByTestId('tb-repeater-add-columns-0-links').click();
  await panel.locator('#rf-columns-0-links-0-label').fill('محصولات');
  await panel.locator('#rf-columns-0-links-0-url').fill('/products');
  await panel.getByTestId('tb-repeater-add-columns-0-links').click();
  await panel.locator('#rf-columns-0-links-1-label').fill('سوالات متداول');
  const unsafe = panel.locator('#rf-columns-0-links-1-url');
  await unsafe.fill('javascript:alert(1)');
  await expect(unsafe).toHaveAttribute('aria-invalid', 'true');
  await expect(panel.getByTestId('tb-repeater-item-columns-0-links-1').getByRole('alert')).toBeVisible();
  await unsafe.fill('/faq');
  await expect(unsafe).toHaveAttribute('aria-invalid', 'false');

  const colToggle = panel.getByTestId('tb-repeater-toggle-columns-0');
  await expect(colToggle).toHaveAttribute('aria-expanded', 'true');
  await colToggle.click();
  await expect(colToggle).toHaveAttribute('aria-expanded', 'false');
  await expect(panel.getByTestId('tb-repeater-columns-0-links')).toBeHidden();
  await colToggle.click();
  await expect(panel.locator('#rf-columns-0-links-1-url')).toHaveValue('/faq');

  // ── Header: mega menu, two nested levels ──────────────────────────────
  const headerId = await insert(page, 'nav_header', 'Nav Header');
  await panel.getByTestId('tb-repeater-add-nav_links').click();
  await panel.locator('#rf-nav_links-0-label').fill('دسته‌بندی‌ها');
  await panel.locator('#rf-nav_links-0-url').fill('/shop');
  await panel.getByTestId('tb-repeater-add-nav_links-0-mega_columns').click();
  await panel.locator('#rf-nav_links-0-mega_columns-0-title').fill('پوشاک');
  await panel.getByTestId('tb-repeater-add-nav_links-0-mega_columns-0-items').click();
  await panel.locator('#rf-nav_links-0-mega_columns-0-items-0-label').fill('پیراهن');
  await panel.locator('#rf-nav_links-0-mega_columns-0-items-0-url').fill('/shirts');

  // ── Save → reload → stored shapes ─────────────────────────────────────
  await idle(page);
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
  await page.reload();
  await hydrate(page);
  const blocks = JSON.parse(await state(page, (s) => JSON.stringify(s.blocks)));

  const plans = list(find(blocks, pricingId).props.plans);
  expect(Array.isArray(find(blocks, pricingId).props.plans), 'plans persist as a real array').toBe(true);
  expect(plans[0].highlight).toBe(true);
  expect(plans[1].highlight).toBe(false);
  expect(plans[1].features).toHaveLength(before + 1);
  expect(plans[1].features.every((f: any) => typeof f === 'object' && typeof f.text === 'string'), 'edited features adopt {text}').toBe(true);
  expect(plans[1].features[before - 1].text).toBe('پشتیبانی تلفنی ویژه');
  expect(list(plans[0].features)).toHaveLength(plan1Features);

  const footer = find(blocks, footerId).props;
  expect(footer.columns[0].title).toBe('فروشگاه');
  expect(footer.columns[0].links.map((l: any) => [l.label, l.url])).toEqual([['محصولات', '/products'], ['سوالات متداول', '/faq']]);

  const nav = find(blocks, headerId).props.nav_links[0];
  expect(nav.mega_columns[0].title).toBe('پوشاک');
  expect(nav.mega_columns[0].items[0]).toMatchObject({ label: 'پیراهن', url: '/shirts' });

  // Reloaded editor shows the nested values again.
  await select(page, pricingId);
  await panel.getByTestId('tb-repeater-toggle-plans-1').click();
  await expect(panel.locator(`#rf-plans-1-features-${before - 1}-text`)).toHaveValue('پشتیبانی تلفنی ویژه');

  // ── Preview at three widths ───────────────────────────────────────────
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  const pricing = page.locator(`#tb-${pricingId}`);
  const footerRoot = page.locator(`#tb-${footerId}`);
  const headerRoot = page.locator(`#tb-${headerId}`);
  await expect(pricing.locator('.tb-pricing-card')).toHaveCount(plans.length);
  await expect(pricing.locator('.tb-pricing-card').nth(0)).toHaveClass(/highlighted/);
  await expect(pricing.locator('.tb-pricing-card').nth(1)).not.toHaveClass(/highlighted/);
  await expect(pricing.locator('.tb-pricing-card').nth(1).getByText('پشتیبانی تلفنی ویژه')).toBeVisible();
  await expect(footerRoot.locator('a[href="/products"]')).toHaveText('محصولات');
  await expect(footerRoot.locator('a[href="/faq"]')).toHaveText('سوالات متداول');
  await expect(headerRoot.locator('a[href="/shirts"]')).toHaveCount(1);
  expect(await headerRoot.textContent()).toContain('پوشاک');
  expect(await page.content()).not.toContain('javascript:alert');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    for (const [label, root] of [['pricing', pricing], ['footer', footerRoot], ['header', headerRoot]] as const) {
      await root.scrollIntoViewIfNeeded();
      await expect(root).toBeVisible();
      await root.screenshot({ path: `test-results/phase02b-${label}-${w.name}.png` });
    }
    await expect(pricing.locator('.tb-pricing-card').nth(1).getByText('پشتیبانی تلفنی ویژه')).toBeVisible();
    await expect(footerRoot.locator('a[href="/products"]')).toBeVisible();
  }

  // ── Leave the shared fixture page as found ─────────────────────────────
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  for (const id of [pricingId, footerId, headerId]) {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      s.removeSelected();
    }, id);
    await idle(page);
  }
  expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);

  expect(problems, problems.join('\n')).toEqual([]);
});
