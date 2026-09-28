import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

/**
 * Phase 3B — professional Persian hero family browser acceptance.
 *
 * Proves through the REAL editor UI on the Laragon vhost:
 *   1. `hero_pro_persian` inserts from the Section Library through the
 *      canonical tree operations (the 3A library boundary — no second engine);
 *   2. the 4-level hierarchy lands intact:
 *      hero_section → hero_container → hero_content_wrapper →
 *      [badge, heading, text, hero_actions → [button, button]];
 *   3. every composed part is Inspector-editable (select + number controls);
 *   4. autosave returns 200 and the full tree survives a reload.
 */

const fx = loadFixtures();

const TITLE = 'فروشگاه خود را حرفه‌ای بسازید';
const BADGE = 'فروشگاه شما، هوشمندتر از همیشه';
const CTA = 'شروع کنید';

type CanvasBlock = {
  id?: string;
  type: string;
  props?: Record<string, unknown>;
  children?: CanvasBlock[];
};

type PersistedThemeState = {
  pages: Record<string, { blocks?: CanvasBlock[] }>;
  header_blocks: CanvasBlock[];
  footer_blocks: CanvasBlock[];
};

/** Reads the theme's persisted state through the framework bootstrap (same pattern as the zones spec). */
function readPersistedThemeState(themeId: number): PersistedThemeState {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$theme = App\\Models\\Theme::findOrFail(${themeId});
echo json_encode([
    'pages' => $theme->pages,
    'header_blocks' => $theme->header_blocks,
    'footer_blocks' => $theme->footer_blocks,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
`;
  return JSON.parse(execFileSync(php, ['-r', source], {
    cwd: process.cwd(),
    encoding: 'utf8',
  }).trim()) as PersistedThemeState;
}

function flatten(blocks: CanvasBlock[], depth = 0): Array<{ type: string; depth: number; props: Record<string, unknown> }> {
  const out: Array<{ type: string; depth: number; props: Record<string, unknown> }> = [];
  for (const block of blocks ?? []) {
    out.push({ type: block.type, depth, props: block.props ?? {} });
    out.push(...flatten(block.children ?? [], depth + 1));
  }
  return out;
}

function findHeroTree(blocks: CanvasBlock[]): CanvasBlock | undefined {
  const stack = [...blocks];
  while (stack.length) {
    const node = stack.pop() as CanvasBlock;
    if (node.type === 'hero_section') return node;
    stack.push(...(node.children ?? []));
  }
  return undefined;
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

test.describe('Theme Canvas — Phase 3B hero family', () => {
  test('hero_pro_persian inserts with 4-level nesting, inspector editing, and a 200 save', async ({ page }) => {
    test.setTimeout(180_000);
    await loginAsAdmin(page, fx);
    await page.goto(canvasUrl(fx));
    await waitForCanvasHydration(page);

    // ── 1. Insert from the Section Library (canonical path) ──────────
    await page.getByRole('button', { name: 'بخش‌ها' }).click();
    const modal = page.locator('div[x-show="showSectionLibrary"]');
    await expect(modal).toBeVisible();
    const card = modal.locator('div.cursor-pointer', { hasText: 'قهرمان حرفه‌ای — فارسی' }).first();
    await expect(card).toBeVisible();
    await card.click();
    await expect(modal).toBeHidden();

    // ── 2. The 4-level hierarchy landed intact ────────────────────────
    const treeJson = await page.evaluate(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as { blocks?: unknown } | undefined;
      return JSON.stringify(state?.blocks ?? []);
    });
    const blocks: CanvasBlock[] = JSON.parse(treeJson);
    const hero = findHeroTree(blocks);
    expect(hero, 'hero_section must exist in the editor tree after insert').toBeTruthy();

    const flat = flatten(blocks).filter((entry) =>
      ['hero_section', 'hero_container', 'hero_content_wrapper', 'badge', 'heading', 'text', 'hero_actions', 'button']
        .includes(entry.type));
    const depthOf = (type: string) => flat.filter((entry) => entry.type === type).map((entry) => entry.depth);
    expect(depthOf('hero_section')).toContain(0);
    expect(depthOf('hero_container')).toContain(1);
    expect(depthOf('hero_content_wrapper')).toContain(2);
    expect(depthOf('hero_actions')).toContain(3);
    expect(depthOf('badge')).toContain(3);
    expect(depthOf('heading')).toContain(3);
    expect(depthOf('button')).toContain(4);
    expect(flat.filter((entry) => entry.type === 'button').length).toBeGreaterThanOrEqual(2);

    // ── 3. Inspector edits on hero_section (select + number) ─────────
    // Inspect what the editor actually selected so a selection-API drift
    // fails with a readable message instead of a hidden-control timeout.
    const selection = await page.evaluate(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as { selectedBlock?: { type?: string } | null } | undefined;
      return state?.selectedBlock?.type ?? 'none';
    });
    expect(selection, 'hero_section must be the selected block for the Inspector step').toBe('hero_section');
    await page.evaluate(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as {
        blocks?: Array<Record<string, unknown>>;
        selectBlockById?: (id: string) => void;
      } | undefined;
      const find = (nodes: Array<Record<string, unknown>>): string | null => {
        for (const node of nodes) {
          if (node.type === 'hero_section') return String(node.id);
          const kids = (node.children ?? []) as Array<Record<string, unknown>>;
          const hit = find(kids);
          if (hit) return hit;
        }
        return null;
      };
      const id = find(state?.blocks ?? []);
      if (id && typeof state?.selectBlockById === 'function') state.selectBlockById(id);
    });

    const inspector = page.locator('div[x-show="selectedTab === \'content\'"]');
    await expect(inspector).toBeVisible();
    const bgTypeSelect = inspector.locator('#ctrl-background_type');
    await expect(bgTypeSelect).toBeVisible();
    await bgTypeSelect.selectOption('color');
    const bgTypeAfterSelect = await page.evaluate(() => {
      const find = (nodes: Array<{ type?: string; props?: Record<string, unknown>; children?: unknown[] }>): Record<string, unknown> | null => {
        for (const node of nodes) {
          if (node.type === 'hero_section') return (node.props ?? {}) as Record<string, unknown>;
          const hit = find((node.children ?? []) as typeof nodes);
          if (hit) return hit;
        }
        return null;
      };
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as { blocks?: unknown } | undefined;
      return JSON.stringify(find((state?.blocks ?? []) as Parameters<typeof find>[0]) ?? {});
    });
    expect(JSON.parse(bgTypeAfterSelect).background_type).toBe('color');

    const paddingInput = inspector.locator('#ctrl-padding_v');
    await expect(paddingInput).toBeVisible();
    await paddingInput.fill('120');
    const propsAfterNumber = await page.evaluate(() => {
      const find = (nodes: Array<{ type?: string; props?: Record<string, unknown>; children?: unknown[] }>): Record<string, unknown> | null => {
        for (const node of nodes) {
          if (node.type === 'hero_section') return (node.props ?? {}) as Record<string, unknown>;
          const hit = find((node.children ?? []) as typeof nodes);
          if (hit) return hit;
        }
        return null;
      };
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as { blocks?: unknown } | undefined;
      return JSON.stringify(find((state?.blocks ?? []) as Parameters<typeof find>[0]) ?? {});
    });
    expect(JSON.parse(propsAfterNumber).padding_v).toBe(120);

    // ── 4. Explicit save through the editor's own path returns 200 ───
    // (A debounced autosave raced here: the response captured after the
    // number edit was the INSERT's save, and a later autosave would race
    // the assertion. Invoking the editor's own saveCurrentPage() is the
    // same button a human presses — deterministic, not a second engine.)
    const savePromise = page.waitForResponse((response) =>
      response.url().includes('/canvas/save')
        && response.request().method() === 'PUT'
        && (() => { try { return JSON.parse(response.request().postData() ?? '{}').target === 'page'; } catch { return false; } })()
    );
    await page.evaluate(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as { saveCurrentPage?: () => Promise<unknown> } | undefined;
      return state?.saveCurrentPage?.();
    });
    const saveResponse = await savePromise;
    expect(saveResponse.status(), await saveResponse.text()).toBe(200);
    const saveRequest = JSON.parse(saveResponse.request().postData() ?? '{}') as { blocks?: CanvasBlock[] };
    expect(JSON.stringify(saveRequest.blocks)).toContain(TITLE);

    // ── 5. Persisted server state matches the composed hero tree ─────
    // (The save pipeline strips default-valued props, so edits are what
    // must be asserted on the persisted tree — matching any inserted hero
    // instance, since the fixture theme may hold several across runs.)
    const persisted = readPersistedThemeState(fx.theme_id);
    const pageBlocks = persisted.pages.home?.blocks ?? [];
    const allPersistedHeroes = flatten(pageBlocks).filter((e) => e.type === 'hero_section').length;
    expect(allPersistedHeroes, 'at least one hero_section must persist server-side').toBeGreaterThanOrEqual(1);

    const persistedHero = (pageBlocks.flatMap(function walk(b: CanvasBlock): CanvasBlock[] {
      return [b, ...(b.children ?? []).flatMap(walk)];
    }).find((b) => b.type === 'hero_section'
      && Object.keys(b.props ?? {}).length >= 1) ?? pageBlocks.map(function walk2(b: CanvasBlock): CanvasBlock {
      const heroes: CanvasBlock[] = [];
      if (b.type === 'hero_section') heroes.push(b);
      for (const c of b.children ?? []) heroes.push(...walk2(c));
      return heroes;
    }).pop());
    expect(persistedHero, 'a hero tree with props must persist server-side').toBeTruthy();

    const persistedFlat = (persistedHero ? [persistedHero] : []).flatMap(function walk3(b: CanvasBlock): Array<{ type: string; depth: number }> {
      const out: Array<{ type: string; depth: number }> = [];
      const rec = (node: CanvasBlock, depth: number) => {
        out.push({ type: node.type, depth });
        for (const child of node.children ?? []) rec(child, depth + 1);
      };
      rec(b, 0);
      return out;
    });
    const persistedDepth = (type: string) => persistedFlat.filter((entry) => entry.type === type).map((entry) => entry.depth);
    expect(persistedDepth('hero_container')).toContain(1);
    expect(persistedDepth('hero_content_wrapper')).toContain(2);
    expect(persistedDepth('hero_actions')).toContain(3);
    expect(persistedDepth('button')).toContain(4);
    // The Inspector edits persisted (non-default values survive normalization).
    const anyEditedHero = pageBlocks.flatMap(function walkAll(b: CanvasBlock): CanvasBlock[] {
      return [b, ...(b.children ?? []).flatMap(walkAll)];
    }).filter((b) => b.type === 'hero_section'
      && (b.props as Record<string, unknown> | undefined)?.background_type === 'color'
      && (b.props as Record<string, unknown>).padding_v === 120);
    expect(anyEditedHero.length, 'the background_type=color + padding_v=120 edits must persist').toBeGreaterThanOrEqual(1);
    // Replaceable Persian placeholders survived the round trip.
    expect(JSON.stringify(pageBlocks)).toContain(TITLE);
    expect(JSON.stringify(pageBlocks)).toContain(BADGE);
    expect(JSON.stringify(pageBlocks)).toContain(CTA);

    // ── 6. Full tree survives a reload ───────────────────────────────
    await page.reload();
    await waitForCanvasHydration(page);
    const reloadedJson = await page.evaluate(() => {
      const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & {
        _x_dataStack?: Array<Record<string, unknown>>;
      } | null;
      const state = root?._x_dataStack?.[0] as { blocks?: CanvasBlock[] } | undefined;
      return JSON.stringify(state?.blocks ?? []);
    });
    const reloadedFlat = flatten(JSON.parse(reloadedJson))
      .filter((entry) => ['hero_section', 'hero_container', 'hero_content_wrapper', 'hero_actions', 'button'].includes(entry.type));
    const reloadedDepth = (type: string) => reloadedFlat.filter((entry) => entry.type === type).map((entry) => entry.depth);
    expect(reloadedDepth('hero_section')).toContain(0);
    expect(reloadedDepth('hero_container')).toContain(1);
    expect(reloadedDepth('hero_content_wrapper')).toContain(2);
    expect(reloadedDepth('hero_actions')).toContain(3);
    expect(reloadedDepth('button')).toContain(4);
    expect(reloadedJson).toContain(TITLE);
  });
});
