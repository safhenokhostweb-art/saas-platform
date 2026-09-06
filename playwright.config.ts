import { defineConfig, devices } from '@playwright/test';

/**
 * Minimal, canonical Playwright harness for saas-platform.
 *
 * Chromium binary note: `npx playwright install chromium` could not reach
 * cdn.playwright.dev from this environment (network blocked). A Chromium
 * build (revision 1234, Chrome 141ish) was already cached locally at
 * %LOCALAPPDATA%\ms-playwright\chromium-1234 from an unrelated earlier
 * install (scripts/headless). We point Playwright directly at that cached
 * executable via `launchOptions.executablePath` below so tests can run
 * without a fresh download. If that cache ever gets cleared, re-run
 * `npx playwright install chromium` (needs network access) and remove the
 * PLAYWRIGHT_CHROME_PATH override.
 */
const CACHED_CHROME_PATH = process.env.PLAYWRIGHT_CHROME_PATH
  || 'C:\\Users\\Farshad-IT\\AppData\\Local\\ms-playwright\\chromium-1234\\chrome-win64\\chrome.exe';

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
    launchOptions: {
      executablePath: CACHED_CHROME_PATH,
    },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
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
