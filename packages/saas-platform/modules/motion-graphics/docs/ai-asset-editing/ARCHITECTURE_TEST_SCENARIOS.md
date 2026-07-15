# Architecture Test Scenarios

Status: **Preparation only.** These scenarios define future acceptance behavior and use a fake adapter. They do not require a real provider, network access, Laravel, database, UI, or runtime API in this phase.

## Test fixture

- one platform-owned immutable Master Asset from `library-index.json`;
- its exact source version, editable metadata, and pre-test checksums;
- one main Platform Admin actor;
- Customer A and Customer B scopes;
- one sanitized fictional logo descriptor;
- the Brand Theme from `examples/mock-provider-request.json`; and
- a deterministic fake provider returning `examples/mock-provider-response.json`.

## Required scenarios

### 1. Select exact Master Asset

Resolve a stable asset ID and exact version. Assert it is platform-owned, active, variant-supported, and checksum-frozen. Reject unknown IDs, `latest`, customer-owned sources, and mismatched versions.

### 2. Submit editing request

Create a `draft` session for Customer A as Platform Admin. Assert required input, customer scope, logo sanitation evidence, editable constraints, and prompt reference are present. Assert a tenant actor receives denial.

### 3. Generate proposal through fake adapter

Move `draft -> processing -> generated`. Assert the normalized request matches `AI_PROVIDER_REQUEST.schema.json`; the deterministic response matches `AI_PROVIDER_PROPOSAL.schema.json`; no network call occurs; and a new attempt/proposal ID is recorded.

### 4. Validate and require review

Run schema, diff, SVG/engine, accessibility, theme, path, size, dependency, and security validators. Assert valid output moves to `review_required`; invalid output moves to `rejected` and cannot be reviewed or materialized.

### 5. Approve exact proposal

Approve as Platform Admin using the exact proposal checksum. Assert the decision is explicit, actor-bound, reasoned, and immutable. Reject tenant approval, AI approval, stale checksum approval, duplicate approval, and approval before validation.

### 6. Create new variant version

Assert approval creates a new Customer A variant version matching `ASSET_VARIANT.schema.json`, with master lineage, parent lineage where applicable, theme, provider, prompt-history reference, checksums, and unpublished status. Assert no existing variant is overwritten.

### 7. Verify Master Asset unchanged

Compare every Master Asset file and metadata checksum captured before the session. Assert byte-for-byte equality and unchanged `library-index.json`. This is a release-blocking invariant.

### 8. Verify immutable history

Assert `AI_EDIT_HISTORY.json` contains session creation, provider attempt, proposal, validation, decision, and resulting variant references. Attempt to update/delete an earlier event and assert denial. Retry must append a new attempt.

### 9. Verify customer isolation

Attempt to resolve Customer A's session, proposal, preview, history, logo, or variant under Customer B scope. Assert deny-by-default and no existence leak. Verify all private storage/cache/job identities are customer/site-scoped.

### 10. Reject forbidden changes

Fake proposals that change ownership, license, source identity, editable schema, external URLs, scripts, unsafe CSS, `viewBox`, accessibility labels, or undeclared dependencies must fail validation and create no variant.

### 11. Separate approval from publication

After approval, assert the new variant is not published, deployed, or tenant-visible. Assert the AI adapter and session workflow expose no publication operation.

### 12. Provider failure and late response

Simulate timeout, malformed JSON, oversized content, provider refusal, and a response arriving after cancellation. Assert controlled failure history, no review package for invalid output, no variant, and unchanged master/published assets.

### 13. Version restoration

Restore an earlier customer variant by creating a new version. Assert history and parent/restore lineage are retained and no historical version or published pointer is rewritten.

### 14. Runtime data minimization

Build a hypothetical public manifest projection and assert it excludes prompts, source files, editable schemas, AI metadata, provider payloads, logo analysis, histories, private previews, actor IDs, and another customer's data.

## Validation matrix

| Area | Positive | Negative |
|---|---|---|
| Authorization | Main Platform Admin | Tenant, unauthenticated, wrong scope |
| Provider | Fake normalized proposal | Timeout, malformed, oversized, unexpected fields |
| Asset | Exact immutable active master | Unknown, mutable alias, checksum mismatch |
| Proposal | Editable, safe, accessible | Script, external URL, forbidden metadata, structure loss |
| Approval | Exact reviewed checksum | AI/self approval, stale or duplicate approval |
| Variant | New scoped immutable version | In-place overwrite or cross-customer pointer |
| History | Append-only complete sequence | Update/delete/secret leakage |
| Publication | Remains separate | Automatic publish or runtime exposure |
