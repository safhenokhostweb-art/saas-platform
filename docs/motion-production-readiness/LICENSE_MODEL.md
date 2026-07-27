# License Model Preparation

This vocabulary is a contract only; it does not implement pricing, payment, or entitlement persistence.

## License classes

| Class | Intended availability |
|---|---|
| `internal` | platform authoring, QA, and internal templates only |
| `customer_available` | eligible customers under platform policy |
| `premium` | requires a future entitlement |
| `restricted` | explicit allowlist and additional approval |

## License snapshot

Each approved version and deployment decision freezes:

```json
{
  "class": "customer_available",
  "usage_limit": 5,
  "allowed_sites": ["site-123"],
  "expiration": null,
  "ownership": { "type": "platform", "customer_id": null }
}
```

`usage_limit` is a non-negative integer or `null` for policy-defined unlimited use. `allowed_sites` contains explicit opaque site IDs; an empty list grants no tenant site usage unless a future policy explicitly defines a wildcard. `expiration` is `null` or RFC 3339. Ownership must match asset lineage.

## Decision rules

- License is evaluated at catalog visibility, variant creation, publication, URL issuance, and runtime consumption.
- A customer variant cannot broaden its source license.
- Changing a license creates a new governed version/snapshot; it never rewrites historical grants.
- Expiry blocks new access after the defined instant while preserving audit evidence.
- Cloning or deduplication never transfers one customer's rights to another.
- Restricted assets require explicit Platform Admin approval and site allowlisting.

