import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const root = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart';
const phase = process.env.FOODMART_QA_PHASE || 'phase2c';
const outputRoot = path.join(root, phase);
const baseUrl = process.env.FOODMART_QA_URL || process.env.FOODMART_PHASE2C_URL || 'http://127.0.0.1:8897/index.html';
const interactionsOnly = process.env.FOODMART_INTERACTIONS_ONLY === '1';
const allViewports = [
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'breakpoint-991', width: 991, height: 900 },
  { name: 'desktop-1200', width: 1200, height: 900 },
  { name: 'large-desktop-1440', width: 1440, height: 900 },
];
const requestedViewports = process.env.FOODMART_VIEWPORTS?.split(',').map(value => Number(value));
const viewports = requestedViewports?.length ? allViewports.filter(viewport => requestedViewports.includes(viewport.width)) : allViewports;
const sections = [
  ['header', 'header'],
  ['hero', '.banner-blocks'],
  ['category-carousel', '.category-carousel'],
  ['brand-carousel', '.brand-carousel'],
  ['trending-products', '.product-tabs, .bootstrap-tabs'],
  ['promo-banners', 'section:has-text("Luxa")'],
  ['best-selling', '.products-carousel >> nth=0'],
  ['newsletter', 'section:has-text("25% Discount"), section:has-text("Discount")'],
  ['most-popular', '.products-carousel >> nth=1'],
  ['just-arrived', '.products-carousel >> nth=2'],
  ['blog', '#latest-blog'],
  ['app-promo', 'section:has-text("foodmart App"), section:has-text("Shop faster")'],
  ['trust-badges', 'section:has-text("Free delivery")'],
  ['footer', 'footer'],
];

const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
});
const summary = { baseUrl, capturedAt: new Date().toISOString(), viewports: [], interactions: [] };

async function settle(page) {
  await page.goto(baseUrl, { waitUntil: 'networkidle', timeout: 60000 });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(1000);
}

try {
  for (const viewport of viewports) {
    if (interactionsOnly) {
      summary.viewports.push(JSON.parse(await fs.readFile(path.join(outputRoot, viewport.name, 'technical-baseline.json'), 'utf8')));
      continue;
    }
    const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    const consoleErrors = [];
    const consoleWarnings = [];
    const pageErrors = [];
    const failedRequests = [];
    const httpErrorResponses = [];
    const requests = [];
    page.on('console', message => {
      if (message.type() === 'error') consoleErrors.push({ text: message.text(), location: message.location() });
      if (message.type() === 'warning') consoleWarnings.push(message.text());
    });
    page.on('pageerror', error => pageErrors.push(String(error)));
    page.on('requestfailed', request => failedRequests.push({ url: request.url(), error: request.failure()?.errorText || null }));
    page.on('request', request => requests.push(request.url()));
    page.on('response', response => { if (response.status() >= 400) httpErrorResponses.push({ url: response.url(), status: response.status() }); });

    let navError = null;
    try { await settle(page); } catch (error) { navError = String(error); }
    const viewportDir = path.join(outputRoot, viewport.name);
    await fs.mkdir(path.join(viewportDir, 'sections'), { recursive: true });
    await page.screenshot({ path: path.join(viewportDir, 'full-page.png'), fullPage: true, animations: 'disabled' });
    await page.locator('.canvas-skip-link').evaluate(element => { element.style.display = 'none'; });
    const sectionResults = [];
    for (const [key, selector] of sections) {
      const locator = page.locator(selector).first();
      const found = await locator.count() > 0;
      const result = { key, selector, found };
      if (found) {
        const box = await locator.boundingBox();
        result.boundingBox = box;
        await locator.screenshot({ path: path.join(viewportDir, 'sections', `${key}.png`), animations: 'disabled' });
      }
      sectionResults.push(result);
    }
    const documentDimensions = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      scrollHeight: document.documentElement.scrollHeight,
      clientWidth: document.documentElement.clientWidth,
      clientHeight: document.documentElement.clientHeight,
      lang: document.documentElement.lang,
      dir: document.documentElement.dir,
      platformHeaderCount: document.querySelectorAll('.canvas-site-header').length,
      platformFooterCount: document.querySelectorAll('.canvas-site-footer').length,
    }));
    const technical = { viewport, navError, documentDimensions, sections: sectionResults, console: { errors: consoleErrors, warnings: consoleWarnings }, pageErrors, network: { requests, failedRequests, httpErrorResponses } };
    await fs.writeFile(path.join(viewportDir, 'technical-baseline.json'), JSON.stringify(technical, null, 2));
    summary.viewports.push(technical);
    await context.close();
  }

  const desktop = await browser.newPage({ viewport: { width: 1200, height: 900 } });
  await settle(desktop);
  const desktopCart = desktop.locator('[data-bs-target="#offcanvasCart"]:visible').first();
  await desktopCart.click();
  await desktop.locator('#offcanvasCart').waitFor({ state: 'visible' });
  await desktop.waitForTimeout(500);
  summary.interactions.push({ test: 'desktop-cart', passed: await desktop.locator('#offcanvasCart').evaluate(el => el.classList.contains('show')) });
  await desktop.locator('#offcanvasCart .btn-close').click();
  await desktop.waitForTimeout(500);
  const quantity = desktop.locator('.product-qty').first();
  const input = quantity.locator('input').first();
  const beforePlus = await input.inputValue();
  await quantity.locator('.quantity-right-plus').click();
  const afterPlus = await input.inputValue();
  await quantity.locator('.quantity-left-minus').click();
  const afterMinus = await input.inputValue();
  summary.interactions.push({ test: 'quantity-plus-minus', beforePlus, afterPlus, afterMinus, passed: Number(afterPlus) === Number(beforePlus) + 1 && afterMinus === beforePlus });
  const fruits = desktop.locator('#nav-fruits-tab');
  await fruits.click();
  summary.interactions.push({ test: 'bootstrap-tabs', passed: await fruits.evaluate(el => el.classList.contains('active') && el.getAttribute('aria-selected') === 'true') });
  const swiper = desktop.locator('.category-carousel .swiper-wrapper').first();
  const beforeTransform = await swiper.evaluate(el => getComputedStyle(el).transform);
  await desktop.locator('.category-carousel-next').first().click();
  await desktop.waitForTimeout(500);
  const afterTransform = await swiper.evaluate(el => getComputedStyle(el).transform);
  summary.interactions.push({ test: 'category-swiper', beforeTransform, afterTransform, passed: beforeTransform !== afterTransform });
  await desktop.close();

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await settle(mobile);
  for (const [test, trigger, panel] of [
    ['mobile-cart', '[data-bs-target="#offcanvasCart"]', '#offcanvasCart'],
    ['mobile-search', '[data-bs-target="#offcanvasSearch"]', '#offcanvasSearch'],
    ['mobile-nav', '[data-bs-target="#offcanvasNavbar"]', '#offcanvasNavbar'],
  ]) {
    await mobile.locator(`${trigger}:visible`).first().click();
    await mobile.locator(panel).waitFor({ state: 'visible' });
    await mobile.waitForTimeout(500);
    const passed = await mobile.locator(panel).evaluate(el => el.classList.contains('show'));
    summary.interactions.push({ test, passed });
    await mobile.locator(`${panel} .btn-close`).click();
    await mobile.waitForTimeout(300);
  }
  await mobile.close();
  await fs.writeFile(path.join(outputRoot, 'capture-summary.json'), JSON.stringify(summary, null, 2));
} finally {
  await browser.close();
}

console.log(JSON.stringify({ viewports: summary.viewports.length, interactions: summary.interactions }, null, 2));
