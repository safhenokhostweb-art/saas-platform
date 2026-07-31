# Site Asset Package Model

A `SiteAssetPackage` is identified by its **natural key**: (`site_id`, `template_reference`) — one site has at most one active binding to a given template, ever. This is enforced by a unique index (`motion_sap_site_template_ref_unique`), not just application logic.

## Why `template_reference` is stored again here

`TemplateAssetPackage` rows are immutable and versioned — a template's requirements changing creates a **new** `TemplateAssetPackage` row with a new `package_version`, never an edit. If `SiteAssetPackage` were keyed on `template_asset_package_id` directly, activating a newer template version for an already-bound site would violate the FK's implied identity and force creating a **second**, parallel `SiteAssetPackage` row for the same site+template — which would make "upgrade" impossible to express (there would be two competing rows instead of one row that moved forward).

Storing the stable `template_reference` as the natural key, with `template_asset_package_id` as a **mutable current pointer**, solves this: activating a newer package for a site that already has a binding updates the existing row's pointer in place (see `SiteAssetPackageActivationService::findOrCreateSitePackage()`), and each `SiteAssetPackageItem` is then individually re-evaluated — an asset whose frozen version didn't change is left alone; one that did change gets an `upgrade` `AssetActivation` entry.

## Lifecycle

```text
pending -> active -> (rolled_back | retired)
```

`activation_status` starts at `pending` on row creation and moves to `active` once `SiteAssetPackageActivationService::activate()` completes successfully. `rolled_back`/`retired` are reserved for a future phase's explicit site-level actions (not driven automatically by this phase — an individual asset's rollback, task item 5, operates at the `SiteAssetPackageItem`/`AssetActivation` level and does not by itself retire the whole package).

## What a Site Asset Package never stores

- Any of the module's own ownership fields (`ownership_type`, `owner_site_identifier`) — those stay solely in the module. `SiteAssetPackage.site_id` records **platform-side assignment**, not module-side ownership; see [OWNERSHIP_RULES.md](OWNERSHIP_RULES.md) for how the two interact.
- Source files, editable schemas, or AI metadata — nothing here ever holds them, because the resolution pipeline (`MotionRuntimeProjectionReadContract`, from Phase 2) never returns them in the first place.
- Another site's data — `site_id` scoping is enforced at every read and write; see `tests/Feature/Modules/MotionGraphics/Persistence/SiteIsolationTest.php`.
