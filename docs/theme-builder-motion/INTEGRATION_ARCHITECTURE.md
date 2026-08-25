# Theme Builder <-> Motion Graphics — Integration Architecture

## What already existed (verified by reading code, not docs)

- **Master library**: standalone package `packages/saas-platform/modules/motion-graphics` — its service provider is deliberately **never registered**; its own tables are **not migrated** on the host DB. Out of scope to change.
- **Host adapter layer** (`app/Modules/MotionGraphics/*`): real, registered, DB-backed. `TemplateAssetPackage` (frozen `{asset_id, version}` list keyed by an opaque `template_reference` string), `SiteAssetPackage` (one active package per site), `MotionRuntimeInstallationService::installTemplateForSite()` (Template Selected → Package Resolver → Site Activation → Runtime Projection, in one idempotent transaction), `MotionDeploymentPreparationService` (prepare → validate → approve → mark ready).
- **Runtime**: customer-site's `<x-motion.graphics name="asset-id">` component + `MotionRuntimeManifestReader`/`AssetResolver`/`Renderer` chain — fails closed at every layer, was fully built but **had zero real call sites anywhere in the app** before this integration.
- **Auth**: `MotionAuthorizationAdapter::authorize()` requires a real authenticated `Auth::user()` — no system/service-actor escape hatch exists or was added.

## What Theme Builder had (verified)

`ThemeTemplate`/`ThemePack`/`Theme` had **zero** motion-related columns or block types. `BlockRegistry`/`BlockRenderer`/`ThemeCanvasRenderer` had no motion awareness. A Phase 6/7 bridge already connected `ThemeTemplate` → Motion at the *service* layer (proven by `ThemeTemplateBridgeIntegrationTest`) but nothing in canvas/block rendering ever called it.

## What this integration adds (additive only)

```
Theme (marketplace)                 ThemeTemplate (section)
   │ ThemeReference::for()              │ ThemeTemplateReference::for()  (pre-existing)
   ▼                                    ▼
   'theme:{slug}'  ──────┬──────  'theme-template:{uuid}'
                          ▼
              TemplateAssetPackage (pre-existing, reused as-is)
                          │  Platform Admin: /admin/motion-graphics/{site}/themes
                          ▼
              SiteAssetPackage (pre-existing, reused as-is)
                          │
                          ▼
        customer-site: block.props.motion_asset_id (new block type: module:motion)
                          │
                          ▼
        ThemeCanvasRenderer::renderModuleMotion()  (new, ~30 lines, zero Motion logic)
                          │
                          ▼
        <x-motion.graphics name="..."> (pre-existing, now finally called for real)
                          │
                          ▼
        MotionRuntimeManifestReader → AssetResolver → Renderer (pre-existing, unmodified)
```

Nothing in the pre-existing boxes was changed. The only new code is: two reference classes (`ThemeReference` mirrors `ThemeTemplateReference`), one new block type + one renderer method (customer-site), one admin controller/view extension mirroring the existing `ThemeTemplate` screens exactly, and a `Theme.settings['motion']` JSON key (no migration).

## Deliberately not built

- **No automatic activation at customer self-service purchase time.** `installTemplateForSite()`'s authorization ability (`motion.manage_template_package`) requires a real authenticated Platform Admin session (`MotionAuthorizationAdapter::authorize()` calls `Auth::user()` directly, no system-actor path). Adding one would mean changing Motion Graphics' authorization model — explicitly out of scope. Site assignment remains a deliberate Platform Admin action via `/admin/motion-graphics/{site}/themes`, identical in shape to the pre-existing internal-test-site workflow.
- **No new asset system, license system, or marketplace.** Every persistence/entitlement/runtime concept is reused verbatim.
