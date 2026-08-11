# Custom Motion Request

## Why a new method, not a new ability

Commission creation was, before this phase, 100% Platform-Admin-only (`MotionBespokeCommissionService`'s `motion.manage_bespoke_commission` ability — never granted to a Customer by `PermissionGatewayAdapter::CUSTOMER_ABILITIES`). The task explicitly still wants "Only Platform Admin can prepare and publish a Custom Motion" to hold, while also wanting a customer-initiated request to exist.

Rather than adding `motion.manage_bespoke_commission` to the customer ability list (which would also hand customers status transitions, asset attachment, and product linking — far beyond "submit a request"), this phase adds one new service method, `MotionBespokeCommissionService::requestFromCustomer()`, authorized via `motion.use` — the **same, already-existing** ability `SiteAssetPackageActivationService::activate()/rollback()` already grants a customer for self-service actions on their own site (`docs/motion-persistence/OWNERSHIP_RULES.md`: "activating/rolling back a site's own binding is consumption, site-scoped"). A custom-motion request is the same class of action. **`PermissionGatewayAdapter` was not changed at all.**

## What the method can and cannot do

`requestFromCustomer(Site $site, string $title, ?string $description, MotionAdapterContext $context)`:
- Creates a `MotionBespokeCommission` in `status=draft` for the caller's own site (tenant-isolation enforced by the existing `MotionAuthorizationAdapter::authorize()` — a siteId mismatch is denied before the method body ever runs, proven by `test_a_customer_cannot_submit_a_request_for_a_site_they_do_not_own`).
- Sets `title` + `description` only.
- Never sets `status` beyond `draft`, `asset_id`, or `product_id` — those remain exclusively reachable through the unchanged, `motion.manage_bespoke_commission`-gated admin methods (`transition()`, `attachAsset()`, `linkProduct()`).

## Flow

```
Customer submits request (title + description)
  ↓
MotionGraphicsController::requestMotion() — verifies the site belongs to this customer
  ↓
MotionBespokeCommissionService::requestFromCustomer() — status=draft
  ↓
Admin reviews, designs, transitions the commission (existing Phase X lifecycle, unchanged)
  ↓
Admin links a real motion_asset Product + publishes
  ↓
Customer sees "published — ready to buy" and purchases (PURCHASE_FLOW.md)
  ↓
Admin assigns after seeing the real purchase (Phase X, unchanged)
```
