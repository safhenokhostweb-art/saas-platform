# Rollback Runbook

**Phase 8.2 — Production Rollback Procedures**

---

## When to Roll Back

- Health check fails after deployment (`platform:health ready` → 503)
- Critical bug discovered within first 30 minutes of release
- Failed deployment that left the system in an inconsistent state
- Migration applied that is causing data issues

---

## Step 1 — Assess the Situation

```bash
php artisan deployment:status
php artisan platform:health --json
php artisan migration:detect-drift
```

Identify the last known-good release UUID from `deployment:status`.

---

## Step 2 — Dry Run First

```bash
php artisan deployment:rollback {uuid} --dry-run
```

Review the output:
- Which migration batches need to be rolled back
- Which module versions will change
- What next steps are required

---

## Step 3 — Isolate Affected Tenants

```bash
php artisan tenant:down {site_id} --message="در حال بازیابی سیستم"
```

---

## Step 4 — Acquire Rollback Lock

```bash
php artisan deployment:lock --ttl=1800
```

---

## Step 5 — Revert Code

```bash
git checkout {previous-commit-hash}
composer install --no-dev --optimize-autoloader
```

---

## Step 6 — Roll Back Migrations

```bash
# Determine how many batches to roll back (from dry-run output)
php artisan migrate:rollback --step=N --force
```

**WARNING:** Only possible if `down()` methods are implemented. Irreversible migrations cannot be rolled back — restore from database backup instead.

---

## Step 7 — Rebuild Assets

```bash
npm ci && npm run build
php artisan vendor:publish --tag=forms-assets --force
php artisan vendor:publish --tag=tickets-assets --force
```

---

## Step 8 — Clear All Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear
```

---

## Step 9 — Restart Queues

```bash
php artisan queue:restart
```

---

## Step 10 — Re-check Health

```bash
php artisan platform:health --fail-on-warn
```

---

## Step 11 — Restore Tenants

```bash
php artisan tenant:up {site_id}
```

---

## Step 12 — Release Lock and Audit

```bash
php artisan deployment:unlock --force
php artisan deployment:validate-governance
```

---

## Irreversible Migrations

If a migration's `down()` method is empty or throws, manual database intervention is required:

1. Restore from pre-deployment database backup
2. Run `php artisan migrate:fresh` (DANGER: loses all data — staging only)
3. Or manually reverse the schema change via SQL

Always keep a database dump immediately before applying migrations.

---

## Rollback Time Budget

| Scenario | Expected RTO |
|---|---|
| Code revert only (no migrations) | < 10 minutes |
| Code + 1-2 migration batches | 10–30 minutes |
| Code + DB backup restore | 30–90 minutes |
| Multi-tenant isolation restore | < 5 minutes per tenant |
