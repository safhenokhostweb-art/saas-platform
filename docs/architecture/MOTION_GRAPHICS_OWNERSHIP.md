# Motion Graphics Ownership Architecture

## Authority boundary

The master library lives only in `packages/saas-platform/modules/motion-graphics`. It is a platform-owned authoring system. A main Platform Admin is an authenticated user with `is_admin=true` and `site_id=null`; every authoring, AI, classification, version, publication, and deployment mutation route enforces that boundary.

Tenants remain consumers. Customer ownership identifies an asset whose availability is restricted to one `site_id`; it does not grant that tenant source access or authoring authority.

## Ownership types

### Global

- Owned and authored by the Platform.
- `ownership_type=global` and `site_id=null`.
- Becomes available to a site only through an enabled deployment.
- May be assigned to multiple sites, pages, and placements.

### Customer

- Authored by Platform Admin for one customer.
- `ownership_type=customer` and a required owner `site_id`.
- May be deployed only to its owner site.
- Cross-site assignment and runtime access fail closed.

`site_id` is the canonical tenant boundary under `PROJECT_CONSTITUTION.md`. `platform_id` is optional platform provenance and does not replace tenant isolation.

## Data boundary

- `motion_graphics`: encrypted current authoring definition, editable schema, AI metadata, encrypted configuration, ownership, classification, latest draft pointer, and published pointer.
- `motion_graphic_versions`: append-only encrypted snapshots, provider provenance, parent lineage, approval, and publication history.
- `motion_groups`, `motion_tags`, `motion_asset_tags`: platform-managed classification.
- `motion_graphic_deployments`: site, page, placement, enabled state, priority, and selected published version.

Tenant responses never serialize source definitions, editable schemas, AI metadata, source descriptions, configuration, prompts, snapshots, or comparison data.

## Publication flow

`draft -> review -> approved -> published -> retired`.

- `current_version` points to the newest draft lineage.
- `published_version` points to the active runtime version.
- Creating a draft updates only `current_version`; an existing published version remains live.
- Publishing atomically retires the former published version, advances `published_version`, records the publisher/time, and advances deployments.
- Rollback copies a historical snapshot into a new draft whose parent is the latest version. It never rewrites history and never publishes automatically.

## Runtime contract

The tenant-authenticated runtime endpoint and `<x-motion.graphics name='iran-network' />` resolve only an enabled deployment for the active site and the motion's published version. The projection contains only:

- slug;
- published version;
- optimized asset URL, SHA-256, and MIME type;
- publication timestamp.

Immutable asset paths use `/assets/motion/{slug}/v{version}/...`.

## Permission matrix

Platform authoring capabilities are `motion.create`, `motion.update`, `motion.ai_edit`, `motion.publish`, `motion.rollback`, and `motion.delete`. Tenant capabilities remain `motion.view` and `motion.use`; they do not grant mutation routes, source access, AI access, classification management, or deployment management.

## AI collaboration governance

AI providers are adapters behind `MotionAiGenerationContract`. Provider output must be normalized to the Motion Asset Schema, validated against the editable schema, and saved as an unpublished immutable draft. AI cannot approve or publish.

Before AI-assisted work, load the module's [AI module index](../../packages/saas-platform/modules/motion-graphics/docs/ai/AI_MODULE_INDEX.json) and every document it lists.
