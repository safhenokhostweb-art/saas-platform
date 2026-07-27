# Tenant Authorization Contract

## Ownership

Platform Master Assets are owned by the platform. Customer variants are owned by the designated customer/site but remain authored and governed through Platform Admin workflows. Tenants are consumers, not library editors.

## Tenant capabilities

Tenants may:

- list an allowlisted public catalog projection;
- consume an authorized, published asset version;
- request or use an allowed customer variant when license and platform policy permit; and
- receive optimized runtime artifacts and minimal read-only configuration.

Tenants may not access or modify Master source, editable schemas, AI metadata, prompts, histories, draft/review artifacts, private previews, provider data, approvals, checksums used as secrets, or global publication controls. They cannot publish globally or change ownership.

## Isolation contract

Every tenant operation binds authenticated tenant/site identity, license entitlement, asset/version, and intended site. Storage keys, caches, jobs, logs, signed URLs, previews, and manifests must use the same boundary. Missing, ambiguous, or mismatched context is denied.

Runtime output is an allowlisted projection containing only published version identity, engine, optimized files, safe theme/runtime options, integrity metadata, accessibility data, and license-safe expiry hints. Source definitions and private governance fields are never serialized.

Signed URLs, if used, must be short-lived, audience/site-bound, unguessable, HTTPS-only, and revocable. Authorization is rechecked before issuance; URL possession must not widen license rights.

