// Horizontal-overflow gate (WU-D) — renders every corpus document under one
// stylesheet at phone, tablet and desktop widths in real Chromium and reports
// each document whose page scrolls sideways, with the elements that stick out.
//
// Usage:
//   node tests/Support/CssParity/overflow.cjs --corpus a.json --css sheet.css --report out.json
//        [--widths 360,768,1280] [--workers 6] [--limit N]
//
// Same isolation as compare.cjs: markup goes in through innerHTML (no JS),
// every request fails at DNS, and motion is frozen. A culprit is an element
// whose box crosses the viewport edge while no ancestor clips it; only the
// outermost culprit of each branch is listed.
const { chromium } = require('@playwright/test');
const fs = require('fs');

const args = {};
for (let i = 2; i < process.argv.length; i += 2) args[process.argv[i].replace(/^--/, '')] = process.argv[i + 1];
for (const required of ['corpus', 'css', 'report']) {
  if (!args[required]) {
    console.error('usage: node overflow.cjs --corpus a.json --css sheet.css --report out.json [--widths 360,768,1280] [--workers 6] [--limit N]');
    process.exit(2);
  }
}

const corpus = JSON.parse(fs.readFileSync(args.corpus, 'utf8'));
const sheet = fs.readFileSync(args.css, 'utf8');
const WIDTHS = (args.widths || '360,768,1280').split(',').map(Number);
const WORKERS = Number(args.workers || 6);
const FREEZE = '*,*::before,*::after{animation-play-state:paused!important;animation-delay:0s!important;transition:none!important}';
const shell = `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>${sheet}</style><style>${FREEZE}</style></head><body></body></html>`;

function measure() {
  const vw = document.documentElement.clientWidth;
  const overflow = document.documentElement.scrollWidth - vw;
  if (overflow <= 1) return { overflow: 0, culprits: [] };
  const clips = (el) => ['hidden', 'clip', 'auto', 'scroll'].includes(getComputedStyle(el).overflowX);
  const culprits = [];
  const walk = (el, clipped) => {
    for (const child of el.children) {
      const cs = getComputedStyle(child);
      if (cs.display === 'none' || cs.position === 'fixed') continue;
      const r = child.getBoundingClientRect();
      if (!clipped && r.width > 0 && (r.right > vw + 1 || r.left < -1)) {
        const label = child.tagName.toLowerCase() + (typeof child.className === 'string' && child.className.trim() ? '.' + child.className.trim().split(/\s+/).join('.') : '');
        culprits.push(`${label} [${Math.round(r.left)}..${Math.round(r.right)}]`);
        continue;
      }
      walk(child, clipped || clips(child));
    }
  };
  walk(document.body, false);
  return { overflow, culprits: culprits.slice(0, 5) };
}

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROME_PATH, args: ['--host-resolver-rules=MAP * ~NOTFOUND'] });
  const report = { documents: 0, widths: WIDTHS, overflowing: {} };
  let queue = Object.keys(corpus);
  if (args.limit) queue = queue.slice(0, Number(args.limit));
  const total = queue.length;

  const worker = async () => {
    const page = await (await browser.newContext()).newPage();
    await page.setContent(shell, { waitUntil: 'domcontentloaded' });
    for (let name = queue.shift(); name !== undefined; name = queue.shift()) {
      report.documents++;
      if (report.documents % 50 === 0) console.error(`progress ${report.documents}/${total}`);
      await page.evaluate((html) => { document.body.innerHTML = html; }, corpus[name]);
      for (const w of WIDTHS) {
        await page.setViewportSize({ width: w, height: 900 });
        const m = await page.evaluate(measure);
        if (m.overflow > 0) (report.overflowing[name] ??= {})[w] = m;
      }
    }
  };
  await Promise.all(Array.from({ length: WORKERS }, worker));
  fs.writeFileSync(args.report, JSON.stringify(report, null, 1));
  const perWidth = Object.fromEntries(WIDTHS.map((w) => [w, Object.values(report.overflowing).filter((d) => d[w]).length]));
  console.log(`${report.documents} docs; overflowing per width: ${JSON.stringify(perWidth)}`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
