# Testing & Delivery Governance

## Status and Authority

Canonical for both repositories (`saas-platform`, `customer-site`). Sits in
the `AGENTS.md` tier of the authority hierarchy defined in
`PROJECT_CONSTITUTION.md` §14 — it does not add a new tier, and it cannot
contradict the Constitution or an ADR in `docs/project-memory/DECISIONS.md`.

This document does not re-author working mechanisms that already exist. It
tells you which gates are mandatory, and points at the canonical
implementation for each. Where an implementation is missing, that is stated
explicitly as a gap, not filled with placeholder content.

Adopted 2026-09-11, after a production diagnosis (Theme Builder / Page
Builder edits not reaching the public page) repeatedly stalled on three
recurring questions: what must be tested, which environment is authoritative,
and what "done" means. See `docs/project-memory/DECISIONS.md` for the ADR.

### Relationship to existing documents

| Document | Status | Relationship |
|---|---|---|
| `PROJECT_CONSTITUTION.md` | Authoritative | This document is subordinate to it. |
| `AGENTS.md` | Authoritative | Bootstrap Sequence and Documentation Ownership table both point here (see the edit accompanying this document). |
| `customer-site/docs/MASTER-DEVELOPMENT-DIRECTIVE.md` | **Historical, superseded** | Predates the AGENTS.md/Constitution split; `customer-site/AGENTS.md` already defers all governance to this repository. Marked superseded in place per Constitution §12 rather than rewritten — it remains a valid historical record of Phase 1–5 planning. Its "Branching Rules" and "Before Any Code Change"/"Mandatory Operating Rule" sections specifically are superseded by the explicit canonical replacement at §9 below, not silently dropped — see the preservation matrix in `docs/project-memory/DECISIONS.md`. |
| `customer-site/docs/RELEASE_GATE.md` | Authoritative, unchanged | Canonical Deployment Gate implementation. Referenced, not duplicated. |
| `customer-site/docs/KNOWN-TEST-FAILURES.md` | Authoritative, unchanged | Canonical Known Baseline Failure Registry for customer-site's PHPUnit suite. Referenced, not duplicated. |
| `customer-site/docs/BUILDER-QA.md` / `BUILDER-TEST-CHECKLIST.md` | Authoritative, unchanged | Canonical spec of Builder behavior to verify. Its manual checklist is the interim procedure; §4 below is the plan to make it automated-first. |
| `customer-site/tests/browser/` (Playwright) | Authoritative, unchanged | Canonical browser-acceptance mechanism. See §3. |
| The ~300 files under `saas-platform/docs/` (phase reports, certifications, audits) | Evidence only | Per `AGENTS.md` § Governance Loading: "Historical governance audits and migration reports are evidence only; they do not override active governance." Nothing in this document changes that rule or re-litigates that backlog. |

---

## 1. Test Layers

| Layer | Name | Mandatory when | Canonical mechanism |
|---|---|---|---|
| L0 | Static/diff integrity | Always | `git status`, `git diff --stat`, `git diff --check` |
| L1 | Focused unit/service tests | Change touches a service, model, or pure logic | `php artisan test --filter=...` / `phpunit` on the touched test class(es) |
| L2 | Focused feature/controller tests | Change touches an HTTP endpoint, controller, or persistence path | `php artisan test --filter=...Feature...` |
| L3 | Focused browser acceptance | Change affects DOM/JS/CSS/responsive/real-form/runtime-render behavior (see §3) | `customer-site/tests/browser/*.spec.js` via `npm run test:browser` |
| L4 | Relevant regression sweep | Change touches a shared contract, migration, or module more than one feature depends on | Re-run the affected suite(s) in full, not just the new test |
| L5 | Repository-wide suite | Change touches shared/core infrastructure, auth, tenant isolation, or licensing | Full `php artisan test` (PHP) and, if Builder/runtime touched, full `npm run test:browser` |
| L6 | Deployment verification | Any change being shipped to production | `php scripts/validate-release.php` (see `RELEASE_GATE.md`) against the staged/extracted artifact |
| L7 | Production smoke validation | After a production deploy that touches Builder, page render, or theme runtime | Production Validation Contract, §7 |

A change that only touches L0-scoped files (docs, comments, non-executed
config) stops at L0. Every other layer's mandatory-or-not determination for a
given change comes from the Feature Acceptance Matrix, §2.

---

## 2. Feature Acceptance Matrix

| Change type | L1 | L2 | L3 (browser) | DB test | Security test | Responsive | L7 smoke | Deployment evidence |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Backend service-only (no HTTP surface, no render) | Required | — | — | If persistence changed | — | — | — | Standard |
| Migration/schema change | — | Required | — | Required (forward + rollback where practical) | If touches tenant-scoped tables | — | — | Standard + migration plan |
| Admin CRUD | Required | Required | Recommended | Required | Required (foreign-tenant denial) | — | — | Standard |
| Builder/Canvas change (Theme Builder, Page Builder) | Required | Required | **Required** — save→reload→preview→public (§4) | Required | — | Required | **Required** (§7) | Standard |
| Theme/runtime change (renderer, resolver, revision selection) | Required | Required | **Required** | Required | — | — | **Required** | Standard |
| Media change (upload, resolver, storage) | Required | Required | Required | Required | Required (path traversal, MIME) | — | Recommended | Standard |
| Auth/security-sensitive change | Required | Required | If UI involved | Required | **Required** (Constitution §7 + §6 below) | — | — | Standard |
| Deployment-system change (agent, service, pipeline) | Required | Required | — | — | Required (allowlist/authority checks) | — | — | Deployment-system's own test suite; never self-certified by inspection alone |
| Extension SDK change | Required | Required | Required (`extension-sdk.spec.js`) | If persistence changed | Required (sandbox/isolation) | — | — | Standard |
| Commercial/release change (pricing, entitlement, license) | Required | Required | — | Required | Required | — | — | Standard + explicit owner sign-off (Constitution §9) |
| Frontend-only visual change (no logic change) | — | — | Required if interactive; screenshot/visual diff otherwise | — | — | Required | — | Standard |
| PWA/service-worker change | — | Required | Required (manifest, offline behavior) | — | — | — | Recommended | Standard |

"Standard" deployment evidence = L6 (RELEASE_GATE.md) passes and the real
deployment-system records Artifact SHA = Deployment SHA = Production SHA for
the shipped commit (§8).

A task that cannot determine its row here should ask, once, rather than
default to the lightest row.

---

## 3. Browser Acceptance Rules

Browser verification is required whenever behavior depends on DOM,
JavaScript, CSS, responsive layout, real forms, navigation, upload, preview,
runtime rendering, Builder save/reload, service worker, or client-side state.
This is not a new rule — `customer-site/tests/browser/README.md` already
states the mechanism; this section makes it mandatory rather than optional.

Rules for any browser test:

- Use real user flows: real login form, real routes, no auth bypass, no
  diagnostics substitute (already the harness's design — do not weaken it).
- No arbitrary `sleep`; wait on real conditions (element state, network
  idle, navigation).
- No force-click / force-fill to paper over an element that isn't actually
  interactable — that hides the exact class of defect this governance exists
  to catch.
- Test actual runtime behavior, not just that an API call was made.
- Verify meaningful responsive widths when the change is visual (desktop,
  tablet, mobile — match `BUILDER-TEST-CHECKLIST.md` §3.2's three states
  unless the change is provably width-independent).
- Gate on console errors, page errors, and failed network requests during
  the flow — a passing assertion next to a red console error is not a pass.
- Where the change is a Builder edit, verify save → reload → render per §4.

Isolation: browser tests run against `customer_site_browser` (an isolated
MySQL database, never the dev or PHPUnit database — see
`tests/browser/README.md`). Never point a browser test at a shared or
production database.

---

## 4. Builder-Specific Delivery Contract

Canonical invariant, for both Theme Builder and Page Builder:

```
AUTHORING → SAVE → RELOAD → PREVIEW → PUBLIC RENDER
```

"Persisted in the database" is not acceptance. Every one of the four
arrows must be verified. This is the exact lesson from the 2026-09-11
production incident: deployment, artifact, and database layers were all
verified clean, and the defect was still only reachable by walking this
chain.

**Current state of automation** (verified against the actual harness, not
assumed):

- `tests/browser/smoke.spec.js` already proves login → Builder → one real
  built-in-block interaction → save → reload → persisted, for Theme Builder.
- Not yet confirmed/extended: explicit verification that the **public page**
  (not just the Builder's own reload) reflects the change, for both Theme
  Builder and Page Builder, and for capabilities beyond the one block
  `smoke.spec.js` covers.

**Required automated coverage per capability**, where the capability exists
on a given element — behavior, not serialization:

| Capability | Assertion |
|---|---|
| text | rendered text content matches |
| color | effective computed style (not just saved hex) matches |
| spacing | effective computed padding/margin matches |
| alignment | effective computed text-align/justify-content matches |
| border | effective computed border style/width/color matches |
| border-radius | effective computed border-radius matches |
| media | resolved `src` (or `background-image` url) matches and 200s |
| link | rendered `href` matches |
| visibility | element is actually present/absent in the rendered DOM, not just a flag in the saved payload |
| responsive state | the property differs correctly across the three widths in §3 |
| repeater | correct item count and order in rendered output |
| slider/carousel | actual runtime config (autoplay, interval, slide count) reflected, not just markup presence |
| nested children | parent-child structure preserved through save→reload→render |

**Gap tracking**: extending `smoke.spec.js` (or adding sibling specs) to
cover the public-render leg and the remaining capabilities above is
implementation work, out of scope for this governance task. It must be
opened as tracked work before the next Builder/Canvas change ships (per the
Feature Acceptance Matrix row), not discovered again mid-incident.

---

## 5. Automated Coverage Ownership

**Claude/Codex owns:**

- Focused tests (L1–L2), regression tests (L4), browser QA (L3)
- Schema and migration verification
- Cross-tenant/site isolation checks (Constitution §4)
- Responsive checks
- Runtime behavior checks (§4 table)
- Diff review, dependency/lockfile review

**The user owns only:**

- Unavoidable real-production confirmation (§7 — kept narrow by design)
- Product/business approval
- Explicit authorization for risky production actions (cache clear, service
  restart, CDN purge, DB mutation)

Routine element-by-element manual QA (e.g. working through
`BUILDER-TEST-CHECKLIST.md` by hand for a normal change) is **not** a
substitute for L3 automated coverage and must not be the default delivery
path. The checklist remains valid as the human-readable spec of what the
automated suite must cover (§4) and as a fallback only when automation for
that specific capability doesn't exist yet.

---

## 6. Regression Classification Rules

| Classification | Requires |
|---|---|
| `NEW_REGRESSION` | Failure did not reproduce on the baseline commit |
| `PRE_EXISTING_FAILURE` | Reproduced on baseline via `git checkout <baseline-sha> -- .` + re-run (the method `KNOWN-TEST-FAILURES.md` already uses) |
| `HARNESS_FAILURE` | Proven to be the test runner/environment (e.g. server-restart cascade), not the application, with evidence isolating it |
| `ENVIRONMENT_FAILURE` | Proven to be local environment (missing binary, wrong PHP version) — not applicable to CI/production |
| `EXPECTED_BLOCKED` | Feature intentionally gated (license, module not enabled) and the test correctly reflects that |
| `UNRELATED_FAILURE` | Proven by code-path analysis that the failing area shares no code with the change — proof required, not assertion |
| `FLAKY_UNPROVEN` | Inconsistent across ≥2 runs with no environment/harness explanation yet — flag, do not silently ignore |
| `SECURITY_BLOCKER` | Any authorization/isolation/injection finding — blocks delivery regardless of scope |
| `DATA_INTEGRITY_BLOCKER` | Any finding that data could be lost, corrupted, or cross-tenant leaked — blocks delivery regardless of scope |

No task may write "unrelated" or "pre-existing" without one of: a baseline
reproduction, or an explicit code-path proof included in the report. This
mirrors `KNOWN-TEST-FAILURES.md` §3–4's existing standard, generalized to
apply outside that one file.

---

## 7. Production Validation Contract

Principle: automate everything possible before production; production
validation stays narrow.

**Builder runtime change** (the pattern used in the 2026-09-11 diagnosis):
edit one ordinary text field with a unique, dated marker string → save →
reload the editor → check Preview → check the exact public page in a
private/incognito window. Report PRESENT/ABSENT at each step, not just
"looks fine."

**Media change**: upload one file → select it → confirm it renders at the
resolved URL.

The user must not be asked to re-run a full QA pass in production. If more
than the single narrow check above seems necessary, that is a signal the
pre-production automated coverage (§3–4) was insufficient — fix that gap,
don't compensate for it with more manual production testing.

---

## 8. Environment Authority Rules

This is the second hard lesson from the 2026-09-11 diagnosis and is
non-negotiable:

- **Production deployment facts (Artifact SHA, Deployment SHA, Production
  SHA, active release, agent last-seen) must come from the real
  deployment-system's own records** — its dashboard or, if read access
  exists, its database — **never** from local git HEAD, a local working
  tree's dirty/clean state, or a local copy of the deployment-system's own
  SQLite database. A local copy of `deployment-system/service/` is
  development/test state for that tool, not an oracle for what's running in
  production. Reconcile identity (agent names, registered servers) before
  trusting any instance as authoritative.
- Local environment tiers, for clarity:
  - **LOCAL**: development only.
  - **TEST**: isolated PHPUnit DB.
  - **BROWSER**: isolated `customer_site_browser` DB (§3).
  - **PRODUCTION**: real deployment-system artifact/release records only.
- A dirty or divergent git workspace on a deploy-target agent does not by
  itself mean production is compromised — verify whether the deploy pipeline
  actually sources from that workspace or from an independently-built
  artifact before concluding either way. For this project's actual pipeline,
  that answer lives in the separate `deployment-system` repository's
  `agent/lib/ArtifactManager.php` and `agent/lib/ReleaseManager.php` (not
  part of `saas-platform` or `customer-site`) — read those, don't assume.

---

## 9. Authorization Policy

This section is the explicit canonical replacement for two rules that
previously existed only in the now-superseded
`customer-site/docs/MASTER-DEVELOPMENT-DIRECTIVE.md` ("Before Any Code
Change", "Mandatory Operating Rule", "Branching Rules"). It modernizes them
for the actual working pattern (task-scoped explicit authorization in
conversation, not a separate written Analysis Report or a branch mandated
for every change) without silently dropping the underlying safety intent.

1. A dedicated branch is **not** mandatory for every change. Direct commits
   to `main` are allowed **only** when the current task explicitly
   authorizes committing to `main`. A dedicated branch remains required
   whenever the current task or a repository-specific policy explicitly
   calls for one.
2. Commit and push remain explicit, separate delivery gates (§10) — passing
   tests does not by itself authorize either.
3. If the current task explicitly authorizes commit and/or push once
   specified acceptance checks pass, that instruction **is** the approval.
   Do not ask for a duplicate confirmation once those checks have actually
   passed.
4. If the current task does **not** explicitly authorize commit/push, stop
   at `READY_FOR_FINAL_REVIEW` and wait for explicit authorization before
   advancing to `READY_TO_COMMIT`.
5. Never infer commit/push authorization merely because implementation or
   tests passed — passing gates makes a change eligible, not authorized.
6. Deployment always requires its own separate explicit authorization,
   distinct from commit/push authorization, unless the current task
   explicitly authorizes deployment too. Commit/push authorization never
   implies deploy authorization.
7. No force push, rebase, reset, stash, or clean, on any branch, unless
   explicitly authorized for that specific action — this is the canonical
   replacement for the old directive's "force pushing over important
   branches" / "deleting branches or tags" safety rules, generalized to the
   equivalent destructive git operations.

## 10. Commit / Push / Deployment Gates

**Commit gate** — no commit if:

- L0 fails (`git diff --check`, unexplained files staged)
- A required L1/L2/L3 test for the change's Feature Acceptance Matrix row
  wasn't run or failed
- An unexplained regression exists (not classified per §6)
- A migration path is unsafe per Constitution §5
- Site/tenant isolation is weakened (Constitution §4)
- Unrelated WIP got staged alongside the intended change

**Push gate** — no push if the commit gate above is incomplete; verify exact
file list, no force push, branch matches expectations, local HEAD is what
you intend to push.

**Deployment gate** — `RELEASE_GATE.md` is canonical for customer-site.
Require, where applicable: artifact commit identity, artifact integrity,
explicit deployment target, health-gate result, migration plan, rollback
path, and active-release verification — sourced per §8, never inferred.

---

## 11. Delivery State Machine

```
AUDIT → IMPLEMENTING → FOCUSED_TESTING → BROWSER_ACCEPTANCE →
REGRESSION_REVIEW → READY_FOR_FINAL_REVIEW → READY_TO_COMMIT →
COMMITTED → PUSHED → DEPLOYMENT_APPROVED → DEPLOYED →
PRODUCTION_VALIDATED → CLOSED
                              (or BLOCKED, from any state)
```

None of these transitions may be skipped or inferred:

- `IMPLEMENTING` cannot jump to `COMMITTED` without the gates in §10.
- `READY_FOR_FINAL_REVIEW` cannot imply `READY_TO_COMMIT` unless the
  mandatory gates for the change (§2) have actually passed.
- `COMMITTED` cannot imply `PUSHED` — push is a separate, explicit gate
  (§9.2).
- `PUSHED` cannot imply `DEPLOYMENT_APPROVED` — deployment authorization is
  separate and explicit (§9.6).
- `DEPLOYMENT_APPROVED` cannot imply `DEPLOYED` — approval to deploy is not
  the deployment itself; the deployment action and its evidence are
  separate.
- `DEPLOYED` cannot imply `PRODUCTION_VALIDATED` — see §7.
- `DEPLOYED` cannot jump to `CLOSED` without `PRODUCTION_VALIDATED` when §2
  marks L7 required for that change type.

Every substantial task report states its current state.

---

## 12. Final Report Contract

Every substantial task should report, at minimum:

```
Scope:
Files changed:
Architecture decisions:
Focused tests (L1/L2):        <e.g. 14 passed / 0 failed / 0 skipped>
Browser tests (L3):           <e.g. 6/6 PASS, or NOT RUN — reason>
Regression review (L4/L5):    <counts + classification per §6>
Known baseline failures:      <reference KNOWN-TEST-FAILURES.md; do not re-copy counts here>
New regressions:
DB mutation:
Dependency/lockfile changes:
Commit:
Push:
Deploy:
Remaining debt:
Delivery state (§11):
Final verdict:
```

Evidence must be exact, not vague:

- Good: `14 passed / 0 failed / 0 skipped`. Bad: "tests look good."
- Good: `foreign GET/PATCH/DELETE = 404`. Bad: "security seems fine."
- Good: `1366 / 768 / 390 PASS`. Bad: "responsive looks okay."

---

## 13. Known Baseline Failure Registry

- **customer-site**: `docs/KNOWN-TEST-FAILURES.md` — canonical, current, and
  actively maintained. Read it directly; do not copy its counts into this
  document (they will drift).
- **saas-platform**: no equivalent registry exists yet. Do not assume a
  clean baseline for saas-platform's suite. The first task that needs to
  classify a saas-platform failure as `PRE_EXISTING_FAILURE` must create
  `docs/KNOWN-TEST-FAILURES.md` here, following the customer-site file's
  baseline-verification method (§6), rather than asserting a baseline from
  memory.

The specific counts mentioned during the 2026-09-11 governance task
(MEDIA-3/MEDIA-4 focused-browser results, a historical "603 tests / 2007
assertions / 8 failures" broad sweep) are **not** registered here — they
were not re-verified against a baseline commit in that task, and this
document follows its own rule in §6: no baseline entry without proof.

---

## 14. Startup Rule

Before implementing any substantial task, per the Bootstrap Sequence in
`AGENTS.md`: read this document, classify the task via §2, identify the
mandatory gates, execute them before final review, and report against §12.
This is now wired into `AGENTS.md` directly (see the accompanying edit) so
it does not depend on a task remembering to ask.

---

## 15. Mature-Reference Product Standard

Adopted 2026-09-13, from the Full Product Commercial Audit (TASK 1/3),
after a domain-by-domain audit repeatedly needed the same judgment call:
whether a gap should be closed by inventing something new or by recognizing
that a mature commercial CMS/builder already solved this exact problem.

**The rule:** for any customer-facing workflow gap — a missing control, a
missing authoring surface, an unclear UX decision — evaluate how mature
commercial systems solve the same problem before designing a solution.
Primary references: **WordPress** (core authoring/admin patterns),
**WooCommerce** (commerce workflows: cart, licensing/extensions, order
lifecycle), **Elementor** (visual builder authoring patterns: repeaters,
widgets, template kits). Other mature commercial CMS/builders may inform a
specific gap when more directly relevant.

This reference requirement is **mandatory** for any audit or design task
that identifies a real product-experience gap. It is satisfied by recording,
per gap: `MATURE_CMS_REFERENCE` (the specific mature pattern),
`GOOD_PATTERN_TO_ADOPT` (what to actually take from it),
`KNOWN_CMS_PROBLEM_TO_AVOID` (a specific, real weakness of that mature
system — not a strawman), and `WHAT_OUR_ARCHITECTURE_CAN_DO_BETTER` (why
this codebase's existing canonical mechanism — e.g. `CustomerMediaService`'s
UUID+usage-ledger identity vs. WordPress's implicit attachment-post model —
already exceeds or can straightforwardly exceed the mature reference,
without adopting its legacy debt).

**Never copy:**
- WordPress/WooCommerce/Elementor's **legacy debt** — e.g. WordPress's
  historical page-vs-template slug ambiguity, or a plugin ecosystem's
  fragmented parallel data models for the same feature (found in this
  audit's own AUD-11 commercial-payment comparison).
- Their **implementation**, only the **pattern**. A goal like "give an
  author a searchable existing-page picker" is a pattern; WordPress's
  specific AJAX endpoint and markup are not something this codebase should
  port.
- **Duplicate authorities.** A canonical single-write-path/single-renderer
  discipline (per Constitution §2 "reuse canonical services... do not
  create parallel implementations") outranks any mature-CMS pattern that
  achieves the same UX through multiple competing code paths.
- **Fake or basic exposed features.** A control copied from a mature system
  "for parity" that has no real behavioral effect here fails the existing
  AUTHORING → SAVE → RELOAD → PREVIEW → PUBLIC RENDER contract (§4) exactly
  as much as a homegrown fake control would.
- **Persistence-only acceptance.** Matching a mature system's UI surface
  is not itself acceptance criteria — the existing Feature Acceptance
  Matrix (§2) and Builder Delivery Contract (§4) still govern what "done"
  means, unchanged by this rule.
- **Unnecessary backward compatibility.** Do not preserve a mature system's
  workaround for constraints (old PHP versions, no client framework, a
  15-year plugin ecosystem) that do not apply here.
- **User-driven trial-and-error QA.** A mature-CMS-informed design still
  requires the automated behavioral proof this document already mandates
  (§3–5) — "it works like WordPress" is not a substitute for a real
  save→reload→render assertion, and routine manual QA remains explicitly
  disallowed as the default delivery path (§5).
- **Their performance debt.** The UX pattern may be adopted; the runtime
  cost that a hook-heavy plugin architecture, generic postmeta-style
  storage, or uncontrolled dynamic module discovery imposes must not be —
  those are exactly the mechanisms a mature-CMS-informed feature must
  reimplement through this codebase's own typed/canonical mechanisms
  instead. Concretely: no per-item (e.g. per-tab, per-row) database query
  introduced by an authoring feature's public render path, no duplicated
  render pass over the same content, no duplicated persisted content tree
  for data that already has one canonical authority, and no loading of
  unrelated modules on every request merely to support one feature. Public
  rendering complexity must not grow with authoring complexity — it renders
  from the already-resolved canonical document/tree.

**Pre-customer convergence context:** while `ACTIVE_REAL_CUSTOMERS = 0`
remains true for this product, any domain found to have more than one
implementation of the same responsibility should be evaluated for
convergence now, before real customer data and integrations make
consolidation costly. Classify each such parallel path CANONICAL /
REQUIRED_BACKEND_ONLY / TEMPORARILY_REQUIRED / OBSOLETE, per the Full
Product Commercial Audit's Issue Ledger — a path being merely "the older
one" or "for compatibility" is not sufficient justification on its own to
classify it as TEMPORARILY_REQUIRED rather than OBSOLETE; the audit must
show what still depends on it.
