# License Integration Boundary

`App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract` (task scope item 5):

```text
checkAvailability(string $assetId, string $siteReference): LicenseGatewayDecision
validateEntitlement(string $siteReference, string $packageReference): LicenseGatewayDecision
```

## No real License system connected this phase

`App\Modules\MotionGraphics\Support\FakeMotionLicenseGateway` is the **only** implementation — a constructor-configurable allow/deny fake (`new FakeMotionLicenseGateway(allow: false)`), bound by default with `allow: true` so the rest of the preparation pipeline can be exercised end to end.

## Never skippable — "bypass License" is a strict rule

`MotionDeploymentPreparationService::prepareDeployment()` calls `checkAvailability()` for **every** item and `validateEntitlement()` once for the whole package — unconditionally, on every call, with no feature flag or actor able to skip either check. A denial from either one fails the entire preparation closed with the stable code `license_denied`, before a manifest is even constructed. See `tests/Unit/Modules/MotionGraphics/Services/MotionDeploymentPreparationServiceTest.php::test_it_rejects_when_the_license_gateway_denies_an_asset` and `tests/Feature/Modules/MotionGraphics/DeploymentPreparation/FakeGatewayFlowTest.php::test_a_denying_license_gateway_blocks_preparation`.

## Relationship to `docs/motion-saas-integration/LICENSE_MODEL.md`

That Phase 1 document defines the license *vocabulary* (`internal`/`customer_available`/`premium`/`restricted` classes, usage limits, allowed sites) but "does not implement pricing, payment, or entitlement persistence." This phase's contract is the **call site** a real implementation of that vocabulary would eventually sit behind — `MotionDeploymentPreparationService` already calls through the contract at the right two points (per-asset availability, per-package entitlement); only the fake's internals need replacing later, not the calling code.

## Where a real implementation would plug in

Same pattern as [DEPLOYMENT_GATEWAY.md](DEPLOYMENT_GATEWAY.md) — one binding line in `MotionGraphicsAdapterServiceProvider::register()`.
