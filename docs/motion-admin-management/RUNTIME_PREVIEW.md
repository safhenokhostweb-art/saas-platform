# Runtime Preview

## What the preview page shows

`GET /admin/motion-graphics/{site}/preview/{sitePackage}` renders, per asset in the package:

- the asset id and published version (`RuntimeProjectionDTO->assetReference->assetId` / `->publishedVersion`)
- the asset type (`->assetReference->type`)
- the optimized runtime reference, if any (`->optimizedReference`)
- theme token adaptation (`->themeTokens`)
- placement rules (`->placementRules`)

This is exactly `App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService::projectSitePackage()`'s existing output (Phase 4, unchanged) — the admin preview page adds no new data source and no new transformation.

## What it must never show (task scope item 5) — and why it can't

- **Source SVG** — never present. `RuntimeProjectionDTO`/`MotionAssetReferenceDTO` are constructed exclusively from `SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel`/`RuntimeProjectionReadModel`, whose own contract (`docs/motion-read-contracts/ASSET_READ_CONTRACT.md`) forbids `source_definition` at the read-model level — the field does not exist anywhere in the chain the view renders.
- **AI instructions** — same reasoning; `ai_metadata` is forbidden at the same boundary.
- **Internal metadata** — `ForbiddenFieldGuard::assertClean()` runs inside both `MotionAssetReferenceDTO` and `RuntimeProjectionDTO`'s own constructors (Phase 1), rejecting `source_definition`, `editable_schema`, `ai_metadata`, `ownership`, `credentials`, `storage_path`, and more, on every construction — not just at this phase's view layer. A forbidden key reaching this point would throw before the DTO could even be built, let alone rendered.

Verified two ways in `AdminSecurityTest`: the rendered HTTP response text is checked to not contain any forbidden key name, and `ForbiddenFieldGuard::assertClean()` is called directly against the same DTO shape the view receives, proving the guarantee doesn't depend on the Blade template "just not printing" a field that was otherwise present in memory.

## Access control

Only the site currently set via `TenantContext::set()` may preview its own packages — `MotionGraphicsAdminService::previewRuntimeProjection()` independently re-checks `(string) $sitePackage->site_id !== $context->siteId` before delegating, so even a crafted URL combining one site's `{site}` segment with another site's `{sitePackage}` id fails closed (`AdminSecurityTest::test_an_admin_cannot_preview_another_sites_package_via_a_crafted_url`).
