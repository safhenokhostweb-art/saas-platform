const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const ENHANCEMENT_ROOT = path.resolve(ROOT, '../smart-template-enhancement-engine');
const { enhance, loadContext } = require(path.join(ENHANCEMENT_ROOT, 'scripts', 'engine.js'));
const { loadPlacementContracts, buildPlacement, createRecommendation } = require(path.join(ENHANCEMENT_ROOT, 'scripts', 'placement-validator.js'));
const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));

function loadSimulationContext() {
  return { enhancement: loadContext(), placement: loadPlacementContracts() };
}

function loadScenarios() {
  return fs.readdirSync(path.join(ROOT, 'scenarios')).filter((file) => file.endsWith('.json')).sort().map((file) => readJson(path.join(ROOT, 'scenarios', file)));
}

function previewPath(primaryPath) {
  return primaryPath.replace(/(?:animation|icon|asset)\.svg$/i, 'preview.svg');
}

function isSimulationEligible(asset) {
  return asset?.status === 'active' && asset.asset_role === 'master' && asset.ownership?.type === 'platform' && asset.theme_support === true && asset.ai_editable === true;
}

function simulateScenario(scenario, providedContext) {
  const context = providedContext || loadSimulationContext();
  const input = { business_profile: scenario.business_profile, template_sections: scenario.template.sections };
  const enhancement = enhance(input, context.enhancement);
  const assetMap = new Map(context.enhancement.library.assets.map((asset) => [asset.id, asset]));
  const sectionMap = new Map(scenario.template.sections.map((section, index) => [`${section.type}-${index + 1}`, section]));
  const deltaPerSection = Math.max(1, Math.round(enhancement.enhancement_score.improvement / scenario.template.sections.length));

  const plans = enhancement.recommendations.map((group) => {
    const section = sectionMap.get(group.section_id) || scenario.template.sections.find((item) => item.type === group.section_type);
    const sectionContract = context.placement.sections.sections[group.section_type];
    const position = sectionContract.allowed_positions[0];
    const accepted = [];
    const rejected = [];

    for (const candidate of group.recommendations) {
      const asset = assetMap.get(candidate.asset_id);
      if (!isSimulationEligible(asset)) { rejected.push({ asset_id: candidate.asset_id, reason: 'eligibility-gate' }); continue; }
      const placement = buildPlacement(asset.id, asset.type, scenario.business_profile.industry, scenario.business_profile.audience, {}, context.placement);
      const proposal = createRecommendation(placement, group.section_type, position, scenario.business_profile.brand_theme, candidate.reasons, context.placement);
      if (!proposal.valid) { rejected.push({ asset_id: candidate.asset_id, reason: proposal.errors.join('; ') }); continue; }
      accepted.push({
        ...proposal.recommendation,
        name: asset.name_fa || asset.name,
        type: asset.type,
        category: asset.category,
        preview_path: previewPath(asset.path),
        match_score: candidate.match_score,
        quality_score: candidate.quality_score,
        theme_tokens: enhancement.brand_adaptation.theme_tokens
      });
      if (accepted.length === 2) break;
    }

    return {
      section: group.section_type,
      title: section?.title || group.section_type,
      before: { content: section?.content || 'محتوای عمومی', existing_visuals: section?.existing_visuals || [], state: 'generic' },
      recommended_assets: accepted,
      placement: accepted[0]?.placement || null,
      reason: accepted[0]?.reason || ['No compatible canonical asset found'],
      expected_improvement: `+${deltaPerSection} امتیاز تخمینی`,
      rejected_candidates: rejected
    };
  });

  return {
    simulation_contract: 'template-enhancement-simulation/v1',
    scenario: { id: scenario.id, label: scenario.label, template_name: scenario.template.template_name },
    business_profile: scenario.business_profile,
    before: { template_type: scenario.template.template_type, sections: scenario.template.sections, score: enhancement.enhancement_score.before },
    after: { enhancement_plan: plans, score: enhancement.enhancement_score.after, brand_theme: scenario.business_profile.brand_theme },
    scoring: enhancement.enhancement_score,
    ai_placeholder: { enabled: false, providers: ['OpenAI', 'Claude', 'Gemini', 'Other'], provider_called: false, can_publish: false },
    validation: {
      canonical_assets_only: plans.every((plan) => plan.recommended_assets.every((asset) => assetMap.has(asset.asset_id))),
      no_broken_references: plans.every((plan) => plan.recommended_assets.every((asset) => fs.existsSync(path.resolve(path.dirname(context.enhancement.libraryPath), asset.preview_path)))),
      placement_rules_respected: plans.every((plan) => plan.recommended_assets.length > 0),
      theme_rules_respected: plans.every((plan) => plan.recommended_assets.every((asset) => Object.keys(asset.theme_tokens).length === 5)),
      industry_rules_respected: plans.every((plan) => plan.recommended_assets.every((asset) => asset.reason.some((reason) => reason.startsWith('industry:') || reason === 'canonical-relation-bundle'))),
      asset_writes: false
    }
  };
}

module.exports = { ROOT, loadSimulationContext, loadScenarios, previewPath, isSimulationEligible, simulateScenario };
