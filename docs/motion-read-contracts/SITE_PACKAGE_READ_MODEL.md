# Site Asset Package Read Contract

`SiteAssetPackageReadContract` exposes the runtime-safe state of one site's installed Motion Graphics deployments.

```text
installedAssetsForSite(siteIdentifier) -> list<AssetReadModel>
packageState(siteIdentifier, pageIdentifier, placementIdentifier = null) -> SitePackageReadModel | null
```

No dedicated "Site Asset Package" table exists (`docs/motion-saas-integration/PERSISTENCE_DESIGN.md` explicitly defers that persistence model). `SitePackageReader` is built on the module's real, existing primitive instead: `MotionGraphicDeployment` (site + page + placement + enabled + selected/published version) — exactly what `MotionDeploymentService` already manages. A "package" in this phase is the set of enabled deployments for a site (optionally narrowed to one page/placement), not a new persisted entity.

## Allowed output — `SitePackageReadModel`

```json
{
  "package_id": "site-a:home:hero",
  "template_reference": null,
  "site_identifier": "site-a",
  "active_assets": ["AssetReadModel, ..."]
}
```

- `package_id` is a deterministic composite of `siteIdentifier:pageIdentifier[:placementIdentifier]` — not a stored primary key.
- `template_reference` is always `null` in this phase: the module has no template identity to report (see [TEMPLATE_READ_MODEL.md](TEMPLATE_READ_MODEL.md)). The Laravel adapter is expected to attach its own platform-side template identity separately when it has one.
- `active_assets` only ever includes a deployment whose motion is enabled and, for a customer-specific asset, owned by exactly `siteIdentifier`.

## Forbidden

- **Private source data**: same construction guarantee as `MotionAssetReadContract` — only `AssetReadModel` entries are returned.
- **Other tenant data**: `installedAssetsForSite()`/`packageState()` query `MotionGraphicDeployment` scoped to the given `siteIdentifier` only, and independently re-check `ownership_type`/`owner_site_identifier` per entry (defense in depth on top of the module's existing deploy-time isolation in `MotionDeploymentService::assign()`, which already refuses to deploy a customer-specific asset to a foreign site).

## Laravel adapter usage

Not yet wired into a Phase 1 adapter method (no existing Phase 1 interface method maps onto "list a site's installed package"). It is available for a future Deployment Engine phase to consume once that phase defines how a real site package id is produced.
