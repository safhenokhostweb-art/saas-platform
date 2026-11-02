import { test, expect } from '@playwright/test';
import { sentinelEnv } from './support/env';

/**
 * ADR-077 — visual regression (opt-in, SENTINEL_VISUAL=1). Screenshots per
 * host and width live in tests/sentinel/__baselines__/<host>/; they are
 * written only with SENTINEL_UPDATE_BASELINES=1, never by the agent. A
 * failing test here becomes VISUAL_DIFF or VISUAL_BASELINE_MISSING in the
 * report (support/sentinel-reporter.ts).
 */

const env = sentinelEnv();
const slug = (p: string) => (p === '/' ? 'home' : p.replace(/^\/+|\/+$/g, '').replace(/[^a-z0-9]+/gi, '-').toLowerCase());

test.describe.configure({ mode: 'parallel' });
test.skip(!env.visual, 'Visual regression is opt-in: SENTINEL_VISUAL=1.');

for (const url of env.pages) {
  for (const width of env.widths) {
    test(`visual ${url} @ ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(url, { waitUntil: 'load' });
      await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => undefined);
      await expect(page).toHaveScreenshot(`${slug(url)}-${width}.png`, { maxDiffPixelRatio: 0.02, animations: 'disabled', caret: 'hide' });
    });
  }
}
