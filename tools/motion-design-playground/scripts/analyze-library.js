import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { derivePreviewPath, normalizeAssets, countReady } from './core.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const toolRoot = path.resolve(here, '..');
const sources = JSON.parse(fs.readFileSync(path.join(toolRoot, 'data/data-sources.json'), 'utf8'));
const resolveSource = (relative) => path.resolve(toolRoot, relative);
const libraryIndex = JSON.parse(fs.readFileSync(resolveSource(sources.library_index), 'utf8'));
const tags = JSON.parse(fs.readFileSync(resolveSource(sources.tags), 'utf8'));
const relations = JSON.parse(fs.readFileSync(resolveSource(sources.relations), 'utf8'));
const assetRoot = resolveSource(sources.asset_root);
const assets = normalizeAssets(libraryIndex);

const missingPrimary = [];
const missingPreview = [];
const missingMetadata = [];
for (const asset of assets) {
  if (!fs.existsSync(path.join(assetRoot, asset.path))) missingPrimary.push(asset.id);
  if (!fs.existsSync(path.join(assetRoot, derivePreviewPath(asset.path)))) missingPreview.push(asset.id);
  if (!fs.existsSync(path.join(assetRoot, path.dirname(asset.path), 'metadata.json'))) missingMetadata.push(asset.id);
}

const categories = [...new Set(assets.map((asset) => asset.category).filter(Boolean))].sort();
const familyCounts = Object.fromEntries([...new Set(assets.map((asset) => asset.type))].sort().map((type) => [type, assets.filter((asset) => asset.type === type).length]));
const themeReady = countReady(assets, 'theme_support');
const aiReady = countReady(assets, 'ai_editable');
const tagCount = Array.isArray(tags.tags) ? tags.tags.length : 0;
const relationCount = Array.isArray(relations.relationships) ? relations.relationships.length : 0;

const lines = [
  '# Playground Library Analysis',
  '',
  `Generated: ${new Date().toISOString()}`,
  '',
  'This report was produced by a read-only analyzer. No Master Asset was modified.',
  '',
  '## Summary',
  '',
  '| Metric | Result |',
  '|---|---:|',
  `| Assets loaded | ${assets.length} |`,
  `| Categories | ${categories.length} |`,
  `| Standard tags loaded | ${tagCount} |`,
  `| Relation groups loaded | ${relationCount} |`,
  `| Missing primary files | ${missingPrimary.length} |`,
  `| Missing previews | ${missingPreview.length} |`,
  `| Missing metadata | ${missingMetadata.length} |`,
  `| Theme compatible | ${themeReady} (${percent(themeReady, assets.length)}%) |`,
  `| AI editable | ${aiReady} (${percent(aiReady, assets.length)}%) |`,
  '',
  '## Asset families',
  '',
  ...Object.entries(familyCounts).map(([type, count]) => `- ${type}: ${count}`),
  '',
  '## Categories',
  '',
  categories.map((category) => `\`${category}\``).join(' · '),
  '',
  '## Missing previews',
  '',
  missingPreview.length ? missingPreview.map((id) => `- ${id}`).join('\n') : 'None.',
  '',
  '## Notes',
  '',
  '- Playground data is loaded from the canonical library index, tag registry, and relation registry.',
  '- Theme and brand previews are generated in browser memory only.',
  '- A browser preview does not create or publish a Variant Asset.',
  '',
];

const reports = path.join(toolRoot, 'reports');
fs.mkdirSync(reports, { recursive: true });
fs.writeFileSync(path.join(reports, 'playground-analysis.md'), lines.join('\n'), 'utf8');
process.stdout.write(`Analyzed ${assets.length} assets; missing previews: ${missingPreview.length}; theme ready: ${themeReady}.\n`);

function percent(value, total) {
  return total ? Number(((value / total) * 100).toFixed(2)) : 100;
}
