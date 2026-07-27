import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {buildInheritance, generateSitePackage, resolveTemplate, validateCompatibility, validateOwnership} from '../scripts/packaging-core.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (name) => JSON.parse(fs.readFileSync(path.join(root, 'data', name), 'utf8'));
const catalog = read('catalog.json');
const templates = read('templates.json').templates;
const sites = read('sites.json').sites;
const construction = templates.find((item) => item.template_id === 'construction-pro');
const alpha = sites.find((item) => item.site_id === 'site-alpha');
const libraryRoot = path.resolve(root, '../../packages/saas-platform/modules/motion-graphics/assets/library');

function treeHash(directory) {
  const files=[]; const walk=(dir)=>fs.readdirSync(dir,{withFileTypes:true}).forEach((entry)=>entry.isDirectory()?walk(path.join(dir,entry.name)):files.push(path.join(dir,entry.name))); walk(directory);
  const hash=crypto.createHash('sha256'); files.sort().forEach((file)=>{hash.update(path.relative(directory,file));hash.update(fs.readFileSync(file));}); return hash.digest('hex');
}

const before = treeHash(libraryRoot);
const results=[];
const test=(name,fn)=>{fn();results.push(name);};

test('template asset resolution',()=>{const result=resolveTemplate(construction,catalog,alpha);assert.equal(result.valid,true);assert.equal(result.resolved.length,3);});
test('ownership validation',()=>{assert.equal(validateOwnership(catalog.assets[0],alpha.site_id).valid,true);const invalid={...catalog.assets[0],ownership:{type:'customer',site_id:alpha.site_id}};assert.equal(validateOwnership(invalid,alpha.site_id).code,'MASTER_OWNERSHIP_INVALID');});
test('customer isolation',()=>{const privateAsset=catalog.assets.find((item)=>item.id==='private.factory-photo');assert.equal(validateOwnership(privateAsset,'site-alpha').valid,true);assert.equal(validateOwnership(privateAsset,'site-sina').code,'CROSS_SITE_ASSET_DENIED');});
test('inheritance tracking',()=>{const item=resolveTemplate(construction,catalog,alpha).resolved[0];const chain=buildInheritance(construction,alpha,item,1);assert.deepEqual(chain.map((node)=>node.level),['platform_master','template_reference','site_variant']);assert.equal(chain[2].owner,'site-alpha');});
test('compatibility checking',()=>{const medical=catalog.assets.find((item)=>item.id==='motion.medical-trust');const reference={type:'motion',version:1,sections:['hero']};const result=validateCompatibility(medical,reference,construction,alpha);assert.equal(result.valid,false);assert.ok(result.failures.includes('INDUSTRY_INCOMPATIBLE'));});
test('package generation',()=>{const result=generateSitePackage(construction,catalog,alpha,1);assert.equal(result.valid,true);assert.equal(result.manifest.site_scope,'site-alpha');assert.equal(result.manifest.assets.every((item)=>item.owner.site_id==='site-alpha'),true);assert.match(result.manifest.demo_fingerprint,/^demo-/);});
test('invalid asset rejection',()=>{const invalid=structuredClone(construction);invalid.assets[0].asset_id='motion.unknown';delete invalid.assets[0].fallback_asset_id;const result=resolveTemplate(invalid,catalog,alpha);assert.equal(result.valid,false);assert.equal(result.errors[0].code,'ASSET_NOT_FOUND');});
test('fallback resolution',()=>{const local=structuredClone(catalog);local.assets.find((item)=>item.id==='motion.company-introduction').status='deprecated';const result=resolveTemplate(construction,local,alpha);assert.equal(result.valid,true);assert.equal(result.resolved[0].used_fallback,true);});

const after=treeHash(libraryRoot);assert.equal(after,before,'Existing Asset Library changed');
console.log(`PASS ${results.length}/${results.length}; asset library unchanged (${before.slice(0,12)}...)`);

