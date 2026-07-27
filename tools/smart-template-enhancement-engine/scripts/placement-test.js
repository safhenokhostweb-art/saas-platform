const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { ROOT, loadPlacementContracts, buildPlacement, validatePlacement, validateSectionCompatibility, validateThemeCompatibility, validateIndustryMatch, createRecommendation } = require('./placement-validator');
const { loadContext } = require('./engine');

const contracts = loadPlacementContracts();
const libraryContext = loadContext();
const scenarioRoot = path.join(ROOT, 'test-scenarios', 'placement');
const scenarios = Object.fromEntries(fs.readdirSync(scenarioRoot).filter((file) => file.endsWith('.json')).map((file) => [file, JSON.parse(fs.readFileSync(path.join(scenarioRoot, file), 'utf8'))]));

function libraryFingerprint() {
  const root = path.dirname(libraryContext.libraryPath);
  const files = [];
  (function walk(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const full = path.join(directory, entry.name);
      if (entry.isDirectory()) walk(full); else files.push(full);
    }
  })(root);
  const hash = crypto.createHash('sha256');
  for (const file of files.sort()) hash.update(path.relative(root, file)).update('\0').update(fs.readFileSync(file));
  return hash.digest('hex');
}

const placementFor = (scenario) => buildPlacement(scenario.asset_id, scenario.asset_type, scenario.industry, scenario.audience, scenario.override || {}, contracts);
const before = libraryFingerprint();
const tests = [];
const test = (name, fn) => tests.push({ name, fn });

test('loads all schemas and eleven section contracts', () => {
  assert.strictEqual(Object.keys(contracts.sections.sections).length, 11);
  assert.strictEqual(Object.keys(contracts.industries.industries).length, 11);
  assert.strictEqual(Object.keys(contracts.defaults.asset_types).length, 4);
  assert.strictEqual(contracts.placementSchema.$id, 'motion-graphics/template-builder/asset-placement/v1');
});

test('valid placement schema produces provider-neutral recommendation', () => {
  const scenario = scenarios['valid-placement.json'];
  const placement = placementFor(scenario);
  assert.deepStrictEqual(validatePlacement(placement), []);
  assert.ok(libraryContext.library.assets.some((asset) => asset.id === scenario.asset_id));
  const result = createRecommendation(placement, scenario.section, scenario.position, scenario.palette, ['industry:medical', 'section:hero', 'theme-ready'], contracts);
  assert.strictEqual(result.valid, true);
  assert.deepStrictEqual(Object.keys(result.recommendation), ['contract_version', 'asset_id', 'reason', 'placement', 'size', 'theme_variant', 'responsive_behavior']);
});

test('invalid asset placement is rejected', () => {
  const scenario = scenarios['invalid-asset-placement.json'];
  assert.ok(validatePlacement(placementFor(scenario)).includes(scenario.expected_error));
});

test('incompatible section is rejected', () => {
  const scenario = scenarios['incompatible-section.json'];
  const errors = validateSectionCompatibility(placementFor(scenario), scenario.section, scenario.position, contracts);
  assert.ok(errors.includes(scenario.expected_error), errors.join('; '));
});

test('industry matching rejects unrelated visual vocabulary', () => {
  const scenario = scenarios['industry-mismatch.json'];
  assert.strictEqual(validateIndustryMatch(scenario.industry, scenario.asset_terms, scenario.section, contracts).compatible, scenario.expected_compatible);
});

test('industry matching accepts manufacturing factory vocabulary', () => {
  const result = validateIndustryMatch('manufacturing', ['factory', 'production', 'quality-control'], 'hero', contracts);
  assert.strictEqual(result.compatible, true);
  assert.ok(result.reasons.includes('industry-term:factory'));
  assert.ok(result.section_preferences.includes('industry motion'));
});

test('invalid responsive rule is rejected', () => {
  const scenario = scenarios['invalid-responsive-rule.json'];
  assert.ok(validatePlacement(placementFor(scenario)).includes(scenario.expected_error));
});

test('theme incompatibility is rejected by contrast gate', () => {
  const scenario = scenarios['incompatible-theme.json'];
  const errors = validateThemeCompatibility(placementFor(scenario), scenario.palette);
  assert.ok(errors.includes(scenario.expected_error), errors.join('; '));
});

test('all defaults remain RTL-aware, responsive, and theme-ready', () => {
  for (const [type, defaults] of Object.entries(contracts.defaults.asset_types)) {
    const prefix = type === 'visual-asset' ? 'visual' : type;
    const placement = buildPlacement(`${prefix}:general:contract-test:v1`, type, 'company', 'company', {}, contracts);
    assert.deepStrictEqual(validatePlacement(placement), [], type);
    assert.strictEqual(defaults.alignment_rules.rtl_aware, true);
    assert.strictEqual(defaults.responsive_rules.preserve_aspect_ratio, true);
    assert.strictEqual(defaults.theme_requirements.theme_ready, true);
  }
});

test('placement validation does not modify any existing asset file', () => assert.strictEqual(libraryFingerprint(), before));

let passed = 0;
for (const item of tests) {
  try { item.fn(); passed += 1; process.stdout.write(`PASS  ${item.name}\n`); }
  catch (error) { process.stderr.write(`FAIL  ${item.name}\n${error.stack}\n`); }
}
process.stdout.write(`\n${passed}/${tests.length} placement contract tests passed.\n`);
if (passed !== tests.length) process.exitCode = 1;
