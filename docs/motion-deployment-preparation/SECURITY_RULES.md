# Security Rules

Task scope item 8, each enforced boundary and where it is tested.

| Rule | Enforcement | Test |
|---|---|---|
| Customer cannot prepare deployment | `motion.prepare_deployment`/`motion.approve_deployment` are absent from `PermissionGatewayAdapter`'s Customer allowlist (`motion.view`/`motion.use` only) | `DeploymentPreparationSecurityTest::test_customer_cannot_prepare_deployment`, `CustomerDeniedTest` |
| Invalid site rejected | `AbstractMotionAdapter::assertSiteMatches()` compares the caller's claimed site against the platform-trusted tenant (never the reverse) | `DeploymentPreparationSecurityTest::test_invalid_site_context_is_rejected` |
| Unpublished asset rejected | `MotionAssetReadContract::findByCanonicalId()` returning `null` fails closed with `asset_not_available`, before any manifest is built | `DeploymentPreparationSecurityTest::test_an_unpublished_asset_is_rejected` |
| Source data leakage prevented | `DeploymentManifest`/`CustomerRuntimePackage` only ever hold already-allowlisted `MotionAssetReferenceDTO`/`RuntimeProjectionDTO` values; `ForbiddenFieldGuard` reruns at the boundary; reflection confirms no extra field exists | `DeploymentPreparationSecurityTest::test_source_and_ai_data_can_never_leak_into_the_manifest` |
| Cross tenant package rejected | `prepareDeployment()` independently checks `sitePackage->site_id === trustedSiteId`, in addition to the context-level `assertSiteMatches()` check | `DeploymentPreparationSecurityTest::test_a_cross_tenant_package_is_rejected` |

## Two independent tenant checks, deliberately

`prepareDeployment()` checks tenant identity **twice**, for two different things:

1. `assertSiteMatches($context->siteId)` — is the *caller's claimed identity* the same as the platform-trusted tenant? (Catches a forged/stale context.)
2. `(string) $sitePackage->site_id !== $context->siteId` — does the *specific resource being acted on* actually belong to that (now-confirmed) tenant? (Catches an admin correctly authenticated as themselves, but handed the wrong site package reference.)

Both must pass. Neither alone is sufficient — an admin can be honestly who they say they are and still be pointed at the wrong resource, or a resource can genuinely belong to the claimed site while the claim itself is spoofed.

## License and authorization are never bypassable

Every one of the four lifecycle methods calls `ensureEnabled()` and `authorize()` before doing anything else; `prepareDeployment()`/`validateDeployment()` additionally always call through `MotionLicenseGatewayContract` — there is no code path, feature-flag state, or actor role that skips either check. See [LICENSE_BOUNDARY.md](LICENSE_BOUNDARY.md).

## No public API surface

This phase adds no route, no controller, no middleware. Every new class is reachable only through the Laravel container by code that already holds a `MotionAdapterContext` — consistent with the task's "create public APIs" prohibition.
