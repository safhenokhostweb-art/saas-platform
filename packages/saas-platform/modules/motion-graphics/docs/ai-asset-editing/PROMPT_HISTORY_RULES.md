# Prompt and AI Editing History Rules

Status: **Architecture only.**

## Standard

Each private editing lineage uses an `AI_EDIT_HISTORY.json` logical document conforming to `schemas/AI_EDIT_HISTORY.schema.json`. `templates/AI_EDIT_HISTORY.json` is the empty template and `examples/AI_EDIT_HISTORY.example.json` is fictional test data.

Each entry records at minimum:

```json
{
  "prompt": "Private normalized prompt",
  "provider": "fake",
  "model": "fake-asset-editor-v1",
  "input_assets": [],
  "changes": [],
  "result": "generated",
  "approval_status": "pending"
}
```

It also records immutable IDs, timestamps, actor/customer scope, source and proposal checksums, validation outcome, and decision evidence.

## Immutability

- History is append-only.
- Existing entries, prompts, attempts, proposals, and decisions are never edited or removed in place.
- A correction appends a superseding event that references the earlier entry.
- Retry/regeneration appends a new attempt with a new proposal ID.
- Approval/rejection appends a decision entry or completes a predeclared immutable event through an implementation mechanism that preserves the original event evidence; an audit log must never silently rewrite provider history.
- Hash chaining or signed event manifests should be considered by the future persistence design.

## Prompt contents

The prompt should contain only:

- the admin's bounded editing intent;
- approved asset identity and editable properties;
- sanitized design context and Brand Theme;
- structural, accessibility, security, file-size, and renderer constraints; and
- an explicit statement that output is a proposal.

Do not include customer secrets, personal data, credentials, internal paths, database content, unrelated assets, raw operational logs, or unrestricted source repositories.

## Privacy and retention

Prompts, raw provider request/response bodies, logo analysis, source snapshots, and private previews are private authoring data. A future implementation must encrypt sensitive content at rest, restrict access to Platform Admin workflows, define provider and local retention, and keep them out of tenant APIs, runtime packages, browser logs, analytics, and public error messages.

If legal deletion removes a raw logo or prompt under an approved policy, immutable history should retain only the minimum permitted opaque reference/checksum and a redaction event; the audit sequence must remain evident.

## Runtime exclusion

`AI_EDIT_HISTORY.json` is never part of `customer-assets/` delivery. Runtime manifests may include only a non-sensitive change label and artifact integrity data.
