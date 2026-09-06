import { test, expect } from '@playwright/test';
import { loadFixtures, loginAsAdmin, assetThemeLivePreviewUrl, type E2EFixtures } from './helpers';

/**
 * Phase 3G-B-6B (ADR-048) — real-browser proof of the FULL declarative
 * Extension asset distribution chain: two real ExtensionReleases (1.0.0 and
 * 2.0.0 of e2etest/asset-suite), each materialized through
 * ExtensionReleaseService::release() with a real local source file (never a
 * hand-placed fixture asset), each under its own version-namespaced
 * `/vendor/e2etest/asset-suite/{version}/` path. A Theme pinned to 1.0.0
 * (with the element already placed) must keep resolving and serving
 * exactly 1.0.0's own CSS/JS — even though 2.0.0 (published after 1.0.0,
 * with different asset content at a different path) is now the Extension's
 * current_release_id.
 *
 * Requires fixtures seeded via:
 *   php artisan e2e:seed-canvas-fixtures > tests/browser/fixtures/e2e-fixtures.json
 * and a dev server (php artisan serve) + Redis running against the same DB.
 */

let fx: E2EFixtures;

test.beforeAll(() => {
  fx = loadFixtures();
});

test.describe('Theme Canvas — declarative Extension asset distribution (real release-backed)', () => {
  test('A. Preview resolves and serves exactly the pinned 1.0.0 release asset, not the newer 2.0.0', async ({ page, request }) => {
    await loginAsAdmin(page, fx);
    await page.goto(assetThemeLivePreviewUrl(fx));

    // The emitted tag corresponds to the exact selected (1.0.0) release, never current_release_id (2.0.0).
    await expect(page.locator(`link[href="${fx.asset_v1_css_path}"]`)).toHaveCount(1);
    await expect(page.locator(`script[src="${fx.asset_v1_js_path}"]`)).toHaveCount(1);
    await expect(page.locator(`link[href="${fx.asset_v2_css_path}"]`)).toHaveCount(0);
    await expect(page.locator(`script[src="${fx.asset_v2_js_path}"]`)).toHaveCount(0);

    // Real HTTP 200 for the exact emitted path, real content.
    const cssResponse = await request.get(fx.asset_v1_css_path);
    expect(cssResponse.status()).toBe(200);
    expect(await cssResponse.text()).toContain('rgb(18,52,86)'); // v1's own marker color

    const jsResponse = await request.get(fx.asset_v1_js_path);
    expect(jsResponse.status()).toBe(200);
    expect(await jsResponse.text()).toContain('v1');

    // Observable CSS effect from the real served file.
    const markerColor = await page.evaluate(() => getComputedStyle(document.body, '::after').backgroundColor);
    expect(markerColor).toBe('rgb(18, 52, 86)');

    // Safe JS effect from the real served file.
    await expect(page.locator('body')).toHaveAttribute('data-e2e-ext-js-loaded', 'v1');
  });

  test('B. The newer 2.0.0 release asset exists independently, with its own distinct content, unaffected by 1.0.0', async ({ request }) => {
    const cssResponse = await request.get(fx.asset_v2_css_path);
    expect(cssResponse.status()).toBe(200);
    const cssText = await cssResponse.text();
    expect(cssText).toContain('rgb(220,38,38)'); // v2's own, different marker color
    expect(cssText).not.toContain('rgb(18,52,86)'); // never v1's color

    const jsResponse = await request.get(fx.asset_v2_js_path);
    expect(jsResponse.status()).toBe(200);
    expect(await jsResponse.text()).toContain('v2');
  });
});
