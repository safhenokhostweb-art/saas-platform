# Security Rules

Task scope item 8 ("Security" tests), each enforced boundary and where it is tested.

| Rule | Enforcement | Test |
|---|---|---|
| AI metadata leakage prevention | `ForbiddenFieldGuard::assertClean()` inside DTO constructors + HTTP response text check | `AdminSecurityTest::test_the_runtime_preview_response_never_contains_forbidden_fields`, `::test_forbidden_field_guard_would_reject_the_underlying_projection_data` |
| Cross tenant access | `MotionGraphicsAdminService::previewRuntimeProjection()` independently checks `sitePackage->site_id === context->siteId`, in addition to `assertSiteMatches()` | `AdminSecurityTest::test_an_admin_cannot_preview_another_sites_package_via_a_crafted_url`, `MotionGraphicsAdminServiceTest::test_preview_runtime_projection_rejects_a_package_belonging_to_another_site` |
| Unauthorized package modification | Route-level (`AdminMiddleware`) + service-level (`PermissionGatewayAdapter`'s Customer allowlist excludes all four admin abilities) | `AdminSecurityTest::test_a_customer_cannot_modify_a_package_through_any_admin_motion_graphics_route`, `AccessControlTest::test_a_customer_cannot_create_a_package_even_by_guessing_the_route` |
| No Master Asset editing exposed to customers | No route exists for it — Master Asset creation/editing was never in scope for any phase, admin or customer; this phase does not add one | N/A by absence |
| Feature flag stays off by default | Global config `null` (Phase 6, unchanged); this phase enables no site | `AccessControlTest::test_every_action_fails_closed_for_a_site_that_was_never_enabled`, `Phase6\InternalTestSiteCommandTest::test_the_global_default_is_null_not_a_forced_value` (unaffected, still passing) |
| Existing Authorization never bypassed | Every `MotionGraphicsAdminService` method calls `MotionAuthorizationInterface::authorize()` — the same interface every Phase 1-6 service already used; no new authorization mechanism was introduced | `MotionGraphicsAdminServiceTest` ability-mismatch tests (one per method) |
| Deployment Engine never replaced or bypassed | This phase adds no reference to `MotionDeploymentPreparationService`, `CustomerInstallationOrchestrator`, or any deployment gateway anywhere in its own code | Verified by absence (grep) |
| Standalone module ownership unchanged | No file under `packages/saas-platform/modules/motion-graphics/` was modified this phase | `git status` |
| Customer-site unchanged | No file under `customer-site/` was modified this phase | `git status` (separate working directory) |

## Two independent tenant checks, deliberately (same pattern as Phase 5)

1. `assertSiteMatches($context->siteId)` inside `authorizeAdmin()` — is the caller's claimed site the same as the platform-trusted tenant (the one the controller just `set()`)?
2. `(string) $sitePackage->site_id !== $context->siteId` inside `previewRuntimeProjection()` — does the *specific resource* actually belong to that (now-confirmed) tenant?

Both must pass, for the same reason documented in Phase 5: an admin can be honestly "acting as" the correct site and still be handed the wrong resource id.
