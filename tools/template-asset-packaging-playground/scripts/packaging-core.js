const clone = (value) => JSON.parse(JSON.stringify(value));

export function fingerprint(value) {
  const text = JSON.stringify(value, Object.keys(value).sort());
  let hash = 2166136261;
  for (let i = 0; i < text.length; i += 1) hash = Math.imul(hash ^ text.charCodeAt(i), 16777619);
  return `demo-${(hash >>> 0).toString(16).padStart(8, '0')}`;
}

export function validateOwnership(asset, siteId) {
  if (asset.role === 'master') return asset.ownership?.type === 'platform'
    ? {valid:true} : {valid:false, code:'MASTER_OWNERSHIP_INVALID'};
  if (asset.role === 'customer_private') {
    if (asset.ownership?.type !== 'customer') return {valid:false, code:'PRIVATE_OWNERSHIP_INVALID'};
    if (asset.ownership.site_id !== siteId) return {valid:false, code:'CROSS_SITE_ASSET_DENIED'};
    return {valid:true};
  }
  return {valid:false, code:'ASSET_ROLE_INVALID'};
}

export function validateCompatibility(asset, reference, template, site) {
  const failures = [];
  if (asset.status !== 'published' && !(asset.role === 'customer_private' && asset.status === 'approved')) failures.push('ASSET_NOT_PUBLISHED');
  if (asset.type !== reference.type) failures.push('TYPE_MISMATCH');
  if (asset.version !== reference.version) failures.push('VERSION_MISMATCH');
  if (!template.industry.some((industry) => asset.industries.includes(industry))) failures.push('INDUSTRY_INCOMPATIBLE');
  if (!reference.sections.every((section) => asset.sections.includes(section))) failures.push('SECTION_INCOMPATIBLE');
  if (!asset.styles.includes('neutral') && !template.style.some((style) => asset.styles.includes(style))) failures.push('STYLE_INCOMPATIBLE');
  if (!template.theme.required_tokens.every((token) => asset.theme_tokens.includes(token))) failures.push('THEME_INCOMPATIBLE');
  if (site && site.industry !== template.industry[0] && !template.industry.includes(site.industry)) failures.push('SITE_INDUSTRY_INCOMPATIBLE');
  return {valid: failures.length === 0, failures};
}

function assetKey(id, version) { return `${id}@${version}`; }

export function resolveTemplate(template, catalog, site) {
  const byKey = new Map(catalog.assets.map((asset) => [assetKey(asset.id, asset.version), asset]));
  const latestById = new Map(catalog.assets.map((asset) => [asset.id, asset]));
  const resolved = [];
  const omitted = [];
  const errors = [];

  if (template.ownership?.type !== 'template' || template.ownership.template_id !== template.template_id) {
    errors.push({code:'TEMPLATE_OWNERSHIP_INVALID', asset_id:null});
  }

  for (const reference of template.assets) {
    let asset = byKey.get(assetKey(reference.asset_id, reference.version));
    let compatibility = asset ? validateCompatibility(asset, reference, template, site) : {valid:false, failures:['ASSET_NOT_FOUND']};
    let usedFallback = false;
    if ((!asset || !compatibility.valid) && reference.fallback_asset_id) {
      const fallback = latestById.get(reference.fallback_asset_id);
      if (fallback) {
        const fallbackReference = {...reference, asset_id:fallback.id, version:fallback.version};
        const fallbackCompatibility = validateCompatibility(fallback, fallbackReference, template, site);
        if (fallbackCompatibility.valid && fallback.performance <= template.performance_budget) {
          asset = fallback; compatibility = fallbackCompatibility; usedFallback = true;
        }
      }
    }
    if (!asset || !compatibility.valid) {
      const issue = {asset_id:reference.asset_id, code:(compatibility.failures || ['ASSET_NOT_FOUND'])[0], required:reference.required};
      if (reference.required) errors.push(issue); else omitted.push(issue);
      continue;
    }
    const ownership = validateOwnership(asset, site.site_id);
    if (!ownership.valid) {
      const issue = {asset_id:reference.asset_id, code:ownership.code, required:reference.required};
      if (reference.required) errors.push(issue); else omitted.push(issue);
      continue;
    }
    resolved.push({reference:clone(reference), asset:clone(asset), used_fallback:usedFallback});
  }

  const totalPerformance = resolved.reduce((sum, item) => sum + item.asset.performance, 0);
  if (totalPerformance > template.performance_budget) errors.push({code:'PACKAGE_PERFORMANCE_EXCEEDED', total:totalPerformance, budget:template.performance_budget});
  return {valid:errors.length === 0, resolved, omitted, errors, total_performance:totalPerformance};
}

export function buildInheritance(template, site, item, packageVersion) {
  const master = {level:'platform_master', id:item.asset.id, version:item.asset.version, owner:'platform'};
  const templateNode = {level:'template_reference', id:`${template.template_id}:${item.reference.asset_id}`, version:template.template_version, owner:template.template_id, parent:`${master.id}@${master.version}`};
  const siteNode = {level:'site_variant', id:`${site.site_id}:${item.asset.id}`, version:packageVersion, owner:site.site_id, parent:`${templateNode.id}@${templateNode.version}`};
  return [master, templateNode, siteNode];
}

export function generateSitePackage(template, catalog, site, packageVersion = 1) {
  const resolution = resolveTemplate(template, catalog, site);
  if (!resolution.valid) return {valid:false, errors:resolution.errors, resolution};
  const assets = resolution.resolved.map((item) => ({
    asset_id:item.asset.id,
    source_version:item.asset.version,
    type:item.asset.type,
    source_role:item.asset.role,
    owner:{type:'customer', site_id:site.site_id},
    variant_id:item.asset.brand_adaptable ? `${site.site_id}.${item.asset.id}.brand-v${packageVersion}` : null,
    used_fallback:item.used_fallback,
    sections:item.reference.sections,
    theme_tokens:item.asset.theme_tokens,
    inheritance:buildInheritance(template, site, item, packageVersion)
  }));
  const manifest = {
    contract_version:'1.0.0', simulation_only:true,
    package_id:`${site.site_id}.${template.template_id}.assets`, package_version:packageVersion,
    site_scope:site.site_id, customer_id:site.customer_id,
    template:{id:template.template_id, version:template.template_version},
    ownership:{type:'customer_private_package', site_id:site.site_id},
    theme:clone(site.theme), performance:{total:resolution.total_performance, budget:template.performance_budget},
    assets
  };
  manifest.demo_fingerprint = fingerprint(manifest);
  return {valid:true, manifest, resolution};
}

