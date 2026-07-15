# AI Asset Editing Provider Contract

Status: **Conceptual, provider-neutral, and not connected to any provider.**

## Boundary

A future adapter for OpenAI, Claude, Gemini, or another provider must translate between its SDK and these normalized envelopes. Provider-specific request fields, response classes, model behavior, credentials, and retry mechanisms must not leak into the domain workflow.

Machine-readable contracts:

- `schemas/AI_PROVIDER_REQUEST.schema.json`
- `schemas/AI_PROVIDER_PROPOSAL.schema.json`

## Input contract

The required business shape is:

```json
{
  "asset_id": "icon:business:company:v1",
  "asset_version": "v1",
  "asset_files": [],
  "metadata": {},
  "brand_theme": {},
  "logo": {},
  "instruction": "Adapt this asset to the approved brand theme.",
  "prompt": "Bounded normalized prompt"
}
```

The normalized contract additionally carries `request_id`, opaque `customer_scope`, editable constraints, and integrity checksums. `asset_files` contains only sanitized, minimum-required working representations. It must not contain an internal filesystem path, unrestricted library archive, private metadata, credential, or unrelated asset. `logo` is a sanitized private descriptor or bounded encoded representation, never a public URL.

The five Brand Theme values are `primary`, `secondary`, `accent`, `background`, and `border`, corresponding to the canonical `--brand-*` tokens in `../brand-adaptation/THEME_TOKEN_CONTRACT.md`.

## Output contract

```json
{
  "status": "generated",
  "proposal_id": "proposal_opaque_id",
  "preview": {},
  "generated_files": [],
  "metadata_changes": [],
  "ai_metadata": {}
}
```

The response is always an untrusted proposal. `generated_files` are proposed bytes/descriptors, not published assets. `metadata_changes` is a bounded list of requested changes, not permission to alter ownership, license, source identity, or governance fields. `ai_metadata` contains safe provenance such as provider, model, request correlation, timing, warnings, and token/cost measurements when allowed; it must not contain secrets or hidden reasoning.

## Contract operations

A future language-specific interface should conceptually expose:

```text
providerKey() -> stable provider identifier
capabilities() -> supported asset classes and bounded features
propose(normalized request) -> normalized proposal
```

It must not expose `approve`, `publish`, `overwriteMaster`, or tenant-facing methods.

## Adapter obligations

- authenticate only with server-held credentials;
- minimize and sanitize provider input;
- enforce request size, timeout, retry, rate, and cost limits;
- normalize all output through the proposal schema;
- preserve request/proposal correlation IDs;
- never trust provider-declared MIME types or checksums;
- never infer approval from provider confidence;
- never log raw logos, raw prompts, source content, or secrets by default; and
- return controlled provider errors without leaking SDK internals.

## Fake adapter

The fake adapter in `examples/` is documentation data only. It deterministically maps the approved Brand Theme tokens onto editable color tokens, returns one proposal, and performs no network call. It must be the only provider used by future architecture tests until real adapters are separately authorized.
