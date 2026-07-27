const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { ROOT, loadSimulationContext, loadScenarios, isSimulationEligible, simulateScenario } = require('../engine/simulation-engine');
const { enhance } = require('../../smart-template-enhancement-engine/scripts/engine');
const { validateThemeCompatibility, buildPlacement } = require('../../smart-template-enhancement-engine/scripts/placement-validator');

const context = loadSimulationContext();
const scenarios = loadScenarios();
const results = scenarios.map((scenario) => simulateScenario(scenario, context));
const assetMap = new Map(context.enhancement.library.assets.map((asset) => [asset.id, asset]));

function libraryFingerprint() {
  const root = path.dirname(context.enhancement.libraryPath); const files=[];
  (function walk(dir){for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,entry.name);entry.isDirectory()?walk(full):files.push(full)}})(root);
  const hash=crypto.createHash('sha256');for(const file of files.sort())hash.update(path.relative(root,file)).update('\0').update(fs.readFileSync(file));return hash.digest('hex');
}
const before=libraryFingerprint(); const tests=[]; const test=(name,fn)=>tests.push({name,fn});
const byIndustry=(industry)=>results.find((result)=>result.business_profile.industry===industry);

for(const industry of ['manufacturing','medical','restaurant','ecommerce','personal-brand']) test(`${industry} template enhancement`,()=>{
  const result=byIndustry(industry);assert.ok(result);assert.ok(result.after.score>result.before.score);assert.ok(result.after.enhancement_plan.every((plan)=>plan.recommended_assets.length>0));
});

test('invalid industry is rejected',()=>{const scenario=JSON.parse(JSON.stringify(scenarios[0]));scenario.business_profile.industry='unknown-industry';assert.throws(()=>enhance({business_profile:scenario.business_profile,template_sections:scenario.template.sections},context.enhancement),/industry is unsupported/)});
test('missing asset reference is detected',()=>{assert.strictEqual(assetMap.has('motion:missing:not-found:v1'),false)});
test('all recommendations resolve to existing eligible assets',()=>{for(const result of results)for(const plan of result.after.enhancement_plan)for(const recommendation of plan.recommended_assets){const asset=assetMap.get(recommendation.asset_id);assert.ok(asset,recommendation.asset_id);assert.strictEqual(isSimulationEligible(asset),true)}});
test('theme compatibility passes for accepted recommendations',()=>{for(const result of results)for(const plan of result.after.enhancement_plan)for(const recommendation of plan.recommended_assets){const placement=buildPlacement(recommendation.asset_id,recommendation.type,result.business_profile.industry,result.business_profile.audience,{},context.placement);assert.deepStrictEqual(validateThemeCompatibility(placement,result.business_profile.brand_theme),[])}});
test('placement contracts are respected',()=>{for(const result of results){assert.strictEqual(result.validation.placement_rules_respected,true);for(const plan of result.after.enhancement_plan)assert.strictEqual(plan.placement.section,plan.section)}});
test('score calculation uses five metrics and bounded improvement',()=>{for(const result of results){assert.strictEqual(Object.keys(result.scoring.metrics).length,5);assert.ok(result.before.score>=0&&result.after.score<=100);assert.strictEqual(result.scoring.improvement,result.after.score-result.before.score)}});
test('all preview references exist',()=>{for(const result of results){assert.strictEqual(result.validation.no_broken_references,true);for(const plan of result.after.enhancement_plan)for(const asset of plan.recommended_assets)assert.ok(fs.existsSync(path.resolve(path.dirname(context.enhancement.libraryPath),asset.preview_path)))}});
test('AI future placeholder cannot call or publish',()=>{for(const result of results){assert.strictEqual(result.ai_placeholder.provider_called,false);assert.strictEqual(result.ai_placeholder.can_publish,false)}});
test('browser shell exposes four inert provider choices',()=>{const html=fs.readFileSync(path.join(ROOT,'index.html'),'utf8');for(const provider of ['OpenAI','Claude','Gemini','Other'])assert.ok(html.includes(`<option>${provider}</option>`))});
test('simulation never modifies existing assets',()=>assert.strictEqual(libraryFingerprint(),before));

let passed=0;for(const item of tests){try{item.fn();passed++;process.stdout.write(`PASS  ${item.name}\n`)}catch(error){process.stderr.write(`FAIL  ${item.name}\n${error.stack}\n`)}}process.stdout.write(`\n${passed}/${tests.length} simulation tests passed.\n`);if(passed!==tests.length)process.exitCode=1;
