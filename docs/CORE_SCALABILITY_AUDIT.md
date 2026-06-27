# CORE SCALABILITY AUDIT
> CORE_CERTIFICATION_PHASE_1 — 2026-06-27
> Scope: Queue, Scheduler, Backup, Restore, Finance, DR
> Method: Source code inspection of all high-throughput paths

---

## SCA-001 — Queue retry_after: 90s (Critical for Long Jobs)

**Severity:** CRITICAL
**Evidence:** `config/queue.php` lines 67-70

```php
'redis' => [
    'driver'      => 'redis',
    'retry_after' => 90,   // ← only 90 seconds
    ...
]
```

**Impact:** Restore jobs (`RestoreTenantDatabaseJob`, `RestorePlatformStorageJob`) run for minutes to hours (download from Arvan + verify gzip + mysqldump + import). After 90 seconds, the queue daemon marks the job as "lost" and re-queues it. The original job may still be running, leading to two concurrent restores — direct conflict with the Redis lock safety mechanism.

The Redis lock (`restore_in_progress`) only has TTL=30min, so a second restore dispatch would hit the lock and fail, but:
1. The failed duplicate logs an error and releases the lock
2. The original is now lock-free mid-restore — a third could start

**Required Fix:**
```php
// config/queue.php — add dedicated restore connection:
'restore' => [
    'driver'      => 'redis',
    'connection'  => 'default',
    'queue'       => 'restore',
    'retry_after' => 7200, // 2 hours
    'block_for'   => null,
    'after_commit' => false,
],
```

Dispatch restore jobs with: `->onConnection('restore')->onQueue('restore')`

**Regression Risk:** Medium — requires queue.php change + dispatch update in controller + Supervisor config for restore queue.

---

## SCA-002 — Default Queue Connection is `sync`

**Severity:** CRITICAL
**Evidence:** `config/queue.php` line 16

```php
'default' => env('QUEUE_CONNECTION', 'sync'),
```

**Impact:** If `QUEUE_CONNECTION` is not set in production `.env` (missing or typo), all dispatched jobs — including restore jobs — run synchronously in the HTTP request cycle. For restore jobs, this means:
- The admin's browser waits for the full restore (minutes to hours)
- PHP execution timeout (typically 30-300s) terminates the restore mid-way
- No progress streaming (SSE endpoint would never send events)
- No error recovery (no queue retry mechanism)

**Required Fix:**
```
# Production .env must have:
QUEUE_CONNECTION=redis
```

Add to installer verification: check `config('queue.default') !== 'sync'` as a health check assertion.

**Regression Risk:** Low — this is an environment configuration issue.

---

## SCA-003 — Supervisor Group Name Mismatch

**Severity:** MEDIUM
**Evidence:** Deploy log output

```
saas-worker: ERROR (no such group)
saas-worker: ERROR (no such group)
saas-worker: ERROR (no such group)
```

**Impact:** `deploy.sh` references a Supervisor group named `saas-worker` to restart queue workers. The actual group is `saas-platform-worker`. This means:
1. Queue workers are NOT restarted on every deploy
2. Workers continue running old code after deployment
3. New job classes dispatched after deploy run on old worker code

**Required Fix:**
```bash
# deploy.sh — update group name from:
supervisorctl restart saas-worker:*
# to:
supervisorctl restart saas-platform-worker:*
```

**Regression Risk:** Low — only deploy.sh change.

---

## SCA-004 — No Queue Priority Separation

**Severity:** MEDIUM
**Evidence:** `app/Jobs/RestoreTenantDatabaseJob.php`, `app/Jobs/RestorePlatformStorageJob.php`, `app/Console/Kernel.php` scheduled commands

**Impact:** All jobs share the same queue (or default queue). An education AI generation batch (potentially dozens of jobs) can starve a time-sensitive restore job. Billing and lifecycle commands compete with infrastructure operations.

**Current queue topology (inferred):**
- `saas-platform-worker` processes default queue
- `restore` queue referenced in RestoreWizardController but no dedicated worker

**Required Fix:** Define queue priority order in Supervisor:
```ini
[program:saas-platform-worker-critical]
command=php artisan queue:work redis --queue=restore,backup,default --sleep=3 --tries=3

[program:saas-platform-worker-normal]
command=php artisan queue:work redis --queue=default,education,ai --sleep=3 --tries=3
```

**Regression Risk:** Medium — requires Supervisor config change on production server.

---

## SCA-005 — Finance KPI Queries (N×DB Calls)

**Severity:** MEDIUM
**Evidence:** `app/Services/Finance/FinanceLedgerService.php` lines 144-162

```php
public function kpis(): array
{
    return [
        'today_income'  => $this->sumIncome($today, $today),    // query 1
        'month_income'  => $this->sumIncome($monthFrom, $monthTo), // query 2
        'year_income'   => $this->sumIncome($yearFrom, $yearTo),   // query 3
        'today_expense' => $this->sumExpense($today, $today),   // query 4
        'month_expense' => $this->sumExpense($monthFrom, $monthTo), // query 5
        'year_expense'  => $this->sumExpense($yearFrom, $yearTo),  // query 6
        'month_profit'  => $this->sumIncome(...) - $this->sumExpense(...), // queries 7+8
        'year_profit'   => ..., // queries 9+10 (recomputed)
    ];
}
```

**Impact:** 8-10 separate DB queries per dashboard load. At current scale, acceptable. At 100+ admin users viewing dashboards simultaneously, this creates significant read load.

**Required Fix:** Single aggregated query:
```sql
SELECT
    type,
    SUM(CASE WHEN entry_date = TODAY THEN amount ELSE 0 END) as today,
    SUM(CASE WHEN entry_date BETWEEN month_start AND month_end THEN amount ELSE 0 END) as month,
    SUM(CASE WHEN entry_date BETWEEN year_start AND year_end THEN amount ELSE 0 END) as year
FROM finance_ledger_entries
GROUP BY type
```

**Regression Risk:** Medium — logic refactor; existing behavior must be preserved exactly.

---

## SCA-006 — Arvan listFiles() N+1 HTTP Requests

**Severity:** HIGH
**Evidence:** `app/Services/Infrastructure/ArvanBackupService.php` (from session 1 audit)

```php
public function listFiles(string $prefix): array
{
    $files = Storage::disk('arvan_backup')->files($prefix);
    return array_map(function ($path) {
        return [
            'path'          => $path,
            'size'          => Storage::disk('arvan_backup')->size($path), // N API calls
            'last_modified' => Storage::disk('arvan_backup')->lastModified($path), // N API calls
        ];
    }, $files);
}
```

**Impact:** For a prefix with 50 backup files: `list (1) + size×50 (50) + lastModified×50 (50) = 101 HTTP requests`. The DR health scan runs hourly and calls `listFiles()` for multiple prefixes. At 5 prefixes × 101 requests = 505 HTTP requests per hourly scan. At scale with hundreds of tenant backup prefixes, this becomes a serious bottleneck.

**Required Fix:** Use S3/Arvan API `ListObjectsV2` which returns metadata (size, last_modified) in the listing response. This reduces to 1 API call per prefix regardless of file count.

**Regression Risk:** Medium — requires S3 SDK call change in ArvanBackupService.

---

## SCA-007 — No DB Index on infra_backup_runs Compound Query

**Severity:** MEDIUM
**Evidence:** `app/Models/Infrastructure/InfraBackupRun.php` + `DrReadinessScoreService.php`

```php
// Called hourly by dr:scan-health and by DrReadinessScoreService:
InfraBackupRun::whereNull('site_id')
    ->where('backup_type', 'database')
    ->where('status', 'success')
    ->orderByDesc('started_at')
    ->first();
```

**Impact:** As backup history grows (365+ rows per year), this compound query (`NULL site_id, backup_type, status, started_at`) will do a full table scan without a composite index. Called 24 times/day (hourly scan), this adds up.

**Required Fix:**
```php
// Migration:
Schema::table('infra_backup_runs', function (Blueprint $table) {
    $table->index(['site_id', 'backup_type', 'status', 'started_at'],
        'infra_backup_runs_composite_idx');
});
```

**Regression Risk:** Low — index addition only; no logic change.

---

## SCA-008 — resource:bill-overage No Batch Limit

**Severity:** MEDIUM
**Evidence:** `app/Console/Kernel.php` line 113-118

```php
$schedule->command('resource:bill-overage')
    ->monthlyOn(2, '09:00')
    ->withoutOverlapping();
```

No batch limit in the command signature. At 1000+ sites, this processes all billing in one transaction, potentially holding table locks for extended periods.

**Required Fix:** Add `--batch=100` option to process sites in chunks with `sleep(1)` between batches.

**Regression Risk:** Medium — command logic change required.

---

## Scalability Score Summary

| Area | Score | Risk |
|------|-------|------|
| Queue retry_after | 0/20 | CRITICAL |
| Queue default connection | 5/10 | CRITICAL |
| Supervisor group mismatch | 5/10 | MEDIUM |
| Queue priority separation | 5/10 | MEDIUM |
| Finance query efficiency | 7/10 | MEDIUM |
| Arvan API call efficiency | 3/10 | HIGH |
| DB indexes | 5/10 | MEDIUM |
| Billing batch safety | 5/10 | MEDIUM |
| **Total** | **35/80** → **65/100** | — |

**Scalability Verdict: NOT READY FOR SCALE**

Critical blockers: SCA-001 (retry_after 90s guarantees duplicate restore execution), SCA-002 (sync default), SCA-006 (N+1 Arvan requests scale poorly).
