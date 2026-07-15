# Future Platform Integration

Status: **Deferred. Nothing in this document is implemented by the architecture phase.**

## Required future work

A separately authorized implementation phase must provide:

- real provider adapters for approved OpenAI, Claude, Gemini, or future providers;
- server-side provider credential management and provider policy;
- Platform Admin authentication and permission enforcement;
- canonical customer/site resolution using the platform tenant boundary;
- private upload sanitation and logo storage;
- editing-session, attempt, proposal, validation, approval, history, and variant persistence;
- immutable asset storage, checksums, retention, encryption, and signed access where required;
- isolated preview and asset-compilation workers;
- schema, editable-diff, accessibility, security, renderer, and package validators;
- admin-only selection, review, comparison, approval, rejection, and history UI;
- queues, idempotency, retry, rate limiting, observability, cost limits, and recovery;
- a separate authorized publication/deployment workflow; and
- customer-site consumption only after an approved runtime contract exists.

## Contract alignment

Future work should adapt these architecture schemas to the existing module contracts rather than create parallel responsibilities. Relevant existing conceptual/implemented boundaries include `MotionAiProviderContract`, asset pipeline/compiler/optimizer/preview/storage contracts, permission and Platform Admin resolvers, site resolver, audit logger, and deployment gateway documented in `../FUTURE_INTEGRATION_CONTRACT.md`.

Before adding code, the implementation team must inspect the actual current contracts and models. These documents do not authorize inventing duplicate services, fields, tables, endpoints, or customer-site logic.

## Suggested implementation sequence

1. Approve data classification, provider/privacy policy, and persistence ADR.
2. Map schemas to existing module value objects/contracts and identify genuine gaps.
3. Implement fake-adapter contract tests and validators first.
4. Add private persistence and immutable history with tenant isolation tests.
5. Add isolated storage/preview/compilation adapters.
6. Add Platform Admin application services and permissions.
7. Add admin-only UI after service/security behavior is tested.
8. Add one real provider adapter behind the neutral contract.
9. Perform security, privacy, cost, failure, and provider-retention review.
10. Design publication/runtime integration as a separate phase.

## Required decisions before implementation

- approved providers/models and data-retention/training policy;
- logo and prompt data classification, encryption, retention, and legal deletion;
- canonical customer-to-`site_id` mapping;
- storage technology, region, signing, and lifecycle policy;
- preview sandbox and renderer hardening;
- version allocation and transactional approval boundary;
- provider budgets, quotas, retries, and fallback policy;
- audit immutability mechanism; and
- publication/deployment authorization and rollback design.

## Explicit exclusions

This phase does not connect Laravel, database, main platform, customer-site, provider SDKs, UI, runtime API, or deployment. It does not modify Master Assets or create customer variants.
