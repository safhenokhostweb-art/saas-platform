# Work-Unit Delivery Workflow

## Status and Authority

Adopted 2026-10-20 by standing owner directive (the workflow-preparation mission
of that date). This document binds the existing canonical mechanisms into one
mandatory delivery cycle per work unit. It is subordinate to
`PROJECT_CONSTITUTION.md`, `AGENTS.md`, the ADRs in
`docs/project-memory/DECISIONS.md`, and `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md`.
It creates no parallel mechanism and re-states nothing those documents own:

| Owned by | Do not duplicate here |
|---|---|
| `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md` | Test layers L0–L7, Feature Acceptance Matrix (§2), authorization policy (§9), commit/push gates (§10), delivery state machine (§11), evidence standards (§12) |
| `.deployment/README.md` + `.deployment/release-policy.json` | Release contract, allowed/excluded paths, migration policy, required release checks, fixtures prerequisites |
| `../deployment-system/` repository | Artifact construction, integrity verification, approval, activation, health checks — all server-owned |

Scope: `saas-platform` (canonical). `customer-site` releases follow the same
cycle through their own registry (`customer-site/.deployment/README.md`); a
cross-repo release records both source commits in its release metadata.

## The Mandatory Cycle

A **work unit** is an independently validatable change set. Every work unit
executed under this workflow moves through these phases in order. Each phase's
result is recorded with real evidence; no phase is skipped or inferred from a
later phase.

```
P1 EXECUTE → P2 VALIDATE → P3 COMMIT → P4 PUSH →
P5 ARTIFACT → P6 DELIVER → P7 CONFIRM RECEIPT → P8 REPORT → owner review
```

### P1 — Execute

- Define the work unit's objective and exact change scope before editing.
- Inspect the related code; make the smallest complete change.
- No unrelated fixes, refactors, or formatting churn inside a work unit —
  they become `unowned_files` and fail the release gate closed (P5).

### P2 — Validate

- Classify the change against the governance Feature Acceptance Matrix (§2)
  and run the resulting mandatory gates (L0 first, then upward proportional
  to risk).
- Record the real result of every check: exact counts, commands, exit codes.
  A check that could not run is `BLOCKED` with its blocker named — never
  reported as a pass.
- A failed or blocked mandatory gate means the unit is **not release-ready**;
  do not proceed to P3 for that unit.

### P3 — Commit

- Satisfy the governance commit gate (§10) first: L0 clean, required tests
  run and passing, regressions classified per §6, no unrelated WIP staged.
- Stage only the work unit's own paths. Create one meaningful commit. Record
  the exact SHA.

### P4 — Push

- Identify the correct remote and branch from the repository, verify the
  remote state, push, and record the real push result. No force push, rebase,
  or history rewrite (§9.7).
- The pushed branch is the reviewable provenance of the work unit. Do **not**
  commit or merge the work unit onto `main` here: `release-prepare` creates
  the single `main` release commit in P5 and fails closed on any pre-existing
  unpushed commit on `main` (`UNPUSHED COMMITS - RELEASE NOT SAFE`).

### P5 — Artifact

Run from an isolated `main` worktree with development dependencies installed,
the test database configured, and the required test fixtures present (see
`.deployment/README.md` for the FoodMart baseline fixture requirement):

1. Record the production baseline: `git rev-parse origin/main` → `base_sha`.
   The worktree HEAD must equal that remote `main` HEAD — the contract fails
   closed on unpushed local commits, on divergence, and on pre-staged (index)
   changes.
2. Bring the work unit's changes into that worktree as **uncommitted,
   unstaged modifications limited to the task's owned paths** — e.g.
   `git diff <base_sha>..<work-unit-commit> -- <owned paths> | git apply`
   (worktree-only: `git checkout <commit> -- <path>` stages the index and
   trips `PRE-STAGED CHANGES`). Record the source commit(s) as provenance
   (the `source_commits` pattern in `.deployment/releases/canvas-homepage.json`);
   they are provenance, not the release commit.
3. Create the untracked control-plane task file `.deployment/release-task.json`
   (gitignored) with `task_id`, `summary`, `base_sha`, and the exact
   `owned_paths` of this work unit
   (see `../deployment-system/.deployment/release-task.json.example`).
4. `php ../deployment-system/deployment/release-prepare.php check` — must
   exit `READY` with all required checks passing.
5. `php ../deployment-system/deployment/release-prepare.php prepare` —
   stages exactly the owned paths, creates the single
   `release(saas-platform): <task_id>` commit on `main`, writes
   `.git/deployment/releases/<commit-sha>.json` (outside the worktree; never
   commit it: a follow-up commit advances the production branch and the Hub
   records it as a newer Release that supersedes the real one), pushes it,
   and delivers the
   signed release metadata (P6).

The artifact itself is **server-managed-exact-commit**
(`.deployment/release-policy.json` → `build.artifact`): it is built,
checksummed, and evidenced by the deployment-system pipeline from that exact
pushed commit. The agent never hand-builds a competing archive. The release
metadata written by `prepare` carries the work-unit identity: `task_id`,
`project_key`, `base_sha`, `changed_files`, `migration_files`, test results,
and dependency commits.

### P6 — Deliver

Delivery to the deployment-system is what `prepare` performs at the end of P5:
the release commit is pushed to the production branch and the signed release
metadata is POSTed to the Hub (`DEPLOYMENT_RELEASE_METADATA_URL`, HMAC-signed
with `DEPLOYMENT_RELEASE_SECRET`, verified by the Hub's
`ProjectReleaseController`). Success of a local build or a git push is **not**
delivery; delivery is confirmed only from the Hub's own records (next phase).

### P7 — Confirm Receipt

Per governance §8 (Environment Authority), receipt and artifact identity are
confirmed only from the real deployment-system's own records — dashboard or
its database — never from local git state, the local working tree, or a local
copy of the deployment-system database. Record from the Hub: artifact id,
deployment id, commit SHA, checksum, and evidence digest. Reconcile
`project_key`, version, and commit identity. If the Hub only confirms file
receipt without content validation, state that limitation explicitly in the
report.

### P8 — Report

Produce the mandatory report (template below) and stop. The owner reviews the
artifact in the deployment-system and decides on deployment. Do not treat
owner approval of an artifact as deployment authorization.

## Authorization Model

The owner's standing directive adopting this workflow (2026-10-20) constitutes
the per-task explicit authorization that governance §9.3 requires: for a task
run under this workflow, passing the P2 mandatory gates authorizes P3 through
P7 for that work unit, without a duplicate confirmation round.

Never covered by that standing grant — each still requires separate explicit
owner approval, always:

- Deploying, activating, or rolling back a release on any server.
- Any production server configuration or production database change.
- Force push, rebase, reset, or history rewrite on any branch.
- Any action the current task explicitly restricts beyond this workflow.

Task-specific restrictions override the standing grant. ADR-019 remains in
force: an unavailable synchronization step keeps the work unit incomplete and
must be recorded as `BLOCKED`, not silently skipped.

## Status Vocabulary

Each phase result is one of, mapped onto the governance state machine (§11):

| Status | Meaning |
|---|---|
| `PASS` | Check executed and succeeded, with recorded evidence |
| `FAILED` | Check executed and did not pass |
| `BLOCKED` | Phase not executed or incomplete due to a named blocker |
| `READY_FOR_REVIEW` | All mandatory phases passed, artifact delivered and receipt confirmed — awaiting owner review |

## Error Handling

- Diagnose the real cause of a failure; apply the lowest-risk fix; do not
  retry an unchanged failing request.
- After three consecutive failures of one execution path on the same
  unresolved blocker, stop that path, preserve all completed phase results,
  and report exactly which phase work can resume from.
- A failed P5/P6 never invalidates completed P3/P4 work; resume from the
  failed phase after removing the blocker.

## Responsibility Boundary

| Agent owns | Owner owns |
|---|---|
| P1–P8 execution and evidence (execute, validate, commit, push, release-prepare, deliver, confirm receipt, report) | Reviewing the artifact in the deployment-system |
| Classifying failures per governance §6 | Approving or rejecting the artifact |
| Recording blockers truthfully | Deciding whether and when to deploy to any server |

## Mandatory Report Template

Every completed work unit reports, at minimum (this extends — not replaces —
the governance §12 contract):

```
Project:            saas-platform (project_key from .deployment/release-policy.json)
Work unit:          task_id from .deployment/release-task.json
Change summary:

Validation:         per mandatory gate: command, PASS/FAILED/BLOCKED, exact counts
Build:              release-prepare check status; artifact build is server-managed (P5)
Limitations:

Branch:
Commit SHA:
Remote / push result:

Artifact:           artifact id from Hub, version, commit SHA, checksum
Artifact location:  Hub record (deployment id) + .git/deployment/releases/<sha>.json
Integrity check:    Hub checksum/evidence digest vs release metadata

Deployment-system:  delivery result, destination artifact id, receipt-confirmation
                    evidence (from Hub records only, §8)

Final status:       READY_FOR_REVIEW | BLOCKED | FAILED
```

## Operational Prerequisites

Mechanism exists for every phase; operability of a phase depends on these
verified-at-use prerequisites, which must be confirmed (not assumed) at the
first real execution and re-confirmed whenever the environment changes:

1. PHP 8.4 CLI and git available to the release shell (verified 2026-10-20:
   Laragon PHP 8.4.16; `php scripts/release-compatibility.php` passes).
2. Isolated `main` release worktree with development dependencies, the
   configured test database, and the FoodMart integrity fixtures (P5).
3. Local PHPUnit gates runnable in that worktree (the dirty development tree
   is not a release environment — see RE-01 in `AI_CONTEXT/` for the current
   local-suite blocker recorded 2026-10-18).
4. `DEPLOYMENT_RELEASE_METADATA_URL` and `DEPLOYMENT_RELEASE_SECRET` set in
   the release environment, and the Hub reachable (P6).
5. Read access to the real deployment-system's records for receipt
   confirmation (P7).
