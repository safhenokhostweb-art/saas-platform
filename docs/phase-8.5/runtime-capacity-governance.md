# Runtime Capacity Governance — Phase 8.5

## Overview

The capacity governance stack forecasts platform saturation, estimates resource exhaustion windows, and recommends scaling actions before problems occur.

## Forecast Models

### Queue Saturation Forecast
- Uses current depth + estimated growth rate (5% of current depth per hour if depth > 100)
- `hours_to_critical = (critical_threshold - current_depth) / growth_rate_per_hour`

### Storage Growth Forecast
- Estimates daily growth from telemetry event count (~2KB per event)
- 30-day projection in GB

### Tenant Expansion Impact
- `memory_increase_est_pct = (additional / current) * 100 * 0.8`
- `queue_pressure_est = additional_tenants * 50 jobs`

## Pressure Prediction

`RuntimePressurePredictor` combines:
- Memory utilization % vs thresholds
- Queue saturation forecast risk
- Platform health score

Output pressure levels: `normal` / `elevated` / `high` / `critical`

## Scaling Advisor Urgency

| Urgency | Trigger |
|---------|---------|
| `immediate` | pressure_level = critical |
| `soon` | pressure_level = high |
| `planned` | pressure_level = elevated |
| `none` | pressure_level = normal |

## Artisan

```bash
# Forecast capacity for next 24 hours
php artisan scalability:forecast-capacity --hours=24

# Simulate adding 50 tenants
php artisan scalability:forecast-capacity --expand=50

# JSON output for automation
php artisan scalability:forecast-capacity --json
```

## Configurable Thresholds

All thresholds are runtime-configurable via `CapacityThresholdManager`:
```php
app(CapacityThresholdManager::class)->set('queue_depth_critical', 2000);
```
