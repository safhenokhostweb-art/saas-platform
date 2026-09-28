import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loadFixtures, loginAsAdmin, canvasUrl, PALETTE_PANEL, TREE_PANEL } from './helpers';

const fx = loadFixtures();

const PAGE_MARKER = 'PAGE_MARKER_UI_C2B';
const HEADER_MARKER = 'HEADER_MARKER_UI_C2B';
const HEADER_NESTED_MARKER = 'HEADER_NESTED_MARKER_UI_C2B';
const HEADER_EXTENSION_MARKER = 'HEADER_EXTENSION_MARKER_UI_C2B';
const FOOTER_MARKER = 'FOOTER_MARKER_UI_C2B';
const FOOTER_NESTED_MARKER = 'FOOTER_NESTED_MARKER_UI_C2B';
const FOOTER_EXTENSION_MARKER = 'FOOTER_EXTENSION_MARKER_UI_C2B';

type CanvasBlock = {
  type?: string;
  props?: Record<string, unknown>;
  children?: CanvasBlock[];
};

type PersistedThemeState = {
  theme_id: number;
  current_release_id: number | null;
  commercially_eligible: boolean;
  pages: Record<string, { blocks?: CanvasBlock[] }>;
  header_blocks: CanvasBlock[];
  footer_blocks: CanvasBlock[];
  settings: Record<string, unknown>;
  release: null | {
    id: number;
    version: string;
    status: string;
    snapshot: Record<string, unknown>;
    dependencies: Array<Record<string, unknown>> | null;
    structured_dependencies: Array<Record<string, unknown>>;
    quality_report_id: number | null;
    quality_report_status: string | null;
  };
};

function readPersistedThemeState(themeId: number): PersistedThemeState {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::findOrFail(${themeId});
$release = $theme->currentRelease()->first();
$report = $release ? $release->qualityReports()->latest('id')->first() : null;
$structured = $release ? $release->structuredDependencies()->get([
    'dependency_type', 'dependency_identifier', 'version_constraint', 'is_required'
])->toArray() : [];
echo json_encode([
    'theme_id' => $theme->id,
    'current_release_id' => $theme->current_release_id,
    'commercially_eligible' => $theme->isCommerciallyEligible(),
    'pages' => $theme->pages,
    'header_blocks' => $theme->header_blocks,
    'footer_blocks' => $theme->footer_blocks,
    'settings' => $theme->settings,
    'release' => $release ? [
        'id' => $release->id,
        'version' => $release->version,
        'status' => $release->status,
        'snapshot' => $release->snapshot,
        'dependencies' => $release->dependencies,
        'structured_dependencies' => $structured,
        'quality_report_id' => $report?->id,
        'quality_report_status' => $report?->status,
    ] : null,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
`;

  return JSON.parse(execFileSync(php, ['-r', source], {
    cwd: process.cwd(),
    encoding: 'utf8',
  }).trim()) as PersistedThemeState;
}

function serialized(value: unknown): string {
  return JSON.stringify(value);
}

async function waitForCanvasHydration(page: import('@playwright/test').Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
      _x_dataStack?: Array<Record<string, unknown>>;
    } | null;
    return root?._x_dataStack?.some((entry) => entry.activeTarget !== undefined
      && entry.showAddPage === false) ?? false;
  });
  await expect(page.locator('[x-show="showAddPage"]')).toBeHidden();
}

function containsNestedMarker(blocks: CanvasBlock[], containerType: string, childType: string, marker: string): boolean {
  return blocks.some((block) => block.type === containerType
    && (block.children ?? []).some((child) => child.type === childType && serialized(child.props).includes(marker)));
}

test.describe('Theme Canvas — header and footer zones', () => {
  test('isolates header, footer, and page saves through the existing Canvas UI', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await waitForCanvasHydration(page);

    for (const zone of [/هدر/, /فوتر/] as const) {
      await page.getByRole('button', { name: zone }).click();
      const expectedTarget = zone.source.includes('هدر') ? 'header' : 'footer';
      const autoSave = page.waitForResponse((response) =>
        response.url().includes('/canvas/save')
          && response.request().method() === 'PUT'
          && JSON.parse(response.request().postData() ?? '{}').target === expectedTarget
      );
      await page.locator(PALETTE_PANEL).getByText('عنوان', { exact: true }).click();
      const autoSaveResponse = await autoSave;
      expect(autoSaveResponse.status(), await autoSaveResponse.text()).toBeGreaterThanOrEqual(200);
      expect(autoSaveResponse.status(), 'Canvas autosave must succeed').toBeLessThan(300);

      // Adding a block already invokes Canvas's canonical autosave. Waiting
      // for a second, manual request races that completed transition and is
      // neither required nor guaranteed by the UI state machine.
      await expect(page.locator('span[x-show="quickSaving"]')).toBeHidden();
    }

    await page.getByRole('button', { name: 'صفحات', exact: true }).click();
    await page.getByRole('button', { name: 'ساختار', exact: true }).click();
    await expect(page.locator(TREE_PANEL)).toBeVisible();

    await page.reload();
    await page.getByRole('button', { name: /هدر/ }).click();
    await page.getByRole('button', { name: 'ساختار', exact: true }).click();
    await expect(page.locator(TREE_PANEL).getByText('عنوان', { exact: true }).first()).toBeVisible();
    await page.getByRole('button', { name: /فوتر/ }).click();
    await expect(page.locator(TREE_PANEL).getByText('عنوان', { exact: true }).first()).toBeVisible();
  });

  test('keeps a failed Header autosave dirty and recovers through the existing save-on-switch path', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await waitForCanvasHydration(page);
    await page.getByRole('button', { name: /هدر/ }).click();

    let rejected = false;
    await page.route('**/canvas/save', async (route) => {
      const payload = JSON.parse(route.request().postData() ?? '{}');
      if (!rejected && payload.target === 'header') {
        rejected = true;
        await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ error: 'controlled save failure' }) });
        return;
      }
      await route.continue();
    });

    const rejectedSave = page.waitForResponse((response) =>
      response.url().includes('/canvas/save')
        && response.request().method() === 'PUT'
        && JSON.parse(response.request().postData() ?? '{}').target === 'header'
        && response.status() === 422
    );
    await page.locator(PALETTE_PANEL).getByText('عنوان', { exact: true }).click();
    await rejectedSave;
    await expect(page.locator('div.fixed.bottom-6.bg-red-600')).toBeVisible();
    // exact: the canvas dirty chip's text is exactly 'ذخیره نشده'; substring
    // matching would also hit the Global Styles panel's 'تغییرات ذخیره نشده'
    // dirty indicator (data-testid=global-styles-dirty) and trip strict mode.
    await expect(page.getByText('ذخیره نشده', { exact: true })).toBeVisible();

    await page.unroute('**/canvas/save');
    const recovery = page.waitForResponse((response) =>
      response.url().includes('/canvas/save')
        && response.request().method() === 'PUT'
        && JSON.parse(response.request().postData() ?? '{}').target === 'header'
        && response.ok()
    );
    await page.getByRole('button', { name: /فوتر/ }).click();
    await recovery;
    await expect(page.getByRole('button', { name: /فوتر/ })).toHaveClass(/bg-indigo-600/);
  });

  for (const [name, width] of [['desktop', 1366], ['tablet', 768], ['mobile', 390]] as const) {
    test(`keeps Canvas zone navigation usable at ${name} width`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await loginAsAdmin(page, fx);
      await page.goto(canvasUrl(fx));
      await waitForCanvasHydration(page);

      const pages = page.getByRole('button', { name: 'صفحات', exact: true });
      const header = page.getByRole('button', { name: /هدر/ });
      const footer = page.getByRole('button', { name: /فوتر/ });
      await expect(pages).toBeVisible();
      await expect(header).toBeVisible();
      await expect(footer).toBeVisible();
      await header.focus();
      await expect(header).toBeFocused();
      await header.click();
      await expect(header).toHaveClass(/bg-indigo-600/);
      await footer.click();
      await expect(footer).toHaveClass(/bg-indigo-600/);
      await pages.click();
      await expect(pages).toHaveClass(/bg-indigo-600/);
      await page.getByRole('button', { name: 'ساختار', exact: true }).click();
      await expect(page.locator(TREE_PANEL)).toBeVisible();
      expect(await page.locator('body').evaluate((body) => body.scrollWidth <= window.innerWidth)).toBeTruthy();
    });
  }

  test('authors the selected Extension block in Header and Footer through the canonical autosave', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx), { waitUntil: 'domcontentloaded' });
    await waitForCanvasHydration(page);
    const palette = page.locator(PALETTE_PANEL);
    const extensionLeaf = palette.locator('[data-block-type="ext:e2etest:gallery"]');
    await expect(extensionLeaf).toBeVisible();

    for (const [zone, target] of [[/هدر/, 'header'], [/فوتر/, 'footer']] as const) {
      await page.getByRole('button', { name: zone }).click();
      await page.getByRole('button', { name: 'بلوک‌ها', exact: true }).click();
      const saved = page.waitForResponse((response) => response.url().includes('/canvas/save')
        && response.request().method() === 'PUT'
        && JSON.parse(response.request().postData() ?? '{}').target === target
        && response.ok());
      await extensionLeaf.click();
      await saved;
      await expect(page.locator('span[x-show="quickSaving"]')).toBeHidden();
      await page.reload();
      await page.getByRole('button', { name: zone }).click();
      await page.getByRole('button', { name: 'ساختار', exact: true }).click();
      expect(await page.locator(TREE_PANEL).locator('[data-block-type="ext:e2etest:gallery"]').count()).toBeGreaterThan(0);
    }
  });

  test('rejects unavailable and unselected Extension blocks in Header and Footer', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx), { waitUntil: 'domcontentloaded' });
    await waitForCanvasHydration(page);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    expect(csrf).toBeTruthy();

    for (const target of ['header', 'footer'] as const) {
      for (const type of ['ext:e2etest:not_registered', 'ext:e2etest:asset_widget'] as const) {
        const result = await page.evaluate(async ({ themeId, csrfToken, targetName, blockType }) => {
          const response = await fetch(`/admin/theme-builder/${themeId}/canvas/save`, {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              target: targetName,
              blocks: [{ id: `rejected-${targetName}-${blockType}`, type: blockType, props: {} }],
              extension_selections: {
                'e2etest/gallery-suite': { version: '1.0.0' },
              },
            }),
          });
          return { status: response.status, body: await response.json() };
        }, {
          themeId: fx.theme_id,
          csrfToken: csrf!,
          targetName: target,
          blockType: type,
        });

        expect(result.status, serialized(result.body)).toBe(422);
        expect(typeof result.body.error, serialized(result.body)).toBe('string');
      }
    }

    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      return root?._x_dataStack?.some((entry) => entry.headerBlocks !== undefined
        && entry.footerBlocks !== undefined) ?? false;
    });
    const zones = await page.locator('[x-data="themeCanvas()"]')
      .evaluate((element) => {
        const stack = (element as HTMLElement & { _x_dataStack?: Array<Record<string, unknown>> })._x_dataStack ?? [];
        const data = stack.find((entry) => entry.headerBlocks !== undefined && entry.footerBlocks !== undefined);
        return JSON.stringify({ header: data?.headerBlocks, footer: data?.footerBlocks });
      });
    expect(zones).not.toContain('ext:e2etest:not_registered');
    expect(zones).not.toContain('ext:e2etest:asset_widget');
  });

  test('serializes a zone switch while a Header autosave response is delayed', async ({ page }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx), { waitUntil: 'domcontentloaded' });
    await waitForCanvasHydration(page);
    const header = page.getByRole('button', { name: /هدر/ });
    const footer = page.getByRole('button', { name: /فوتر/ });
    const pages = page.getByRole('button', { name: 'صفحات', exact: true });
    const dirtyIndicator = page.getByText('ذخیره نشده', { exact: true });
    const raceMarker = 'HEADER_DELAYED_RACE_MARKER_UI_C2B';

    await header.click();
    const initialHeaderSave = page.waitForResponse((response) => response.url().includes('/canvas/save')
      && response.request().method() === 'PUT'
      && JSON.parse(response.request().postData() ?? '{}').target === 'header'
      && response.ok());
    await page.locator(PALETTE_PANEL).getByText('عنوان', { exact: true }).click();
    await initialHeaderSave;
    await page.locator('#ctrl-text').fill(raceMarker);
    await expect(dirtyIndicator).toBeVisible();

    let release!: () => void;
    const gate = new Promise<void>((resolve) => { release = resolve; });
    let intercepted!: Record<string, unknown>;
    let seen!: () => void;
    const seenRequest = new Promise<void>((resolve) => { seen = resolve; });
    await page.route('**/canvas/save', async (route) => {
      const payload = JSON.parse(route.request().postData() ?? '{}');
      if (!intercepted && payload.target === 'header') {
        intercepted = payload;
        seen();
        await gate;
      }
      await route.continue();
    });

    const completedSave = page.waitForResponse((response) => response.url().includes('/canvas/save')
      && response.request().method() === 'PUT'
      && JSON.parse(response.request().postData() ?? '{}').target === 'header');
    await footer.click();
    await seenRequest;
    expect(intercepted.target).toBe('header');
    await expect(footer).toBeDisabled();
    await expect(pages).toBeDisabled();
    await expect(header).toHaveClass(/bg-indigo-600/);
    await expect(footer).not.toHaveClass(/bg-indigo-600/);
    await expect(dirtyIndicator).toBeVisible();
    await expect(pages.click({ timeout: 1_000 })).rejects.toThrow();
    const pendingHeader = await page.locator('[x-data="themeCanvas()"]')
      .evaluate((element) => {
        const stack = (element as HTMLElement & { _x_dataStack?: Array<Record<string, unknown>> })._x_dataStack ?? [];
        const data = stack.find((entry) => entry.headerBlocks !== undefined);
        return JSON.stringify(data?.headerBlocks);
      });
    expect(pendingHeader).toContain(raceMarker);

    release();
    const saveResponse = await completedSave;
    expect(saveResponse.ok(), await saveResponse.text()).toBeTruthy();
    await expect(footer).toHaveClass(/bg-indigo-600/);
    await expect(footer).toBeEnabled();
    const footerTree = await page.locator('[x-data="themeCanvas()"]')
      .evaluate((element) => {
        const stack = (element as HTMLElement & { _x_dataStack?: Array<Record<string, unknown>> })._x_dataStack ?? [];
        const data = stack.find((entry) => entry.footerBlocks !== undefined);
        return JSON.stringify(data?.footerBlocks);
      });
    expect(footerTree).not.toContain(raceMarker);

    await pages.click();
    await expect(pages).toHaveClass(/bg-indigo-600/);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await waitForCanvasHydration(page);
    const persisted = readPersistedThemeState(fx.theme_id);
    expect(serialized(persisted.header_blocks)).toContain(raceMarker);
    expect(serialized(persisted.footer_blocks)).not.toContain(raceMarker);
    expect(serialized(persisted.pages)).not.toContain(raceMarker);
  });

  test('opens the canonical selected-zone preview for Header and Footer', async ({ page, context }) => {
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx), { waitUntil: 'domcontentloaded' });
    await waitForCanvasHydration(page);
    for (const [zone, target] of [[/هدر/, 'header'], [/فوتر/, 'footer']] as const) {
      await page.getByRole('button', { name: zone }).click();
      const previewPromise = context.waitForEvent('page');
      await page.getByRole('button', { name: 'پیش‌نمایش', exact: true }).click();
      const preview = await previewPromise;
      await preview.waitForLoadState('domcontentloaded');
      expect(preview.url()).toContain(`target=${target}`);
      await expect(preview.locator('body')).not.toBeEmpty();
      await preview.close();
    }
  });

  test('renders distinct selected zones and publishes their exact state into a new ThemeRelease', async ({ page, context }) => {
    // The commercial gate owns four real browser checks. Its canonical
    // process budget is 90 seconds per check; this outer synchronization
    // bound accommodates that existing budget plus UI/setup overhead.
    test.setTimeout(480_000);

    const saveButton = page.getByRole('button', { name: 'ذخیره', exact: true });
    const dirtyIndicator = page.getByText('ذخیره نشده', { exact: true });
    const quickSaving = page.locator('span[x-show="quickSaving"]');
    const palette = page.locator(PALETTE_PANEL);

    const matchingSave = (target: 'page' | 'header' | 'footer') => page.waitForResponse((response) => {
      if (!response.url().includes('/canvas/save') || response.request().method() !== 'PUT') return false;
      const payload = JSON.parse(response.request().postData() ?? '{}');
      return payload.target === target;
    });

    const assertSuccessfulSave = async (response: import('@playwright/test').Response) => {
      const responseText = await response.text();
      expect(response.status(), responseText).toBeGreaterThanOrEqual(200);
      expect(response.status(), responseText).toBeLessThan(300);
      expect(JSON.parse(responseText).success, responseText).toBe(true);
      await expect(quickSaving).toBeHidden();
    };

    const addBlock = async (target: 'page' | 'header' | 'footer', type: string) => {
      const blockButton = palette.locator(`[data-block-type="${type}"]`);
      await expect(blockButton).toBeVisible();
      const saved = matchingSave(target);
      await blockButton.click();
      await assertSuccessfulSave(await saved);
    };

    const persistProp = async (target: 'page' | 'header' | 'footer', controlId: string, value: string) => {
      await page.locator(controlId).fill(value);
      await expect(dirtyIndicator).toBeVisible();
      const saved = matchingSave(target);
      await saveButton.click();
      const response = await saved;
      const payload = JSON.parse(response.request().postData() ?? '{}');
      expect(serialized(payload.blocks)).toContain(value);
      await assertSuccessfulSave(response);
      await expect(dirtyIndicator).toBeHidden();
    };

    const addNestedBlock = async (
      target: 'header' | 'footer',
      containerType: 'container' | 'grid',
      childName: 'عنوان' | 'متن',
      childControlId: '#ctrl-text' | '#ctrl-content',
      marker: string,
    ) => {
      await addBlock(target, containerType);
      await page.getByRole('button', { name: 'افزودن', exact: true }).click();
      const childModal = page.locator('div.fixed.inset-0').filter({ hasText: 'افزودن بلوک فرزند' });
      await expect(childModal).toBeVisible();
      const childLabel = childModal.getByText(childName, { exact: true });
      await expect(childLabel).toBeVisible();
      const childSaved = matchingSave(target);
      await childLabel.click();
      await assertSuccessfulSave(await childSaved);
      await persistProp(target, childControlId, marker);
    };

    const alpineState = async () => {
      await page.waitForFunction(() => {
        const element = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
          _x_dataStack?: Array<Record<string, unknown>>;
        } | null;
        return element?._x_dataStack?.some((entry) => entry.pages !== undefined
          && entry.headerBlocks !== undefined && entry.footerBlocks !== undefined) ?? false;
      });

      return page.locator('[x-data="themeCanvas()"]')
        .evaluate((element) => {
          const stack = (element as HTMLElement & { _x_dataStack?: Array<Record<string, unknown>> })._x_dataStack ?? [];
          const data = stack.find((entry) => entry.pages !== undefined
            && entry.headerBlocks !== undefined && entry.footerBlocks !== undefined);
          if (!data) throw new Error('Canvas Alpine state is unavailable');
          return JSON.parse(JSON.stringify({
            pages: data.pages,
            headerBlocks: data.headerBlocks,
            footerBlocks: data.footerBlocks,
            extensionSelections: data.extensionSelections,
          }));
        }) as Promise<{
        pages: Record<string, { blocks?: CanvasBlock[] }>;
        headerBlocks: CanvasBlock[];
        footerBlocks: CanvasBlock[];
        extensionSelections: Record<string, { version?: string }>;
      }>;
    };

    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx), { waitUntil: 'domcontentloaded' });
    await waitForCanvasHydration(page);
    await expect(page.locator('[x-data="themeCanvas()"]')).toBeVisible();

    // PAGE: one exact, normal built-in block marker. Setup is intentionally
    // resume-safe because a failed later preview/publish assertion may leave
    // already-successful canonical saves in the isolated fixture.
    let setupState = await alpineState();
    if (!serialized(setupState.pages.home?.blocks ?? []).includes(PAGE_MARKER)) {
      await addBlock('page', 'heading');
      await persistProp('page', '#ctrl-text', PAGE_MARKER);
    }

    // HEADER: normal heading, nested container/text, and exact selected Extension.
    await page.getByRole('button', { name: /هدر/ }).click();
    setupState = await alpineState();
    if (!serialized(setupState.headerBlocks).includes(HEADER_MARKER)) {
      await addBlock('header', 'heading');
      await persistProp('header', '#ctrl-text', HEADER_MARKER);
    }
    if (!containsNestedMarker(setupState.headerBlocks, 'container', 'text', HEADER_NESTED_MARKER)) {
      await addNestedBlock('header', 'container', 'متن', '#ctrl-content', HEADER_NESTED_MARKER);
    }
    setupState = await alpineState();
    if (!setupState.headerBlocks.some((block) => block.type === 'ext:e2etest:gallery'
      && serialized(block.props).includes(HEADER_EXTENSION_MARKER))) {
      await addBlock('header', 'ext:e2etest:gallery');
      await persistProp('header', '#ctrl-title', HEADER_EXTENSION_MARKER);
    }

    // FOOTER: deliberately different normal/nested built-in block types.
    await page.getByRole('button', { name: /فوتر/ }).click();
    setupState = await alpineState();
    if (!serialized(setupState.footerBlocks).includes(FOOTER_MARKER)) {
      await addBlock('footer', 'text');
      await persistProp('footer', '#ctrl-content', FOOTER_MARKER);
    }
    if (!containsNestedMarker(setupState.footerBlocks, 'grid', 'heading', FOOTER_NESTED_MARKER)) {
      await addNestedBlock('footer', 'grid', 'عنوان', '#ctrl-text', FOOTER_NESTED_MARKER);
    }
    setupState = await alpineState();
    if (!setupState.footerBlocks.some((block) => block.type === 'ext:e2etest:gallery'
      && serialized(block.props).includes(FOOTER_EXTENSION_MARKER))) {
      await addBlock('footer', 'ext:e2etest:gallery');
      await persistProp('footer', '#ctrl-title', FOOTER_EXTENSION_MARKER);
    }

    // Reload makes this browser state server-originated rather than merely an
    // in-memory Alpine assertion, then verify exact zone ownership/shape.
    await page.reload({ waitUntil: 'domcontentloaded' });
    const persistedInCanvas = await alpineState();
    const persistedPage = persistedInCanvas.pages.home?.blocks ?? [];
    expect(serialized(persistedPage)).toContain(PAGE_MARKER);
    expect(serialized(persistedPage)).not.toContain(HEADER_MARKER);
    expect(serialized(persistedPage)).not.toContain(FOOTER_MARKER);
    expect(serialized(persistedInCanvas.headerBlocks)).toContain(HEADER_MARKER);
    expect(containsNestedMarker(persistedInCanvas.headerBlocks, 'container', 'text', HEADER_NESTED_MARKER)).toBe(true);
    expect(persistedInCanvas.headerBlocks.some((block) => block.type === 'ext:e2etest:gallery'
      && serialized(block.props).includes(HEADER_EXTENSION_MARKER))).toBe(true);
    expect(serialized(persistedInCanvas.headerBlocks)).not.toContain(FOOTER_MARKER);
    expect(serialized(persistedInCanvas.headerBlocks)).not.toContain(PAGE_MARKER);
    expect(serialized(persistedInCanvas.footerBlocks)).toContain(FOOTER_MARKER);
    expect(containsNestedMarker(persistedInCanvas.footerBlocks, 'grid', 'heading', FOOTER_NESTED_MARKER)).toBe(true);
    expect(persistedInCanvas.footerBlocks.some((block) => block.type === 'ext:e2etest:gallery'
      && serialized(block.props).includes(FOOTER_EXTENSION_MARKER))).toBe(true);
    expect(serialized(persistedInCanvas.footerBlocks)).not.toContain(HEADER_MARKER);
    expect(serialized(persistedInCanvas.footerBlocks)).not.toContain(PAGE_MARKER);
    expect(persistedInCanvas.extensionSelections['e2etest/gallery-suite']?.version).toBe('1.0.0');

    const previewZone = async (
      zoneName: RegExp,
      target: 'header' | 'footer',
      ownMarker: string,
      nestedMarker: string,
      extensionMarker: string,
      staleMarkers: string[],
    ) => {
      await page.getByRole('button', { name: zoneName }).click();
      const opened = context.waitForEvent('page');
      await page.getByRole('button', { name: 'پیش‌نمایش', exact: true }).click();
      const preview = await opened;
      await preview.waitForLoadState('domcontentloaded');
      expect(new URL(preview.url()).searchParams.get('target')).toBe(target);
      await expect(preview.getByText(ownMarker, { exact: true }).first()).toBeVisible();
      await expect(preview.getByText(nestedMarker, { exact: true }).first()).toBeVisible();
      const extensionOutput = preview.locator('[data-type="ext:e2etest:gallery"]')
        .filter({ hasText: extensionMarker });
      await expect(extensionOutput.first()).toBeVisible();
      for (const staleMarker of staleMarkers) {
        await expect(preview.getByText(staleMarker, { exact: true })).toHaveCount(0);
      }
      const extensionType = await extensionOutput.first().getAttribute('data-type');
      expect(extensionType).toBe('ext:e2etest:gallery');
      await preview.close();
    };

    await previewZone(
      /هدر/,
      'header',
      HEADER_MARKER,
      HEADER_NESTED_MARKER,
      HEADER_EXTENSION_MARKER,
      [FOOTER_MARKER, PAGE_MARKER],
    );
    await previewZone(
      /فوتر/,
      'footer',
      FOOTER_MARKER,
      FOOTER_NESTED_MARKER,
      FOOTER_EXTENSION_MARKER,
      [HEADER_MARKER, PAGE_MARKER],
    );

    const beforePublish = readPersistedThemeState(fx.theme_id);
    expect(serialized(beforePublish.pages.home?.blocks ?? [])).toContain(PAGE_MARKER);
    expect(serialized(beforePublish.header_blocks)).toContain(HEADER_MARKER);
    expect(serialized(beforePublish.footer_blocks)).toContain(FOOTER_MARKER);

    // The visible Canvas action is the operation under test. It performs its
    // own canonical save before POSTing the existing commercial publish route.
    // If a later assertion failed after a successful POST, resume from that
    // immutable new release rather than creating an unrelated duplicate.
    let afterPublish = beforePublish;
    if (beforePublish.current_release_id === fx.theme_release_id) {
      expect(beforePublish.release?.version).toBe(fx.theme_release_version);
      const publishResponsePromise = page.waitForResponse((response) => response.url().endsWith(`/theme-builder/${fx.theme_id}/publish`)
        && response.request().method() === 'POST', { timeout: 420_000 });
      await page.getByRole('button', { name: 'انتشار', exact: true }).click();
      const publishResponse = await publishResponsePromise;
      const publishResponseText = await publishResponse.text();
      expect(publishResponse.status(), publishResponseText).toBeGreaterThanOrEqual(200);
      expect(publishResponse.status(), publishResponseText).toBeLessThan(300);
      expect(JSON.parse(publishResponseText).success, publishResponseText).toBe(true);
      await expect(page.getByRole('button', { name: 'انتشار', exact: true })).toBeEnabled();
      afterPublish = readPersistedThemeState(fx.theme_id);
    }

    expect(afterPublish.current_release_id).not.toBe(fx.theme_release_id);
    expect(afterPublish.release?.id).toBe(afterPublish.current_release_id);
    expect(afterPublish.release?.status).toBe('published');
    expect(afterPublish.release?.quality_report_status).toBe('passed');
    expect(afterPublish.commercially_eligible).toBe(true);

    const snapshot = afterPublish.release?.snapshot ?? {};
    const snapshotPages = snapshot.pages as Record<string, { blocks?: CanvasBlock[] }>;
    const snapshotHeader = snapshot.header_blocks as CanvasBlock[];
    const snapshotFooter = snapshot.footer_blocks as CanvasBlock[];
    expect(serialized(snapshotPages.home?.blocks ?? [])).toContain(PAGE_MARKER);
    expect(serialized(snapshotPages)).not.toContain(HEADER_MARKER);
    expect(serialized(snapshotPages)).not.toContain(FOOTER_MARKER);
    expect(serialized(snapshotHeader)).toContain(HEADER_MARKER);
    expect(containsNestedMarker(snapshotHeader, 'container', 'text', HEADER_NESTED_MARKER)).toBe(true);
    expect(serialized(snapshotHeader)).not.toContain(FOOTER_MARKER);
    expect(serialized(snapshotHeader)).not.toContain(PAGE_MARKER);
    expect(serialized(snapshotFooter)).toContain(FOOTER_MARKER);
    expect(containsNestedMarker(snapshotFooter, 'grid', 'heading', FOOTER_NESTED_MARKER)).toBe(true);
    expect(serialized(snapshotFooter)).not.toContain(HEADER_MARKER);
    expect(serialized(snapshotFooter)).not.toContain(PAGE_MARKER);
    expect((snapshot.settings as { extension_selections?: Record<string, { version?: string }> })
      .extension_selections?.['e2etest/gallery-suite']?.version).toBe('1.0.0');

    const expectedDependency = {
      type: 'extension',
      ref: 'e2etest/gallery-suite',
      version_constraint: '=1.0.0',
      is_required: true,
    };
    expect(afterPublish.release?.dependencies).toEqual(expect.arrayContaining([
      expect.objectContaining(expectedDependency),
    ]));
    expect(afterPublish.release?.structured_dependencies).toEqual(expect.arrayContaining([
      expect.objectContaining({
        dependency_type: expectedDependency.type,
        dependency_identifier: expectedDependency.ref,
        version_constraint: expectedDependency.version_constraint,
        is_required: expectedDependency.is_required,
      }),
    ]));

  });
});
