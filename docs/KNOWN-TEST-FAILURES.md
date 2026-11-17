# Known Baseline Test Failures — saas-platform

> The saas-platform Known Baseline Failure Registry required by
> `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md` §13. Created 2026-11-17 by
> the first task that had to classify failures as `PRE_EXISTING_FAILURE`
> (ADR-088, branch `feat/unified-onboarding-wizard`). Same method as
> `customer-site/docs/KNOWN-TEST-FAILURES.md`: an entry exists only with
> baseline proof, and an entry is removed when its test passes.

## Baseline verification

- Baseline commit: `a6c9db61` (`origin/main`, release WU-MAIN-CONFLICT-REVERT).
- Method: work stashed (`git stash push -u`), so the tree equals the commit,
  then each failing path re-run **serially** with
  `vendor/bin/phpunit <path>` (PHP 8.4.16, Windows, `saas_platform_test`).
  The full suite runs per directory on Windows (memory); never run two
  PHPUnit processes against `saas_platform_test` at once: they collide
  (missing `migrations` table, `DROP TABLE` deadlocks) and fake failures.
- The same failures, with the same counts, reproduce on the baseline commit
  and on the branch.

## Registry

| # | Test | Count | Cause | Kind |
|---|---|---|---|---|
| 1 | `Tests\Feature\DisasterRecovery\RestoreWizardTest` (whole class) | 43 errors | Needs Redis at `127.0.0.1:6379` (`Predis StreamInitException: connection refused`); Redis is not running on this workstation. | Environment |
| 2 | `Tests\Feature\Console\BackupTenantDatabaseCommandTest::test_backup_contains_only_the_seeded_tenants_rows_and_records_complete_coverage`, `::test_backup_of_a_genuinely_empty_tenant_still_succeeds_with_zero_inserts` | 2 failures | The backup command exits 1 where the test expects 0 (assertion at line 191). Not yet diagnosed; suspected local dump-tool availability. | Undiagnosed |
| 3 | `Tests\Feature\ThemeBuilder\TemplateLibraryTest::test_every_template_conforms_to_the_registry_schema` | 1 failure | Library templates `header-mobile-standard` (`drawer_action_url`) and `solar-header` (`nav_links[*].url`, mega-menu item URLs) hold links that are neither library routes nor absolute external URLs. | Package data defect |
| 4 | `Tests\Unit\AssetAccess\AssetAccessCatalogContractTest::test_every_canonical_widget_is_classified`, `::test_the_solar_kit_is_restricted_and_generic_assets_stay_public` | 1 failure + 1 error | Order-dependent: pass when the class runs alone, fail when run after `tests/Feature/AssetAccess` (host block types registered by an earlier test leak into the static `BlockRegistry`). | Test isolation |
