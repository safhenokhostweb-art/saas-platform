# Proposal Approval Flow

Status: **Architecture only.**

## Proposal rule

Every AI result is a proposal. A successful provider response is neither approved nor a variant, and it has no publication authority.

AI may propose:

- recoloring within approved theme/editable tokens;
- style changes allowed by the editable schema;
- a brand theme variant;
- bounded composition adjustment;
- an industry-specific variation; and
- safe logo placement only where a logo placeholder is editable.

AI may not:

- overwrite or delete a Master Asset;
- change source lineage, platform ownership, customer ownership, or license;
- widen the editable schema;
- remove accessibility or security controls;
- approve, publish, deploy, or expose private metadata; or
- create a cross-customer reference.

## Automated validation gate

Before admin review, a proposal must pass:

1. request/proposal JSON Schema validation;
2. source asset/version/checksum match;
3. generated-file MIME, extension, size, and checksum verification;
4. editable-property allowlist comparison;
5. SVG/XML or engine-specific structural safety validation;
6. no scripts, external URLs, event handlers, unsafe CSS, fonts, or undeclared dependencies;
7. accessibility preservation (`title`, `desc`, ARIA, reduced motion where applicable);
8. Brand Theme syntax, contrast, and token mapping validation;
9. metadata allowlist validation;
10. private customer/site scope and path validation;
11. preview generation in an isolated future renderer; and
12. immutable audit/history append readiness.

Failure produces a rejected proposal with controlled reasons. It cannot be manually promoted around the validator.

## Review package

The Platform Admin review surface, when implemented, should receive a private allowlisted package containing:

- source identity and exact version;
- proposal identity and checksum;
- before/after private previews;
- normalized visual and structural diff;
- proposed editable metadata changes;
- provider/model provenance and warnings;
- validation profile and results;
- customer/site ownership boundary; and
- concise change and risk summary.

Raw provider payloads, credentials, hidden reasoning, and unrelated internal metadata are excluded.

## Approval

Approval requires a main Platform Admin, an explicit action, the exact reviewed proposal checksum, and a reason or change description. A future implementation must record the approval and create the variant version as one consistent operation. Approval:

- closes the session as `approved`;
- appends the immutable decision history;
- creates a new customer/site-owned Variant Version; and
- leaves all Master Assets and published pointers unchanged.

Approval does **not** publish, deploy, or expose the variant to a tenant.

## Rejection and regeneration

Rejection records actor, timestamp, reason, proposal checksum, and validation evidence, then closes the session as `rejected`. Regeneration creates a new attempt and proposal. It never modifies or deletes the rejected record.

## Publication separation

A separately authorized future publication workflow may consume an approved variant. It must repeat artifact integrity checks and enforce publication permission. No AI editing provider, session coordinator, or approval response may advance the published pointer.
