# Customer Runtime Package

`App\Modules\MotionGraphics\DTO\CustomerRuntimePackage` (task scope item 4) — the narrow, customer-facing subset of a `DeploymentManifest`:

```php
$manifest->toCustomerRuntimePackage(); // -> CustomerRuntimePackage { assets: list<RuntimeProjectionDTO> }
```

## Allowed

Published assets, optimized references, theme tokens, placement rules — exactly the `RuntimeProjectionDTO` allowlist already established in Phase 1/2, reused unchanged. `CustomerRuntimePackage` adds no new fields of its own beyond a plain list of these.

## Forbidden

Editing data, source definitions, ownership data, approval data — never present, by construction. `CustomerRuntimePackage` holds nothing but already-allowlisted `RuntimeProjectionDTO` values, and it deliberately omits everything `DeploymentManifest` carries that a customer never needs to see: `siteReference`, `templateReference`, `packageVersion`, `checksum`, `compatibilityResult`. Those are operational/admin bookkeeping, not customer runtime data — keeping them out of `CustomerRuntimePackage` is itself part of the allowlist discipline, even though none of them are individually "forbidden" in the sense of leaking authoring internals.

## Why this is a separate type from `DeploymentManifest`

A `DeploymentManifest` is what the (future) Deployment Engine and Platform Admin reason about — it needs the site/template/package identity to route and audit the deployment. A `CustomerRuntimePackage` is what a customer site would eventually *render* — it needs nothing but the assets themselves. Collapsing the two into one type would mean either over-exposing manifest bookkeeping to a customer-facing structure, or under-specifying what the Deployment Engine needs to track. Keeping them distinct, with an explicit one-way `toCustomerRuntimePackage()` narrowing, makes the allowlist boundary a type-level guarantee rather than a runtime convention someone could forget to apply.

## Still no delivery mechanism

This phase defines the *shape* of the output only. No route, no controller, no customer-site code, no public API consumes `CustomerRuntimePackage` — task scope forbids public APIs and customer-site changes entirely. A future phase deciding how a customer site actually receives this package is unaffected by this decision either way, since the shape is already customer-safe.
