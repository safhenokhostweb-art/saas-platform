# STRATEGIC-ROADMAP.md
## Phase 7.3 — Strategic Roadmap
**Date:** 2026-05-25

This roadmap is based on AUDITED reality, not intended architecture. Phases are ordered by:
1. Security risk reduction (highest priority)
2. Blocking distributiblity fixes
3. Scaling enablement
4. Marketplace readiness

---

## PHASE 8.0 — SECURITY HARDENING (BEFORE ANY PRODUCTION TRAFFIC)

**Risk Level:** CRITICAL — do this first  
**Architectural Impact:** HIGH  
**Dependencies:** None — these are self-contained fixes

### 8.0-1: Remove Unauthenticated Legacy Ticket API
- Add `tenant.api` middleware to `/api/support-tickets/*` routes OR remove entirely
- Rationale: Public unauthenticated write/read of any site's tickets is a critical security hole
- **Breaking change risk:** Medium — customer-site may still call legacy routes. Verify via access logs first.

### 8.0-2: Move License Key from POST Body to Authorization Header
- Change `AuthenticateTenantApi` to read `Authorization: Bearer <license_key>` header
- Update customer-site API client (`SupportApiClient`) to send the header
- Rationale: Credentials in POST body appear in server logs
- **Breaking change risk:** High — must coordinate platform + customer-site deploy simultaneously

### 8.0-3: Add Eloquent Global Scopes for Tenant Isolation
- Add `site_id` Global Scope to `Ticket`, `Form`, `FormSubmission` models
- Add `reseller_id` Global Scope to `Customer`, `Site` models
- Rationale: Structural isolation instead of convention-based isolation
- **Breaking change risk:** Medium — all existing queries must be verified to not break

### 8.0-4: Storage File Access Control
- The `Route::get('storage/{path}')` route serves files without access checks
- Add auth check: only the owner/admin or the site that uploaded can access the file
- Rationale: Attachments from one tenant accessible to unauthenticated users knowing the path

---

## PHASE 8.1 — PACKAGE BOUNDARY REPAIR (PREREQUISITE FOR DISTRIBUTION)

**Risk Level:** HIGH  
**Architectural Impact:** VERY HIGH  
**Dependencies:** Phase 8.0 complete

### 8.1-1: Define Platform Contracts in Core-Utils
Create in `packages/saas-platform/core-utils/src/Contracts/Platform/`:
- `SiteInterface { getId(): int; getDomain(): string; }`
- `UserInterface { getId(): int; getName(): string; }`
- `ModuleInterface { getSlug(): string; isEnabled(SiteInterface $site): bool; }`
- `CaptchaServiceInterface`
- `ActivityLoggerInterface`
- `ModuleLicenseServiceInterface`

### 8.1-2: Update Tickets Module to Use Contracts
Replace in `modules/tickets/src/`:
- `use App\Models\Site` → `use SaaSPlatform\CoreUtils\Contracts\Platform\SiteInterface`
- `use App\Models\User` → `use SaaSPlatform\CoreUtils\Contracts\Platform\UserInterface`
- `use App\Http\Controllers\Controller` → `use SaaSPlatform\CoreUtils\Http\Controllers\BaseController`
- `use App\Services\TenantContext` → `use SaaSPlatform\CoreUtils\Contracts\TenantContextContract`

### 8.1-3: Update Forms Module to Use Contracts
Replace ALL `App\` imports in forms module with contract equivalents (14+ files).

### 8.1-4: Create BaseController in Core-Utils
```php
namespace SaaSPlatform\CoreUtils\Http\Controllers;
use Illuminate\Routing\Controller;
class BaseController extends Controller { ... }
```
This gives modules a non-platform-bound base controller.

### 8.1-5: Remove Dual Ticket System
- Remove `App\Contracts\Services\SupportTicketServiceInterface` binding from `AppServiceProvider`
- Remove `App\Services\SupportTicketService` class (or archive it)
- Remove `App\Repositories\SupportTicketRepository` binding
- Verify legacy `SupportTicket` model is no longer used
- **Risk:** Verify legacy API routes (even deprecated ones) don't call the legacy service

---

## PHASE 8.2 — SEMANTIC VERSIONING & PRIVATE PACKAGIST

**Risk Level:** HIGH  
**Architectural Impact:** HIGH  
**Dependencies:** Phase 8.1 complete (packages must be clean before releasing)

### 8.2-1: Tag Initial Versions
- `core-utils` → tag `v1.0.0`
- `modules/tickets` → tag `v1.0.0`
- `modules/forms` → tag `v1.0.0`

### 8.2-2: Set Up Satis (Private Packagist)
- Create `satis.json` pointing to each package repository
- Build and serve the Satis JSON feed
- Update `composer.json` to use Satis URL instead of `path` repositories

### 8.2-3: Update Version Constraints
- Change `@dev` to `^1.0` in all `composer.json` files
- Add `composer.lock` to git for each customer-site deployment

### 8.2-4: Establish Release Process
- Create a `RELEASING.md` documenting the tagging and Satis rebuild process
- Wire `ModuleVersionCommand` and `ModuleReleaseCommand` into the release workflow

---

## PHASE 8.3 — TENANT CONTEXT ELEVATION

**Risk Level:** MEDIUM  
**Architectural Impact:** HIGH  
**Dependencies:** Phase 8.1 (contracts needed)

### 8.3-1: Extend TenantContext to Resolve Sites
Current `TenantContext` resolves Resellers only. Extend to resolve Sites when:
- A request has a `site_id` parameter (for admin contexts)
- A request is authenticated via `tenant.api` (set `tenant_site` attribute → TenantContext)

### 8.3-2: Site-Level Singleton Context
Make the authenticated site available through `TenantContextContract` so that modules can call `$tenantContext->currentSite()` rather than reading `$request->attributes->get('tenant_site')` — which is a controller-layer detail leaking into services.

### 8.3-3: Fix SetTenantContext + DetectResellerFromDomain Redundancy
Merge both middleware into one, or cache the reseller lookup result across the middleware pipeline.

---

## PHASE 8.4 — PRODUCTION HARDENING

**Risk Level:** MEDIUM  
**Architectural Impact:** MEDIUM  
**Dependencies:** Phase 8.0, 8.1

### 8.4-1: Fix Production Asset Registration Bug
Remove the `if (app()->environment('production')) { return; }` guard from `TicketsServiceProvider::registerModuleAssets()`.

### 8.4-2: Database Indexes
Add explicit indexes to migrations:
- `tickets.site_id` (required for tenant isolation queries)
- `tickets.status` (common filter)
- `tickets.priority` (common filter)
- `form_submissions.form_id`
- `form_submissions.site_id`
- Full-text index on `tickets.subject`, `tickets.description`

### 8.4-3: Cache Reseller Domain Resolution
Add Redis/cache layer in `ResellerDetectionService::fromRequest()`:
```php
Cache::remember("reseller:domain:{$host}", 300, fn() => ...DB query...);
```

### 8.4-4: Ticket Stats Caching
Cache `TicketService::getStats()` with a 30-second TTL.

### 8.4-5: Queue Configuration
- Configure queue worker for notifications
- Ensure `TicketNotificationService` dispatches jobs to queue, not synchronous
- Document `supervisor` configuration for production workers

### 8.4-6: Health Check Endpoint
Create `GET /health` endpoint returning JSON with:
- DB connectivity status
- Redis connectivity status
- Queue worker status
- License server reachability
- Package versions

---

## PHASE 8.5 — FRONTEND CANONICAL CONSOLIDATION

**Risk Level:** LOW-MEDIUM  
**Architectural Impact:** MEDIUM  
**Dependencies:** Phase 8.1

### 8.5-1: Delete Legacy Views
- Delete `resources/views/admin/support-tickets/_legacy/`
- Delete `resources/views/admin/forms/_legacy/`
- Delete `resources/views/customer/support-tickets/` (redirect chain handles routing)
- Delete `resources/views/admin/support-tickets/` (replaced by package views)

### 8.5-2: Enforce Canonical Components
All admin module views must use:
- `<x-core-ui::stats-card>` instead of inline stat card HTML
- `<x-core-ui::status-badge>` instead of inline badge CSS
- `<x-core-ui::filter-bar>` instead of inline filter markup
- `<x-core-ui::empty-state>` instead of custom empty state HTML
- `<x-core-ui::table-pagination>` for all pagination

### 8.5-3: Tailwind Config - Include Package Paths
Add to `tailwind.config.js` content:
```js
content: [
    './resources/**/*.blade.php',
    './packages/saas-platform/*/resources/**/*.blade.php',
    './packages/saas-platform/modules/*/Resources/**/*.blade.php',
]
```

### 8.5-4: Move Inline CSS to Compiled Assets
Extract all `<style>` blocks from admin views into Tailwind components or a `resources/css/admin.css` file.

---

## PHASE 8.6 — CI/CD FOUNDATION

**Risk Level:** LOW  
**Architectural Impact:** HIGH for sustainability

### 8.6-1: GitHub Actions (or equivalent) Pipeline
- `lint` job: Laravel Pint
- `test` job: `php artisan test` (requires test DB setup)
- `package-test` job: Test each package independently
- `security` job: Dependency vulnerability scan

### 8.6-2: Package-Level Test Suites
Create `tests/` directories in each package:
- `packages/saas-platform/core-utils/tests/`
- `packages/saas-platform/modules/tickets/tests/`
- `packages/saas-platform/modules/forms/tests/`

### 8.6-3: Drift Automation
Wire `DriftScanCommand` and `ValidateAuthorityCommand` into CI so package boundary violations are caught before merge.

---

## PHASE 9.0 — MARKETPLACE READINESS

**Risk Level:** LOW  
**Architectural Impact:** HIGH  
**Dependencies:** All Phase 8.x complete

### 9.0-1: Standard Module Manifest
Define `composer.json` `extra` section for SaaS platform modules:
```json
"extra": {
    "saas-platform": {
        "module": {
            "slug": "tickets",
            "version": "1.0.0",
            "min_platform": "1.0.0",
            "requires_migrations": true,
            "lifecycle": "SaaSPlatform\\Modules\\Tickets\\Services\\TicketModuleLifecycle"
        }
    }
}
```

### 9.0-2: Shared ModuleLifecycleInterface in Core-Utils
Move `ModuleLifecycleInterface` from individual module namespaces to:
`SaaSPlatform\CoreUtils\Contracts\ModuleLifecycleInterface`

Both modules implement this shared interface.

### 9.0-3: Module Installation Sandboxing
Before running module migrations, validate:
- Required platform version
- Required PHP extensions
- No conflicting module slugs
- Migration rollback plan exists

### 9.0-4: Auto-Update Architecture
The `ModuleUpdateOrchestrator` exists. Wire it to:
- Satis feed for new version detection
- Automated `composer update <package>` in a staging environment
- Health check validation post-update before promoting to production

---

## PHASE 9.1 — BILLING & MONITORING INTEGRATION

**Dependencies:** Phase 9.0 complete

### 9.1-1: Monitoring
- Integrate Laravel Telescope (dev) + Sentry/Datadog (production)
- Add `site_id` context to all error reports
- Alert on: failed license validations, ticket SLA breaches, queue depth spikes

### 9.1-2: Billing Hooks
- `EntitlementLedger` model exists — wire to actual billing events
- Stripe/payment gateway integration for module subscriptions

---

## RECOMMENDED NEXT PHASE ORDER

```
Phase 8.0 → Security (IMMEDIATE, before any production traffic)
Phase 8.1 → Package Boundaries (BEFORE any distributed deployment)
Phase 8.2 → Versioning (BEFORE first external customer-site install)
Phase 8.3 → Tenant Context Elevation (BEFORE scaling to 10+ sites)
Phase 8.4 → Production Hardening (BEFORE public launch)
Phase 8.5 → Frontend Consolidation (Parallel with 8.4)
Phase 8.6 → CI/CD (Start immediately, complete by Phase 8.4)
Phase 9.0 → Marketplace (After stable production)
Phase 9.1 → Billing/Monitoring (With Phase 9.0)
```

---

## FREEZE/REFACTOR POINTS

### FREEZE — Do Not Add New Features Until Fixed
- Do not add new modules until package boundary violations (Phase 8.1) are fixed
- Do not deploy to production until security hardening (Phase 8.0) is complete
- Do not distribute packages until semantic versioning (Phase 8.2) is established

### REFACTOR — Technical Debt to Address
- Dual ticket system: eliminate legacy `SupportTicketService`
- 34+ singletons at boot: make most validation services lazy-loaded
- `_legacy` view directories: delete
- Inline CSS in admin views: extract to compiled assets
- Redundant middleware: merge DetectResellerFromDomain + SetTenantContext
