import {generateSitePackage} from './packaging-core.js';

const $ = (selector) => document.querySelector(selector);
const [catalog, templateData, siteData] = await Promise.all([
  fetch('./data/catalog.json').then((response)=>response.json()),
  fetch('./data/templates.json').then((response)=>response.json()),
  fetch('./data/sites.json').then((response)=>response.json())
]);
const templates=templateData.templates, sites=siteData.sites;
let current=null;

for(const template of templates) $('#template-select').add(new Option(template.name_fa,template.template_id));
for(const site of sites) $('#site-select').add(new Option(site.name_fa,site.site_id));

function selected(){return {template:templates.find((item)=>item.template_id===$('#template-select').value),site:sites.find((item)=>item.site_id===$('#site-select').value)};}
function render(){
  const {template,site}=selected();
  const version=Math.max(1,Number($('#package-version').value)||1);
  current=generateSitePackage(template,catalog,site,version);
  $('#template-owner').textContent=template.template_id;
  $('#site-owner').textContent=site.site_id;
  $('#validation-state').textContent=current.valid?'معتبر':'رد شده';
  $('#validation-state').style.color=current.valid?'var(--cyan)':'var(--danger)';
  $('#asset-count').textContent=`${template.assets.length} مورد`;
  $('#asset-list').innerHTML=template.assets.map((ref)=>`<div class="asset"><h3>${ref.asset_id}</h3><span class="${ref.required?'required':''}">${ref.required?'الزامی':'اختیاری'}</span><p>${ref.type} · v${ref.version} · ${ref.sections.join('، ')}${ref.fallback_asset_id?` · fallback: ${ref.fallback_asset_id}`:''}</p></div>`).join('');
  const findings=current.valid?
    current.resolution.resolved.map((item)=>({error:false,text:`${item.asset.name_fa}: سازگار${item.used_fallback?' — Fallback فعال شد':''}`})):
    current.errors.map((item)=>({error:true,text:`${item.asset_id||'Package'}: ${item.code}`}));
  $('#validation-list').innerHTML=findings.map((item)=>`<div class="check ${item.error?'error':''}">${item.error?'رد':'تأیید'} — ${item.text}</div>`).join('');
  const perf=current.resolution.total_performance||0; $('#performance').textContent=`بودجه ${perf}/${template.performance_budget}`;
  const assets=current.valid?current.manifest.assets:[];
  $('#lineage-select').innerHTML=assets.map((asset,index)=>`<option value="${index}">${asset.asset_id}</option>`).join('');
  renderLineage();
  $('#package-output').textContent=JSON.stringify(current.valid?current.manifest:{valid:false,errors:current.errors},null,2);
  $('#download').disabled=!current.valid;
}

function renderLineage(){
  const asset=current?.valid?current.manifest.assets[Number($('#lineage-select').value)||0]:null;
  $('#lineage').innerHTML=asset?asset.inheritance.map((node)=>`<div class="node"><small>${node.level}</small><strong>${node.id}</strong><span>version ${node.version}</span><span>owner: ${node.owner}</span></div>`).join(''):'<div class="check error">زنجیره معتبری در دسترس نیست.</div>';
}

$('#generate').addEventListener('click',render);
$('#lineage-select').addEventListener('change',renderLineage);
$('#template-select').addEventListener('change',render);
$('#site-select').addEventListener('change',render);
$('#download').addEventListener('click',()=>{
  if(!current?.valid)return;
  const blob=new Blob([`${JSON.stringify(current.manifest,null,2)}\n`],{type:'application/json'});
  const url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=`${current.manifest.package_id}.simulation.json`;link.click();URL.revokeObjectURL(url);
});
render();

