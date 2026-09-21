/**
 * Real interactive Dark Mode verification (khab-shimana-tablet
 * certification, 2026-09-21) — not a static capture. Navigates, clicks the
 * real .tb-theme-toggle button (window.__tbTheme.cycle(), same code path a
 * real visitor uses), reads the real [data-theme] attribute the click
 * produced, reloads the page and confirms the choice survived (localStorage
 * persistence), cycles back to light and confirms that too. Sibling script
 * to quality-check.mjs — same Playwright install, same system-Chrome
 * fallback, no changes to that file.
 *
 * Usage: node dark-mode-check.mjs <url> <viewport>
 * viewport: desktop | tablet | mobile
 * Output (stdout, JSON): { steps: [...], success: bool }
 */
import { chromium } from 'playwright';
import { existsSync } from 'fs';
import os from 'os';

const url = process.argv[2];
const viewportName = (process.argv[3] || 'desktop').toLowerCase();

const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 390, height: 844 },
};
const viewport = VIEWPORTS[viewportName] ?? VIEWPORTS.desktop;

function findSystemChrome() {
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) return process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  const candidates = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    `${process.env.LOCALAPPDATA}\\Google\\Chrome\\Application\\chrome.exe`,
  ];
  for (const c of candidates) if (c && existsSync(c)) return c;
  return null;
}

async function readThemeState(page) {
  return page.evaluate(() => ({
    dataTheme: document.documentElement.getAttribute('data-theme'),
    dataThemePref: document.documentElement.getAttribute('data-theme-pref'),
    localStorage: (() => { try { return localStorage.getItem('tb-theme'); } catch (e) { return null; } })(),
    headerBg: (() => { const h = document.querySelector('.tb-site-header'); return h ? getComputedStyle(h).backgroundColor : null; })(),
    bodyBg: getComputedStyle(document.body).backgroundColor,
    heroBg: (() => { const h = document.querySelector('.tb-hero-split, .tb-section'); return h ? getComputedStyle(h).backgroundColor : null; })(),
    trustBadgesBg: (() => { const h = document.querySelector('.tb-trust-badges'); return h ? getComputedStyle(h).backgroundColor : null; })(),
    trustBadgeCardBg: (() => { const h = document.querySelector('.tb-trust-badge'); return h ? getComputedStyle(h).backgroundColor : null; })(),
    trustBadgeTitleColor: (() => { const h = document.querySelector('.tb-trust-info-title'); return h ? getComputedStyle(h).color : null; })(),
  }));
}

const steps = [];
let success = true;

const chromePath = findSystemChrome();
const browser = await chromium.launch(chromePath ? { executablePath: chromePath } : {});
try {
  const context = await browser.newContext({ viewport, locale: 'fa-IR' });
  const page = await context.newPage();

  await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  const initial = await readThemeState(page);
  steps.push({ step: 'initial_load', ...initial });

  // Below the 768px breakpoint the desktop header (and its .tb-theme-toggle)
  // is display:none; a second toggle instance lives inside the hamburger
  // mobile-menu panel instead, only reachable once that panel is opened —
  // and a reload re-closes it, so this must be called again after every
  // reload, not just once up front.
  async function openMobileMenuIfPresent() {
    const hamburger = page.locator('#tb-mobile-menu-btn, .tb-menu-btn').first();
    if (await hamburger.count() && await hamburger.isVisible().catch(() => false)) {
      await hamburger.click();
      await page.waitForTimeout(200);
      return true;
    }
    return false;
  }

  const opened = await openMobileMenuIfPresent();
  if (opened) steps.push({ step: 'opened_mobile_menu' });

  const toggle = page.locator('.tb-theme-toggle:visible').first();
  const toggleCount = await toggle.count();
  if (toggleCount === 0) {
    steps.push({ step: 'toggle_not_found', note: 'No .tb-theme-toggle button found on the page.' });
    success = false;
  } else {
    const visible = await toggle.isVisible().catch(() => false);
    steps.push({ step: 'toggle_visibility', visible });
    if (!visible) {
      steps.push({ step: 'toggle_not_visible', note: 'The .tb-theme-toggle button exists in the DOM but is not visible/clickable at this viewport.' });
      success = false;
    } else {
      // cycle() order is fixed: light -> dark -> system -> light ... A fresh
      // context always starts at pref='system' (empty localStorage), so two
      // clicks are needed to reach an explicit 'dark' (system -> light -> dark).
      await toggle.click();
      await page.waitForTimeout(150);
      await toggle.click();
      await page.waitForTimeout(150);
      const afterClick2 = await readThemeState(page);
      steps.push({ step: 'after_click_2_dark', ...afterClick2 });
      if (afterClick2.dataTheme !== 'dark' || afterClick2.dataThemePref !== 'dark') success = false;

      const screenshotDark = await page.screenshot({ fullPage: false });
      steps.push({ step: 'screenshot_dark_pre_reload', screenshot_base64: screenshotDark.toString('base64') });

      await page.reload({ waitUntil: 'networkidle', timeout: 30000 });
      const afterReload = await readThemeState(page);
      steps.push({ step: 'after_reload', ...afterReload });
      if (afterReload.dataTheme !== 'dark' || afterReload.localStorage !== 'dark') success = false;

      const screenshotDarkReload = await page.screenshot({ fullPage: false });
      steps.push({ step: 'screenshot_dark_post_reload', screenshot_base64: screenshotDarkReload.toString('base64') });

      await openMobileMenuIfPresent();

      // dark -> system (3rd click) -> light (4th click), landing on explicit 'light'.
      const toggle2 = page.locator('.tb-theme-toggle:visible').first();
      await toggle2.click();
      await page.waitForTimeout(150);
      await toggle2.click();
      await page.waitForTimeout(150);
      const afterClick4 = await readThemeState(page);
      steps.push({ step: 'after_click_4_back_to_light', ...afterClick4 });
      if (afterClick4.dataThemePref !== 'light' || afterClick4.dataTheme !== 'light') success = false;

      await page.reload({ waitUntil: 'networkidle', timeout: 30000 });
      const afterReload2 = await readThemeState(page);
      steps.push({ step: 'after_reload_light', ...afterReload2 });
      if (afterReload2.dataTheme !== 'light' || afterReload2.localStorage !== 'light') success = false;

      const screenshotLight = await page.screenshot({ fullPage: false });
      steps.push({ step: 'screenshot_light_post_reload', screenshot_base64: screenshotLight.toString('base64') });
    }
  }
} catch (e) {
  steps.push({ step: 'error', message: String(e && e.message || e) });
  success = false;
} finally {
  await browser.close();
}

process.stdout.write(JSON.stringify({ success, viewport: viewportName, steps }));
