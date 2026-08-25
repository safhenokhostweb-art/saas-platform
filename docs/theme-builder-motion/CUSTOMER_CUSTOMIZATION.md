# Customer Customization — Motion in Theme Builder

## What a customer may safely do (implemented)

- **Enable/disable** an assigned motion block — `props.enabled` toggle in the existing canvas block-props editor. `ThemeCanvasRenderer::renderModuleMotion()` returns nothing when `enabled === false`, regardless of whether the asset would otherwise resolve.
- **See a graceful result** whenever motion isn't available for any reason (not yet assigned to their site, unpublished, license/entitlement denied, deleted) — either the configured `fallback_image`, or nothing at all. The page never breaks and never shows an error to the visitor.
- **Preview** — since rendering goes through the real `ThemeCanvasRenderer` (same renderer as production), the existing canvas live-preview shows exactly what will render publicly, including motion.

## What a customer cannot do (preserved, not re-implemented)

- **Browse the Master Motion Library.** Customer-site never talks to the master library — only to the already-filtered, already-sanitized runtime manifest for their own site (`GET {platform}/api/motion-runtime/manifest`, authenticated by their own license key).
- **Access another site's motion.** Enforced by the pre-existing `MotionAuthorizationAdapter`/`TenantContextContract` chain, verified again in this integration by a cross-tenant test (`ThemeMotionBridgeIntegrationTest`) that proves Site B cannot read Site A's `SiteAssetPackage` projection.
- **Edit motion source, or see AI metadata.** The block only ever carries a stable `motion_asset_id` string (see `TEMPLATE_MOTION_CONTRACT.md`) — there is no field, no endpoint, and no UI path that could carry source/timeline/AI data into customer-site.
- **Bypass assignment/license rules.** `<x-motion.graphics>` (reused unmodified) fails closed through the exact same manifest-fetch chain for every request; there is no client-side override.

## Variant selection

If a template's declared package includes multiple assets for the same conceptual slot (e.g. two hero-motion options), the customer picks by setting a different `motion_asset_id` — restricted in practice to whatever their site's own active `SiteAssetPackage` actually contains, since anything else simply won't resolve. A live, name/thumbnail-only picker UI (rather than typing an asset id) is the natural next iteration — see `FINAL_REPORT.md` §11.
