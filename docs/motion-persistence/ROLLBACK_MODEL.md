# Rollback Model

## Rule

**Never update an existing activation. Always create a new one.**

```text
Current:  AssetActivation { version: 3, activation_type: install, previous_version: null }

Rollback: AssetActivation { version: 2, activation_type: rollback, previous_version: 3 }
          (a brand new row — the version:3 row above is untouched)

SiteAssetPackageItem.active_version moves from 3 to 2 (the current-pointer
update — this is expected and is not a violation of history; it mirrors
MotionGraphic.published_version moving on publish/rollback while
MotionGraphicVersion rows stay immutable).
```

`SiteAssetPackageActivationService::rollback()` enforces this mechanically: `AssetActivation`'s model guard makes every row append-only (`updating()`/`deleting()` both throw `LogicException` unconditionally — see `app/Modules/MotionGraphics/Models/AssetActivation.php`), so there is no code path — accidental or deliberate — that could rewrite history. Only `SiteAssetPackageItem.active_version`/`bound_checksum` (the explicitly-mutable current pointer) change.

## Validation before a rollback is accepted

1. **Tenant isolation** — the item's owning `SiteAssetPackage.site_id` must equal the caller's trusted site (never the claimed one); a mismatch fails closed with `tenant_isolation_violation`.
2. **Target version must be currently readable** — `MotionVersionReadContract::resolveVersion(assetId, targetVersion)` and `MotionAssetReadContract::findByCanonicalId(assetId, targetVersion)` must both succeed. Per `docs/motion-read-contracts/VERSION_READ_MODEL.md`, only `published`/`approved` versions are always readable; a version that is now `retired` (because a newer one has since been published) is only rollback-able when `motion-graphics.deprecated_versions_readable` is enabled. This is an intentional safety rail — arbitrary old content cannot silently become active again.
3. A new `PackageSnapshot` is captured reflecting the post-rollback state of every item in the package (not just the one that changed), so the package's full state is always reconstructable from its latest snapshot alone.

## What rollback does not do

- It does not touch the module's own `MotionGraphicVersion` rows — the module's publish/retire history is untouched; only this site's *binding* moves.
- It does not retire the `SiteAssetPackage` itself — `activation_status` stays `active`. Package-level retirement is a separate, future action.
- It is not exposed to any UI in this phase (task rule: "Do not build full UI").
