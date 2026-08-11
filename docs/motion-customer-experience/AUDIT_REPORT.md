# Phase 11 — Audit Report

Mandatory pre-implementation audit. No code was written before this document.

## 1. Existing customer purchase flow

A real, in-app, authenticated customer purchase flow already exists — separate from the tenant.api JSON channel Phases 8/9/X integrated with:

`StoreController@index / @modules` (Blade, `/store`, `/store/modules`) → `CartService` (session cart, `App\Services\CartService`) → `CheckoutController::process()` (creates a real `Invoice` + `InvoiceItem`s, resolves a real `PaymentGatewayInterface`, redirects to the gateway) → gateway callback → `CheckoutController::callback()` (verifies payment, marks `Invoice::STATUS_PAID`) → **`OrderFulfillmentService::fulfill($invoice)`** — the exact same, unmodified service Phase 9 and Phase X already dispatch through by `Product.type`.

This is the concrete mechanism the task calls "Existing Product Catalog → Existing Order System → Existing Payment System → Existing Fulfillment." Phase 11 is the first phase to build customer-facing Blade UI in front of it; every phase before this only reached the fulfillment layer via the API.

## 2. Existing marketplace structure

Products are filtered for customer visibility via `Module::scopeCustomerFacing()` (`type IN (core, paid)`) inside `StoreController`. Since Phase 9 already reclassified `motion-graphics` to `type = paid` and seeded a real `module_addon` Product for it, **"Motion Graphics Engine" already appears in `/store/modules` today, with zero code changes** — confirmed by reading `StoreController::modules()`'s query directly, not assumed.

`StoreController::index()` has no product-type filter beyond `excludeInternalModuleProducts()` (which only inspects `module_id`-bearing rows) — meaning any `Product::TYPE_MOTION_ASSET` row would appear there to **every** logged-in customer, not just the one it was commissioned for. Products are not customer-scoped anywhere in this codebase. This is a real gap (§6).

## 3. Existing entitlement model

Two independent, already-real layers, neither needing duplication:
- `ModuleLicense` (Phase 9): module-granularity, "does this site have Motion Graphics at all."
- `MotionBespokeAssignment` / `MotionBespokePurchase` (Phase X): asset-granularity, "which specific bespoke asset, at which version, does this site have."

`MotionBespokeCustomerRuntimeService` (Phase X, `app/Modules/MotionGraphics/Services/`) already exposes exactly the read surface a customer page needs: `bespokeAssetsForSite()` (published+assigned, entitlement-gated) and `commissionsStatusForSite()` (purchased flag + business status + assigned version, ownership-scoped, not entitlement-gated — a customer must see "in review" even before the runtime is technically on).

## 4. Where Motion Graphics should appear

- **Module purchase** ("Motion Graphics Engine"): already surfaces in the existing `/store/modules` grid — no new page.
- **Bespoke assets**: must NOT be listed in the generic `/store` pages (§2's gap) — need a dedicated customer section scoped to the caller's own site(s), reusing the existing cart/checkout for the actual purchase action.
- A real customer dashboard shell already exists: `layouts.admin` (shared by admin and customer, branches on `auth()->user()->is_admin`), with a data-driven sidebar (`App\Helpers\CustomerMenu`, mirroring `App\Helpers\AdminMenu`) toggled per-item via `Setting::get('customer_menu_<key>')`. This is where "Motion Graphics" belongs as a new sidebar entry.

## 5. Existing components reusable for Motion

`CartService::add()`, `CheckoutController` (unmodified), `OrderFulfillmentService` (unmodified, already dispatches `TYPE_MOTION_ASSET` since Phase X), `EnsureProfileIsComplete` middleware, `CustomerMenu` + `resources/views/customer/*` Blade conventions (`@extends('layouts.admin')`), `MotionBespokeCommissionService` / `MotionBespokeCustomerRuntimeService` / `MotionBespokeAssignmentService` (all Phase X, unmodified), `Module::scopeCustomerFacing()`.

## 6. Architecture gaps found

1. `AdminProductController::store()/update()` validates `type` against a hardcoded `in:license_template,module_addon,support_plan` list — `motion_asset` (Phase X) is rejected by the standard product-creation UI today. Needs widening (additive) or admins can't create a sellable bespoke-motion Product without raw DB access.
2. `StoreController`'s generic listings would leak a customer-specific bespoke Product to every other customer (§2) — needs an explicit exclusion, mirroring the existing `excludeInternalModuleProducts()` pattern.
3. Commission creation is 100% Platform-Admin-only today (`MotionBespokeCommissionService`'s `motion.manage_bespoke_commission` ability, never granted to a Customer by `PermissionGatewayAdapter`) — correct and unchanged for design/publish/assign, but the task explicitly asks for a customer-initiated "request." No customer-writable path exists yet. `motion.use` is already the established ability for "customer self-service action on their own site" (it already gates `SiteAssetPackageActivationService::activate()/rollback()` for customers) — the narrowest fix is a new, tightly-scoped service method authorized via that same existing ability, not a new ability string and not a weakening of `motion.manage_bespoke_commission`.
4. No customer-facing controller/route/view exists for Motion Graphics at all (expected — this is precisely Phase 11's scope).
