import { defineConfig, devices } from '@playwright/test';

/**
 * Minimal, canonical Playwright harness for saas-platform.
 *
 * Uses Playwright's own standard, machine-portable browser resolution by
 * default (`npx playwright install chromium`). No committed path may point
 * at a specific developer's machine. If a machine genuinely needs a
 * pre-existing Chrome/Chromium binary instead of Playwright's managed
 * install, set PLAYWRIGHT_CHROME_PATH — its absence uses Playwright's
 * standard browser, never a hardcoded fallback.
 */
const CACHED_CHROME_PATH = process.env.PLAYWRIGHT_CHROME_PATH || undefined;

export default defineConfig({
  testDir: './tests/browser',
  timeout: 60_000,
  expect: { timeout: 8_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    launchOptions: CACHED_CHROME_PATH ? { executablePath: CACHED_CHROME_PATH } : {},
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    },
  ],
  // We start `php artisan serve` ourselves (see tests/browser/README notes /
  // the task runner) because this app needs a real MySQL/Redis-backed dev
  // environment, not Playwright's generic webServer auto-start assumptions.
  // If you want Playwright to manage the server itself, uncomment below.
  // webServer: {
  //   command: 'php artisan serve --port=8000',
  //   url: 'http://127.0.0.1:8000',
  //   reuseExistingServer: true,
  // },
});
