# Motion Graphics Ownership Architecture

## Authority boundary

The master library lives only in `packages/saas-platform/modules/motion-graphics`. It is a platform-only, non-distributable package. A main platform administrator is an authenticated user with `is_admin=true` and `site_id=null`. Every authoring route enforces both conditions.

Tenant applications contain only `MotionRuntimeClient` and the `<x-motion.graphics name=... />` Blade consumer. They contain no authoring model, source parser, editable schema, AI editor, mutation endpoint, or configuration UI.

## Data boundary

- `motion_graphics`: encrypted current authoring definition, editable schema, and AI metadata.
- `motion_graphic_versions`: append-only encrypted source snapshots and immutable compiled-asset manifests.
- `motion_graphic_deployments`: the canonical `site_id` tenant boundary and selected published version.

The requested generic `tenant_id` is intentionally represented as `site_id`, because `PROJECT_CONSTITUTION.md` defines `site_id` as the structural tenant-isolation key.

## Publication flow

`draft -> approved -> published -> retired`. Publish atomically retires the former published version, promotes the approved version, updates the master pointer, and advances every deployment. Rollback never rewrites history; it copies the selected snapshot into a new draft version requiring approval.

## Runtime contract

The license-authenticated tenant endpoint returns only `name`, `version`, sanitized compiled asset descriptors (`url`, `sha256`, `mime`), and `published_at`. Model hidden fields and encrypted casts prevent source, schema, and AI metadata serialization. Asset paths are immutable: `/assets/motion/{slug}/v{version}/...`.

## Permission matrix

Platform authoring permissions are `motion.create`, `motion.update`, `motion.ai_edit`, `motion.publish`, `motion.rollback`, and `motion.delete`. Tenant capabilities are only `motion.view` and `motion.use`; these do not grant access to the master module or any mutation route.

## AI collaboration governance

Before any AI-assisted work, load the module's machine-readable [AI module index](../../packages/saas-platform/modules/motion-graphics/docs/ai/AI_MODULE_INDEX.json) and every document it lists. That layer defines the AI workflow, asset schema, change boundaries, and prompt contract without expanding AI, admin, or tenant authority.
