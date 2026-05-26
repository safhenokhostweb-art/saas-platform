# Zero-Downtime Deployment Flow

**Phase 8.2 — Per-Tenant Isolation Strategy**

---

## Concept

Laravel's built-in `php artisan down` is a global maintenance gate — it takes ALL tenants offline simultaneously.

This is unacceptable for a multi-tenant SaaS.

**Phase 8.2 solution:** Use `TenantMaintenanceManager` to put only the tenants that are affected by the deployment under maintenance, while all other tenants remain online.

---

## Flow Diagram

```
[Start Deployment]
        │
        ▼
[Identify affected tenants]
        │
        ▼
[tenant:down {id} for each affected tenant]   ← only these go offline
        │                                         ← others stay online
        ▼
[Acquire deployment lock]
        │
        ▼
[git pull + composer install]
        │
        ▼
[php artisan migrate --force]  ← tenant-scoped tables only affected tenants
        │
        ▼
[npm run build + vendor:publish]
        │
        ▼
[cache:clear + optimize]
        │
        ▼
[queue:restart]
        │
        ▼
[platform:health ready]
        │
        ├── PASS ──▶ [tenant:up {id} for each affected tenant]
        │                    │
        │                    ▼
        │             [deployment:unlock]
        │                    │
        │                    ▼
        │             [Deployment complete ✓]
        │
        └── FAIL ──▶ [See rollback-runbook.md]
```

---

## Determining Affected Tenants

A tenant is "affected" by a deployment if:
1. The deployment includes migrations that touch their tables
2. A module they have enabled is being updated

Use `migration:validate-safety` output to identify tenant-sensitive migrations.

---

## Commands

```bash
# Put specific tenants into maintenance
php artisan tenant:down 42 --message="به‌روزرسانی ماژول فرم‌ها"
php artisan tenant:down 55 --message="به‌روزرسانی ماژول فرم‌ها"

# Check which tenants are under maintenance
php artisan tenant:maintenance-status

# After successful deployment
php artisan tenant:up 42
php artisan tenant:up 55
```

---

## What Bypasses Maintenance Mode

By default, these always bypass tenant maintenance:
1. Platform admin users (`is_admin = true`)
2. Health check endpoints (`/health/*`)
3. Deployment orchestrator internal calls

---

## When to Use Global Maintenance Mode

Use `php artisan down` (global) only when:
- Schema changes affect ALL tenants simultaneously
- Platform-wide infrastructure changes (Redis, DB server)
- Security patches requiring full restart

In all other cases, use per-tenant maintenance.
