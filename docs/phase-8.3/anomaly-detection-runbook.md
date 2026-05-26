# Anomaly Detection Runbook — Phase 8.3

## Anomaly Engine

`RuntimeAnomalyEngine::scan()` runs all four detectors and emits a telemetry event per detected anomaly. Duplicate anomalies are suppressed for 5 minutes via cache cooldown.

## Anomaly Codes

| Code | Severity | Detector | Threshold |
|---|---|---|---|
| `QUEUE_FAILED_JOBS_HIGH` | warning/critical | QueueAnomalyDetector | ≥10 failed jobs |
| `QUEUE_BACKLOG_HIGH` | warning | QueueAnomalyDetector | ≥500 pending jobs |
| `TENANT_ISOLATION_VIOLATIONS` | critical | TenantIsolationAnomalyDetector | ≥3 violations |
| `DEPLOYMENT_FAILURES_RECENT` | critical | DeploymentFailureDetector | ≥2 failures in 24h |
| `DEPLOYMENT_STALE_LOCK` | critical | DeploymentFailureDetector | Lock held >120 min |
| `SECURITY_AUTH_FAILURE_BURST` | critical | SecurityAnomalyDetector | ≥20 failures in 15 min |
| `RUNTIME_MEMORY_HIGH` | warning | RuntimeAnomalyEngine | >85% of PHP memory limit |

## Running the Scanner

```bash
# Scan all detectors via tinker:
php artisan tinker
>>> app(\SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine::class)->scan()

# Check memory anomaly specifically:
>>> app(\SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine::class)->scanMemory()
```

## Responding to Anomalies

### QUEUE_FAILED_JOBS_HIGH
```bash
php artisan queue:diagnostics
php artisan queue:diagnostics --job=<id>
# Fix root cause then: php artisan queue:flush
```

### DEPLOYMENT_STALE_LOCK
```bash
php artisan deployment:status
php artisan deployment:unlock --all --force
# If mid-deployment: php artisan deployment:rollback --dry-run
```

### SECURITY_AUTH_FAILURE_BURST
```bash
# Check server access logs for IP patterns
# Consider enabling rate limiting or IP blocking
php artisan observability:validate-governance
```

### TENANT_ISOLATION_VIOLATIONS
```bash
# IMMEDIATE: investigate all recent requests with X-Trace-Id
# Check telemetry_events for security.tenant.boundary_violation events
# Consider disabling the affected tenant site pending investigation
```

## Cooldown Behavior

Anomaly events are rate-limited per code to one emission per 5 minutes. The cooldown key format is:
`anomaly.cooldown.<md5_of_code>`
