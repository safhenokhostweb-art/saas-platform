# Deployment Engine Contract

`App\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract` (task scope item 3) — provider-neutral, mirroring the existing driver-abstraction pattern already used for customer installations (`app/Services/CustomerInstallation/Drivers/{CpanelDeploymentDriver,SshRemoteDriver,DockerDeploymentDriver,...}`), so a real deployment backend could implement this contract later without changing `MotionDeploymentPreparationService`.

```text
prepareDeployment(DeploymentManifest $manifest): DeploymentGatewayResult
validateTarget(string $siteReference, MotionAdapterContext $context): DeploymentGatewayResult
reportStatus(string $manifestUuid): string   // one of DeploymentManifest::STATUS_* or 'unknown'
```

## No real implementation this phase

`App\Modules\MotionGraphics\Support\FakeMotionDeploymentGateway` is the **only** implementation, and it is what `MotionGraphicsAdapterServiceProvider` binds by default. It:

- never executes a deployment,
- never transfers a file,
- never touches a customer server,
- only records, in a process-local array, which manifest uuids it has "seen" and what status they carried at that moment — purely so `reportStatus()` has something honest to answer.

It is constructor-configurable (`new FakeMotionDeploymentGateway(allow: false)`) so tests can prove the gateway boundary is never bypassed — see `tests/Feature/Modules/MotionGraphics/DeploymentPreparation/FakeGatewayFlowTest.php`.

## Not to be confused with the module's own `MotionDeploymentGatewayContract`

The standalone module already declares `SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract` — a single `published(string $motionUuid, int $version): void` notification hook the module calls into the host when a Motion version is published (Phase 1). This phase's contract lives in a completely different namespace (`App\Modules\MotionGraphics\Contracts\`), has a different purpose (host-orchestrated deployment preparation, not a module-to-host publish notification), and the two are never used interchangeably anywhere in this codebase.

## Where a real implementation would plug in

`MotionGraphicsAdapterServiceProvider::register()` is the single place `MotionDeploymentGatewayContract` is bound — the same pattern already used for every other Motion Graphics contract in this codebase. A future phase wiring a real deployment backend only needs to change that one binding.
