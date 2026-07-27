# Integration Boundary

## SaaS Platform owns

- customers, users, authentication, roles, and Platform Admin authority;
- structural `site_id` tenant context and site lifecycle;
- template selection and deployment orchestration;
- centralized licensing/entitlements and commercial policy;
- platform transactions, audit transport, queues, feature flags, observability, and delivery infrastructure.

## Motion Graphics module owns

- canonical Asset Library and immutable Motion versions;
- rendering, placement, compatibility, packaging, lineage, and ownership-validation rules;
- customer variant lifecycle and quality/security validation semantics;
- construction of a strict published Runtime Projection.

## Adapter owns

The future Laravel adapter maps platform scalar identities and authorized context into versioned module requests, invokes module contracts, maps controlled results back, and enforces data minimization. It must not pass ORM models into the module, query module internals directly, infer permissions, duplicate lifecycle logic, or expose authoring data.

Cross-boundary requests carry contract version, opaque actor/customer/site/template IDs, correlation and idempotency IDs, expected version/checksum, license decision reference, and locale/direction where needed. Unknown versions, missing site context, stale checksums, or unavailable bindings fail closed.

