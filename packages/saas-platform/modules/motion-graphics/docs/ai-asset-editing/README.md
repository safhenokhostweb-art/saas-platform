# AI Asset Editing Architecture

Status: **Architecture preparation only — no runtime implementation.**

This documentation defines a provider-neutral, Platform Admin-only workflow for deriving customer/site asset variants from immutable assets in the Motion Graphics Master Library. It covers motion graphics, icons, objects, and visual assets.

The architecture extends, and does not replace, the existing governance in:

- `../ai/` for module AI ownership rules;
- `../library/` for library identity and asset rules; and
- `../brand-adaptation/` for inheritance, customer ownership, theme tokens, and customer packages.

## Non-negotiable invariant

```text
Immutable Master Asset
        |
        | read-only sanitized working snapshot
        v
AI Editing Session -> AI Proposal -> validation -> Platform Admin decision
                                            |
                                            | approved
                                            v
                              New immutable Variant Version
                                            |
                                            v
                                separate future publication
```

AI never edits a master, approves its own result, or publishes an asset. Approval creates a new private variant version; it does not make that version available to customer runtime.

## Contents

- `AI_EDITING_ARCHITECTURE.md`: boundaries, components, invariants, and decisions.
- `AI_PROVIDER_CONTRACT.md`: normalized request/proposal contract and fake-adapter behavior.
- `EDITING_SESSION_FLOW.md`: session states and transition rules.
- `PROPOSAL_APPROVAL_FLOW.md`: validation, comparison, approval, and rejection.
- `VARIANT_GENERATION_RULES.md`: lineage, paths, ownership, and version creation.
- `PROMPT_HISTORY_RULES.md`: append-only `AI_EDIT_HISTORY.json` standard.
- `AI_SECURITY_RULES.md`: privacy, tenant isolation, least-data, and output safety.
- `ARCHITECTURE_TEST_SCENARIOS.md`: implementation-neutral acceptance scenarios.
- `FUTURE_PLATFORM_INTEGRATION.md`: explicitly deferred implementation work.
- `schemas/`: machine-readable architecture contracts.
- `templates/`: private authoring record templates.
- `examples/`: non-executable fake-provider examples.
- `AI_EDITING_INDEX.json`: machine-readable entrypoint.

## Explicit non-goals

This package does not add Laravel classes, database records, routes, APIs, queues, UI, storage, provider SDKs, customer-site integration, deployment behavior, or real AI calls. It does not modify any Master Asset.
