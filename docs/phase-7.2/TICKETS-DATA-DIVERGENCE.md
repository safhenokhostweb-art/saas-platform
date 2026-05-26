# PHASE 7.2 — Tickets Data Divergence Analysis

**Generated:** 2026-05-25  
**Scope:** Full column-level drift between `support_tickets` (legacy) and `tickets` (canonical)

---

## 1. Schema Comparison: Main Ticket Table

| Column              | `support_tickets` (legacy)         | `tickets` (canonical)              | Migration Action              |
|---------------------|------------------------------------|------------------------------------|-------------------------------|
| `id`                | bigint unsigned, PK                | bigint unsigned, PK                | —                             |
| `ticket_number`     | string, unique (added via backfill) | string, unique                    | Direct copy                   |
| `subject`           | string                             | string                             | Direct copy                   |
| `message`           | text                               | **`description` (renamed)**        | Copy `message` → `description`|
| `status`            | string, default `open`             | string, default `open`             | Direct copy                   |
| `priority`          | string, default `normal`           | string, default `normal`           | Direct copy                   |
| `customer_email`    | string (NOT NULL)                  | string (nullable)                  | Direct copy                   |
| `customer_name`     | string (nullable)                  | string (nullable)                  | Direct copy                   |
| `category`          | string (nullable, added 2026-05-19)| string (nullable)                  | Direct copy                   |
| `tags`              | text (nullable, added 2026-05-19)  | text (nullable)                    | Direct copy                   |
| `attachments`       | text/nullable (added 2026-05-19)   | **json** (nullable)                | Cast: json_decode if needed   |
| `site_id`           | FK → sites, cascade delete         | FK → sites, nullOnDelete           | Direct copy                   |
| `customer_id`       | **MISSING**                        | FK → users, nullOnDelete           | Resolve via email match        |
| `assigned_to`       | **added via migration** (nullable) | FK → users, nullOnDelete           | Direct copy if exists         |
| `support_level`     | string (nullable, added later)     | **MISSING**                        | Drop (not in canonical schema) |
| `closed_at`         | timestamp (nullable)               | timestamp (nullable)               | Direct copy                   |
| `first_response_at` | timestamp (nullable, added via tracking migration) | timestamp (nullable) | Direct copy               |
| `resolved_at`       | **MISSING**                        | timestamp (nullable)               | Set to `closed_at` if status=closed |
| `deleted_at`        | timestamp (soft-delete, added via harden) | timestamp (soft-delete)   | Direct copy                   |
| `created_at`        | timestamp                          | timestamp                          | Direct copy                   |
| `updated_at`        | timestamp                          | timestamp                          | Direct copy                   |

### Critical Divergences

| # | Divergence                           | Impact                         | Resolution Strategy                     |
|---|--------------------------------------|--------------------------------|-----------------------------------------|
| D1 | `message` → `description`           | Breaking rename                | Copy `message` value to `description`  |
| D2 | `attachments` text vs json           | Type mismatch                  | `json_decode()` text if valid JSON, else wrap in array |
| D3 | `customer_id` missing in legacy      | Orphaned tickets               | Attempt user lookup by `customer_email`; null if no match |
| D4 | `resolved_at` missing in legacy      | SLA gap                        | Set to `closed_at` where status=closed |
| D5 | `support_level` in legacy, not canon | Legacy field                   | Drop on migration (not in canonical schema) |
| D6 | Legacy FK: site_id cascade delete    | Constraint behavior difference | Acceptable — nullOnDelete is safer     |
| D7 | `customer_email` NOT NULL (legacy)   | Stricter constraint            | Canonical allows nullable — safe       |

---

## 2. Schema Comparison: Replies Table

| Column              | `support_ticket_replies` (legacy)   | `ticket_replies` (canonical)       | Migration Action              |
|---------------------|-------------------------------------|------------------------------------|-------------------------------|
| `id`                | bigint unsigned, PK                 | bigint unsigned, PK                | —                             |
| `support_ticket_id` | FK → support_tickets, cascade       | **`ticket_id`** FK → tickets       | Map to canonical ticket ID    |
| `user_id`           | FK → users, nullOnDelete            | FK → users, nullOnDelete           | Direct copy                   |
| `message`           | text                                | text                               | Direct copy                   |
| `author_type`       | string(32), default `staff`         | string, default `staff`            | Direct copy                   |
| `customer_email`    | string (nullable)                   | string (nullable)                  | Direct copy                   |
| `customer_name`     | string (nullable)                   | string (nullable)                  | Direct copy                   |
| `attachments`       | text (nullable, added 2026-05-19)   | **json** (nullable)                | Cast: json_decode if needed   |
| `deleted_at`        | timestamp (added via harden)        | timestamp (soft-delete)            | Direct copy                   |
| `created_at`        | timestamp                           | timestamp                          | Direct copy                   |
| `updated_at`        | timestamp                           | timestamp                          | Direct copy                   |

### Critical Divergences — Replies

| # | Divergence                           | Impact               | Resolution Strategy              |
|---|--------------------------------------|----------------------|----------------------------------|
| R1 | FK column name: `support_ticket_id` → `ticket_id` | Join broken | Map via ticket ID lookup table  |
| R2 | `attachments` text vs json          | Type mismatch        | Same as D2 above                 |

---

## 3. Index Divergence

### `support_tickets` Indexes (legacy)
- `PRIMARY` on `id`
- `UNIQUE` on `ticket_number`
- `support_tickets_site_status_created_idx` (site_id, status, created_at)
- `support_tickets_site_updated_idx` (site_id, updated_at)
- `support_tickets_assignee_status_idx` (assigned_to, status)
- `support_tickets_ticket_number_idx` (ticket_number)

### `tickets` Indexes (canonical)
- `PRIMARY` on `id`
- `UNIQUE` on `ticket_number`
- Composite: `(status, priority)`
- Simple: `customer_id`, `site_id`, `assigned_to`

**Assessment:** Canonical indexes are more comprehensive. No index migration needed — canonical table has better coverage.

---

## 4. Foreign Key Constraint Differences

| Table    | Column      | Legacy Constraint     | Canonical Constraint  | Risk      |
|----------|-------------|-----------------------|-----------------------|-----------|
| tickets  | `site_id`   | `cascadeOnDelete()`   | `nullOnDelete()`      | Low — canonical is safer |
| tickets  | `customer_id` | n/a                 | `nullOnDelete()`      | New FK — no data risk    |
| replies  | `ticket_id` | `cascadeOnDelete()`   | `cascadeOnDelete()`   | Same      |

---

## 5. Data Volume Estimate

| Table                   | Row Estimate | Notes                    |
|-------------------------|--------------|--------------------------|
| `support_tickets`        | Unknown      | Check: `SELECT COUNT(*) FROM support_tickets` |
| `support_ticket_replies` | Unknown      | Check: `SELECT COUNT(*) FROM support_ticket_replies` |
| `tickets`                | 0            | Empty until migration     |
| `ticket_replies`         | 0            | Empty until migration     |

---

## 6. Migration SQL Blueprint

The `TicketDataMigrationService` (TASK 4) implements this logic programmatically.  
The following is the conceptual SQL for documentation purposes:

```sql
-- Step 1: Migrate tickets
INSERT INTO tickets (
    ticket_number, subject, description, status, priority,
    customer_id, assigned_to, site_id,
    customer_email, customer_name, category, tags, attachments,
    closed_at, first_response_at, resolved_at,
    deleted_at, created_at, updated_at
)
SELECT
    st.ticket_number,
    st.subject,
    st.message AS description,          -- D1: rename
    st.status,
    st.priority,
    u.id AS customer_id,                -- D3: lookup by email
    st.assigned_to,
    st.site_id,
    st.customer_email,
    st.customer_name,
    st.category,
    st.tags,
    st.attachments,                     -- D2: may need JSON cast in PHP
    st.closed_at,
    st.first_response_at,
    CASE WHEN st.status = 'closed' THEN st.closed_at ELSE NULL END AS resolved_at,  -- D4
    st.deleted_at,
    st.created_at,
    st.updated_at
FROM support_tickets st
LEFT JOIN users u ON u.email = st.customer_email AND u.site_id = st.site_id;

-- Step 2: Migrate replies (requires ticket ID mapping)
INSERT INTO ticket_replies (
    ticket_id, user_id, message, author_type,
    customer_email, customer_name, attachments,
    deleted_at, created_at, updated_at
)
SELECT
    t.id AS ticket_id,                  -- R1: map via ticket_number
    str.user_id,
    str.message,
    str.author_type,
    str.customer_email,
    str.customer_name,
    str.attachments,
    str.deleted_at,
    str.created_at,
    str.updated_at
FROM support_ticket_replies str
JOIN support_tickets st ON st.id = str.support_ticket_id
JOIN tickets t ON t.ticket_number = st.ticket_number;
```

---

## 7. Rollback Plan

If migration fails or produces bad data:

1. `TicketRollbackService::rollback()` truncates `tickets` and `ticket_replies`
2. All active routes continue pointing to legacy `support_tickets` via redirect bypass
3. `TicketIntegrityValidationService::validate()` detects count mismatches and reports
4. Legacy tables are NEVER dropped during PHASE 7.2 — they remain intact

**Safety constraint:** `tickets` and `ticket_replies` tables can be safely truncated without affecting production because:
- Legacy routes still serve `support_tickets`  
- Canonical routes will only become primary after PHASE 7.2 validation passes
