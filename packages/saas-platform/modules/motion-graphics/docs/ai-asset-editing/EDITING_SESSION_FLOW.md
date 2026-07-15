# Editing Session Flow

Status: **Architecture only.**

## Session record

An Editing Session freezes the authoring context for one Master Asset version and one customer/site boundary:

```json
{
  "session_id": "session_opaque_id",
  "asset_id": "icon:business:company:v1",
  "source_version": "v1",
  "requested_by": "platform-admin-opaque-id",
  "customer_id": "customer-opaque-id",
  "provider": "fake",
  "prompt": "private bounded prompt",
  "status": "review_required"
}
```

The complete shape is defined in `schemas/EDITING_SESSION.schema.json`.

## Canonical statuses

| Status | Meaning | Permitted next states |
|---|---|---|
| `draft` | Private request is being prepared; no provider call occurred. | `processing`, `cancelled` |
| `processing` | Frozen request is being processed by one provider attempt. | `generated`, `cancelled` |
| `generated` | A response was normalized; validation is pending or running. | `review_required`, `rejected`, `cancelled` |
| `review_required` | Proposal passed automated validation and awaits Platform Admin. | `approved`, `rejected`, `cancelled` |
| `approved` | Explicit admin decision recorded and a new variant version may be created. | terminal |
| `rejected` | Proposal was invalid or explicitly rejected. | terminal |
| `cancelled` | Session was cancelled without a variant. | terminal |

`pending_review` is not a stored status; integrations must normalize that legacy/display phrase to `review_required`.

## Flow

1. Verify main Platform Admin authority and customer/site scope.
2. Resolve a stable `asset_id`, exact source version, editable schema, and checksums.
3. Validate and sanitize the authorized logo and Brand Theme.
4. Create a `draft` session and append its first history event.
5. Freeze inputs and start a uniquely identified provider attempt; move to `processing`.
6. Normalize the provider response; move to `generated` only when it matches the proposal contract.
7. Run structural, security, accessibility, renderer, theme, metadata, and customer-isolation validation.
8. If validation succeeds, create a private review package and move to `review_required`.
9. Platform Admin approves, rejects, or cancels.
10. Approval creates a new immutable variant version and finalizes the session as `approved`; it does not publish.

## Concurrency and retries

- A session has one frozen source/checksum set.
- Each provider call has a new `attempt_id`; retry never rewrites an earlier attempt.
- Each normalized result has a new `proposal_id`.
- Duplicate submission uses an idempotency key scoped to actor, customer/site, source version, and normalized inputs.
- Approval must compare the proposal checksum reviewed by the admin with the checksum being materialized.
- A stale proposal cannot be approved after its review package or source verification changes.

## Cancellation and failures

Cancellation is best-effort for an in-flight external request but immediately prevents its late response from entering review. Provider failure records an immutable failed attempt while the session may either be cancelled or use a separately recorded retry policy in a future implementation. No failure changes a master or published variant.
