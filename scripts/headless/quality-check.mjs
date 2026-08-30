/**
 * Headless Quality-Check Extractor — Phase 2g.
 *
 * Deliberately separate from extract.mjs (the Visual RE competitor-scraping
 * engine — framework fingerprinting, component extraction, colors/typography
 * for cloning a THIRD-PARTY site). This script answers a different question:
 * "does OUR OWN Theme render correctly, right now, in a real browser?" — one
 * navigation, one viewport, real evidence only. No fabricated results: every
 * field is either a real browser-observed fact or explicitly absent.
 *
 * Usage:
 *   node quality-check.mjs <url> [timeout_ms] [viewport]
 *   viewport: desktop (default) | tablet | mobile
 *
 * Output (stdout, JSON): see buildResult() below.
 * Errors (stderr, JSON) + exit code 1: navigation/launch failure.
 */

import { chromium } from 'playwright';
import { existsSync } from 'fs';
import os from 'os';

const url       = process.argv[2];
const timeoutMs = parseInt(process.argv[3] || '30000');
const viewportName = (process.argv[4] || 'desktop').toLowerCase();

if (!url) {
  process.stderr.write(JSON.stringify({ success: false, error: 'URL is required as first argument' }));
  process.exit(1);
}

const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  tablet:  { width: 768,  height: 1024 },
  mobile:  { width: 390,  height: 844 },
};
const viewport = VIEWPORTS[viewportName] ?? VIEWPORTS.desktop;

// Cap how many individual entries of each evidence type are returned — real
// evidence, just bounded so a pathological page can't produce an unbounded
// payload. The *counts* below are always the true totals even when the
// detail lists are truncated.
const MAX_DETAIL_ENTRIES = 20;
// Cap how many DOM nodes are sampled for contrast — a real, bounded sample,
// never a claim of exhaustive coverage.
const MAX_CONTRAST_SAMPLES = 80;

function findSystemChrome() {
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
    return process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  }
  const platform = os.platform();
  if (platform === 'win32') {
    const candidates = [
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
      `${process.env.LOCALAPPDATA}\\Google\\Chrome\\Application\\chrome.exe`,
    ];
    for (const c of candidates) if (c && existsSync(c)) return c;
  }
  if (platform === 'linux') {
    const candidates = ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium-browser', '/usr/bin/chromium'];
    for (const c of candidates) if (existsSync(c)) return c;
  }
  if (platform === 'darwin') {
    const candidates = ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/Applications/Chromium.app/Contents/MacOS/Chromium'];
    for (const c of candidates) if (existsSync(c)) return c;
  }
  return null;
}

/**
 * Real WCAG 2.x relative-luminance + contrast-ratio formulas — no
 * approximation. rgb components are 0-255.
 */
function relativeLuminance([r, g, b]) {
  const srgb = [r, g, b].map((c) => {
    const v = c / 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * srgb[0] + 0.7152 * srgb[1] + 0.0722 * srgb[2];
}

function contrastRatio(rgbA, rgbB) {
  const lA = relativeLuminance(rgbA);
  const lB = relativeLuminance(rgbB);
  const lighter = Math.max(lA, lB);
  const darker = Math.min(lA, lB);
  return (lighter + 0.05) / (darker + 0.05);
}

async function runQualityCheck() {
  const systemChrome = findSystemChrome();
  const launchOptions = {
    headless: true,
    args: [
      '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
      '--disable-gpu', '--no-first-run', '--disable-extensions',
    ],
  };
  if (systemChrome) launchOptions.executablePath = systemChrome;

  const browser = await chromium.launch(launchOptions);
  const context = await browser.newContext({
    viewport,
    ignoreHTTPSErrors: true,
    javaScriptEnabled: true,
  });
  const page = await context.newPage();

  const consoleErrors = [];
  const resourceFailures = [];
  const pageErrors = [];

  page.on('console', (msg) => {
    const type = msg.type();
    if (type === 'error' || type === 'warning') {
      consoleErrors.push({ level: type, text: msg.text().slice(0, 500) });
    }
  });

  page.on('pageerror', (err) => {
    pageErrors.push({ message: String(err?.message ?? err).slice(0, 500) });
  });

  page.on('requestfailed', (request) => {
    const failure = request.failure();
    resourceFailures.push({
      url: request.url().slice(0, 500),
      resourceType: request.resourceType(),
      error: failure ? failure.errorText : 'unknown',
      // Same-origin vs cross-origin matters for severity policy — computed
      // in PHP from this raw fact, not decided here.
      sameOriginAsTarget: (() => {
        try { return new URL(request.url()).origin === new URL(url).origin; } catch { return null; }
      })(),
    });
  });

  let navigation = { ok: false, error: null, status: null };
  try {
    const response = await page.goto(url, { waitUntil: 'networkidle', timeout: timeoutMs });
    navigation.status = response ? response.status() : null;
    // A reachable-but-erroring page (404/500/...) is a real render failure,
    // not a success — never treat "the browser got a response" as "the
    // theme's page actually rendered."
    navigation.ok = navigation.status === null || navigation.status < 400;
    if (!navigation.ok) navigation.error = `HTTP ${navigation.status}`;
  } catch (err) {
    // One honest retry with a looser wait condition before declaring the
    // navigation a real failure — matches extract.mjs's own tolerance for
    // slow-but-working pages, not a way to hide a genuine failure.
    try {
      const response = await page.goto(url, { waitUntil: 'load', timeout: timeoutMs });
      await page.waitForTimeout(1000);
      navigation.status = response ? response.status() : null;
      navigation.ok = navigation.status === null || navigation.status < 400;
      if (!navigation.ok) navigation.error = `HTTP ${navigation.status}`;
    } catch (err2) {
      navigation.ok = false;
      navigation.error = String(err2?.message ?? err2).slice(0, 500);
    }
  }

  let overflow = { measured: false };
  let domRootPresent = false;
  let bodyContentLength = 0;
  let screenshotB64 = '';
  let contrast = { sampled: 0, checked: 0, passed: 0, failed: 0, unverified: 0, failures: [] };

  if (navigation.ok) {
    try {
      const dims = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        bodyExists: !!document.body,
        bodyContentLength: document.body ? document.body.innerHTML.trim().length : 0,
      }));
      overflow = {
        measured: true,
        scrollWidth: dims.scrollWidth,
        clientWidth: dims.clientWidth,
        hasOverflow: dims.scrollWidth > dims.clientWidth + 1, // +1: sub-pixel rounding tolerance
        overflowPx: Math.max(0, dims.scrollWidth - dims.clientWidth),
      };
      domRootPresent = dims.bodyExists;
      bodyContentLength = dims.bodyContentLength;
    } catch { /* leave overflow.measured = false — honestly unmeasured, not fabricated as clean */ }

    try {
      const shot = await page.screenshot({ type: 'png', fullPage: true, timeout: 15000 });
      screenshotB64 = shot.toString('base64');
    } catch { /* non-fatal — screenshot absence is reported via empty string */ }

    try {
      contrast = await page.evaluate(({ maxSamples }) => {
        function parseRgb(colorStr) {
          const m = colorStr.match(/rgba?\(([^)]+)\)/);
          if (!m) return null;
          const parts = m[1].split(',').map((s) => parseFloat(s.trim()));
          if (parts.length < 3 || parts.some((n) => Number.isNaN(n))) return null;
          const alpha = parts.length >= 4 ? parts[3] : 1;
          return { rgb: [parts[0], parts[1], parts[2]], alpha };
        }

        // Walk up the ancestor chain for the first element with a real,
        // fully-opaque solid background color and no background-image —
        // anything else (transparent all the way up, any bg-image/gradient
        // layer in between) is honestly unresolvable, never guessed.
        function resolveEffectiveBackground(el) {
          let node = el;
          while (node && node !== document.documentElement.parentElement) {
            const style = getComputedStyle(node);
            if (style.backgroundImage && style.backgroundImage !== 'none') {
              return { unresolvable: true, reason: 'background-image/gradient present' };
            }
            const bg = parseRgb(style.backgroundColor);
            if (bg && bg.alpha >= 0.99) {
              return { unresolvable: false, rgb: bg.rgb };
            }
            if (bg && bg.alpha > 0 && bg.alpha < 0.99) {
              return { unresolvable: true, reason: 'partially transparent background (compositing)' };
            }
            node = node.parentElement;
          }
          return { unresolvable: true, reason: 'no opaque background found up to <html>' };
        }

        const selector = 'h1,h2,h3,h4,h5,h6,p,a,span,button,label,li,td,th,figcaption';
        const nodes = Array.from(document.querySelectorAll(selector));
        let sampled = 0, checked = 0, passed = 0, failed = 0, unverified = 0;
        const failures = [];

        for (const el of nodes) {
          if (sampled >= maxSamples) break;
          const text = (el.textContent || '').trim();
          if (text.length < 2) continue;
          const rect = el.getBoundingClientRect();
          if (rect.width === 0 || rect.height === 0) continue; // not actually visible
          const style = getComputedStyle(el);
          if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0) continue;

          sampled++;
          const fg = parseRgb(style.color);
          if (!fg || fg.alpha < 0.99) {
            unverified++;
            continue;
          }
          const bg = resolveEffectiveBackground(el);
          if (bg.unresolvable) {
            unverified++;
            continue;
          }

          checked++;
          const fontSize = parseFloat(style.fontSize) || 16;
          const fontWeight = parseInt(style.fontWeight, 10) || 400;
          const isLargeText = fontSize >= 24 || (fontSize >= 18.66 && fontWeight >= 700);
          const threshold = isLargeText ? 3.0 : 4.5;

          // Compute contrast ratio inline (duplicated formula — this closure
          // runs inside the browser page context, isolated from the Node
          // process's own contrastRatio()/relativeLuminance() helpers above).
          function luminance([r, g, b]) {
            const s = [r, g, b].map((c) => {
              const v = c / 255;
              return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * s[0] + 0.7152 * s[1] + 0.0722 * s[2];
          }
          const lA = luminance(fg.rgb);
          const lB = luminance(bg.rgb);
          const ratio = (Math.max(lA, lB) + 0.05) / (Math.min(lA, lB) + 0.05);

          if (ratio >= threshold) {
            passed++;
          } else {
            failed++;
            if (failures.length < 15) {
              failures.push({
                tag: el.tagName.toLowerCase(),
                text: text.slice(0, 80),
                fg: `rgb(${fg.rgb.map(Math.round).join(',')})`,
                bg: `rgb(${bg.rgb.map(Math.round).join(',')})`,
                ratio: Math.round(ratio * 100) / 100,
                required: threshold,
                isLargeText,
              });
            }
          }
        }

        return { sampled, checked, passed, failed, unverified, failures };
      }, { maxSamples: MAX_CONTRAST_SAMPLES });
    } catch (e) {
      contrast = { sampled: 0, checked: 0, passed: 0, failed: 0, unverified: 0, failures: [], error: String(e?.message ?? e).slice(0, 300) };
    }
  }

  await browser.close();

  return {
    success: true,
    viewport: viewportName,
    viewport_dimensions: viewport,
    navigation,
    dom: { rootPresent: domRootPresent, bodyContentLength },
    overflow,
    console_errors: {
      total: consoleErrors.length,
      entries: consoleErrors.slice(0, MAX_DETAIL_ENTRIES),
    },
    page_errors: {
      total: pageErrors.length,
      entries: pageErrors.slice(0, MAX_DETAIL_ENTRIES),
    },
    resource_failures: {
      total: resourceFailures.length,
      entries: resourceFailures.slice(0, MAX_DETAIL_ENTRIES),
    },
    contrast,
    screenshot_base64: screenshotB64,
  };
}

(async () => {
  try {
    const result = await runQualityCheck();
    process.stdout.write(JSON.stringify(result));
    process.exit(0);
  } catch (err) {
    process.stderr.write(JSON.stringify({ success: false, error: String(err?.message ?? err) }));
    process.exit(1);
  }
})();
