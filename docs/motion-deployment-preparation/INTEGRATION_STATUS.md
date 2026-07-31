# Integration Status

## What is wired end-to-end (through the real container, real host DB test suite)

- `MotionDeploymentPreparationService::prepareDeployment()` — full validation pipeline (site package, template compatibility, license availability/entitlement, runtime projection) producing an immutable, checksummed `DeploymentManifest`.
- The four-step lifecycle (`prepareDeployment` → `validateDeployment` → `approveDeployment` → `markReady`), each independently authorized, tenant-checked, and audited.
- `MotionDeploymentGatewayContract`/`MotionLicenseGatewayContract`, each bound to their only implementation (`FakeMotionDeploymentGateway`/`FakeMotionLicenseGateway`), consulted on every relevant call and swappable in tests to prove neither is bypassable.
- Feature flag gating (`motion_graphics_integration`, default `false`) on every entry point, identical to every Phase 1-4 adapter/service.

## What remains deferred (carried since Phase 2)

Same limitation as every prior phase: the standalone module's own `MotionGraphicsServiceProvider` is still not registered on the host app, so real database-backed reads are proven by the module's own isolated Testbench suite, not the host test suite (which fakes the Phase 2 read contracts here exactly as Phase 2-4 did).

## What is explicitly out of scope for this phase (unchanged, still true after implementation)

- No customer-site changes — nothing in `customer-site/` was touched.
- No deployment to real customer servers, no production site activation.
- No public API — no route, no controller.
- No real deployment engine or license system connection — both gateways remain fakes.
- No new database migration — this phase adds no persistence; `DeploymentManifest` is a pure, in-memory value object.
- No UI.

## Known gaps

- `DeploymentManifest` is not persisted between lifecycle calls — a caller must hold the reference itself across `prepareDeployment()` → `validateDeployment()` → `approveDeployment()` → `markReady()`. A future phase introducing real orchestration (e.g. a queued deployment job) will need to decide where that state lives.
- `CustomerRuntimePackage` has no delivery mechanism yet — it is a defined shape, not a served response.
- Performance budget / responsive support / license status still trace back to Phase 4's honest "not implemented" stubs inside `TemplateBuilderCompatibilityValidator` — this phase's own license gateway is a *separate*, additional check (entitlement/availability), not a replacement for those three.
- `motion.approve_deployment` and `motion.prepare_deployment` remain admin-only abilities with no Template Manager/Designer distinction, consistent with the gap recorded since Phase 1.

## Feature flag state at the end of this phase

`motion_graphics_integration` remains `false` by default in `packages/saas-platform/core-utils/config/feature-flags.php` — unchanged by this phase.
