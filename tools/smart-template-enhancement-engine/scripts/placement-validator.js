const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const CONTRACT_ROOT = path.resolve(ROOT, '../../packages/saas-platform/modules/motion-graphics/docs/template-builder-contract');
const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const SECTION_IDS = ['hero', 'about', 'services', 'features', 'products', 'portfolio', 'testimonials', 'team', 'contact', 'cta', 'footer'];
const ASSET_TYPES = ['motion', 'icon', 'object', 'visual-asset'];
const ALIGNMENTS = ['start', 'center', 'end', 'inline-start', 'inline-end'];
const DISPLAY = ['show', 'hide', 'static-preview'];
const TOKENS = ['--brand-primary', '--brand-secondary', '--brand-accent', '--brand-background', '--brand-border'];
const INTENSITY = { none: 0, subtle: 1, moderate: 2, high: 3 };

function loadPlacementContracts() {
  return {
    defaults: readJson(path.join(CONTRACT_ROOT, 'contracts', 'asset-type-placement-defaults.json')),
    sections: readJson(path.join(CONTRACT_ROOT, 'contracts', 'section-intelligence.json')),
    industries: readJson(path.join(CONTRACT_ROOT, 'contracts', 'industry-placement-rules.json')),
    placementSchema: readJson(path.join(CONTRACT_ROOT, 'schemas', 'asset-placement.schema.json')),
    inputSchema: readJson(path.join(CONTRACT_ROOT, 'schemas', 'ai-template-builder-input.schema.json')),
    recommendationSchema: readJson(path.join(CONTRACT_ROOT, 'schemas', 'asset-recommendation.schema.json'))
  };
}

function clone(value) { return JSON.parse(JSON.stringify(value)); }

function deepMerge(base, override) {
  const result = clone(base);
  for (const [key, value] of Object.entries(override || {})) {
    result[key] = value && typeof value === 'object' && !Array.isArray(value) && result[key] && typeof result[key] === 'object' && !Array.isArray(result[key])
      ? deepMerge(result[key], value)
      : clone(value);
  }
  return result;
}

function buildPlacement(assetId, assetType, industry, audience, override = {}, contracts = loadPlacementContracts()) {
  if (!contracts.defaults.asset_types[assetType]) throw new Error(`Unknown asset type: ${assetType}`);
  return deepMerge({
    contract_version: '1.0',
    asset_id: assetId,
    asset_type: assetType,
    ...clone(contracts.defaults.asset_types[assetType]),
    industry_usage: [industry],
    audience_usage: [audience]
  }, override);
}

function validatePlacement(placement) {
  const errors = [];
  const required = ['contract_version', 'asset_id', 'asset_type', 'allowed_sections', 'recommended_sections', 'placement_priority', 'size_rules', 'responsive_rules', 'animation_behavior', 'alignment_rules', 'spacing_rules', 'theme_requirements', 'industry_usage', 'audience_usage'];
  for (const key of required) if (!(key in (placement || {}))) errors.push(`${key} is required`);
  if (!placement || errors.length === required.length) return errors;
  if (placement.contract_version !== '1.0') errors.push('contract_version must be 1.0');
  if (!/^(motion|icon|object|visual):[a-z0-9-]+:[a-z0-9-]+:v\d+$/.test(placement.asset_id || '')) errors.push('asset_id is invalid');
  if (!ASSET_TYPES.includes(placement.asset_type)) errors.push('asset_type is invalid');
  for (const key of ['allowed_sections', 'recommended_sections']) {
    if (!Array.isArray(placement[key]) || !placement[key].length || placement[key].some((item) => !SECTION_IDS.includes(item))) errors.push(`${key} is invalid`);
  }
  if (Array.isArray(placement.recommended_sections) && Array.isArray(placement.allowed_sections) && placement.recommended_sections.some((item) => !placement.allowed_sections.includes(item))) errors.push('recommended_sections must be a subset of allowed_sections');
  if (!Number.isInteger(placement.placement_priority) || placement.placement_priority < 1 || placement.placement_priority > 100) errors.push('placement_priority must be 1..100');
  const size = placement.size_rules || {};
  if (!['small', 'medium', 'large', 'fluid'].includes(size.preferred)) errors.push('size_rules.preferred is invalid');
  if (!Number.isInteger(size.min_width_px) || !Number.isInteger(size.max_width_px) || size.min_width_px < 16 || size.max_width_px > 2400 || size.min_width_px > size.max_width_px) errors.push('size_rules width bounds are invalid');
  if (!['preserve', 'contain', 'cover'].includes(size.aspect_ratio_mode)) errors.push('size_rules.aspect_ratio_mode is invalid');
  const responsive = placement.responsive_rules || {};
  if (responsive.preserve_aspect_ratio !== true) errors.push('responsive_rules must preserve aspect ratio');
  for (const breakpoint of ['desktop', 'tablet', 'mobile']) {
    const rule = responsive[breakpoint] || {};
    if (!DISPLAY.includes(rule.display) || !Number.isInteger(rule.width_percent) || rule.width_percent < 10 || rule.width_percent > 100 || !ALIGNMENTS.includes(rule.alignment)) errors.push(`responsive_rules.${breakpoint} is invalid`);
  }
  const animation = placement.animation_behavior || {};
  if (!(animation.intensity in INTENSITY) || !['immediate', 'on-visible', 'manual'].includes(animation.viewport_trigger) || !['static-preview', 'disable', 'simplified'].includes(animation.reduced_motion)) errors.push('animation_behavior is invalid');
  if (placement.asset_type === 'motion' && !['static-preview', 'disable', 'simplified'].includes(animation.reduced_motion)) errors.push('motion requires reduced-motion behavior');
  const alignment = placement.alignment_rules || {};
  if (!ALIGNMENTS.includes(alignment.preferred) || !Array.isArray(alignment.allowed) || !alignment.allowed.includes(alignment.preferred) || alignment.rtl_aware !== true) errors.push('alignment_rules are invalid or not RTL-aware');
  for (const breakpoint of ['desktop', 'tablet', 'mobile']) {
    const spacing = placement.spacing_rules?.[breakpoint] || {};
    if (![spacing.block_px, spacing.inline_px].every((value) => Number.isInteger(value) && value >= 0 && value <= 160)) errors.push(`spacing_rules.${breakpoint} is invalid`);
  }
  const theme = placement.theme_requirements || {};
  if (theme.theme_ready !== true || !Array.isArray(theme.required_tokens) || theme.required_tokens.some((token) => !TOKENS.includes(token)) || theme.contrast_validation !== true) errors.push('theme_requirements are invalid');
  if (!Array.isArray(placement.industry_usage) || !placement.industry_usage.length) errors.push('industry_usage is required');
  if (!Array.isArray(placement.audience_usage) || !placement.audience_usage.length) errors.push('audience_usage is required');
  return [...new Set(errors)];
}

function validateSectionCompatibility(placement, section, position, contracts = loadPlacementContracts()) {
  const errors = validatePlacement(placement);
  const intelligence = contracts.sections.sections[section];
  if (!intelligence) return [...errors, 'section is unknown'];
  if (!placement.allowed_sections.includes(section)) errors.push(`asset type ${placement.asset_type} is not allowed in ${section}`);
  if (!intelligence.recommended_asset_types.includes(placement.asset_type)) errors.push(`asset type ${placement.asset_type} is incompatible with ${section} hierarchy`);
  if (!intelligence.allowed_positions.includes(position)) errors.push(`position ${position} is incompatible with ${section}`);
  if (INTENSITY[placement.animation_behavior.intensity] > INTENSITY[intelligence.animation_intensity]) errors.push(`animation intensity exceeds ${section} limit`);
  return [...new Set(errors)];
}

function luminance(hex) {
  const channels = hex.slice(1).match(/../g).map((part) => parseInt(part, 16) / 255).map((value) => value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
  return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
}

function validateThemeCompatibility(placement, palette) {
  const errors = [];
  const keyByToken = { '--brand-primary': 'primary', '--brand-secondary': 'secondary', '--brand-accent': 'accent', '--brand-background': 'background', '--brand-border': 'border' };
  for (const token of placement.theme_requirements.required_tokens) {
    const key = keyByToken[token];
    if (!/^#[0-9a-f]{6}$/i.test(palette?.[key] || '')) errors.push(`${token} is missing or invalid`);
  }
  if (!errors.length && placement.theme_requirements.contrast_validation && palette.primary && palette.background) {
    const ratio = (Math.max(luminance(palette.primary), luminance(palette.background)) + 0.05) / (Math.min(luminance(palette.primary), luminance(palette.background)) + 0.05);
    if (ratio < 3) errors.push('primary/background contrast is below 3:1');
  }
  return errors;
}

function validateIndustryMatch(industry, assetTerms, section, contracts = loadPlacementContracts()) {
  const rule = contracts.industries.industries[industry];
  if (!rule) return { compatible: false, reasons: ['unsupported industry'] };
  const normalized = new Set((assetTerms || []).map((term) => String(term).toLowerCase()));
  const matches = rule.asset_terms.filter((term) => normalized.has(term));
  return { compatible: matches.length > 0, reasons: matches.map((term) => `industry-term:${term}`), section_preferences: rule.preferred[section] || [] };
}

function createRecommendation(placement, section, position, palette, reasons, contracts = loadPlacementContracts()) {
  const errors = [...validateSectionCompatibility(placement, section, position, contracts), ...validateThemeCompatibility(placement, palette)];
  if (errors.length) return { valid: false, errors };
  return {
    valid: true,
    recommendation: {
      contract_version: '1.0', asset_id: placement.asset_id, reason: reasons,
      placement: { section, position, alignment: placement.alignment_rules.preferred, priority: placement.placement_priority },
      size: placement.size_rules.preferred,
      theme_variant: 'brand-proposal',
      responsive_behavior: {
        desktop: placement.responsive_rules.desktop.display,
        tablet: placement.responsive_rules.tablet.display,
        mobile: placement.responsive_rules.mobile.display,
        reduced_motion: placement.animation_behavior.reduced_motion
      }
    }
  };
}

module.exports = { ROOT, CONTRACT_ROOT, loadPlacementContracts, deepMerge, buildPlacement, validatePlacement, validateSectionCompatibility, validateThemeCompatibility, validateIndustryMatch, createRecommendation };
