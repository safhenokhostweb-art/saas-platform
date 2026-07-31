# Motion Graphics Persistence Foundation

Status: **implemented — Phase 3 of the Motion Graphics SaaS integration**. This layer gives the SaaS Platform its own persistence for "which Motion assets belong to a template, which are assigned to a site, which version is active, how rollback works, and how ownership is tracked" — without duplicating the standalone module's own data and without ever touching the module's tables.

```text
Platform Master Asset            (standalone module — packages/saas-platform/modules/motion-graphics)
        |
        | resolved via docs/motion-read-contracts/ (Phase 2), never queried directly
        v
Template Asset Package           (host — frozen {asset_id, version} references for one template)
        |
        v
Site Asset Package                (host — one site's binding to a template, current-pointer + brand)
        |
        v
Activated Runtime Projection      (Phase 2's MotionRuntimeProjectionReadContract, at the bound version)
```

This is **not** the final deployment phase: no customer-site connection, no real customer activation, no UI. It is the persistence and version-binding foundation those later phases will build on.

## Documents

| Document | Covers |
|---|---|
| [PERSISTENCE_MODEL.md](PERSISTENCE_MODEL.md) | The five tables/entities and their fields |
| [SITE_PACKAGE_MODEL.md](SITE_PACKAGE_MODEL.md) | Site Asset Package structure and lifecycle |
| [VERSION_BINDING_RULES.md](VERSION_BINDING_RULES.md) | How a version gets frozen and stays frozen |
| [ROLLBACK_MODEL.md](ROLLBACK_MODEL.md) | Append-only rollback behavior |
| [ACTIVATION_FLOW.md](ACTIVATION_FLOW.md) | The end-to-end activation pipeline and its validation gates |
| [OWNERSHIP_RULES.md](OWNERSHIP_RULES.md) | Who may create/activate/roll back, and why |
| [MIGRATION_DECISION.md](MIGRATION_DECISION.md) | Which tables belong to the host vs. the module, and why |
| [INTEGRATION_UPDATE.md](INTEGRATION_UPDATE.md) | New services, bindings, and how Phase 1/2 pieces are reused |

## What this phase does not do

No customer-site connection, no real customer deployment activation, no UI, no source file or AI metadata exposure, no Master Asset modification, no bypass of the module's ownership rules, no removal of immutable versioning, and no direct coupling between SaaS Eloquent models and the module's internal Eloquent models (enforced structurally — see `tests/Feature/Modules/MotionGraphics/Persistence/PersistenceSecurityTest.php`).
