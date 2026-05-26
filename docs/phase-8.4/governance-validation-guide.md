# Governance Validation Guide — Phase 8.4

## Running Validation

```bash
# Standard validation (warnings allowed)
php artisan governance:validate-runtime

# Strict mode — warnings treated as failures
php artisan governance:validate-runtime --strict

# JSON output (for CI integration)
php artisan governance:validate-runtime --json
```

## Validation Dimensions

| Dimension | What is Checked |
|-----------|----------------|
| `state_machine` | Current state is one of 4 valid states |
| `policy_registry` | At least one policy registered and enabled |
| `circuit_breakers` | CircuitBreakerManager can instantiate circuits |
| `recovery_engine` | Idempotent strategies correctly classified |
| `telemetry_bridge` | TelemetryManager bound in service container |
| `cache_backend` | Cache read/write cycle completes successfully |
| `db_tables` | governance_incidents, recovery_logs, telemetry_events exist |
| `tenant_isolation` | Tenant degradation level returns valid range |
| `policy_evaluator` | PolicyConditionEvaluator evaluates correctly |
| `stability_index` | RuntimeStabilityIndexCalculator returns 0-100 |

## Exit Codes

- `0` — all checks passed (strict: no warnings either)
- `1` — one or more checks failed

## CI Integration

```yaml
- name: Validate governance runtime
  run: php artisan governance:validate-runtime --strict --json
```

## Common Failures and Fixes

**`db_tables` warning**: Run `php artisan migrate` to create governance tables.

**`policy_registry` warning**: The registry bootstrap runs in `RuntimeGovernanceKernel` constructor. Ensure the kernel is resolved from the container at least once before validation.

**`cache_backend` fail**: Check `CACHE_DRIVER` config and that the cache store is reachable.

**`telemetry_bridge` fail**: Verify `CoreUtilsServiceProvider` is registered in `config/app.php`.
