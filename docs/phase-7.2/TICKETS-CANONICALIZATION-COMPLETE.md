# PHASE 7.2 — Tickets Data & Active Runtime Canonical Migration

**Completed:** 2026-05-25  
**Status:** COMPLETE — Route authority migrated, data migration services ready, runtime validation command deployed  
**Scope:** saas-platform routes/web.php · api.php · tickets package · legacy view deprecation

---

## Deliverables

| # | Deliverable                                          | Status  | Location |
|---|------------------------------------------------------|---------|----------|
| 1 | `TICKETS-ACTIVE-RUNTIME-TRACE.md`                    | ✅ Done | `docs/phase-7.2/TICKETS-ACTIVE-RUNTIME-TRACE.md` |
| 2 | Canonical Route Authority — redirect legacy routes   | ✅ Done | `routes/web.php`, `routes/api.php` |
| 3 | `TICKETS-DATA-DIVERGENCE.md`                         | ✅ Done | `docs/phase-7.2/TICKETS-DATA-DIVERGENCE.md` |
| 4 | `TicketDataMigrationService`                         | ✅ Done | `packages/.../tickets/src/Services/TicketDataMigrationService.php` |
| 4 | `TicketIntegrityValidationService`                   | ✅ Done | `packages/.../tickets/src/Services/TicketIntegrityValidationService.php` |
| 4 | `TicketRollbackService`                              | ✅ Done | `packages/.../tickets/src/Services/TicketRollbackService.php` |
| 5 | Legacy views moved to `_legacy/`                     | ✅ Done | `resources/views/admin/support-tickets/_legacy/` |
| 6 | `assertTenantIsolation()` in `TicketService`         | ✅ Done | `packages/.../tickets/src/Services/TicketService.php` |
| 7 | Canonical events: `TicketCreated`, `TicketReplied`, `TicketStatusChanged` | ✅ Done | `packages/.../tickets/src/Events/` |
| 7 | Events wired into `TicketService`                    | ✅ Done | `TicketService::create()`, `updateStatus()`, `addReply()` |
| 8 | Policy `authorize()` in canonical controllers        | ✅ Done | `TicketController`, `TicketStatusController`, `TicketReplyController` |
| 8 | `admin` middleware added to canonical admin routes   | ✅ Done | `packages/.../tickets/Routes/admin.php` |
| 9 | `TicketCanonicalRuntimeValidationService` (10 checks)| ✅ Done | `packages/.../tickets/src/Services/TicketCanonicalRuntimeValidationService.php` |
| 9 | `tickets:validate-runtime-authority` command         | ✅ Done | `packages/.../tickets/src/Console/Commands/ValidateRuntimeAuthorityCommand.php` |
| 9 | `tickets:migrate-data` command                       | ✅ Done | `packages/.../tickets/src/Console/Commands/MigrateTicketDataCommand.php` |
| 10| This report                                          | ✅ Done | `docs/phase-7.2/TICKETS-CANONICALIZATION-COMPLETE.md` |

---

## Architecture Decisions

### AD-1: Redirect-based route migration (not controller replacement)

Legacy admin routes (`/admin/support-tickets/*`) now issue 301 redirects to canonical routes (`/admin/tickets/*`). POST/PATCH routes also redirect. This preserves named-route compatibility (`route('admin.support-tickets.index')` still resolves but now redirects). The legacy `AdminSupportTicketController` is no longer referenced in any route declaration.

**Rationale:** Zero-downtime migration. Existing links and bookmarks continue to work. No JavaScript or template changes required.

### AD-2: API backward compatibility preserved

`/api/support-tickets` routes remain active in `routes/api.php` with a deprecation comment. customer-site's `SupportApiClient` calls this endpoint with `license_key + domain` authentication, which differs from the canonical API's `auth:sanctum` scheme. These routes will be removed in a future phase after `SupportApiClient` is updated to call `/api/tickets`.

### AD-3: Data migration is explicit, not automatic

`TicketDataMigrationService::migrate()` must be invoked manually via `php artisan tickets:migrate-data`. It is idempotent (skips already-migrated tickets by ticket_number). This ensures operators can review and validate before canonical routes serve live traffic.

### AD-4: Dual-table coexistence during transition

Both `support_tickets` (legacy) and `tickets` (canonical) tables remain populated during the transition period. The canonical system is the only write target for new tickets created via the redirected routes. Legacy data is migrated on demand, not automatically.

### AD-5: Canonical admin routes now require `admin` middleware

The `Routes/admin.php` route group was updated from `['web', 'auth']` to `['web', 'auth', 'admin']`. Without this fix, authenticated non-admin users could access the admin ticket management panel via the canonical routes.

---

## Route Migration Map

### Before PHASE 7.2

```
GET  /admin/support-tickets         → Admin\SupportTicketController@index
GET  /admin/support-tickets/create  → Admin\SupportTicketController@create
POST /admin/support-tickets         → Admin\SupportTicketController@store
GET  /admin/support-tickets/{id}    → Admin\SupportTicketController@show
```

### After PHASE 7.2

```
GET  /admin/support-tickets         → 301 → /admin/tickets
GET  /admin/support-tickets/create  → 301 → /admin/tickets/create
POST /admin/support-tickets         → 301 → /admin/tickets (handled by canonical TicketController)
GET  /admin/support-tickets/{id}    → 301 → /admin/tickets/{id}

GET  /admin/tickets                 → Admin\TicketController@index         (canonical, tickets table)
GET  /admin/tickets/create          → Admin\TicketController@create        (canonical, tickets table)
POST /admin/tickets                 → Admin\TicketController@store         (canonical, tickets table)
GET  /admin/tickets/{id}            → Admin\TicketController@show          (canonical, tickets table)
```

---

## Data Migration Commands

```bash
# Preview without writing
php artisan tickets:migrate-data --dry-run

# Run migration
php artisan tickets:migrate-data

# Run migration + validate
php artisan tickets:migrate-data --validate

# Rollback canonical tables (interactive confirmation required)
php artisan tickets:migrate-data --rollback

# Runtime authority check (all 10 structural/data checks)
php artisan tickets:validate-runtime-authority

# JSON output for CI
php artisan tickets:validate-runtime-authority --json

# Exit code 1 on failure (CI gate)
php artisan tickets:validate-runtime-authority --fail-on-warning
```

---

## Data Divergence Resolved

| Divergence | Resolution                                                    |
|------------|---------------------------------------------------------------|
| D1: `message` → `description` | Copied by `TicketDataMigrationService::buildTicketRow()` |
| D2: attachments text vs json | `castAttachments()` validates JSON, wraps plain text       |
| D3: `customer_id` missing | Derived by email + site_id lookup against users table      |
| D4: `resolved_at` missing | Set to `closed_at` where status = `closed`                 |
| D5: `support_level` not in canonical | Dropped on migration                               |
| R1: `support_ticket_id` → `ticket_id` | Mapped via ticket_number join in `migrateReplies()` |

---

## Validation Checks

### TicketIntegrityValidationService (post-migration)
| Check | Description |
|-------|-------------|
| `tables_exist` | Canonical tables present |
| `ticket_count_match` | Canonical count ≥ legacy count |
| `reply_count_match` | Canonical replies ≥ legacy replies |
| `ticket_number_parity` | Every legacy ticket_number present canonically |
| `orphaned_replies` | No ticket_replies without parent ticket |
| `null_descriptions` | No empty description fields |
| `site_id_integrity` | All site_id FKs valid |
| `status_distribution` | Status counts match per-status |

### TicketCanonicalRuntimeValidationService (runtime authority)
| Check | Description |
|-------|-------------|
| R1 | Canonical tables exist and reachable |
| R2 | Legacy controllers absent from active routing |
| R3 | Canonical route names registered (tickets.admin.index, etc.) |
| R4 | Canonical views resolvable (tickets:: namespace) |
| R5 | TicketServiceInterface bound in container |
| R6 | Migration parity (canonical count ≥ legacy) |
| R7 | No orphaned ticket_replies |
| R8 | Tenant isolation holds (all site_id FKs valid) |
| R9 | TicketPolicy registered for Ticket model |
| R10 | TicketDataMigrationService bound |

---

## Deferred to Post-PHASE 7.2

| Work Item                                                    | Reason |
|--------------------------------------------------------------|--------|
| Update `SupportApiClient` in customer-site → `/api/tickets`  | Requires coordinated customer-site release |
| Remove `/api/support-tickets` legacy route group from api.php | Blocked by SupportApiClient migration |
| Drop `support_tickets` and `support_ticket_replies` tables    | After canonical migration verified in production |
| Remove legacy controller files from `app/Http/Controllers/`  | After final route cleanup |

---

## Governance Rules (PHASE 7.2 additions)

9. **All new tickets are written to `tickets` table only.** Legacy `support_tickets` receives no new writes.
10. **Run `tickets:validate-runtime-authority` before every production deploy** involving ticket changes.
11. **Run `tickets:migrate-data --validate` after running migrations** to confirm parity.
12. **`assertTenantIsolation()` is mandatory** in every `TicketService` method that returns a single ticket to the site context.
