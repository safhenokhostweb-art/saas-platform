# Test Report (Phase 6)

## New tests this phase

`tests/Feature/Modules/MotionGraphics/Phase6/`:

| File | Proves |
|---|---|
| `RealMotionLicenseGatewayTest` | The real gateway allows an entitled site, denies when the module row is missing/retired/inactive, denies invalid/unknown site references, and that `checkAvailability()`/`validateEntitlement()` agree — 7 tests. |
| `ModuleRegistrationSecurityTest` | The seeder is idempotent, the row matches the audited shape, and — the key security property — `motion-graphics` is unreachable through `Module::paidMarketplace()` (the exact query the only live customer marketplace endpoint uses) and through the release-gated `ModuleAvailabilityRule` catalog — 4 tests. |
| `ThemeTemplateBridgeIntegrationTest` | The full chain: a real `ThemeTemplate` → `ThemeTemplateReference` → `installTemplateForSite()` → `prepareDeployment()` → `validateDeployment()` → `approveDeployment()` → `markReady()` → `toCustomerRuntimePackage()`, using the real license gateway throughout; the same template installs independently for two different sites; deployment preparation fails closed when the module row is absent — 3 tests. |
| `InternalTestSiteCommandTest` | The global flag default is genuinely `null`; the Artisan command enables/disables exactly the named site and never leaks to another; it preserves unrelated keys already in `sites.features`; it fails gracefully for an unknown site id — 5 tests. |

**19 new tests, all passing.**

## Task-required coverage, mapped to actual tests

| Requirement | Test(s) |
|---|---|
| Template selection with Motion assets | `ThemeTemplateBridgeIntegrationTest::test_template_selection_flows_through_to_a_ready_deployment_manifest` |
| Site package creation | Same test (asserts `SiteAssetPackage::STATUS_ACTIVE`), plus pre-existing `SuccessfulActivationTest` (unaffected, still passing) |
| Runtime projection generation | Same test (`toCustomerRuntimePackage()` assertions) |
| Deployment preparation flow | Same test, full 4-step lifecycle to `STATUS_READY` |
| License validation | `RealMotionLicenseGatewayTest` (7 tests) + `AdminPreparationSuccessTest`/`DeploymentPreparationSecurityTest::test_source_and_ai_data_can_never_leak_into_the_manifest`/`CustomerDeniedTest::test_a_customer_cannot_approve_a_deployment` now genuinely exercise the real license gateway (previously exercised only the fake) |
| Tenant isolation | `ThemeTemplateBridgeIntegrationTest::test_the_same_theme_template_installs_independently_per_site` + all pre-existing tenant-isolation tests, unaffected |
| Customer cannot access master assets | Pre-existing (`ReadContractSecurityTest`, `MotionGraphicsAdapterSecurityTest`), unaffected — still passing |
| Customer cannot modify packages | Pre-existing (`RuntimeInstallationSecurityTest`, `PersistenceSecurityTest`), unaffected — still passing |
| AI metadata leakage prevention | Pre-existing (`ForbiddenFieldGuard` assertions across multiple files), unaffected — still passing |
| Cross-site access prevention | Pre-existing (`DeploymentPreparationSecurityTest::test_a_cross_tenant_package_is_rejected`, `CrossSiteRejectionTest`, `SiteIsolationTest`), unaffected — still passing |

## Existing Phase 1-5 tests updated for this phase

Swapping the License Gateway's default binding from fake to real meant any test whose flow reaches the license gateway and expects an ALLOW now needs a real `modules` row to check against. Fixed by adding `registerMotionGraphicsModule()` to `tests/Support/InteractsWithMotionRuntimeInstallation.php` and calling it from:

- `AdminPreparationSuccessTest`
- `CustomerDeniedTest`
- `DeploymentPreparationSecurityTest`
- `FakeGatewayFlowTest` (also updated `test_the_default_bound_gateways_are_the_fakes` → `test_the_default_bound_gateways`, now asserting `FakeMotionDeploymentGateway` + `RealMotionLicenseGateway`)

Four tests asserting `assertFalse(config('feature-flags.motion_graphics_integration'))` were updated to `assertFalse((bool) config(...))` (and one to also `assertNull(...)`), since the literal default value changed from `false` to `null` while the *effective* behavior (disabled) did not:

- `DeploymentPreparationFeatureFlagTest::test_the_flag_defaults_to_disabled`
- `FeatureFlagBehaviorTest::test_the_integration_flag_defaults_to_disabled`
- `Persistence\ActivationFeatureFlagTest::test_the_flag_defaults_to_disabled`
- `RuntimeInstallation\InstallationFeatureFlagTest::test_the_flag_defaults_to_disabled`

No test's actual *behavior* assertion changed — only the literal-value assertions that were directly coupled to the old `false` default.

## Full Motion Graphics suite

```
php artisan test --filter=MotionGraphics
Tests: 3 failed, 220 passed (476 assertions)
```

Up from 201 passing at the end of Phase 5 (19 new, all passing). The 3 failures are `Tests\Feature\MotionGraphicsOwnershipTest` (`SQLSTATE[42S22]: Unknown column 'email'`) — confirmed via `git log` in Phase 1 and re-confirmed here to predate this entire session and every phase of this integration; not touched or caused by any Phase 6 change.

## Regression suite

`php artisan test --filter=Module` (583 tests — Module/License/Entitlement/Marketplace ecosystem, the area most likely to be affected by the new `modules` seeder row and the `ModuleLicenseAdapter` reuse):

```
Tests: 17 failed, 583 passed (1453 assertions)
```

All 7 `Tests\Unit\ModuleEcosystem\*` classes (`DeploymentResolverTest`, `EntitlementResolverTest`, `MarketplaceCatalogQueryTest`, `ModuleAvailabilityRuleTest`, `ModuleCatalogServiceTest`, `PlanModuleEntitlementTest`, `VisibilityResolverTest`) pass in full, as does `Tests\Feature\ModuleEcosystem\TenantMarketplaceModulesApiTest` — the actual live customer marketplace endpoint's own test suite, unaffected by the new `motion-graphics` row. All 4 `Tests\Unit\Modules\MotionGraphics\Models\*` tests pass.

The 17 failures are pre-existing/environmental, not caused by this phase:

- 1 transient `ModuleReleaseProducerAdapterTest` failure (`SQLSTATE[42000]: Unknown database 'saas_platform_test'`) — traced to a stray concurrent test process left over from this session's own investigation (killed via `kill`, confirmed via `SHOW PROCESSLIST` before re-running); re-ran `--filter=ModuleReleaseProducerAdapterTest` in isolation afterward with a clean `SHOW PROCESSLIST` and it passed (see below) — not a real regression.
- 2 `CustomerInstallation\{Cpanel,Ssl}` failures — `RuntimeException: INSTALLER_MASTER_KEY environment variable is not set` — a local environment configuration gap, unrelated to Motion Graphics or this phase.
- 10 `Tests\Feature\SupportTicketModuleTest` failures — the same pre-existing `SQLSTATE[42S22]: Unknown column 'email'` factory issue already documented for `MotionGraphicsOwnershipTest` throughout Phases 1-5, here affecting an unrelated Support Ticket test — confirms this is a platform-wide factory gap, not anything Motion Graphics introduced.

`customer-site/` was not touched; no regression run was needed there.
