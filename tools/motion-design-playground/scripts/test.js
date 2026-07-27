import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { normalizeAssets, derivePreviewPath, filterAssets, collectOptions, normalizePalette, paletteToCss, deriveBrandPalette, createAiContract } from './core.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const toolRoot = path.resolve(here, '..');
const sources = JSON.parse(fs.readFileSync(path.join(toolRoot, 'data/data-sources.json'), 'utf8'));
const resolveSource = (relative) => path.resolve(toolRoot, relative);
const indexPath = resolveSource(sources.library_index);
const assetRoot = resolveSource(sources.asset_root);
const indexBefore = digest(indexPath);
const index = JSON.parse(fs.readFileSync(indexPath, 'utf8'));
const assets = normalizeAssets(index);
const assetFingerprintBefore = fingerprintAssets(assets);

const tests = [];
function test(name, callback) { tests.push({ name, callback }); }

test('loads all canonical assets', () => {
  assert.equal(assets.length, 601);
  assert.deepEqual(index.asset_types, { motion: 22, icons: 80, objects: 450, visual_assets: 49 });
});

test('all primary and preview paths resolve', () => {
  for (const asset of assets) {
    assert.equal(fs.existsSync(path.join(assetRoot, asset.path)), true, `missing primary: ${asset.id}`);
    assert.equal(fs.existsSync(path.join(assetRoot, derivePreviewPath(asset.path))), true, `missing preview: ${asset.id}`);
  }
});

test('search matches names, IDs, and keywords', () => {
  const canonical = assets[0];
  assert.ok(filterAssets(assets, { query: canonical.slug, type: 'all' }).some((asset) => asset.id === canonical.id));
  assert.ok(filterAssets(assets, { query: 'برند', type: 'all' }).length > 0);
});

test('category, industry, usage, tag, and type filters work', () => {
  const category = collectOptions(assets, 'category')[0];
  const industry = collectOptions(assets, 'industry')[0];
  const usage = collectOptions(assets, 'usage')[0];
  const tag = collectOptions(assets, 'tags')[0];
  assert.ok(filterAssets(assets, { category, type: 'all' }).every((asset) => asset.category === category));
  assert.ok(filterAssets(assets, { industry, type: 'all' }).every((asset) => asset.industry.includes(industry)));
  assert.ok(filterAssets(assets, { usage, type: 'all' }).every((asset) => asset.usage.includes(usage)));
  assert.ok(filterAssets(assets, { tag, type: 'all' }).every((asset) => asset.tags.includes(tag)));
  assert.ok(filterAssets(assets, { type: 'icon' }).every((asset) => asset.type === 'icon'));
});

test('theme values map to all five asset variables', () => {
  const theme = normalizePalette({ primary: '#112233', secondary: '#223344', accent: '#334455', background: '#f8fafc', border: '#0f172a' });
  const css = paletteToCss(theme);
  for (const key of ['primary', 'secondary', 'accent', 'background', 'border']) assert.ok(css.includes(`--asset-${key}:${theme[key]}`));
});

test('fake brand analyzer produces a valid five-color palette', () => {
  const palette = deriveBrandPalette(['#2563eb', '#172554', '#f59e0b']);
  assert.deepEqual(Object.keys(palette), ['primary', 'secondary', 'accent', 'background', 'border']);
  Object.values(palette).forEach((color) => assert.match(color, /^#[0-9a-f]{6}$/));
});

test('AI placeholder cannot publish', () => {
  const contract = createAiContract(assets[0], 'OpenAI', 'Change colors', { primary: '#112233', secondary: '#223344', accent: '#334455', background: '#ffffff', border: '#111111' });
  assert.equal(contract.status, 'ui-placeholder-only');
  assert.equal(contract.publish, false);
});

test('all Master SVG files and index remain byte-for-byte unchanged', () => {
  assert.equal(digest(indexPath), indexBefore);
  assert.equal(fingerprintAssets(assets), assetFingerprintBefore);
});

let failures = 0;
for (const current of tests) {
  try { current.callback(); process.stdout.write(`PASS  ${current.name}\n`); }
  catch (error) { failures += 1; process.stdout.write(`FAIL  ${current.name}\n      ${error.message}\n`); }
}
process.stdout.write(`\n${tests.length - failures}/${tests.length} tests passed.\n`);
process.exitCode = failures ? 1 : 0;

function digest(file) {
  return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
}

function fingerprintAssets(items) {
  const hash = crypto.createHash('sha256');
  const paths = items.flatMap((asset) => [asset.path, derivePreviewPath(asset.path)]).sort();
  for (const relative of paths) {
    hash.update(relative);
    hash.update(fs.readFileSync(path.join(assetRoot, relative)));
  }
  return hash.digest('hex');
}
