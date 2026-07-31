# Runtime Installation Flow

`MotionRuntimeInstallationService::installTemplateForSite(templateReference, requiredAssetRefs, optionalAssetRefs, compatibilityRules, brandVariant, context): ActivationPlan`

```text
1. ensureEnabled()                          — motion_graphics_integration must be true
2. authorize(actorId, 'motion.manage_template_package', ...)  — Platform Admin only
3. assertSiteMatches(context->siteId)       — never trust the caller's claimed site
4. brand variant approval check             — see BRAND_VARIANT_FLOW.md
5. audit: motion.template.activation.started (always recorded, even on later failure)
6. [outer transaction — "all operations must be atomic, failure rolls back everything"]
   a. resolveOrCreateTemplatePackage()      — TemplateAssetPackageService, reused; idempotent (IDEMPOTENCY_RULES.md)
   b. validateTemplateBuilderCompatibility() — TemplateBuilderCompatibilityValidator, new (TEMPLATE_ACTIVATION_MODEL.md)
   c. SiteAssetPackageActivationService::activate() — Phase 3, reused unchanged:
        - re-validates existence/publication/ownership/compatibility/license per item
        - creates/updates SiteAssetPackage, SiteAssetPackageItem(s), AssetActivation(s), PackageSnapshot
   d. projectSitePackage()                  — Phase 2's MotionRuntimeProjectionReadContract, reused
   e. build ActivationPlan
7. on success: audit motion.template.activation.completed, return the plan
8. on any exception: audit motion.template.activation.failed, re-throw (the transaction already rolled back)
```

## What "generate activation plan" means here

The task lists "generate activation plan" as one of the service's responsibilities, ordered *after* "create site package" in its own bullet list. This phase takes that literally: the **plan is the method's return value** — `ActivationPlan` — not a separate pre-flight dry-run call. It bundles:

```json
{
  "sitePackage": "the SiteAssetPackage Eloquent row",
  "items": [{ "asset_id": "...", "version": 3, "activation_type": "install|upgrade" }],
  "projections": ["RuntimeProjectionDTO, ...— see PROJECTION_SECURITY.md"],
  "wasAlreadyUpToDate": false
}
```

A caller (a future Template Builder integration, still out of scope) inspects this plan to know exactly what was installed and what a customer site would now receive.

## What this service must not do (task scope item 1)

- **Copy physical master files** — nothing here ever touches a file on disk; every asset reference stays an opaque `{asset_id, version}` pair resolved through Phase 2's read contracts.
- **Modify assets** — no write path to the module exists anywhere in this service.
- **Publish assets** — publishing a Motion version remains exclusively `MotionLibraryService::publish()` inside the standalone module, unreachable from here.

## Atomicity in practice

The outer `DB::transaction()` wraps calls into `TemplateAssetPackageService::createTemplateAssetPackage()` and `SiteAssetPackageActivationService::activate()`, both of which open their own nested transactions (Laravel uses savepoints automatically). If projection generation or any later step throws, **everything** written in steps 6a-6c rolls back — including a newly created `TemplateAssetPackage` row. A `TemplateAssetPackage` that already existed and was merely *reused* (idempotent path) is, by definition, unaffected by a later failure.
