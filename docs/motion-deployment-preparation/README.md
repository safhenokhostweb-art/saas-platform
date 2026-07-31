# Motion Graphics SaaS Deployment Preparation

Status: **implemented — Phase 5 of the Motion Graphics SaaS integration**. This phase prepares (never executes) the bridge between template selection and a future real customer deployment.

```text
Template Builder
        |
        v
SaaS Deployment Engine        (MotionDeploymentPreparationService, new — orchestrates, never deploys)
        |
        v
Motion Site Package            (Phase 3/4 — SiteAssetPackage, reused unchanged)
        |
        v
Customer Runtime Projection    (Phase 2 — MotionRuntimeProjectionReadContract, reused unchanged)
```

## New in this phase

- `App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService` — see [README's Deployment Preparation Service section below] and [DEPLOYMENT_LIFECYCLE.md](DEPLOYMENT_LIFECYCLE.md).
- `App\Modules\MotionGraphics\DTO\DeploymentManifest` — immutable manifest. See [DEPLOYMENT_MANIFEST.md](DEPLOYMENT_MANIFEST.md).
- `App\Modules\MotionGraphics\DTO\CustomerRuntimePackage` — the narrow customer-facing output. See [RUNTIME_PACKAGE_MODEL.md](RUNTIME_PACKAGE_MODEL.md).
- `App\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract` + `Support\FakeMotionDeploymentGateway` — see [DEPLOYMENT_GATEWAY.md](DEPLOYMENT_GATEWAY.md).
- `App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract` + `Support\FakeMotionLicenseGateway` — see [LICENSE_BOUNDARY.md](LICENSE_BOUNDARY.md).
- Four new audit events, one per lifecycle state (`motion.deployment.{prepared,validated,approved,ready}`).

## Deployment Preparation Service

`MotionDeploymentPreparationService` composes Phase 1-4 building blocks — it never duplicates their logic:

| Responsibility (task scope item 1) | How |
|---|---|
| Receive deployment request | `prepareDeployment(SiteAssetPackage $sitePackage, MotionAdapterContext $context)` |
| Validate site package | tenant ownership + `activation_status === ACTIVE` + non-empty items |
| Validate template compatibility | `TemplateBuilderCompatibilityValidator` (Phase 4), re-run per item |
| Generate deployment manifest | see [DEPLOYMENT_MANIFEST.md](DEPLOYMENT_MANIFEST.md) |
| Prepare runtime projection | `MotionRuntimeProjectionReadContract` (Phase 2), per item |

It never executes a deployment, transfers a file, or modifies a customer server — every side effect is in-memory DTO construction plus a call into one of the two fake-only gateway contracts.

## Documents

| Document | Covers |
|---|---|
| [DEPLOYMENT_MANIFEST.md](DEPLOYMENT_MANIFEST.md) | Manifest fields, immutability, checksum |
| [DEPLOYMENT_GATEWAY.md](DEPLOYMENT_GATEWAY.md) | The provider-neutral deployment engine contract and its fake |
| [LICENSE_BOUNDARY.md](LICENSE_BOUNDARY.md) | The license integration boundary and its fake |
| [RUNTIME_PACKAGE_MODEL.md](RUNTIME_PACKAGE_MODEL.md) | The customer-facing runtime package output |
| [DEPLOYMENT_LIFECYCLE.md](DEPLOYMENT_LIFECYCLE.md) | Prepared → Validated → Approved → Ready → (future) Deployed |
| [SECURITY_RULES.md](SECURITY_RULES.md) | Every enforced boundary and its test |
| [INTEGRATION_STATUS.md](INTEGRATION_STATUS.md) | What's wired, what's deferred |

## What this phase does not do

No customer-site changes, no deployment to real customer servers, no production site activation, no source SVG or AI metadata exposure, no Master Asset modification, no license or authorization bypass, no public API, no change to the standalone module's ownership model. No commit, no push, no production deployment. `motion_graphics_integration` remains `false` by default.
