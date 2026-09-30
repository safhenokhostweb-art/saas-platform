/**
 * Phase 2A — FoodMart visual-regression capture at the accepted
 * representative widths (390 / 768 / 991 / 1200 / 1440).
 *
 * Serves the byte-verified FoodMart QA prototype (assembled by
 * FoodMartPrototypeRenderTest through the canonical shared renderer) with
 * the same local vendor/CDN fulfillment convention the phase2d harness
 * used, captures full-page PNGs per width, and writes a capture summary.
 *
 * Read-only with respect to the repository: writes only under
 * storage/app/theme-baselines/foodmart/phase2a-capture/.
 *
 * Usage: node scripts/headless/foodmart-phase2a-capture.mjs <outDirName>
 *   <outDirName> defaults to 'phase2a-capture'; pass a different name to
 *   capture a comparison set later.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const root = 'storage/app/theme-baselines/foodmart';
const outName = process.argv[2] ?? 'phase2a-capture';
const outDir = path.join(root, outName);
const widths = [390, 768, 991, 1200, 1440];
const vendorDir = path.join(root, 'phase2d/vendor');

const localResponses = new Map([
  ['https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css', ['text/css', await fs.readFile(`${vendorDir}/swiper-bundle.min.css`)]],
  ['https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', ['text/javascript', await fs.readFile(`${vendorDir}/swiper-bundle.min.js`)]],
  ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css', ['text/css', await fs.readFile(`${vendorDir}/bootstrap-5.3.0-alpha3.min.css`)]],
  ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js', ['text/javascript', await fs.readFile(`${vendorDir}/bootstrap.bundle.min.js`)]],
  ['https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap', ['text/css', await fs.readFile(`${vendorDir}/google-fonts.css`)]],
]);

const browser = await chromium.launch({
  headless: true,
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
});

const summary = { outName, widths: [], capturedAt: new Date().toISOString() };

for (const width of widths) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  await page.route('**/*', async (route) => {
    const url = route.request().url();
    const local = localResponses.get(url);
    if (local) {
      await route.fulfill({ status: 200, contentType: local[0], body: local[1] });
      return;
    }
    await route.continue();
  });
  await page.goto('http://127.0.0.1:8897/index.html', { waitUntil: 'load', timeout: 30000 });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(1000);
  const file = path.join(outDir, `w${width}.png`);
  await fs.mkdir(outDir, { recursive: true });
  await page.screenshot({ path: file, fullPage: true });
  summary.widths.push({ width, file: file.replace(/\\/g, '/') });
  await page.close();
}

await browser.close();
await fs.writeFile(path.join(outDir, 'capture-summary.json'), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
