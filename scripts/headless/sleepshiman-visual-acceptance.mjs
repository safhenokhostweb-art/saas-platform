import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const input = resolve(process.argv[2]);
const output = resolve(process.argv[3] || 'storage/app/theme-baselines/sleepshiman-home-blue');
mkdirSync(output, { recursive: true });
const systemBrowsers = process.platform === 'win32' ? [
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
] : [];
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || systemBrowsers.find(existsSync);
const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
try {
  const page = await browser.newPage({ viewport: { width: 1370, height: 900 }, deviceScaleFactor: 1 });
  await page.goto(pathToFileURL(input).href, { waitUntil: 'networkidle' });
  for (const [name, width, height] of [['desktop', 1370, 900], ['tablet', 768, 900], ['mobile', 390, 844]]) {
    await page.setViewportSize({ width, height });
    await page.locator('img').evaluateAll((images) => images.forEach((candidate) => {
      candidate.loading = 'eager';
    }));
    await page.evaluate(async () => {
      for (let y = 0; y < document.documentElement.scrollHeight; y += Math.max(320, window.innerHeight * 0.75)) {
        window.scrollTo(0, y);
        await new Promise((resolveScroll) => setTimeout(resolveScroll, 35));
      }
      window.scrollTo(0, 0);
    });
    await page.waitForFunction(() => [...document.images].every((candidate) => candidate.complete), null, { timeout: 10_000 });
    await page.waitForTimeout(250);
    const brokenImages = await page.locator('img').evaluateAll((images) => images
      .filter((candidate) => !candidate.complete || candidate.naturalWidth === 0)
      .map((candidate) => candidate.getAttribute('src')));
    if (brokenImages.length > 0) throw new Error(`${name}: broken images: ${brokenImages.join(', ')}`);
    await page.screenshot({ path: resolve(output, `${name}.png`), fullPage: true });
    const horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (horizontalOverflow) throw new Error(`${name}: horizontal overflow`);
  }
  const firstTrack = page.locator('[data-ss-carousel]').first();
  const target = `#${await firstTrack.getAttribute('id')}`;
  const controls = page.locator(`[data-ss-scroll="${target}"]`);
  if (await controls.count() !== 2) throw new Error('carousel: missing scoped controls');
  const before = await firstTrack.evaluate((track) => track.scrollLeft);
  await controls.first().click();
  await page.waitForTimeout(500);
  let after = await firstTrack.evaluate((track) => track.scrollLeft);
  if (after === before) {
    await controls.last().click();
    await page.waitForTimeout(500);
    after = await firstTrack.evaluate((track) => track.scrollLeft);
  }
  if (after === before) throw new Error('carousel: controls did not move the track');
} finally {
  await browser.close();
}
