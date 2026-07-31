# Activation Flow

`SiteAssetPackageActivationService::activate(TemplateAssetPackage $templatePackage, ?string $brandVariantReference, MotionAdapterContext $context): SiteAssetPackage`

```text
1. ensureEnabled()                         — motion_graphics_integration must be true
2. authorize(actorId, 'motion.use', ...)   — MotionAuthorizationInterface (Phase 1)
3. assertSiteMatches(context->siteId)      — never trust the caller's claimed site
4. resolve the trusted Site from TenantContext
5. validateRequiredAssets()                — see below; ALL required assets must pass
                                              before ANY row is written
6. [transaction]
   a. findOrCreateSitePackage()            — new row, or upgrade the existing pointer
   b. bindItem() for each resolved asset   — install / upgrade / no-op
   c. captureSnapshot()                    — one immutable PackageSnapshot
   d. mark the SiteAssetPackage `active`
7. audit: motion.site.package.created (+ motion.asset.activated per item)
```

## Step 5 in detail — "Reject invalid package"

For every entry in `templatePackage.required_assets`:

| Check | How | Failure code |
|---|---|---|
| Asset exists, has a version, is published, is owned by this site | `MotionRuntimeProjectionReadContract::projectAsset(assetId, siteId)` — `null` fails all four at once | `asset_not_available_for_site` |
| Industry/theme/other compatibility | `MotionAssetResolverInterface::validateCompatibility()` (Phase 1, reused unchanged) against `templatePackage.compatibility_rules` | `compatibility_rejected` |
| License compatibility | `LicenseCompatibilityDecision::notImplemented()` — see note below | (currently always passes) |

Validation runs to completion **before** the transaction opens — if any required asset fails, nothing is written: no `SiteAssetPackage`, no items, no activations, no snapshot. This is what "reject invalid package" means operationally: atomic, all-or-nothing.

## License compatibility — an honest stub

No Motion-asset-specific license/entitlement persistence exists anywhere yet (`docs/motion-saas-integration/LICENSE_MODEL.md`: "a contract only; it does not implement pricing, payment, or entitlement persistence"). Rather than skip the step silently, `LicenseCompatibilityDecision::notImplemented()` returns an explicit, auditable `allowed=true, code='license_check_not_implemented'` — the pipeline shape already has a license gate; a future phase only needs to replace this one decision's implementation, not change how activation is structured.

## Idempotency

Calling `activate()` again with the same `TemplateAssetPackage` and the same resolved versions is a no-op for items that haven't changed (`bindItem()` returns early — no duplicate `AssetActivation` row). Calling it with a newer `TemplateAssetPackage` (same `template_reference`) upgrades in place — see [VERSION_BINDING_RULES.md](VERSION_BINDING_RULES.md) and [SITE_PACKAGE_MODEL.md](SITE_PACKAGE_MODEL.md).

## Rollback

A separate entry point, `SiteAssetPackageActivationService::rollback()` — see [ROLLBACK_MODEL.md](ROLLBACK_MODEL.md).
