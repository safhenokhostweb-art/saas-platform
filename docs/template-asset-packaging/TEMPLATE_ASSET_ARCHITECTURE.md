# Template Asset Architecture

## Boundary

The Template Builder owns layout, section requirements, typography intent, placement, and a `template-assets.json` reference manifest. The Motion Graphics Library owns canonical asset identity, immutable versions, metadata, compatibility, lifecycle, licensing, and compiled artifacts. A future adapter owns authenticated resolution, tenant/site authorization, persistence, audit, and delivery.

Templates must not embed, copy, rename, or overwrite Master Asset bytes. A template package is a versioned set of references plus rules. Resolution pins exact versions and checksums before any site package is activated.

## Package identities

- `platform-master/{asset_id}/{version}`: globally reusable source lineage.
- `template-package/{template_id}/{version}`: template-owned reference graph.
- `site-package/{site_id}/{package_id}/{version}`: isolated runtime snapshot.

An asset may appear in several packages without transferring ownership. Physical byte deduplication is an internal optimization and does not merge manifests, access controls, histories, or licenses.

## Failure model

Resolution fails closed for missing versions, unpublished/quarantined assets, incompatible metadata, unresolved required dependencies, invalid ownership, license conflicts, cycles, or cross-site private references. Optional failures may use a declared compatible fallback; undeclared substitution is forbidden.

