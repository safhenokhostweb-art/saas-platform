const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const unique = (values) => [...new Set(values.filter(Boolean))];

function loadContext() {
  const sources = readJson(path.join(ROOT, 'data', 'data-sources.json'));
  const resolveSource = (value) => path.resolve(ROOT, value);
  return {
    sources,
    libraryPath: resolveSource(sources.library_index),
    tagsPath: resolveSource(sources.tag_registry),
    relationsPath: resolveSource(sources.relation_registry),
    library: readJson(resolveSource(sources.library_index)),
    tags: readJson(resolveSource(sources.tag_registry)),
    relations: readJson(resolveSource(sources.relation_registry)),
    matching: readJson(path.join(ROOT, 'rules', 'asset-matching.json')),
    scoring: readJson(path.join(ROOT, 'rules', 'scoring.json'))
  };
}

function validateProfile(profile) {
  const allowedIndustries = ['company', 'medical', 'restaurant', 'ecommerce', 'education', 'construction', 'personal-brand', 'tourism', 'finance', 'technology', 'manufacturing'];
  const allowedAudience = ['company', 'startup', 'professional', 'consumer'];
  const allowedStyles = ['modern', 'premium', 'minimal', 'traditional', 'corporate', 'luxury', 'playful'];
  const allowedSections = ['hero', 'features', 'services', 'about', 'products', 'portfolio', 'testimonials', 'team', 'contact', 'cta', 'footer'];
  const color = /^#[0-9a-f]{6}$/i;
  const errors = [];

  if (!profile || typeof profile !== 'object' || Array.isArray(profile)) return ['business_profile must be an object'];
  if (typeof profile.name !== 'string' || !profile.name.trim()) errors.push('name is required');
  if (!allowedIndustries.includes(profile.industry)) errors.push('industry is unsupported');
  if (typeof profile.business_type !== 'string' || !profile.business_type.trim()) errors.push('business_type is required');
  if (!allowedAudience.includes(profile.audience)) errors.push('audience is unsupported');
  if (!allowedStyles.includes(profile.style)) errors.push('style is unsupported');
  if (!Array.isArray(profile.required_sections) || !profile.required_sections.length || profile.required_sections.some((item) => !allowedSections.includes(item))) errors.push('required_sections is invalid');
  const theme = profile.brand_theme || {};
  for (const token of ['primary', 'secondary', 'accent', 'background', 'border']) {
    if (!color.test(theme[token] || '')) errors.push(`brand_theme.${token} must be a six-digit hex color`);
  }
  return errors;
}

function validateSections(sections) {
  const allowed = new Set(['hero', 'features', 'services', 'about', 'products', 'portfolio', 'testimonials', 'team', 'contact', 'cta', 'footer']);
  if (!Array.isArray(sections) || !sections.length) return ['template_sections must be a non-empty array'];
  const errors = [];
  sections.forEach((section, index) => {
    if (!section || !allowed.has(section.type)) errors.push(`template_sections[${index}].type is invalid`);
    if (section?.existing_visuals && (!Array.isArray(section.existing_visuals) || section.existing_visuals.some((type) => !['motion', 'icon', 'object', 'visual-asset'].includes(type)))) errors.push(`template_sections[${index}].existing_visuals is invalid`);
  });
  return errors;
}

function industryTerms(profile, rules) {
  return unique([profile.industry, ...(rules.industry_aliases[profile.industry] || [])]);
}

function analyzeSections(sections, rules) {
  return sections.map((section, index) => {
    const rule = rules.section_rules[section.type];
    const existing = section.existing_visuals || [];
    return {
      id: section.id || `${section.type}-${index + 1}`,
      type: section.type,
      purpose: rule.visual_roles,
      existing_visuals: existing,
      missing_visual_types: rule.asset_types.filter((type) => !existing.includes(type)),
      recommended_asset_types: rule.asset_types,
      recommended_usage: rule.usage,
      needs_enhancement: rule.asset_types.some((type) => !existing.includes(type))
    };
  });
}

function qualityAverage(asset) {
  const scores = Object.values(asset.quality_score || {}).filter(Number.isFinite);
  return scores.length ? scores.reduce((sum, value) => sum + value, 0) / scores.length : 0;
}

function intersects(haystack, needles) {
  const values = new Set((haystack || []).map((value) => String(value).toLowerCase()));
  return needles.some((needle) => values.has(String(needle).toLowerCase()));
}

function searchableTerms(asset) {
  return unique([
    asset.category,
    asset.subcategory,
    asset.slug,
    ...(asset.tags || []),
    ...(asset.keywords || []),
    ...(asset.synonyms || []),
    ...(asset.industry || []),
    ...(asset.usage || [])
  ]).map((value) => String(value).toLowerCase());
}

function relationAssetIds(profile, context) {
  const matchTerms = new Set([profile.industry, profile.audience, profile.style, ...industryTerms(profile, context.matching)]);
  return new Set(context.relations.relationships
    .filter((relation) => relation.tags.some((tag) => matchTerms.has(tag)))
    .flatMap((relation) => relation.assets));
}

function isEligible(asset, minimumQuality) {
  return asset.status === 'active'
    && asset.asset_role === 'master'
    && asset.ownership?.type === 'platform'
    && qualityAverage(asset) >= minimumQuality;
}

function rankAsset(asset, section, profile, context, bundleIds) {
  const rules = context.matching;
  const weights = rules.weights;
  const terms = searchableTerms(asset);
  const industries = industryTerms(profile, rules);
  const sectionRule = rules.section_rules[section.type];
  const preferences = rules.industry_section_preferences[profile.industry]?.[section.type] || [];
  const industryMatch = intersects(terms, industries) || intersects(asset.industry, industries);
  const usageMatch = intersects(asset.usage, sectionRule.usage) || intersects(terms, sectionRule.usage);
  const preferredMatch = intersects(terms, preferences);
  const typeIndex = sectionRule.asset_types.indexOf(asset.type);
  const typeScore = typeIndex < 0 ? 0 : weights.asset_type * (1 - (typeIndex * 0.18));
  const quality = qualityAverage(asset);

  let score = 0;
  score += industryMatch ? weights.industry : 0;
  score += usageMatch ? weights.section_usage : 0;
  score += typeScore;
  score += terms.includes(profile.style) ? weights.style : 0;
  score += terms.includes(profile.audience) ? weights.audience : 0;
  score += weights.quality * (quality / 100);
  score += preferredMatch ? 8 : 0;
  score += bundleIds.has(asset.id) ? 5 : 0;

  const reasons = [];
  if (industryMatch) reasons.push(`industry:${profile.industry}`);
  if (usageMatch) reasons.push(`usage:${section.type}`);
  if (preferredMatch) reasons.push('industry-section-preference');
  if (typeIndex >= 0) reasons.push(`type:${asset.type}`);
  if (terms.includes(profile.style)) reasons.push(`style:${profile.style}`);
  if (bundleIds.has(asset.id)) reasons.push('canonical-relation-bundle');

  return { asset, score: Math.round(Math.min(score, 100) * 10) / 10, reasons, quality: Math.round(quality * 10) / 10 };
}

function recommendAssets(profile, analysis, context) {
  const bundleIds = relationAssetIds(profile, context);
  const candidates = context.library.assets.filter((asset) => isEligible(asset, context.matching.minimum_quality));
  return analysis.map((section) => {
    const ranked = candidates
      .map((asset) => rankAsset(asset, section, profile, context, bundleIds))
      .filter((result) => (result.reasons.some((reason) => reason.startsWith('industry:')) || result.reasons.includes('canonical-relation-bundle')) && result.reasons.some((reason) => reason.startsWith('type:')))
      .sort((a, b) => b.score - a.score || b.quality - a.quality || a.asset.id.localeCompare(b.asset.id));

    const selected = [];
    const seenTypes = new Set();
    for (const result of ranked) {
      if (selected.length >= context.matching.maximum_recommendations_per_section) break;
      if (!seenTypes.has(result.asset.type) || selected.length >= section.recommended_asset_types.length) {
        selected.push({
          asset_id: result.asset.id,
          slug: result.asset.slug,
          type: result.asset.type,
          category: result.asset.category,
          version: result.asset.version,
          path: result.asset.path,
          match_score: result.score,
          quality_score: result.quality,
          reasons: result.reasons,
          theme_ready: result.asset.theme_support === true,
          ownership: result.asset.ownership.type
        });
        seenTypes.add(result.asset.type);
      }
    }

    return { section_id: section.id, section_type: section.type, recommendations: selected };
  });
}

function calculateEnhancementScore(sections, recommendations, scoring) {
  const metrics = scoring.metrics;
  const generic = Object.values(metrics).reduce((sum, metric) => sum + metric.generic, 0);
  const enhanced = Object.values(metrics).reduce((sum, metric) => sum + metric.enhanced, 0);
  const existingCoverage = sections.filter((section) => (section.existing_visuals || []).length > 0).length / sections.length;
  const recommendationCoverage = recommendations.filter((item) => item.recommendations.length > 0).length / sections.length;
  const themeCoverage = recommendations.flatMap((item) => item.recommendations).filter((item) => item.theme_ready).length;
  const totalRecommended = recommendations.flatMap((item) => item.recommendations).length;
  const before = Math.min(100, Math.round(generic + existingCoverage * 8));
  const after = Math.min(100, Math.round(generic + (enhanced - generic) * recommendationCoverage));

  return {
    before,
    after,
    improvement: after - before,
    metrics: Object.fromEntries(Object.entries(metrics).map(([key, value]) => [key, { before: value.generic, after: Math.round(value.generic + (value.enhanced - value.generic) * recommendationCoverage), maximum: value.weight }])),
    coverage: {
      existing_visual_sections_percent: Math.round(existingCoverage * 100),
      recommended_sections_percent: Math.round(recommendationCoverage * 100),
      recommended_assets_theme_ready_percent: totalRecommended ? Math.round(themeCoverage / totalRecommended * 100) : 0
    }
  };
}

function brandHandoff(theme) {
  return {
    status: 'proposal-only',
    create_variant: false,
    publish: false,
    theme_tokens: {
      '--brand-primary': theme.primary,
      '--brand-secondary': theme.secondary,
      '--brand-accent': theme.accent,
      '--brand-background': theme.background,
      '--brand-border': theme.border
    },
    asset_token_mapping: {
      '--asset-primary': 'var(--brand-primary)',
      '--asset-secondary': 'var(--brand-secondary)',
      '--asset-accent': 'var(--brand-accent)',
      '--asset-background': 'var(--brand-background)',
      '--asset-border': 'var(--brand-border)'
    },
    approval_required: 'platform-admin'
  };
}

function aiPlaceholder(description, profile) {
  const dictionary = {
    medical: ['ط·آ¸ط¢آ¾ط·آ·ط¢آ²ط·آ·ط¢آ´ط·آ¹ط¢آ©', 'ط·آ·ط¢آ¯ط·آ·ط¢آ±ط·آ¸أ¢â‚¬آ¦ط·آ·ط¢آ§ط·آ¸أ¢â‚¬آ ', 'ط·آ¹ط¢آ©ط·آ¸أ¢â‚¬â€چط·ط›ط¥â€™ط·آ¸أ¢â‚¬آ ط·ط›ط¥â€™ط·آ¹ط¢آ©', 'ط·آ·ط¢آ³ط·آ¸أ¢â‚¬â€چط·آ·ط¢آ§ط·آ¸أ¢â‚¬آ¦ط·آ·ط¹آ¾'],
    manufacturing: ['ط·آ·ط¹آ¾ط·آ¸ط«â€ ط·آ¸أ¢â‚¬â€چط·ط›ط¥â€™ط·آ·ط¢آ¯', 'ط·آ·ط¢آµط·آ¸أ¢â‚¬آ ط·آ·ط¢آ¹ط·آ·ط¹آ¾', 'ط·آ¹ط¢آ©ط·آ·ط¢آ§ط·آ·ط¢آ±ط·آ·ط¢آ®ط·آ·ط¢آ§ط·آ¸أ¢â‚¬آ ط·آ¸أ¢â‚¬طŒ', 'ط·آ¸أ¢â‚¬ع‘ط·آ·ط¢آ·ط·آ·ط¢آ¹ط·آ·ط¢آ§ط·آ·ط¹آ¾'],
    restaurant: ['ط·آ·ط¢آ±ط·آ·ط¢آ³ط·آ·ط¹آ¾ط·آ¸ط«â€ ط·آ·ط¢آ±ط·آ·ط¢آ§ط·آ¸أ¢â‚¬آ ', 'ط·آ·ط·â€؛ط·آ·ط¢آ°ط·آ·ط¢آ§', 'ط·آ¸أ¢â‚¬آ¦ط·آ¸أ¢â‚¬آ ط·آ¸ط«â€ '],
    ecommerce: ['ط·آ¸ط¸آ¾ط·آ·ط¢آ±ط·آ¸ط«â€ ط·آ·ط¢آ´ط·آ¹ط¢آ¯ط·آ·ط¢آ§ط·آ¸أ¢â‚¬طŒ', 'ط·آ·ط¢آ®ط·آ·ط¢آ±ط·ط›ط¥â€™ط·آ·ط¢آ¯', 'ط·آ¸أ¢â‚¬آ¦ط·آ·ط¢آ­ط·آ·ط¢آµط·آ¸ط«â€ ط·آ¸أ¢â‚¬â€چ'],
    technology: ['ط·آ¸ط¸آ¾ط·آ¸أ¢â‚¬آ ط·آ·ط¢آ§ط·آ¸ط«â€ ط·آ·ط¢آ±ط·ط›ط¥â€™', 'ط·آ·ط¹آ¾ط·آ¹ط¢آ©ط·آ¸أ¢â‚¬آ ط·آ¸ط«â€ ط·آ¸أ¢â‚¬â€چط·آ¸ط«â€ ط·آ¹ط¹آ©ط·ط›ط¥â€™', 'ط·آ¸أ¢â‚¬طŒط·آ¸ط«â€ ط·آ·ط¢آ´ ط·آ¸أ¢â‚¬آ¦ط·آ·ط¢آµط·آ¸أ¢â‚¬آ ط·آ¸ط«â€ ط·آ·ط¢آ¹ط·ط›ط¥â€™', 'ط·آ¸أ¢â‚¬آ ط·آ·ط¢آ±ط·آ¸أ¢â‚¬آ¦ط£آ¢أ¢â€ڑآ¬ط¥â€™ط·آ·ط¢آ§ط·آ¸ط¸آ¾ط·آ·ط¢آ²ط·آ·ط¢آ§ط·آ·ط¢آ±']
  };
  const detected = Object.entries(dictionary).find(([, words]) => words.some((word) => (description || '').includes(word)))?.[0] || profile.industry;
  return {
    interface: 'future-ai-recommendation/v1',
    mode: 'placeholder',
    provider_called: false,
    detected_industry: detected,
    suggested_query: [detected, profile.style, profile.audience],
    can_publish: false,
    note: 'A future provider may propose query terms only; canonical validation and Platform Admin approval remain mandatory.'
  };
}

function enhance(input, providedContext) {
  const context = providedContext || loadContext();
  const profileErrors = validateProfile(input.business_profile);
  const sectionErrors = validateSections(input.template_sections);
  const errors = [...profileErrors, ...sectionErrors];
  if (errors.length) {
    const error = new Error(`Invalid enhancement input: ${errors.join('; ')}`);
    error.validationErrors = errors;
    throw error;
  }

  const analysis = analyzeSections(input.template_sections, context.matching);
  const recommendations = recommendAssets(input.business_profile, analysis, context);
  return {
    contract: 'smart-template-enhancement/v1',
    prototype: true,
    business: { name: input.business_profile.name, industry: input.business_profile.industry },
    template_before: input.template_sections.map((section, index) => ({
      id: section.id || `${section.type}-${index + 1}`,
      type: section.type,
      title: section.title || '',
      existing_visuals: section.existing_visuals || []
    })),
    template_after: recommendations.map((section) => ({
      section_id: section.section_id,
      section_type: section.section_type,
      recommended_asset_ids: section.recommendations.map((asset) => asset.asset_id)
    })),
    section_analysis: analysis,
    recommendations,
    enhancement_score: calculateEnhancementScore(input.template_sections, recommendations, context.scoring),
    brand_adaptation: brandHandoff(input.business_profile.brand_theme),
    ai_recommendation: aiPlaceholder(input.business_profile.description || '', input.business_profile),
    safety: {
      source_mode: context.sources.mode,
      asset_writes: false,
      variants_created: false,
      published: false
    }
  };
}

module.exports = {
  ROOT,
  loadContext,
  validateProfile,
  validateSections,
  analyzeSections,
  recommendAssets,
  calculateEnhancementScore,
  brandHandoff,
  aiPlaceholder,
  enhance
};
