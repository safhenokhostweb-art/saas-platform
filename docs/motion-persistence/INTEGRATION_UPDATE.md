# Phase 3 Integration Update

## New classes

| Class | Role |
|---|---|
| `App\Modules\MotionGraphics\Models\TemplateAssetPackage` | Eloquent model, `motion_template_asset_packages` |
| `App\Modules\MotionGraphics\Models\SiteAssetPackage` | Eloquent model, `motion_site_asset_packages` |
| `App\Modules\MotionGraphics\Models\SiteAssetPackageItem` | Eloquent model, `motion_site_asset_package_items` |
| `App\Modules\MotionGraphics\Models\AssetActivation` | Eloquent model, `motion_asset_activations` (append-only) |
| `App\Modules\MotionGraphics\Models\PackageSnapshot` | Eloquent model, `motion_package_snapshots` (append-only) |
| `App\Modules\MotionGraphics\Services\TemplateAssetPackageService` | Creates frozen Template Asset Packages — see [VERSION_BINDING_RULES.md](VERSION_BINDING_RULES.md) |
| `App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService` | Activates a package for a site and provides rollback — see [ACTIVATION_FLOW.md](ACTIVATION_FLOW.md), [ROLLBACK_MODEL.md](ROLLBACK_MODEL.md) |
| `App\Modules\MotionGraphics\DTO\LicenseCompatibilityDecision` | The license-check pipeline slot — see [ACTIVATION_FLOW.md](ACTIVATION_FLOW.md) |

Both services extend `App\Modules\MotionGraphics\Support\AbstractMotionAdapter` (Phase 1) — the same `ensureEnabled()`/`assertSiteMatches()` guards used by every Phase 1/2 adapter, applied unchanged here.

## Reused, not duplicated

- `MotionAuthorizationInterface` (Phase 1) — every service method authorizes through it.
- `MotionAssetReadContract`, `MotionVersionReadContract`, `MotionRuntimeProjectionReadContract` (Phase 2) — every asset/version/publication lookup goes through these; no new module-facing read logic was written.
- `MotionAssetResolverInterface::validateCompatibility()` (Phase 1) — industry/theme compatibility validation is the exact same comparison logic already used by the Phase 1/2 adapters, called again here rather than reimplemented.
- `App\Modules\MotionGraphics\Support\ReadModelMapper` (Phase 2) — used to convert a read model into the `MotionAssetReferenceDTO` that `validateCompatibility()` expects.
- `MotionAuditEvents` (Phase 1) — extended with four new constants (`TEMPLATE_PACKAGE_CREATED`, `SITE_PACKAGE_CREATED`, `ASSET_ACTIVATED`, `ASSET_ROLLBACK`); the recording mechanism itself (`ActivityLoggerInterface`) is unchanged.

## New container bindings

`App\Providers\MotionGraphicsAdapterServiceProvider` — the same single wiring point from Phase 1/2 — now also singleton-binds `TemplateAssetPackageService` and `SiteAssetPackageActivationService`. Nothing else in that provider changed.

## Feature flag

No new flag. `motion_graphics_integration` (Phase 1) gates both new services identically to every existing adapter: `ensureEnabled()` throws `MotionAdapterFailClosedException('motion_adapter_disabled', ...)` when it is `false` (the default), before either service touches the database or calls a read contract.

## Audit events (task scope item 9 — preparation only, no full audit UI)

`motion.template.package.created`, `motion.site.package.created`, `motion.asset.activated`, `motion.asset.rollback` — recorded via the existing `MotionAuditEvents`/`ActivityLoggerInterface` path, identically to Phase 1's three events.

## Known limitation carried forward from Phase 2

The module's own `MotionGraphicsServiceProvider` is still not registered on the host app (unchanged since Phase 1 — see `packages/saas-platform/modules/motion-graphics/docs/STANDALONE_MODULE_STATUS.md`). The read contracts this phase depends on are bound to their real, database-backed implementations, but those implementations query the module's own tables, which do not exist on the host database until a later phase formally activates the module. Host-side tests for this phase therefore fake the Phase 2 read contracts (`MotionAssetReadContract`, `MotionRuntimeProjectionReadContract`, `MotionVersionReadContract`) exactly as Phase 2's own tests did; the Phase 3 persistence tables themselves are host-owned and are exercised for real against the test database via `RefreshDatabase`.
