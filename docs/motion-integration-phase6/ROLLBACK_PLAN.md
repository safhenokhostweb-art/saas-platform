# Rollback Plan (Phase 6)

Every change this phase made is additive and independently reversible. No destructive migration, no data deletion, no code removed from Phases 1-5.

## 1. Disable at the flag level (fastest, no deploy needed)

If a specific internal test site was enabled via `motion:internal-test-site {id}`, disable it:

```bash
php artisan motion:internal-test-site {id} --disable
```

This alone returns every site to the pre-Phase-6 effective state (Motion Graphics adapters fail closed via `ensureEnabled()`, exactly as in Phases 1-5), without touching any code or the `modules` row.

## 2. Revert the License Gateway to a fake (code-level, one line)

`app/Providers/MotionGraphicsAdapterServiceProvider.php`:

```php
// Revert:
$this->app->singleton(MotionLicenseGatewayContract::class, RealMotionLicenseGateway::class);
// to:
$this->app->singleton(MotionLicenseGatewayContract::class, FakeMotionLicenseGateway::class);
```

`FakeMotionLicenseGateway` was never deleted — it still exists at `app/Modules/MotionGraphics/Support/FakeMotionLicenseGateway.php` and is still exercised directly by `FakeGatewayFlowTest::test_a_denying_license_gateway_blocks_preparation`.

## 3. Remove the `modules` row (data-level, reversible)

```php
Module::where('slug', 'motion-graphics')->delete();
```

or simply don't run the new seeder entry. Nothing else references this row by foreign key — `RealMotionLicenseGateway` degrades to a clean `license_denied`/"module not registered" response if the row is absent (proven by `RealMotionLicenseGatewayTest::test_it_denies_when_the_module_is_not_registered_at_all`), it does not error.

## 4. Revert the feature-flag config default (code-level, one line)

`packages/saas-platform/core-utils/config/feature-flags.php`:

```php
// Revert:
'motion_graphics_integration' => env('FEATURE_MOTION_GRAPHICS_INTEGRATION', null),
// to:
'motion_graphics_integration' => env('FEATURE_MOTION_GRAPHICS_INTEGRATION', false),
```

This restores the Phase 1-5 forced-global-false behavior, making any leftover per-site `features` override permanently inert again (no data loss — the JSON key just stops being read).

## 5. Full revert to end of Phase 5

Reverting all of steps 2 and 4, plus dropping the `modules` row (step 3), plus deleting the new files below, returns the codebase to exactly Phase 5's shipped state:

- `app/Modules/MotionGraphics/Support/RealMotionLicenseGateway.php`
- `app/Modules/MotionGraphics/Support/ThemeTemplateReference.php`
- `app/Console/Commands/MotionGraphicsInternalTestSiteCommand.php`
- `docs/motion-integration-phase6-audit.md`, `docs/motion-integration-phase6/*`
- The `motion-graphics` entry in `database/seeders/ModuleSeeder.php`
- The Phase 6 test files under `tests/Feature/Modules/MotionGraphics/Phase6/`
- The `registerMotionGraphicsModule()` addition to `tests/Support/InteractsWithMotionRuntimeInstallation.php` and its call sites in the Phase 1-5 tests that were touched (see TEST_REPORT.md for the exact list)

## No migration to roll back

This phase added no schema migration — only a seeder entry (data, not schema) and a config default change. There is nothing for `php artisan migrate:rollback` to do here.

## Customer Site

Untouched. `customer-site/` was not modified in any step of this phase — rollback there is a non-issue.
