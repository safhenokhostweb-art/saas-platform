import fs from 'node:fs';
import path from 'node:path';

export const readJson=(file)=>JSON.parse(fs.readFileSync(file,'utf8'));
export function loadRules(root){const config=readJson(path.join(root,'config.json'));const rules={};for(const [name,file] of Object.entries(config.rules))rules[name]=readJson(path.join(root,file));return {config,rules};}
const issue=(items,code,message,field)=>items.push({severity:'error',code,message,...(field?{field}:{})});

function validateContracts(manifest,rules,issues){
  const contracts=manifest.contracts||{};
  for(const [name,operations] of Object.entries(rules.required_contracts)){
    if(!contracts[name]){issue(issues,'CONTRACT_MISSING',`Missing contract: ${name}`,`contracts.${name}`);continue;}
    const actual=contracts[name].operations||[];
    for(const operation of operations)if(!actual.includes(operation))issue(issues,'CONTRACT_OPERATION_MISSING',`${name} is missing ${operation}`,`contracts.${name}.operations`);
  }
}

function validateAuthorization(manifest,rules,issues){
  const matrix=manifest.authorization_matrix||{};
  for(const [role,expected] of Object.entries(rules.roles)){
    if(!matrix[role]){issue(issues,'AUTH_ROLE_MISSING',`Missing role: ${role}`,`authorization_matrix.${role}`);continue;}
    for(const action of rules.actions){
      if(typeof matrix[role][action]!=='boolean')issue(issues,'AUTH_DECISION_MISSING',`${role}.${action} must be boolean`,`authorization_matrix.${role}.${action}`);
      else if(matrix[role][action]!==expected[action])issue(issues,'AUTH_MATRIX_INCONSISTENT',`${role}.${action} contradicts the approved matrix`,`authorization_matrix.${role}.${action}`);
    }
  }
  for(const action of rules.platform_admin_only)for(const [role,values] of Object.entries(matrix))if(role!=='platform_admin'&&values?.[action]===true)issue(issues,'ADMIN_ONLY_PERMISSION_LEAK',`${action} granted to ${role}`,`authorization_matrix.${role}.${action}`);
}

function visitKeys(value,callback,prefix=''){
  if(Array.isArray(value))return value.forEach((item,index)=>visitKeys(item,callback,`${prefix}[${index}]`));
  if(!value||typeof value!=='object')return;
  for(const [key,item] of Object.entries(value)){callback(key,prefix?`${prefix}.${key}`:key);visitKeys(item,callback,prefix?`${prefix}.${key}`:key);}
}

function validateRuntime(manifest,rules,issues){
  const projection=manifest.runtime_projection_example||{};
  visitKeys(projection,(key,field)=>{
    if(rules.forbidden_fields.includes(key))issue(issues,'RUNTIME_DATA_LEAK',`Forbidden runtime field: ${key}`,field);
    else if(!rules.allowed_fields.includes(key)&&!/^\d+$/.test(key))issue(issues,'RUNTIME_FIELD_NOT_ALLOWLISTED',`Runtime field is not allowlisted: ${key}`,field);
  });
}

function validateReferences(manifest,issues){
  const contracts=new Set(Object.keys(manifest.contracts||{}));
  for(const reference of manifest.contract_references||[])if(!contracts.has(reference))issue(issues,'CONTRACT_REFERENCE_BROKEN',`Unknown contract reference: ${reference}`,'contract_references');
}

function validateOwnership(manifest,rules,issues){
  const flow=manifest.ownership_flow||[];
  if(flow.map((item)=>item.level).join('|')!==rules.ownership_sequence.join('|'))issue(issues,'OWNERSHIP_FLOW_INVALID','Ownership flow does not match the canonical sequence','ownership_flow');
  const site=flow.find((item)=>item.level==='site_package');
  const variant=flow.find((item)=>item.level==='customer_variant');
  if(site&&variant&&site.site_id!==variant.site_id)issue(issues,'CROSS_SITE_OWNERSHIP','Customer Variant must remain in the Site Package boundary','ownership_flow');
  const master=flow.find((item)=>item.level==='platform_master');
  if(master&&master.owner!=='platform')issue(issues,'MASTER_OWNER_INVALID','Platform Master must be platform-owned','ownership_flow');
}

function validateIntegration(manifest,rules,issues){
  const entities=new Set(manifest.persistence_entities||[]);for(const entity of rules.required_entities)if(!entities.has(entity))issue(issues,'PERSISTENCE_ENTITY_MISSING',`Missing logical entity: ${entity}`,'persistence_entities');
  const operations=new Set(manifest.deployment_operations||[]);for(const operation of rules.deployment_operations)if(!operations.has(operation))issue(issues,'DEPLOYMENT_OPERATION_MISSING',`Missing deployment contract operation: ${operation}`,'deployment_operations');
  for(const [name,expected] of Object.entries(rules.required_assumptions))if(manifest.assumptions?.[name]!==expected)issue(issues,'INTEGRATION_ASSUMPTION_BROKEN',`Broken integration assumption: ${name}`,`assumptions.${name}`);
}

export function validateIntegrationContract(manifest,{rules}){
  const issues=[];
  if(manifest.contract_version!=='1.0.0')issue(issues,'CONTRACT_VERSION_UNSUPPORTED','contract_version must be 1.0.0','contract_version');
  validateContracts(manifest,rules.contracts,issues);validateAuthorization(manifest,rules.authorization,issues);validateRuntime(manifest,rules.runtime,issues);validateReferences(manifest,issues);validateOwnership(manifest,rules.integration,issues);validateIntegration(manifest,rules.integration,issues);
  return {valid:issues.length===0,errors:issues.length,issues};
}

export function markdown(report,input){return ['# Motion SaaS Integration Validation Report','',`- Input: \`${input}\``,`- Valid: **${report.valid?'yes':'no'}**`,`- Errors: **${report.errors}**`,'','## Findings','',...(report.issues.length?report.issues.map((item)=>`- ERROR \`${item.code}\`: ${item.message}`):['No findings.']),''].join('\n');}

