# PRODUCTION FIX PLAN
> Generated: 2026-06-15 | Production Server: 87.107.12.81:9011
> Status: AWAITING APPROVAL — DO NOT MODIFY CODE UNTIL APPROVED
>
> Rules:
> - All fixes classified Safe Fix / Risky Fix
> - Every Critical fix includes rollback procedure
> - Execution order respects dependencies
> - No env vars modified directly — only .env.example + deploy checklist

---

## EXECUTIVE SUMMARY

| Severity | Count | Safe Fix | Risky Fix |
|----------|-------|----------|-----------|
| CRITICAL | 2 | 1 | 1 |
| HIGH | 3 | 3 | 0 |
| MEDIUM | 4 | 4 | 0 |
| LOW | 3 | 3 | 0 |
| **Total** | **12** | **11** | **1** |

---

## CRITICAL FINDINGS

---

### PROD-001 — `/health/release` Information Disclosure

| Field | Detail |
|-------|--------|
| **Risk Level** | CRITICAL |
| **Classification** | Safe Fix |
| **Source** | TW-003 (KNOWN_ISSUES.md) |
| **Production Impact** | Any internet user can hit `GET /health/release` and receive: deployment UUID, git commit hash, platform version string, pending migration count. This reveals internal deployment state to attackers. |
| **Estimated Impact** | MEDIUM — reveals deployment internals; no RCE or data exfiltration directly. But git commit hash can be used to identify known CVEs in a specific commit. |
| **Rollback Complexity** | LOW — single route file change, can revert in <2 min |

**Files Affected:**
- `routes/web.php` — lines 80–96 (health prefix group)

**What it exposes today (confirmed by reading RuntimeHealthService.php:53–71):**
```json
{
  "coherent": true,
  "timestamp": "...",
  "latest_release": {
    "uuid": "deployment-uuid-here",
    "released_at": "...",
    "git_commit": "abc1234",
    "platform": "x.y.z"
  },
  "schema_drift": false,
  "pending_migrations": 0
}
```

**Database changes required:** NO
**Route changes required:** YES — add IP middleware or move behind admin auth
**Deployment required:** YES

**Proposed Fix Options (choose one at approval):**
- Option A (Safest): Add `ThrottleRequests` + restrict to server's own IP via a simple middleware check
- Option B (Simpler): Move `/health/release` behind `auth:sanctum` (load-balancer probes don't need it)
- Option C (Quickest): Drop `git_commit` and `platform` from the response — keep only `coherent` + `pending_migrations`

**Rollback Procedure:**
```
git revert <commit-sha> -- routes/web.php
.\deploy.ps1
```

---

### PROD-002 — MHC Migrations May Not Have Run on Production

| Field | Detail |
|-------|--------|
| **Risk Level** | CRITICAL |
| **Classification** | Risky Fix |
| **Source** | MHC Phase (2026-06-15) — 6 new migrations + 1 ALTER TABLE |
| **Production Impact** | If migrations did not run: `infra_incidents` table lacks `status`, `assigned_to_id`, `investigating_at`, `closed_at`, `customer_notified_at` columns → MonitorPlatformCommand and IncidentLifecycleService will throw Column Not Found exceptions every 2 minutes (scheduled command). |
| **Estimated Impact** | HIGH — scheduler crashes every 2 min, error logs fill up, platform monitoring blind |
| **Rollback Complexity** | HIGH — involves ALTER TABLE rollback on live DB |

**Files Affected (migrations):**
- `database/migrations/2026_06_15_200001_enhance_infra_incidents_for_health_center.php` — ALTER TABLE `infra_incidents` (adds 6 columns + updates severity `warning→medium`)
- `database/migrations/2026_06_15_200002_create_infra_incident_notes_table.php` — NEW TABLE
- `database/migrations/2026_06_15_200003_create_infra_platform_health_logs_table.php` — NEW TABLE
- `database/migrations/2026_06_15_200004_create_infra_backup_runs_table.php` — NEW TABLE
- `database/migrations/2026_06_15_200005_create_infra_scheduler_runs_table.php` — NEW TABLE
- `database/migrations/2026_06_15_200006_extend_infra_site_health_logs_table.php` — ALTER TABLE

**Critical Risk in migration 200001:**
- `UPDATE infra_incidents SET severity='medium' WHERE severity='warning'` — data mutation on live table
- If the migration ran partially and failed, some rows will be `medium` and some `warning`, making the column inconsistent

**Database changes required:** YES — 4 new tables + 2 ALTER TABLE
**Route changes required:** NO
**Deployment required:** YES (migration run only)

**Pre-flight Check (run before fix):**
```bash
# On server via SSH:
ssh -p 9011 root@87.107.12.81
cd /var/www/saas-platform
php artisan migrate:status | grep "2026_06_15"
```

**Rollback Procedure:**
```bash
# If migration 200001 fails midway:
php artisan migrate:rollback --step=1
# Verify:
php artisan migrate:status
```

**Safe Execution:**
```bash
# Take DB snapshot first (via existing BackupDatabaseCommand):
php artisan db:backup
# Then migrate:
php artisan migrate --force 2>&1 | tee /tmp/migrate_20260615.log
```

---

## HIGH FINDINGS

---

### PROD-003 — PlatformSettings: Raw PHP File Ops (Path Traversal Risk)

| Field | Detail |
|-------|--------|
| **Risk Level** | HIGH |
| **Classification** | Safe Fix |
| **Source** | F2.1 (INFRASTRUCTURE_READINESS_REPORT.md) |
| **Production Impact** | `app/Helpers/PlatformSettings.php` uses `mkdir()` + `$file->move()` for logo/file uploads instead of `Storage::` facade. On the current single-VPS this works, but the upload directory is created with `0755` permissions by raw PHP — path manipulation in filenames could write outside intended directory. |
| **Estimated Impact** | MEDIUM on current VPS; HIGH if storage is migrated to S3/MinIO later |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `app/Helpers/PlatformSettings.php` — lines ~435–440

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

**Rollback Procedure:**
```
git revert <commit-sha> -- app/Helpers/PlatformSettings.php
.\deploy.ps1
```

---

### PROD-004 — Raw `mkdir()` Calls in Controllers (Path Safety)

| Field | Detail |
|-------|--------|
| **Risk Level** | HIGH |
| **Classification** | Safe Fix |
| **Source** | F2.8, F2.9 (INFRASTRUCTURE_READINESS_REPORT.md) |
| **Production Impact** | `AppReleaseController` and `MobileTicketController` call `mkdir()` directly. No path sanitization. If release filename or ticket attachment filename contains `../` sequences, directories can be created outside the intended path. |
| **Estimated Impact** | MEDIUM — requires authenticated admin access for AppRelease; ticket attachment requires auth too |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `app/Http/Controllers/Admin/AppReleaseController.php` — line ~64–65
- `app/Http/Controllers/Api/Mobile/MobileTicketController.php` — line ~218
- `app/Services/SecurityValidationService.php` — line ~60 (hardcoded `storage_path('app/public/ticket-attachments')`)

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

**Rollback Procedure:**
```
git revert <commit-sha> -- app/Http/Controllers/Admin/AppReleaseController.php app/Http/Controllers/Api/Mobile/MobileTicketController.php app/Services/SecurityValidationService.php
.\deploy.ps1
```

---

### PROD-005 — CSV Export Services Use Hardcoded `fopen()` Paths

| Field | Detail |
|-------|--------|
| **Risk Level** | HIGH |
| **Classification** | Safe Fix |
| **Source** | F2.3, F2.4, F2.6 (INFRASTRUCTURE_READINESS_REPORT.md) |
| **Production Impact** | CSV exports write to `app/exports/` using raw `fopen()`. On the current VPS this works if the directory exists. If the directory is missing (first deploy, fresh server), the export will throw a PHP warning and the download will be empty. Not a security issue but a reliability issue on production. |
| **Estimated Impact** | LOW on current VPS (directory likely exists); HIGH on clean deployment |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `app/Services/CustomerService.php` — lines ~181–187
- `app/Http/Controllers/Admin/UserController.php` — lines ~230–235 and ~275–284
- `app/Services/UserService.php` — lines ~789–795

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

**Rollback Procedure:**
```
git revert <commit-sha> -- app/Services/CustomerService.php app/Http/Controllers/Admin/UserController.php app/Services/UserService.php
.\deploy.ps1
```

---

## MEDIUM FINDINGS

---

### PROD-006 — No DB Indexes on `site_id` Columns

| Field | Detail |
|-------|--------|
| **Risk Level** | MEDIUM |
| **Classification** | Safe Fix |
| **Source** | KI-003 / OPF-004 (KNOWN_ISSUES.md, AUDIT_HISTORY.md) |
| **Production Impact** | All content tables use `site_id` as primary tenant filter. Without indexes, every tenant-scoped query does a full table scan. At current tenant count: acceptable. At 50+ tenants with significant data: queries slow to seconds. |
| **Estimated Impact** | LOW now; HIGH at scale |
| **Rollback Complexity** | LOW — indexes are purely additive, rollback = drop index |

**Files Affected:**
- NEW migration file (to be created): adds indexes on `site_id` across all content tables

**Key tables needing indexes (estimated from codebase):**
`edu_articles`, `edu_walkthroughs`, `edu_learning_paths`, `edu_recommendations`, `edu_feature_usage`, `tickets`, `ticket_replies`, `form_entries`, `posts`, `products`, `orders`, `crm_contacts`, `crm_deals`, `installment_orders`, `content_translations`, `notification_center_logs`

**Database changes required:** YES — new migration (index only, no data changes)
**Route changes required:** NO
**Deployment required:** YES

**Rollback Procedure:**
```bash
php artisan migrate:rollback --step=1
# Or drop individual indexes:
# ALTER TABLE edu_articles DROP INDEX idx_edu_articles_site_id;
```

---

### PROD-007 — Storage Disk Hardcoding (`Storage::disk('public')`)

| Field | Detail |
|-------|--------|
| **Risk Level** | MEDIUM |
| **Classification** | Safe Fix |
| **Source** | F3.1, F3.2 (INFRASTRUCTURE_READINESS_REPORT.md) |
| **Production Impact** | 9+ locations hardcode `Storage::disk('public')` or `Storage::disk('local')`. If production is migrated to S3/ArvanCloud Object Storage, uploads will silently write to local disk instead. Current VPS: not a problem. Future S3 migration: silent data loss. |
| **Estimated Impact** | LOW on current single-VPS; CRITICAL if S3 migration happens without this fix |
| **Rollback Complexity** | LOW |

**Files Affected:** (9+ files across controllers and services — full list requires grep scan)
```
grep -r "Storage::disk('public')\|Storage::disk('local')" app/
```

**Fix:** Replace with `Storage::disk(config('filesystems.upload_disk'))` — `UPLOAD_DISK` env var already exists in `.env.example`

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

**Rollback Procedure:**
```
git revert <commit-sha>
.\deploy.ps1
```

---

### PROD-008 — Sanctum Stateful Domains Has Localhost Fallback

| Field | Detail |
|-------|--------|
| **Risk Level** | MEDIUM |
| **Classification** | Safe Fix |
| **Source** | F1.4 (INFRASTRUCTURE_READINESS_REPORT.md) |
| **Production Impact** | `config/sanctum.php` fallback includes `localhost` and `127.0.0.1` in stateful domains. In production, a request from `localhost` (if reachable) could receive a session cookie. If any admin tooling runs on localhost with browser access, this is a session fixation vector. Low risk on VPS with no browser on server. |
| **Estimated Impact** | LOW — requires local server access |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `config/sanctum.php` — lines 18–22
- `.env.example` — already has correct `SANCTUM_STATEFUL_DOMAINS=panel.safhenokhost.ir,safhenokhost.ir`

**Fix:** Ensure production `.env` has `SANCTUM_STATEFUL_DOMAINS` set (no code change needed; verify on server)

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** NO (env-only fix on server, verify with `php artisan config:cache`)

**Rollback Procedure:** N/A — env-only change, reverse by updating .env value

---

### PROD-009 — Circuit Breaker State Uses File Cache (Multi-Node Risk)

| Field | Detail |
|-------|--------|
| **Risk Level** | MEDIUM |
| **Classification** | Safe Fix |
| **Source** | KI-004 (KNOWN_ISSUES.md) |
| **Production Impact** | Circuit breaker state and Runtime Governance Kernel cache can use the file driver if `CACHE_DRIVER` is misconfigured on production. On the current single-VPS with `CACHE_DRIVER=redis` in `.env.example` this is NOT an issue — but if Redis goes down, Laravel auto-fallback to file cache means circuit breakers won't coordinate. |
| **Estimated Impact** | LOW on single-VPS with Redis; HIGH on multi-node |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `app/Services/Infrastructure/CacheManager.php` — add startup assertion
- `config/cache.php` — add `CACHE_DRIVER` validation in `AppServiceProvider`

**Fix:** Add a boot-time check in `AppServiceProvider` that throws in production if `CACHE_DRIVER=file`

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

**Rollback Procedure:**
```
git revert <commit-sha> -- app/Providers/AppServiceProvider.php
.\deploy.ps1
```

---

## LOW FINDINGS

---

### PROD-010 — Tailwind v4 Lightning CSS Shadow-Var Warnings

| Field | Detail |
|-------|--------|
| **Risk Level** | LOW |
| **Classification** | Safe Fix |
| **Source** | OPF-002 (AUDIT_HISTORY.md) |
| **Production Impact** | `npm run build` exits 0 but emits Lightning CSS optimizer notices for remaining `shadow-[var(--ui-shadow-*)]` usages not yet converted. No user-visible impact. CSS is compiled and served correctly. |
| **Estimated Impact** | NONE — cosmetic build noise only |
| **Rollback Complexity** | LOW |

**Files Affected:** Remaining CSS files with `shadow-[var(--ui-shadow-*)]` pattern (need grep to enumerate)

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES (build + deploy)

---

### PROD-011 — KB Routes Registered but Absent from Admin Menu

| Field | Detail |
|-------|--------|
| **Risk Level** | LOW |
| **Classification** | Safe Fix |
| **Source** | ARC-002 (AUDIT_HISTORY.md) |
| **Production Impact** | `admin.kb.*` routes are registered and the `KnowledgeBaseController` is live. The feature works but is invisible in the sidebar. Admins must know the direct URL. No security impact — it's behind `auth + admin` middleware. |
| **Estimated Impact** | UX gap only — no production breakage |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `app/Helpers/AdminMenu.php` — add `kb` key

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

---

### PROD-012 — Dead Code: Orphaned Form Controllers

| Field | Detail |
|-------|--------|
| **Risk Level** | LOW |
| **Classification** | Safe Fix |
| **Source** | ARC-003 (AUDIT_HISTORY.md) |
| **Production Impact** | `FormFieldController`, `FormSiteController`, `FormSubmissionController` exist in `app/Http/Controllers/Admin/` with zero registered routes. They cannot be called. No security risk. Dead code only. |
| **Estimated Impact** | NONE — unreachable code |
| **Rollback Complexity** | LOW |

**Files Affected:**
- `app/Http/Controllers/Admin/FormFieldController.php` — DELETE
- `app/Http/Controllers/Admin/FormSiteController.php` — DELETE
- `app/Http/Controllers/Admin/FormSubmissionController.php` — DELETE

**Database changes required:** NO
**Route changes required:** NO
**Deployment required:** YES

---

## RECOMMENDED EXECUTION ORDER

| Step | Finding | Rationale | Risk |
|------|---------|-----------|------|
| 1 | **PROD-002** — Migration verification + run | Schema drift causes scheduler crashes every 2 min | RISKY — take DB backup first |
| 2 | **PROD-001** — /health/release protection | Security: information disclosure live right now | SAFE |
| 3 | **PROD-003** — PlatformSettings file ops | Security: path traversal in upload flow | SAFE |
| 4 | **PROD-004** — mkdir() in controllers | Security: path traversal in release/ticket attach | SAFE |
| 5 | **PROD-005** — CSV export fopen() | Reliability: export breaks on clean deploy | SAFE |
| 6 | **PROD-006** — site_id indexes | Performance: additive-only migration, zero downtime | SAFE |
| 7 | **PROD-007** — Storage disk abstraction | Future-proofing: no current breakage | SAFE |
| 8 | **PROD-008** — Sanctum domains verification | Security: env-only check on server | SAFE |
| 9 | **PROD-009** — Cache driver boot assertion | Operational: prevents silent misconfiguration | SAFE |
| 10 | **PROD-010** — Tailwind CSS warnings | Cosmetic: bundle with next frontend change | SAFE |
| 11 | **PROD-011** — KB menu entry | UX gap: low priority | SAFE |
| 12 | **PROD-012** — Dead code removal | Cleanup: bundle with next code pass | SAFE |

---

## PRE-FIX CHECKLIST (Must Complete Before Step 1)

- [ ] SSH to production server and run `php artisan migrate:status | grep 2026_06_15` — confirm migration state
- [ ] Run `php artisan db:backup` on server to create pre-fix snapshot
- [ ] Note the backup file location from output
- [ ] Confirm `CACHE_DRIVER=redis` and `SESSION_DRIVER=redis` are set in production `.env`
- [ ] Confirm `SANCTUM_STATEFUL_DOMAINS` is set correctly in production `.env`
- [ ] Confirm `QUEUE_CONNECTION=redis` is set in production `.env`

---

## ITEMS EXPLICITLY NOT IN SCOPE

These were found in documentation but are **not production-breaking on current single-VPS deployment** and should be deferred:

| Item | Reason to Defer |
|------|----------------|
| F1.5 — SQS prefix placeholder | SQS not used; Redis queue is primary |
| F3.3 — 'private' disk | Correctly defined, low risk |
| KI-004 multi-node Redis | Single VPS, not multi-node yet |
| TD-003 — Forms 3,253 violations | Visual debt only, not breaking |
| TD-004 — Alpine template exceptions | Documented architectural exception |
| TD-005 — Firebase FCM | Optional feature, not blocking |
| M-01 — education:validate missing EDU-17 | LOW debt, closed epic |

---

*This plan was generated with zero code changes. All findings sourced from:*
- `docs/project-memory/KNOWN_ISSUES.md`
- `docs/project-memory/AUDIT_HISTORY.md`
- `docs/infrastructure/INFRASTRUCTURE_READINESS_REPORT.md`
- `docs/infrastructure/MONITORING_CENTER_AUDIT.md`
- Direct code inspection of `routes/web.php`, `RuntimeHealthService.php`, `deploy.ps1`, migration files
