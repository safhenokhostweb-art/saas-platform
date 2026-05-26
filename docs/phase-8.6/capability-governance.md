# Capability Governance — Phase 8.6

## Capability Definitions

| Capability | Risk | Requires Grant | Allowed Contexts |
|---|---|---|---|
| filesystem.read | medium | yes | plugin, tenant |
| filesystem.write | high | yes | plugin, tenant |
| queue.dispatch | medium | yes | plugin, tenant, system |
| notification.send | medium | yes | plugin, tenant |
| api.external | high | yes | plugin |
| tenant.read | medium | yes | plugin, tenant |
| tenant.write | high | yes | plugin, tenant |
| telemetry.emit | low | no | plugin, tenant, system |
| storage.upload | medium | yes | plugin, tenant |
| runtime.schedule | medium | yes | plugin, system |
| deployment.execute | critical | yes | system |

## Grant Lifecycle

```
CapabilityGrantManager.grant(moduleId, capability, grantedBy)
  → validates capability exists in registry
  → stores in cache with 24h TTL
  → grant can be revoked individually or all at once

CapabilityResolutionEngine.resolve(moduleId, capability)
  → if !requiresExplicitGrant → always allowed
  → else → check GrantManager
```

## Deny-by-Default

Any module without an explicit grant has access only to `telemetry.emit` (the only free capability). All 10 remaining capabilities require explicit grant.

## Runtime Enforcement

```php
RuntimeCapabilityEnforcer.enforce(moduleId, capability, context)
  → resolve() → false → emit warning telemetry → return false
  → resolve() → true  → emit debug telemetry → return true

RuntimeCapabilityEnforcer.enforceOrFail(moduleId, capability)
  → throws CapabilityDeniedException on deny
```
