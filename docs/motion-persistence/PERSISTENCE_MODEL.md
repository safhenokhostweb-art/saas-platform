# Persistence Model

All five tables are host-owned (see [MIGRATION_DECISION.md](MIGRATION_DECISION.md)), created in one migration: `database/migrations/2026_07_29_100000_create_motion_persistence_tables.php`. Every reference to a Motion asset is an opaque `asset_id` (the module's `MotionGraphic.uuid`) plus an integer `version` — never a foreign key into the module's own tables.

## A) Template Asset Package — `motion_template_asset_packages` / `TemplateAssetPackage`

| Field | Notes |
|---|---|
| `uuid` | generated on creation |
| `template_reference` | opaque platform template id |
| `required_assets` | JSON list of frozen `{asset_id, version}` |
| `optional_assets` | JSON list of frozen `{asset_id, version}` (unresolved optional refs are dropped, never block creation) |
| `compatibility_rules` | JSON dimension map (e.g. `{"industry": "finance"}`), checked against each asset at activation time |
| `package_version` | integer, auto-incremented per `template_reference` |

**Immutable**: every field above, plus `created_by_actor_identifier`. A change to a template's requirements creates a **new row** with `package_version + 1` — never an update. Enforced by a `static::updating()` guard (`LogicException` on violation), the same pattern as the module's own `MotionGraphicVersion`.

## B) Site Asset Package — `motion_site_asset_packages` / `SiteAssetPackage`

| Field | Notes |
|---|---|
| `site_id` | FK to `sites`, `restrictOnDelete` |
| `template_reference` | denormalized natural key — see [SITE_PACKAGE_MODEL.md](SITE_PACKAGE_MODEL.md) |
| `template_asset_package_id` | FK to `motion_template_asset_packages` — the **current pointer** |
| `brand_variant_reference` | nullable, immutable once set |
| `activation_status` | `pending` \| `active` \| `rolled_back` \| `retired` |
| `activated_at` | nullable |

**Immutable**: `site_id`, `template_reference`, `brand_variant_reference`, `created_by_actor_identifier`. **Mutable**: `template_asset_package_id` (moves forward on upgrade), `activation_status`, `activated_at`.

## C) Asset Activation Record — `motion_asset_activations` / `AssetActivation`

| Field | Notes |
|---|---|
| `site_asset_package_item_id` | FK, `cascadeOnDelete` |
| `asset_id`, `version` | what became active |
| `previous_version` | nullable — what it replaced |
| `activation_type` | `install` \| `upgrade` \| `rollback` |
| `activated_at` | when |
| `actor_identifier` | who (opaque, from `MotionAdapterContext::$actorId`) |

**Fully immutable and append-only** — `UPDATED_AT = null`, and both `updating()`/`deleting()` throw unconditionally. See [ROLLBACK_MODEL.md](ROLLBACK_MODEL.md).

## D) Package Snapshot — `motion_package_snapshots` / `PackageSnapshot`

| Field | Notes |
|---|---|
| `site_asset_package_id` | FK, `cascadeOnDelete` |
| `snapshot` | JSON: `{template_reference, brand_variant_reference, assets: [{asset_id, version, checksum, type}, ...]}` |
| `checksum` | `sha256` of the snapshot JSON itself — an integrity check on the record |
| `captured_at` | when |

**Fully immutable and append-only**, same pattern as `AssetActivation`. Captured on every activation/upgrade/rollback so a package's exact historical state can be reconstructed without re-querying the module.

## E) Site Asset Package Item — `motion_site_asset_package_items` / `SiteAssetPackageItem`

Not explicitly named in the task's "Suggested" list under its own letter, but required to represent "installed assets" + "active versions" (task item B) as first-class rows rather than a single JSON blob — this is what lets each asset within a package be upgraded/rolled back independently.

| Field | Notes |
|---|---|
| `site_asset_package_id` | FK, `cascadeOnDelete` |
| `asset_id` | opaque module asset id |
| `active_version` | the **current pointer** for this asset within this package |
| `bound_checksum` | integrity hash captured at bind time |
| `is_active` | boolean |
| `installed_at` | first-install timestamp, immutable |

**Immutable**: `site_asset_package_id`, `asset_id`, `installed_at`. **Mutable**: `active_version`, `bound_checksum`, `is_active` — the current-pointer fields that move on upgrade/rollback, mirroring `MotionGraphic.published_version`.
