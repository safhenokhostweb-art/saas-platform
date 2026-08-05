# Targeted Enablement

## Required gates (task section 7), mapped to real checks

| Gate | Enforced by |
|---|---|
| Global integration capability available | `App\Providers\MotionGraphicsAdapterServiceProvider` registered (unchanged since Phase 1) |
| Site-specific feature enabled | `sites.features['motion_graphics_integration']` (Phase 6), checked by `AbstractMotionAdapter::ensureEnabled()` on every call |
| Valid package assigned | `SiteAssetPackage.activation_status === ACTIVE` lookup in `MotionCustomerRuntimeService::buildManifestForSite()` — `no_active_package` otherwise |
| Valid license | `RealMotionLicenseGateway::validateEntitlement()` (Phase 6) — `license_denied` otherwise |
| Valid manifest | `CustomerRuntimeManifest`'s own construction (`ForbiddenFieldGuard`) plus customer-site's independent `MotionRuntimeManifestReader::isStructurallyValid()` re-check |
| Deployment state READY or ACTIVE | `SiteAssetPackage::STATUS_ACTIVE` — see CUSTOMER_RUNTIME_ARCHITECTURE.md for why this (not a separate Phase 5 lifecycle run) is the gate used here |

## Single-site-only enforcement

`MotionCustomerActivationService::enable()`:

```php
$otherEnabledSite = Site::query()
    ->where('id', '!=', $site->id)
    ->where('features->motion_graphics_integration', true)
    ->first(['id']);

if ($otherEnabledSite !== null) {
    // fails closed: 'another_site_already_enabled'
}
```

This is a real, enforced database check, not a documented convention — proven by `MotionCustomerActivationServiceTest::test_enable_refuses_when_another_site_is_already_enabled`, which creates Site A already enabled, then asserts Site B's `enable()` call is rejected and Site B's flag remains `false`.

## The only way to enable a site

`MotionCustomerActivationService::enable(Site $site, MotionAdapterContext $context)` — no route, no controller exposes this over HTTP in this phase (consistent with "no public asset-management APIs" and "no admin editing interface" being about the *asset* surface, not this internal orchestration call). An operator invokes it via `php artisan tinker` or a future thin CLI wrapper (not built this phase, since no real site exists to target — see AUDIT_REPORT.md's conclusion).

## The only way to disable / roll back

`MotionCustomerActivationService::disable(Site $site, MotionAdapterContext $context, string $reason)` — always available regardless of current state, idempotent (disabling an already-disabled site is a no-op that still records the audit trail).

## State at the end of this phase

No site was enabled by this phase's own actions — `enable()` was exercised only inside tests (against synthetic, `RefreshDatabase`-scoped test sites, never against the real application database). See FINAL_INTEGRATION_STATUS.md.
