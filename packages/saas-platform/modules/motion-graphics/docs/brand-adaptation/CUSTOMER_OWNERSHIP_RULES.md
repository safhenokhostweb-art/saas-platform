# Customer Ownership Rules

Status: **Architecture only.**

## Authority

Only a main Platform Admin may:

- select global masters;
- upload or replace a customer logo on the customer's behalf;
- request AI adaptation;
- create variant drafts;
- compare, approve, publish, restore, clone, or retire variants; and
- generate customer packages.

Tenant users remain consumers. They cannot access source assets, prompts, logo-analysis data, editable schemas, AI provider controls, draft previews, or version-authoring endpoints.

## Ownership boundaries

| Asset | Logical owner | Editing authority |
|---|---|---|
| Global master | Platform | Platform Admin |
| Customer logo | Customer/site, platform-custodied | Platform Admin workflow |
| Generated variant | Customer/site | Platform Admin workflow |
| Compiled package | Customer/site | Read-only consumer |
| Reuse template | Platform | Platform Admin |

A customer-specific variant must carry an explicit `customer_id` or finalized site boundary. Null customer ownership is valid only for platform-global assets and reviewed reuse templates.

## Tenant isolation

Every private lookup, job, cache key, preview, package, log, and signed URL must include and enforce the customer/site boundary. Deny access when tenant context is missing, ambiguous, or mismatched.

Customer A must never learn that Customer B has the same palette, logo characteristics, prompt, provider, or generated variant.

## Reuse and cloning

Similar colors do not transfer ownership. Cross-customer reuse is allowed only through one of these safe models:

1. **Compiled payload deduplication:** identical sanitized bytes are stored once internally, while each customer receives a distinct ownership manifest and access boundary.
2. **Platform reuse template:** Platform Admin reviews a generic, logo-free result and promotes it to a versioned platform template.
3. **Authorized clone:** a new customer-owned lineage is created from an approved reusable template, with a new manifest, customer identity, audit entry, and approval.

Never point one customer directly at another customer's variant record or private package.

## Logo rights and deletion

Platform Admin must confirm authorization to use the uploaded logo. Original logos remain private, encrypted where required, and subject to retention/deletion policy. Deleting a logo does not rewrite immutable historical audit records; retained history stores only the minimum permitted checksum/reference and follows legal retention requirements.

