# OPEN TASKS
> Current epics, features, tasks, status, dependencies, and acceptance criteria.
> Last Updated: 2026-06-06

---

## COMPLETED EPIC: EDU-16 — Human Review & Approval Workflow ✅

**Status:** COMPLETE (2026-06-06)  
**Architectural note:** `pending_review` status eliminated. Final state machine: `draft → published (approve) | archived (reject)`.  
**Delivered:**
- Migration: `reviewed_by_id`, `reviewed_at`, `review_note` + composite index
- `POST .../approve` + `POST .../reject` with Gate + policy checks
- `EducationDraftReviewService` bound via `EducationDraftReviewServiceInterface`
- Admin review dashboard (RTL, Vazirmatn, FIFO queue, Alpine inline actions)
- `EducationGeneratedContentController`: removed `pending_review` key; analytics uses `reviewed_at IS NOT NULL`
- 30 new tests (DraftReviewServiceTest + ReviewEndpointTest)
- KI-001: confirmed wired in all 4 commands from EDU-15.2

---

## CERTIFIED EPIC: EDU-17 — Live AI Provider Integration ✅

**Status:** CERTIFIED COMPLETE (2026-06-07)  
**Certification:** EDU17_CERTIFIED_COMPLETE  
**Tests:** 57 passed / 0 failed / 77 assertions  
**Validation score:** 100/100

### Commit Evidence

| Phase | Commit | Description |
|-------|--------|-------------|
| Phase 2 — Circuit Breaker | `da0cbbc` | feat(edu-17): add circuit breaker and Persian prompt builder |
| Phase 3 — Persian Prompt Builder | `da0cbbc` | feat(edu-17): add circuit breaker and Persian prompt builder |
| Phase 4 — TenantAiQuota + Integration | `18e218c` | feat(edu-17): complete AI provider integration — Phases 2-5 |
| Phase 5 — Tests (mocked HTTP) | `18e218c` | feat(edu-17): complete AI provider integration — Phases 2-5 |

### Task List

| # | Task | Status |
|---|------|--------|
| 1 | Implement real AI provider adapter (Claude + OpenAI) for education | ✅ Done (prev session) |
| 2 | Wire `EDUCATION_AI_PROVIDER` env to `EducationServiceProvider` binding | ✅ Done (prev session) |
| 3 | Connect to CircuitBreakerAiContentProvider (CLOSED/OPEN/HALF_OPEN) | ✅ Done (prev session) |
| 4 | Set rate limits: `TenantAiQuotaManager` integration | ✅ Done (2026-06-07) |
| 5 | Test real generation round-trip with mocked HTTP (no live API calls in CI) | ✅ Done — 57/57 pass |

### Delivered

- `education.ai.quota.*` config section added (`EDUCATION_AI_QUOTA_ENABLED`, `EDUCATION_AI_QUOTA_DEFAULT_DAILY`)
- `CircuitBreakerAiContentProvider`: optional `TenantAiQuotaManager` injected — quota check before circuit, increment after success
- `ClaudeAiContentProvider`: real Claude API HTTP adapter
- `PersianEducationPromptBuilder`: Persian-language prompt construction
- `EducationServiceProvider`: env-driven provider selection, `TenantAiQuotaManager` injected when quota enabled
- `TenantAiQuotaIntegrationTest.php`: 5 tests (quota disabled, within limit, exceeded, key format, null siteId)
- 57/57 AI provider tests pass (CircuitBreaker + Claude HTTP + Quota + NullProvider)

### Technical Debt — EDU-17

| ID | Severity | Description | Action |
|----|----------|-------------|--------|
| M-01 | LOW | `education:validate` does not check EDU-17-specific components (ClaudeAiContentProvider, CircuitBreaker config, PersianEducationPromptBuilder registration, EDU-17 test presence) | Add EDU-17 checks to validation command in a future maintenance pass — do NOT reopen EDU-17 |

---

## COMPLETED: Subscription UX — Auto-renew Toggle + Post-expiry CTA ✅ (2026-06-08)

**Auto-renew:** toggleAutoRenew() controller method + route + toggle button in show/index views (badge).
**Post-expiry:** amber warning bar on index + expired CTA card on show page + "تمدید" row button in history.
**Admin CSV:** already existed; verified.
**Tests:** 13/13 pass.

---

## COMPLETED: Invoice PDF + Subscription Webhook ✅ (2026-06-08)

**Invoice PDF:** barryvdh/laravel-dompdf, RTL template, front+admin download routes. 5/5 tests.
**Subscription Webhook:** webhook_endpoints + webhook_events tables, WebhookDispatchService, DispatchWebhookJob (HMAC-signed, 3 retries), SubscriptionService integration (6 events), admin CRUD UI. 16/16 tests.
**Time-based Coupons:** Already complete — starts_at/expires_at exist and are validated.
**Total tests:** 406/406 pass.

---

## COMPLETED: 8.3b DB Backup + 52 Test Fixes ✅ (2026-06-08)

**8.3b:** BackupDatabaseCommand — daily 02:00, retention, gzip, integrity check. 8/8 tests.
**Test fixes:** 52 → 0 failures — Tag::posts(), coupons view, CaptchaService static calls, customer_mobile, SoftDeletes migrations (tags, forms), FormService slug, FormController redirect, FormFieldRequest validation, forms breadcrumb+guide.
**Result:** 385/385 pass, 0 failures, installed.lock preserved.

---

## COMPLETED: Admin Subscription Panel ✅ (2026-06-07)

**Status:** COMPLETE — 12/12 tests, installed.lock fix delivered
- force-extend (N days) + manual plan-change + group member remove
- Tests: AdminSubscriberActionsTest — 12 passed / 32 assertions
- Bug fixed: TestCase.php now preserves installed.lock across test runs

---

## COMPLETED: SaaS Infrastructure Foundation ✅ (2026-06-08)

**Infrastructure Audit + Readiness:**
- 23 findings (CRITICAL/HIGH/MEDIUM/LOW) — see `docs/infrastructure/INFRASTRUCTURE_READINESS_REPORT.md`
- CORS fixed (F1.3) — now env-driven via `CORS_ALLOWED_ORIGINS`
- SMS failover: KavenegarGateway + GhasedakGateway + FailoverSmsGateway deployed
- 5 deployment scenarios designed — see `docs/infrastructure/DEPLOYMENT_ARCHITECTURE.md`

**Infrastructure Management Module (admin panel):**
- 5 migrations: infra_servers, infra_domains, infra_hosting, infra_storage_providers, infra_database_servers
- 5 models, 6 controllers, 14 Blade views (RTL, Persian, professional)
- 31 routes under `admin/infrastructure/*`
- Menu: group `زیرساخت / DevOps` in admin sidebar

**Remaining from audit (open):**
- F2.1: PlatformSettings.php — use Storage facade for logo uploads (INFRA-2)
- F2.3–F2.6: CSV export services — use Storage streams (INFRA-2)
- F2.2: InstallController — .env file_put_contents (INFRA-3)
- `UPLOAD_DISK` / `EXPORT_DISK` env vars (INFRA-2)
- Deploy migrations to production: `php artisan migrate` (5 new tables)

---

## COMPLETED: CACHE-1 — Redis Cache Architecture ✅ (2026-06-08)

**Status:** CERTIFIED COMPLETE  
**Tests:** 15/15 CacheManager tests pass. Zero regressions from CACHE-1.

**Delivered:**
- `app/Services/Infrastructure/CacheManager.php` — central cache abstraction
  - `key($name, ?$siteId)` — tenant-isolated key builder
  - `store($name)` — named store access
  - `remember/forget/put/get/flushStore` — per-store ops (never global flush)
- `config/database.php` — 7 new Redis connections (DB2–DB8) for named stores
- `config/cache.php` — 6 named stores: users(DB4), customers(DB5), themes(DB6), tickets(DB7), modules(DB8), rate_limit(DB3)
- Eliminated all `Cache::flush()` calls — replaced with `Cache::store('X')->flush()`
- Migrated all cache `remember/forget` calls to named stores:
  - `UserRepository` + `UserService` → `Cache::store('users')`
  - `CustomerRepository` + `CustomerService` → `Cache::store('customers')`
  - `SupportTicketRepository` → `Cache::store('tickets')`
  - `ModuleFeatureService` → `Cache::store('modules')`
  - `ThemeProfileManager` → `Cache::store('themes')`
- `tests/Feature/Infrastructure/CacheManagerTest.php` — 15 tests

**Architecture:** Each named store = own Redis DB → `flushStore('users')` only flushes DB4, never touches DB5+ 

---

## COMPLETED: Platform Site Agent ✅ (2026-06-11)

**ادمین پلتفرم در سایت مشتری — کامل شد**

- `platform_site_agents` جدول در saas-platform
- `PlatformSiteAgentService` — اتصال داینامیک به DB مشتری + provision/revoke/log
- UI کامل در `/admin/sites/{site}/agent`
- `is_platform_agent` column در customer-site + UserObserver (deletion guard)
- لاگ دوطرفه: platform (activity_logs) + customer (admin_activity_log)
- نمایش رمز عبور (AJAX, یک‌بار) + بازنشانی رمز
- دکمه "ادمین پلتفرم" در صفحه ویرایش سایت

**Migration لازم:**
```bash
# saas-platform:
php artisan migrate

# customer-site (هر سایت مشتری باید اجرا شود):
php artisan migrate
```

---

## INFRASTRUCTURE BACKLOG

### Phase 8.x Remaining

| ID | Task | Priority |
|----|------|----------|
| 8.3a | Private Satis/Packagist for package distribution | ✅ DONE (2026-06-11) |
| 8.3b | Automated DB backup before deploy | ✅ DONE (2026-06-11) |
| 8.3c | GitHub Actions CI pipeline | ✅ DONE (2026-06-07) |
| 8.3d | Slack webhook alerting for deployment/anomaly events | ✅ DONE (2026-06-11) |
| 8.4a | DB indexes on site_id columns (all tables) | ✅ DONE (2026-06-07) |
| 8.4b | Cache for domain resolution in TenantContext | ✅ DONE (CACHE-1) |
| 8.4c | External alerting: critical anomaly → Slack | ✅ DONE (2026-06-11) — via SlackAlertService |
| 8.4d | OpenTelemetry export to Jaeger/Tempo | ✅ DONE (2026-06-11) |
| CACHE-1 | Redis named stores + CacheManager + no global flush | ✅ DONE (2026-06-08) |

### Infrastructure Remediation Backlog (from INFRA audit 2026-06-08)

| ID | Task | Priority |
|----|------|----------|
| INFRA-1 | Production .env setup checklist + run 5 new migrations | Critical |
| INFRA-2 | Storage facade migration (PlatformSettings, CSV exports) | ✅ DONE (pre-existing + verified 2026-06-11) |
| INFRA-3 | InstallController .env safety + LocalAI endpoint guard | ✅ DONE (2026-06-11) |
| INFRA-4 | DB backup command + scheduler integration | ✅ DONE (2026-06-11) |
| INFRA-5 | SMS delivery statistics dashboard | ✅ DONE (2026-06-11) |
| INFRA-6 | Remote Agent Layer (Scenario E) — design only complete | Future |

---

## MOBILE BACKLOG

| ID | Task | Priority |
|----|------|----------|
| M2.1-a | File attachment upload flow | Medium |
| M2.1-b | iOS code signing pipeline | ✅ DONE (2026-06-11) |
| M2.1-c | Firebase FCM injection (optional, not required) | Low |
| M2.1-d | Deep-link routing | Medium |
| M2.1-e | AppImage packaging (Linux) | Low |
| M2.1-f | System tray icon assets | Low |

---

## FRONTEND BACKLOG

| ID | Task | Priority |
|----|------|----------|
| FE-01 | Wire x-core-ui::navigation-item/sidebar-group into layout files | ✅ DONE (2026-06-11) |
| FE-02 | Retrofit admin views to x-core-ui::button | Low |
| FE-03 | Resolve Tailwind v4 shadow-var optimizer warnings | Low |
| FE-04 | Remaining raw controls in forms/analytics, forms/create, forms/submissions | Low |
| FE-05 | Retrofit tickets/show to x-core-ui::button | Low |

---

## EDUCATION MODULE COMMANDS REFERENCE

All Artisan commands available for the education module:

```
education:validate               # Score module health (target: ≥ 95/100)
education:coverage               # Coverage report
education:adopt                  # Adoption analytics
education:generate-content       # Generate articles from content deficit queue
education:generate-gap-content   # Generate articles from knowledge gap queue
education:generate-articles      # ArticleDraftGenerator pipeline
education:generate-walkthroughs  # WalkthroughDraftGenerator pipeline
education:refresh-recommendations # Expire stale + regenerate
education:build-gap-queue        # Build critical/high gap remediation queue
education:build-content-deficit-queue # Build content deficit queue
education:sync-customer {id}     # Sync one customer site
education:sync-all-customers     # Sync all active sites
education:verify-sync {id}       # Verify sync state (--fix to auto-repair)
education:generate-docs          # Generate documentation
education:generate-recommendations # Generate recommendations
education:discover-features      # Discover platform features
education:coverage-report        # Full coverage report
education:migrate-kb             # Migrate legacy KB to education module
education:list-sites             # List all sites
```
