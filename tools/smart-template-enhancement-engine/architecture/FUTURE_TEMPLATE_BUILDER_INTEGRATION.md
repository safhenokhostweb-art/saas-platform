# Future Template Builder Integration

## Boundary

This prototype must remain outside runtime until an approved architecture phase creates a server-side adapter. Claude or another implementation agent should treat the files here as preparation evidence, not production code to import directly into Laravel.

## Suggested future flow

1. The Template Builder submits a validated business profile and a normalized section inventory.
2. A platform application service resolves published, licensed, active Master Asset versions.
3. The deterministic matcher returns candidates without SVG source, editable schema, or AI metadata.
4. An optional AI provider proposes improved query terms through an adapter; provider output is untrusted input.
5. Eligibility, tenant boundary, permissions, quality, theme compatibility, and asset paths are revalidated server-side.
6. Platform Admin reviews recommendations and any requested brand-variant proposal.
7. Approved references are stored through a versioned builder contract. Master Assets remain immutable.

## Integration contract

- Input: versioned business profile plus template section schema.
- Output: stable asset IDs, published versions, rendering-safe asset URLs, recommendation reasons, and score evidence.
- Never output to tenants: source definition, editable schema, AI metadata, private source paths, or authoring controls.
- Never let AI publish directly.
- Never infer license or tenant access from library visibility.

## Required production work

- Laravel contracts and adapter design approved under project governance.
- Platform Admin authorization and audit events.
- Published-version and license resolver.
- Tenant isolation and cache namespace tests.
- Provider-neutral AI adapter with timeout, validation, cost, and privacy controls.
- Template Builder compatibility and rollback contract.
- Accessibility, contrast, visual QA, and browser rendering tests.

No part of that production work is implemented by this prototype.
