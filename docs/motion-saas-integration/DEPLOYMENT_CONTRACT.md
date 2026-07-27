# Deployment Integration Contract

The future Deployment Engine communicates only through the adapter façade:

```text
prepareTemplateAssets(templateRef, siteContext, requestMeta) -> PreparedTemplateAssets
resolveSitePackage(preparedId, brandRef, requestMeta) -> SitePackageCandidate
validatePackage(candidateId, policyContext) -> ImmutableValidationReport
activateRuntimeProjection(candidateId, approvalRef, requestMeta) -> ActivationDecision
rollbackPackage(activePackageId, restoreVersion, requestMeta) -> RestoreCandidate
```

Preparation and resolution are side-effect-free definitions where practical. Validation freezes candidate checksum. Activation is a Platform-owned atomic operation and requires Platform Admin approval, license recheck, exact checksum, optimistic active version, idempotency key, and audit correlation. The module never changes a site directly.

Rollback creates a new candidate from a verified historical package, validates it, and activates only after approval. Repeated requests with the same idempotency key return the same safe outcome. Timeout or partial failure leaves the old active projection intact.

