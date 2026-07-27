# Version Read Contract

`MotionVersionReadContract` resolves version lifecycle state and lineage.

```text
resolveVersion(assetId, version) -> VersionReadModel | null
resolvePublishedVersion(assetId) -> VersionReadModel | null
lineage(assetId) -> list<LineageEntry>
```

## Allowed output

`VersionReadModel`:

```json
{ "asset_id": "…", "version": 3, "status": "published", "parent_version": 2, "is_deprecated": false }
```

`LineageEntry` (one per readable version, oldest first):

```json
{ "version": 2, "parent_version": 1, "status": "retired" }
```

## Rules

- **Published version only for default consumption.** `resolvePublishedVersion()` is the only method that targets `MotionGraphic.published_version`; every other lookup requires an explicit version number.
- **Rollback creates a new version.** `MotionLibraryService::rollback()` (unchanged by this phase) always appends a new `MotionGraphicVersion` row rather than rewriting an old one; `lineage()` reflects this as an ordinary new entry with its own `parent_version`, never as a mutation of history.
- **A retired ("deprecated") version stays readable only when policy allows.** `motion-graphics.deprecated_versions_readable` (new config key, default `false`) gates both `resolveVersion()`/`resolvePublishedVersion()` for a `retired` version and its inclusion in `lineage()`. Published and approved versions are always readable regardless of this flag; draft and review versions are never readable regardless of this flag.

## Forbidden

`VersionReadModel`/`LineageEntry` never carry `source_snapshot`, `editable_schema_snapshot`, `ai_metadata_json`, `change_description`, or actor identity (`created_by_actor_identifier`, `approved_by_actor_identifier`, `published_by_actor_identifier`). `DatabaseMotionVersionReader` never reads those columns.

## Laravel adapter usage

`App\Modules\MotionGraphics\Adapters\SiteAssetPackageResolverAdapter::lineage()` delegates to this contract (see the Site Asset Package simplification note in [RUNTIME_PROJECTION_CONTRACT.md](RUNTIME_PROJECTION_CONTRACT.md) for what `packageId` means in this phase) and maps each `LineageEntry` to a plain array.
