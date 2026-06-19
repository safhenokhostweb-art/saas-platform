# Customer Site Lifecycle Architecture

> Generated: 2026-06-19  
> Phase: LIFECYCLE-1 — Complete

---

## Overview

Every customer site managed by the SaaS Platform has a lifecycle state that governs its operational status. States are automatically managed based on subscription expiry, hosting payments, and elapsed time. Platform admins retain full manual override capability at all times.

---

## State Machine

```
                    Subscription expires
                    ┌────────────────────────────────────────────┐
                    ▼                                            │
┌─────────────────────────┐                                     │
│         ACTIVE          │ ◄──────── Payment received ─────────┤
└─────────────────────────┘                                     │
           │                                                    │
           │ expiry detected (lifecycle:check-expired)          │
           │ daily 08:05                                        │
           ▼                                                    │
┌─────────────────────────┐                                     │
│      GRACE_PERIOD       │ ──── 15 days (configurable) ───────►│
│  Site online, SMS/notif │                                     │
│  reminder every 3 days  │                                     │
└─────────────────────────┘                                     │
           │                                                    │
           │ grace ends without payment (lifecycle:check-grace-period)
           │ daily 08:10                                        │
           ▼                                                    │
┌─────────────────────────┐                                     │
│       SUSPENDED         │ ──── Payment + request ─────────────┘
│  Public: suspended page │
│  Admin: full access     │
└─────────────────────────┘
           │
           │ 90 days elapsed (lifecycle:check-suspended)
           │ daily 03:05
           ▼
┌─────────────────────────┐
│        ARCHIVED         │ ──── Restore invoice paid ─────────► ACTIVE
│  Archive package created│                                        (restore)
│  Resources freed        │
└─────────────────────────┘
           │
           │ 365 days elapsed, no restore request
           │ (lifecycle:check-archived) daily 04:05
           ▼
┌─────────────────────────┐
│        DELETED          │  (final state — no transitions)
│  All data purged        │
└─────────────────────────┘
```

---

## Site States

### 1. ACTIVE
- Site fully operational for public visitors and admin users.
- No restrictions. This is the default state for all new sites.

### 2. GRACE_PERIOD
- Triggered when `host_expires_at` or `support_expires_at` passes while site is ACTIVE.
- Default duration: **15 days** (configurable via `LIFECYCLE_GRACE_PERIOD_DAYS`).
- Site remains **fully online** for public visitors.
- Customer receives:
  - SMS notification at start of grace period
  - SMS reminder when ≤3 days remain
  - Dashboard warning widget in admin panel
  - Platform admin notification

### 3. SUSPENDED
- Triggered when GRACE_PERIOD ends without payment/renewal.
- **Public visitors** see a professional suspended page (503 response).
- **Customer admin panel** remains fully accessible.
- Customer **can**: login, view invoices, pay invoices, open tickets, view subscription.
- Customer **cannot**: edit content, manage products, publish pages, modify site settings.
- Platform admin can see all suspended sites in the lifecycle overview.

### 4. ARCHIVED
- Triggered 90 days after SUSPENDED state (configurable via `LIFECYCLE_ARCHIVE_AFTER_DAYS`).
- System automatically creates archive package:
  - `site_snapshot.json` — site settings, modules, features
  - `theme_data.json` — theme builder pages/blocks
  - `README.md` — archive metadata
- Archive stored at `storage/app/archives/site_{id}_{date}.zip`
- Server resources are freed.
- Customer account remains accessible.
- Customer can request restoration (creates invoice).

### 5. DELETED
- Triggered 365 days after ARCHIVED, with no restore request on record.
- Only if: no payment, no restore request, retention period expired.
- Final, irreversible state.

---

## Database Changes

### saas-platform

**Migration 1: `2026_06_19_100001_add_lifecycle_columns_to_sites_table.php`**

Adds to `sites` table:
```sql
lifecycle_status       VARCHAR(30)  DEFAULT 'active'
lifecycle_changed_at   TIMESTAMP    NULL
grace_period_started_at TIMESTAMP   NULL
grace_period_ends_at   TIMESTAMP    NULL
suspended_at           TIMESTAMP    NULL
suspended_reason       VARCHAR(500) NULL
archived_at            TIMESTAMP    NULL
archive_path           VARCHAR(1000) NULL
restore_requested_at   TIMESTAMP    NULL
lifecycle_deleted_at   TIMESTAMP    NULL
```

**Migration 2: `2026_06_19_100002_create_site_lifecycle_logs_table.php`**

Audit log for every lifecycle transition:
```sql
CREATE TABLE site_lifecycle_logs (
  id           BIGINT PK,
  site_id      BIGINT FK → sites.id,
  from_status  VARCHAR(30),
  to_status    VARCHAR(30),
  reason       VARCHAR(500),
  changed_by   BIGINT NULL,   -- NULL = system automated
  is_system    BOOLEAN,
  metadata     JSON,
  timestamps
)
```

**Migration 3: `2026_06_19_100003_create_site_lifecycle_invoices_table.php`**

Restoration/reactivation invoices:
```sql
CREATE TABLE site_lifecycle_invoices (
  id                VARCHAR(50) PK,
  site_id           BIGINT FK → sites.id,
  invoice_number    VARCHAR(50) UNIQUE,
  type              ENUM(reactivation, archive_restore, storage_retention),
  amount            BIGINT,     -- in Rials
  status            ENUM(pending, paid, cancelled),
  due_at            TIMESTAMP NULL,
  paid_at           TIMESTAMP NULL,
  payment_reference VARCHAR(200),
  line_items        JSON,
  timestamps
)
```

### customer-site

No new migrations. The `settings` table (existing key-value store) receives:
- `site_lifecycle_status` = current lifecycle status string

This is pushed from saas-platform via dynamic DB connection on every transition.

---

## Scheduler Flow

All 4 commands are registered in `app/Console/Kernel.php`:

```
08:05 daily  lifecycle:check-expired       → ACTIVE → GRACE_PERIOD
08:10 daily  lifecycle:check-grace-period  → GRACE_PERIOD → SUSPENDED + reminders
03:05 daily  lifecycle:check-suspended     → SUSPENDED → ARCHIVED
04:05 daily  lifecycle:check-archived      → ARCHIVED → DELETED
```

Each command supports `--dry-run` flag for safe preview.

---

## Notification Flow

### Grace Period Started
- **SMS** → customer mobile (fetched from `Site::customer()`)
- **Dashboard** → platform admin notification center (`lifecycle.grace_period_started`)

### Grace Period Reminder (≤3 days remaining)
- **SMS** → customer mobile with days remaining
- **Dashboard** → platform admin notification center

### Site Suspended
- **SMS** → customer mobile
- **Dashboard** → platform admin (HIGH priority)

### Site Archived
- **SMS** → customer mobile
- **Dashboard** → platform admin

### Site Restored
- **SMS** → customer mobile (confirmation)
- **Dashboard** → platform admin (LOW priority)

**All SMS via:** `SmsGatewayResolver::resolve()` → failover to secondary provider on failure.

---

## Recovery (Restore) Flow

```
Customer requests restore
         │
         ▼
SiteLifecycleService::requestRestore()
         │ creates SiteLifecycleInvoice (type=reactivation)
         │ amount = reactivation_fee + archive_restore_fee + storage_retention_fee
         ▼
Admin marks invoice as paid
    (or customer pays via /admin/subscription)
         │
         ▼
SiteLifecycleService::restore()
         │ clears suspended_at, archived_at, restore_requested_at
         │ sets is_active = true
         │ transitions → ACTIVE
         │ pushes 'active' to customer-site settings
         │ sends SMS + notification
         ▼
Site is fully operational
```

---

## Storage Strategy

Archive packages are stored at:
```
storage/app/archives/site_{id}_{Ymd_His}.zip
```

Contents:
```
site_{id}_{date}.zip/
├── README.md           ← metadata (id, domain, timestamp)
├── site_snapshot.json  ← full site config, modules, features
└── theme_data.json     ← theme builder page/block data
```

Path is saved in `sites.archive_path` for admin reference and restore lookup.

**Storage note:** Files uploaded by customers live in the customer-site's own storage. The archive package captures only platform-side configuration and theme builder data. Customer-site file backups are managed separately via `db:backup` on the customer instance.

---

## Admin Control Panel

**Route:** `/admin/lifecycle`

### Overview Page
- KPI cards: count per state (active/grace/suspended/archived/deleted)
- Tables for grace period sites (ordered by expiry)
- Tables for suspended sites (ordered by suspension date)
- Tables for archived sites (ordered by archive date)
- Link to per-site lifecycle page

### Per-Site Lifecycle Page
**Route:** `/admin/sites/{site}/lifecycle`
- Current status badge
- Grace period / suspension / archive timestamps
- Manual state transition form (any admin can force)
- Force restore button (for archived sites)
- Invoice management (mark as paid with reference)
- Full audit log with from→to transitions, reason, system/manual flag

### Settings Page
**Route:** `/admin/lifecycle/settings`
- Grace period days (default: 15)
- Archive after N days (default: 90)
- Delete after N days (default: 365)
- Reactivation fee (default: 0)
- Archive restore fee (default: 0)
- Storage retention fee (default: 0)

---

## Configuration

```env
LIFECYCLE_GRACE_PERIOD_DAYS=15
LIFECYCLE_ARCHIVE_AFTER_DAYS=90
LIFECYCLE_DELETE_AFTER_DAYS=365
LIFECYCLE_REACTIVATION_FEE=0
LIFECYCLE_ARCHIVE_RESTORE_FEE=0
LIFECYCLE_STORAGE_RETENTION_FEE=0
```

---

## Customer-Site Enforcement

### Middleware: `CheckSiteLifecycle`
Registered as **last** in the `web` middleware group in `bootstrap/app.php`.

**Logic:**
- Reads `Setting::get('site_lifecycle_status', 'active')`
- If `active` or `grace_period` → allow all traffic
- If `/admin/*`, `/login`, `/logout`, `/api/*` → always allow (admin continuity)
- Otherwise → return `resources/views/front/suspended.blade.php` with HTTP 503

### Suspended Page
- Professional, branded page
- Shows site name prominently
- No technical error details exposed
- Login button to reach admin panel (for invoice payment)
- Persian RTL, Vazirmatn font, indigo gradient design

### Dashboard Widget
Included in `resources/views/admin/dashboard.blade.php` via:
```blade
@include('admin.partials.lifecycle-widget')
```

Only renders when status is NOT `active`. Shows:
- Status badge (yellow for grace_period, red for suspended, gray for archived)
- Contextual explanation message
- Pending invoice with amount + pay button

---

## Multi-Tenant Isolation

- **All lifecycle transitions** are scoped per `site_id` — no cross-site contamination.
- **Status push** uses a dynamic per-site DB connection (`lifecycle_push_{site_id}`) — same pattern as `PlatformSiteAgentService`.
- **Cache key** `settings` in customer-site is cleared on push to ensure fresh reads.
- **Log entries** are always linked to `site_id` — platform admin cannot see logs across sites.

---

## Key Files

### saas-platform
| File | Purpose |
|------|---------|
| `database/migrations/2026_06_19_100001_*.php` | Add lifecycle columns to sites |
| `database/migrations/2026_06_19_100002_*.php` | Create site_lifecycle_logs table |
| `database/migrations/2026_06_19_100003_*.php` | Create site_lifecycle_invoices table |
| `app/Models/Site.php` | Constants + lifecycle state methods |
| `app/Models/SiteLifecycleLog.php` | Audit log model |
| `app/Models/SiteLifecycleInvoice.php` | Invoice model |
| `app/Services/Lifecycle/SiteLifecycleService.php` | Core state machine |
| `app/Services/Lifecycle/SiteArchiveService.php` | Archive package creation |
| `app/Services/Lifecycle/SiteLifecycleNotificationService.php` | SMS + dashboard notifications |
| `app/Console/Commands/CheckExpiredSitesCommand.php` | ACTIVE → GRACE_PERIOD |
| `app/Console/Commands/CheckGracePeriodSitesCommand.php` | GRACE_PERIOD → SUSPENDED |
| `app/Console/Commands/CheckSuspendedSitesCommand.php` | SUSPENDED → ARCHIVED |
| `app/Console/Commands/CheckArchivedSitesCommand.php` | ARCHIVED → DELETED |
| `app/Http/Controllers/Admin/SiteLifecycleController.php` | Admin panel controller |
| `app/Helpers/AdminMenu.php` | lifecycle menu entry |
| `config/lifecycle.php` | Configurable thresholds/fees |
| `resources/views/admin/lifecycle/overview.blade.php` | Platform lifecycle overview |
| `resources/views/admin/lifecycle/index.blade.php` | Per-site lifecycle management |
| `resources/views/admin/lifecycle/settings.blade.php` | Settings panel |

### customer-site
| File | Purpose |
|------|---------|
| `app/Http/Middleware/CheckSiteLifecycle.php` | Enforce lifecycle in web requests |
| `bootstrap/app.php` | Registers middleware in web group |
| `resources/views/front/suspended.blade.php` | Professional suspended page (503) |
| `resources/views/admin/partials/lifecycle-widget.blade.php` | Dashboard status widget |
| `resources/views/admin/dashboard.blade.php` | Includes lifecycle widget |

---

## Allowed Transitions (State Machine)

| From | To | Trigger |
|------|----|---------|
| active | grace_period | Subscription/hosting expired |
| active | suspended | Manual (admin force) |
| active | archived | Manual (admin force) |
| grace_period | active | Payment received / manual restore |
| grace_period | suspended | Grace period ends |
| suspended | active | Payment + restore |
| suspended | archived | 90 days elapsed (auto) or manual |
| archived | active | Restore invoice paid |
| archived | deleted | 365 days elapsed, no restore request |

Illegal transitions are logged as warnings and silently rejected.

---

## Certification

- Implementation: 2026-06-19
- Both repos: saas-platform + customer-site
- Deploy: via `deploy.ps1`
