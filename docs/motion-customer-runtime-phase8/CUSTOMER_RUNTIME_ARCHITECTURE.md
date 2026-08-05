# Customer Runtime Architecture

## The chain

```text
Platform Admin (Phase 7)                 [saas-platform]
  activates a SiteAssetPackage for one site
        |
        v
sites.features['motion_graphics_integration'] = true    [Phase 6 mechanism, unchanged]
        |
        v
customer-site: MotionRuntimeManifestReader              [Phase 8, new]
  Http::withToken($license->license_key)
    ->get("{platform_url}/api/motion-runtime/manifest", ['domain' => ...])
        |
        v
saas-platform: routes/api.php "motion-runtime/manifest"  [Phase 8, new]
  middleware: tenant.api (existing, unchanged — LicenseServerService::validate())
        |
        v
MotionCustomerRuntimeApiController::manifest()            [Phase 8, new]
  resolves $site from $request->attributes->get('tenant_site')
        |
        v
MotionCustomerRuntimeService::buildManifestForSite()       [Phase 8, new]
  ensureEnabled() -> RealMotionLicenseGateway::validateEntitlement()
  -> active SiteAssetPackage lookup -> per-item MotionAssetReadContract
     + MotionRuntimeProjectionReadContract (Phase 2, unchanged)
        |
        v
CustomerRuntimeManifest JSON  (schema_version, assets[], manifest_checksum)
        |
        v
customer-site: MotionRuntimeManifestReader validates + caches (300s)
        |
        v
MotionRuntimeAssetResolver (lookup by id) -> MotionRuntimeRenderer (safe HTML)
        |
        v
<x-motion.graphics name="..."> Blade component, @once-guarded
```

## Why this shape, not the alternatives the audit considered

- **Not a module package**: no code needs to ship to customer-site for this feature — the consumer code (`MotionRuntimeClient`'s Phase 8 replacement) already exists in the codebase, deployed by whatever mechanism most recently shipped customer-site's code (out of this phase's scope to touch, per the user's explicit no-deployment-execution instruction).
- **Not a raw site-asset-package dump**: `SiteAssetPackage`/`SiteAssetPackageItem` carry host-internal bookkeeping (uuid pointers, activation timestamps, actor identifiers) that must never cross the boundary — `CustomerRuntimeManifest` is a deliberately narrower, versioned, independently-stable contract.
- **Not carried through the file-deployment pipeline**: confirmed in AUDIT_REPORT.md §2 — that pipeline ships files to a server via SSH/cPanel drivers; a manifest is fetched by already-present code, not delivered as a file.

## Why MotionCustomerRuntimeService bypasses MotionAuthorizationInterface

This was discovered during testing, not designed upfront — see TEST_REPORT.md for the full story. In short: `MotionRuntimeInstallationService`/`MotionRuntimeProjectionAdapter` (Phases 2-4) call `MotionAuthorizationInterface::authorize()`, which requires a real `Illuminate\Support\Facades\Auth::user()` — built for an authenticated Platform Admin or Customer User browsing the SaaS platform's own web/admin panel. The `tenant.api` channel (license_key + domain) that customer-site actually uses has no such session — it's a machine-to-machine credential, resolved by `AuthenticateTenantApi` into `$request->attributes->get('tenant_site')`, never into `Auth::user()`. Every other `tenant.api` controller (`ThemeMarketplaceApiController`, `ApiLifecycleController`, etc.) already reflects this: none of them call `MotionAuthorizationInterface`-equivalent user-ability checks either. `MotionCustomerRuntimeService` therefore calls the read contracts (`MotionAssetReadContract`, `MotionRuntimeProjectionReadContract`) directly — the same provider-neutral, published-only-safe primitives every other phase uses — and treats `MotionLicenseGatewayContract::validateEntitlement()` as the authorization for this specific channel, which is the correct trust boundary for a verified-license machine caller.

## What was NOT built

- **No persisted "published manifest" table.** The manifest is computed on demand from `SiteAssetPackage.activation_status === ACTIVE` every time it's fetched (and cached client-side for 300s). This avoids new schema entirely — see AUDIT_REPORT.md §3 item 4.
- **No real Deployment Gateway connection.** See DEPLOYMENT_GATEWAY_INTEGRATION.md — design only, per the explicit no-execution instruction.
- **No customer-site admin UI.** `MotionRuntimeManifestReader` et al. are pure backend consumer code; nothing customer-facing was added to any admin panel.
