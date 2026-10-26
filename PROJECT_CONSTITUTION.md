# Project Constitution

## 1. Purpose and Authority

This constitution defines the permanent engineering principles of the SaaS Platform workspace. It is the highest project-level architectural authority and applies to both repositories:

- `saas-platform`: platform administration, APIs, package distribution, and shared modules.
- `customer-site`: tenant-facing web runtime.

Implementation guides, workflows, project memory, and architectural decisions must remain consistent with this constitution. Amendments require an explicit architectural decision and must preserve an auditable history.

## 2. Enterprise Architecture Principles

- Preserve clear boundaries between platform, tenant runtime, shared packages, infrastructure, and external integrations.
- Prefer explicit contracts, dependency inversion, configuration-driven bindings, and replaceable adapters.
- Keep business rules in domain or application services rather than presentation, transport, or infrastructure layers.
- Reuse canonical services and components; do not create parallel implementations of the same responsibility.
- Make operationally significant workflows observable, recoverable, and auditable.

## 3. Workspace and Modular Architecture

- The two repositories form one product workspace and must evolve through compatible contracts.
- Shared packages must remain independently distributable and must not import host `App\` namespaces.
- Host integrations from packages must use contracts, service-provider bindings, or configuration-resolved classes.
- Module boundaries must be explicit; cross-module access must use public contracts rather than internal implementation details.
- Canonical shared UI and infrastructure components take precedence over module-local copies.

## 4. Data and Tenant Isolation

- `site_id` is the structural tenant-isolation boundary for tenant-owned data.
- Tenant context must be enforced in storage, queries, authorization, cache namespaces, queues, telemetry, and external operations.
- Platform-level null tenant context must be handled explicitly; implicit SQL null comparisons are forbidden.
- Cross-tenant access is deny-by-default and requires an explicit, authorized platform context.
- Data integrity constraints belong in both application validation and the database where enforceable.

## 5. Database and Migration Rules

- Applied or production migrations are immutable; schema evolution uses new forward migrations.
- Migrations must be deterministic, reviewable, reversible where practical, and safe for existing data.
- Destructive schema or data operations require a verified recovery path and explicit authorization.
- Production migration workflows must include backup, execution verification, and rollback or recovery planning.
- Indexes and constraints must support tenant scoping, integrity, and known query patterns.

## 6. Deployment and Installation Rules

- Deployment and installation must use the repository's canonical, versioned pipelines and adapters.
- A completed task must commit and push all task-owned changes to the configured Git remote, synchronize them to every affected production server, and verify post-deployment health.
- Documentation-only changes still require Git synchronization and server synchronization, but do not require runtime restarts or application deployment actions.
- If Git or server synchronization is unavailable, the task remains incomplete and the blocker must be recorded explicitly.
- Environment-specific hosts, paths, credentials, and provider details must not be embedded in application logic or portable artifacts.
- Releases must preserve rollback or recovery capability and must verify application health after execution.
- Long-running, remote, or failure-prone stages must be resumable or idempotent where practical.
- Installation and deployment contracts must remain backward compatible unless an approved ADR defines a migration path.

## 7. Security Rules

- Authentication, authorization, tenant isolation, and license enforcement are server-side and deny-by-default.
- Secrets, credentials, tokens, certificates, and keys must never be committed, exposed in responses, or written to unsafe logs.
- Sensitive credentials must use approved encrypted storage and controlled rotation mechanisms.
- All external input, uploaded content, paths, archives, and remote responses must be validated before use.
- Security-sensitive operations require least privilege, auditable events, and explicit failure behavior.

## 8. Recovery and Continuity Rules

- Backups are valid only when integrity can be verified and restoration is operationally testable.
- Recovery artifacts must be portable and free of machine-specific absolute paths, embedded credentials, and fixed server identities.
- Recovery workflows must preserve manifests, checksums, audit history, and required schema upgrades.
- Failed operations must leave enough state for diagnosis, retry, rollback, or safe manual recovery.
- Production continuity must not depend on chat history, a single workstation, or a single external provider.

## 9. Licensing Rules

- License validation is a server-side platform capability and cannot rely on presentation-layer controls.
- License identity and scope must bind to the correct customer, tenant, domain, product, and enabled capability.
- Invalid, absent, expired, or unverifiable licenses fail closed except where a documented recovery policy explicitly applies.
- Licensing logic must remain centralized, auditable, and compatible across both repositories.
- Commercial policy changes require owner authorization and an ADR or equivalent permanent record when they affect architecture.

## 10. Performance and Scalability Rules

- Query design must avoid unbounded scans, N+1 access, and cross-tenant cache leakage.
- Tenant-scoped indexes, bounded pagination, queues, streaming, caching, and circuit breakers must be used where workload characteristics require them.
- Long-running work must not block interactive requests when an asynchronous workflow is appropriate.
- Performance optimizations must preserve correctness, tenant isolation, observability, and recovery behavior.
- Capacity assumptions and operational limits must be configurable and measurable.

## 11. Coding Standards

- Follow the versions, conventions, and canonical patterns recorded in current project architecture and state.
- Keep changes minimal, cohesive, typed where supported, testable, and consistent with surrounding code.
- Validate at system boundaries and return controlled errors without leaking internals.
- Preserve the Persian RTL product standard and canonical design system for user-facing interfaces.
- Remove dead duplication rather than introducing compatibility forks or hidden behavior.

## 12. Backward Compatibility

- Existing public APIs, package contracts, data formats, deployment interfaces, recovery artifacts, and supported workflows remain compatible by default.
- Breaking changes require explicit impact analysis, migration strategy, rollback strategy, tests, documentation, and an approved ADR.
- Compatibility shims must be thin, documented, and must delegate to a single canonical implementation.
- Historical records remain immutable evidence; they do not become competing active authorities.

## 13. ADR Compliance

- `docs/project-memory/DECISIONS.md` is the canonical architectural decision log.
- ADRs refine implementation choices but cannot silently override this constitution.
- A conflict between an ADR and this constitution must be resolved by an explicit constitutional amendment or a superseding ADR that records the amendment.
- ADR content must not be duplicated across governance, wrappers, reports, or project memory summaries.

## 14. Governance Hierarchy

Authority descends in this order:

```text
PROJECT_CONSTITUTION.md
        ↓
AGENTS.md
        ↓
Agent wrapper
        ↓
Project memory
        ↓
Current task
```

Lower layers may add context and execution detail but may not contradict higher layers.

## 15. Non-Negotiable Compliance

Every change must preserve tenant isolation, package independence, data integrity, security boundaries, recoverability, licensing enforcement, backward compatibility, and ADR traceability. A change that cannot satisfy these principles requires explicit architectural resolution before implementation.

## 16. Commercial Iranian UI/UX Standard

Adopted 2026-10-25 by owner directive (ADR-060). Every user-facing interface — tenant sites, theme widgets, headers, footers, sections, and the builders that author them — must be an elite, enterprise-grade, visually refined, and highly intuitive commercial experience for modern Iranian businesses: RTL-first, clean Persian typography, purposeful micro-interactions, mobile-first, and conversion-focused. Mediocre, classic, generic, or fragile code and design are not acceptable; every widget must feel like a premium SaaS product with a frictionless experience.

This standard is enforced through these minimum requirements:

- **RTL and typography:** layouts use logical (inline/block) properties. Persian text uses locally served Vazirmatn (never a third-party font CDN). Persian digits and currency (toman) are produced by one shared formatter, not ad-hoc string handling.
- **Design tokens:** colors, radii, and spacing come from theme tokens (`--tb-*`), not hardcoded hex defaults. Default states meet WCAG 2.1 AA contrast.
- **Mobile-first and motion:** every interface is verified at desktop, tablet, and mobile widths. Touch interaction works, and `prefers-reduced-motion` is respected.
- **Authoring experience:** every author-facing property has a real visual control, generated from its declared schema. Raw JSON editing is a fallback for schema-less legacy data only, never the intended path for new work.
- **Honest interactions:** forms, search, and calls-to-action perform their real function or are explicitly marked as preview-only in the editor. No control may appear to succeed without doing anything.
- **Safe output:** author-supplied URLs are scheme-validated before rendering, and author-supplied colors and styles are allowlisted, never trusted verbatim.
