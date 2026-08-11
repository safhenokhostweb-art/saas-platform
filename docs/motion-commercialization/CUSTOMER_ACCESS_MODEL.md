# Customer Access Model

## What the customer panel shows (task section 7)

Through the real, only-live customer marketplace endpoint (`App\Http\Controllers\Api\TenantMarketplaceController::modules()`, unmodified this phase), Motion Graphics now appears exactly like every other paid module:

| Site state | What's shown |
|---|---|
| No `ModuleLicense` | `owned: false`, `installed: false`, `purchasable: true`, `purchase_url` set — the "upgrade suggestion" the task asks for, using the platform's existing purchase-link mechanism, not a new one |
| Valid `ModuleLicense`, not yet technically activated | `owned: true` — the customer sees they own it; whether the runtime is actually rendering is a separate, admin-controlled step (see ENTITLEMENT_RULES.md's "two genuinely separate steps") |
| Valid `ModuleLicense` + technically activated | `owned: true`, and the customer's actual site renders Motion assets (Phase 8, unmodified) |

Proven directly in `CustomerVisibilityTest` (2 tests): a non-entitled site sees `owned: false, purchasable: true`; an entitled site sees `owned: true`.

## What the customer can never do (task section 7 + STRICT RULES)

| Forbidden | Why it's structurally impossible, not just policy |
|---|---|
| Upload Master assets | No upload endpoint exists anywhere in either repository, in any phase |
| Edit global templates | No customer-facing template-editing route exists; `TemplateAssetPackageService`/admin controller are Platform-Admin-only (`AdminMiddleware`, Phase 7, unchanged) |
| Access the AI workflow | The standalone module's AI capability (if any) has never been connected to any host-side route or contract, in any of the 9 phases |
| Change ownership | No ownership-transfer capability exists; `ModuleLicense.site_id` is set once at grant/purchase time and only an admin (`ModuleLicenseController`) can revoke/re-grant |

## No new customer-facing code this phase

Every row in the table above is produced by code that already existed before Phase 9 — `TenantMarketplaceController`, `Module::paidMarketplace()`, `EntitlementResolver`/`VisibilityResolver` (`app/Services/ModuleEcosystem/`). Motion Graphics simply now satisfies the query conditions those already-built, already-tested components expect from any commercial module (MODULE_POSITIONING.md). This directly satisfies "do not duplicate the Module system" — there was nothing to build.
