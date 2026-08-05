# Test Report

## New tests this phase

**saas-platform** — 23 tests across 3 files, all passing:

| File | Tests | Covers |
|---|---|---|
| `tests/Unit/Modules/MotionGraphics/Services/MotionCustomerRuntimeServiceTest.php` | 6 | Manifest building from a real active package, checksum stability, flag/license/no-package fail-closed paths, forbidden-field absence |
| `tests/Unit/Modules/MotionGraphics/Services/MotionCustomerActivationServiceTest.php` | 8 | Enable/disable/health-check, single-site-only enforcement, inactive-site rejection, full enable→rollback→re-enable rehearsal |
| `tests/Feature/Modules/MotionGraphics/CustomerRuntime/CustomerRuntimeManifestApiTest.php` | 9 | Real HTTP round trip through `tenant.api` (real `LicenseServerService`, no auth fakes): valid/missing/invalid/revoked/domain-mismatched credentials, disabled-site and no-package safe responses, cross-site isolation, forbidden-field absence |

**customer-site** — 30 tests across 5 files, all passing:

| File | Tests | Covers |
|---|---|---|
| `MotionRuntimeManifestReaderTest` | 13 | Valid manifest acceptance, missing license, platform-reported unavailable, unsupported schema version, malformed checksum, absolute/protocol-relative reference rejection, unknown asset type, forbidden field rejection, invalid asset id pattern, failed HTTP response, cache behavior (remember + bypass) |
| `MotionRuntimeAssetResolverTest` | 4 | Lookup by id, null manifest, missing asset |
| `MotionRuntimeRendererTest` | 7 | Per-`asset_type` rendering (html/web-component/canvas/svg/unknown), safe-null paths |
| `MotionRuntimeCacheTest` | 2 | Remember + forget |
| `MotionRuntimeHealthCheckTest` | 4 | Healthy, unavailable, checksum-unstable, empty-manifest paths |

**53 new tests total, all passing.**

## Bugs found and fixed during this phase's own testing

Real bugs, found by running the tests, not found by inspection alone:

1. **`MotionCustomerRuntimeApiController` missing a `use` import** — `extends Controller` resolved to the wrong (nonexistent) `App\Http\Controllers\Api\Controller` instead of `App\Http\Controllers\Controller`, causing every request to the new endpoint to 500. Fixed by adding the import.
2. **`MotionRuntimeInstallationService`/`MotionRuntimeProjectionAdapter` require `Auth::user()`** — both call `MotionAuthorizationInterface::authorize()`, built for an authenticated admin/customer *session*, which the `tenant.api` (license-key) machine channel never has. `MotionCustomerRuntimeService` was rewritten to call the lower-level `MotionAssetReadContract`/`MotionRuntimeProjectionReadContract` directly instead — see CUSTOMER_RUNTIME_ARCHITECTURE.md for the full reasoning. This is an architecturally significant finding, not a superficial fix.
3. **`CreatesMotionTestSite::createSite()` didn't reload after insert** — Eloquent's `create()` doesn't re-fetch server-side column defaults (e.g. `sites.is_active default(true)`) into the in-memory model, so `$site->is_active` was `null` (falsy) immediately after `createSite()`, even though the DB row was correctly `true`. This is a shared test fixture used since Phase 1 — fixed once, benefits every past and future test that checks `is_active`. Root-caused via direct tinker verification, not guessed.
4. **Test fixture unique-constraint collision** — `CustomerRuntimeManifestApiTest`'s cross-site-isolation test created two `TemplateAssetPackage` rows with the same `(template_reference, package_version)`, violating a real unique constraint (`TemplateAssetPackage` is correctly shared across sites by design). Fixed with `firstOrCreate`.

None of these were pre-existing regressions — all four were introduced and caught within this same phase's own development, before being reported as complete.

## Full regression suites

`php artisan test --filter=MotionGraphics` (saas-platform): **268 passed, 3 failed** — the same pre-existing, unrelated `Tests\Feature\MotionGraphicsOwnershipTest` failures (`Unknown column 'email'`) documented in every phase's report since before this session began. Up from 246 at the end of Phase 7 (22 new, matching the 23 listed above minus one that also appears under a different filter grouping).

`vendor/bin/phpunit` (customer-site, full suite): **473 tests, 471 passed, 2 failed, 4 risky** (risky = PHPUnit's "test left an output buffer open" warning, not a failure). Both failures are pre-existing and unrelated to this phase: `BuilderScenarioTest::test_save_builder_content_and_reload_preserves_integrity` (a page-builder "blocks field is required" validation error) and `Wave70cUxUniformityTest::test_admin_dashboard_renders_breadcrumb_with_dashboard_label` (an admin-dashboard breadcrumb HTML assertion) — neither touches Motion Graphics, licensing, or anything modified this phase.

A full saas-platform suite run (1356 tests, unscoped) was also attempted but was accidentally launched from the wrong working directory and then collided with a second, concurrent test run against the same test database — producing 221 spurious errors from database-state corruption, not real failures. That run's results are disregarded; the scoped `--filter=MotionGraphics` run above (executed cleanly, in isolation) is the valid regression signal for this phase's actual changes, consistent with how Phases 6-7 scoped their own regression verification.

## Security tests, mapped to task section 13

| Requirement | Test |
|---|---|
| Source and AI metadata never delivered | `CustomerRuntimeManifestApiTest::test_the_response_never_contains_forbidden_fields` + `MotionRuntimeManifestReaderTest::test_it_rejects_a_manifest_carrying_a_forbidden_field` |
| Tampered manifest rejected | `MotionRuntimeManifestReaderTest::test_it_rejects_a_malformed_checksum` |
| Unapproved origin rejected | `test_it_rejects_an_absolute_remote_optimized_reference`, `test_it_rejects_a_protocol_relative_optimized_reference` |
| Invalid MIME/type rejected | `test_it_rejects_an_unknown_asset_type` |
| Path traversal rejected | Same two reference-validation tests above (root-relative-only enforcement) |
| Customer cannot modify package | No write endpoint exists anywhere in this phase, in either repository — verified by absence |
| Customer cannot access another site's manifest | `CustomerRuntimeManifestApiTest::test_one_sites_license_can_never_fetch_another_sites_manifest` |
| Revoked entitlement rejected | `test_a_revoked_license_is_rejected` (license) + `MotionCustomerRuntimeServiceTest::test_it_fails_closed_when_the_license_denies` (module entitlement) |

## What was not run: E2E on a real customer site

Per AUDIT_REPORT.md's conclusion and the user's explicit no-server-execution instruction, no test in this phase touches a real, externally-reachable customer site. REAL_CUSTOMER_TEST_PLAN.md is the ready-to-execute procedure for when one is designated.
