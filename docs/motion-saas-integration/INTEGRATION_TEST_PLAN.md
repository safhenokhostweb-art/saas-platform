# Integration Test Plan

No Laravel integration tests run in this phase. The future implementation must automate:

1. install a template with required and optional Motion assets;
2. create an immutable customer Site Asset Package;
3. apply only an approved, correctly scoped Brand Variant;
4. reject an industry/section/style/theme/performance-incompatible asset and validate fallback;
5. prevent customer access to Master source and authoring metadata;
6. rollback by creating a new version while preserving history;
7. upgrade a template package without silently changing the active site;
8. isolate two customers across queries, cache, queues, files, URLs, logs, exports, and errors;
9. fail closed on absent/expired/mismatched license;
10. prove Runtime Projection allowlist and forbidden-field rejection.

Additional mandatory tests cover permission matrix, idempotency, concurrency/stale checksum, transaction rollback, feature disable/emergency behavior, malicious SVG/archive/provider output, audit completeness, contract-version compatibility, backup/restore, real-browser RTL/accessibility/reduced-motion, and performance budgets.

Each test records actor/site scope, fixture version/checksum, expected stable code, database/storage side effects, audit event, and cleanup. Cross-tenant tests must be genuine negative controls, not missing-context false positives.

