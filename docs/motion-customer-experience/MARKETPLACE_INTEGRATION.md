# Marketplace Integration

No new marketplace. Two product categories, two different visibility rules, both enforced in the one existing `StoreController`.

## A) Motion Graphics Module

`Product::TYPE_MODULE_ADDON`, `module_id` → the `motion-graphics` module (`type=paid` since Phase 9). `StoreController::modules()` already filters to `TYPE_MODULE_ADDON` products whose module passes `Module::scopeCustomerFacing()` (`type IN (core, paid)`). **Already appears in `/store/modules` today — confirmed by reading the query and by `test_the_motion_graphics_module_itself_is_purchasable_from_the_existing_module_store_unchanged`, zero lines of `StoreController` changed for this category.**

Purchase flow is exactly the task's diagram, entirely pre-existing: Customer → `/store/modules` → cart → `/checkout` → real payment gateway → `OrderFulfillmentService::fulfillModuleAddon()` (Phase 9, unmodified) → `ModuleLicense` → runtime availability (Phase 6/8, unmodified).

## B) Bespoke Motion Assets

`Product::TYPE_MOTION_ASSET` (Phase X). **Deliberately excluded** from both `StoreController::index()` and `::modules()` (new `excludeBespokeMotionAssets()` scope, folded into the existing `excludeInternalModuleProducts()` filter) — a bespoke Product represents one customer-specific commission; showing it in the generic storefront would let every other customer see and buy another customer's custom-designed asset (confirmed as a real, pre-Phase-11 gap in `AUDIT_REPORT.md` §6, since Products aren't customer-scoped anywhere in this codebase).

Instead, a bespoke Product only ever surfaces inside `/dashboard/motion-graphics`, scoped to the caller's own site's `MotionBespokeCommission` rows (`status=published` + linked `product_id`), and only ever enters the cart via the new `MotionGraphicsController::purchase()` action — which re-validates ownership, publish status, and the product link before calling the exact same `CartService::add()` every other product uses.
