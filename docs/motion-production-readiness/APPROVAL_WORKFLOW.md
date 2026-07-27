# Approval Workflow

## Workflow

```text
Creator -> Reviewer -> Platform Admin -> Published Asset
```

1. Creator creates a draft or accepts a validated AI proposal as a new draft.
2. Automated production checks run and freeze the review checksum.
3. Reviewer assesses visual quality, accessibility, license, performance, responsive behavior, and lineage; the reviewer cannot publish.
4. Platform Admin approves the exact reviewed checksum with a reason.
5. A distinct publish action repeats integrity checks and advances the published pointer atomically.

## Non-delegable Platform Admin actions

Only the main Platform Admin may approve, publish, rollback/restore, or change ownership. Permission is checked at the request boundary, service boundary, asynchronous job boundary, and immediately before the state change.

AI cannot approve itself, publish, alter ownership or licenses, mutate a Master Asset, widen editable properties, or bypass a failed validation. AI output is hostile proposal data until sanitized, validated, reviewed, and approved.

## Approval evidence

An approval record contains asset/version ID, artifact checksum, review checksum, actor ID and role, decision, reason, timestamp, validation report ID, and license snapshot. Approval expires when content, metadata, lineage, license, or validation profile changes.

Creator and reviewer separation should be enforced for premium/restricted assets. Regardless of role combination, an explicit Platform Admin approval and a separate publish command are mandatory.

## Failure behavior

Missing approval, stale checksum, invalid lifecycle transition, unauthorized actor, failed validation, or license conflict leaves the published pointer unchanged and appends a safe audit event.

