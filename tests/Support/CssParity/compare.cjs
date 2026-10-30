// CSS parity harness (ADR-063) — computed-style diff of a markup corpus
// rendered under two stylesheets, at three widths, in real Chromium.
//
// Usage:
//   node tests/Support/CssParity/compare.cjs --corpus a.json --before old.css --after new.css --report out.json
//        [--corpus-after b.json] [--workers 6] [--limit N]
//
// --corpus-after: compare a corpus rendered by changed renderer code (same
// document keys, same element tree) against the original one.
//
// Every element's full computed style, plus its ::before/::after, is
// hashed per width; only elements whose hash differs are read in full and
// diffed property by property. Colours are normalised to rgba() first, so
// the same colour spelled as rgb(), color(srgb …) or a color-mix() result
// never counts as a change. JS stays off for the inserted markup (it goes in
// through innerHTML), every request fails at DNS, and motion is frozen, so
// a run is deterministic: comparing a stylesheet with itself reports zero.
const { chromium } = require('@playwright/test');
const fs = require('fs');

const args = {};
for (let i = 2; i < process.argv.length; i += 2) args[process.argv[i].replace(/^--/, '')] = process.argv[i + 1];
for (const required of ['corpus', 'before', 'after', 'report']) {
  if (!args[required]) {
    console.error('usage: node compare.cjs --corpus a.json --before old.css --after new.css --report out.json [--corpus-after b.json] [--workers 6] [--limit N]');
    process.exit(2);
  }
}

const corpusBefore = JSON.parse(fs.readFileSync(args.corpus, 'utf8'));
const corpusAfter = args['corpus-after'] ? JSON.parse(fs.readFileSync(args['corpus-after'], 'utf8')) : corpusBefore;
const css = { before: fs.readFileSync(args.before, 'utf8'), after: fs.readFileSync(args.after, 'utf8') };
const WIDTHS = [1366, 768, 390];
const WORKERS = Number(args.workers || 6);
// Same in both runs: freeze motion so computed transforms are deterministic.
const FREEZE = '*,*::before,*::after{animation-play-state:paused!important;animation-delay:0s!important;transition:none!important}';
const shell = (sheet) => `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>${sheet}</style><style>${FREEZE}</style></head><body></body></html>`;

// Runs in the page: rgb()/rgba()/color(srgb …) → one rgba() spelling.
function normalizeColorsSource() {
  return function normalize(value) {
    const r = (x) => Math.round(Math.min(255, Math.max(0, x)));
    const a = (x) => String(Math.round(x * 1000) / 1000);
    return value
      .replace(/color\(srgb ([\d.e-]+) ([\d.e-]+) ([\d.e-]+)(?: \/ ([\d.e-]+))?\)/g, (_, R, G, B, A) => `rgba(${r(R * 255)}, ${r(G * 255)}, ${r(B * 255)}, ${a(A === undefined ? 1 : Number(A))})`)
      .replace(/rgba?\((\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?)(?:,\s*([\d.]+))?\)/g, (_, R, G, B, A) => `rgba(${r(Number(R))}, ${r(Number(G))}, ${r(Number(B))}, ${a(A === undefined ? 1 : Number(A))})`);
  };
}

function snapshot(normalizeSrc) {
  const normalize = eval(`(${normalizeSrc})`)();
  // Sorted: custom-property enumeration order is not stable between pages.
  const ser = (cs) => {
    const props = [];
    for (let i = 0; i < cs.length; i++) props.push(cs[i]);
    props.sort();
    let s = '';
    for (const p of props) s += p + ':' + normalize(cs.getPropertyValue(p)) + ';';
    return s;
  };
  return [...document.body.querySelectorAll('*')].map((el) => {
    const s = ser(getComputedStyle(el)) + '|B|' + ser(getComputedStyle(el, '::before')) + '|A|' + ser(getComputedStyle(el, '::after'));
    let h = 0;
    for (let k = 0; k < s.length; k++) h = (h * 31 + s.charCodeAt(k)) | 0;
    return h;
  });
}

function detail([normalizeSrc, indices]) {
  const normalize = eval(`(${normalizeSrc})`)();
  const els = document.body.querySelectorAll('*');
  const map = (cs) => {
    const o = {};
    for (let i = 0; i < cs.length; i++) o[cs[i]] = normalize(cs.getPropertyValue(cs[i]));
    return o;
  };
  return indices.map((i) => {
    const el = els[i];
    const label = el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).join('.') : '');
    return { label, self: map(getComputedStyle(el)), before: map(getComputedStyle(el, '::before')), after: map(getComputedStyle(el, '::after')) };
  });
}

(async () => {
  // Every request fails at DNS inside Chromium — no per-request Node round-trip.
  const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROME_PATH, args: ['--host-resolver-rules=MAP * ~NOTFOUND'] });
  const normalizeSrc = normalizeColorsSource.toString();
  const report = { documents: 0, elements: 0, differingElements: 0, diffs: {} };
  let queue = Object.keys(corpusBefore);
  if (args.limit) queue = queue.slice(0, Number(args.limit));
  const total = queue.length;

  const worker = async () => {
    // One context per page: many pages in one context stall in setContent.
    const pages = { before: await (await browser.newContext()).newPage(), after: await (await browser.newContext()).newPage() };
    for (const side of ['before', 'after']) await pages[side].setContent(shell(css[side]), { waitUntil: 'domcontentloaded' });
    for (let name = queue.shift(); name !== undefined; name = queue.shift()) {
      if (!(name in corpusAfter)) throw new Error(`document missing from --corpus-after: ${name}`);
      report.documents++;
      if (report.documents % 25 === 0) console.error(`progress ${report.documents}/${total}`);
      await pages.before.evaluate((html) => { document.body.innerHTML = html; }, corpusBefore[name]);
      await pages.after.evaluate((html) => { document.body.innerHTML = html; }, corpusAfter[name]);
      for (const w of WIDTHS) {
        const snaps = {};
        for (const side of ['before', 'after']) {
          await pages[side].setViewportSize({ width: w, height: 900 });
          snaps[side] = await pages[side].evaluate(snapshot, normalizeSrc);
        }
        if (snaps.before.length !== snaps.after.length) throw new Error(`element count differs in ${name}`);
        if (w === WIDTHS[0]) report.elements += snaps.before.length;
        const idx = [];
        snaps.before.forEach((h, i) => { if (h !== snaps.after[i]) idx.push(i); });
        if (!idx.length) continue;
        report.differingElements += idx.length;
        const b = await pages.before.evaluate(detail, [normalizeSrc, idx]);
        const a = await pages.after.evaluate(detail, [normalizeSrc, idx]);
        b.forEach((eb, k) => {
          const ea = a[k];
          for (const part of ['self', 'before', 'after']) {
            for (const p of new Set([...Object.keys(eb[part]), ...Object.keys(ea[part])])) {
              if (eb[part][p] === ea[part][p]) continue;
              const key = `${eb.label}${part === 'self' ? '' : '::' + part} | ${p}: ${eb[part][p]} -> ${ea[part][p]}`;
              const d = (report.diffs[key] ??= { count: 0, widths: new Set(), docs: new Set() });
              d.count++;
              d.widths.add(w);
              if (d.docs.size < 3) d.docs.add(name);
            }
          }
        });
      }
    }
  };
  await Promise.all(Array.from({ length: WORKERS }, worker));
  for (const d of Object.values(report.diffs)) { d.widths = [...d.widths]; d.docs = [...d.docs]; }
  fs.writeFileSync(args.report, JSON.stringify(report, null, 1));
  console.log(`${report.documents} docs, ${report.elements} elements/width, ${report.differingElements} differing element-widths, ${Object.keys(report.diffs).length} distinct diffs`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
