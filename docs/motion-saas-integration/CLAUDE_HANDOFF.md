# Claude Integration Handoff

Status: **design handoff only; implementation requires a new authorized task**.

Claude must first read the project constitution, ADR-017 execution governance, accepted Motion integration ADR, existing module contracts, production readiness, template packaging, and this directory.

Official implementation sequence:

1. obtain approval for the integration ADR, contract/schema versions, ownership mapping, and migration strategy;
2. inspect canonical platform identity, tenant context, license, module registration, deployment, audit, queue, and feature-flag services;
3. implement thin Laravel adapters for the documented interfaces without importing host `App\` namespaces into shared package code;
4. introduce DTOs/read models with strict allowlists and stable error codes;
5. design forward migrations and transaction/idempotency/locking behavior, then test on disposable MySQL;
6. bind authorization and licensing deny-by-default at every boundary;
7. implement staging, integrity, projection, and activation through existing deployment infrastructure;
8. add all tests in `INTEGRATION_TEST_PLAN.md`, security review, real-browser QA, and operational runbooks;
9. enable read-only/shadow flags first for allowlisted internal sites; and
10. deploy only with backup, rollback, health checks, observability, and project-memory updates.

Do not duplicate Motion models/services/contracts, expose sources, auto-publish AI output, mutate published history, add tenant editing, or create a parallel deployment/updater.

