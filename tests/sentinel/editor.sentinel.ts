import { test } from '@playwright/test';
import { sentinelEnv } from './support/env';
import { record, REMEDIES } from './support/findings';
import { gotoWithRetry } from './support/navigation';

/**
 * ADR-077 — read-only Canvas editor audit (opt-in). Needs an authenticated
 * Playwright storage state (SENTINEL_STORAGE_STATE) and the editor paths
 * (SENTINEL_EDITOR_PATHS). It opens each editor and measures; it never
 * clicks, types or saves.
 */

const env = sentinelEnv();

test.describe.configure({ mode: 'parallel' });
test.skip(!env.storageState || env.editorPaths.length === 0, 'Editor audit needs SENTINEL_STORAGE_STATE and SENTINEL_EDITOR_PATHS.');
if (env.storageState) {
  test.use({ storageState: env.storageState });
}

for (const editorPath of env.editorPaths) {
  for (const width of [1024, 1280]) {
    test(`editor ${editorPath} @ ${width}px`, async ({ page }) => {
      const note = (code: string, severity: 'warning' | 'blocker', message: string, evidence?: Record<string, unknown>) =>
        record({ code, severity, page: editorPath, width, widget: null, message, evidence, remedy: REMEDIES[code] ?? '' });
      await page.setViewportSize({ width, height: 860 });
      const { response } = await gotoWithRetry(page, editorPath);
      if (!response || response.status() >= 400 || /\/login\b/.test(page.url())) {
        note('PAGE_HTTP_ERROR', 'blocker', `The editor did not open (status ${response?.status() ?? 'none'}, at ${page.url()}); is the storage state still signed in?`);
        return;
      }
      const strip = page.locator('[data-tb-tabstrip]').first();
      if (await strip.count() === 0) {
        note('TAB_STRIP_MISSING', 'blocker', 'The editor has no page-tab strip.');
        return;
      }
      await strip.waitFor({ state: 'visible', timeout: env.navTimeout }).catch(() => undefined);
      const m = await page.evaluate(() => {
        const s = document.querySelector('[data-tb-tabstrip]') as HTMLElement;
        const r = s.getBoundingClientRect();
        return { overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, left: r.left, right: r.right, width: r.width, vw: document.documentElement.clientWidth };
      });
      if (m.overflow > 1) note('EDITOR_HORIZONTAL_OVERFLOW', 'blocker', `The editor scrolls sideways by ${m.overflow}px.`);
      if (m.left < -1 || m.right > m.vw + 1 || m.width < 120) {
        note('TAB_STRIP_UNREACHABLE', 'blocker', `The page-tab strip spans ${Math.round(m.left)}..${Math.round(m.right)}px of ${m.vw}px.`, m);
      }
    });
  }
}
