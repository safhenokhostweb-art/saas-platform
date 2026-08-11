# Security Model

Per the task's explicit instruction, menu visibility is never treated as security — every check below is enforced at the route/service/query layer, proven by real HTTP requests in `tests/Feature/Modules/MotionGraphics/CustomerExperience/CustomerMotionGraphicsTest.php`.

| Requirement | Enforcement | Test |
|---|---|---|
| Customer accessing another site's motion | `MotionGraphicsController` re-resolves the target site from `auth()->user()->customer->sites` (never trusts a route/body `site_id` alone) before any read; `purchase()` additionally checks `commission->site_id === $site->id`, `403` otherwise | `test_a_customer_cannot_submit_a_request_for_a_site_they_do_not_own`, `test_a_customer_cannot_purchase_another_sites_bespoke_commission` |
| Direct API access without valid entitlement | Unchanged from Phase X — `tenant.api` `bespoke-manifest`/`bespoke-commissions` endpoints, real license-key auth | Phase X's `SecurityTest` (unmodified, still passing) |
| Unpublished motion / draft version | `purchase()` requires `status=published`; runtime display requires `MotionAssetReadContract` to return non-null, which is structurally impossible for draft/review versions | `test_an_unpublished_commission_cannot_be_purchased_by_guessing_the_route` |
| Expired/revoked license | `ModuleLicenseService`/`ModuleLicense::isValid()`, unchanged since Phase 9 | Phase 9's `EntitlementRulesTest` (unmodified, still passing) |
| Master Asset / AI metadata leakage | This phase's controller never reads `MotionGraphic`/`MotionGraphicVersion` directly — only Phase X's already-hardened read services, which never fetch `source_definition_json`/`editable_schema_json`/`ai_metadata_json` (`ForbiddenFieldGuard`, unchanged since Phase 1) | Phases 1-8's existing tests (unaffected, unmodified) |
| Cross-customer product leakage in the storefront | `excludeBespokeMotionAssets()` in `StoreController` | `test_a_bespoke_motion_asset_product_never_appears_in_the_generic_storefront` |
| Unauthenticated access | Standard `auth` middleware group (unchanged) | `test_a_guest_is_redirected_to_login` |
| Customer escalating to admin-only commission actions (transition/attach/publish) | `PermissionGatewayAdapter::CUSTOMER_ABILITIES` was not modified — still only `motion.view`/`motion.use`; `requestFromCustomer()` cannot reach `transition()`/`attachAsset()`/`linkProduct()`, which remain gated by the unchanged `motion.manage_bespoke_commission` | Phase X's existing `test_a_customer_actor_cannot_assign_or_rollback_or_revoke` (unmodified, still passing) plus the fact that no customer-reachable route in this phase calls those methods at all |
