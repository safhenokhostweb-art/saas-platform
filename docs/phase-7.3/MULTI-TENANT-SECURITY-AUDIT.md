# MULTI-TENANT-SECURITY-AUDIT.md
## Phase 7.3 — Multi-Tenant Security Audit
**Date:** 2026-05-25

---

## EXECUTIVE SUMMARY

The platform implements a Reseller-level tenant model with PARTIAL site-level isolation. The TenantContext only resolves Reseller entities — not Sites. Site-level isolation is enforced at the service and repository call-site, not at the container or global-scope level. This creates a security model that is correct-by-convention, not correct-by-construction.

---

## 1. TENANT ISOLATION AUDIT

### Reseller Isolation
**Status: PARTIALLY ENFORCED**

| Layer | Enforcement | Verified |
|-------|-------------|----------|
| Middleware | `DetectResellerFromDomain` + `SetTenantContext` | YES |
| TenantContext | resolves to Reseller from domain | YES |
| `TenantContext::scopeQueryToTenant()` | applies `WHERE reseller_id = ?` | YES |
| Controller usage of scope | depends on developer discipline | UNVERIFIED — no global scope |
| `UserRepository` | has reseller scoping | YES (partially) |
| `CustomerRepository` | has reseller scoping | YES (partially) |

**Gap:** No Eloquent Global Scope on models with `reseller_id`. Scoping is opt-in via `scopeQueryToTenant()`, not automatic.

### Site Isolation (Module-Level)
**Status: MANUALLY ENFORCED, NOT STRUCTURAL**

Site isolation in modules relies on callers always passing `site_id` filters:
- `TicketRepository::search(['site_id' => $x])` — correct only if caller provides it
- `TicketService::getBySiteId(int $siteId)` — correct
- `TicketService::getByIdForSite(int $id, int $siteId)` — correct, has `assertTenantIsolation()`

**Critical gap:** `TicketService::getAll()` → `TicketRepository::search()` with no `site_id` filter returns ALL tickets across ALL sites if called without filter. An admin who calls `getAll([])` sees every tenant's data.

---

## 2. POLICY ENFORCEMENT

### Ticket Policies
- `TicketPolicy` registered for `Ticket::class`
- `TicketReplyPolicy` registered for `TicketReply::class`
- `TicketAdminPolicy` registered as a standalone gate

**Verified in controllers:**
- `TicketController::index()` — `$this->authorize('viewAny', Ticket::class)` ✓
- `TicketController::show()` — verify in actual file (not confirmed)
- `TicketReplyController::store()` — `$this->authorize('reply', $ticket)` ✓
- `TicketStatusController::update()` — `$this->authorize('updateStatus', $ticket)` ✓

**Not verified:** Whether `TicketPolicy::viewAny()` checks reseller/site scope or just auth status.

---

## 3. ROUTE PROTECTION

### Admin Routes
```
Route::middleware(['web', 'auth', 'admin']) → tickets.admin.*
```
**AdminMiddleware** checks `is_admin` boolean OR role (SUPER_ADMIN/STAFF). This is binary — no granular permission per admin action.

### Legacy API Routes — UNSAFE
```
Route::prefix('support-tickets')->middleware('throttle:30,1')
```
Only rate-limited. No auth, no license check. An unauthenticated client can:
- `POST /api/support-tickets` — create tickets with arbitrary site_id
- `GET /api/support-tickets` — retrieve ticket lists
- `GET /api/support-tickets/unread-count` — enumerate ticket activity

### Canonical API Routes — SAFE
```
Route::prefix('tenant/tickets')->middleware(['throttle:30,1', 'tenant.api'])
```
`AuthenticateTenantApi` validates `license_key` + `domain` and attaches `tenant_site` to request. Tenant isolation enforced at middleware layer.

---

## 4. MIDDLEWARE CHAINS

### Web Chain (full)
```
TrustProxies → HandleCors → ValidatePostSize → TrimStrings → ConvertEmptyStrings
→ EncryptCookies → AddQueuedCookiesToResponse → StartSession → ShareErrors
→ VerifyCsrfToken → SubstituteBindings → AddNoindexHeaders
→ DetectResellerFromDomain → SetTenantContext
→ [auth] Authenticate → [admin] AdminMiddleware → Controller
```

**Security gap:** `DetectResellerFromDomain` sets request attribute. `SetTenantContext` reads the same service again. If a domain is spoofed in a `Host:` header, the reseller detection could be manipulated unless the platform validates known reseller domains against a whitelist.

### API Chain
```
ThrottleRequests → SubstituteBindings → [tenant.api] AuthenticateTenantApi
```
**No CSRF** (expected for stateless API). **No session** (expected). **No auth:sanctum** on the public legacy routes — potential security hole.

---

## 5. AUTHORIZATION GAPS

### GAP-1: Legacy Ticket API Has No Auth
`/api/support-tickets/*` has only `throttle:30,1`. No license validation, no session auth, no API key.
**Risk:** Any attacker can enumerate and create tickets for any site_id they guess.

### GAP-2: Ticket Creation Accepts Arbitrary site_id
In `TenantTicketController::store()`, `site_id` comes from the validated `tenant_site` (middleware-resolved). This is SAFE for the canonical route.

But `ApiSupportTicketController::store()` (legacy) — what does it use as site_id? If it reads `site_id` from the request body, any caller can create tickets under any site.

### GAP-3: Admin ticket search with no site_id returns all sites' data
If an admin accidentally calls the tickets index without a site filter, they receive tickets from ALL customer sites. This is a data isolation failure in multi-site setups.

### GAP-4: No Rate Limiting on Admin Routes
Admin routes (`/admin/*`) have no rate limiting. Brute-force against `/admin/login` or bulk admin operations are unconstrained.

### GAP-5: Mass Assignment
Ticket `create` in `TenantTicketController` uses `$validated` array passed directly to `$this->ticketService->create()`. The service merges `site_id` from the middleware-resolved site. This appears safe — but requires the service to always override `site_id` from the validated input, which must be verified.

---

## 6. SITE_ID ENFORCEMENT ANALYSIS

| Component | site_id Source | Enforcement | Safe? |
|-----------|---------------|-------------|-------|
| `TenantTicketController::store()` | `$request->attributes->get('tenant_site')->id` | Middleware | YES |
| `TenantTicketController::index()` | `$request->attributes->get('tenant_site')->id` | Middleware | YES |
| `ApiSupportTicketController` (legacy) | UNKNOWN — not verified | None confirmed | RISK |
| `TicketController::index()` (admin) | `$filters['site_id']` from request query | No mandatory scope | RISK |
| `TicketService::getAll()` | Caller-provided filter | Not mandatory | RISK |
| `FormSiteService` | `site_id` parameter | Service layer | MEDIUM |

---

## 7. UNSAFE QUERY SCOPES

### No Global Scopes on Multi-Tenant Models
The following models have no Eloquent Global Scope enforcing tenant isolation:
- `Ticket` — no global scope for `site_id`
- `Form` — no global scope for `site_id`
- `SupportTicket` (legacy) — no global scope for `site_id`

This means any `Ticket::all()`, `Ticket::find(1)`, or `Ticket::where(...)` without explicit `site_id` filter will return cross-tenant data.

---

## 8. UNSAFE CACHE KEYS

No tenant-scoped cache keys verified. If caching is used (e.g., in UserRepository), the cache key must include `reseller_id` or `site_id` as a prefix. Without this, cached data from one tenant can be served to another on the same cache store.

Example risk: If `UserRepository` caches results with a key like `users:admin_list`, and reseller A and reseller B share the same Redis instance, reseller A's cached admin list could be returned to reseller B's requests.

---

## 9. TENANT-AWARE EVENTS

`TicketCreated`, `TicketReplied`, `TicketStatusChanged` events are dispatched from `TicketService`.
- Do these events carry `site_id`? — Not confirmed from code review.
- Do event listeners scope to the correct tenant? — Not confirmed.
- If events trigger notifications (email), do they use tenant-specific templates? — `TicketNotificationService` exists but internal implementation not fully verified.

---

## 10. FILE UPLOAD ISOLATION

**File:** `TicketService::handleFileUploads()` — exists but content not audited.
**Risk:** If attachments are stored to a shared disk without a `{site_id}/` prefix in the path, attachments from one site are accessible to users of other sites who guess the URL.

**The storage serving route** in `routes/web.php`:
```php
Route::get('storage/{path}', function (string $path) {
    // Path traversal check: replaces '..' and '\' 
    $path = str_replace(['..', '\\'], ['', '/'], $path);
```
Path traversal is partially mitigated but the file is served without any access control check. Any authenticated (or unauthenticated) user who knows a file path can access it.

---

## 11. API EXPOSURE

| Endpoint | Auth | Tenant Scoped | Notes |
|----------|------|---------------|-------|
| `GET /api/site-config` | throttle only | NO | Public — exposes site config without auth |
| `GET /api/products` | throttle only | NO | Public product listing |
| `POST /api/license/validate` | throttle only | N/A | License validation — appropriate |
| `GET /api/updates/check` | throttle only | NO | Version info — low risk |
| `GET /api/tenant/tickets` | tenant.api | YES | SAFE |
| `POST /api/tenant/tickets` | tenant.api | YES | SAFE |
| `GET /api/support-tickets` | throttle only | NO | CRITICAL — unauthenticated read |
| `POST /api/support-tickets` | throttle only | NO | CRITICAL — unauthenticated write |
| `GET /api/tenant-marketplace/modules` | throttle only | NO | Module list without auth |

---

## MULTI-TENANT SECURITY SCORE

| Category | Score | Notes |
|----------|-------|-------|
| Reseller isolation | 65/100 | Works for domain-based, no global scopes |
| Site isolation | 40/100 | Manual, convention-based, no structural guarantee |
| API authentication | 30/100 | Legacy routes unauthenticated |
| Route protection | 60/100 | Admin routes protected, legacy API not |
| Policy enforcement | 65/100 | Policies exist but coverage incomplete |
| File upload isolation | UNKNOWN | Not auditable without full implementation review |
| Cache isolation | UNKNOWN | No evidence of tenant-scoped cache keys |

**Overall Multi-Tenant Safety Score: 45/100**
