# AI Editing History

Status: **Architecture only.**

## Audit objective

Every AI-assisted change must be reconstructable without exposing private authoring data to tenants.

An append-only history entry records:

- adaptation request identity;
- Platform Admin actor identity;
- customer/site boundary;
- source asset identity, source version, and checksums;
- sanitized logo identity and checksum;
- normalized theme palette before and after review;
- protected prompt identity and prompt revision;
- AI provider and model identifier;
- provider request/response correlation identifier when safe;
- generation date;
- proposal checksum;
- validator profile and result;
- preview checksum;
- output artifact checksums;
- approval or rejection actor and timestamp;
- reason and change summary; and
- resulting variant/version identity.

## Prompt privacy

`AI_prompt`, raw provider input, raw output, logo analysis, and chain-of-thought-like provider material are private authoring records. They must never be included in:

- customer runtime APIs;
- public manifests;
- downloadable customer packages;
- browser logs;
- tenant-visible errors; or
- analytics payloads.

Runtime packages may expose a non-sensitive change label and integrity data only.

## History events

Recommended normalized event names:

```text
brand.logo.uploaded
brand.palette.proposed
brand.adaptation.requested
brand.ai.proposal.received
brand.proposal.validation_failed
brand.variant.draft_created
brand.variant.approved
brand.variant.rejected
brand.variant.published
brand.variant.restored
brand.variant.cloned
brand.package.generated
```

## Provider failure

A provider timeout, refusal, malformed response, policy error, or validation failure leaves the published variant unchanged. The failure is recorded, raw sensitive payloads follow retention policy, and retry creates a new attempt rather than altering an earlier record.

