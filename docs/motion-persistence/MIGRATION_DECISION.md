# Database Strategy Decision

## Which tables belong to the standalone module

None, in this phase. The module already owns `motion_graphics`, `motion_graphic_versions`, `motion_groups`, `motion_categories`, `motion_tags`, `motion_graphic_deployments` (from its own `2026_07_13_000001_create_motion_graphics_tables.php`) and this phase does not add, alter, or reference any of them.

## Which tables belong to the SaaS host

All five new ones — `motion_template_asset_packages`, `motion_site_asset_packages`, `motion_site_asset_package_items`, `motion_asset_activations`, `motion_package_snapshots` — created in `database/migrations/2026_07_29_100000_create_motion_persistence_tables.php`.

### Why the host, not the module

1. **`docs/motion-saas-integration/INTEGRATION_BOUNDARY.md`** (Phase 1, still authoritative): "SaaS Platform owns: ... template selection and deployment orchestration." A Template Asset Package and a Site Asset Package are both fundamentally about *platform orchestration* — which site has which template, at which bound version — not about asset authoring, rendering, or lifecycle validation (the module's actual domain).
2. **`site_id` is a host concept.** `Site` (the Eloquent model, the `sites` table) belongs entirely to the host app. A table that must foreign-key to `sites` structurally cannot live inside an independently-distributable package (`PROJECT_CONSTITUTION.md` §3: "Shared packages must remain independently distributable and must not import host `App\` namespaces").
3. **The task's own rule**: "Do NOT create direct coupling between SaaS models and Motion internal models." Placing platform orchestration tables inside the module, or giving host tables foreign keys into the module's tables, would be exactly that coupling in the other direction. Keeping them on the host with only opaque `asset_id`/`version` references achieves the opposite: the module could be replaced or reimplemented behind its read contracts (`docs/motion-read-contracts/`) without touching a single host migration.

## No duplicated ownership data

`SiteAssetPackage`/`SiteAssetPackageItem` never store `ownership_type` or `owner_site_identifier` — those remain solely in the module's `motion_graphics` table. See [OWNERSHIP_RULES.md](OWNERSHIP_RULES.md) for the precise distinction between the module's *asset* ownership and the host's *site assignment* bookkeeping.

## Migration rules followed

- **Forward-only**: one new migration, no edits to any existing migration.
- **Reversible**: `down()` drops exactly the five tables `up()` created, in dependency order, nothing else.
- **No destructive migration**: purely additive — no existing table, column, or index is touched.
- **No production-data assumption**: every new table starts empty; nothing is backfilled.
- Foreign keys to the pre-existing `sites` table use `restrictOnDelete` (a site cannot be deleted out from under an active package); foreign keys between the five new tables use `cascadeOnDelete` (a child row has no meaning without its parent) — the same convention already used by `module_licenses`/`module_entitlements` in this codebase.

## Was a migration required at all?

Yes — task-authorized for this phase specifically ("If migration is required... rules: forward only, reversible where possible, no destructive migration"), and there was no way to represent "which site has activated which asset at which version, with append-only history" without new host-owned storage; nothing in Phase 1/2's DTOs or the module's own schema could hold this.
