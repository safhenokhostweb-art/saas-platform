# Test Report (Phase 7)

## New tests this phase

**Unit** — `tests/Unit/Modules/MotionGraphics/Services/MotionGraphicsAdminServiceTest.php` (10 tests): ability-gating for every method (denies the wrong ability, allows the right one), real dashboard counts against real DB rows, flag-disabled fail-closed, tenant-context-mismatch fail-closed, package assignment persists correctly, activation creates an active `SiteAssetPackage`, cross-tenant preview rejection, owned-package preview success.

**Feature** — `tests/Feature/Modules/MotionGraphics/Admin/` (16 tests across 5 files):

| File | Covers |
|---|---|
| `AdminWorkflowSuccessTest` | Full HTTP journey (select-site → dashboard → templates → assign → activate → preview → preview.show) + compatibility-preview mismatch reporting |
| `AccessControlTest` | Guest redirected to login; customer denied on every route; feature-flag-disabled fails closed on every route with no DB write |
| `InternalSiteActivationTest` | Activation creates the package with no deployment reference anywhere; cross-site isolation; idempotent re-activation |
| `AdminSecurityTest` | AI-metadata/source-data absence (response text + direct `ForbiddenFieldGuard` check); cross-tenant crafted-URL rejection; customer cannot modify a package via any route |
| `LicenseFailureTest` | Real license denial (module row missing / retired) still blocks `MotionDeploymentPreparationService::prepareDeployment()` — see note below |

**26 new tests, all passing.**

## Note on "license failure" coverage

The new admin workflow itself (assign/activate/preview) never calls the License Gateway — only `MotionDeploymentPreparationService` (Phase 5/6) does, and this phase's UI deliberately stops at "Generate Runtime Projection," matching the task's own workflow diagram (no deployment-prep step is exposed in the admin UI). `LicenseFailureTest` therefore exercises the real license check at the service layer it actually lives in (`RealMotionLicenseGateway` → `ModuleLicenseAdapter`, both real since Phase 6), proving an admin who goes one step further than this phase's UI still hits a genuine license check rather than a silent gap.

## A required fix, not a workaround

`App\Http\Middleware\CheckTenantMaintenance` called a nonexistent method (`TenantContextContract::getSiteId()`) — a genuine pre-existing bug, unrelated to Motion Graphics, that this phase's controller was the first code path to trigger (by being the first to legitimately call `TenantContext::set($site)` before a full HTTP request). Fixed to call the real method (`id()`). No test previously covered this middleware (`grep` across `tests/` for it returns nothing); the fix is required for the admin UI to function over real HTTP at all, in production as well as in tests. See ADMIN_WORKFLOW.md.

## Full Motion Graphics suite

```
php artisan test --filter=MotionGraphics
Tests: 3 failed, 246 passed (547 assertions)
```

Up from 220 passing at the end of Phase 6 (26 new, all passing). The 3 failures are the same pre-existing, unrelated `Tests\Feature\MotionGraphicsOwnershipTest` failures (`SQLSTATE[42S22]: Unknown column 'email'`) documented in every prior phase's report.

## Regression suite

`php artisan test tests/Feature/Admin` (the broader admin-controller area, the most likely place to catch a regression from editing `routes/web.php` and `CheckTenantMaintenance.php`):

```
Tests: 3 failed, 54 passed (171 assertions)
```

All 3 failures are `RuntimeException: INSTALLER_MASTER_KEY environment variable is not set` in `CustomerDeploymentController*`/`CustomerRemoteServerAdminController*` tests — a pre-existing local environment configuration gap (also seen identically in Phase 6's own regression run), unrelated to routing or middleware changes.

`customer-site/` was not touched; no regression run was needed there.
