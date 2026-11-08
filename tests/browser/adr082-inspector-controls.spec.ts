import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * ADR-082 in the platform Canvas Inspector, on a real nav_header:
 *
 *   - Content / Style / Advanced each list the widget's own fields
 *     (`panel`), and a `condition` shows a field only while it has an effect;
 *   - the header hides the generic wrapper sections of the Style tab, and a
 *     block that still holds such values can clear them;
 *   - the shared controls: range, per-device values with the device switch,
 *     Normal / Hover / Active, spacing, typography and shadow. Their test
 *     fields are added to the client-side schema only (never saved).
 *
 * The header block is removed again, so the shared fixture page is left as found.
 */

const fx = loadFixtures();

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

function watchProblems(page: Page): string[] {
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('response', (r) => { if (r.status() >= 400) problems.push(`http ${r.status()}: ${r.request().method()} ${r.url()}`); });
  return problems;
}

const tab = (page: Page, label: string) => page.locator('button', { hasText: new RegExp(`^${label}$`) }).first();

test('ADR-082 header Inspector: tabs, conditions, hidden generic style and the shared controls', async ({ page }) => {
  test.setTimeout(240_000);
  const problems = watchProblems(page);
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
  const id = await state(page, (s) => s.selectedBlock.id as string);

  try {
    // ── Content: own fields only, conditions ───────────────────────────
    const content = page.getByTestId('tb-panel-fields-content');
    await expect(content.getByTestId('tb-field-logo_image')).toBeVisible();
    for (const moved of ['bg_color', 'text_color', 'sticky', 'cta_glow_color']) {
      await expect(content.getByTestId(`tb-field-${moved}`), `${moved} is not a content field`).toHaveCount(0);
    }
    await expect(content.getByTestId('tb-field-search_mode')).toHaveCount(0);
    await content.getByTestId('tb-field-show_search').locator('label').last().click();
    expect(await state(page, (s) => s.selectedBlock.props.show_search)).toBe(true);
    await expect(content.getByTestId('tb-field-search_mode')).toBeVisible();
    await expect(content.getByTestId('tb-field-cta_url')).toHaveCount(0);
    await content.getByTestId('tb-field-cta_text').locator('input').fill('مشاوره');
    await expect(content.getByTestId('tb-field-cta_url')).toBeVisible();

    // logo height is a range: slider + exact number
    const range = content.getByTestId('tbc-range-logo_height');
    await expect(range.locator('input[type=range]')).toBeVisible();
    await range.getByTestId('tbc-range-value-logo_height').fill('52');
    await range.getByTestId('tbc-range-value-logo_height').press('Tab');
    expect(await state(page, (s) => s.selectedBlock.props.logo_height)).toBe(52);
    await page.getByTestId('tb-inspector-scroll').screenshot({ path: 'test-results/adr082-content.png' });

    // ── Style: header colours, generic sections hidden ─────────────────
    await tab(page, 'استایل').click();
    const style = page.getByTestId('tb-panel-fields-style');
    await expect(style.getByTestId('tb-field-group-bg_color')).toHaveText('رنگ‌های هدر');
    await expect(style.getByTestId('tb-field-group-cta_glow_enabled')).toHaveText('دکمهٔ CTA و تماس');
    await expect(style.getByTestId('tb-field-logo_image')).toHaveCount(0);
    for (const section of ['تایپوگرافی', 'رنگ‌ها', 'فاصله‌گذاری', 'حاشیه', 'سایه']) {
      await expect(page.locator('div[x-show="selectedTab === \'style\'"] button', { hasText: new RegExp(`^\\s*${section}\\s*$`) }), `generic ${section} hidden`).toBeHidden();
    }
    await expect(page.getByTestId('tb-style-breakpoints'), 'the wrapper device bar goes with the wrapper sections').toBeHidden();
    await expect(page.getByTestId('tb-hidden-generic-style')).toHaveCount(0);
    // A block saved with wrapper values before the sections were hidden.
    await page.evaluate(() => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectedBlock.style = { bgColor: '#000000', customClass: 'keep-me' };
    });
    await expect(page.getByTestId('tb-hidden-generic-style')).toBeVisible();
    await page.getByTestId('tb-clear-hidden-generic-style').click();
    await expect(page.getByTestId('tb-hidden-generic-style')).toHaveCount(0);
    expect(await state(page, (s) => s.selectedBlock.style)).toEqual({ customClass: 'keep-me' });
    await idle(page);
    await page.getByTestId('tb-inspector-scroll').screenshot({ path: 'test-results/adr082-style.png' });

    // ── Advanced: behaviour, condition on sticky ───────────────────────
    await tab(page, 'پیشرفته').click();
    const advanced = page.getByTestId('tb-panel-fields-advanced');
    await expect(advanced.getByTestId('tb-field-group-sticky')).toHaveText('هدر چسبان و شفاف');
    await expect(advanced.getByTestId('tb-field-sticky_hide_on_scroll')).toHaveCount(0);
    await advanced.getByTestId('tb-field-sticky').locator('label').last().click();
    await expect(advanced.getByTestId('tb-field-sticky_hide_on_scroll')).toBeVisible();
    await expect(page.locator('input[placeholder="مثال: my-hero featured-section"]'), 'generic Advanced controls stay').toBeVisible();

    // ── Shared controls on test-only schema fields ─────────────────────
    await page.evaluate(() => {
      const schema = (window as any).eval('BLOCK_SCHEMAS').nav_header.schema;
      Object.assign(schema, {
        t_pad: { type: 'spacing', label: 'فاصلهٔ داخلی (تست)', panel: 'style', group: 'تست', responsive: true },
        t_typo: { type: 'typography', label: 'تایپوگرافی منو (تست)', panel: 'style', group: 'تست', responsive: true },
        t_color: { type: 'color', label: 'رنگ منو', default: '', panel: 'style', group: 'تست', state_group: 't_menu', state: 'normal' },
        t_color_hover: { type: 'color', label: 'رنگ منو', default: '', panel: 'style', group: 'تست', state_group: 't_menu', state: 'hover' },
        t_shadow: { type: 'shadow', label: 'سایه (تست)', panel: 'style', group: 'تست' },
      });
    });
    await tab(page, 'استایل').click();
    await expect(style.getByTestId('tb-field-group-t_pad')).toHaveText('تست');

    // spacing: linked by default, per device through the switch
    await style.getByTestId('tbc-spacing-t_pad-block-start').fill('12');
    await style.getByTestId('tbc-spacing-t_pad-block-start').press('Tab');
    expect(await state(page, (s) => s.selectedBlock.props.t_pad)).toMatchObject({ 'block-start': 12, 'inline-start': 12, 'block-end': 12, 'inline-end': 12, linked: true });
    await style.getByTestId('tbc-device-t_pad-mobile').click();
    expect(await state(page, (s) => [s.activeBreakpoint, s.device])).toEqual(['mobile', 'mobile']);
    await expect(style.getByTestId('tbc-inherit-t_pad')).toContainText('از دستگاه بزرگ‌تر');
    await style.getByTestId('tbc-spacing-t_pad-block-start').fill('6');
    await style.getByTestId('tbc-spacing-t_pad-block-start').press('Tab');
    expect(await state(page, (s) => [s.selectedBlock.props.t_pad['block-start'], s.selectedBlock.props.t_pad_mobile['block-start']])).toEqual([12, 6]);
    await expect(style.getByTestId('tbc-device-t_pad-mobile').locator('.tbc-dev-dot')).toBeVisible();
    await style.getByTestId('tbc-reset-t_pad').click();
    expect(await state(page, (s) => 't_pad_mobile' in s.selectedBlock.props)).toBe(false);

    // typography: a phone override stores only what changed
    await style.getByTestId('tbc-typography-t_typo').locator('button').first().click();
    await style.getByTestId('tbc-typography-t_typo-size').fill('13');
    await style.getByTestId('tbc-typography-t_typo-size').press('Tab');
    expect(await state(page, (s) => s.selectedBlock.props.t_typo_mobile)).toEqual({ size: 13 });
    await style.getByTestId('tbc-device-t_typo-desktop').click();
    await style.getByTestId('tbc-typography-t_typo-weight').selectOption('700');
    expect(await state(page, (s) => s.selectedBlock.props.t_typo)).toEqual({ weight: '700' });

    // Normal / Hover / Active
    await expect(style.getByTestId('tbc-states-t_menu')).toBeVisible();
    await expect(style.getByTestId('tb-field-t_color')).toBeVisible();
    await expect(style.getByTestId('tb-field-t_color_hover')).toHaveCount(0);
    await style.getByTestId('tbc-state-t_menu-hover').click();
    await expect(style.getByTestId('tb-field-t_color_hover')).toBeVisible();
    await expect(style.getByTestId('tb-field-t_color')).toHaveCount(0);
    await expect(style.getByTestId('tbc-state-t_menu-hover')).toHaveAttribute('aria-selected', 'true');

    // shadow presets
    await style.getByTestId('tbc-shadow-t_shadow-md').click();
    expect(await state(page, (s) => s.selectedBlock.props.t_shadow)).toMatchObject({ y: 4, blur: 12, spread: -2 });
    await expect(style.getByTestId('tbc-shadow-t_shadow-md')).toHaveAttribute('aria-pressed', 'true');
    await style.getByTestId('tbc-shadow-t_shadow-none').click();
    expect(await state(page, (s) => s.selectedBlock.props.t_shadow)).toBe('');
    await page.getByTestId('tb-inspector-scroll').screenshot({ path: 'test-results/adr082-controls.png' });

    // the live preview survived every edit
    const frame = page.frameLocator('#tb-live-iframe');
    await expect(frame.locator(`[data-tb-block-id="${id}"][data-widget-type="nav_header"]`)).toHaveCount(1);
  } finally {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.setDevice('desktop');
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
    expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
    await idle(page);
  }

  expect(problems, problems.join('\n')).toEqual([]);
});

test('ADR-082 step 3: the header Style tab drives the live preview through its own --tb-hdr-* values', async ({ page }) => {
  test.setTimeout(240_000);
  const problems = watchProblems(page);
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
  const id = await state(page, (s) => s.selectedBlock.id as string);

  try {
    await tab(page, 'استایل').click();
    const style = page.getByTestId('tb-panel-fields-style');
    await expect(style.getByTestId('tb-field-group-min_height')).toHaveText('کانتینر هدر');
    await expect(style.getByTestId('tb-field-group-menu_typography')).toHaveText('منوی ناوبری');
    await expect(style.getByTestId('tbc-states-menu_text')).toBeVisible();
    await expect(style.getByTestId('tb-field-content_max_width')).toBeVisible();
    await style.getByTestId('tb-segmented-content_width-full').click();
    await expect(style.getByTestId('tb-field-content_max_width'), 'max width only for a boxed header').toHaveCount(0);
    await expect(style.getByTestId('tb-field-pointer_animation')).toBeVisible();
    await style.getByTestId('tb-field-pointer').locator('select').selectOption('framed');
    await expect(style.getByTestId('tb-field-pointer_animation'), 'animation only for line pointers').toHaveCount(0);

    // Height through the range control → the preview header re-renders with its own style.
    await style.getByTestId('tbc-range-value-min_height').fill('92');
    await style.getByTestId('tbc-range-value-min_height').press('Tab');
    expect(await state(page, (s) => s.selectedBlock.props.min_height)).toBe(92);
    const frame = page.frameLocator('#tb-live-iframe');
    await expect.poll(() => frame.locator(`#tb-${id} .tb-nav-header-inner`).evaluate((el) => getComputedStyle(el).minHeight), { timeout: 20_000 }).toBe('92px');
    await expect(frame.locator(`#tb-${id}`)).toHaveClass(/tb-nav-header--pointer-framed/);
    await expect(frame.locator(`style[data-tb-hdr="tb-${id}"]`)).toHaveCount(1);

    // A menu hover colour: the Hover tab, then the colour value.
    await style.getByTestId('tbc-state-menu_text-hover').click();
    await style.getByTestId('tb-color-value-menu_color_hover').fill('#2563eb');
    await expect.poll(() => state(page, (s) => s.selectedBlock.props.menu_color_hover)).toBe('#2563eb');
    await expect.poll(() => frame.locator(`#tb-${id}`).getAttribute('data-tb-hdr-set'), { timeout: 20_000 }).toContain('menu-color-hover');
    await expect(frame.locator(`style[data-tb-hdr="tb-${id}"]`), 'still one style for the header after another swap').toHaveCount(1);
    await page.getByTestId('tb-inspector-scroll').screenshot({ path: 'test-results/adr082-step3-style-tab.png' });
  } finally {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
    expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
    await idle(page);
  }
  expect(problems, problems.join('\n')).toEqual([]);
});

test('ADR-082 step 4: the header Advanced tab shows sticky and drawer options only while they apply', async ({ page }) => {
  test.setTimeout(240_000);
  const problems = watchProblems(page);
  await loginAsAdmin(page, fx);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(canvasUrl(fx));
  await hydrate(page);
  await page.locator('input[x-model="librarySearch"]').fill('هدر سایت');
  await page.evaluate(() => {
    const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
    for (const k of Object.keys(s.expandedCats)) s.expandedCats[k] = true;
  });
  await page.locator('button[data-block-type="nav_header"]').click();
  await page.locator('input[x-model="librarySearch"]').fill('');
  await idle(page);
  const id = await state(page, (s) => s.selectedBlock.id as string);

  try {
    await tab(page, 'پیشرفته').click();
    const advanced = page.getByTestId('tb-panel-fields-advanced');
    for (const key of ['sticky_devices', 'scroll_threshold', 'scrolled_bg_color', 'sticky_shrink_height']) {
      await expect(advanced.getByTestId(`tb-field-${key}`), `${key} needs sticky`).toHaveCount(0);
    }
    await advanced.getByTestId('tb-field-sticky').locator('label').last().click();
    for (const key of ['sticky_devices', 'sticky_offset', 'scroll_threshold', 'sticky_shrink', 'sticky_shrink_height', 'sticky_logo_scale', 'scrolled_bg_color', 'scrolled_shadow']) {
      await expect(advanced.getByTestId(`tb-field-${key}`), key).toBeVisible();
    }
    await advanced.getByTestId('tb-field-sticky_shrink').locator('label').last().click();
    await expect(advanced.getByTestId('tb-field-sticky_shrink_height'), 'no shrink: no shrink height').toHaveCount(0);

    await expect(advanced.getByTestId('tb-field-group-burger_breakpoint')).toHaveText('منوی موبایل');
    await expect(advanced.getByTestId('tb-field-drawer_side')).toBeVisible();
    await advanced.getByTestId('tb-segmented-drawer_style-full').click();
    await expect(advanced.getByTestId('tb-field-drawer_side'), 'a full-screen menu has no side').toHaveCount(0);
    await advanced.getByTestId('tb-field-burger_breakpoint').locator('select').selectOption('never');
    await expect(advanced.getByTestId('tb-field-drawer_style'), 'never: no drawer').toHaveCount(0);
    expect(await state(page, (s) => [s.selectedBlock.props.sticky, s.selectedBlock.props.sticky_shrink, s.selectedBlock.props.drawer_style, s.selectedBlock.props.burger_breakpoint]))
      .toEqual([true, false, 'full', 'never']);

    const frame = page.frameLocator('#tb-live-iframe');
    await expect.poll(() => frame.locator(`#tb-${id}`).getAttribute('data-tb-burger'), { timeout: 20_000 }).toBe('never');
    await page.getByTestId('tb-inspector-scroll').screenshot({ path: 'test-results/adr082-step4-advanced-tab.png' });
  } finally {
    await page.evaluate((blockId) => {
      const s = (document.querySelector('[x-data="themeCanvas()"]') as any)._x_dataStack[0];
      s.selectBlockById(blockId);
      if (s.selectedBlock?.id === blockId) s.removeSelected();
    }, id);
    await idle(page);
    expect(await state(page, (s) => s.saveCurrentPage())).toBe(true);
    await idle(page);
  }
  expect(problems, problems.join('\n')).toEqual([]);
});
