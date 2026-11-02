import { test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { sentinelEnv } from './support/env';
import { record, REMEDIES, type SentinelFinding } from './support/findings';
import { pageAudit, type AuditInput } from './support/measure';

/**
 * ADR-077 — Sentinel public-page audit. For every page × width:
 * overflow (page and per widget), the widget feature profiles
 * (widget-profiles.json, generated from the registry), broken images,
 * failed requests, console errors, clipped text, touch-target size on
 * phones, RTL and Vazirmatn. Plus one reduced-motion pass per page.
 *
 * A test only fails when the page could not be audited at all; defects are
 * findings, merged by support/sentinel-reporter.ts into report.json.
 */

const env = sentinelEnv();
const profiles = JSON.parse(fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), 'widget-profiles.json'), 'utf8'));
const FREEZE = '*,*::before,*::after{animation-play-state:paused!important;transition:none!important;caret-color:transparent!important}';

test.describe.configure({ mode: 'parallel' });

function finding(page: string, width: number | null, f: Omit<SentinelFinding, 'page' | 'width' | 'remedy'> & { remedy?: string }): void {
  record({ page, width, widget: null, ...f, remedy: f.remedy ?? REMEDIES[f.code] ?? '' });
}

function watchNetwork(page: Page, url: string, width: number): () => void {
  const origin = new URL(env.baseUrl).origin;
  const errors = new Set<string>();
  page.on('console', (m) => {
    if (m.type() === 'error' && errors.size < 10) errors.add(m.text().slice(0, 300));
  });
  page.on('pageerror', (e) => finding(url, width, { code: 'PAGE_SCRIPT_ERROR', severity: 'warning', message: `Uncaught script error: ${e.message.slice(0, 200)}` }));
  page.on('response', (r) => {
    if (r.status() >= 400 && r.url().startsWith(origin) && r.request().resourceType() !== 'document') {
      finding(url, width, { code: 'REQUEST_FAILED', severity: 'warning', message: `${r.request().method()} ${r.url().slice(0, 200)} answered ${r.status()}.`, evidence: { status: r.status() } });
    }
  });
  page.on('requestfailed', (r) => {
    const reason = r.failure()?.errorText ?? '';
    if (r.url().startsWith(origin) && !/ERR_ABORTED/.test(reason)) {
      finding(url, width, { code: 'REQUEST_FAILED', severity: 'warning', message: `${r.method()} ${r.url().slice(0, 200)} failed (${reason}).` });
    }
  });
  return () => errors.forEach((text) => finding(url, width, { code: 'CONSOLE_ERROR', severity: 'warning', message: `Console error: ${text}` }));
}

async function open(page: Page, url: string, width: number): Promise<boolean> {
  const response = await page.goto(url, { waitUntil: 'load' });
  if (!response || response.status() >= 400) {
    finding(url, width, { code: 'PAGE_HTTP_ERROR', severity: 'blocker', message: `The page answered ${response?.status() ?? 'no response'}.`, evidence: { status: response?.status() ?? null } });
    return false;
  }
  await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => undefined);
  return true;
}

for (const url of env.pages) {
  for (const width of env.widths) {
    test(`page ${url} @ ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      const flushConsole = watchNetwork(page, url, width);
      if (!(await open(page, url, width))) return;
      await page.addStyleTag({ content: FREEZE });

      const input: AuditInput = { profiles: profiles.profiles, global: profiles.global, touch: width < 768 };
      const a = await page.evaluate(pageAudit, input);

      if (a.overflow > 1) {
        finding(url, width, { code: 'PAGE_HORIZONTAL_OVERFLOW', severity: 'blocker', message: `The page scrolls sideways by ${a.overflow}px.`, evidence: { culprits: a.culprits } });
        for (const c of a.culprits.filter((c) => c.widget)) {
          finding(url, width, { code: 'WIDGET_OVERFLOW', severity: 'blocker', widget: c.widget, selector: c.selector, message: `${c.selector} crosses the viewport (${c.left}..${c.right}px of ${width}px).` });
        }
      }
      for (const w of a.widgetOverflow) {
        finding(url, width, { code: 'WIDGET_CONTENT_OVERFLOW', severity: 'warning', widget: w.widget, selector: w.selector, message: `${w.selector} sticks out of its widget by ${w.by}px.` });
      }
      for (const m of a.profileMisses) {
        finding(url, width, { code: m.code, severity: m.severity as SentinelFinding['severity'], widget: m.widget, selector: m.selector, message: m.message, remedy: m.remedy });
      }
      for (const f of a.forbidden) {
        finding(url, width, { code: f.code, severity: f.severity as SentinelFinding['severity'], widget: f.widget, selector: f.selector, message: `${f.message} (${f.count}×)`, remedy: f.remedy });
      }
      for (const img of a.brokenImages) {
        finding(url, width, { code: 'BROKEN_IMAGE', severity: 'warning', widget: img.widget, message: `Image does not load: ${img.src}` });
      }
      for (const c of a.clipped) {
        finding(url, width, { code: 'TEXT_CLIPPED', severity: 'warning', widget: c.widget, selector: c.selector, message: `Text is cut off: «${c.text}».` });
      }
      for (const t of a.smallTargets) {
        finding(url, width, { code: 'TAP_TARGET_SMALL', severity: 'warning', widget: t.widget, selector: t.selector, message: `${t.selector} is ${t.width}×${t.height}px on a touch screen.` });
      }
      // Page-level facts are the same at every width: report them once.
      if (width === env.widths[0]) {
        if (a.dir !== 'rtl') finding(url, null, { code: 'RTL_MISSING', severity: 'blocker', message: `The page direction is "${a.dir || 'unset'}", not rtl.` });
        if (!a.vazirmatn) finding(url, null, { code: 'FONT_NOT_LOADED', severity: 'warning', message: 'Vazirmatn is not loaded on this page.' });
        if (a.widgets.length === 0) finding(url, null, { code: 'NO_WIDGETS_FOUND', severity: 'info', message: 'No Canvas widgets (data-widget-type) on this page.' });
      }
      flushConsole();
    });
  }

  test(`page ${url} reduced motion`, async ({ browser }) => {
    const context = await browser.newContext({ reducedMotion: 'reduce', baseURL: env.baseUrl, ignoreHTTPSErrors: env.ignoreHttpsErrors, viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    try {
      const response = await page.goto(url, { waitUntil: 'load' });
      if (!response || response.status() >= 400) return; // reported by the width tests
      await page.waitForTimeout(1500);
      const running = await page.evaluate(() => document.getAnimations()
        .filter((anim) => anim.playState === 'running' && (anim.effect?.getTiming().iterations ?? 1) === Infinity)
        .map((anim) => {
          const target = (anim.effect as KeyframeEffect | null)?.target as Element | null;
          return target ? target.tagName.toLowerCase() + (target.className && typeof target.className === 'string' ? '.' + target.className.trim().split(/\s+/).slice(0, 2).join('.') : '') : 'unknown';
        }));
      if (running.length > 0) {
        finding(url, null, { code: 'MOTION_NOT_REDUCED', severity: 'warning', message: `${running.length} infinite animation(s) keep running under reduced motion.`, evidence: { targets: running.slice(0, 10) } });
      }
    } finally {
      await context.close();
    }
  });
}
