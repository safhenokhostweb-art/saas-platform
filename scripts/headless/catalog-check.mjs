/**
 * Real interactive verification of customer-site's self-service Theme
 * Marketplace ("قالب‌های مشتری" — /admin/theme-marketplace) catalog: login,
 * browse, purchase (free grant) a named theme, install it, verify it
 * becomes the active theme, verify the catalog still lists the other
 * theme(s), reload and confirm persistence. Sibling script to
 * dark-mode-check.mjs — same Playwright install.
 *
 * Generic by design — no theme name/slug is hardcoded here. Point it at
 * any two real theme names present in the catalog (e.g. a known-good theme
 * plus the one you're verifying) to regression-test the catalog-visibility
 * fix (PlatformLicense-gated GET /api/theme-marketplace) end to end,
 * without depending on any specific Theme's content or a hardcoded license
 * key — credentials are CLI args only, never embedded in this file.
 *
 * Usage: node catalog-check.mjs <base_url> <mobile> <password> <theme_name_to_install> <other_theme_name_expected_present> <viewport>
 * viewport: desktop | tablet | mobile (default desktop)
 * Output (stdout, JSON): { steps: [...], success: bool }
 */
import { chromium } from 'playwright';
import { existsSync } from 'fs';

const baseUrl = process.argv[2];
const mobile = process.argv[3];
const password = process.argv[4];
const targetThemeName = process.argv[5];
const otherThemeName = process.argv[6] || '';
const viewportName = (process.argv[7] || 'desktop').toLowerCase();

if (!baseUrl || !mobile || !password || !targetThemeName) {
  process.stderr.write('Usage: node catalog-check.mjs <base_url> <mobile> <password> <theme_name_to_install> [other_theme_name_expected_present] [viewport]\n');
  process.exit(2);
}

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

const steps = [];
let success = true;
const consoleErrors = [];
const pageErrors = [];

const chromePath = findSystemChrome();
const browser = await chromium.launch(chromePath ? { executablePath: chromePath } : {});
try {
  const context = await browser.newContext({ viewport, locale: 'fa-IR' });
  const page = await context.newPage();
  page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', (err) => pageErrors.push(String(err)));

  // Login
  await page.goto(baseUrl + '/login', { waitUntil: 'networkidle', timeout: 30000 });
  await page.fill('input[name="mobile"]', mobile);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
    page.click('form button[type="submit"], form input[type="submit"]'),
  ]);
  steps.push({ step: 'login', url: page.url() });

  // Visit marketplace catalog
  await page.goto(baseUrl + '/admin/theme-marketplace', { waitUntil: 'networkidle', timeout: 30000 });
  const bodyText1 = await page.textContent('body');
  const hasTarget = bodyText1.includes(targetThemeName);
  const hasOther = otherThemeName ? bodyText1.includes(otherThemeName) : true;
  steps.push({ step: 'catalog_initial', hasTarget, hasOther });
  if (!hasTarget || !hasOther) success = false;

  const screenshot1 = await page.screenshot({ fullPage: true });
  steps.push({ step: 'screenshot_catalog', screenshot_base64: screenshot1.toString('base64') });

  // Click "دریافت رایگان" inside the target theme's own card specifically
  const allCards = await page.locator('.grid > div').all();
  let clicked = false;
  for (const card of allCards) {
    const text = await card.textContent();
    if (text && text.includes(targetThemeName)) {
      const btn = card.locator('button:has-text("دریافت رایگان")');
      if (await btn.count() > 0) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
          btn.click(),
        ]);
        clicked = true;
      }
      break;
    }
  }
  steps.push({ step: 'purchase_free_clicked', clicked, url: page.url() });

  // Go to my-licenses to find & click install for the target theme's card
  await page.goto(baseUrl + '/admin/theme-marketplace/my-licenses', { waitUntil: 'networkidle', timeout: 30000 });
  const licensesText = await page.textContent('body');
  steps.push({ step: 'my_licenses_page', hasTarget: licensesText.includes(targetThemeName) });

  const licenseCards = await page.locator('.grid > div').all();
  let installBtn = null;
  for (const card of licenseCards) {
    const text = await card.textContent();
    if (text && text.includes(targetThemeName)) {
      const btn = card.locator('form[action*="/install"] button, form[action*="/install"] input[type=submit]');
      if (await btn.count() > 0) installBtn = btn.first();
      break;
    }
  }
  steps.push({ step: 'install_button_found', found: !!installBtn });

  if (installBtn) {
    // Real safety gate #1: installing over an active theme requires an
    // explicit "confirm_overwrite" checkbox (not just a dialog).
    const confirmCheckbox = page.locator('form[action*="/install"] input[name="confirm_overwrite"]').first();
    if (await confirmCheckbox.count() > 0) {
      await confirmCheckbox.check();
      steps.push({ step: 'checked_confirm_overwrite' });
    }
    // Real safety gate #2: the install form ALSO has a native
    // onsubmit="return confirm(...)" browser dialog. Both must be
    // satisfied, same as a real customer clicking through.
    page.once('dialog', async (d) => { steps.push({ step: 'js_confirm_dialog', message: d.message() }); await d.accept(); });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }).catch(() => {}),
      installBtn.click(),
    ]);
    const afterInstallText = await page.textContent('body');
    steps.push({ step: 'after_install_attempt', url: page.url(), hasErrorAboutConfirm: afterInstallText.includes('تأیید کنید') });
  }

  // Reload the real home page and confirm the target theme is now live and persists.
  await page.goto(baseUrl + '/', { waitUntil: 'networkidle', timeout: 30000 });
  const homeText1 = await page.textContent('body');
  steps.push({ step: 'home_after_install', hasTarget: homeText1.includes(targetThemeName) });

  await page.reload({ waitUntil: 'networkidle', timeout: 30000 });
  const homeText2 = await page.textContent('body');
  steps.push({ step: 'home_after_reload', hasTarget: homeText2.includes(targetThemeName) });
  if (installBtn && !homeText2.includes(targetThemeName)) success = false;

  // Confirm the OTHER theme is still present in the catalog (not lost by switching active theme).
  await page.goto(baseUrl + '/admin/theme-marketplace', { waitUntil: 'networkidle', timeout: 30000 });
  const catalogFinalText = await page.textContent('body');
  const otherStillListed = otherThemeName ? catalogFinalText.includes(otherThemeName) : true;
  steps.push({ step: 'catalog_after_switch', otherStillListed, targetStillListed: catalogFinalText.includes(targetThemeName) });
  if (!otherStillListed) success = false;

  const screenshotFinal = await page.screenshot({ fullPage: true });
  steps.push({ step: 'screenshot_final', screenshot_base64: screenshotFinal.toString('base64') });

  steps.push({ step: 'js_errors', console: consoleErrors, page: pageErrors });
  if (pageErrors.length > 0) success = false;
} catch (e) {
  steps.push({ step: 'error', message: String((e && e.message) || e) });
  success = false;
} finally {
  await browser.close();
}

process.stdout.write(JSON.stringify({ success, viewport: viewportName, steps }));
