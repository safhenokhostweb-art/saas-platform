import { execFileSync } from 'node:child_process';
import { test, expect, type Page, type ConsoleMessage } from '@playwright/test';
import { loadFixtures, loginAsAdmin } from './helpers';

/**
 * ADR-088 — unified onboarding wizard, real browser flow: live mobile
 * lookup (multi-site owner), live domain check, vertical-driven kit/theme
 * selection, step gating, review summary, at desktop/tablet/mobile widths.
 *
 * The spec NEVER submits: a submit dispatches a real provisioning job. Its
 * only fixtures are a `fake`-driver server (cannot provision anything) and a
 * customer with two sites, created in beforeAll and deleted in afterAll.
 */

const PHP = process.env.PHP_BIN || 'php';
const OWNER_MOBILE = '09990001122';
const SERVER_LABEL = 'E2E Onboarding Fake Server';

function tinker(code: string): string {
  return execFileSync(PHP, ['artisan', 'tinker', '--execute=' + code], { encoding: 'utf8' }).trim();
}

function cleanup(): void {
  tinker(
    `$c = App\\Models\\Customer::withTrashed()->where('mobile', '${OWNER_MOBILE}')->first();` +
    ` if ($c) { App\\Models\\Site::where('customer_id', $c->id)->delete(); $c->forceDelete(); }` +
    ` App\\Models\\CustomerRemoteServer::where('label', '${SERVER_LABEL}')->delete();` +
    // SiteObserver announces each fixture site to the admin notification centre.
    ` App\\Models\\SystemNotification::where('body', 'like', '%e2e-one.example.ir%')->orWhere('body', 'like', '%e2e-two.example.ir%')->get()->each->delete();`,
  );
}

test.beforeAll(() => {
  cleanup();
  tinker(
    `App\\Models\\CustomerRemoteServer::create(['label' => '${SERVER_LABEL}', 'type' => 'fake', 'host' => '127.0.0.1', 'port' => 22, 'username' => 'e2e', 'auth_method' => 'key']);` +
    ` $c = App\\Models\\Customer::create(['name' => 'مالک آزمایشی', 'mobile' => '${OWNER_MOBILE}']);` +
    ` foreach (['e2e-one.example.ir', 'e2e-two.example.ir'] as $d) { App\\Models\\Site::create(['customer_id' => $c->id, 'title' => $d, 'domain' => $d, 'is_active' => true]); }`,
  );
});

test.afterAll(() => cleanup());

function trackErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (m: ConsoleMessage) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('requestfailed', (r) => { if (r.url().includes('/admin/onboarding')) errors.push('request failed: ' + r.url()); });
  return errors;
}

const card = (page: Page, heading: string) => page.locator('section.ob-card').filter({ has: page.getByRole('heading', { name: heading, exact: true }) });

for (const viewport of [
  { name: 'desktop', width: 1366, height: 900 },
  { name: 'tablet', width: 820, height: 1180 },
  { name: 'mobile', width: 390, height: 844 },
]) {
  test(`wizard walks every step with live validation (${viewport.name})`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    const errors = trackErrors(page);
    await loginAsAdmin(page, loadFixtures());
    await page.goto('/admin/onboarding');
    // Exactly one page heading (the layout's), not a second copy in the wizard body.
    await expect(page.getByRole('heading', { name: 'راه‌اندازی مشتری و سایت', level: 1 })).toHaveCount(1);
    await expect(page.getByRole('heading', { name: 'راه‌اندازی مشتری و سایت', level: 1 })).toBeVisible();

    // No horizontal page scroll at any width.
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);

    // Step 1 — gating, then an existing owner typed in Persian digits shows both sites.
    const next = page.getByRole('button', { name: 'مرحلهٔ بعد' });
    await next.click();
    await expect(card(page, 'مشتری')).toBeVisible();
    const mobile = page.locator('#ob-mobile');
    await mobile.fill('۰۹۹۹۰۰۰۱۱۲۲');
    const wizard = page.locator('.ob');
    await expect(wizard.getByText('مشتری موجود: مالک آزمایشی')).toBeVisible();
    await expect(wizard.getByText('e2e-one.example.ir', { exact: true })).toBeVisible();
    await expect(wizard.getByText('e2e-two.example.ir', { exact: true })).toBeVisible();
    await next.click();

    // Step 2 — title + domain with a live check (custom domain: no platform root in dev).
    await expect(card(page, 'سایت و دامنه')).toBeVisible();
    await page.locator('#ob-title').fill('سایت آزمایشی ویزارد');
    const domain = page.locator('#ob-domain');
    await page.getByRole('button', { name: 'دامنهٔ اختصاصی' }).click();
    await domain.fill('e2e-one.example.ir');
    await expect(wizard.getByText(/قبلاً به سایت/)).toBeVisible();
    await next.click();
    await expect(card(page, 'سایت و دامنه')).toBeVisible(); // blocked: domain taken
    await domain.fill('example.com');
    await expect(wizard.getByText(/آزاد است/)).toBeVisible({ timeout: 15_000 });
    await next.click();

    // Step 3 — vertical choice, licence type, base modules.
    await expect(card(page, 'کسب‌وکار، لایسنس و کیت‌ها')).toBeVisible();
    const solar = page.getByRole('radio', { name: /صنعت برق و انرژی خورشیدی/ });
    await solar.click();
    await expect(solar).toHaveAttribute('aria-checked', 'true');
    await page.getByRole('button', { name: 'حرفه‌ای' }).click();
    await expect(page.getByRole('button', { name: 'حرفه‌ای' })).toHaveAttribute('aria-pressed', 'true');
    await next.click();

    // Step 4 — theme step renders its choices.
    await expect(card(page, 'قالب سایت')).toBeVisible();
    await expect(page.getByRole('radio', { name: /بدون قالب/ })).toBeVisible();
    await next.click();

    // Step 5 — the fake server is selectable.
    await expect(card(page, 'سرور و استقرار')).toBeVisible();
    await page.getByRole('radio', { name: new RegExp(SERVER_LABEL) }).click();
    await next.click();

    // Step 6 — review reflects every choice; submit is present but NOT pressed.
    const review = card(page, 'بازبینی و اجرا');
    await expect(review).toBeVisible();
    await expect(review.getByText('09990001122')).toBeVisible();
    await expect(review.getByText('مالک آزمایشی (موجود)')).toBeVisible();
    await expect(review.getByText('example.com')).toBeVisible();
    await expect(review.getByText('صنعت برق و انرژی خورشیدی')).toBeVisible();
    await expect(review.getByText(SERVER_LABEL)).toBeVisible();
    await expect(page.getByRole('button', { name: 'تأیید و راه‌اندازی خودکار' })).toBeEnabled();

    // Back-navigation through the step bar keeps state.
    await page.getByRole('button', { name: 'ویرایش' }).first().click();
    await expect(mobile).toHaveValue('۰۹۹۹۰۰۰۱۱۲۲');

    expect(errors, errors.join('\n')).toEqual([]);
  });
}
