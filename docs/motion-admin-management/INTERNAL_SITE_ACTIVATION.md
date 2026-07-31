# Internal Site Activation

## Task scope item 3, mapped to real code

| Step | Implementation |
|---|---|
| Admin selects Template + Internal Test Site | `{site}` route segment + `{template}` route segment, both real route-model-bound Eloquent models |
| Validate: feature flag | `AbstractMotionAdapter::ensureEnabled()` — `sites.features['motion_graphics_integration']` for the selected site (Phase 6) |
| Validate: license | Not applicable to activation — `MotionRuntimeInstallationService`/`TemplateAssetPackageService` never call the License Gateway (only `MotionDeploymentPreparationService`, Phase 5, does — see LICENSE_FAILURE coverage in TEST_REPORT.md) |
| Validate: ownership | `assertSiteMatches()` — the site set via `TenantContext::set()` must equal the `{site}` route segment's id; `MotionRuntimeInstallationService`'s own `assertSiteMatches($context->siteId)` re-checks it a second time |
| Validate: compatibility | `TemplateBuilderCompatibilityValidator::validate()`, run per required asset (Phase 4, unchanged) |
| Create Site Asset Package | `SiteAssetPackageActivationService::activate()` (Phase 3, unchanged), invoked via `MotionRuntimeInstallationService::installTemplateForSite()` |
| Generate Runtime Projection | `MotionRuntimeProjectionReadContract::projectAsset()` per item (Phase 2, unchanged) |
| No real deployment | Confirmed by absence: nothing in this phase's controller, service, or routes references `MotionDeploymentPreparationService`, `DeploymentManifest`, or any deployment gateway |

## How "Internal Test Site" is chosen

There is no new "is this an internal test site" flag or model concept. Any `App\Models\Site` row can be picked from the selector at `/admin/motion-graphics`; whether it actually *behaves* as an internal test site is entirely governed by whether `motion:internal-test-site {id}` (Phase 6) has been run for it. Picking an un-enabled site is harmless — every subsequent action fails closed with a clear, localized "not enabled" message (`AccessControlTest::test_every_action_fails_closed_for_a_site_that_was_never_enabled`) rather than silently doing nothing or erroring.

## Idempotency

Activating the same template for the same site twice does not create a second `SiteAssetPackage` row — `MotionRuntimeInstallationService::installTemplateForSite()`'s existing idempotency logic (Phase 4, `resolveOrCreateTemplatePackage()`/`canonicalize()`) is unchanged and reused as-is; confirmed by `InternalSiteActivationTest::test_a_second_activation_for_the_same_site_and_template_is_idempotent`.

## Cross-site isolation

Activating for Site A never creates, modifies, or exposes anything for Site B — confirmed by `InternalSiteActivationTest::test_activating_for_one_site_never_touches_a_different_site` (checks the database directly, and checks Site B's own preview page never mentions Site A's template reference).
