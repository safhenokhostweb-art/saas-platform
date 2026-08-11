# Test Report

## New tests this phase

**16 new tests across 4 new files, all passing:**

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/Modules/MotionGraphics/Commercialization/EntitlementRulesTest.php` | 7 | No license (denied), valid license (allowed), expired (denied), revoked (denied), cross-site isolation, upgrade/downgrade via `expires_at`, never-expiring license |
| `tests/Feature/Modules/MotionGraphics/Commercialization/SiteActivationFlowTest.php` | 2 | The full task-section-5 workflow (purchase → license → entitlement → site permission → manifest) through real, unmodified commerce code; revoked-license blocks enablement |
| `tests/Feature/Modules/MotionGraphics/Commercialization/CustomerVisibilityTest.php` | 2 | Non-entitled site shows purchasable/not-owned; entitled site shows owned |
| `tests/Feature/Modules/MotionGraphics/Commercialization/SecurityValidationTest.php` | 5 | Expired/revoked license denied via the real HTTP API; no-license denied via API; cross-site credential isolation via API; unauthorized customer denied on the admin dashboard |

**2 existing files updated for the Phase 9 reclassification** (both fully passing):

- `tests/Feature/Modules/MotionGraphics/Phase6/ModuleRegistrationSecurityTest.php` — assertions changed from "type=core, invisible to marketplace" (Phase 6's intentional state) to "type=paid, visible in the real marketplace query, still excluded from the release-gated catalog" (Phase 9's intentional state) — 5 tests.
- `tests/Unit/Modules/MotionGraphics/Services/MotionCustomerActivationServiceTest.php` — the single-site-cap test replaced with a real multi-site test (Phase 8's cap no longer exists) — 8 tests.

## Real bugs found and fixed during this phase's own testing

1. **`ModuleLicense.granted_by` foreign-key violation** — test fixtures across this session used an unsaved, in-memory `User` with a fake `id`; `grantByAdmin()` writes that id as a real FK. Fixed by persisting a real `User` row in the new tests' `admin()` helper.
2. **A genuine, pre-existing (Phase 8) design bug in `MotionCustomerActivationService::enable()`**: it called `prepare()` first, which requires the feature flag to already be on (`ensureEnabled()`) — but `enable()`'s entire job is to turn that flag on. This was invisible in Phase 8's own tests because they used a mocked `TenantContext` that always returned `true` regardless of real DB state; Phase 9's tests use the real `TenantContext`, which surfaced it immediately. Fixed with a `withFlagTemporarilyOn()` helper: the flag is flipped inside the same DB transaction that validates entitlement/package/health, and rolled back (both in the DB and explicitly in the in-memory model) if validation fails. This also fixed the same latent issue in `healthCheck()`/`prepare()` for pre-activation dry runs.
3. **Wrong initial assumption about purchase → license timing**: the first version of `SiteActivationFlowTest` assumed a purchase alone must never create a `ModuleLicense`. Running the test proved this wrong — `ModulePurchaseService::recordPurchaseFromInvoice()` already calls `ModuleLicenseService::createFromInvoiceItem()` automatically, for every paid module on this platform, unmodified. The test (and ENTITLEMENT_RULES.md) were corrected to reflect this real, existing, correct behavior — real entitlement on purchase is expected SaaS behavior, not the "automatic customer activation" the STRICT RULES forbid (that's specifically about the runtime flag, which stays separate — see the same test's next assertion).
4. **`TemplateAssetPackage` unique-constraint collisions** in two multi-site test fixtures (the same pattern already fixed once in Phase 8) — fixed with `firstOrCreate` in both new fixture helpers.

## Full regression suites

`php artisan test --filter=MotionGraphics`: **286 passed, 3 failed** — the same pre-existing, unrelated `Tests\Feature\MotionGraphicsOwnershipTest` failures documented in every phase's report since before this session began. Up from 268 at the end of Phase 8 (18 new/changed, all passing).

`php artisan test --filter=Module` (task's required Module/License/Plan/Deployment regression sweep, 800 tests): **784 passed, 16 failed** — all 16 confirmed pre-existing and unrelated to this phase:
- 14 × `SupportTicketModuleTest` — the same pre-existing `Unknown column 'email'` factory gap documented since Phase 6.
- 1 × `CpanelApiCapabilitiesTest` — the same pre-existing `INSTALLER_MASTER_KEY` local environment gap documented since Phase 6/8.
- 1 × `CustomerSiteModulesUpdateFlowTest` — an unrelated admin site-creation redirect assertion (`/admin/sites` vs `/admin/sites/{id}/edit`); confirmed to fail identically in isolation (not a Phase 9 side effect), and touches `SiteController`/site-creation code this phase never modified.

All 7 `Tests\Unit\ModuleEcosystem\*` classes, `Tests\Feature\ModuleEcosystem\TenantMarketplaceModulesApiTest`, and `Tests\Feature\ModuleEcosystem\ModuleVisibilityFlowTest` pass in full — the exact real, live commercial-visibility surfaces this phase relies on, unaffected.

## Security tests, mapped to task section 9

| Requirement | Test |
|---|---|
| Unauthorized customer access | `SecurityValidationTest::test_unauthorized_customer_cannot_read_admin_dashboard_licensing_fields` |
| Expired license | `SecurityValidationTest::test_direct_api_access_with_an_expired_module_license_is_denied` + `EntitlementRulesTest::test_an_expired_license_is_denied` |
| Revoked license | `SecurityValidationTest::test_direct_api_access_with_a_revoked_module_license_is_denied` + `EntitlementRulesTest::test_a_revoked_license_is_denied` |
| Cross-site access | `SecurityValidationTest::test_a_licensed_sites_manifest_can_never_be_fetched_using_another_sites_credentials` + `EntitlementRulesTest::test_a_license_never_grants_entitlement_to_a_different_site` |
| Direct API access | All of `SecurityValidationTest` — every case goes through the real HTTP endpoint, not a service-level shortcut |
| Master Asset exposure | Unaffected, still proven by Phases 6-8's existing tests (no new exposure surface added) |
| AI metadata leakage | Unaffected, still proven by Phases 1-8's existing `ForbiddenFieldGuard` tests at every DTO boundary |
