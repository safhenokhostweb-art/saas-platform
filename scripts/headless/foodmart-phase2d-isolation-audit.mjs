import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';

const outDir = 'storage/app/theme-baselines/foodmart/phase2d';
await fs.mkdir(outDir, { recursive: true });

const browser = await chromium.launch({
  headless: true,
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
});

const widths = [390, 768, 991, 1200, 1440];
const results = {};
const vendorDir = `${outDir}/vendor`;
const localResponses = new Map([
  ['https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css', ['text/css', await fs.readFile(`${vendorDir}/swiper-bundle.min.css`)]],
  ['https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', ['text/javascript', await fs.readFile(`${vendorDir}/swiper-bundle.min.js`)]],
  ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css', ['text/css', await fs.readFile(`${vendorDir}/bootstrap-5.3.0-alpha3.min.css`)]],
  ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js', ['text/javascript', await fs.readFile(`${vendorDir}/bootstrap.bundle.min.js`)]],
  ['https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap', ['text/css', await fs.readFile(`${vendorDir}/google-fonts.css`)]],
]);

async function capture(name, url, width, omitPlatformCss = false) {
  const page = await browser.newPage({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('console', message => {
    if (message.type() === 'error') errors.push(`console: ${message.text()}`);
  });
  page.on('pageerror', error => errors.push(`pageerror: ${error.message}`));
  page.on('requestfailed', request => errors.push(`requestfailed: ${request.url()} ${request.failure()?.errorText ?? ''}`));

  await page.route('**/*', async route => {
    const local = localResponses.get(route.request().url());
    if (local) {
      await route.fulfill({ status: 200, contentType: local[0], body: local[1] });
      return;
    }
    await route.continue();
  });

  if (omitPlatformCss) {
    await page.route('**/assets/app.css', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
  }

  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.evaluate(() => Promise.race([
    document.fonts.ready,
    new Promise(resolve => setTimeout(resolve, 3000)),
  ]));
  await page.addStyleTag({ content: '*,*::before,*::after{animation-duration:0s!important;animation-delay:0s!important;transition:none!important;caret-color:transparent!important}' });
  await page.waitForTimeout(1000);
  const metrics = await page.evaluate(() => ({
    width: document.documentElement.scrollWidth,
    height: document.documentElement.scrollHeight,
    bodyWidth: document.body.scrollWidth,
    bodyHeight: document.body.scrollHeight,
  }));
  await page.screenshot({ path: `${outDir}/${name}-${width}.png`, fullPage: true, animations: 'disabled' });
  await page.close();
  return { ...metrics, errors };
}

for (const width of widths) {
  results[width] = {
    baseline: await capture('baseline', 'http://127.0.0.1:8899/index.html', width),
    current: await capture('current', 'http://127.0.0.1:8897/index.html', width),
    isolatedCss: await capture('isolated-css', 'http://127.0.0.1:8897/index.html', width, true),
  };
}

await browser.close();
await fs.writeFile(`${outDir}/isolation-audit.json`, JSON.stringify(results, null, 2));
console.log(JSON.stringify(results, null, 2));
