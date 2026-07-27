# Published Runtime Projection Read Contract

`MotionRuntimeProjectionReadContract` builds the customer-safe, published-only runtime projection for one asset.

```text
projectAsset(assetId, siteIdentifier = null) -> RuntimeProjectionReadModel | null
```

`siteIdentifier` must be supplied and must match the asset's `owner_site_identifier` for a customer-specific asset — a mismatch, or a missing identifier for a customer-specific asset, returns `null` rather than leaking another tenant's asset. A global asset accepts any (or no) site identifier. This is the exact ownership rule `MotionLibraryService::runtimeManifest()` already enforces for deployment-bound manifests; `PublishedRuntimeProjectionProvider` applies the same rule independently so a projection can be built without requiring an active `MotionGraphicDeployment` row.

## Allowed output — `RuntimeProjectionReadModel`

```json
{
  "asset_reference": { "...": "AssetReadModel, see ASSET_READ_CONTRACT.md" },
  "published_version": 3,
  "optimized_reference": "/motion/{slug}/v3/animation.json",
  "theme_tokens": { "primary": "#111111" },
  "placement_rules": { "dimensions": { "width": 800, "height": 600 }, "reduced_motion_label": "…" }
}
```

- `optimized_reference` is the first compiled asset's opaque `url` (from `MotionGraphicVersion.compiled_assets`) — never a filesystem path or storage key.
- `theme_tokens` is read only from `runtime_configuration.theme_tokens` (empty array when absent).
- `placement_rules` is assembled only from `dimensions` and `accessibility.label` — both already non-hidden, already-safe model attributes.
- `published_version` is only ever set from a `MotionGraphicVersion` row whose `status` is exactly `published`; an approved-but-unpublished version is never projected here (contrast with `MotionAssetReadContract`, which does allow approved-version preview for authorized actors).

## Forbidden

Draft data, approval history (actor identity, timestamps beyond `published_version`), prompts, and internal metadata never appear — `PublishedRuntimeProjectionProvider` reads only `published_version`, `ownership_type`, `owner_site_identifier`, `runtime_configuration`, `dimensions`, `accessibility`, and the one published version's `compiled_assets`. No other version-table column is read.

## Laravel adapter usage

`App\Modules\MotionGraphics\Adapters\MotionRuntimeProjectionAdapter::projectPublished()` authorizes the request, re-verifies the tenant context, calls `projectAsset()`, and maps the result to the Phase 1 `RuntimeProjectionDTO` (extended in Phase 2 with an `optimizedReference` field — see [INTEGRATION_UPDATE.md](INTEGRATION_UPDATE.md)) via `ReadModelMapper`. `null`, or a `publishedVersion` that does not match the caller's requested version, fails closed with the stable code `projection_not_available`.

## Site Asset Package simplification

No dedicated Site Asset Package persistence model exists yet (see `docs/motion-saas-integration/PERSISTENCE_DESIGN.md`). In this phase, the Laravel adapter's `sitePackageId` parameter is the canonical asset id; resolving a real package id to its constituent asset ids is a future Deployment Engine phase's responsibility, explicitly out of scope here.
