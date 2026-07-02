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
9. Apply the capability profile declared by the active agent wrapper. The wrapper is bootstrap metadata and must not be recursively reloaded.
10. Build an internal task model, then execute the current task under the authority hierarchy below.

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
- Historical governance audits and migration reports are evidence only; they do not override active governance.
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

A task is complete when its requested implementation or audit is finished, relevant validation passes, regression impact is reviewed, and canonical documentation is updated without duplication.
