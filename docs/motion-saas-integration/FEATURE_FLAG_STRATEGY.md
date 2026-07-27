# Feature Flag Strategy

The future Platform owns flags; the standalone module does not read environment-specific SaaS configuration directly.

Recommended staged flags:

1. `motion_adapter_enabled`: bindings available, no customer traffic.
2. `motion_catalog_read_enabled`: admin read-only catalog.
3. `motion_package_prepare_enabled`: shadow candidate generation.
4. `motion_runtime_projection_enabled`: allowlisted pilot sites.
5. `motion_publication_enabled`: Platform Admin publication after all gates.

Disabled mode preserves standalone behavior and existing site runtime. Enabled mode requires an allowlisted site cohort, supported contract version, healthy dependencies, and audit/metrics. Flags cannot bypass permissions, licenses, validation, or approvals.

Emergency disable stops new preparation/publication/activation, keeps last known-good immutable runtime projections available under policy, revokes unsafe signed delivery, drains/cancels safe jobs, and retains history. Rollback order: disable exposure, stop mutations, restore previous projection through governed flow, then revert adapter code if needed. Schema removal is never an emergency response.

