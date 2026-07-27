# Security Boundary — Read Contracts

## Safety by construction, not by filtering

Every provider in `src/Support/Read/` is written so the forbidden columns are never fetched, rather than fetched-then-stripped:

- `MotionGraphic::$hidden` = `source_definition_json`, `editable_schema_json`, `ai_metadata_json`
- `MotionGraphicVersion::$hidden` = `source_snapshot`, `editable_schema_snapshot`, `ai_metadata_json`

None of `DatabaseMotionAssetReader`, `PublishedRuntimeProjectionProvider`, `DatabaseMotionVersionReader`, `TemplateAssetReader`, or `SitePackageReader` references any of those six column names anywhere in their implementation. A reviewer can confirm the boundary holds by grepping the six names against `src/Support/Read/` and finding zero matches — there is no "allowlist filter" step to audit or accidentally bypass.

## Lifecycle gate

`isReadable(status)` (duplicated identically in `DatabaseMotionAssetReader` and `DatabaseMotionVersionReader`) is the single choke point for "may this version cross the boundary at all":

```text
published, approved -> always readable
retired              -> readable only if motion-graphics.deprecated_versions_readable
draft, review        -> never readable
```

Every read-contract method funnels through this gate before building a read model.

## Tenant isolation

`PublishedRuntimeProjectionProvider::projectAsset()` and `SitePackageReader`'s two methods each independently re-check `ownership_type === OWNERSHIP_CUSTOMER_SPECIFIC && owner_site_identifier !== $siteIdentifier` before including an asset — the same rule `MotionLibraryService::runtimeManifest()` and `MotionDeploymentService::assign()` already enforce at their own boundaries. A customer-specific asset can never be returned for a site that does not own it, and a global asset is unaffected.

## Laravel adapter side

`App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard` (Phase 1) still runs on every DTO the adapters build from a read model — a second, independent check at the Laravel boundary in addition to the module boundary above, matching `docs/motion-saas-integration/AUTHORIZATION_MATRIX.md`'s "rechecked at every boundary" rule. Every read-contract-backed adapter method (`resolveCanonical`, `projectPublished`, `createCandidate`, `lineage`, `resolveRequiredAssets`) still runs through `MotionAuthorizationInterface` and `AbstractMotionAdapter::assertSiteMatches()` before the read contract is ever called — an unauthorized or cross-tenant caller never reaches the module at all.

## What this phase does not add

- No new database table or column (the one additive config key, `motion-graphics.deprecated_versions_readable`, is config, not schema).
- No write path — every read contract method is read-only; nothing here can create, approve, publish, or roll back a version.
- No customer-site or UI exposure — these contracts are only reachable through the Laravel adapter layer, which remains gated by `motion_graphics_integration` (default `false`) and the module's own `motion-graphics.enabled` (default `false`).
