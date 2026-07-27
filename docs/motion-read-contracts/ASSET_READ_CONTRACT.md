# Canonical Asset Read Contract

`MotionAssetReadContract` resolves a safe reference to one asset version.

```text
findByCanonicalId(assetId, version = null) -> AssetReadModel | null
resolveActiveVersion(assetId) -> int | null
verifyLifecycleState(assetId, version, expectedState) -> bool
```

`assetId` is the module's `MotionGraphic.uuid` — the same opaque identifier already used in the module's own audit events (`motion_uuid`). When `version` is omitted, resolution targets the published version only (the customer-safe default). An explicit `version` also permits an **approved** version (for Platform Admin/Designer preview flows); a **draft** or **review** version is never returned regardless of how it is requested. A **retired** version is returned only when `motion-graphics.deprecated_versions_readable` is enabled — see [VERSION_READ_MODEL.md](VERSION_READ_MODEL.md).

## Allowed output — `AssetReadModel`

```json
{
  "asset_id": "…",
  "version": 3,
  "type": "lottie",
  "category": "finance",
  "compatibility": { "engine": "lottie", "asset_type": "lottie", "group": "…", "category": "…", "tags": ["…"] },
  "checksum": "sha256…",
  "theme_support": true
}
```

- `type` is the module's `asset_type` (one of `motion-graphics.allowed_asset_types`).
- `category` is the asset's `MotionCategory.slug`, or null when unclassified.
- `compatibility` is derived from existing, already-non-hidden classification data (engine, asset type, group slug, category slug, tag slugs) — there is no separate `compatibility` column, so no migration was needed to add this field.
- `checksum` is `MotionGraphicVersion.source_checksum` — already treated as non-secret integrity data elsewhere in the module (it is the `cache.etag` value in `MotionLibraryService::runtimeManifest()`).
- `theme_support` is derived from `runtime_configuration.supports_theme_tokens` (defaults to `false` when absent) — a soft convention within the existing JSON column, not a new schema field.

## Forbidden

`source_definition`, `editable_schema`, AI metadata, internal storage paths, and ownership internals (`owner_site_identifier`, `ownership_type`, actor identities) never appear in `AssetReadModel`. This is enforced by construction: `DatabaseMotionAssetReader` never reads `MotionGraphic::$hidden` columns (`source_definition_json`, `editable_schema_json`, `ai_metadata_json`) or `MotionGraphicVersion::$hidden` columns (`source_snapshot`, `editable_schema_snapshot`, `ai_metadata_json`) anywhere in its implementation — there is no filter step to bypass, because the forbidden data is never fetched in the first place.

## Laravel adapter usage

`App\Modules\MotionGraphics\Adapters\MotionAssetResolverAdapter::resolveCanonical()` authorizes the request, calls `findByCanonicalId()`, and maps the result to the Phase 1 `MotionAssetReferenceDTO` via `App\Modules\MotionGraphics\Support\ReadModelMapper`. A `null` result fails closed with the stable code `asset_not_available`.
