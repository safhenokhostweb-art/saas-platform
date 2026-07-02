# Agent Governance Migration Report

> Historical Phase 8 migration record. The current architecture is documented in `docs/GOVERNANCE_V2_REPORT.md`.

## Previous structure

Governance was rooted in `CLAUDE.md` and duplicated across Claude settings, Cline, DeepSeek, Continue, AI policy documents, recovery documentation, and project-memory summaries.

## New structure

`AGENTS.md` is the single agent-agnostic source of truth. Agent-specific and legacy entrypoints are compatibility wrappers that only direct agents to `AGENTS.md`. ADR-017 remains solely in `docs/project-memory/DECISIONS.md`, and project memory remains under `docs/project-memory/`.

## Compatibility status

Backward compatibility is preserved for Claude Code, Codex, Cline, DeepSeek, Continue, and existing documentation links. No application code, runtime configuration, database schema, or product behavior changed.

## Files changed

- Root authority and wrappers: `AGENTS.md`, `CLAUDE.md`, `CODEX.md`, `.clinerules`, `.deepseek-prompt.md`.
- Continue wrappers: `.continue/agent_rules.md`, `.continue/rules.md`, `.continue/rules/saas-strict-rules.md`.
- Claude compatibility setting: `.claude/settings.json` (blocking behavior preserved; message now references `AGENTS.md`).
- Compatibility documentation: `docs/AI_GOVERNANCE_POLICY.md`, `docs/AI_DECISION_POLICY.md`, `docs/project-memory/RECOVERY_PROTOCOL.md`.
- Existing records and indexes: `docs/project-memory/DECISIONS.md`, `docs/project-memory/HANDOVER.md`, `docs/AI_CONTEXT/PROJECT_MEMORY_INDEX.md`, `docs/AI_CONTEXT/DOCUMENT_INVENTORY.md`.
- Migration report: `docs/AGENT_GOVERNANCE_MIGRATION_REPORT.md`.
