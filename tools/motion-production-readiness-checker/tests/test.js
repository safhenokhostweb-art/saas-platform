import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { loadRules, readJson, validateManifest } from '../lib/checker-core.js';

const testRoot = path.dirname(fileURLToPath(import.meta.url));
const toolRoot = path.resolve(testRoot, '..');
const fixtureRoot = path.join(testRoot, 'fixtures');
const scenariosRoot = path.join(testRoot, 'scenarios');
const libraryRoot = path.resolve(toolRoot, '../../packages/saas-platform/modules/motion-graphics/assets/library');
const { rules } = loadRules(toolRoot);

function merge(base, patch) {
  if (patch === null || typeof patch !== 'object' || Array.isArray(patch)) return patch;
  const result = {...base};
  for (const [key, value] of Object.entries(patch)) {
    result[key] = value && typeof value === 'object' && !Array.isArray(value)
      ? merge(base?.[key] && typeof base[key] === 'object' ? base[key] : {}, value)
      : value;
  }
  return result;
}

function treeHash(root) {
  const hash = crypto.createHash('sha256');
  const files = [];
  const walk = (directory) => {
    for (const entry of fs.readdirSync(directory, {withFileTypes: true})) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) walk(absolute);
      else if (entry.isFile()) files.push(absolute);
    }
  };
  walk(root);
  for (const file of files.sort()) {
    hash.update(path.relative(root, file).split(path.sep).join('/'));
    hash.update(fs.readFileSync(file));
  }
  return hash.digest('hex');
}

const before = treeHash(libraryRoot);
const base = readJson(path.join(fixtureRoot, 'valid-production-package.json'));
const validReport = validateManifest(base, {root: fixtureRoot, rules});
assert.equal(validReport.valid, true, JSON.stringify(validReport.issues));
assert.equal(validReport.errors, 0);

const results = [{name: 'valid production package', passed: true, codes: []}];
const scenarioFiles = fs.readdirSync(scenariosRoot).filter((file) => file.endsWith('.json')).sort();
for (const file of scenarioFiles) {
  const scenario = readJson(path.join(scenariosRoot, file));
  const report = validateManifest(merge(base, scenario.patch), {root: fixtureRoot, rules});
  const codes = new Set(report.issues.map((issue) => issue.code));
  for (const code of scenario.expected_codes) assert.equal(codes.has(code), true, `${scenario.name}: missing ${code}`);
  assert.equal(report.valid, scenario.expected_valid === true, `${scenario.name}: unexpected validity`);
  results.push({name: scenario.name, passed: true, codes: [...codes]});
}

assert.equal(treeHash(libraryRoot), before, 'Existing Asset Library changed during validation');
const summary = {
  generated_at: new Date().toISOString(),
  total: results.length,
  passed: results.filter((result) => result.passed).length,
  failed: 0,
  asset_library_unchanged: true,
  asset_library_sha256: before,
  scenarios: results
};
fs.mkdirSync(path.join(toolRoot, 'reports'), {recursive: true});
fs.writeFileSync(path.join(toolRoot, 'reports', 'test-report.json'), `${JSON.stringify(summary, null, 2)}\n`);
fs.writeFileSync(path.join(toolRoot, 'reports', 'test-report.md'), [
  '# Production Readiness Checker Test Report', '',
  `- Scenarios: **${summary.total}**`,
  `- Passed: **${summary.passed}**`,
  '- Failed: **0**',
  '- Existing Asset Library unchanged: **yes**',
  `- Asset Library SHA-256: \`${before}\``, '',
  ...results.map((result) => `- PASS: ${result.name}`), ''
].join('\n'));
console.log(`PASS ${summary.passed}/${summary.total}; asset library unchanged (${before.slice(0, 12)}...)`);

