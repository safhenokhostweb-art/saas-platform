# Failed Deployment Recovery

**Phase 8.2 — Recovering from Interrupted or Failed Deployments**

---

## Symptoms of a Failed Deployment

| Symptom | Likely Cause |
|---|---|
| `deployment:status` shows stale lock | Deployment process died mid-flight |
| `/health/ready` returns 503 | Database/cache/storage failure post-deploy |
| `/health/release` returns 503 | Schema drift or missing manifest |
| Tenants stuck in maintenance | `tenant:up` was never called |
| Queue workers producing errors | Workers running stale code |

---

## Recovery Decision Tree

```
Is there an active deployment lock?
├── YES → Was it acquired in the last hour?
│         ├── YES → Wait for it to complete OR abort (see Abort below)
│         └── NO  → Force-unlock: deployment:unlock --all --force
│
└── NO → Is /health/ready returning 503?
          ├── YES → Check which subsystem failed (database/cache/storage)
          │         See: Health Failure Recovery below
          └── NO  → Is /health/release returning 503?
                    ├── YES → Schema drift: run migration:detect-drift
                    └── NO  → System is healthy — no recovery needed
```

---

## Procedure A — Stale Lock Recovery

A lock is stale when the deploying process died without releasing it.

```bash
# View the stale lock
php artisan deployment:status

# Force-release all locks
php artisan deployment:unlock --all --force

# Verify cleared
php artisan deployment:status
```

Locks also auto-expire after their TTL (default: 1 hour).

---

## Procedure B — Tenants Stuck in Maintenance

If a deployment failed after `tenant:down` but before `tenant:up`:

```bash
# Check which tenants are under maintenance
php artisan tenant:maintenance-status

# Bring each one back online
php artisan tenant:up {site_id}

# Or: if maintenance was linked to a deployment UUID
# it will be lifted automatically when rollback completes
```

---

## Procedure C — Health Failure Recovery

**Database failure:**
```bash
# Verify DB connection manually
php artisan tinker --execute="DB::select('SELECT 1')"
# Check .env DB_HOST, DB_PASSWORD
# Restart DB server if needed
php artisan config:clear && php artisan cache:clear
```

**Cache failure:**
```bash
php artisan cache:clear
php artisan config:clear
# Restart Redis if using Redis cache
```

**Storage failure:**
```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

---

## Procedure D — Schema Drift Recovery

```bash
php artisan migration:detect-drift

# If pending migrations (deployment was interrupted after code but before migrate):
php artisan migrate --force

# If orphaned migrations (file deleted but DB record exists):
# Manually delete the orphaned record from the migrations table
DB::table('migrations')->where('migration', 'orphaned_name')->delete();
```

---

## Procedure E — Stale Queue Workers

```bash
php artisan queue:restart
php artisan queue:validate-runtime

# If workers are still running old code after restart:
# Kill all worker processes and restart supervisor/queue daemon
# supervisorctl restart all
```

---

## Procedure F — Full Failed Deployment Recovery

When the deployment left the system in an unknown state:

```bash
# 1. Force-release all locks
php artisan deployment:unlock --all --force

# 2. Clear all caches
php artisan optimize:clear

# 3. Bring tenants online
php artisan tenant:maintenance-status
# For each stuck tenant:
php artisan tenant:up {site_id}

# 4. Check health
php artisan platform:health

# 5. If health fails → follow rollback-runbook.md
php artisan deployment:rollback --dry-run
php artisan deployment:rollback {previous_uuid}

# 6. Post-recovery validation
php artisan deployment:validate-governance
```

---

## Escalation Checklist

If automated recovery procedures fail:

- [ ] Restore database from pre-deployment backup
- [ ] `git reset --hard {pre-deployment-commit}`
- [ ] `composer install --no-dev`
- [ ] `php artisan migrate:fresh` (DANGER: only on staging)
- [ ] Contact infrastructure team for DB-level intervention

---

## Audit Trail

All recovery actions are logged in:
- `storage/logs/laravel.log` — application logs
- `deployment_logs` table — deployment phase logs
- `release_locks` table — lock acquisition/release history
