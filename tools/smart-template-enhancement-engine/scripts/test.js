const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { ROOT, loadContext, validateProfile, analyzeSections, enhance, aiPlaceholder } = require('./engine');

const context = loadContext();
const libraryRoot = path.dirname(context.libraryPath);
const examplesDir = path.join(ROOT, 'examples');
const examples = fs.readdirSync(examplesDir).filter((file) => file.endsWith('.json')).sort().map((file) => ({ file, input: JSON.parse(fs.readFileSync(path.join(examplesDir, file), 'utf8')) }));

function masterFingerprint() {
  const files = [context.libraryPath];
  const walk = (directory) => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const full = path.join(directory, entry.name);
      if (entry.isDirectory()) walk(full);
      else if (/\.(svg|json|md|txt)$/i.test(entry.name)) files.push(full);
    }
  };
  walk(libraryRoot);
  const hash = crypto.createHash('sha256');
  for (const file of [...new Set(files)].sort()) hash.update(path.relative(libraryRoot, file)).update('\0').update(fs.readFileSync(file));
  return hash.digest('hex');
}

const before = masterFingerprint();
const tests = [];
const test = (name, fn) => tests.push({ name, fn });

test('loads canonical library and governance registries', () => {
  assert.strictEqual(context.sources.mode, 'read-only');
  assert.strictEqual(context.sources.allow_asset_writes, false);
  assert.strictEqual(context.library.assets.length, 601);
  assert.ok(context.tags.tags.length > 0);
  assert.ok(context.relations.relationships.length > 0);
});

test('validates all supported business profiles', () => {
  for (const example of examples) assert.deepStrictEqual(validateProfile(example.input.business_profile), [], example.file);
});

test('rejects malformed theme input', () => {
  const profile = structuredClone(examples[0].input.business_profile);
  profile.brand_theme.primary = 'url(https://unsafe.example)';
  assert.ok(validateProfile(profile).some((error) => error.includes('primary')));
});

test('detects missing visual roles for generic sections', () => {
  const analysis = analyzeSections(examples[0].input.template_sections, context.matching);
  assert.ok(analysis.every((section) => section.needs_enhancement));
  assert.ok(analysis.find((section) => section.type === 'hero').missing_visual_types.includes('motion'));
});

test('matches recommendations by business industry and section', () => {
  for (const example of examples) {
    const output = enhance(example.input, context);
    assert.ok(output.recommendations.every((section) => section.recommendations.length > 0), example.file);
    assert.ok(output.recommendations.flatMap((section) => section.recommendations).every((asset) => asset.ownership === 'platform'));
  }
});

test('improves score for all validation scenarios', () => {
  for (const example of examples) {
    const score = enhance(example.input, context).enhancement_score;
    assert.ok(score.after > score.before, `${example.file}: ${score.before} -> ${score.after}`);
    assert.ok(score.after <= 100);
  }
});

test('passes all five theme tokens without altering assets', () => {
  const output = enhance(examples[0].input, context);
  assert.deepStrictEqual(Object.keys(output.brand_adaptation.theme_tokens), ['--brand-primary', '--brand-secondary', '--brand-accent', '--brand-background', '--brand-border']);
  assert.strictEqual(output.brand_adaptation.create_variant, false);
  assert.ok(output.recommendations.flatMap((section) => section.recommendations).every((asset) => asset.theme_ready));
});

test('keeps future AI interface non-operational and non-publishing', () => {
  const result = aiPlaceholder('شرکت تولید قطعات صنعتی', examples[0].input.business_profile);
  assert.strictEqual(result.provider_called, false);
  assert.strictEqual(result.can_publish, false);
  assert.strictEqual(result.detected_industry, 'manufacturing');
});

test('does not modify any existing library document or asset', () => {
  assert.strictEqual(masterFingerprint(), before);
});

let passed = 0;
for (const item of tests) {
  try {
    item.fn();
    passed += 1;
    process.stdout.write(`PASS  ${item.name}\n`);
  } catch (error) {
    process.stderr.write(`FAIL  ${item.name}\n${error.stack}\n`);
  }
}
process.stdout.write(`\n${passed}/${tests.length} tests passed.\n`);
if (passed !== tests.length) process.exitCode = 1;
