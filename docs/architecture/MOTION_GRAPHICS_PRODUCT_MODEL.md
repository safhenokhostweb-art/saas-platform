# Motion Graphics Product Model

## Product boundary

Phase M2 turns Motion Graphics into a centralized Motion Asset Management foundation. Platform Admin owns authoring and operations; customer websites consume only authorized published projections. No animation asset is generated or bundled by this phase.

## Classification and discovery

Every motion belongs to one `motion_group` and may have many `motion_tags`. Admin library queries support text search plus group, tag, and ownership filters. Groups and tags are platform-managed.

## Motion asset envelope

An asset records identity, ownership, group/tags, renderer engine, asset type, encrypted configuration, encrypted editable source, encrypted editable schema, encrypted AI metadata, source description, provider provenance, draft pointer, published pointer, and lifecycle status.

Supported normalized inputs are Lottie JSON, SVG, HTML animation, Canvas 2D, and Web Components. `MotionAssetPipelineContract` validates a provider-neutral envelope and immutable SHA-256 asset descriptors. Optimization and preview generation are represented as pipeline stages; concrete compilers and storage adapters remain a later delivery.

## AI workflow

1. Platform Admin submits a prompt.
2. `MotionAiGenerationContract` delegates to the configured provider adapter.
3. Provider output is normalized to an array-based Motion Asset Schema.
4. Editable-schema and asset-path validation run.
5. Prompt, provider, response metadata, and generation time are stored encrypted on the new version.
6. The new immutable version remains a draft.
7. Platform Admin reviews, approves, and separately publishes.

No provider-specific field is present in the domain service. OpenAI, Claude, Gemini, Codex, or another adapter can implement the same contract.

## Deployment model

`motion_graphic_deployments` assigns a published motion to:

- one site;
- one page key;
- one placement;
- an enabled state;
- a priority.

A site can have multiple motions, and a page/placement can contain multiple motions ordered by priority. A customer-owned motion may be assigned only to its owner site. A global motion is visible only to sites with an enabled assignment.

## Template and API integration

The runtime API accepts a motion slug and optional page/placement filters. The Blade alias is:

`<x-motion.graphics name='iran-network' page='homepage' placement='hero' />`

Both resolve the active site's enabled deployment and published version. Runtime output includes sanitized optimized asset descriptors only.

## Version lifecycle

`current_version` and `published_version` are independent:

- new draft: advances current only;
- review: marks a draft reviewable;
- approval: records actor/time without publishing;
- publish: switches published pointer atomically and updates deployments;
- compare: returns an admin-only source-path diff;
- rollback: creates a new draft from history with parent lineage;
- archive: removes an asset from active discovery without deleting history.

Version source, compiled assets, AI metadata, lineage, change description, and creator are immutable after creation.

## Security model

- `site_id` is enforced in assignment and runtime lookup.
- Source, configuration, editable schema, AI metadata, prompts, and snapshots use encrypted model casts.
- Private fields are hidden by models and absent from runtime projections.
- Tenant routes are read-only and authenticated by the existing tenant middleware.
- All mutation endpoints require a main Platform Admin.
- Asset URLs are immutable and integrity-addressed; signed delivery can be added by a storage adapter where required.

## Deferred implementation

Phase M2 defines but does not implement concrete Lottie/SVG/HTML optimization engines, preview workers, object-storage publication, signed URL generation, provider selection UI, or customer-site JavaScript renderers. Those require separate infrastructure and runtime phases.
