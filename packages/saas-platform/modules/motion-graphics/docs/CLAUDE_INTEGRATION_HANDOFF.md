# Claude Integration Handoff

This module is complete and tested as a standalone package but is not currently connected to the main SaaS platform or customer websites.

## Purpose and structure

Motion Graphics is a centralized master library: Platform Admins author immutable versions; tenants consume published safe manifests. Package code lives under config, database/migrations, routes, resources, src, tests, and docs.

## Integration steps

1. Read all module status, contract, checklist, testing, and docs/ai documents.
2. Register package autoloading and MotionGraphicsServiceProvider once through the finalized module mechanism.
3. Bind all resolver, permission, AI, storage, compiler, optimizer, preview, audit, deployment, and clock contracts.
4. Map scalar actor/site identifiers to finalized RBAC and TenantContext authorities; decide explicitly whether foreign keys are warranted.
5. Validate migrations in disposable MySQL and staging before production.
6. Add Platform Admin routes/menu only after tenant authoring is denied.
7. Integrate AI only through MotionAiProviderContract; AI creates validated drafts and never approves/publishes.
8. Configure private authoring/preview storage and immutable optimized asset storage.
9. Bind audit/deployment infrastructure and enable publication only with rollback.
10. Install the read-only customer runtime and motion.graphics Blade component separately.
11. Connect the finalized update infrastructure; do not add another updater here.
12. Enable flags one at a time after their activation checklist gates pass.

## Integration tests

Verify Platform Admin-only authoring, ownership/site isolation, draft continuity of published runtime, separate approval/publication, immutable copy-on-rollback, malicious AI rejection, runtime allowlist, storage integrity/access, deployments/ordering, migration up/down, and platform/customer regressions.

## Rollback

Disable flags and exposure first, stop publication/deployment events, retain history/assets, and revert adapters/code. Roll schema back only before real history exists or via an approved retention migration.

## Do not duplicate

Do not duplicate module models, migrations, lifecycle services, validator, pipeline, manifest builder, contracts, policies, routes, views, tests, or governance documents. Platform code supplies adapters.

## Risks and remaining decisions

Final RBAC/TenantContext, licensing/updater, storage/signing/CDN, queues, deployment, usage metering, and customer delivery remain external decisions. Executable formats require a hardened production compiler. Encryption rotation/search and scalar-ID retention need policy/ADR. Existing root integration traces require review, not automatic reuse or reversal.
