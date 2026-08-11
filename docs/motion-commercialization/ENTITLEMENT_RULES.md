# Entitlement Rules

## Allowed / Denied (task section 4)

| Allowed | Enforced by |
|---|---|
| View assigned assets | Existing Phase 2 read contracts (`MotionAssetReadContract`), gated by `RealMotionLicenseGateway` per site — unchanged |
| Use published runtime assets | `MotionCustomerRuntimeService::buildManifestForSite()` (Phase 8), now real-entitlement-gated per site (Phase 9) |
| Use template packages | `MotionRuntimeInstallationService`/`SiteAssetPackage` (Phase 3-4) — unchanged, already tenant-isolated |

| Denied | Enforced by |
|---|---|
| Modify Master Assets | No customer-facing write path exists anywhere, in any phase — verified by absence |
| Edit global library | Same — no admin-editing surface was ever exposed to customers (Phase 7's admin controller is Platform-Admin-only, `AdminMiddleware`) |
| Access other sites' assets | `assertSiteMatches()` + the explicit `sitePackage->site_id !== context->siteId` re-check (Phase 5-8, unchanged) + now also real per-site `ModuleLicense` (a license for Site A grants nothing for Site B — EntitlementRulesTest, `test_a_license_never_grants_entitlement_to_a_different_site`) |
| Publish AI variants | No publishing capability was ever built on the SaaS-platform/customer-site side of the boundary — the standalone module's own AI workflow (if any) stays entirely on its side, untouched by any phase |

## The real gate, unmodified

`RealMotionLicenseGateway` (Phase 6) → `ModuleLicenseAdapter::checkEntitlement()` → `ModuleLicenseService::hasValidLicense()` (all pre-existing, all unmodified by this phase). Reclassifying `motion-graphics` to `type = paid` (MODULE_POSITIONING.md) changed *which branch* of already-existing code runs — `isCore()`'s unconditional allow (Phase 6-8) → the real `TYPE_PAID` branch, requiring an actual per-site `ModuleLicense` row. Zero lines of `RealMotionLicenseGateway` changed.

## Expiration, revoke, upgrade, downgrade — all proven against the real gateway, no fakes

`tests/Feature/Modules/MotionGraphics/Commercialization/EntitlementRulesTest.php` (7 tests, all passing):

- No license → denied.
- Valid admin-granted license → allowed.
- Expired license (`expires_at` in the past) → denied.
- Revoked license (`ModuleLicenseService::revoke()`) → denied.
- A license never grants entitlement to a *different* site.
- "Upgrade" (extend `expires_at`) restores entitlement after it lapsed; "downgrade" (shorten `expires_at`) removes it — same row, no new code path.
- A never-expiring license (`expires_at = null`) remains valid indefinitely.

## Two genuinely separate steps, not one

**Entitlement** (a `ModuleLicense` row existing and being valid) and **technical activation** (`sites.features['motion_graphics_integration'] = true`) are deliberately different things, proven in `SiteActivationFlowTest::test_the_full_purchase_to_runtime_flow`:

1. A real purchase (`Invoice` → `OrderFulfillmentService::fulfill()`) **does** automatically create a valid `ModuleLicense` — this was verified directly by running the test, not assumed: `App\Services\ModulePurchaseService::recordPurchaseFromInvoice()` (line 47-49) already calls `ModuleLicenseService::createFromInvoiceItem()` for every paid module on this platform, unmodified. This is real, existing, expected SaaS behavior — a paying customer becoming entitled is not the "automatic customer activation" the STRICT RULES forbid.
2. The site's runtime flag (`sites.features`) is asserted to **still be `false`** immediately after that purchase, in the same test. Flipping it is only ever done by `MotionCustomerActivationService::enable()` — never called by the purchase/fulfillment pipeline. **This** is the "no automatic customer activation" guarantee the STRICT RULES require, and it's enforced by the simple fact that no code path connects them, not by an extra check that could be bypassed.

## A note on `motion:internal-test-site` (Phase 6)

That command only flips `sites.features` — it was never connected to licensing (Phase 6 predates real per-site entitlement, since `motion-graphics` was `type = core` then, unconditionally entitled). Under Phase 9's `type = paid` reclassification, running that command alone on a site with no `ModuleLicense` will flip the flag but the site will still fail closed on the actual manifest fetch (`license_denied`) — free internal assignment now genuinely requires **both** `motion:internal-test-site {id}` **and** an admin-granted `ModuleLicense` (`ModuleLicenseController::grant()` or `ModuleLicenseService::grantByAdmin()`). This is intentional, not a bug: it makes "internal test site" go through the exact same real entitlement gate a real customer does, rather than a separate, weaker path.
