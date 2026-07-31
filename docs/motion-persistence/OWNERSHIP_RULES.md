# Ownership Rules

Two distinct kinds of "ownership" exist across the boundary, and this phase is careful never to blur them.

## Module ownership (unchanged, module-owned)

`MotionGraphic.ownership_type` (`global` / `customer_specific`) and `owner_site_identifier` — who authored/owns a Master Asset, and which single site (if any) it is restricted to. This lives **only** in the module's own `motion_graphics` table and is never copied into any Phase 3 table or model. Every persistence-layer read of "is this asset usable by this site" goes through `MotionRuntimeProjectionReadContract::projectAsset(assetId, siteId)`, which enforces the module's ownership rule internally and returns `null` rather than exposing the raw `ownership_type`/`owner_site_identifier` fields to the host at all.

## Platform assignment (new, host-owned)

`SiteAssetPackage.site_id` / `SiteAssetPackageItem` — which site has **activated** which asset, at which version. This is a platform-level bookkeeping record, analogous to the existing `ModuleLicense`/`ModuleEntitlement` pattern in this codebase (a site's entitlement to use something, tracked by the host, without the host owning the underlying thing itself).

A site can activate a global asset, or a customer-specific asset that it owns; it can never activate a customer-specific asset owned by another site — enforced by the module (step 1 of [ACTIVATION_FLOW.md](ACTIVATION_FLOW.md)'s validation), not re-implemented here.

## Who may call what

| Action | Ability checked | Who passes |
|---|---|---|
| `TemplateAssetPackageService::createTemplateAssetPackage()` | `motion.manage_template_package` | Platform Admin only — this ability is not among the abilities `PermissionGatewayAdapter` grants a Customer, so a customer request is denied without any change to that adapter |
| `SiteAssetPackageActivationService::activate()` | `motion.use` | Platform Admin, or a Customer acting on their **own** site (tenant-matched) |
| `SiteAssetPackageActivationService::rollback()` | `motion.use` | Same as activation — a site rolling back its own bound version is a self-service configuration action, not a Master Asset governance action |

This mirrors `docs/motion-saas-integration/AUTHORIZATION_MATRIX.md`: creating a template's asset requirements is authoring-adjacent (platform-controlled); activating/rolling back a site's own binding is consumption (site-scoped). `motion.rollback` (the module's own Master Asset rollback ability, Platform-Admin-only) is a completely different operation from the rollback described here — it rewrites which module version is *published*; this phase's rollback only ever changes which already-published version one site's package *points at*.

## Why no new role is modeled

`docs/motion-saas-integration/AUTHORIZATION_MATRIX.md` also defines Template Manager and Designer roles; this codebase's `PermissionGatewayAdapter` (Phase 1) only distinguishes Platform Admin vs. site-bound Customer, a known gap recorded in the Phase 1 report. Phase 3 does not add new roles — `motion.manage_template_package` is deliberately an admin-only ability today, not a Template-Manager-scoped one, until that gap is addressed.
