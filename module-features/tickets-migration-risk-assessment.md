# TASK 9: Tickets Migration Risk Assessment

## Objective
Identify and assess all risks associated with migrating from Implementation B (canonical authority, project-level) to Implementation A (package module). This is an **analysis document only** — no migration yet.

---

## 1. Risk Matrix

| ID | Risk | Probability | Impact | RPN | Category |
|----|------|------------|--------|-----|----------|
| R1 | Data model field mismatch (`description` vs `message`) | HIGH | HIGH | 9 | Data |
| R2 | Existing tickets lose data during migration | LOW | CRITICAL | 8 | Data |
| R3 | `HasUuid` trait breaks existing FK relationships | MEDIUM | HIGH | 6 | Data |
| R4 | `HasUserStamps` trait requires schema changes | MEDIUM | MEDIUM | 4 | Data |
| R5 | Package not autoloaded in saas-platform | LOW | CRITICAL | 6 | Infrastructure |
| R6 | `LicenseServerService` dependency not injectable | MEDIUM | HIGH | 6 | Architecture |
| R7 | SLA breach detection breaks with different field names | HIGH | MEDIUM | 6 | Logic |
| R8 | Frontend views use different layout names | HIGH | MEDIUM | 6 | Frontend |
| R9 | customer-site Tailwind v4 incompatibility | HIGH | HIGH | 9 | Frontend |
| R10 | Route conflicts between package and project | MEDIUM | HIGH | 6 | Routing |
| R11 | Cache invalidation after migration | MEDIUM | MEDIUM | 4 | Performance |
| R12 | Permission/authorization differences | MEDIUM | HIGH | 6 | Security |
| R13 | API response format changes | MEDIUM | MEDIUM | 4 | API |
| R14 | File upload path changes (`tickets/` vs `support-tickets/`) | HIGH | MEDIUM | 6 | Storage |
| R15 | Activity logging breaks during transition | MEDIUM | LOW | 2 | Monitoring |

**RPN = Probability × Impact (1-3 scale each, max 9)**

---

## 2. Critical Risks (RPN ≥ 8)

### R1: Data Model Field Mismatch (`description` vs `message`)

**Description:** The package module uses `description` for the ticket body field, while Implementation B (canonical) uses `message`. All existing data uses `message`.

**Impact:** If not handled properly, existing ticket bodies will be invisible after migration.

**Mitigation Strategy:**
```php
// Option A: Add accessor to package Ticket model (RECOMMENDED)
public function getMessageAttribute(): ?string
{
    return $this->description;
}

public function setMessageAttribute(?string $value): void
{
    $this->description = $value;
}

// Option B: Rename column via migration (RISKY - data loss)
// Option C: Config-based field mapping (FLEXIBLE)
```

**Recommendation:** Option A — add accessor/mutator. Zero data loss, zero downtime.

### R2: Existing Tickets Lose Data During Migration

**Description:** Any migration that alters the `tickets` table schema could cause data loss if not handled carefully.

**Impact:** Complete loss of all existing support tickets.

**Mitigation Strategy:**
1. Always run `php artisan migrate --pretend` before actual migration
2. Take a full database backup before any migration
3. Use `Schema::hasColumn()` checks in all migrations
4. Never drop columns — only add new ones
5. Test migration on a staging environment first

### R9: Customer-Site Tailwind v4 Incompatibility

**Description:** The customer-site project has `@tailwindcss/vite@4.3.0` installed, which requires Tailwind v4, but the project has `tailwindcss@3.4.19`. This is a pre-existing issue that blocks any frontend work.

**Impact:** Vite build fails, frontend changes cannot be tested.

**Mitigation Strategy:**
1. Remove `@tailwindcss/vite` from customer-site
2. Add `postcss.config.js` and `tailwind.config.js` to customer-site
3. Update `vite.config.js` to use PostCSS plugin
4. Update `app.css` to use `@tailwind` directives instead of `@import 'tailwindcss'`
5. See PHASE 6.875 for the exact steps (already completed for saas-platform)

---

## 3. High Risks (RPN = 6)

### R3: `HasUuid` Trait Breaks Existing FK Relationships

**Description:** Adding UUID to existing tickets changes the primary key type from `int` to `string`. This breaks all FK relationships in `ticket_replies` table.

**Impact:** All replies become orphaned.

**Mitigation Strategy:**
```php
// Keep integer IDs for existing data, add UUID as secondary identifier
// Do NOT change primary keys
protected $fillable = [
    'uuid', // New field, not primary key
    // ... existing fields
];
```

### R5: Package Not Autoloaded

**Description:** The package module must be properly registered in `composer.json` autoload PSR-4 mapping and in `config/app.php` providers array.

**Impact:** Module completely unavailable.

**Mitigation Strategy:**
```json
// composer.json
"autoload": {
    "psr-4": {
        "SaaSPlatform\\Modules\\Tickets\\": "packages/saas-platform/modules/tickets/src/"
    }
}
```

### R6: `LicenseServerService` Dependency Not Injectable

**Description:** The package cannot hardcode `LicenseServerService` as it's an external service. Must use interface-based injection.

**Impact:** Portal service cannot validate licenses.

**Mitigation Strategy:**
```php
// Create interface in package
interface LicenseValidationInterface
{
    public function validate(string $licenseKey, string $domain): array;
}

// Bind in service provider
$this->app->bind(LicenseValidationInterface::class, \App\Services\LicenseServerService::class);
```

### R8: Frontend Views Use Different Layout Names

**Description:** Package views use `@extends('admin.layout')` while Implementation B uses `@extends('layouts.admin')`.

**Impact:** Views render with wrong layout or break entirely.

**Mitigation Strategy:**
```php
// Use config-based layout resolution (like Forms module)
// config/tickets.php
'layouts' => [
    'admin' => env('TICKETS_ADMIN_LAYOUT', 'admin.layout'),
],

// In views:
@extends(config('tickets.layouts.admin'))
```

### R10: Route Conflicts

**Description:** Package routes (`/admin/tickets`) may conflict with project routes (`/admin/support-tickets`).

**Impact:** 404 errors or wrong controller handling.

**Mitigation Strategy:**
```php
// Phase 6: Update project routes to use package prefix
// routes/web.php
Route::prefix('admin/tickets')->group(function () {
    // Package routes
});
```

### R12: Permission/Authorization Differences

**Description:** Implementation B uses custom authorization logic that may not be present in the package.

**Impact:** Unauthorized access or false denials.

**Mitigation Strategy:**
```php
// Add authorization gates to package
// TicketsServiceProvider::boot()
Gate::define('view-tickets', function ($user) {
    return $user->hasPermission('view_tickets');
});
```

### R14: File Upload Path Changes

**Description:** Package stores files in `tickets/` directory while Implementation B uses `support-tickets/`.

**Impact:** Existing attachments become inaccessible.

**Mitigation Strategy:**
```php
// Config-based storage path
// config/tickets.php
'storage_path' => env('TICKETS_STORAGE_PATH', 'tickets'),

// In service:
$path = $file->store(config('tickets.storage_path') . '/' . now()->format('Y/m'), 'public');
```

---

## 4. Low-Medium Risks (RPN ≤ 4)

| ID | Risk | Mitigation |
|----|------|------------|
| R4 | `HasUserStamps` schema changes | Add `created_by`, `updated_by` columns in separate migration |
| R7 | SLA field name mismatch | Use accessors (same as R1 mitigation) |
| R11 | Cache invalidation | Run `php artisan optimize:clear` after migration |
| R13 | API response format | Add response transformer layer in package |
| R15 | Activity logging | Add logging hooks in package service |

---

## 5. Migration Order Risk

### Recommended Safe Order:
```
Phase 1 (Package Foundation) → Phase 2 (Data Model) → Phase 3 (Licensing/SLA)
→ Phase 4 (Lifecycle) → Phase 5 (Frontend) → Phase 6 (saas-platform)
→ Phase 7 (customer-site) → Phase 8 (Testing)
```

### Risk of Changing Order:
| Change | Risk |
|--------|------|
| Skip Phase 2 | Data model mismatch breaks everything |
| Skip Phase 3 | Licensing/SLA logic lost |
| Skip Phase 4 | No lifecycle management |
| Skip Phase 5 | Frontend inconsistencies |
| Phase 6 before Phase 2 | Broken FK relationships |
| Phase 7 before Phase 6 | customer-site depends on saas-platform API |

---

## 6. Rollback Triggers

The migration should be rolled back immediately if:

1. **Any existing ticket data is lost** — Stop, restore from backup
2. **Vite build fails** — Frontend changes cannot be deployed
3. **License validation fails** — Customers cannot access support
4. **SLA breach detection is incorrect** — Wrong deadlines shown to customers
5. **File uploads fail** — Attachments cannot be added to tickets
6. **Route conflicts cause 404 errors** — Navigation broken

---

## 7. Go/No-Go Checklist

Before starting Phase 6 (project-level migration):

- [ ] All Phase 1-5 tasks completed and tested
- [ ] Database backup taken
- [ ] `composer.json` autoload updated
- [ ] `config/app.php` providers updated
- [ ] `php artisan optimize:clear` run
- [ ] `php artisan migrate --pretend` verified
- [ ] customer-site Tailwind v4 issue resolved
- [ ] Staging environment available for testing
- [ ] Rollback plan documented and understood
- [ ] Team notified of maintenance window

---

## 8. Overall Risk Score

| Category | Score | Interpretation |
|----------|-------|----------------|
| Data Integrity | 7/10 | HIGH — Field name mismatch is the biggest risk |
| Infrastructure | 4/10 | MEDIUM — Autoloading and Tailwind issues |
| Architecture | 5/10 | MEDIUM — Interface extraction needed |
| Frontend | 6/10 | HIGH — Layout and CSS inconsistencies |
| Security | 3/10 | LOW — Authorization can be preserved |
| Performance | 2/10 | LOW — Cache invalidation is standard |

**Overall: 4.5/10 — MODERATE RISK**

The migration is feasible but requires careful planning, especially around:
1. The `description` ↔ `message` field mapping
2. The customer-site Tailwind v4 issue
3. The `LicenseServerService` interface extraction
