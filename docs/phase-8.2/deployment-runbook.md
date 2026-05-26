# Deployment Runbook

**Phase 8.2 — Production Deployment & Release Safety**  
**Platform:** SaaS Platform (Laravel 10, multi-tenant)

---

## Prerequisites

Before any deployment:

- [ ] All tests pass on feature branch
- [ ] `php artisan migration:validate-safety` passes or `--force-production` approved
- [ ] `php artisan package:validate-distribution` passes
- [ ] `php artisan deployment:validate-governance` score ≥ 80
- [ ] No active deployment lock: `php artisan deployment:status`

---

## Standard Deployment Order

### Step 1 — Pre-flight

```bash
php artisan deployment:status
php artisan migration:validate-safety --fail-on-warning
php artisan migration:detect-drift --fail-on-drift
php artisan platform:health ready
```

### Step 2 — Drain Queue

```bash
php artisan queue:drain-safe --timeout=300 --stamp
```

### Step 3 — Acquire Deployment Lock

```bash
php artisan deployment:lock --ttl=3600
```

### Step 4 — Enable Maintenance (per affected tenant if needed)

```bash
# Full platform maintenance (Laravel native):
php artisan down

# OR per-tenant only (preferred for zero-downtime):
php artisan tenant:down {site_id} --message="در حال به‌روزرسانی"
```

### Step 5 — Pull New Code

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

### Step 6 — Run Migrations

```bash
php artisan migrate --force
```

### Step 7 — Build Assets

```bash
npm ci && npm run build
php artisan vendor:publish --tag=forms-assets --force
php artisan vendor:publish --tag=tickets-assets --force
```

### Step 8 — Rebuild Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Step 9 — Restart Queues

```bash
php artisan queue:restart
```

### Step 10 — Disable Maintenance

```bash
# Per-tenant:
php artisan tenant:up {site_id}
# Or global:
php artisan up
```

### Step 11 — Health Check

```bash
php artisan platform:health --fail-on-warn
curl https://your-domain.com/health/ready
```

### Step 12 — Release Manifest

```bash
# Create deployment manifest (or done automatically by DeploymentOrchestrator)
# Verify latest manifest:
php artisan deployment:status
```

### Step 13 — Release Lock

```bash
php artisan deployment:unlock --force
```

---

## Emergency Abort

If anything fails before step 10:

```bash
php artisan deployment:unlock --all --force
php artisan tenant:up {site_id}   # or: php artisan up
# See rollback-runbook.md for full recovery
```

---

## Deployment Commands Reference

| Command | Purpose |
|---|---|
| `deployment:lock` | Acquire exclusive deployment lock |
| `deployment:unlock {uuid}` | Release a specific lock |
| `deployment:unlock --all` | Emergency: release all locks |
| `deployment:status` | Show active lock + latest release |
| `deployment:rollback {uuid}` | Roll back to a previous release |
| `deployment:validate-governance` | Full governance score |
| `migration:validate-safety` | Detect dangerous migrations |
| `migration:detect-drift` | Detect schema/migration inconsistencies |
| `tenant:down {id}` | Isolate one tenant into maintenance |
| `tenant:up {id}` | Bring tenant back online |
| `tenant:maintenance-status` | List all tenants under maintenance |
| `queue:drain-safe` | Graceful queue drain before deploy |
| `queue:validate-runtime` | Validate queue/release coherence |
| `platform:health` | Full runtime health report |
