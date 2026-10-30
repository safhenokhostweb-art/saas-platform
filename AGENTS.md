# Agent Orchestration Protocol

## Purpose

This file is the vendor-neutral orchestration layer for coding agents operating in the SaaS Platform workspace. Permanent project principles belong only in `PROJECT_CONSTITUTION.md`; this file must not duplicate them.

## Bootstrap Sequence

For every task, process context in this order:

1. Load `PROJECT_CONSTITUTION.md` before any other project governance document.
2. Identify the workspace scope: `saas-platform`, `customer-site`, or both.
3. Load the relevant project memory entrypoint:
   - General or cross-workspace task: `docs/project-memory/HANDOVER.md`.
   - Education task: `docs/project-memory/HANDOVER_EDU.md`.
   - Subscription task: `docs/project-memory/HANDOVER_SUBSCRIPTION.md`.
4. Load architectural decisions from `docs/project-memory/DECISIONS.md`, including ADR-017 for agent execution governance.
5. Load current phase from `docs/project-memory/PROJECT_STATE.md`.
6. Load open work from `docs/project-memory/OPEN_TASKS.md`.
7. Load architecture from `docs/project-memory/ARCHITECTURE.md` and any task-specific architecture document.
8. Load relevant known issues and audit history from `docs/project-memory/KNOWN_ISSUES.md` and `docs/project-memory/AUDIT_HISTORY.md`.
9. For any task that implements, changes, or ships code, load `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md`, classify the task against its Feature Acceptance Matrix, and carry the resulting mandatory gates into the task model.
10. For any task that ships, releases, or delivers a work unit, load `docs/quality/WORK_UNIT_DELIVERY_WORKFLOW.md` and carry its mandatory delivery cycle (validate → commit → push → artifact → deliver → confirm receipt → report) into the task model alongside those gates.
11. Apply the capability profile declared by the active agent wrapper. The wrapper is bootstrap metadata and must not be recursively reloaded.
12. Build an internal task model, then execute the current task under the authority hierarchy below.

Repository evidence takes precedence over chat history. Missing optional context must not be invented.

## Authority Hierarchy

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

- `PROJECT_CONSTITUTION.md` owns permanent project principles.
- `AGENTS.md` owns agent orchestration, loading, capability declarations, and execution workflow.
- Agent wrappers own only bootstrap identity, capability profile selection, and entry instructions.
- Project memory owns current state, history, open work, known issues, and ADRs.
- The current task provides scoped intent and acceptance criteria.

## Governance Loading

- ADR-017 remains the canonical execution-governance decision and must be applied from `docs/project-memory/DECISIONS.md` without restating it here.
- `docs/AI_GOVERNANCE_POLICY.md` and `docs/AI_DECISION_POLICY.md` are legacy compatibility paths, not independent authorities.
- Historical governance audits and migration reports are evidence only; they do not override active governance. `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md` is the one live exception this rule does not cover — it is current, not historical.
- Conflicts are resolved by the authority hierarchy, then by the newest non-superseded ADR within its valid scope.

## Task Orchestration

Before modifying files:

- Inspect repository state and preserve unrelated user changes.
- Identify affected repositories, modules, contracts, migrations, policies, tests, and tenant boundaries.
- State the intended impact before implementation.

During implementation:

- Follow the constitution and applicable ADRs.
- Prefer established repository patterns and the smallest complete change.
- Use repository evidence; do not invent models, fields, services, configuration, or infrastructure.
- Keep external state changes within the explicit task scope.

After implementation:

- Validate the narrowest relevant behavior first, then broader checks proportional to risk.
- Review regression impact across both repositories when shared contracts are affected.
- Update project memory only where state, tasks, decisions, issues, audits, or handover context actually changed.
- Commit and push all task-owned changes, synchronize them to every affected production server, and verify health before marking the task complete.
- For documentation-only work, synchronize files without unnecessary runtime restarts or application deployment actions.

## Release Delivery to the Deployment Hub

The cycle itself is owned by `docs/quality/WORK_UNIT_DELIVERY_WORKFLOW.md`; these are the operating facts agents must apply when running it:

- Each project has its own Hub endpoint, set as `DEPLOYMENT_RELEASE_METADATA_URL` in the release shell only:
  - `saas-platform`: `https://deployment.safhenokhost.ir/api/projects/saas-platform/releases`
  - `customer-site`: `https://deployment.safhenokhost.ir/api/projects/customer-site/releases`
- `DEPLOYMENT_RELEASE_SECRET` must equal that project's `github_webhook_secret` as stored by the Hub (`ProjectReleaseController` verifies `X-Deployment-Signature-256` against it and answers `401` on mismatch, `202` on acceptance). The local deployment-system database does not hold these projects and is never a source for the secret; obtain it from the owner. Never commit, print, or log it, and never rotate it to unblock a release: the same value authenticates the project's GitHub webhook.
- `release-prepare prepare` commits and pushes the release commit to `main` before it delivers metadata. Run it only after `check` is `READY` and both variables are confirmed in the shell; otherwise stop at `check` and record P6 as `BLOCKED`.
- The local role ends at `check`, `prepare`, the release manifest, and signed metadata delivery. The Hub builds the artifact on the server from the exact pushed commit; agents never hand-build an archive.
- Release order is `saas-platform` first, then `customer-site`: customer-site resolves `saas-platform/packages/**` through Composer path repositories, so its `check` requires the sibling `saas-platform` checkout on a `main` that already contains the shared package code it calls.
- Sentinel Verifier is a post-deploy auditor only. It runs automatically on `deployment.completed` after the Hub deploys an owner-approved release; agents must not fabricate or pre-generate Sentinel evidence before deployment.

## Documentation Ownership

| Information | Canonical location |
|---|---|
| Permanent project principles | `PROJECT_CONSTITUTION.md` |
| Agent orchestration and capability model | `AGENTS.md` |
| Architectural decisions | `docs/project-memory/DECISIONS.md` |
| Current phase and status | `docs/project-memory/PROJECT_STATE.md` |
| Open tasks | `docs/project-memory/OPEN_TASKS.md` |
| Current architecture | `docs/project-memory/ARCHITECTURE.md` |
| Known issues | `docs/project-memory/KNOWN_ISSUES.md` |
| Audit findings | `docs/project-memory/AUDIT_HISTORY.md` |
| Session handover | `docs/project-memory/HANDOVER.md` |
| Testing and delivery governance (both repositories) | `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md` |
| Standard work-unit delivery cycle and artifact handoff to deployment-system | `docs/quality/WORK_UNIT_DELIVERY_WORKFLOW.md` |
| Agent bootstrap identity | Active wrapper only |

Do not copy content between these authorities. Link to the canonical location.

## Standard Coding Agent Capability Profile

Capability declarations describe the governance envelope, not guaranteed product features.

Legend:

- `H`: available only when the host runtime exposes it.
- `T`: performed through an available terminal capability.
- `R`: repository-backed capability.
- `G`: governed by ADR-017 and the current task scope.
- `O`: optional integration; never assumed.

### Workspace and Delivery Capabilities

| Agent | Filesystem | Terminal | Git | Commit | Branch | Merge |
|---|---:|---:|---:|---:|---:|---:|
| Claude Code | H | H | T | T | T | T |
| Codex | H | H | T | T | T | T |
| Cline | H | H | T | T | T | T |
| Continue | H | H | T | T | T | T |
| DeepSeek | H | H | T | T | T | T |

### Infrastructure Capabilities

| Agent | SSH | Deploy | Docker | Browser | Memory | Planning |
|---|---:|---:|---:|---:|---:|---:|
| Claude Code | T | T | T | O | R | H |
| Codex | T | T | T | O | R | H |
| Cline | T | T | T | O | R | H |
| Continue | T | T | T | O | R | H |
| DeepSeek | T | T | T | O | R | H |

### Engineering Capabilities

| Agent | Patch | Search | Multi-file Refactor | Tool Calling | Code Review | Autonomous Execution |
|---|---:|---:|---:|---:|---:|---:|
| Claude Code | H | H | H | H | H | G |
| Codex | H | H | H | H | H | G |
| Cline | H | H | H | H | H | G |
| Continue | H | H | H | H | H | G |
| DeepSeek | H | H | H | H | H | G |

Unavailable host capabilities must be reported; they must never be simulated or claimed.

## Wrapper Contract

Every supported wrapper contains only:

1. Agent bootstrap identity.
2. Capability profile declaration.
3. Entry instruction.
4. Reference to `AGENTS.md`.

The standard wrappers are `CLAUDE.md`, `CODEX.md`, `CLINE.md`, `CONTINUE.md`, and `DEEPSEEK.md`. Tool-required legacy entrypoints may remain as thin wrappers with the same contract.

To add any future coding agent, create one wrapper that selects the Standard Coding Agent Capability Profile and points to `AGENTS.md`. No constitution, orchestration, ADR, memory, or governance document changes are required.

## Recovery and Continuation

When context is reset, restart the Bootstrap Sequence. Project memory is the continuity mechanism; conversational history is not an authority.

## Completion Standard

A task is complete when its requested implementation or audit is finished, relevant validation passes, regression impact is reviewed, and canonical documentation is updated without duplication. For any task that implements or changes code, "relevant validation passes" means the gates identified from `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md` §2 were executed and reported per its §12 template, and commit/push/deploy actions followed its §9 Authorization Policy. For a task that delivers a work unit, completion additionally means the cycle in `docs/quality/WORK_UNIT_DELIVERY_WORKFLOW.md` ran through receipt confirmation and its report was produced; owner review and any deployment decision remain outside the agent's completion.
