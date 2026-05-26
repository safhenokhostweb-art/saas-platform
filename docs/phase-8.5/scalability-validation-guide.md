# Scalability Validation Guide — Phase 8.5

## Commands

```bash
# Full scalability validation (8 dimensions)
php artisan scalability:validate

# Strict mode — warnings are failures
php artisan scalability:validate --strict

# Load simulation
php artisan scalability:simulate-load --tenants=1,2,3

# Tenant isolation test
php artisan scalability:test-isolation --tenant=1 --victims=2,3

# Capacity forecast
php artisan scalability:forecast-capacity --hours=24 --expand=10
```

## Validation Dimensions

| Dimension | What is Checked |
|-----------|----------------|
| `tenant_isolation` | Platform isolation health; no emergency quota states |
| `noisy_neighbor` | Fairness score ≥ 70 |
| `queue_scalability` | Queue coordination; no critical recommendations |
| `cache_isolation` | Cross-tenant cache key detection works |
| `storage_isolation` | Cross-tenant path detection works |
| `scheduling_fairness` | Deferred queue < 200 pending |
| `pressure_predictor` | Predictor runs; level not critical |
| `node_coordination` | Node coordinator operational |

## CI Integration

```yaml
- name: Scalability validation
  run: php artisan scalability:validate --strict --json
```

## Simulation Options

### Load Simulation
```bash
php artisan scalability:simulate-load \
  --tenants=1,2,3 \
  --pending=500 \
  --failed=10
```

### Isolation Test
```bash
# Test tenant 1 as noisy neighbor, tenants 2-5 as victims
php artisan scalability:test-isolation --tenant=1 --victims=2,3,4,5
```

## Score Thresholds

| Score | Grade | Meaning |
|-------|-------|---------|
| 90–100 | A+ | Hyperscale ready |
| 80–89 | A | Enterprise ready |
| 70–79 | B | Production ready |
| 60–69 | C | Caution — monitor |
| 50–59 | D | Scaling work needed |
| < 50 | F | Not ready |
