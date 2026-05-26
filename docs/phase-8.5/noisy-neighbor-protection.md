# Noisy Neighbor Protection — Phase 8.5

## Overview

The Noisy Neighbor protection stack detects tenants generating disproportionate platform load and applies adaptive throttling to protect all other tenants.

## Detection Thresholds

| Pressure Level | Utilization | Action |
|----------------|-------------|--------|
| normal | < 40% | No action |
| moderate | 40–70% | Monitoring only |
| high | 70–90% | Throttle factor 0.5 |
| noisy | ≥ 90% | Throttle factor 0.2, strike recorded |

## Strike System

`TenantPenaltyEngine` tracks up to 3 strikes per tenant per hour:
- Strike 1: 20% slowdown (factor 0.8)
- Strike 2: 50% throttle (factor 0.5)  
- Strike 3: Severe throttle (factor 0.2)

Strikes reset automatically after 1 hour.

## Adaptive Throttling Flow

```
RuntimeFairnessCoordinator.enforceFairness(tenantIds)
    ↓
NoisyNeighborDetector.detect()      ← marks detected tenants in cache (5min)
    ↓ (for each noisy tenant)
TenantPenaltyEngine.recordStrike()
    ↓
AdaptiveTenantThrottler.adapt()     ← computes effective factor
    ↓
RuntimeThrottleManager.activateTenant()
```

## Restoration

Tenants that previously triggered throttling are automatically released when their pressure score drops below `high` threshold on the next fairness enforcement cycle.

## Fairness Score

`RuntimeFairnessCoordinator.fairnessScore(tenantIds)` returns 0-100:
- 100 = all tenants in normal pressure
- Penalty: -2 per noisy tenant %, -1 per high-pressure tenant %

## Platform Pressure Levels

| Level | Noisy Tenant % |
|-------|---------------|
| normal | < 5% |
| elevated | 5–10% |
| high | 10–20% |
| critical | ≥ 20% |
