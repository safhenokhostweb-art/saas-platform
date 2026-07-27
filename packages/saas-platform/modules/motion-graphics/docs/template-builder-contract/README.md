# Template Builder Asset Placement Contract

Status: **standalone contract layer; not integrated**.

This provider-neutral layer defines how published Motion Graphics Library assets may be recommended and positioned in a future professional Template Builder. It supplies JSON Schemas, canonical section intelligence, asset-type defaults, industry rules, examples, and a read-only validator.

## Boundary

- No Laravel classes, API, database, authentication, tenant runtime, or AI provider.
- No Master Asset or asset metadata changes.
- Visibility and recommendation never grant license, tenant access, editing, or publication rights.
- All AI output remains an untrusted proposal requiring deterministic validation and Platform Admin approval.

## Canonical resources

- `schemas/asset-placement.schema.json`
- `schemas/ai-template-builder-input.schema.json`
- `schemas/asset-recommendation.schema.json`
- `contracts/asset-type-placement-defaults.json`
- `contracts/section-intelligence.json`
- `contracts/industry-placement-rules.json`

Validation lives in `tools/smart-template-enhancement-engine/scripts/placement-validator.js` and does not import application code.
