# Motion Asset Schema

This is the logical private authoring envelope for motion graphics, not a tenant API response. Existing database and service contracts remain canonical for storage.

## Identity

| Field | Purpose |
|---|---|
| `id` | Internal persistent identifier. |
| `uuid` | Stable non-sequential identifier. |
| `name` | Human-readable name. |
| `slug` | Stable unique lookup key. |
| `description` | Authoring description and intended use. |

## Source and edit controls

| Field | Purpose |
|---|---|
| `source_definition_json` | Canonical validated authoring definition. |
| `editable_schema_json` | Allowlist of AI-editable properties, types, ranges, enums, and constraints. |
| `ai_metadata_json` | Private semantic constraints, intent, and generation provenance. |

Absence from the editable schema means a property is not AI-editable. Never store credentials, tenant data, or unbounded conversations in metadata.

## Ownership and classification

| Field | Purpose |
|---|---|
| ownership_type | global or customer_specific. |
| `platform_id` | Optional platform provenance. |
| owner_site_identifier | External owner boundary for customer-specific assets; null for global assets. |
| `group_id` | Required primary classification. |
| `tags` | Zero or more platform-managed search tags. |

## Rendering

| Field | Purpose |
|---|---|
| `engine` | Approved renderer and compatible format/version. |
| `assets` | Immutable compiled asset descriptors and integrity data. |
| `dependencies` | Explicit approved rendering dependencies and versions. |
| `asset_type` | Lottie, SVG, HTML, Canvas, or Web Component. |
| `configuration` | Encrypted renderer configuration. |

Dependencies must be minimal and reproducible. Compiled assets must not embed authoring fields.

## Version

| Field | Purpose |
|---|---|
| `version` | Immutable version identifier. |
| `parent_version` | Direct lineage reference for comparison. |
| `change_log` | Concise explanation of changes. |
| `published_version` | Independently tracked active runtime version. |

Version data is append-only. A missing parent is valid only for the first version. Rollback derives a new version from history.

## Runtime projection and invariants

Tenants may receive only published identity, version, sanitized compiled asset descriptors, integrity metadata, and publication time. They never receive sources, schemas, AI metadata, snapshots, prompts, private previews, or authoring lineage.

The slug stays unique; source and edits validate; engine and dependencies are approved; assets match their snapshot and carry integrity metadata; versions remain immutable; and tenant consumers resolve only the valid published version.
