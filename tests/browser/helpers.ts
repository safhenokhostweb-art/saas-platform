import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Page } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export interface E2EFixtures {
  admin_user_id: number;
  admin_mobile: string;
  admin_password: string;
  extension_id: number;
  extension_identifier: string;
  release_id: number;
  release_version: string;
  theme_id: number;
  theme_slug: string;
  asset_theme_id: number;
  asset_theme_slug: string;
  asset_extension_identifier: string;
  asset_release_v1_id: number;
  asset_release_v2_id: number;
  asset_v1_css_path: string;
  asset_v1_js_path: string;
  asset_v2_css_path: string;
  asset_v2_js_path: string;
}

/**
 * Loads the fixture IDs written by `php artisan e2e:seed-canvas-fixtures`
 * (see tests/browser/fixtures/e2e-fixtures.json). Strips a possible UTF-8
 * BOM — the fixture file is regenerated via PowerShell's `Out-File -Encoding
 * utf8`, which prepends one.
 */
export function loadFixtures(): E2EFixtures {
  const file = path.join(__dirname, 'fixtures', 'e2e-fixtures.json');
  let raw = fs.readFileSync(file, 'utf8');
  if (raw.charCodeAt(0) === 0xfeff) raw = raw.slice(1);
  return JSON.parse(raw);
}

/** Logs in through the real mobile+password login form as the seeded admin. */
export async function loginAsAdmin(page: Page, fx: E2EFixtures): Promise<void> {
  await page.goto('/login');
  await page.locator('#login-mobile').fill(fx.admin_mobile);
  await page.locator('#login-password').fill(fx.admin_password);
  await page.locator('button[type=submit]').click();
  await page.waitForURL(/\/admin(\/|$)/, { timeout: 15_000 });
}

export function canvasUrl(fx: E2EFixtures): string {
  return `/admin/theme-builder/${fx.theme_id}/canvas`;
}

export function assetThemeCanvasUrl(fx: E2EFixtures): string {
  return `/admin/theme-builder/${fx.asset_theme_id}/canvas`;
}

export function assetThemeLivePreviewUrl(fx: E2EFixtures, page: string = 'home'): string {
  return `/admin/theme-builder/${fx.asset_theme_id}/canvas/live-preview?page=${page}`;
}

/**
 * Attribute selectors scoping to the Canvas's left-panel "Blocks" (palette)
 * vs. "Structure" (tree) tab containers. Needed because several labels
 * (a block's name, a category name) legitimately appear more than once in
 * the DOM at the same time — in the palette, in the (hidden) "add child"
 * modal grid, and in the structure tree once a block has been authored —
 * so plain `getByText()`/`getByRole()` without this scoping is ambiguous
 * (Playwright strict-mode violation) or, worse, silently resolves to the
 * wrong (hidden) element depending on incidental DOM order.
 */
export const PALETTE_PANEL = "div[x-show=\"leftPanelTab==='blocks'\"]";
export const TREE_PANEL = "div[x-show=\"leftPanelTab==='tree'\"]";
