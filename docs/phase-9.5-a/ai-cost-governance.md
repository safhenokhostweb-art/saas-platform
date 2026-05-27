# AI Cost Governance — Phase 9.5-A

## Architecture

Cost governance operates at three levels:
1. **Usage quotas** (requests/day) — `TenantAiQuotaManager`
2. **Rate limits** (requests/minute) — `AiRateLimitCoordinator`
3. **Budget caps** (USD/day) — `AiRequestBudgetManager`

All three are checked by `AiUsageGovernor` before any AI request executes.

---

## Per-Tenant Quotas

```php
$quotaManager->setTenantPlan($tenantId, 'pro');
// Plans: free(10), basic(100), pro(500), enterprise(2000), unlimited(-1)

$quotaManager->setCustomLimit($tenantId, 750);  // override for specific tenant
```

Check:
```php
$result = $quotaManager->check($tenantId);
// ['allowed' => true, 'used' => 23, 'limit' => 100, 'remaining' => 77, 'pct' => 23.0]
```

---

## Rate Limiting

Three buckets — all must pass:
1. Global RPM (`AI_GLOBAL_RPM`, default 60)
2. Per-tenant RPM (`AI_TENANT_RPM`, default 20)
3. Per-user RPM (`AI_USER_RPM`, default 10)

Burst factor: `AI_BURST_FACTOR=1.5` allows short burst up to 1.5× the RPM limit.

---

## Cost Estimation

Approximate USD costs per 1K tokens (configurable in `config/ai.php`):

| Model | Input | Output |
|---|---|---|
| gpt-4o-mini | $0.00015 | $0.00060 |
| gpt-4o | $0.00500 | $0.01500 |
| claude-haiku-4-5-20251001 | $0.00025 | $0.00125 |
| claude-sonnet-4-6 | $0.00300 | $0.01500 |

```php
$cost = $costEstimator->estimateCost('claude', 'claude-haiku-4-5-20251001', 500, 300);
// ~$0.0005
```

---

## Daily Budget Caps

```php
$budgetManager->setDailyBudget($tenantId, 1.00);  // $1.00/day limit

$status = $budgetManager->getBudgetStatus($tenantId);
// ['has_budget' => true, 'budget' => 1.0, 'spent' => 0.43, 'remaining' => 0.57, 'pct_used' => 43, 'status' => 'ok']
```

Status values: `ok`, `warning` (≥80%), `exhausted` (≥100%)

---

## Monitoring Commands

```bash
php artisan ai:quota-report                    # System overview
php artisan ai:quota-report tenant-123 --days=30
php artisan ai:quota-report --json
```

---

## Cost Report

Daily cost tracking is stored per tenant per day. Monthly rollup is available:
```php
$costEstimator->getTenantTotalCost($tenantId, 30);  // Total USD for 30 days
$costEstimator->getTenantCost($tenantId, 30);        // Day-by-day breakdown
```
