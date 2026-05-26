# Runtime Recovery Runbook — Phase 8.4

## Automated Recovery (No Operator Action Required)

The following anomalies auto-heal:

| Anomaly | Auto Strategy |
|---------|-------------|
| QUEUE_FAILED_JOBS_HIGH | health_check_retry |
| QUEUE_BACKLOG_HIGH | queue_restart |
| RUNTIME_MEMORY_HIGH | cache_rebuild → health_check_retry |

## Operator-Required Recovery

### DEPLOYMENT_STALE_LOCK

1. Check: `php artisan deployment:status`
2. If safe: `php artisan deployment:unlock --force`
3. Validate: `php artisan governance:validate-runtime`

### DEPLOYMENT_FAILURES_RECENT

1. Review: `php artisan deployment:status`
2. Check failed jobs: `php artisan queue:diagnostics`
3. If deployment frozen: investigate then `php artisan governance:runtime-status`

### TENANT_ISOLATION_VIOLATIONS (PLATFORM_EMERGENCY)

1. Immediately check: `php artisan governance:runtime-status`
2. Identify affected tenant from telemetry logs
3. Review `telemetry.tenant_violations.total` cache counter
4. Investigate cross-tenant data access in code
5. Only restore after root cause identified and fixed

### SECURITY_AUTH_FAILURE_BURST (CRITICAL)

1. Review security telemetry for source IP/tenant patterns
2. Check rate limiting configuration
3. Verify no credential stuffing attack in progress
4. Manual policy decisions on blocking

## Releasing a Deployment Freeze

A governance-applied deployment freeze stores a reason in cache:
```php
Cache::get('governance.deployment.frozen') // ['frozen_at', 'reason', 'auto_frozen']
```

Unfreeze via:
```php
app(RuntimeGovernanceKernel::class)->unfreezeDeployment();
// or directly:
Cache::forget('governance.deployment.frozen');
```

## Releasing Tenant Isolation

```php
app(TenantProtectionCoordinator::class)->restore($tenantId);
```

## Queue Quarantine Release

```php
app(DeadLetterGovernanceService::class)->releaseQuarantine('default');
```
**Always review failed jobs before releasing quarantine.**
