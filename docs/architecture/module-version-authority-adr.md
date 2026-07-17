# ADR: Module Version Authority Decision

**Status:** Accepted
**Date:** 2026-07-10
**Phase:** Phase 5 / Commit 12
**Supersedes:** No prior ADR existed for this topic — version handling was previously implicit across `module.json`, `modules.version`, and `module_releases`.
**Related:** [update-safety-rules.md](update-safety-rules.md), [distributed-module-delivery.md](distributed-module-delivery.md)

---

## بخش ۱ — Architecture Decision

**Title:** Module Version Authority Decision

**Decision:**
`module_releases` is the single authoritative source for **released** module versions — i.e. the version a customer site is told exists, the version compared during update-eligibility checks, and the version referenced for rollback.

No other table, file, or service may be treated as the source of truth for "what version is currently released." `module.json` and `modules.version` continue to exist, but under the narrower responsibilities defined in بخش ۳ below.

This decision was made following the Phase 5 / Commit 12 Audit, which found three parallel version sources with no reconciliation mechanism between them, and confirmed that the customer-facing update pipeline (`DeploymentResolver`, `ModuleUpdateEligibilityService`, `UpdateController::moduleHistory`) was structurally incapable of reporting real update availability as a result.

---

## بخش ۲ — Problem Statement

Prior to this decision, "module version" resolved to three different values depending on which code path asked:

```text
module.json
    ↓
Development Version
    (edited by a developer via `module:version`; describes the working
     copy of a package in packages/saas-platform/modules/{slug}/)

modules.version
    ↓
Database Cached Version
    (a column on the `modules` table, seeded once at `1.0.0`
     and never written to again by any production code path)

module_releases
    ↓
Release History
    (a table designed to hold one row per published version,
     with changelog, release_type, and distributed_at — but with
     no producer in production code, so it is always empty)
```

### Why multiple sources caused drift

- **No reconciliation step existed.** Nothing compared `module.json`'s version against `modules.version` or `module_releases`, so the three values could diverge indefinitely without detection.
- **The two consumer-facing tables never received real data.** `module_releases` — the table every customer-facing consumer (`DeploymentResolver`, `ModuleUpdateEligibilityService`, `UpdateController::moduleHistory`) prefers — had no writer. `modules.version` — its fallback — was frozen at its seed value.
- **Concrete example (forms module, verified during the Commit 12 Audit):** `module.json` reported `1.1.0`; the database `modules.version` row reported `1.0.0`; `module_releases` had zero rows; no release manifest had ever been generated on disk. The result: every customer-facing update check permanently reported the module as up to date at `1.0.0`, regardless of real changes shipped in `1.1.0`. This was not a theoretical risk — it was the actual, reproducible behavior of the system prior to this ADR.
- **Two unrelated lifecycles shared vocabulary.** `module.json` governs the *build-time* concerns of an internal composer package (compatibility, schema_version, namespace). `module_releases` governs the *distribution-time* concern of what a customer has been told exists. Treating either one as authoritative for the other's job is what produced the drift.

---

## بخش ۳ — Final Responsibility Map

### `module.json`

**مسئول (Responsible for):**
- Development — the working version a developer bumps via `module:version`
- Build Validation — semver format and structural checks enforced by `module:build`
- Package Metadata — compatibility constraints, `schema_version`, namespace, provider, dependencies

**نیست (Not responsible for):**
- Customer Update Announcement — `module.json`'s version MUST NOT be read directly by any customer-facing update check, eligibility service, or marketplace display. It describes intent and working state, not what has been published.

### `module_releases`

**مسئول (Responsible for):**
- Published Version — the only value that may be reported to a customer site as "the latest version"
- Changelog — per-version release notes surfaced via `UpdateController::moduleHistory`
- Release History — the ordered, auditable record of what shipped and when
- Update Availability — the comparison basis for `DeploymentResolver` and `ModuleUpdateEligibilityService`
- Rollback Reference — `is_active` allows a bad release to be deactivated without deleting history; prior rows remain available as rollback targets

### `modules.version`

**مسئول (Responsible for):**
- Cache — a denormalized convenience column, kept in sync with the latest `module_releases` row for the module
- Performance Optimization — avoids a join/subquery for callers that only need a cheap current-version read

**قانون (Rule):**
هیچ‌وقت دستی تغییر نکند. `modules.version` must never be written to directly by any admin action, controller, or ad hoc script. Its only legitimate writer is the same release-publication step that inserts the corresponding `module_releases` row — it is derived data, not a decision point. If `modules.version` and the latest `module_releases.version` for a module ever disagree, `module_releases` wins and the cache is stale by definition.

---

## بخش ۴ — Final Version Lifecycle

```text
Developer
  │
  │  edits module.json (module:version {slug} --patch|--minor|--major)
  ↓
module.json version
  │
  │  working copy updated; composer.json kept in sync
  ↓
Release Validation
  │
  │  module:build — structural + semver format checks
  │  release-time checks — see update-safety-rules.md Rule 3 & Rule 5
  │  (reversible migrations; schema_version incremented if migrations changed)
  ↓
Create ModuleRelease
  │
  │  a new module_releases row is the ONLY event that promotes a
  │  module.json version from "development" to "released"
  ↓
Publish Release
  │
  │  release manifest + distribution artifact (git tag / Satis package)
  │  modules.version cache column updated to match
  ↓
Update Customer Availability
  │
  │  module_releases becomes visible to:
  │    - DeploymentResolver
  │    - ModuleUpdateEligibilityService
  │    - UpdateController::checkModules / moduleHistory
  ↓
Tenant Update Detection
  │
  │  customer site compares its own site_modules.installed_version
  │  against the latest module_releases row — this is the first point
  │  in the entire flow where "is an update available?" can be
  │  correctly and truthfully answered
```

**Key invariant established by this ADR:** a version is not "released" until a `module_releases` row exists for it. A bump to `module.json` alone is a private development event with no visibility to any customer site.

---

## Consequences

- Any future code that needs "the current version of module X for a customer" must query `module_releases` (directly, or via `ModuleRelease::getLatestVersionForModule()`), never `module.json` and never `modules.version` directly.
- The Phase 6.98 package tooling (`module:release`, `ReleaseManifest`, `ModuleUpdateOrchestrator`) will need an implementation change to actually produce `module_releases` rows — this ADR records the target architecture only; the producer, backfill, and validation-rule implementation are tracked as separate, later commits per the Commit 12 Audit's roadmap (12-B, 12-C onward) and are out of scope for this document.
- Until that producer exists, the system remains in the pre-ADR state described in بخش ۲ (i.e. this document defines the destination, not yet the current runtime behavior).
