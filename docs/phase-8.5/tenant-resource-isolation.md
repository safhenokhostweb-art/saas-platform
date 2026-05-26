# Tenant Resource Isolation — Phase 8.5

## Overview

The Tenant Resource Isolation layer enforces per-tenant runtime quotas, preventing any single tenant from exhausting shared platform resources.

## Architecture

```
RuntimeIsolationCoordinator
  ├── TenantResourceGovernor     ← enforcement decisions
  ├── TenantConsumptionTracker   ← request/job/storage accounting
  ├── TenantRuntimeBudget        ← sliding-window budget counters
  ├── TenantQuotaManager         ← quota configs per plan
  └── TenantCapacityProfile      ← plan-to-quota mapping
```

## Quota Plans

| Plan         | Req/min | Jobs/hr | Runtime sec/hr | Storage MB | Concurrent Jobs |
|--------------|---------|---------|----------------|------------|-----------------|
| trial        | 60      | 100     | 300            | 100        | 2               |
| starter      | 300     | 500     | 1,800          | 1,024      | 5               |
| professional | 1,200   | 2,000   | 7,200          | 10,240     | 20              |
| enterprise   | 6,000   | 10,000  | 36,000         | 102,400    | 100             |

## Budget Tracking

Sliding 1-hour window per resource. Budget keys are time-bucketed (`window = floor(time / 3600)`), so each window auto-expires without manual cleanup.

## Enforcement Flow

```
Request arrives → TenantConsumptionTracker.trackRequest()
                → TenantRuntimeBudget.charge()
                → TenantResourceGovernor.enforce()
                     ↓ (>90% utilization)
                RuntimeThrottleManager.activateTenant(factor=0.1)
                     ↓ (80-90% utilization)
                RuntimeThrottleManager.activateTenant(factor=0.5)
```

## Emergency Quota

`TenantQuotaManager.applyEmergencyQuota()` reduces all limits to 10% for 30 minutes. Used during critical incidents affecting that tenant.

## Cache Keys

- `isolation.quota.tenant.{id}` — quota config (1h TTL)
- `isolation.budget.tenant.{id}.{resource}.{window}` — consumption counter
