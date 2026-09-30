import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';

const outDir = 'storage/app/theme-baselines/foodmart/phase2d';
const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const properties = [
  'display', 'position', 'box-sizing', 'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height',
  'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'border-radius',
  'font-family', 'font-size', 'font-weight', 'line-height', 'color', 'background-color', 'vertical-align',
  'text-align', 'text-transform', 'list-style-type', 'overflow', 'overflow-x', 'overflow-y', 'gap', 'flex-shrink',
];
const vendorDir = `${outDir}/vendor`;
const localResponses = new Map([
  ['https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css', ['text/css', await fs.readFile(`${vendorDir}/swiper-bundle.min.css`)]],
  ['https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js', ['text/javascript', await fs.readFile(`${vendorDir}/swiper-bundle.min.js`)]],
  ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css', ['text/css', await fs.readFile(`${vendorDir}/bootstrap-5.3.0-alpha3.min.css`)]],
  ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js', ['text/javascript', await fs.readFile(`${vendorDir}/bootstrap.bundle.min.js`)]],
  ['https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap', ['text/css', await fs.readFile(`${vendorDir}/google-fonts.css`)]],
]);

async function snapshot(width, omitPlatformCss) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  await page.route('**/*', async route => {
    const url = route.request().url();
    if (omitPlatformCss && url.endsWith('/assets/app.css')) {
      await route.fulfill({ status: 200, contentType: 'text/css', body: '' });
      return;
    }
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
  const data = await page.evaluate(properties => {
    function pathFor(element) {
      const parts = [];
      for (let node = element; node && node.nodeType === 1; node = node.parentElement) {
        let part = node.tagName.toLowerCase();
        if (node.id) part += `#${node.id}`;
        else {
          const classes = [...node.classList].slice(0, 3);
          if (classes.length) part += `.${classes.join('.')}`;
          if (node.parentElement) part += `:nth-child(${[...node.parentElement.children].indexOf(node) + 1})`;
        }
        parts.unshift(part);
        if (parts.length === 5) break;
      }
      return parts.join(' > ');
    }

    function flattenRules(rules, out = []) {
      for (const rule of rules) {
        if (rule.selectorText) out.push(rule);
        if (rule.cssRules) flattenRules(rule.cssRules, out);
      }
      return out;
    }

    const platformSheet = [...document.styleSheets].find(sheet => sheet.href?.endsWith('/assets/app.css'));
    const platformRules = platformSheet ? flattenRules(platformSheet.cssRules) : [];
    return [...document.querySelectorAll('*')].map((element, index) => {
      const computed = getComputedStyle(element);
      const values = Object.fromEntries(properties.map(property => [property, computed.getPropertyValue(property)]));
      const rect = element.getBoundingClientRect();
      const matchedPlatformRules = platformRules.flatMap(rule => {
        try {
          if (!element.matches(rule.selectorText)) return [];
        } catch { return []; }
        const declarations = {};
        for (const property of properties) {
          const value = rule.style.getPropertyValue(property);
          if (value) declarations[property] = { value, priority: rule.style.getPropertyPriority(property) };
        }
        return Object.keys(declarations).length ? [{ selector: rule.selectorText, declarations }] : [];
      });
      return { index, tag: element.tagName.toLowerCase(), path: pathFor(element), rect: { x: rect.x, y: rect.y, width: rect.width, height: rect.height }, values, matchedPlatformRules };
    });
  }, properties);
  await page.close();
  return data;
}

const result = {};
for (const width of [390, 768, 991, 1200, 1440]) {
  const current = await snapshot(width, false);
  const isolated = await snapshot(width, true);
  const changed = [];
  for (let index = 0; index < Math.min(current.length, isolated.length); index++) {
    const propertyChanges = {};
    for (const property of properties) {
      if (current[index].values[property] !== isolated[index].values[property]) {
        propertyChanges[property] = { withPlatformCss: current[index].values[property], isolated: isolated[index].values[property] };
      }
    }
    const rectA = current[index].rect;
    const rectB = isolated[index].rect;
    const rectChanged = ['x', 'y', 'width', 'height'].some(key => Math.abs(rectA[key] - rectB[key]) > 0.01);
    if (Object.keys(propertyChanges).length || rectChanged) {
      changed.push({ ...current[index], isolatedRect: rectB, propertyChanges });
    }
  }
  result[width] = { elementCount: current.length, changedCount: changed.length, changed };
}

await browser.close();
await fs.writeFile(`${outDir}/cascade-audit.json`, JSON.stringify(result, null, 2));
console.log(JSON.stringify(Object.fromEntries(Object.entries(result).map(([width, data]) => [width, { elementCount: data.elementCount, changedCount: data.changedCount }])), null, 2));
