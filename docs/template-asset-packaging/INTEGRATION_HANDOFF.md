# Integration Handoff

Status: **candidate contract; no SaaS attachment authorized**.

Before Claude or another implementation agent connects this layer, it must:

1. read the project constitution, accepted integration ADR, Motion ownership/security documents, and this package;
2. freeze/version JSON Schemas and error codes with compatibility tests;
3. map Platform Admin, template, customer, and site identities without leaking host models into standalone code;
4. implement deny-by-default authorization, license, tenant isolation, and published-only catalog projection;
5. design append-only persistence and atomic site-package activation/rollback;
6. use production-grade archive/SVG/media validation and signed integrity;
7. add contract, cross-tenant, concurrency, failure recovery, and penetration tests;
8. pass real browser/RTL/reduced-motion/performance QA;
9. define feature flags, observability, retention, backup/recovery, incident response, and operational owners; and
10. deploy only under a separately approved task with rollback and health verification.

The static playground is executable contract evidence only. It must not be copied into a production authorization or deployment path.

