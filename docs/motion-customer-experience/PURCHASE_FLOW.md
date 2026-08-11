# Purchase Flow

One pipeline for both product categories — no second payment/order/fulfillment system exists or was built.

```
Customer
  ↓
Product Catalog (StoreController — existing)
  ↓
Cart (CartService — existing, session-based)
  ↓
Checkout (CheckoutController::process() — existing: real Invoice + InvoiceItem rows)
  ↓
Payment Gateway (PaymentGatewayInterface — existing, real gateway redirect + callback verification)
  ↓
OrderFulfillmentService::fulfill($invoice) — existing, unmodified since Phase 9/X
  ├─ Product::TYPE_MODULE_ADDON  → fulfillModuleAddon()   → ModulePurchase + ModuleLicense (Phase 9)
  └─ Product::TYPE_MOTION_ASSET  → fulfillMotionAsset()   → MotionBespokePurchase only (Phase X)
```

For a bespoke asset, `fulfillMotionAsset()` (Phase X, unmodified this phase) creates **only** a `MotionBespokePurchase` row — it never touches `site_modules`, never creates a `MotionBespokeAssignment`, and never calls any deployment service. Runtime availability for a bespoke asset still requires a separate, explicit Platform-Admin `assign()` call (Phase X's `MotionBespokeAssignmentService`, unmodified) — exactly the task's "after successful payment, the Motion must NOT be directly/automatically activated" rule, proven again this phase by `test_purchasing_a_published_commission_adds_it_to_the_real_cart_and_never_auto_assigns` (asserts zero `MotionBespokePurchase`/`MotionBespokeAssignment` rows exist immediately after checkout — those only appear once the real invoice is actually paid and fulfilled, or an admin assigns).

The only genuinely new code in this whole pipeline is `MotionGraphicsController::purchase()` — and its entire job is calling the existing `CartService::add()` with a pre-validated `product_id` + `site_id`, identical to what `StoreController::addToCart()` already does for every other product.
