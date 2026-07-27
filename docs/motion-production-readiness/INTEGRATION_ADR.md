# Proposed Integration ADR: Motion Graphics Adapter Boundary

Status: **proposed; not accepted and not implemented**.

## Context

The standalone Motion Graphics ecosystem must connect later without transferring Master ownership, exposing authoring data, or coupling its provider-neutral contracts to Laravel/runtime details.

## Decision proposal

```text
SaaS Platform
    |
    | authenticated commands / published read model
    v
Motion Graphics Adapter
    |
    | versioned standalone contracts
    v
Standalone Motion Module
```

The SaaS Platform owns identity, Platform Admin authorization, tenant/site context, entitlements, persistence transactions, audit transport, and delivery policy. The adapter translates platform models to versioned module commands/read models and enforces data minimization. The standalone module owns asset validation semantics, lifecycle invariants, compilation/preview contracts, lineage, and production readiness rules.

Master sources and private AI/editing metadata remain in protected platform-controlled authoring storage. Tenants receive only a published runtime projection. The adapter may not leak ORM models into standalone contracts or allow the standalone module to infer platform permissions.

## Required contracts

- `CreateDraft`, `SubmitReview`, `ApproveVersion`, `PublishVersion`, `CreateRestoreDraft` commands;
- `ValidateProductionManifest` and immutable validation report;
- `PublishedMotionProjection` with strict allowlist;
- tenant/site/license authorization decision;
- idempotency, optimistic version, checksum, and audit correlation fields;
- stable error codes without sensitive detail.

## Consequences

Integration remains replaceable and testable, but requires an accepted platform ADR, schema versioning, transactional publication design, contract tests, and an explicit migration/rollback plan. This document grants no authority to modify SaaS code.

