# Tenant Degradation Strategy — Phase 8.4

## Overview

Tenant degradation limits resource consumption for unhealthy tenants while preserving critical capabilities (billing, admin, support) at all levels.

## Degradation Levels

| Level | Name | Restrictions | Preserved |
|-------|------|-------------|-----------|
| 0 | Normal | None | All capabilities |
| 1 | Reduced Telemetry | Heavy telemetry disabled | All features |
| 2 | Limited Reports | No heavy reports | Core features |
| 3 | Queue-Only Notifications | Realtime notifications off | Core + billing |
| 4 | Read-Only | No writes (except billing/admin) | Billing, admin |
| 5 | Isolated | No operations | Billing, admin, support |

## Unconditionally Preserved Capabilities

At ALL degradation levels, the following are always allowed:
- `billing` — payment processing must never be blocked
- `admin` — admin access for operator intervention
- `health_bypass` — health check endpoints
- `support` — support ticket access

## Protection Flow

```
TenantProtectionCoordinator.protect(tenantId)
    ↓
TenantDegradationManager.degrade(tenantId, level+1)
    ↓ (at level 5)
TenantCircuitRegistry — open tenant circuit
```

`TenantProtectionCoordinator.restore(tenantId)` resets to level 0 and closes the tenant circuit.

## Capability Check

```php
$coordinator = app(TenantProtectionCoordinator::class);
$allowed = $coordinator->isCapabilityAllowed($tenantId, 'reports');
```

## Cache Keys

- `governance.degradation.tenant.{id}` — current degradation level
- `governance.throttle.tenant.{id}` — throttle factor (1.0 = no throttle)
- `governance.circuit.tenant.{id}.{resource}.state` — per-tenant circuit state
