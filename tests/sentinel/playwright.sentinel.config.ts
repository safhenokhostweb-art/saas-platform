import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { sentinelEnv } from './support/env';

/**
 * ADR-077 — the Sentinel UI verifier suite. Read-only: it only loads pages.
 *
 *   BASE_URL=https://safhenokhost.ir npx playwright test -c tests/sentinel/playwright.sentinel.config.ts
 *
 * Every option is an environment variable (support/env.ts). The workspace
 * agent `sentinel-ui-verifier` (../agents) runs this with its own output
 * directory and seals report.json as a `sentinel-ui` artifact.
 */
const env = sentinelEnv();
const here = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  testDir: here,
  testMatch: /.*\.sentinel\.ts$/,
  outputDir: path.join(env.outputDir, 'playwright'),
  snapshotPathTemplate: `${here}/__baselines__/${env.host}/{arg}{ext}`,
  fullyParallel: true,
  workers: env.workers,
  retries: 0,
  timeout: env.navTimeout * 3,
  globalTimeout: env.globalTimeout,
  updateSnapshots: env.updateBaselines ? 'all' : 'none',
  reporter: [['line'], [path.join(here, 'support/sentinel-reporter.ts')]],
  use: {
    baseURL: env.baseUrl,
    ignoreHTTPSErrors: env.ignoreHttpsErrors,
    locale: 'fa-IR',
    navigationTimeout: env.navTimeout,
    trace: 'off',
    screenshot: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROME_PATH || undefined } },
    },
  ],
});
