import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * TAP_TARGET_SMALL on the slider dots (WCAG 2.2 SC 2.5.8). The real
 * renderer output, the real shared sheets and the real Design HUD audit
 * (canvas-audit.js), on the full-width and the boxed slider:
 *
 *   - the audit reports no TAP_TARGET_SMALL, and every dot button is at
 *     least 24x24 px with no overlap between neighbouring buttons;
 *   - the painted dot keeps its size (0.55rem; the full-width active pill
 *     2.4rem) and its place (13.6 px under the boxed slider, 21.6 px above
 *     the full-width slider's bottom edge), and pixels outside it stay
 *     unpainted, hovered or not;
 *   - no horizontal overflow at any width;
 *   - control: with the old 9 px buttons the audit reports the finding.
 */

const ORIGIN = 'http://tb-dots.test';
const WIDTHS = [
  { name: 'desktop', width: 1366 },
  { name: 'canvas', width: 928 },
  { name: 'tablet', width: 768 },
  { name: 'mobile', width: 390 },
] as const;
const REM = 16;

function renderDocument(): string {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$slides = [
    ['image' => '/s1.png', 'title' => 'حراج پاییزه'],
    ['image' => '/s2.png', 'title' => 'ارسال رایگان'],
    ['image' => '/s3.png', 'title' => 'محصولات تازه'],
    ['image' => '/s4.png', 'title' => 'پیشنهاد ویژه'],
];
$full = $r->renderBlock(['type' => 'slider', 'id' => 'full', 'props' => ['slides' => $slides, 'autoplay' => false]]);
$boxed = $r->renderBlock(['type' => 'slider', 'id' => 'boxed', 'props' => ['slides' => $slides, 'autoplay' => false, 'boxed' => true]]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;background:#fff;font-family:Tahoma,sans-serif}</style></head><body><main>'
    . $full . '<div style="height:40px"></div>' . $boxed . '<div style="height:80px"></div>'
    . '</main><script>' . file_get_contents(SaaSPlatform\\ThemeRuntime\\Providers\\ThemeRuntimeServiceProvider::canvasAuditPath()) . '</script></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
}

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');

async function serve(page: Page, html: string) {
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin === ORIGIN && url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    return route.fulfill({ contentType: 'image/png', body: PNG });
  });
}

type Geometry = {
  tapCodes: string[];
  buttons: { left: number; right: number; top: number; bottom: number; width: number; height: number }[];
  painted: { width: number; height: number; top: number; bottom: number; left: number; right: number; active: boolean }[];
  after: number;
};

// The rules before the fix (9 px buttons, 0.4rem gap), re-applied on top of
// the current sheets to measure where the dots used to be painted. The
// full-width dots animate their width, so transitions are off while measuring.
const OLD_RULES = '.tb-carousel-dot{transition:none!important}'
  + '.tb-carousel-dots{gap:.4rem!important;margin-top:.85rem!important;margin-bottom:0!important}'
  + '.tb-carousel-dot{box-sizing:content-box!important;width:.55rem!important;height:.55rem!important;padding:0!important}'
  + '.tb-slider-full .tb-carousel-dots{bottom:1.35rem!important;margin:0!important}'
  + '.tb-slider-full .tb-carousel-dot[aria-current="true"]{width:2.4rem!important}';

async function oldRules(page: Page, on: boolean) {
  await page.evaluate(([css, enable]) => {
    document.getElementById('old-dot-rules')?.remove();
    if (enable) {
      const s = document.createElement('style');
      s.id = 'old-dot-rules';
      s.textContent = css as string;
      document.head.appendChild(s);
    }
  }, [OLD_RULES, on] as const);
}

/** Button boxes, painted (content) boxes and the audit's tap findings for one slider. */
async function geometry(page: Page, id: string): Promise<Geometry> {
  return page.evaluate((sliderId) => {
    const root = document.getElementById('tb-' + sliderId) || document.querySelector('[data-tb-block-id="' + sliderId + '"]');
    if (!root) throw new Error('slider ' + sliderId + ' not found');
    const audit = (window as any).TbAudit.auditNow(root) as { code: string }[];
    const dots = Array.from(root.querySelectorAll<HTMLElement>('.tb-carousel-dot'));
    const buttons = dots.map((d) => {
      const r = d.getBoundingClientRect();
      return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
    });
    const painted = dots.map((d) => {
      const r = d.getBoundingClientRect();
      const cs = getComputedStyle(d);
      const pt = parseFloat(cs.paddingTop), pb = parseFloat(cs.paddingBottom), pl = parseFloat(cs.paddingLeft), pr = parseFloat(cs.paddingRight);
      const clip = cs.backgroundClip;
      const inset = clip === 'content-box';
      return {
        width: inset ? r.width - pl - pr : r.width,
        height: inset ? r.height - pt - pb : r.height,
        top: inset ? r.top + pt : r.top,
        bottom: inset ? r.bottom - pb : r.bottom,
        left: inset ? r.left + pl : r.left,
        right: inset ? r.right - pr : r.right,
        active: d.getAttribute('aria-current') === 'true',
      };
    });
    // The first element after the slider: the page below must not move.
    let outer: Element = root;
    while (outer.parentElement && outer.parentElement.tagName !== 'MAIN') outer = outer.parentElement;
    let next = outer.nextElementSibling;
    while (next && (next.tagName === 'STYLE' || next.tagName === 'SCRIPT')) next = next.nextElementSibling;
    const after = next ? next.getBoundingClientRect().top + window.scrollY : NaN;
    return { tapCodes: audit.map((f) => f.code).filter((c) => c === 'TAP_TARGET_SMALL'), buttons, painted, after };
  }, id);
}

/** RGB of one screen pixel, read from a clipped screenshot. */
async function pixel(page: Page, x: number, y: number): Promise<number[]> {
  const shot = await page.screenshot({ clip: { x: Math.floor(x), y: Math.floor(y), width: 1, height: 1 } });
  return page.evaluate(async (b64) => {
    const img = new Image();
    img.src = 'data:image/png;base64,' + b64;
    await img.decode();
    const c = document.createElement('canvas');
    c.width = 1; c.height = 1;
    const ctx = c.getContext('2d')!;
    ctx.drawImage(img, 0, 0);
    return Array.from(ctx.getImageData(0, 0, 1, 1).data.slice(0, 3));
  }, shot.toString('base64'));
}

test('slider dots are 24 px tap targets that look and sit as before', async ({ page }) => {
  test.setTimeout(120_000);
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await serve(page, renderDocument());

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 900 });
    await page.goto(`${ORIGIN}/`);

    for (const id of ['full', 'boxed']) {
      const g = await geometry(page, id);
      const where = `${id} @ ${w.name}`;
      expect(g.buttons.length, `${where}: dots rendered`).toBe(4);
      expect(g.tapCodes, `${where}: no TAP_TARGET_SMALL`).toEqual([]);

      for (const b of g.buttons) {
        expect(b.width, `${where}: button width`).toBeGreaterThanOrEqual(24 - 0.01);
        expect(b.height, `${where}: button height`).toBeGreaterThanOrEqual(24 - 0.01);
      }
      const byLeft = [...g.buttons].sort((a, b) => a.left - b.left);
      for (let i = 1; i < byLeft.length; i++) {
        expect(byLeft[i].left, `${where}: neighbouring tap areas do not overlap`).toBeGreaterThanOrEqual(byLeft[i - 1].right - 0.01);
      }

      // Against the old rules: same painted size, same row height and
      // centre line, same horizontal centre of the row, page below unmoved.
      await oldRules(page, true);
      const before = await geometry(page, id);
      await oldRules(page, false);
      const centreX = (ps: Geometry['painted']) => (Math.min(...ps.map((p) => p.left)) + Math.max(...ps.map((p) => p.right))) / 2;
      expect(Math.abs(centreX(g.painted) - centreX(before.painted)), `${where}: dot row stays centred`).toBeLessThan(0.6);
      expect(Math.abs(g.after - before.after), `${where}: content after the slider does not move`).toBeLessThan(0.6);
      g.painted.forEach((p, i) => {
        const o = before.painted[i];
        const wantWidth = id === 'full' && p.active ? 2.4 * REM : 0.55 * REM;
        expect(Math.abs(p.width - wantWidth), `${where}: painted width`).toBeLessThan(0.1);
        expect(Math.abs(p.height - 0.55 * REM), `${where}: painted height`).toBeLessThan(0.1);
        expect(Math.abs(p.width - o.width), `${where}: painted width as before`).toBeLessThan(0.1);
        expect(Math.abs(p.top - o.top), `${where}: painted dot at the same height as before`).toBeLessThan(0.6);
      });
    }

    // Pixels: an inactive boxed dot paints its centre, not its padding, hovered or not.
    const dot = page.locator('#tb-boxed .tb-carousel-dot, [data-tb-block-id="boxed"] .tb-carousel-dot').nth(2);
    await dot.scrollIntoViewIfNeeded();
    for (const state of ['rest', 'hover']) {
      if (state === 'hover') await dot.hover();
      else await page.mouse.move(0, 0);
      const box = (await dot.boundingBox())!;
      const corner = await pixel(page, box.x + 2, box.y + 2);
      const centre = await pixel(page, box.x + box.width / 2, box.y + box.height / 2);
      expect(corner, `${w.name} ${state}: padding stays unpainted`).toEqual([255, 255, 255]);
      expect(centre.some((c) => c < 245), `${w.name} ${state}: the dot itself is painted`).toBe(true);
    }

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
  }

  // Control: the old 9 px buttons with a 0.4rem gap are what the audit flags.
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto(`${ORIGIN}/`);
  await oldRules(page, true);
  for (const id of ['full', 'boxed']) {
    const old = await geometry(page, id);
    expect(Math.min(...old.buttons.map((b) => b.width)), `${id}: the old dot buttons were 0.55rem`).toBeLessThan(10);
    expect(old.tapCodes.length, `${id}: the old dots are flagged`).toBeGreaterThan(0);
  }

  expect(errors).toEqual([]);
});
