# Integration Status

## What is wired end-to-end (through the real container, real host DB test suite)

- `MotionRuntimeInstallationService::installTemplateForSite()` — full atomic pipeline, idempotent, audited, Platform-Admin-only.
- `MotionRuntimeInstallationService::projectSitePackage()` — customer-accessible read path producing allowlisted `RuntimeProjectionDTO`s.
- `TemplateBuilderCompatibilityValidator` — asset_type/section/industry/theme checks are real; performance/responsive/license are honest stubs (see [TEMPLATE_ACTIVATION_MODEL.md](TEMPLATE_ACTIVATION_MODEL.md)).
- Feature flag gating (`motion_graphics_integration`, default `false`) on every new entry point, identical to every Phase 1-3 adapter.
- Audit events prepared (not a full audit UI, per task scope item 8): `motion.template.activation.started/completed/failed`, `motion.runtime.projection.generated`, plus the already-existing `motion.site.package.created` (Phase 3) reused unchanged.

## What remains deferred (same limitation carried since Phase 2)

The standalone module's own `MotionGraphicsServiceProvider` is still not registered on the host app, and the module's migrations have not been run against the host database (see `docs/motion-saas-integration/STANDALONE_MODULE_STATUS.md`-referenced decision, and `docs/motion-read-contracts/INTEGRATION_UPDATE.md`'s "Known limitation"). This phase's host-side tests therefore fake the Phase 2 read contracts exactly as Phase 2/3 did; genuine database-backed reads are proven by the module's own isolated Testbench suite.

## What is explicitly out of scope for this phase (unchanged, still true after implementation)

- No customer-site project changes — nothing in `customer-site/` was touched.
- No asset deployment to customer servers, no real customer-site activation.
- No public runtime endpoint — `projectSitePackage()` is a service method, not a route.
- No UI of any kind.
- No real Brand Variant persistence or AI generation (see [BRAND_VARIANT_FLOW.md](BRAND_VARIANT_FLOW.md)).
- No new database migration — this phase is purely a service/orchestration layer over Phase 3's existing schema.

## Known gaps carried forward or introduced this phase

- `BrandVariantReference.siteId` is not yet cross-checked against the trusted tenant inside `installTemplateForSite()` beyond the approval gate — there is no variant *lookup* to check against yet, since no variant persistence exists.
- Performance budget, responsive support, and license status validation remain honest "not implemented" stubs — real checks require read-contract changes (a module-side, Phase 2-adjacent change) out of this phase's scope.
- Template Manager/Designer roles remain unmodeled (a Phase 1 gap, restated in Phase 3's `OWNERSHIP_RULES.md`, still true here) — `motion.manage_template_package`/`motion.activate_site_package`/`motion.rollback_site_package` are all admin-only today.
- `SiteAssetPackageReadContract` (Phase 2) is still unused by any Phase 1-4 code path.

## Feature flag state at the end of this phase

`motion_graphics_integration` remains `false` by default in `packages/saas-platform/core-utils/config/feature-flags.php` — unchanged by this phase, per the task's closing instruction ("Feature flag must remain OFF").
