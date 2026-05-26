# DISTRIBUTED-PRODUCTION-READINESS.md
## Phase 7.3 — Production Distributed Deployment Audit
**Date:** 2026-05-25

**Assumed topology:**
- Server A: saas-platform (platform authority)
- Server B: customer-site-1
- Server C: customer-site-2
- Server D: customer-site-3

---

## REAL BLOCKERS

### BLOCKER-1 — Package Versioning Is @dev Only
**Status:** BLOCKS PRODUCTION  
All packages use `@dev` version constraints. There is no:
- Semantic version tagging (`v1.0.0`)
- Git tag-based release management
- Version pinning in consuming projects
- Rollback-by-version capability

`composer update` on any server can silently pull breaking changes. Two customer-sites can run different package code after independent deploys with no way to detect divergence.

**Fix required:** Tag each package release. Switch to `^1.0` constraints in all `composer.json` files.

---

### BLOCKER-2 — Packages Use Local Path Repositories
**Status:** BLOCKS DISTRIBUTED DEPLOYMENT  
`composer.json` declares:
```json
"repositories": [
    {"type": "path", "url": "packages/saas-platform/core-utils"},
    {"type": "path", "url": "packages/saas-platform/modules/tickets"},
    {"type": "path", "url": "packages/saas-platform/modules/forms"}
]
```
Local `path` repositories work ONLY when the package directory exists on the same filesystem as the consuming project. Customer-sites on Servers B-D cannot consume these packages via path repositories — they need a private Packagist or VCS repository.

**Fix required:** Set up private Packagist (Satis/Repman/Packagist Pro) or GitHub Packages before any distributed deployment.

---

### BLOCKER-3 — Packages Import Platform App Classes Directly
**Status:** BLOCKS DISTRIBUTED DEPLOYMENT  
`modules/tickets` and `modules/forms` import:
- `App\Models\Site`
- `App\Models\User`
- `App\Services\TenantContext`
- `App\Services\CaptchaService`
- etc.

Customer-site cannot install these packages unless it has ALL of these platform app classes in its `App\` namespace at the exact same paths. customer-site does NOT have `App\Models\Site` — it has its own model structure.

**Fix required:** Replace all `App\` imports with contract interfaces defined in core-utils.

---

### BLOCKER-4 — No Migration Ordering Strategy Across Packages + App
**Status:** BLOCKS PRODUCTION  
The platform has:
- 125 migrations in `database/migrations/` (app-level)
- N migrations in `packages/saas-platform/modules/tickets/Database/Migrations/`
- N migrations in `packages/saas-platform/modules/forms/Database/Migrations/`

Package migrations run via `loadMigrationsFrom()` which auto-discovers them. However:
1. Ordering between app migrations and package migrations is undefined
2. Package migrations reference tables (`sites`, `users`) that must exist from app migrations
3. Running `php artisan migrate` on a fresh DB may fail if package migrations execute before app tables exist

**Fix required:** Add explicit `after` constraints or a documented `migrate:fresh` sequence.

---

### BLOCKER-5 — License Credentials in POST Body Appear in Logs
**Status:** SECURITY BLOCKER  
`AuthenticateTenantApi` reads `license_key` from `$request->input('license_key')`. This means:
- Access logs record the full POST body on debug configurations
- Reverse proxies (nginx) may log request bodies
- `dd()` / `dump()` during debugging exposes license keys
- Security scanners will flag this

**Fix required:** Move `license_key` to `Authorization: Bearer` header.

---

## UNSAFE ASSUMPTIONS

### ASSUMPTION-1 — customer-site Is a "Thin Tenant Runtime"
**Reality:** customer-site has 8231 PHP files, its own:
- Builder system (ElementRegistry, 12+ Element types)
- Admin panel (AdminMenu, AdminSidebarTheme helpers)
- Shop/Cart/Orders subsystem
- Custom middleware chain (EnsureLicenseValid, CheckInstalled)
- Own TenantContext (bound to License model, not Reseller model)
- Own AppServiceProvider (binds Forms module)

This is a FULL standalone Laravel application that happens to call the platform API. It cannot be "updated" by updating the platform.

### ASSUMPTION-2 — All Migrations Are Idempotent
**Reality:** No migration rollback strategy is documented. `TicketRollbackService` exists in tickets module but it's a custom rollback service, not a standard Laravel rollback. If a migration fails mid-deploy on a production DB, recovery path is unclear.

### ASSUMPTION-3 — Module Enable/Disable Is Real-time
**Reality:** Module enablement is a DB flag in `site_modules` pivot. The customer-site reads module state from the platform API at startup or cached. There's no push mechanism — tenant sites may continue showing disabled modules until their config cache refreshes.

### ASSUMPTION-4 — TenantContext Is Cleared Between Requests
**Reality:** `SetTenantContext` calls `$this->tenantContext->clear()` before AND after processing. However, if a PHP worker has a stuck exception between `set()` and `clear()`, the context persists to the next request on the same worker. The singleton lifecycle on FPM is request-scoped but on Octane/Swoole would be GLOBAL.

---

## HIDDEN LOCAL-DEVELOPMENT DEPENDENCIES

1. **Laragon Windows environment** — storage paths use Windows-style separators in some places
2. **path repositories** — only work on the developer's local machine
3. **No `.env.example` for production** — no documented required environment variables for distributed deployment
4. **Storage symlinks** — custom storage serving route exists (`Route::get('storage/{path}', ...)`) suggesting `php artisan storage:link` is not set up or unreliable
5. **Verta (Persian date)** — hardcoded Jalali date library in forms module. Production servers need this even if the platform is used by non-Persian customers.

---

## ROLLBACK RISKS

| Scenario | Risk Level | Notes |
|----------|------------|-------|
| Package version rollback | EXTREME | No versions exist. Must use git SHA. No `composer.lock` guarantees. |
| DB migration rollback | HIGH | Package migrations may roll back before app migrations they depend on |
| Ticket data migration rollback | HIGH | `TicketRollbackService` exists but is not wired to any automated process |
| Asset publishing rollback | MEDIUM | `php artisan vendor:publish` is not reversible. Stale published assets may remain. |
| Config cache after package update | MEDIUM | `php artisan config:cache` must be re-run after any package config change |
| Queue rollback | MEDIUM | In-flight jobs dispatched with old payload shapes may fail with new code |

---

## DEPLOYMENT ORDERING REQUIREMENTS

For a SAFE first production deployment:
```
1. Deploy saas-platform (Server A)
2. Run: composer install (no --dev in production)
3. Run: php artisan migrate (app + package migrations)
4. Run: php artisan config:cache
5. Run: php artisan route:cache
6. Run: php artisan view:cache
7. Run: php artisan vendor:publish --tag=tickets-assets
8. Run: php artisan vendor:publish --tag=core-ui-views
9. Deploy customer-site (Servers B-D) AFTER platform is healthy
10. customer-site: verify license key resolves against Server A API
```

Steps 1-8 have NEVER been tested in distributed mode. Step 10 requires Server A to be reachable from Servers B-D — no network topology documentation exists.

---

## ZERO-DOWNTIME READINESS

**Current status: NOT READY**

Required for zero-downtime:
- [ ] Blue-green or rolling deployment strategy — not documented
- [ ] Database migrations must be backward-compatible with old code — not verified
- [ ] Queue workers must drain before deploy — no supervisor configuration documented
- [ ] Cache warming strategy — not implemented
- [ ] Health check endpoints — not implemented
- [ ] Asset versioning for CDN cache busting — partially implemented via `module-assets.php` but not production-hardened
