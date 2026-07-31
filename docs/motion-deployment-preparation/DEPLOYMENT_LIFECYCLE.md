# Deployment State Lifecycle

```text
Prepared -> Validated -> Approved -> Ready -> (future) Deployed
```

Task scope item 6: "No actual deployment." `Deployed` is reserved in `DeploymentManifest::STATUS_DEPLOYED` and modeled in `DeploymentLifecycle`'s transition table, but **no method on `MotionDeploymentPreparationService` ever produces it** — the furthest any code path in this phase reaches is `Ready`.

## The four reachable steps

| Method | Transition | What additionally happens |
|---|---|---|
| `prepareDeployment(SiteAssetPackage, context)` | *(none)* → `Prepared` | Full validation pipeline (site package, template compatibility, license, runtime projection) + `MotionDeploymentGatewayContract::prepareDeployment()` |
| `validateDeployment(manifest, context)` | `Prepared` → `Validated` | Re-checks entitlement (`validateEntitlement`) + `MotionDeploymentGatewayContract::validateTarget()` |
| `approveDeployment(manifest, context)` | `Validated` → `Approved` | Authorization only (`motion.approve_deployment`) — the deliberate human governance gate |
| `markReady(manifest, context)` | `Approved` → `Ready` | `MotionDeploymentGatewayContract::validateTarget()` again — the final pre-deployment target check |

Every step re-runs `ensureEnabled()`, authorization, and tenant-match checks — nothing about being "further along" in the lifecycle relaxes any boundary.

## Steps cannot be skipped

`DeploymentLifecycle::canTransition(from, to)` is consulted before every transition; calling e.g. `approveDeployment()` on a manifest still at `Prepared` fails closed with `invalid_lifecycle_transition` (`MotionDeploymentPreparationServiceTest::test_lifecycle_steps_cannot_be_skipped`). There is no backward transition either — a manifest can only move forward.

## Manifests are not persisted between steps

Nothing in this phase writes a `DeploymentManifest` to a database — each method takes the manifest returned by the previous step as its own input and returns a new one (`withStatus()`). A caller (a future orchestration layer, still out of scope) is responsible for holding the manifest reference across the four calls within one request/session. This mirrors the task's explicit absence of any "Database Strategy" section for Phase 5 — no new persistence was requested or added.

## Audit trail

Every transition records its own event (`motion.deployment.prepared/validated/approved/ready`) via the same `MotionAuditEvents`/`ActivityLoggerInterface` path used since Phase 1 — see `docs/motion-deployment-preparation/README.md`.
