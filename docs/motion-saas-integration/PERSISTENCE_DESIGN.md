# Persistence Preparation

This is a logical model only; no tables or migrations are created.

| Entity | Relationships | Immutable core |
|---|---|---|
| `MotionAsset` | has many versions; platform owner | canonical ID, UUID, owner type, created identity |
| `MotionVersion` | belongs to asset and optional parent/restore source | version, source/artifact checksums, lineage, approved/published content |
| `TemplateAssetPackage` | template/version; has reference items | template ID/version, manifest checksum, dependency lock after approval |
| `SiteAssetPackage` | site/customer/template package; has variants/deployment records | site scope, package version, lineage, approved manifest/checksum |
| `AssetVariant` | exact Master/version and site package | source lineage, site/customer owner, generation fingerprint |
| `ApprovalRecord` | polymorphic exact candidate/checksum | actor, decision, checksum, reason, time, policy/report version |
| `LicenseSnapshot` | exact package/version and site | evaluated grant dimensions and decision reference |
| `DeploymentRecord` | site package activation attempt | idempotency, previous/next projection, status history, correlation |
| `AuditRecord` | actor/site/resource event | append-only event identity, safe payload checksum, time |

All tenant-owned records require non-null `site_id`; platform scope is explicit, never an implicit null comparison. Published versions and approvals are append-only. Rollback creates new versions/records. Foreign keys, unique constraints, tenant indexes, encryption/search strategy, retention, and transaction boundaries require an accepted implementation ADR before migrations.

