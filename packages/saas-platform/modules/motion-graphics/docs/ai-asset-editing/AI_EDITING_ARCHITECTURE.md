# AI Asset Editing Architecture

Status: **Architecture only.** The components below are conceptual contracts, not implemented services.

## Objective

A main Platform Admin selects an exact immutable Master Asset version, supplies authorized customer branding and an editing instruction, requests a provider-neutral AI proposal, reviews the validated result, and may approve it as a new immutable customer/site Variant Version.

## Authority and ownership

| Concern | Authority | Rule |
|---|---|---|
| Master Asset | Platform | Immutable and never copied back over. |
| Editing session | Main Platform Admin | Tenants cannot create or inspect it. |
| Provider proposal | AI provider, untrusted | Proposal only; never authoritative. |
| Approval/rejection | Main Platform Admin | Explicit authenticated decision required. |
| Customer variant | Customer/site, platform-custodied | Platform Admin-only authoring; tenant is a consumer. |
| Publication | Future authorized platform workflow | Separate from approval and absent in this phase. |

`site_id` remains the future canonical tenant boundary. Architecture examples use `customer_id` where required by the business vocabulary; integration must resolve it to the final customer/site authority without weakening `site_id` isolation.

## Conceptual components

1. **Master Asset Resolver** resolves `asset_id` plus `asset_version` from `assets/library/library-index.json` and verifies platform ownership, active status, and variant support.
2. **Authorization Gate** proves the actor is a main Platform Admin before any authoring action.
3. **Input Sanitizer** validates the logo, strips active/private metadata, and produces a private checksum-addressed representation.
4. **Editing Session Coordinator** freezes source identity, actor, customer/site boundary, request, and provider choice.
5. **Prompt Builder** combines the admin instruction with allowlisted editable properties and structural/security constraints.
6. **AI Asset Editing Provider** receives the normalized least-data request and returns a normalized proposal.
7. **Proposal Validator** treats all provider output as untrusted and checks schema, structure, accessibility, safety, size, theme, and renderer compatibility.
8. **Comparison Builder** prepares an admin-only source-to-proposal change summary and private preview.
9. **Approval Gate** records an explicit Platform Admin approval or rejection; AI cannot call or bypass it.
10. **Variant Version Factory** creates a new immutable customer/site variant lineage after approval.
11. **History Writer** appends immutable session, prompt, provider, validation, and decision evidence.

These names do not prescribe Laravel class names or storage technology.

## Bounded data flow

```text
Master index + exact version (read only)
              |
              v
sanitized working snapshot + editable allowlist
              |
customer/site scope + sanitized logo + brand theme + instruction
              |
              v
normalized provider request
              |
              v
untrusted normalized proposal
              |
     schema/security/structure/render validation
              |
              v
private review package --reject--> immutable rejection history
              |
            approve
              v
new customer/site Variant Version --X--> no automatic publication
```

## Architectural invariants

- Master files and historical versions are read-only throughout the workflow.
- A request references one exact source version and its checksums; aliases such as `latest` are not accepted.
- Only properties allowed by the asset's editable schema may change.
- Provider output is never a version until validation and explicit approval succeed.
- Approval and publication are separate permissions and state transitions.
- Every retry or regeneration creates a new attempt/proposal identity.
- Every approved result creates a new version; existing variants are never edited in place.
- Prompt history is append-only and private.
- Customer/site ownership is explicit on every session, proposal, preview, history entry, variant, cache key, and future storage path.
- Runtime/customer packages exclude sources, prompts, editable schemas, provider payloads, private previews, logo analysis, and AI metadata.

## Failure behavior

Authorization failure, invalid input, provider timeout, malformed output, validation failure, rejection, cancellation, or history-write failure leaves the Master Asset and every published version unchanged. A variant must not be created unless the approval decision and immutable history entry can be recorded atomically by a future implementation.

## Decisions made in this layer

1. The provider boundary uses normalized request/proposal envelopes rather than provider SDK types.
2. `review_required` is the canonical review state. The example term `pending_review` is treated only as a legacy/display synonym and must normalize to `review_required` before persistence.
3. Approval produces an unpublished Variant Version, not a runtime publication.
4. Provider payloads use sanitized working representations, never internal paths, database objects, credentials, or unrestricted Master Library archives.
5. JSON Schema defines the transport-neutral shape; implementation language bindings are deferred.
