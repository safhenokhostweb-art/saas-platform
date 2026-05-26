# Operational Metrics Reference — Phase 8.3

## OperationalMetricsAggregator

The single entry point for platform-wide operational metrics. Returns a structured snapshot suitable for admin dashboards (backend data only — no Blade views yet).

```php
$agg = app(\SaaSPlatform\CoreUtils\Telemetry\Metrics\OperationalMetricsAggregator::class);

// Full aggregate (24-hour window by default)
$agg->aggregate(hours: 24);

// Health score (0-100, Grade A+ to F)
$agg->healthScore();
```

## Health Score Dimensions

| Dimension | Weight | Healthy Condition |
|---|---|---|
| Runtime | 25pts | Memory < 256 MB |
| Queue | 25pts | 0 failed jobs |
| Deployment | 25pts | Success rate ≥ 95% |
| Errors | 25pts | 0 critical telemetry events in last hour |

## Individual Repositories

### RuntimeMetricsRepository
```php
$runtime = app(\SaaSPlatform\CoreUtils\Telemetry\Metrics\RuntimeMetricsRepository::class);
$runtime->current();                          // PHP version, memory, hostname, PID
$runtime->eventCountBySeverity(hours: 24);    // ['critical' => 0, 'error' => 3, ...]
$runtime->eventCountByModule(hours: 24);      // ['tickets' => 42, 'queue' => 5, ...]
$runtime->recentErrors(limit: 20);            // Last N error/critical events (no data payload)
```

### DeploymentMetricsRepository
```php
$dep = app(\SaaSPlatform\CoreUtils\Telemetry\Metrics\DeploymentMetricsRepository::class);
$dep->summary(days: 30);                      // Total, failed, success_rate, avg_duration_ms
$dep->phaseFailureBreakdown(days: 30);        // ['migrations' => 2, 'health-check' => 1]
$dep->recentDeployments(limit: 10);           // UUID, status, phase, timestamps
```

### QueueMetricsRepository
```php
$q = app(\SaaSPlatform\CoreUtils\Telemetry\Metrics\QueueMetricsRepository::class);
$q->summary();                                // Pending, failed, queue_depths, failed_last24h
$q->failedJobTrend(days: 7);                  // ['2026-05-20' => 3, '2026-05-21' => 0, ...]
```

### TenantMetricsRepository
```php
$t = app(\SaaSPlatform\CoreUtils\Telemetry\Metrics\TenantMetricsRepository::class);
$t->allTenantSummary();                       // event_count + error_count per tenant (24h)
$t->forTenant(tenantId: 42, hours: 24);      // Tenant-scoped events and error count
$t->maintenanceSummary();                     // Active windows + windows in last 24h
```
