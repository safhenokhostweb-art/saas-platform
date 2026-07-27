# Runtime Projection Contract

The customer site receives only an allowlisted, published read model.

Allowed fields:

- canonical `asset_id` and published `version`;
- optimized opaque URL/reference and integrity hash;
- safe theme tokens and rendering mode;
- placement and responsive/reduced-motion rules;
- engine/type, accessibility label, cache/version hint, and package projection version.

Forbidden fields:

- source SVG or source definitions;
- AI prompt, provider payload, AI metadata, analysis, or history;
- editable schema or authoring configuration;
- ownership records, internal customer IDs, approvals, audit details;
- internal license/entitlement data, storage paths, credentials, or draft references.

Projection is generated only from an exact published package/version after authorization and license decisions. URLs, if signed, are site/audience-bound and short-lived. A serializer allowlist—not removal after serialization—enforces the contract. Unknown fields fail validation.

