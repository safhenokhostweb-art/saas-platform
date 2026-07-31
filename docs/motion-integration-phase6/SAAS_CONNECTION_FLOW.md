# SaaS Connection Flow (Phase 6)

## What changed vs. Phase 5

Phase 5 shipped the full deployment-preparation lifecycle behind two fake gateways. Phase 6 keeps the lifecycle unchanged and swaps one gateway for a real one:

| Boundary | Phase 5 | Phase 6 |
|---|---|---|
| `MotionLicenseGatewayContract` | `FakeMotionLicenseGateway` (constructor `allow: bool`) | `RealMotionLicenseGateway` → `ModuleLicenseServiceInterface` → `App\Adapters\ModuleLicenseAdapter` → `Module`/`ModuleLicenseService`/`ModuleAssignmentService` |
| `MotionDeploymentGatewayContract` | `FakeMotionDeploymentGateway` | **Unchanged** — still `FakeMotionDeploymentGateway`, deliberately (see below) |
| Module registration | None — no `modules` row existed | One `modules` row (`slug: motion-graphics`), seeded via `database/seeders/ModuleSeeder.php` |
| Template Builder | No connection | `App\Modules\MotionGraphics\Support\ThemeTemplateReference::for(ThemeTemplate $t)` derives the `template_reference` string `installTemplateForSite()` already accepted |
| Feature flag | Global default forced `false` | Global default `null` (per-site override reachable); `motion:internal-test-site` Artisan command is the only lever |

## The full chain

```text
Platform Admin picks a ThemeTemplate (real Template Builder, app/Models/ThemeTemplate.php)
  │
  ▼
ThemeTemplateReference::for($themeTemplate)          [Phase 6 — new, thin reference derivation]
  │
  ▼
MotionRuntimeInstallationService::installTemplateForSite()   [Phase 4 — unchanged]
  │  Template Asset Package Resolver (TemplateAssetPackageService, Phase 3)
  │  Compatibility Validation (TemplateBuilderCompatibilityValidator, Phase 4)
  │  Site Asset Package Creation (SiteAssetPackageActivationService, Phase 3)
  │  Runtime Projection Generation (MotionRuntimeProjectionReadContract, Phase 2)
  ▼
MotionDeploymentPreparationService::prepareDeployment()      [Phase 5 — unchanged logic,
  │  -> validateDeployment() -> approveDeployment() -> markReady()   now backed by a real license check]
  │
  │  Every step calls MotionLicenseGatewayContract, which is now
  │  RealMotionLicenseGateway -> ModuleLicenseAdapter::checkEntitlement()
  │  -> real Module/ModuleLicenseService (the same entitlement gate every
  │     other module on the platform goes through).
  ▼
DeploymentManifest (status: Ready) -> toCustomerRuntimePackage()
  │
  ▼
CustomerRuntimePackage — the only shape a customer site could ever receive
(list<RuntimeProjectionDTO>; no source, no AI metadata, no ownership data)
```

## Why the Deployment Gateway stays fake

Confirmed by direct code reading (docs/motion-integration-phase6-audit.md §4), not assumed: the platform's only real "deploy something to a site" pipeline (`CustomerInstallationOrchestrator` → SSH/cPanel/SFTP/Docker/DirectAdmin drivers → `ModuleDeploymentRequestService`) requires an already-provisioned site with a `remote_server_id` and exists to ship *files* to a *remote server*. Motion Graphics' "deployment" is DB-only — activating an already-persisted `SiteAssetPackage` for a site that already exists on the platform. There is no real service that plays that narrower role; the closest real analog is Motion Graphics' own `SiteAssetPackageActivationService` (Phase 3), which it already uses upstream of `MotionDeploymentPreparationService`.

Forcing a connection to the SSH pipeline here would be using it for a problem it doesn't solve — a violation of "do not bypass the existing deployment engine" in spirit, even while nominally "connecting" to it. `FakeMotionDeploymentGateway` therefore remains the bound implementation, now explicitly documented as a deliberate limitation (`app/Providers/MotionGraphicsAdapterServiceProvider.php`), not an oversight. A future phase that needs real cross-server delivery of Motion runtime assets will need new design work, not just a gateway swap.

## What a Platform Admin can now do (service layer only — no new UI/routes this phase)

- View Motion Graphics assets — via the existing Phase 2 read contracts (`MotionAssetReadContract`, etc.), unchanged.
- Assign Motion packages to templates — `MotionRuntimeInstallationService::installTemplateForSite()`, now reachable via a real `ThemeTemplate` reference instead of an arbitrary string.
- Assign template packages to selected sites — same call, `MotionAdapterContext::siteId` scopes it to one site; tenant isolation is enforced by `AbstractMotionAdapter::assertSiteMatches()` exactly as in every prior phase.
- Generate a runtime projection — `MotionRuntimeInstallationService::projectSitePackage()` / `MotionDeploymentPreparationService::prepareDeployment()...markReady()` → `DeploymentManifest::toCustomerRuntimePackage()`.

No controller, no route, no admin UI screen was added this phase — every capability above is exercised through the real container in tests, exactly as Phases 1-5 were. Building an actual admin-facing screen for these actions is future work.
