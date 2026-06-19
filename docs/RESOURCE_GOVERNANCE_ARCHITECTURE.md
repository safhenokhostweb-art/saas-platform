# Resource Governance Architecture

> Generated: 2026-06-19  
> Phase: LIFECYCLE-2 — Complete

---

## Overview

Every customer site hosted on the SaaS platform consumes server resources from a shared VPS infrastructure. Resource governance tracks usage per site, enforces configurable limits, alerts the platform admin when thresholds are exceeded, and produces billing data for storage/bandwidth overage fees.

This system integrates directly with LIFECYCLE-1's state machine: when a site is **archived**, its active resources are automatically released and only the archive package size is retained for billing.

---

## Tracked Resources

| Resource | Source | Unit |
|---|---|---|
| Storage (uploaded files) | Estimated from customer-site DB content counts | Bytes |
| Database size | `information_schema.tables` via dynamic DB connection | Bytes |
| Backup size | `infra_backup_runs.file_size_mb` sum | Bytes |
| Archive size | Actual ZIP file size on disk (`sites.archive_path`) | Bytes |
| Monthly traffic | `site_bandwidth_usage` table (cumulative counter) | Bytes |
| Request count | `site_bandwidth_usage.request_count` | Count |
| File count | Row count across key customer-site tables | Count |
| CPU weight | Plan-derived allocation (1–100 scale) | Relative |
| RAM weight | Plan-derived allocation (1–100 scale) | Relative |

---

## Database Schema

### `site_resource_usage` (one row per site — current snapshot)

```sql
id, site_id (UNIQUE FK→sites),
storage_bytes, db_bytes, backup_bytes, archive_bytes, file_count,
bandwidth_bytes_monthly, request_count_monthly,
cpu_weight (1–100), ram_weight (1–100),
alerted_80 BOOL, alerted_95 BOOL,   -- prevent repeated alerts
calculated_at TIMESTAMP,
timestamps
```

### `site_resource_limits` (per-site override or plan-level defaults)

```sql
id,
site_id UNIQUE NULLABLE (NULL = plan default),
plan_name VARCHAR NULLABLE,
storage_limit_bytes     (default 1 GB),
bandwidth_limit_bytes   (default 10 GB/month),
archive_retention_days  (default 365),
backup_retention_days   (default 30),
cpu_weight, ram_weight  (default 10),
storage_overage_fee_per_gb    (Rials, 0 = not billed),
bandwidth_overage_fee_per_gb  (Rials, 0 = not billed),
archive_retention_fee         (Rials),
restore_fee                   (Rials),
timestamps
```

**Lookup order for limits:**
1. Per-site row (`site_id = X`) — highest priority
2. Plan-level row (`site_id IS NULL, plan_name = 'basic'`)
3. `config/resource_governance.php` defaults — fallback

### `site_bandwidth_usage` (monthly time-series per site)

```sql
id, site_id FK→sites,
year SMALLINT, month TINYINT,
bytes BIGINT, request_count INT,
UNIQUE(site_id, year, month)
```

### `site_storage_snapshots` (daily history for trending)

```sql
id, site_id FK→sites,
snapshot_date DATE,
storage_bytes, db_bytes, backup_bytes, archive_bytes, file_count,
UNIQUE(site_id, snapshot_date)
```

---

## Service Layer

### `SiteResourceCalculatorService`

| Method | Purpose |
|---|---|
| `calculate(Site, dryRun)` | Full metric collection + persist + snapshot |
| `getDatabaseBytes(Site)` | `information_schema.tables` via dynamic DB conn |
| `getStorageBytes(Site)` | Estimates from content record counts |
| `getArchiveBytes(Site)` | `Storage::size()` of `sites.archive_path` |
| `getBackupBytes(Site)` | Sum of `infra_backup_runs.file_size_mb` |
| `getFileCount(Site)` | Count rows in posts/products/courses tables |
| `releaseActiveResources(Site)` | Called on archive — zeroes all except archive_bytes |
| `pushSummaryToCustomerSite(Site, metrics)` | Writes `site_resource_summary` JSON to customer settings |

### `SiteResourceGovernorService`

| Method | Purpose |
|---|---|
| `getLimits(Site)` | Returns effective SiteResourceLimit |
| `getUsage(Site)` | Returns SiteResourceUsage (or new unsaved instance) |
| `storagePercent(Site)` | % of storage quota used |
| `bandwidthPercent(Site)` | % of bandwidth quota used |
| `calculateOverageFees(Site)` | Returns array of line items (storage, bandwidth, archive) |
| `totalOverageFee(Site)` | Sum of all overage fee line items |
| `sitesAboveStoragePercent(pct)` | Filter by usage threshold |
| `sitesOrderedByStorage(limit)` | Descending storage sort |
| `oversizedArchiveSites(threshold)` | Archives > threshold bytes |
| `inactiveSites()` | Sites with no calculation in 48h |

### `SiteResourceAlertService`

| Event | Threshold | Channel |
|---|---|---|
| `alertStorageWarning` | >80% (configurable) | Dashboard (medium) + Slack |
| `alertStorageCritical` | >95% (configurable) | Dashboard (critical) + Slack |
| `alertAbnormalArchiveGrowth` | >100MB/day (configurable) | Dashboard (high) |
| `alertBackupMissing` | No backup in N days | Dashboard (high) |

**Alert deduplication:** `alerted_80` and `alerted_95` flags on `site_resource_usage` prevent repeat notifications. Flags are reset automatically when usage drops below the warning threshold.

### `SiteResourceReportService`

| Method | Purpose |
|---|---|
| `generateMonthlyReport()` | All sites with overages → notification + return array |
| `generateArchiveReport()` | Oversized archives → notification + return array |
| `platformSummary()` | Aggregate totals for all sites |
| `purgeOldSnapshots()` | Delete snapshots older than retention days |
| `purgeOldBandwidthRecords()` | Delete bandwidth records older than 12 months |

---

## Scheduler Commands

| Command | Schedule | Purpose |
|---|---|---|
| `resource:calculate --push` | Daily 06:00 | Calculate all sites, save snapshot, push to customer-site |
| `resource:cleanup` | Weekly Sunday 02:00 | Purge old snapshots and bandwidth records |
| `resource:archive-report` | Weekly Monday 07:00 | Scan oversized archives, detect abnormal growth |
| `resource:usage-report` | Monthly 1st 08:00 | Overage fee report → admin notification |

All commands support `--dry-run` for safe preview.  
All commands write to `storage/logs/resource.log`.

---

## Lifecycle Integration

When `SiteLifecycleService::archive()` is called:

```
Site enters ARCHIVED state
       │
       ▼
SiteArchiveService::createArchivePackage()   — creates ZIP
       │
       ▼
SiteResourceCalculatorService::releaseActiveResources()
       │
       ├── storage_bytes  → 0
       ├── db_bytes       → 0
       ├── backup_bytes   → 0
       ├── archive_bytes  → actual ZIP file size
       ├── file_count     → 0
       ├── alerted_80     → false
       └── alerted_95     → false
```

**Archive retention billing:** If `archive_retention_fee > 0`, the monthly report includes a line item for archived sites that have not been restored.

---

## Admin Panel (saas-platform)

**Route:** `/admin/resource-governance`

### Overview (`GET /admin/resource-governance`)
- 4 KPI cards: total storage, total DB, monthly bandwidth, sites over 80%
- Red alert banner when any site is over 95%
- Filter bar: All / Over 80% / Over 95% / Inactive
- Sort by: Storage / DB / Archive / Bandwidth / Files
- Table: site name, storage % + bar, DB size, archive, bandwidth, files, last calculated, detail link

### Per-Site Detail (`GET /admin/resource-governance/{site}`)
- 4 usage cards (storage + progress bar, DB, bandwidth + bar, archive)
- CPU/RAM weight + last calculated timestamp
- Overage fee breakdown (if any)
- 30-day trend chart (storage + DB — Chart.js line chart)
- Per-site limit override form (all 10 fields)
- 30-day snapshot history table
- Force-recalculate button (POST /admin/resource-governance/{site}/recalculate)

### Settings (`GET /admin/resource-governance/settings`)
- Default limits: storage GB, bandwidth GB, archive retention days, backup retention days
- Alert thresholds: warning %, critical %
- Snapshot retention days
- 4 overage fee fields (storage/bandwidth/archive/restore)
- Writes to `.env` via regex replace

---

## Customer Dashboard (customer-site)

### Resource Widget (`admin/partials/resource-widget.blade.php`)
- Only renders when storage usage > 60% (configurable)
- Reads `site_resource_summary` from Settings table (JSON pushed by calculator)
- Color-coded: blue (60–79%) → yellow (80–94%) → red (95%+)
- Progress bar, used/limit display, DB size badge
- Contextual message: "upgrade storage" at critical level

### Resources Page (`/account/resources`)
- Full 3-card layout: Storage (with quota bar), Database, Archive
- Total usage breakdown with per-resource bars
- Support contact CTA

---

## Push Mechanism (saas-platform → customer-site)

`SiteResourceCalculatorService::pushSummaryToCustomerSite()` uses the same dynamic DB connection pattern as `SiteLifecycleService::pushStatusToCustomerSite()`:

```php
// Opens dynamic MySQL connection to customer-site DB
// Upserts settings key: site_resource_summary
// Value: JSON with storage_pct, storage_bytes, storage_limit, db_bytes, archive_bytes, calculated_at
// Clears customer-site settings cache (cache table key='settings')
```

The customer-site widget reads this value directly from its Settings table — no HTTP round-trip, no shared Redis required.

---

## Configuration

```env
RESOURCE_DEFAULT_PLAN=basic

# Limits
RESOURCE_STORAGE_LIMIT_BYTES=1073741824      # 1 GB
RESOURCE_BANDWIDTH_LIMIT_BYTES=10737418240   # 10 GB/month
RESOURCE_ARCHIVE_RETENTION_DAYS=365
RESOURCE_BACKUP_RETENTION_DAYS=30
RESOURCE_CPU_WEIGHT=10
RESOURCE_RAM_WEIGHT=10

# Fees (Rials, 0 = not billed)
RESOURCE_STORAGE_OVERAGE_FEE=0
RESOURCE_BANDWIDTH_OVERAGE_FEE=0
RESOURCE_ARCHIVE_RETENTION_FEE=0
RESOURCE_RESTORE_FEE=0

# Alerts
RESOURCE_ALERT_WARNING_PCT=80
RESOURCE_ALERT_CRITICAL_PCT=95

# Data retention
RESOURCE_SNAPSHOT_RETENTION_DAYS=90
RESOURCE_ARCHIVE_GROWTH_ANOMALY=104857600    # 100 MB/day
```

---

## Key Files

### saas-platform

| File | Purpose |
|---|---|
| `database/migrations/2026_06_19_200001_create_site_resource_usage_table.php` | Current usage per site |
| `database/migrations/2026_06_19_200002_create_site_resource_limits_table.php` | Per-site or plan limits |
| `database/migrations/2026_06_19_200003_create_site_bandwidth_usage_table.php` | Monthly traffic tracking |
| `database/migrations/2026_06_19_200004_create_site_storage_snapshots_table.php` | Daily history |
| `app/Models/SiteResourceUsage.php` | Current usage model |
| `app/Models/SiteResourceLimit.php` | Limits model (with defaultForSite()) |
| `app/Models/SiteBandwidthUsage.php` | Monthly bandwidth model |
| `app/Models/SiteStorageSnapshot.php` | Daily snapshot model |
| `app/Services/ResourceGovernance/SiteResourceCalculatorService.php` | Core metric collection |
| `app/Services/ResourceGovernance/SiteResourceGovernorService.php` | Limit checks + overage fees |
| `app/Services/ResourceGovernance/SiteResourceAlertService.php` | Threshold alerts |
| `app/Services/ResourceGovernance/SiteResourceReportService.php` | Reports + cleanup |
| `app/Console/Commands/CalculateSiteResourcesCommand.php` | `resource:calculate` |
| `app/Console/Commands/CleanupResourceDataCommand.php` | `resource:cleanup` |
| `app/Console/Commands/ArchiveReportCommand.php` | `resource:archive-report` |
| `app/Console/Commands/UsageReportCommand.php` | `resource:usage-report` |
| `app/Http/Controllers/Admin/SiteResourceGovernanceController.php` | Admin panel controller |
| `config/resource_governance.php` | All configurable values |
| `resources/views/admin/resource-governance/index.blade.php` | Overview |
| `resources/views/admin/resource-governance/site.blade.php` | Per-site detail |
| `resources/views/admin/resource-governance/settings.blade.php` | Settings |

### customer-site

| File | Purpose |
|---|---|
| `resources/views/admin/partials/resource-widget.blade.php` | Dashboard quota widget |
| `resources/views/admin/account/resources.blade.php` | Full resources page |
| `app/Helpers/NumberHelper.php` | Added `formatBytes()` helper |
| `routes/web.php` | `/account/resources` route |

---

## Billing Flow

```
resource:usage-report (monthly)
       │
       ▼
SiteResourceReportService::generateMonthlyReport()
       │
       ├── For each site with usage > limit:
       │     calculateOverageFees() → line items array
       │     Total fee = storage_overage + bandwidth_overage + archive_retention
       │
       ▼
NotificationCenterService::notify()  — admin dashboard notification
       │
       └── (Future: auto-create SiteLifecycleInvoice for outstanding fees)
```

**Overage fee types:**
- `storage_overage` — (used_bytes − limit_bytes) / 1 GB × overage_fee_per_gb
- `bandwidth_overage` — (bandwidth_bytes − limit_bytes) / 1 GB × overage_fee_per_gb
- `archive_retention` — flat fee per billing cycle when `archive_path` is set

---

## Multi-Tenant Isolation

- All resource records are scoped to `site_id` — no cross-site data leakage
- Dynamic DB connections use per-site keys (`resource_calc_{siteId}`) — purged after use
- `SiteResourceLimit::defaultForSite()` respects per-site overrides before plan defaults
- Admin can only see/edit limits for specific sites
- Customer sees only their own usage via the settings-based push mechanism

---

## Certification

- Implementation: 2026-06-19
- Phase: LIFECYCLE-2
- Both repos: saas-platform + customer-site
- Integrated with: LIFECYCLE-1 state machine, MHC incident system, Notification Center, Slack alerts
