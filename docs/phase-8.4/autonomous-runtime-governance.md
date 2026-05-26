# Autonomous Runtime Governance — Phase 8.4

## Overview

Phase 8.4 introduces a fully autonomous, policy-driven runtime governance stack. The platform can detect degradation, isolate failing components, prevent cascading failures, apply recovery policies, and autonomously heal from recoverable incidents — all without operator intervention for auto-resolvable severities.

## Architecture

```
RuntimeGovernanceKernel
  ├── collectSignals()          ← RuntimeHealthService, RuntimeAnomalyEngine, queue/memory
  ├── RuntimeDecisionEngine     ← evaluate signals against policies
  ├── GovernancePolicyRegistry  ← declarative policy store (5 default policies)
  └── GovernanceExecutionPipeline ← execute decisions, record actions
```

## Runtime State Machine

```
HEALTHY → DEGRADED → CRITICAL → EMERGENCY
                 ↑                    |
                 └─── (via CRITICAL) ─┘
```

EMERGENCY state can only recover to CRITICAL, never directly to HEALTHY.

## Key Components

| Component | Responsibility |
|-----------|---------------|
| `RuntimeGovernanceKernel` | Orchestrates a single governance tick cycle |
| `GovernancePolicyRegistry` | Stores and retrieves declarative policies |
| `RuntimeDecisionEngine` | Evaluates signals against policy conditions |
| `RuntimeStateCoordinator` | Manages platform state transitions with history |
| `GovernanceExecutionPipeline` | Executes governance decisions and records results |

## Default Policies

1. **tenant_overload_protection** — triggers on `tenant_overload`; degrades affected tenant
2. **queue_pressure_relief** — triggers on `queue_anomaly`; freezes deployment when queue backlog high
3. **critical_failure_isolation** — triggers on `health_degradation`; transitions to CRITICAL and freezes deployment
4. **stale_lock_recovery** — triggers on `deployment_anomaly`; transitions to DEGRADED state
5. **memory_pressure_relief** — triggers on `memory_pressure`; transitions to DEGRADED state

## Governance Tick Cycle

1. Collect signals from health, queue, anomaly engine, memory, platform metrics
2. Evaluate all enabled matching policies against signals
3. Execute prioritized decisions (deployment_freeze=100, state transitions=90-95, recovery=80)
4. Mark cooldowns to prevent policy thrashing
5. Emit telemetry for every action taken

## Artisan Commands

```bash
php artisan governance:runtime-status           # Current governance state overview
php artisan governance:validate-runtime         # Validate all governance components
php artisan governance:list-policies            # List registered policies
php artisan governance:simulate-failure TYPE    # Simulate an incident (non-destructive)
php artisan governance:test-recovery STRATEGY   # Test a safe recovery strategy
```
