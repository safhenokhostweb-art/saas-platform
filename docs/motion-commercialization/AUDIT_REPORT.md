# Phase 9 — Mandatory Pre-Implementation Audit

Produced before any Phase 9 code changes. Synthesizes a dedicated research pass over the commercialization layer plus direct verification of every load-bearing claim (file:line spot-checked, not taken on trust).

## 1. How existing modules become sellable

`App\Models\Product` (`app/Models/Product.php`) is the sellable unit — three types: `TYPE_LICENSE_TEMPLATE`, `TYPE_MODULE_ADDON`, `TYPE_SUPPORT_PLAN`. A `TYPE_MODULE_ADDON` product has `module_id` pointing at a `Module` row. Admin CRUD: `Admin\ProductController`. A module becomes sellable by: (a) `Module.type = TYPE_PAID` with a price, and (b) a `Product` row (`type = module_addon`, `module_id`, `price_irr`, `is_active = true`) referencing it. `Module::scopePaidMarketplace()` additionally requires `marketplace_visible = true` and `license_required = false` for the *customer catalog* query specifically (`TenantMarketplaceController`); `ModuleAvailabilityRule` (a stricter, separate discovery-layer query) additionally requires a published `ModuleRelease` and an active `Product` for `paid_marketplace` modules.

## 2. How plans assign modules

**They don't — there is no Plan/bundle model in this codebase.** Verified directly: `App\Models\Plan` does not exist; the only "Plan" is `App\Models\SupportPlan` (`app/Models/SupportPlan.php`), a support/SLA tier (response-time terms, duration), entirely unrelated to module entitlement — `Product::TYPE_SUPPORT_PLAN` sells *that*, not a module bundle. `Site` has no `plan_id`/`plan()` relation (grepped, zero matches). The only `plan_id` string anywhere is a mislabeled activity-log payload key inside `EntitlementLedger::logEntitlementGranted()` (`app/Services/EntitlementLedger.php:356`) — `'plan_id' => $module->id`, not an actual plan reference.

**Commercialization here is entirely à la carte, per-module** — not plan-tiered. This directly affects §3 below.

## 3. How add-ons work

"Add-on" = `Product::TYPE_MODULE_ADDON`, a **Product type**, not a `Module` type (`Module` only has `TYPE_CORE`/`TYPE_PAID`). Full flow: `Invoice` → `InvoiceItem` (references the `Product`) → paid → `App\Services\OrderFulfillmentService::fulfill()` (`app/Services/OrderFulfillmentService.php:25-36`) dispatches by `$product->type`; for `module_addon`, `fulfillModuleAddon()` (lines 108-183):
1. `ModuleAssignmentService::activatePaidPurchase($site, $moduleId)` — dispatches a **real code-deployment** via `ModuleDeploymentRequestService`, sets `site_modules.is_active = true` once dispatch succeeds.
2. `ModulePurchaseService::recordPurchaseFromInvoice()` — creates a `ModulePurchase` row (`site_module_purchases`), the permanent ownership record.
3. `EntitlementLedger::grant(..., SOURCE_PURCHASE)` — append-only audit write (confirmed still true: `EntitlementLedger`'s own docblock states it "NEVER decides run-time module visibility... the runtime gate remains site_modules.is_active + valid license").

There is no time-limited/trial add-on concept distinct from a full purchase — `ModuleLicense.expires_at` is the only temporality mechanism, nullable, set only when an admin explicitly picks a date in `ModuleLicenseController::grant()` (`app/Http/Controllers/Admin/ModuleLicenseController.php:56-106`, backed by `ModuleLicenseService::grantByAdmin()`). No cron ever auto-expires a license (`isExpired()`/`refreshStatus()` exist but nothing schedules them — expiry is evaluated lazily, on read, only).

**Gap found (not caused by this phase, worth knowing):** `ModuleLicenseController::revoke()` (line 50-54) calls `ModuleLicenseService::revoke()` only — it never calls `EntitlementLedger::revoke()`, leaving the ledger's entitlement row `STATUS_ACTIVE` after a real revoke. Currently harmless (the runtime gate checks `ModuleLicense` directly, not the ledger), but a latent inconsistency.

## 4. How customer sites receive entitlements

The runtime gate for a *standard* paid module is `site_modules.is_active` (`ModuleAssignmentService::isModuleEnabledForSite()`), set true by `activatePaidPurchase()`'s real deployment dispatch. Motion Graphics **does not use this gate** — established since Phase 6: its runtime gate is `sites.features['motion_graphics_integration']` (a per-site JSON flag) plus an active `SiteAssetPackage` (Phase 3) plus `RealMotionLicenseGateway::checkEntitlement()` (module-row-based). This is a deliberate, already-audited difference (Phase 6 §4: "the fine-grained... restriction is deliberately enforced one layer up, by the feature flag... not by this gateway"), not a gap to fix — see §5's design decision.

Customer-facing visibility: `Api\TenantMarketplaceController::modules()` (`routes/api.php:154-155`) lists `Module::paidMarketplace()` modules with `installed`/`owned`/`available` status via `DeploymentResolver`/`EntitlementResolver`/`VisibilityResolver` (`app/Services/ModuleEcosystem/`) — a flat marketplace listing (no plan comparison, since no Plan exists).

## 5. Where Motion Graphics should connect

- **Commercial layer (new this phase):** `Module.type = TYPE_PAID` + one `Product` row (`type = module_addon`) — reusing the exact pipeline in §1/§3 verbatim. This is a **reversal of Phase 6's seeder choice** (`type = core, marketplace_visible = false`, deliberately non-commercial for internal-testing purposes) — an intentional, expected evolution: Phase 6 was "prove the technical integration safely with zero commercial surface"; Phase 9 is "make it commercially available." Not a contradiction; a new phase with a new goal.
- **Entitlement check (no code change needed):** `RealMotionLicenseGateway` (Phase 6) already wraps `ModuleLicenseAdapter::checkEntitlement()` unmodified. Once `Module.type` becomes `paid`, `checkEntitlement()`'s existing branching automatically starts requiring a real `ModuleLicense` row (`hasValidLicense()`'s `type === TYPE_PAID` branch) instead of unconditionally allowing every site (`isCore()` branch) — the exact real, per-site, revocable/expirable entitlement Phase 9 asks for, with **zero changes to Motion Graphics' own adapter code**. This is the cleanest possible "use existing systems, don't duplicate" outcome available.
- **Free internal assignment (task requirement):** already fully supported, unmodified — `ModuleLicenseController::grant()`/`ModuleLicenseService::grantByAdmin()` creates a `ModuleLicense` row directly, no `Invoice`/`Product` purchase required. No new code needed for this requirement either.
- **Technical site activation (Phase 6-8, must be reconsidered):** `MotionCustomerActivationService::enable()` (Phase 8) hard-blocks a *second* site from ever being enabled (`another_site_already_enabled`) — correct for "one controlled test site," wrong for "a real commercial product with potentially many paying customers." §"Site Activation Flow" below documents the fix: replace the single-site cap with a **real-entitlement-required** gate (`hasValidLicense()` must be true for the target site) — stricter and more correct for commercial use, while still satisfying "do not enable globally" (each site needs its own valid entitlement) and "do not activate real customers automatically" (the flag flip stays a distinct, deliberate admin action, never triggered by purchase fulfillment itself).

## 6. The "no fully-wired real premium module" finding

No seeder, factory, or production data anywhere creates a genuinely `type=paid` module with a real `Product`, purchased by a real customer — confirmed by grep (zero `Product::create`/`firstOrCreate` outside `ProductController`, zero paid-module seed data). The closest precedents are three tests that fabricate the full scenario in-memory: `tests/Unit/Services/OrderFulfillmentServiceModuleTest.php`, `tests/Feature/Wave80bFulfillmentAuditLogTest.php`, `tests/Unit/ModuleEcosystem/ModuleAvailabilityRuleTest.php`. These are the real recipe this phase's own tests follow.

## Conclusion — no blocking conflict, one significant scope adjustment

**No architectural conflict.** The à la carte Module/Product/Invoice/ModuleLicense/EntitlementLedger pipeline fully supports everything Phase 9 needs, reused unmodified. **One task assumption does not hold**: "Plan Integration... use existing Plan management" cannot be satisfied literally because no Plan management exists to use. Per the explicit "Do NOT redesign architecture" rule, **no new Plan model is built this phase** — inventing one would itself be the kind of architecture change the task forbids. Instead, PLAN_INTEGRATION.md documents how the requested tiers (Basic/Business/Premium/Enterprise) map onto the real, existing primitives (distinct `Product` pricing/limits + admin-grant tooling) as a business-packaging convention, not a new coded entity — satisfying "do not hardcode plans" by not hardcoding anything resembling a plan.

Proceeding to implementation.
