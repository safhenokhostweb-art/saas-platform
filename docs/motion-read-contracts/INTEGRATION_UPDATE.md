# Phase 1 Adapter Update

How the four Phase 1 Laravel adapters (`app/Modules/MotionGraphics/Adapters/`) changed to consume the Phase 2 read contracts. Authorization checks, tenant checks, the `motion_graphics_integration` feature flag, and deny-by-default behavior are unchanged in every method — only the previously fail-closed bodies were replaced.

| Adapter | Method | Phase 1 | Phase 2 |
|---|---|---|---|
| `MotionAssetResolverAdapter` | `resolveCanonical()` | always threw `module_contract_unavailable` | calls `MotionAssetReadContract::findByCanonicalId()`; `null` throws `asset_not_available`, otherwise maps to `MotionAssetReferenceDTO` |
| `MotionRuntimeProjectionAdapter` | `projectPublished()` | always threw `module_contract_unavailable` | calls `MotionRuntimeProjectionReadContract::projectAsset()`; `null` or a version mismatch throws `projection_not_available`, otherwise maps to `RuntimeProjectionDTO` |
| `SiteAssetPackageResolverAdapter` | `lineage()` | always threw `module_contract_unavailable` | calls `MotionVersionReadContract::lineage()` and maps each entry to a plain array |
| `SiteAssetPackageResolverAdapter` | `createCandidate()` | built the DTO from caller-supplied data only | additionally confirms every required asset via `MotionAssetReadContract::findByCanonicalId()`; an unresolved required asset throws `unresolved_required_asset` |
| `TemplateAssetResolverAdapter` | `resolveRequiredAssets()` | built the DTO from caller-supplied data only | additionally resolves every reference via `TemplateAssetReadContract::resolveReferences()`; an unresolved **required** reference throws `unresolved_required_asset`, an unresolved **optional** reference is dropped |

`TemplateAssetResolverAdapter::validateManifest()` and `::createPackageDefinition()`, and `SiteAssetPackageResolverAdapter::validateOwnership()`, were already functional in Phase 1 (no module dependency) and are unchanged.

## New mapping layer

`App\Modules\MotionGraphics\Support\ReadModelMapper` is the single place that converts the module's Phase 2 read models into the Phase 1 DTOs:

- `AssetReadModel -> MotionAssetReferenceDTO` (module's `category`, `checksum`, and `theme_support` are folded into the DTO's existing `compatibility` array under those same keys)
- `RuntimeProjectionReadModel -> RuntimeProjectionDTO`

## DTO change

`RuntimeProjectionDTO` gained one new optional constructor parameter, `optimizedReference` (defaults to `null`), carrying the opaque optimized asset URL described in `docs/motion-saas-integration/RUNTIME_PROJECTION.md`'s original allowlist. This is additive and backward compatible — every Phase 1 call site that constructs a `RuntimeProjectionDTO` without it still works unchanged.

## New adapter dependencies

Each adapter gained one or two new constructor dependencies (the relevant read contract interfaces), all bound in `App\Providers\MotionGraphicsAdapterServiceProvider` to the module's real `src/Support/Read/*` implementations:

```text
MotionAssetResolverAdapter        + MotionAssetReadContract
MotionRuntimeProjectionAdapter    + MotionRuntimeProjectionReadContract
SiteAssetPackageResolverAdapter   + MotionAssetReadContract, MotionVersionReadContract
TemplateAssetResolverAdapter      + TemplateAssetReadContract
```

`MotionAuthorizationAdapter` is unchanged — it has no read-contract dependency.

## Known limitation: host database has no module tables yet

`App\Providers\MotionGraphicsAdapterServiceProvider` binds the read contracts to the module's real, database-backed readers (`DatabaseMotionAssetReader` and friends) — this is architecturally correct wiring. However, the standalone module's own `MotionGraphicsServiceProvider` is still not registered on the host app (an intentional Phase 1 decision, preserved in this phase — see `packages/saas-platform/modules/motion-graphics/docs/STANDALONE_MODULE_STATUS.md`'s "deliberately inactive: platform provider"), so the module's migrations have never run against the host's database. Calling a read-contract method for real (with the feature flag enabled) against the host database will fail with a missing-table error until a later phase formally activates the module on the host. This is why the host-side integration tests (`tests/Feature/Modules/MotionGraphics/`) exercise the adapters against **faked** read contracts, while the real database-backed providers are proven correct by the module's own isolated Testbench suite (`packages/saas-platform/modules/motion-graphics/tests/Feature/Read/`), which runs against an in-memory SQLite database independent of the host.
