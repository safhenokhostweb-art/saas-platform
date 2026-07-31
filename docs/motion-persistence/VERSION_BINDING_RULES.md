# Version Binding Rules

```text
Template Asset Reference
        |
        |  {asset_id, version?} — caller supplies asset_id; version is optional
        v
Resolve Published Version
        |
        |  MotionAssetReadContract::resolveActiveVersion() when version is
        |  omitted, or ::findByCanonicalId(assetId, version) to confirm an
        |  explicit version is currently readable (see
        |  docs/motion-read-contracts/ASSET_READ_CONTRACT.md)
        v
Bind Version Snapshot
        |
        |  the resolved {asset_id, version} pair is frozen into
        |  TemplateAssetPackage.required_assets / optional_assets — an
        |  immutable row, never edited again
        v
(later, at site activation)
        |
        |  SiteAssetPackageActivationService re-validates the frozen
        |  version is still published/owned-for-this-site via
        |  MotionRuntimeProjectionReadContract, then freezes it again into
        |  SiteAssetPackageItem.active_version + bound_checksum
        v
Site Asset Package Item (current pointer)
```

## The core guarantee

**Future Master changes must NOT automatically modify installed sites.**

```text
Master:  company-motion-v3
Site A:  company-motion-v3 (frozen in SiteAssetPackageItem.active_version)

Master update: company-motion-v4  (a new MotionGraphicVersion row in the module; module's published_version moves to 4)

Site A remains v3 — nothing in this persistence layer re-reads the module on
its own. Site A moves to v4 only when an explicit upgrade activates a new
TemplateAssetPackage (or a future explicit "upgrade item" action) that
resolves and freezes v4.
```

This holds because:

1. `TemplateAssetPackage.required_assets` is frozen at creation time and is immutable (a schema-level guard throws on any attempt to change it).
2. `SiteAssetPackageItem.active_version` only changes when `SiteAssetPackageActivationService::bindItem()` is explicitly called with a **different** resolved version — which only happens when a site is activated against a **different** (newer) `TemplateAssetPackage` row. Nothing polls the module or reacts to the module's own `published_version` changing.
3. `AssetActivation` never updates a prior row — every version change is a new, additional history entry, never a mutation.

## Where "published version" is resolved from

Every resolution in this phase goes through the Phase 2 read contracts (`MotionAssetReadContract`, `MotionRuntimeProjectionReadContract`) — never a direct query against the module's `MotionGraphic`/`MotionGraphicVersion` tables. See `docs/motion-read-contracts/` and [MIGRATION_DECISION.md](MIGRATION_DECISION.md).
