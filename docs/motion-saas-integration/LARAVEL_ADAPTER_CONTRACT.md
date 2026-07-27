# Laravel Adapter Contract Design

These are language-neutral interface definitions, not PHP implementation.

```text
MotionAssetResolverContract
  resolveCanonical(assetId, version, context) -> ResolvedAsset
  validateCompatibility(asset, templateRequirement, siteProfile) -> CompatibilityDecision

TemplateAssetPackageContract
  validateManifest(templateManifest, context) -> ValidationReport
  resolveRequiredAssets(templateManifest, siteProfile) -> DependencyLock
  createPackageDefinition(dependencyLock, context) -> TemplatePackageDefinition

SiteAssetPackageContract
  createCandidate(templatePackage, brandIntent, context) -> SitePackageCandidate
  validateOwnership(candidate, siteId) -> OwnershipDecision
  lineage(packageId, version, context) -> LineageProjection

MotionRuntimeProjectionContract
  projectPublished(sitePackageId, version, context) -> PublishedRuntimeProjection
  validateProjection(projection) -> ValidationReport

MotionAuthorizationContract
  authorize(actorId, ability, resourceRef, siteId) -> AuthorizationDecision
  validateOwnership(resourceRef, expectedOwner, context) -> OwnershipDecision
  validateTenantAccess(siteId, resourceRef, context) -> AuthorizationDecision

MotionVersionContract
  find(assetId, version, context) -> MotionVersionRecord
  validateLifecycle(version, requestedTransition) -> LifecycleDecision
  createRollbackReference(assetId, sourceVersion, nextVersion, context) -> RestoreDraftReference
```

Every decision is explicit (`allowed/denied`, stable code, safe reason, policy version) and never throws sensitive internals across the boundary. Mutating commands require idempotency, optimistic version, exact checksum, actor/site scope, and audit correlation. `createRollbackReference` creates a new draft reference; it never rewrites history.

Existing module contracts remain canonical for storage, compiler, optimizer, preview, audit, provider, permissions, and deployment notification. The adapter composes them rather than creating parallel services.

