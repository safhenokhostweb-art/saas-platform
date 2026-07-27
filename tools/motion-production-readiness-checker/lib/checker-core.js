import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

export function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

export function sha256(content) {
  return crypto.createHash('sha256').update(content).digest('hex');
}

export function isInside(parent, candidate) {
  const relative = path.relative(path.resolve(parent), path.resolve(candidate));
  return relative === '' || (!relative.startsWith(`..${path.sep}`) && relative !== '..' && !path.isAbsolute(relative));
}

export function loadRules(toolRoot, config = readJson(path.join(toolRoot, 'config.json'))) {
  const loaded = {};
  for (const [name, relative] of Object.entries(config.rules)) {
    loaded[name] = readJson(path.resolve(toolRoot, relative));
  }
  return { config, rules: loaded };
}

function add(issues, severity, code, message, field = null) {
  issues.push({ severity, code, message, ...(field ? { field } : {}) });
}

function requiredObject(manifest, key, issues) {
  if (!manifest[key] || typeof manifest[key] !== 'object' || Array.isArray(manifest[key])) {
    add(issues, 'error', 'MANIFEST_REQUIRED', `Required object is missing: ${key}`, key);
    return {};
  }
  return manifest[key];
}

function validateLifecycle(manifest, rules, issues) {
  const version = requiredObject(manifest, 'version', issues);
  const state = version.state;
  const previous = version.previous_state;
  if (!rules.states.includes(state)) add(issues, 'error', 'LIFECYCLE_STATE_INVALID', `Unknown lifecycle state: ${state}`, 'version.state');
  if (previous && state && !(rules.transitions[previous] || []).includes(state)) {
    add(issues, 'error', 'LIFECYCLE_TRANSITION_INVALID', `Transition ${previous} -> ${state} is not allowed`, 'version');
  }
  if (version.mutates_existing && rules.immutable_states.includes(version.existing_state)) {
    add(issues, 'error', 'IMMUTABLE_VERSION_MUTATION', 'Published or deprecated versions cannot be modified', 'version.mutates_existing');
  }
  if (version.operation === 'rollback' && (!version.creates_new_version || !version.restore_from_version)) {
    add(issues, 'error', 'ROLLBACK_NOT_APPEND_ONLY', 'Rollback must create a new version and name its restore source', 'version');
  }
  if (rules.approval_required_for.includes(state)) {
    const approval = manifest.approval;
    if (!approval || approval.status !== 'approved' || approval.actor_role !== 'platform_admin' || !approval.checksum) {
      add(issues, 'error', 'APPROVAL_MISSING', `${state} requires checksum-bound Platform Admin approval`, 'approval');
    }
  }
}

function validateAuthorization(manifest, rules, issues) {
  const action = requiredObject(manifest, 'action', issues);
  const allowed = rules.permissions[action.actor_role] || [];
  if (!allowed.includes(action.name)) {
    add(issues, 'error', 'ACTION_UNAUTHORIZED', `${action.actor_role || 'unknown'} cannot perform ${action.name || 'unknown'}`, 'action');
  }
  if (rules.platform_admin_only.includes(action.name) && action.actor_role !== 'platform_admin') {
    add(issues, 'error', 'PLATFORM_ADMIN_REQUIRED', `${action.name} is reserved for Platform Admin`, 'action.actor_role');
  }
}

function validateAsset(manifest, issues) {
  const asset = requiredObject(manifest, 'asset', issues);
  if (!asset.id) add(issues, 'error', 'ASSET_ID_MISSING', 'Asset ID is required', 'asset.id');
  if (!/^[a-f0-9]{64}$/i.test(asset.checksum || '')) add(issues, 'error', 'CHECKSUM_INVALID', 'Asset checksum must be SHA-256', 'asset.checksum');
  if (!asset.metadata_complete) add(issues, 'error', 'METADATA_INCOMPLETE', 'Production metadata must be complete', 'asset.metadata_complete');
  if (!asset.theme_compatible) add(issues, 'error', 'THEME_INCOMPATIBLE', 'Asset must satisfy its declared theme contract', 'asset.theme_compatible');
  if (asset.role === 'master' && asset.ownership?.type !== 'platform') {
    add(issues, 'error', 'MASTER_OWNERSHIP_INVALID', 'Master Assets must be platform-owned', 'asset.ownership');
  }
  if (asset.role === 'master' && manifest.action?.actor_role === 'tenant' && ['modify_master', 'create_draft', 'publish'].includes(manifest.action?.name)) {
    add(issues, 'error', 'TENANT_MASTER_MODIFICATION', 'Tenant cannot modify a Master Asset', 'action');
  }
}

function validateLicense(manifest, rules, issues) {
  const license = requiredObject(manifest, 'license', issues);
  if (!rules.classes.includes(license.class)) add(issues, 'error', 'LICENSE_CLASS_INVALID', `Invalid license class: ${license.class}`, 'license.class');
  if (!(license.usage_limit === null || (Number.isInteger(license.usage_limit) && license.usage_limit >= 0))) {
    add(issues, 'error', 'LICENSE_USAGE_INVALID', 'usage_limit must be null or a non-negative integer', 'license.usage_limit');
  }
  if (!Array.isArray(license.allowed_sites)) add(issues, 'error', 'LICENSE_SITES_INVALID', 'allowed_sites must be an array', 'license.allowed_sites');
  if (license.expiration !== null && Number.isNaN(Date.parse(license.expiration))) add(issues, 'error', 'LICENSE_EXPIRY_INVALID', 'expiration must be null or RFC 3339', 'license.expiration');
  if (!rules.ownership_types.includes(license.ownership?.type)) add(issues, 'error', 'LICENSE_OWNERSHIP_INVALID', 'License ownership type is invalid', 'license.ownership');
  if (license.class === 'restricted' && (!Array.isArray(license.allowed_sites) || license.allowed_sites.length === 0)) {
    add(issues, 'error', 'RESTRICTED_SITE_REQUIRED', 'Restricted licenses require an explicit site allowlist', 'license.allowed_sites');
  }
}

function validateSvgFile(svgFile, root, rules, issues) {
  const absolute = path.resolve(root, svgFile);
  if (!isInside(root, absolute)) {
    add(issues, 'error', 'SVG_PATH_ESCAPE', 'SVG path leaves the input boundary', 'motion.svg_file');
    return;
  }
  if (!fs.existsSync(absolute)) {
    add(issues, 'error', 'SVG_FILE_MISSING', `SVG file does not exist: ${svgFile}`, 'motion.svg_file');
    return;
  }
  const svg = fs.readFileSync(absolute, 'utf8');
  if (!/<svg\b/i.test(svg) || !/<\/svg>/i.test(svg)) add(issues, 'error', 'SVG_XML_INVALID', 'SVG root is malformed', 'motion.svg_file');
  for (const rule of rules.forbidden_svg_patterns) {
    if (new RegExp(rule.pattern, 'i').test(svg)) add(issues, 'error', rule.code, `Unsafe SVG pattern detected: ${rule.code}`, 'motion.svg_file');
  }
  if (!/<title(?:\s|>)/i.test(svg)) add(issues, 'error', 'SVG_TITLE_MISSING', 'SVG requires title', 'motion.svg_file');
  if (!/<desc(?:\s|>)/i.test(svg)) add(issues, 'error', 'SVG_DESC_MISSING', 'SVG requires desc', 'motion.svg_file');
  if (!/<svg\b[^>]*\brole\s*=/i.test(svg)) add(issues, 'error', 'SVG_ROLE_MISSING', 'SVG requires role', 'motion.svg_file');
  if (!/<svg\b[^>]*\baria-(?:label|labelledby)\s*=/i.test(svg)) add(issues, 'error', 'SVG_ARIA_MISSING', 'SVG requires aria-label or aria-labelledby', 'motion.svg_file');
}

function validateMotion(manifest, root, securityRules, issues) {
  const motion = requiredObject(manifest, 'motion', issues);
  const timeline = motion.timeline || {};
  if (!Number.isFinite(timeline.start) || !Number.isFinite(timeline.end) || timeline.start < 0 || timeline.end <= timeline.start) {
    add(issues, 'error', 'TIMELINE_INVALID', 'Timeline requires finite start >= 0 and end > start', 'motion.timeline');
  }
  const available = new Set(manifest.available_asset_ids || []);
  for (const reference of motion.asset_references || []) {
    if (!available.has(reference)) add(issues, 'error', 'MOTION_REFERENCE_BROKEN', `Unknown asset reference: ${reference}`, 'motion.asset_references');
  }
  if (!motion.accessibility?.reduced_motion || !motion.accessibility?.label) {
    add(issues, 'error', 'MOTION_ACCESSIBILITY_INVALID', 'Motion requires a label and reduced-motion behavior', 'motion.accessibility');
  }
  if (motion.svg_file) validateSvgFile(motion.svg_file, root, securityRules, issues);
  for (const field of securityRules.protected_runtime_fields) {
    if (Object.prototype.hasOwnProperty.call(manifest.runtime_projection || {}, field)) {
      add(issues, 'error', 'RUNTIME_PRIVATE_FIELD', `Runtime projection exposes protected field: ${field}`, `runtime_projection.${field}`);
    }
  }
}

function performanceBand(value, limits) {
  if (!Number.isFinite(value) || value < 0) return 'fail';
  if (value <= limits.excellent) return 'excellent';
  if (value <= limits.good) return 'good';
  if (value <= limits.warning) return 'warning';
  return 'fail';
}

function validatePerformance(manifest, rules, issues) {
  const metrics = requiredObject(manifest, 'performance', issues);
  const bands = {};
  for (const [name, limits] of Object.entries(rules.metrics)) {
    bands[name] = performanceBand(metrics[name], limits);
    if (bands[name] === 'warning') add(issues, 'warning', 'PERFORMANCE_WARNING', `${name} is in the warning band`, `performance.${name}`);
    if (bands[name] === 'fail') add(issues, 'error', 'PERFORMANCE_BUDGET_FAIL', `${name} exceeds or does not satisfy the production budget`, `performance.${name}`);
  }
  const rank = ['excellent', 'good', 'warning', 'fail'];
  const overall = Object.values(bands).reduce((worst, band) => rank.indexOf(band) > rank.indexOf(worst) ? band : worst, 'excellent');
  return { bands, overall, score: rules.scores[overall] };
}

export function validateManifest(manifest, { root, rules }) {
  const issues = [];
  if (manifest.contract_version !== '1.0.0') add(issues, 'error', 'CONTRACT_VERSION_UNSUPPORTED', 'contract_version must be 1.0.0', 'contract_version');
  validateLifecycle(manifest, rules.lifecycle, issues);
  validateAuthorization(manifest, rules.authorization, issues);
  validateAsset(manifest, issues);
  validateLicense(manifest, rules.license, issues);
  validateMotion(manifest, root, rules.security, issues);
  const performance = validatePerformance(manifest, rules.performance, issues);
  const errors = issues.filter((issue) => issue.severity === 'error').length;
  const warnings = issues.filter((issue) => issue.severity === 'warning').length;
  return {
    contract_version: '1.0.0',
    valid: errors === 0,
    errors,
    warnings,
    performance,
    issues
  };
}

export function markdownReport(report, inputName) {
  const lines = [
    '# Motion Production Readiness Report', '',
    `- Input: \`${inputName}\``,
    `- Valid: **${report.valid ? 'yes' : 'no'}**`,
    `- Errors: **${report.errors}**`,
    `- Warnings: **${report.warnings}**`,
    `- Performance: **${report.performance.overall} (${report.performance.score})**`, '',
    '## Findings', ''
  ];
  if (report.issues.length === 0) lines.push('No findings.');
  else for (const issue of report.issues) lines.push(`- ${issue.severity.toUpperCase()} \`${issue.code}\`: ${issue.message}`);
  return `${lines.join('\n')}\n`;
}

