# TASK 8: Tickets Drift Elimination Plan

## Objective
Create a comprehensive, step-by-step plan to eliminate all divergences between the three Tickets implementations, converging on the canonical package module architecture.

---

## 1. Divergence Summary

### 1.1 Total Divergences Found: 47

| Category | Count | Severity |
|----------|-------|----------|
| Data Model | 5 | HIGH |
| Backend Architecture | 8 | HIGH |
| Frontend | 10 | MEDIUM |
| Routing | 4 | MEDIUM |
| Licensing/SLA | 6 | HIGH |
| Lifecycle | 6 | HIGH |
| Config | 4 | MEDIUM |
| External Dependencies | 4 | LOW |

### 1.2 Canonical Authority
**Implementation B** (saas-platform project-level) is the canonical authority with 30/30 feature score.

---

## 2. Elimination Phases

### PHASE 1: Package Foundation (Week 1)
**Goal:** Establish canonical contracts, repositories, and service layer in the package.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 1.1 | Create `TicketServiceInterface` in package | `Contracts/Services/TicketServiceInterface.php` | None |
| 1.2 | Create `TicketRepositoryInterface` in package | `Contracts/Repositories/TicketRepositoryInterface.php` | None |
| 1.3 | Create `TicketReplyRepositoryInterface` in package | `Contracts/Repositories/TicketReplyRepositoryInterface.php` | None |
| 1.4 | Create `TicketRepository` in package | `Repositories/TicketRepository.php` | 1.2 |
| 1.5 | Create `TicketReplyRepository` in package | `Repositories/TicketReplyRepository.php` | 1.3 |
| 1.6 | Refactor `TicketService` to use repositories | `Services/TicketService.php` | 1.4, 1.5 |
| 1.7 | Update `TicketsServiceProvider` with bindings | `Providers/TicketsServiceProvider.php` | 1.1-1.6 |
| 1.8 | Create `TicketPortalException` in package | `Exceptions/TicketPortalException.php` | None |

**✅ COMPLETED** — All Phase 1 steps are done (TASK 5).

### PHASE 2: Data Model Unification (Week 1-2)
**Goal:** Unify the data model across all implementations.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 2.1 | Add `message` field alias to package `Ticket` model | `Models/Ticket.php` | None |
| 2.2 | Add `support_level` field to package `Ticket` model | `Models/Ticket.php` | None |
| 2.3 | Add `support_level` migration to package | `Database/Migrations/xxxx_add_support_level_to_tickets.php` | None |
| 2.4 | Update package `TicketReply` to use `ticket_id` FK | `Models/TicketReply.php` | None |
| 2.5 | Add `HasUuid` trait to package models | `Models/Ticket.php`, `Models/TicketReply.php` | None |
| 2.6 | Add `HasUserStamps` trait to package models | `Models/Ticket.php`, `Models/TicketReply.php` | None |
| 2.7 | Add `description` ↔ `message` field mapping config | `config/tickets.php` | None |

**Risk:** Adding `HasUuid` and `HasUserStamps` to existing tables requires migration changes. Must handle existing data.

### PHASE 3: Licensing & SLA (Week 2-3)
**Goal:** Extract licensing and SLA logic into the package.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 3.1 | Create `TicketsLicensingServiceInterface` | `Contracts/Services/TicketsLicensingServiceInterface.php` | None |
| 3.2 | Create `TicketsLicensingService` | `Services/TicketsLicensingService.php` | 3.1 |
| 3.3 | Create `TicketsSlaServiceInterface` | `Contracts/Services/TicketsSlaServiceInterface.php` | None |
| 3.4 | Create `TicketsSlaService` | `Services/TicketsSlaService.php` | 3.3 |
| 3.5 | Create `LicenseValidationInterface` | `Contracts/Services/LicenseValidationInterface.php` | None |
| 3.6 | Create `TicketsPortalServiceInterface` | `Contracts/Services/TicketsPortalServiceInterface.php` | None |
| 3.7 | Create `TicketsPortalService` | `Services/TicketsPortalService.php` | 3.5, 3.6 |
| 3.8 | Add SLA config to `config/tickets.php` | `config/tickets.php` | None |
| 3.9 | Register bindings in `TicketsServiceProvider` | `Providers/TicketsServiceProvider.php` | 3.1-3.7 |

**Risk:** `LicenseServerService` is an external dependency that must be injected via interface. The package cannot hardcode this.

### PHASE 4: Lifecycle Management (Week 3)
**Goal:** Add full lifecycle management to the Tickets module.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 4.1 | Create `TicketsModuleLifecycleService` | `Services/TicketsModuleLifecycleService.php` | None |
| 4.2 | Implement `install()` with publish + migrate | `Services/TicketsModuleLifecycleService.php` | None |
| 4.3 | Implement `uninstall()` with cleanup | `Services/TicketsModuleLifecycleService.php` | None |
| 4.4 | Implement `activate(int $siteId)` | `Services/TicketsModuleLifecycleService.php` | Phase 3 |
| 4.5 | Implement `deactivate(int $siteId)` | `Services/TicketsModuleLifecycleService.php` | Phase 3 |
| 4.6 | Implement `update()` + `rollback()` | `Services/TicketsModuleLifecycleService.php` | None |
| 4.7 | Register in `TicketsServiceProvider` | `Providers/TicketsServiceProvider.php` | 4.1-4.6 |

### PHASE 5: Frontend Canonicalization (Week 3-4)
**Goal:** Standardize all frontend views to canonical patterns.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 5.1 | Update `index.blade.php` — canonical layout, CSS, icons | `Resources/views/index.blade.php` | None |
| 5.2 | Update `show.blade.php` — canonical chat UI, Alpine.js | `Resources/views/show.blade.php` | None |
| 5.3 | Update `create.blade.php` — canonical form patterns | `Resources/views/create.blade.php` | None |
| 5.4 | Add canonical stat cards component | `Resources/views/partials/ticket-stats.blade.php` | None |
| 5.5 | Add canonical filter bar component | `Resources/views/partials/ticket-filters.blade.php` | None |
| 5.6 | Add canonical chat component | `Resources/views/partials/ticket-chat.blade.php` | None |
| 5.7 | Standardize all icons to `w-5 h-5` | All view files | None |
| 5.8 | Standardize all form fields to `px-4 py-3 rounded-xl` | All view files | None |
| 5.9 | Standardize all cards to `p-6 rounded-2xl shadow-sm` | All view files | None |
| 5.10 | Standardize primary buttons to gradient | All view files | None |

### PHASE 6: Project-Level Migration (Week 4-5)
**Goal:** Migrate Implementation B (saas-platform) to use the package module.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 6.1 | Update `SupportTicketController` to use package contracts | `app/Http/Controllers/Admin/SupportTicketController.php` | Phase 1 |
| 6.2 | Update `SupportTicketService` to extend package service | `app/Services/SupportTicketService.php` | Phase 1 |
| 6.3 | Update `SupportTicketRepository` to extend package repo | `app/Repositories/SupportTicketRepository.php` | Phase 1 |
| 6.4 | Update `SupportTicketReplyRepository` to extend package repo | `app/Repositories/SupportTicketReplyRepository.php` | Phase 1 |
| 6.5 | Update `SupportLicenseService` to extend package service | `app/Services/SupportLicenseService.php` | Phase 3 |
| 6.6 | Update `SupportSlaService` to extend package service | `app/Services/SupportSlaService.php` | Phase 3 |
| 6.7 | Update `SupportPortalService` to extend package service | `app/Services/SupportPortalService.php` | Phase 3 |
| 6.8 | Update views to use `@extends('templates::admin.layout')` | All view files | Phase 5 |
| 6.9 | Remove duplicate contracts from `app/Contracts/` | All interface files | 6.1-6.7 |
| 6.10 | Remove duplicate repositories from `app/Repositories/` | All repository files | 6.3, 6.4 |
| 6.11 | Update routes to point to package controllers | `routes/web.php` | Phase 1 |

### PHASE 7: Customer-Site Migration (Week 5-6)
**Goal:** Migrate Implementation C (customer-site) to use the package module via API client.

| Step | Task | Files | Dependencies |
|------|------|-------|-------------|
| 7.1 | Update `SupportController` to use package API client | `app/Http/Controllers/Admin/SupportController.php` | Phase 1 |
| 7.2 | Update `SupportApiClient` to use package contracts | `app/Services/SupportApiClient.php` | Phase 1 |
| 7.3 | Update views to use canonical patterns | All view files | Phase 5 |
| 7.4 | Remove inline CSS, use canonical classes | All view files | Phase 5 |
| 7.5 | Standardize date/number formatting | All view files | Phase 5 |
| 7.6 | Add SLA breach display (currently hardcoded to 0) | All view files | Phase 3 |

### PHASE 8: Testing & Validation (Week 6)
**Goal:** Verify all implementations work correctly after migration.

| Step | Task | Dependencies |
|------|------|-------------|
| 8.1 | Run package unit tests | All phases |
| 8.2 | Test ticket CRUD in saas-platform | Phase 6 |
| 8.3 | Test ticket CRUD in customer-site | Phase 7 |
| 8.4 | Test licensing enforcement | Phase 3 |
| 8.5 | Test SLA breach detection | Phase 3 |
| 8.6 | Test lifecycle install/uninstall | Phase 4 |
| 8.7 | Test tenant activation/deactivation | Phase 4 |
| 8.8 | Verify frontend canonical patterns | Phase 5 |
| 8.9 | Run Vite build in both projects | All phases |

---

## 3. Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| Data loss during model unification | LOW | HIGH | Run migrations with `--pretend` first, backup DB |
| Breaking changes to existing tickets | MEDIUM | HIGH | Add `message` as accessor, not column rename |
| `LicenseServerService` unavailable | LOW | MEDIUM | Interface-based injection, fallback to config |
| Frontend regressions | MEDIUM | MEDIUM | Visual regression testing, component isolation |
| customer-site Tailwind v4 incompatibility | HIGH | HIGH | Fix customer-site Tailwind pipeline first (see PHASE 6.875) |
| Package not autoloaded | LOW | HIGH | Verify `composer.json` autoload PSR-4 mapping |

---

## 4. Timeline Estimate

| Phase | Duration | Dependencies |
|-------|----------|-------------|
| Phase 1: Package Foundation | ✅ COMPLETED | None |
| Phase 2: Data Model Unification | 3-5 days | Phase 1 |
| Phase 3: Licensing & SLA | 4-6 days | Phase 1 |
| Phase 4: Lifecycle Management | 2-3 days | Phase 3 |
| Phase 5: Frontend Canonicalization | 3-5 days | Phase 1 |
| Phase 6: Project-Level Migration | 4-6 days | Phases 1-5 |
| Phase 7: Customer-Site Migration | 3-5 days | Phases 1-5 |
| Phase 8: Testing & Validation | 3-5 days | All phases |

**Total estimated time: 22-40 days (4-8 weeks)**

---

## 5. Rollback Strategy

If any phase causes issues:

1. **Phase 2-5 (Package changes):** Revert package files via git, run `composer dump-autoload`
2. **Phase 6 (saas-platform):** Revert project files, keep package changes
3. **Phase 7 (customer-site):** Revert project files, keep package changes
4. **Database:** Run down migrations for any schema changes

**Critical rule:** Never deploy Phase 6 and Phase 7 simultaneously. Migrate one project at a time.
