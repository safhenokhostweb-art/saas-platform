# Governance V2 Report

**Phase:** PHASE-008 — Enterprise Governance V2
**Date:** 2026-07-02
**Scope:** `saas-platform`, workspace entrypoints, and `customer-site` governance entrypoints only

## Audit Result

The previous architecture was vendor-neutral at its root but still mixed permanent project principles with agent orchestration in `AGENTS.md`. `CLINE.md`, `CONTINUE.md`, and `DEEPSEEK.md` were absent. The workspace root and `customer-site` retained duplicated legacy Cline/Continue rules, including obsolete and conflicting framework guidance. Compatibility policy files were pointers, ADR-017 remained canonical, and project memory remained in its established directory.

## New Architecture

- `PROJECT_CONSTITUTION.md` owns permanent project principles and contains no agent instructions.
- `AGENTS.md` owns bootstrap sequencing, context loading, task orchestration, capability declarations, wrapper contracts, and recovery behavior.
- Canonical agent wrappers contain only identity, capability profile selection, entry instruction, and an `AGENTS.md` reference.
- Workspace and `customer-site` bridges delegate to the canonical files without copying governance.
- Project memory, ADRs, historical audits, and compatibility paths retain their existing roles and locations.

## Governance Hierarchy

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

The runtime bootstrap direction is wrapper → `AGENTS.md` → Constitution first → project context. The Constitution does not load lower layers, and wrappers are not recursively loaded, so the process has no load cycle.

## Capability Matrix Summary

`AGENTS.md` defines one Standard Coding Agent Capability Profile for Claude Code, Codex, Cline, Continue, and DeepSeek. It declaratively covers Filesystem, Terminal, Git, Commit, Branch, Merge, SSH, Deploy, Docker, Browser, Memory, Planning, Patch, Search, Multi-file Refactor, Tool Calling, Code Review, and Autonomous Execution. Availability is host-declared; repository memory and ADR-017 remain the canonical continuity and autonomy controls.

## Files Created

### Canonical repository

- `PROJECT_CONSTITUTION.md`
- `CLINE.md`
- `CONTINUE.md`
- `DEEPSEEK.md`
- `docs/GOVERNANCE_V2_REPORT.md`

### Workspace coverage

- `C:\laragon\www\AGENTS.md`
- `C:\laragon\www\customer-site\AGENTS.md`

## Files Modified

### Canonical authority and wrappers

- `AGENTS.md`, `CLAUDE.md`, `CODEX.md`
- `.clinerules`, `.deepseek-prompt.md`
- `.continue/agent_rules.md`, `.continue/rules.md`, `.continue/rules/saas-strict-rules.md`
- `.claude/settings.json` retains its blocking behavior and points to `AGENTS.md`

### Compatibility, ADR, memory, and indexes

- `docs/AI_GOVERNANCE_POLICY.md`, `docs/AI_DECISION_POLICY.md`
- `docs/project-memory/RECOVERY_PROTOCOL.md`, `docs/project-memory/HANDOVER.md`
- `docs/project-memory/PROJECT_STATE.md`, `docs/project-memory/CHANGELOG_AI.md`
- `docs/project-memory/DECISIONS.md` (ADR-017 preserved; ADR-018 added)
- `docs/AI_CONTEXT/PROJECT_MEMORY_INDEX.md`, `docs/AI_CONTEXT/DOCUMENT_INVENTORY.md`
- `docs/AGENT_GOVERNANCE_MIGRATION_REPORT.md` marked as a historical Phase 8 report

### Workspace and customer-site legacy entrypoints

- `C:\laragon\www\.clinerules`
- `C:\laragon\www\customer-site\.clinerules`
- `C:\laragon\www\customer-site\.continue\agent_rules.md`
- `C:\laragon\www\customer-site\.continue\rules.md`

## Validation Result

| Check | Result | Evidence |
|---|---|---|
| No duplicated active governance | PASS | Legacy rule bodies removed; canonical ownership table established |
| No duplicated ADR | PASS | Exactly one `## ADR-017` canonical heading; ADR-018 exists only in `DECISIONS.md` |
| No duplicated project memory | PASS | Governance files link to canonical memory files without copying state |
| No circular loading | PASS | Wrappers are bootstrap-only and are not recursively loaded |
| No broken governance paths | PASS | Canonical, workspace, customer-site, compatibility, and memory paths resolved |
| No orphan wrappers | PASS | Every canonical and legacy wrapper resolves to an `AGENTS.md` entrypoint |
| No agent-specific project principles | PASS | Constitution scan found no agent or vendor names |
| No coding-agent vendor dependency | PASS | Permanent authority is independent; capability availability is host-declared |
| JSON compatibility | PASS | Claude and Continue configuration files parse successfully |
| Formatting integrity | PASS | `git diff --check` passed in both repositories |

Historical audit reports may mention superseded Claude-era governance as historical evidence; they are not active authorities and were not rewritten.

## Backward Compatibility

PASS. ADR-017 remains active, its decision text and permitted exceptions remain unchanged, and only its impact references were updated. Existing `CLAUDE.md`, `CODEX.md`, hidden Cline/DeepSeek/Continue paths, policy document paths, project-memory loading, and development workflows remain available. No application code, business logic, installation pipeline, deployment pipeline, schema, or runtime configuration was changed by this phase.

## Future Extensibility

PASS. A new coding agent requires one wrapper containing bootstrap identity, the Standard Coding Agent Capability Profile declaration, an entry instruction, and a reference to `AGENTS.md`. Gemini, Qwen, Copilot, future OpenAI agents, or any other tool can be added without modifying the Constitution, orchestration, ADRs, memory, or governance reports.
