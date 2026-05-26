# Plugin Resource Governance — Phase 8.6

## Default Resource Limits (PluginResourceBudget)

| Resource | Limit |
|---|---|
| Queue jobs per hour | 60 |
| HTTP requests per hour | 120 |
| Storage uploads per day | 5 |

## Budget Tracking

Sliding window using `floor(time() / 3600)` as window key. Cache key: `plugin.resource.budget2.{moduleId}.{resource}.{window}`.

## Pressure Classification (ExtensionRuntimePressureAnalyzer)

| Utilization | Level |
|---|---|
| < 50% | normal |
| 50–74% | elevated |
| 75–89% | high |
| ≥ 90% | critical |

## Fairness Coordinator

`PluginFairnessCoordinator` tracks all registered modules and computes a platform fairness score:

```
fairness_score = max(0, 100 - (noisy_pct × 1.5))
```

## Load Balancer

`SandboxLoadBalancer` applies throttle factors by pressure:

| Pressure | Throttle Factor |
|---|---|
| normal | 1.0 |
| elevated | 0.7 |
| high | 0.4 |
| critical | 0.1 |

## Quota Manager

`PluginQuotaManager` emits warning telemetry when limits are exceeded and returns false (non-blocking) so plugins can gracefully degrade.
