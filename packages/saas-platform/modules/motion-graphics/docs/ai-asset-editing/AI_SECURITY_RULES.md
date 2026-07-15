# AI Asset Editing Security Rules

Status: **Architecture only.** These rules are mandatory acceptance criteria for future implementation.

## Authority

- Only an authenticated main Platform Admin may create, submit, review, approve, reject, cancel, regenerate, or materialize an editing session.
- Tenant permissions such as `motion.view` or `motion.use` never authorize AI editing.
- Authorization is checked at every operation and again inside future jobs; UI visibility is not authorization.
- AI providers and adapters have no approval, publication, deployment, ownership, or permission authority.

## Tenant/customer isolation

- Bind every session, provider attempt, proposal, preview, history entry, variant, cache key, job, storage key, and signed URL to one explicit customer/site scope.
- Deny missing, ambiguous, stale, or mismatched scope.
- Never reveal whether another customer has a related logo, palette, prompt, proposal, or variant.
- Cross-customer clone/reuse requires a new authorized lineage and ownership manifest.

## Input protection

- Confirm authorization to use the customer logo.
- Validate extension, MIME, signature, dimensions, complexity, decompression limits, and size.
- Rasterize or sanitize active vector content according to future security policy.
- Strip metadata, scripts, event handlers, external references, embedded credentials, and hidden payloads.
- Use an opaque private reference or bounded encoding; never give a provider a public logo URL.
- Send only the selected sanitized asset representation and required allowlisted metadata.

## Master and metadata protection

- Master files remain immutable and platform-owned.
- A provider receives a disposable sanitized working representation only when required; it never receives internal paths, database records, full library archives, credentials, private AI metadata, license internals, or unrelated sources.
- `source_definition_json`, `editable_schema_json`, and `ai_metadata_json` remain private. Only the minimum editable allowlist and safe semantic constraints may enter a provider request.
- Tenant and runtime projections never expose sources, schemas, prompts, histories, proposals, private previews, provider payloads, or logo analysis.

## Provider controls

- Credentials are server-held, encrypted, rotated, and never placed in prompts or logs.
- Configure retention and training opt-out where supported and approved.
- Enforce provider/model allowlists, data residency policy, timeouts, retry ceilings, rate limits, concurrency limits, circuit breakers, and cost budgets.
- Use controlled request/response size and safe parsers.
- Record safe correlation IDs and checksums, not secrets or raw payloads by default.
- Treat hidden reasoning as unnecessary and do not request, store, or expose it.

## Output controls

Treat every provider response as hostile input. Validate schema, MIME, signatures, file paths, size, XML/SVG, CSS, accessibility, editable-property diff, dependencies, theme tokens, and renderer behavior. Reject scripts, `javascript:`, external URLs, unsafe event handlers, path traversal, arbitrary HTML, unapproved fonts/dependencies, data exfiltration constructs, and ownership/license changes.

Preview generation must use a future isolated renderer with network disabled, strict resource and time limits, no credentials, and disposable storage.

## Approval and publication

- No automatic approval or publication.
- Approval requires an explicit main Platform Admin decision over the exact validated proposal checksum.
- Publication is a separate future permission and workflow.
- Failure at any stage leaves masters and published variants unchanged.

## Logging and errors

Security logs contain opaque IDs, actor/customer scope, event type, checksums, provider key, result code, and safe diagnostics. They exclude raw logos, raw asset content, prompts, generated source, credentials, provider raw responses, private previews, and personal data. Tenant-visible errors are generic and never disclose provider or internal details.
