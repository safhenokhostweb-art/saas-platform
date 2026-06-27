# Customer Installation Admin Center
## Technical Design Document — PHASE-003B

**Status:** COMPLETE  
**Phase:** PHASE-003B  
**Depends on:** PHASE-003A (Foundation Services)  
**Date:** 2026-06-27

---

## 1. Overview

The Customer Installation Admin Center is the administrative UI layer for the remote customer installation pipeline. It provides full lifecycle management — from server registration through real-time monitoring to post-install history and manual rollback — while reusing all business logic services from PHASE-003A without duplication.

### Scope

| In Scope | Out of Scope |
|---|---|
| Admin UI (Blade + Alpine.js) | Core installation logic (PHASE-003A) |
| Route definitions | Restore / Backup services |
| Controller orchestration | Theme implementation |
| SSE real-time streaming | PSDS changes |
| Server CRUD | New queue workers |

---

## 2. Architecture

### Layering

```
Browser (Alpine.js + SSE)
        │
HTTP/SSE Layer
        │
Controllers (Admin/Infrastructure/)
        │
PHASE-003A Services (no duplication)
        │
Database / Redis / Remote SSH
```

### Controllers

| Controller | Namespace | Purpose |
|---|---|---|
| `CustomerDeploymentController` | `Admin\Infrastructure` | Dashboard, wizard, history, show, start, rollback, packages |
| `CustomerRemoteServerAdminController` | `Admin\Infrastructure` | Server CRUD + SSH test |
| `CustomerInstallationProgressController` | `Admin\Infrastructure` | SSE stream |

All controllers live in `app/Http/Controllers/Admin/Infrastructure/`.

### Route Prefix

All routes are nested under:

```
/admin/infrastructure/customer-deployment/
```

Named with prefix `admin.infrastructure.customer-deployment.`

---

## 3. Modules

### 3.1 Dashboard

**Route:** `GET /admin/infrastructure/customer-deployment/`  
**View:** `admin.infrastructure.customer-deployment.dashboard`

Displays:
- 6 stat cards: Active installs, Queued, Complete, Failed, Servers registered, Active packages
- Active installation list with animated status indicators
- Recent 10 installations table
- Quick action buttons

Data source: Direct Eloquent queries — no service layer needed for read-only aggregates.

### 3.2 Installation Wizard

**Route:** `GET /admin/infrastructure/customer-deployment/wizard`  
**Submit:** `POST /admin/infrastructure/customer-deployment/start`  
**View:** `admin.infrastructure.customer-deployment.wizard`

8-step Alpine.js wizard with client-side step validation. No page reloads.

| Step | Content |
|---|---|
| 1 | Customer info: company, owner, email, phone, language, timezone, currency |
| 2 | Server selection: radio cards showing SSH servers with test status |
| 3 | Domain + install mode (FRESH / RESTORE / CLONE / MOVE / REINSTALL) |
| 4 | Config: backup policy, SSL, auto DB credentials notice |
| 5 | License tier: basic / professional / enterprise |
| 6 | Theme placeholder (marketplace coming) |
| 7 | Module selection: education, tickets, forms, ecommerce |
| 8 | Review all data before submit |

On submission: creates `CustomerInstallation`, dispatches `CustomerInstallationPipelineJob` on `customer-install` queue, redirects to detail view.

### 3.3 Remote Server Manager

**Routes:** `/admin/infrastructure/customer-deployment/servers/*`  
**Views:** `servers/index`, `servers/create`, `servers/edit`

Full CRUD for `CustomerRemoteServer` records.

**Security guarantees:**
- Private keys and passwords are encrypted via `CredentialVault::encrypt()` (AES-256-GCM) before any DB write
- Credential fields are never pre-populated in edit forms
- Credential fields are never returned in API responses or logs
- Empty credential fields on update = keep existing (never overwrite with null)

**Test Connection (AJAX):**
- `POST /admin/infrastructure/customer-deployment/servers/{id}/test`
- Calls `RemoteConnectionService::testAndRecord($server)`
- Returns: `php_version`, `disk_free`, `fingerprint` — no credential data
- Prevents deletion of servers with active installations

### 3.4 Real-time Installation Monitor

**Route:** `GET /admin/infrastructure/customer-deployment/installations/{id}/progress`  
**Controller:** `CustomerInstallationProgressController::stream()`  
**Protocol:** Server-Sent Events (SSE)

Event flow:
```
→ init      (installation metadata from DB)
→ progress  (from Redis LRANGE install_progress_{id})
→ status    (DB refresh every 5 ticks)
→ done      (when terminal status reached)
→ end       (stream closed after 1h max)
```

Fallback: If Redis unavailable, continues with DB-only polling (every 5s).  
Auto-close: On `complete`, `failed`, `rolled_back`, `rollback_failed`.  
Max stream duration: 3600 seconds (1 hour).

Client-side (in `show.blade.php`):
```javascript
const src = new EventSource('/admin/infrastructure/...progress');
src.addEventListener('done', e => { src.close(); setTimeout(() => location.reload(), 2000); });
```

### 3.5 Installation History

**Route:** `GET /admin/infrastructure/customer-deployment/history`  
**View:** `admin.infrastructure.customer-deployment.history`

Filterable paginated table (15/page):
- Filters: free-text search (company/domain/email), status, server, date_from, date_to
- Columns: ID, customer (company + email), domain, server, install mode, status badge, current stage, date, duration
- Duration calculated: `completed_at - started_at` for finished, `started_at->diffForHumans()` for running

### 3.6 Installation Detail

**Route:** `GET /admin/infrastructure/customer-deployment/installations/{id}`  
**View:** `admin.infrastructure.customer-deployment.show`

Left column:
- Immutable log timeline (color-coded by event type)
- SSE auto-connect for running installations

Right column:
- Installation metadata table
- 20-stage progress list (done=green strikethrough, active=blue pulse, pending=gray)
- Rollback checkpoint list

### 3.7 Manual Rollback

**Route:** `POST /admin/infrastructure/customer-deployment/installations/{id}/rollback`  
**Controller:** `CustomerDeploymentController::rollback()`

Guards:
- Installation status must be `failed` or `rollback_failed`
- At least one `CustomerInstallationRollback` checkpoint with `executed_at = null` must exist
- Confirmation dialog in UI (`confirm()`)

On success: dispatches `CustomerInstallationRollbackJob` on `customer-install` queue.  
Reuses `CustomerInstallationRollbackService` from PHASE-003A — no new rollback logic.

### 3.8 Packages List

**Route:** `GET /admin/infrastructure/customer-deployment/packages`  
**View:** `admin.infrastructure.customer-deployment.packages`

Read-only list of `CustomerInstallationPackage` records with version, active status, size, SHA256/manifest checksums, release date, release notes.  
Active package highlighted in green.  
Packages are registered via Artisan command `install:register-package`.

---

## 4. Security Controls

| Control | Implementation |
|---|---|
| CSRF | `@csrf` on all forms; `X-CSRF-TOKEN` header on AJAX |
| Credential encryption | AES-256-GCM via `CredentialVault` before any DB write |
| No plaintext in responses | SSH keys/passwords never returned to browser |
| No secrets in logs | `CredentialVault` is the only class handling raw credentials |
| Admin-only access | All routes behind `auth` + admin middleware |
| Rollback guard | Status + checkpoint validation before dispatching rollback |
| Delete guard | Cannot delete server with active installations |
| Rate limiting | Standard Laravel throttle middleware on API routes |

---

## 5. Menu Integration

Entry added to `AdminMenu.php`:

```php
MENU_KEYS['customer_deployment']        = 'استقرار مشتریان'
MENU_ROUTES['customer_deployment']      = 'admin.infrastructure.customer-deployment.dashboard'
MENU_ROUTE_PATTERNS['customer_deployment'] = 'admin.infrastructure.customer-deployment.*'
```

Placed in group: `زیرساخت / DevOps`.

---

## 6. View Structure

```
resources/views/admin/infrastructure/customer-deployment/
├── dashboard.blade.php         ← stat cards + active list + recent table
├── wizard.blade.php            ← 8-step Alpine.js wizard
├── history.blade.php           ← filterable paginated history
├── show.blade.php              ← detail view + SSE + timeline
├── packages.blade.php          ← release packages list
└── servers/
    ├── index.blade.php         ← server list + AJAX test
    ├── create.blade.php        ← add server form (credentials never shown back)
    └── edit.blade.php          ← edit server + inline test connection
```

---

## 7. Deployment

All changes deploy via standard `.\deploy.ps1` to production at `87.107.12.81:9011`.  
No schema migrations required for PHASE-003B (all tables created in PHASE-003A).

---

*This document is part of the PHASE-003B Customer Installation Admin Center deliverables.*
