# Motion Graphics AI Context

## Module identity

- Module: Motion Graphics
- Owner: Main SaaS Platform
- Architecture: Centralized Master Library
- Canonical source: `packages/saas-platform/modules/motion-graphics`

This is the mandatory starting point for any AI assistant. The package is prepared and tested but deliberately disabled and not connected; repository governance and module-local integration documents remain authoritative.

## Ownership rules

Only a main Platform Admin may create or modify motion graphics, access sources, use AI editing, generate or approve versions, publish, roll back, or delete. Authority must be resolved only through module contracts; no current platform user or role shape may be assumed.

Tenant users are consumers only. They may view, select, and use published animations when permitted. Never give tenants editing, source access, AI modification, version creation, approval, publication, rollback, or deletion capability.

Motion ownership may be global or customer_specific. Customer ownership restricts an asset to one external owner_site_identifier; creation and editing still occur only through Platform Admin.

AI assistance does not replace Platform Admin authority. AI may prepare a source proposal and draft inside the authorized admin workflow, but it must not approve or publish its own output.

## Architecture rules

Master source exists only in `packages/saas-platform/modules/motion-graphics`. Customer projects consume published optimized assets.

Never duplicate authoring code in a customer project, move ownership to `customer-site`, send authoring data or AI endpoints to tenants, or modify runtime consumer logic without explicit architectural approval. Tenant runtime resolves only a published version and must not reconstruct authoring data.

## Version rules

Version snapshots and published assets are immutable. Never edit a version in place.

1. Create a new draft with a parent reference.
2. Record and compare changes.
3. Generate and validate preview and optimized assets.
4. Wait for Platform Admin approval.
5. Publish only through the authorized workflow.

Rollback creates a new draft from a historical snapshot; it never rewrites history.

## Security rules

Never expose `source_definition_json`, `editable_schema_json`, `ai_metadata_json`, source snapshots, or prompts through tenant APIs, templates, manifests, logs, or errors.

Tenant responses may contain only required published identity, version, sanitized optimized asset descriptors, integrity metadata, and publication time. Enforce tenant isolation and signed asset access where configured.

## Development rules

Before changing code:

1. Read repository governance plus module docs STANDALONE_MODULE_STATUS.md, FUTURE_INTEGRATION_CONTRACT.md, and CLAUDE_INTEGRATION_HANDOFF.md.
2. Read every document in `AI_MODULE_INDEX.json`.
3. Inspect existing contracts.
4. Follow Laravel and package conventions.
5. Preserve backward compatibility.
6. Add focused tests.

Use repository evidence; do not invent fields, services, endpoints, or infrastructure.
