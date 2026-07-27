# Asset Ownership Model

## Platform Master Asset

Owned by the platform, immutable by version, globally addressable, and reusable subject to lifecycle and license. Customers cannot modify it. Brand adaptation derives a new variant and retains the exact source ID/version/checksum.

## Template Asset Package

Owned by a template identity. It contains canonical references, requirements, compatibility constraints, placement/typography intent, and fallback policy. It owns no referenced Master bytes and receives no source definitions, editable schemas, prompts, or AI metadata.

## Customer Private Asset Package

Owned by one customer/site boundary. It may contain approved compiled logo motion, private illustrations, customer media, and customer variants. It is isolated, non-public, and non-reusable by other customers by default.

| Operation | Platform Admin | Template author | Tenant/customer | AI |
|---|---:|---:|---:|---:|
| Manage Master/version | yes | no | no | proposal only |
| Author template references | approves | within template scope | no | proposal only |
| Generate/approve site package | yes | no | consume/request | no |
| Promote private asset | yes, with rights review | no | consent required | no |
| Access another site package | deny | deny | deny | deny |

Ownership changes are append-only governed events. UI, file location, shared checksums, or identical colors never establish ownership.

