# Template Motion Contract

## Block-level reference (the only thing a template ever stores)

```json
{
  "type": "module:motion",
  "props": {
    "motion_asset_id": "medical-hero-intro",
    "placement": "hero",
    "enabled": true,
    "fallback_image": ""
  }
}
```

| Field | Meaning |
|---|---|
| `motion_asset_id` | Stable reference to an already-published/assigned Motion asset. **Not** a URL, not source, not a timeline. |
| `placement` | `hero` \| `section` \| `cta` \| `background` — informational only, matches the Motion module's own `placement_identifier` vocabulary. |
| `enabled` | Customer-facing on/off toggle (Step 6). |
| `fallback_image` | Optional plain image URL shown when the asset can't be resolved (unpublished, unassigned, unauthorized, deleted). No new asset type — just an `<img>`. |

## One widget for every motion (ADR-078)

`module:motion` is the only motion block. A hero motion, a business-growth
motion, or any future library asset is an asset chosen in this widget's
picker, never a block type of its own (`hero_motion` and `motion_graphic`
were retired). The customer-site picker reads the site's runtime manifest,
which lists only assets the site is licensed for:

| Tier | Needs |
|---|---|
| `free` (e.g. `hero-motion`) | the `motion-graphics` module license |
| `premium` (e.g. `business-growth-motion`) | also the `motion-graphics-premium` license |

The platform library and its publishing rules are described in ADR-078.

## Explicitly never stored in a template/block

Master SVG/Lottie source, editable timeline/schema, AI prompts or provider metadata, internal Motion Graphics IDs beyond the public `asset_id`, or any other customer's assets. This is enforced independently at **three** layers, not just by convention: `MotionGraphic`/`MotionGraphicVersion` hide+encrypt these fields; the manifest reader hard-rejects a payload containing `source_definition`/`ai_metadata`/`editable_schema`/`prompt`/etc.; and the block schema itself has no field capable of holding them.

## Package-level declaration (Theme, not block)

Stored in the existing `themes.settings` JSON column — no migration:

```json
"settings": {
  "motion": {
    "package_reference": "theme:clinic-premium",
    "mode": "bundled",
    "required_assets": [
      {"asset_id": "medical-hero-intro"},
      {"asset_id": "medical-process-flow"},
      {"asset_id": "medical-cta-pulse"}
    ]
  }
}
```

This is descriptive metadata only for the authoring/marketplace UI — the actual `TemplateAssetPackage` row (with resolved versions) is created by the existing `TemplateAssetPackageService` when a Platform Admin assigns the package via `/admin/motion-graphics/{site}/themes`.

## Support levels (Step 3)

- **Block level** — done: `module:motion` block type, usable anywhere a block can go (hero, section, footer, etc.).
- **Section level** — no separate mechanism needed: a "section" is just a container block with a `module:motion` child, already supported by the existing container-block rendering path.
- **Package level** — done: `Theme.settings.motion.required_assets`, resolved into a real `TemplateAssetPackage` by the existing service when an admin assigns it.
