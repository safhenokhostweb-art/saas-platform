import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';

const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const selectors = [
  'header', 'header .container-fluid:first-of-type', 'header .row:first-of-type',
  'header .main-logo', 'header .search-bar', 'header .support-box', 'header .cart',
  'header .support-box span', 'header .support-box h5', 'header .cart span:first-child', 'header .cart span:last-child',
  'section:has-text("Free delivery")', 'section:has-text("Free delivery") .row:first-of-type',
  'section:has-text("Free delivery") .row:first-of-type > div:nth-child(1)',
  'section:has-text("Free delivery") .row:first-of-type > div:nth-child(2)',
  'section:has-text("Free delivery") .row:first-of-type > div:nth-child(3)',
  'footer', 'footer .row:first-of-type', 'footer .footer-menu',
  'footer .row:first-of-type > div:nth-child(1)', 'footer .row:first-of-type > div:nth-child(2)',
  'footer .row:first-of-type > div:nth-child(3)', 'footer .row:first-of-type > div:nth-child(4)',
];

async function inspect(url, width) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  await page.goto(url, { waitUntil: 'load', timeout: 60000 });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(1000);
  const result = {};
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (!await locator.count()) { result[selector] = null; continue; }
    result[selector] = await locator.evaluate(element => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return {
        rect: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
        display: style.display, direction: style.direction, fontFamily: style.fontFamily,
        fontSize: style.fontSize, lineHeight: style.lineHeight, textAlign: style.textAlign,
        margin: style.margin, padding: style.padding, gap: style.gap,
      };
    });
  }
  await page.close();
  return result;
}

const output = {};
const widths = process.env.FOODMART_DIAG_WIDTHS ? process.env.FOODMART_DIAG_WIDTHS.split(',').map(Number) : [390, 768, 991, 1200, 1440];
for (const width of widths) {
  output[width] = {
    baseline: await inspect('http://127.0.0.1:8899/index.html', width),
    phase2c: await inspect('http://127.0.0.1:8897/index.html', width),
  };
}
await browser.close();
await fs.writeFile('storage/app/theme-baselines/foodmart/phase2c/style-diagnosis.json', JSON.stringify(output, null, 2));
console.log('wrote style-diagnosis.json');
