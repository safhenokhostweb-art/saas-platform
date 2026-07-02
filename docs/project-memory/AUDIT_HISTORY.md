# AUDIT HISTORY
> All findings recorded permanently. Resolved findings marked Fixed — never deleted.
> Format: Finding ID | Severity | Status | Evidence | Resolution
> Last Updated: 2026-07-03

---

## PHASE-009 INSTALLER & RECOVERY AUDIT — 2026-07-03

**Scope:** Installation, deletion, recovery, restore, rollback, deployment drivers, portability, failure scenarios, verification, and performance.
**Verdict:** CONDITIONAL; enterprise certification withheld.
**Evidence:** `FINAL_GAP_ANALYSIS.md`, `INSTALLER_ENTERPRISE_CERTIFICATION.md`, `RECOVERY_ENTERPRISE_CERTIFICATION.md`, `DELETE_ENGINE_CERTIFICATION.md`, `DISASTER_RECOVERY_CERTIFICATION.md`.
**Resolved findings:** Arvan package return-contract defect; missing DNS/web rollback registration; restore snapshot/migration fail-open behavior; pending-abort race; unsafe tenant-storage dispatch; removable final-delete backup; recovery disk/prefix/directory cleanup; optional manifest signature and unlisted DB payload.
**Deployment evidence:** selective production release passed SHA-256, rollback-archive checksum, Laravel cache, route, HTTP, and worker checks; backup `/var/backups/saas-platform/phase009-20260703-013616.tar.gz`.
**Open findings:** tracked as `CERT-009-001` through `CERT-009-004` in `KNOWN_ISSUES.md`.

---

## BUSINESS FLOW AUDIT — 2026-06-21

**Scope:** All 15 end-to-end business workflows. Both repos.  
**Verdict:** PRODUCTION_NOT_READY (67/100)  
**Full Reports:** `docs/BUSINESS_FLOW_AUDIT.md`, `docs/BUSINESS_FLOW_GAP_ANALYSIS.md`, `docs/PRODUCTION_READINESS_SCORE.md`

### BFA-C01 — CRITICAL | OPEN

**Title:** Restore Invoice Has No Payment Flow  
**Evidence:** `SiteLifecycleService::requestRestore()` creates `SiteLifecycleInvoice` with `STATUS_PENDING`. No route or payment gateway integration exists. Admin must mark paid manually.  
**Impact:** Reactivation fee uncollectable; revenue loss.  
**Required Fix:** Add customer-facing restore invoice page + Zarinpal/IDPay payment flow.

---

### BFA-C02 — CRITICAL | OPEN

**Title:** Subscription Renewal Does NOT Reactivate Suspended Site  
**Evidence:** `ProcessRenewalsCommand` + `SubscriptionPaymentController` never call `SiteLifecycleService::activate()`. Both systems are completely decoupled.  
**Impact:** Customer pays renewal, site stays on 503. Immediate churn.  
**Required Fix:** After `Subscription::STATUS_ACTIVE`, check `site->lifecycle_status` and call `activate()` if not active.

---

### BFA-C03 — CRITICAL | OPEN

**Title:** Scheduled SMS Campaigns Never Auto-Fire  
**Evidence:** `sms_campaigns.scheduled_at` column + `scheduled` status exist. No scheduler command queries and triggers them.  
**Impact:** UI-level dead feature. Campaigns set to schedule silently never send.  
**Required Fix:** `ProcessScheduledSmsCampaignsCommand` scheduled in Kernel.php.

---

### BFA-C04 — CRITICAL | OPEN

**Title:** No Customer Self-Service Reactivation  
**Evidence:** `suspended.blade.php` shows 503 with no payment link or request button. Customer cannot reactivate without contacting admin.  
**Impact:** Every suspension requires admin manual intervention. Unscalable.  
**Required Fix:** Add "درخواست بازگردانی" button + `AccountLifecycleController::requestRestore()`.

---

### BFA-C05 — CRITICAL | OPEN

**Title:** Storage Release Is Accounting-Only (Files Not Deleted)  
**Evidence:** `releaseActiveResources()` resets DB counters only. No file system operation. Server disk fills with archived site files indefinitely.  
**Impact:** Production disk exhaustion risk at scale.  
**Required Fix:** `SiteArchiveService::deleteActiveFiles($site)` after counter reset.

---

## PRODUCTION SAFETY PHASE — PROD-003–012 (2026-06-16)

### PROD-008 — MEDIUM | FIXED

**Title:** SANCTUM_STATEFUL_DOMAINS not set in production .env  
**Status:** FIXED  
**Evidence:** Production .env had no `SANCTUM_STATEFUL_DOMAINS`. Default fallback includes `localhost,127.0.0.1,::1` — local access vectors on the server.  
**Resolution:** `SANCTUM_STATEFUL_DOMAINS=panel.safhenokhost.ir` added to production .env; `php artisan config:cache` re-run. Localhost no longer in stateful domains list.  
**Final Verdict:** PASS

---

### PROD-009 — MEDIUM | FIXED

**Title:** No boot-time assertion for CACHE_DRIVER=file on production  
**Status:** FIXED  
**Evidence:** If Redis goes down and `.env` is misconfigured to `file`, circuit breakers and 6 named Redis stores would silently degrade.  
**Resolution:** Added `RuntimeException` in `AppServiceProvider::boot()` when `APP_ENV=production` and `CACHE_DRIVER=file`. File: `app/Providers/AppServiceProvider.php`. Commit: `8ef3a27`.  
**Final Verdict:** PASS

---

### PROD-011 — LOW | FIXED

**Title:** KB routes registered but absent from admin sidebar menu  
**Status:** FIXED  
**Resolution:** `kb` key added to `AdminMenu::MENU_KEYS/ROUTES/ROUTE_PATTERNS/getGroups()`. KnowledgeBase now accessible under بازاریابی/دانش‌محور group in admin sidebar. Commit: `8ef3a27`.  
**Final Verdict:** PASS

---

### PROD-012 — LOW | FIXED

**Title:** Dead code — orphaned form controllers with no registered routes  
**Status:** FIXED  
**Resolution:** `FormFieldController`, `FormSiteController`, `FormSubmissionController` deleted from `app/Http/Controllers/Admin/`. 303 LOC removed. Commit: `8ef3a27`.  
**Final Verdict:** PASS

---

### PROD-003/004/005/006/007/010 — PRE-RESOLVED

All verified as already resolved by prior infrastructure work:
- **PROD-003/004/005:** `StorageManager` already handles all file uploads/CSV exports
- **PROD-006:** `migration 2026_06_07_000001_add_composite_site_id_indexes.php` already covers all key tables
- **PROD-007:** Only `SitemapService` uses `Storage::disk('public')` — intentionally public, no change needed
- **PROD-010:** Shadow-var CSS warnings already fixed by KI-002 (0 patterns remaining)

---

## PRODUCTION SAFETY PHASE — PROD-001 & PROD-002 (2026-06-15)

### PROD-001 — CRITICAL | FIXED

**Title:** Health endpoints expose deployment metadata to public internet  
**Severity:** CRITICAL  
**Status:** FIXED  
**Evidence:**
- `GET /health/release` returned: `deployment_uuid`, `git_commit` (SHA1), `platform_version`, `schema_drift`, `pending_migrations` — all unauthenticated
- `GET /health/live` returned: `php` version string
- `GET /health/ready` returned: `detail` strings including `"140 tables accessible"`, Redis/cache error messages

**Resolution:**
- Added `isProduction(): bool` helper to `RuntimeHealthService`
- In production: `/health/release` returns only `{coherent, timestamp}`; `/health/live` omits `php`; `/health/ready` omits all `detail` strings
- In non-production: full diagnostic data preserved
- File: `packages/saas-platform/core-utils/src/Services/Health/RuntimeHealthService.php`
- Commit: `f8846d7`

**Regression Risk:** LOW — change is additive filtering only; all endpoints remain functional

**Final Verdict:** PASS

---

### PROD-002 — CRITICAL | RESOLVED (No Action Required)

**Title:** MHC migrations (2026-06-15) status on production — risk of schema drift  
**Severity:** CRITICAL  
**Status:** RESOLVED — all migrations confirmed Ran  
**Evidence:**
- All 6 MHC migrations (batch 10) confirmed via `php artisan migrate:status` on production
- `infra_incidents` table has correct schema (status, severity as medium not warning)
- `monitor:platform` command completed without exception

**Final Verdict:** PASS — no migration action required

---

### INFRA-DEPLOY-001 — HIGH | FIXED

**Title:** Server deploy.sh did not sync packages/saas-platform/ → vendor/saas-platform/  
**Severity:** HIGH  
**Status:** FIXED (2026-06-15 during PROD-001 execution)  
**Evidence:**
- PHP Composer autoloader loads from `vendor/` not `packages/`
- Previous deploys only updated `packages/` — `vendor/` retained stale bytecode
- Discovered when PROD-001 fix did not take effect despite correct file on disk
- `vendor/saas-platform/core-utils/src/Services/Health/RuntimeHealthService.php` was dated 2026-06-09 while packages/ was updated to 2026-06-15

**Resolution:**
- `/var/www/deploy.sh` on production server updated: added `rsync -a --delete packages/saas-platform/$PKG/ vendor/saas-platform/$PKG/` for all 4 local packages
- Added `systemctl restart php8.3-fpm` at end of deploy.sh to clear opcache on every deploy

**Final Verdict:** PASS — future deploys will correctly update vendor/ and clear bytecode cache

---

## EDU-INFRA AUDIT — Platform vs Customer Module Separation (2026-06-15)

**Scope:** Complete classification of all modules across both repositories — Platform Admin (saas-platform) and Customer Site (customer-site).

### ARC-001 — CRITICAL | FIXED

**Title:** `forms_submissions` in Platform Admin menu → route not registered in platform mode  
**Severity:** CRITICAL  
**Status:** FIXED  
**Evidence:**  
- `AdminMenu::MENU_KEYS` contained `'forms_submissions' => 'ارسال‌های فرم'`
- `AdminMenu::MENU_ROUTES` contained `'forms_submissions' => 'admin.forms.submissions.index'`
- Forms package `routes/web.php` guards all admin routes with `if (!config('forms.platform_mode', false))`
- `FORMS_PLATFORM_MODE=true` in `.env` → route `admin.forms.submissions.index` is NEVER registered
- Blade sidebar generates `route('admin.forms.submissions.index')` → `RouteNotFoundException`
- Form submissions are tenant-owned data; Customer Site has full CRUD under `admin/forms/submissions`
**Resolution:**  
Removed `forms_submissions` from `AdminMenu::MENU_KEYS`, `MENU_ROUTES`, `MENU_ROUTE_PATTERNS`, and `getGroups()`. Removed icon entries from `sidebar-icon.blade.php` and `sidebar-icon-bare.blade.php`.  
**Regression Risk:** LOW — removal only. No functional loss; the route was broken already.  
**Final Verdict:** PASS

### ARC-002 — LOW | NOTED (no action required)

**Title:** KB (Knowledge Base) routes registered but no menu entry in Platform Admin  
**Severity:** LOW  
**Status:** FIXED (2026-06-16)  
**Evidence:** `admin.kb.*` routes registered in `routes/web.php` with full CRUD; `KnowledgeBaseController` exists; but `kb` key was absent from `AdminMenu::MENU_KEYS`.  
**Resolution:** Added `kb` key to `AdminMenu::MENU_KEYS`, `MENU_ROUTES`, `MENU_ROUTE_PATTERNS`, and `getGroups()` (group: بازاریابی/دانش‌محور). Commit: `8ef3a27`.  
**Final Verdict:** PASS

### ARC-003 — LOW | NOTED (no action required)

**Title:** Orphaned App-level form controllers in Platform Admin with no routes  
**Severity:** LOW  
**Status:** FIXED (2026-06-16)  
**Evidence:** `App\Http\Controllers\Admin\FormFieldController`, `FormSiteController`, `FormSubmissionController` existed with zero registered routes.  
**Resolution:** All 3 files deleted. 303 lines of dead code removed. Commit: `8ef3a27`.  
**Final Verdict:** PASS

### CLASSIFICATION TABLE — ALL MODULES

| Module / Feature | Platform Admin | Customer Site | Notes |
|-----------------|:-:|:-:|-------|
| Platform users/customers/sites | ✅ | | Core SaaS operator function |
| Module + license registry | ✅ | | |
| Platform products (SaaS licenses) | ✅ | | Different entity from tenant products |
| Resellers / commissions / payouts | ✅ | | |
| Support plans | ✅ | | |
| Platform payment gateways | ✅ | | Different credentials from tenant gateways |
| Support tickets (platform↔tenant) | ✅ | | Each side sees only their channel |
| Profile fields | ✅ | | |
| Platform settings / captcha / access | ✅ | | |
| Platform static pages | ✅ | | Different content from tenant pages |
| Module labels | ✅ | | |
| Theme Builder (create themes) | ✅ | | Wizard-only |
| Theme Manager (engine) | ✅ | | |
| Theme Marketplace (sell themes) | ✅ | ✅ | Operator manages; tenant buys |
| Education module (teach tenants) | ✅ | | |
| Education center (read content) | | ✅ | Sync from platform |
| Infrastructure / monitoring | ✅ | | |
| Notification center | ✅ | | |
| Module adoption dashboard | ✅ | | |
| App releases | ✅ | | |
| Forms (read-only ADR-015 overview) | ✅ | | Overview only — no CRUD |
| forms_submissions | ~~✅~~ ❌ REMOVED | ✅ | Tenant data only |
| Blog / posts / categories | | ✅ | |
| Shop / orders / coupons | | ✅ | |
| Subscription management | | ✅ | |
| CRM | | ✅ | |
| Inventory | | ✅ | |
| Courses (tenant-offered) | | ✅ | |
| Invoices | | ✅ | |
| Theme design / canvas | | ✅ | Tenant applies themes |
| Tenant payment gateways | | ✅ | |
| Tenant settings | | ✅ | |
| Site content assistant | | ✅ | |
| Analytics | | ✅ | |
| User notifications | | ✅ | |
| Installments / access lists | | ✅ | |
| Account panel (end users) | | ✅ | |
| Business cards / ads / referrals | | ✅ | |
| WhatsApp / SMS | | ✅ | |
| Activity log | ✅ | ✅ | Separate scopes |
| Pages | ✅ | ✅ | Separate content |
| Payment gateways | ✅ | ✅ | Different contexts |

---

## EDU-16 DESIGN VALIDATION (2026-06-06)

### ADR-016 — pending_review eliminated

**Severity:** MEDIUM (design risk avoided)  
**Evidence:** `EducationGeneratedContentController` mapped `pending_review = draftCount` (identical values); `scopePendingReview()` queried `whereIn(REVIEWABLE_STATUSES)` containing two states with identical semantics; no business actor exists that creates a "draft but not yet pending review" state.  
**Decision:** Remove `pending_review` entirely. Simplified state machine: `draft → published | archived`. Review audit tracked via `reviewed_at IS NOT NULL`.  
**Final Verdict:** PASS — simplified architecture adopted. Zero remaining `pending_review` references.

---

## EDU-15.2 FORENSIC AUDIT (2026-06-05)

### F-01 — HIGH | FIXED

**Title:** GenerateContentCommand / GenerateGapContentCommand set module_slug='unknown'  
**Severity:** HIGH  
**Status:** FIXED  
**Evidence:**  
Both commands used `new ContentGenerationJobDto(moduleSlug: $module ?? 'unknown')`. When run without `--module`, every generated article received `module_slug = 'unknown'`.  
**Resolution:**  
Both commands now consume queue services directly and call `executeJob()` per item, preserving the real `moduleSlug` from each queue item DTO.  
**Tests:** `GenerateContentCommandTest`, `GenerateGapContentCommandTest` — both updated with regression guard asserting `module_slug != 'unknown'`.

---

### T-01 — CRITICAL | FIXED

**Title:** GeneratedContentSecurityTest — cross-site isolation tests were false positives  
**Severity:** CRITICAL (test infrastructure)  
**Status:** FIXED  
**Evidence:**  
`GeneratedContentSecurityTest` stored `$siteId` as a private field the controller couldn't read. Tests `test_platform_level_drafts_not_visible_to_tenant` and `test_provider_usage_is_site_scoped` would pass even without isolation enforcement.  
**Resolution:**  
Added `withTenantSite(int $siteId): static` helper that injects `tenant_site` request attribute via a one-time middleware, matching production `AuthenticateTenantApi` behavior exactly.  
**Impact:** All cross-site isolation tests now accurately verify tenant boundary enforcement.

---

### F-02 — MEDIUM | FIXED (EDU-15.2)

**Title:** EducationAiContentGenerationServiceInterface missing providerName() + logFailure()  
**Severity:** MEDIUM  
**Status:** FIXED  
**Evidence:**  
`STATUS_VALIDATION_FAILED` and `STATUS_PROVIDER_ERROR` constants existed in `EducationGenerationLog` but were never written by any code path. `generationAnalytics()` success rate always returned 1.0.  
**Resolution:**  
- Added `providerName(): string` to interface + implementation  
- Added `logFailure(ContentGenerationJobDto, string, string): void` to `EducationDraftPersistenceServiceInterface` + implementation  
- All 4 commands now call `logFailure()` in typed exception catch blocks  
**Remaining:** EDU-16 should wire `STATUS_VALIDATION_FAILED` / `STATUS_PROVIDER_ERROR` writes before analytics go live to operators.

---

### RC-01 — MEDIUM | FIXED (EDU-15.2)

**Title:** No database-level unique constraint on AI draft articles  
**Severity:** MEDIUM  
**Status:** FIXED  
**Evidence:**  
Concurrent command runs could create duplicate AI-generated articles. Application-level `draftExists()` check has a TOCTOU race condition.  
**Resolution:**  
- Migration `2026_06_05_000002_add_ai_draft_dedup_index_to_edu_articles.php`
- Unique index `edu_articles_ai_draft_unique` on `(auto_generated, module_slug, site_id, title(191))`  
- Supporting index `edu_articles_ai_draft_lookup` on `(auto_generated, site_id, module_slug)`
- `persist()` now has two-layer protection: `lockForUpdate()` re-check + `QueryException(1062)` catch  
- Pre-existing duplicate cleanup included in migration

---

### S-02 — MEDIUM | FIXED (EDU-15.2)

**Title:** GET /generated-content had no role/policy check beyond auth:sanctum  
**Severity:** MEDIUM  
**Status:** FIXED  
**Evidence:**  
Any authenticated user could access the AI draft review endpoint, not just admins.  
**Resolution:**  
- Added `viewGeneratedContent($user): bool` to `EducationArticlePolicy` — allows `is_admin` only  
- `EducationGeneratedContentController::index()` calls `Gate::authorize('viewGeneratedContent', EducationArticle::class)`  
- Non-admins receive 403  
- Tests in `EDU152RemediationTest.php`  
**Remaining (EDU-16):** Review action endpoints (approve/reject) must also have Gate checks when implemented.

---

## EDU-14A.1 STABILITY AUDIT (2026-06-05)

### F-01 — HIGH | FIXED

**Title:** ContentReadinessService — division by 100 in score calculation  
**Severity:** HIGH  
**Status:** FIXED  
**Evidence:** Score was being divided by 100 unnecessarily, producing scores in 0.0–1.0 range instead of 0–100.  
**Resolution:** Removed `/100` division from `ContentReadinessService`.

---

### F-02 — HIGH | FIXED

**Title:** ContentReadinessService — N+1 queries  
**Severity:** HIGH  
**Status:** FIXED  
**Evidence:** Individual article count queries per module instead of batch.  
**Resolution:** Batch to 5 total queries across all modules.

---

### F-03 — HIGH | FIXED

**Title:** KnowledgeGapService — whereNull not used for null siteId  
**Severity:** HIGH  
**Status:** FIXED  
**Evidence:** `where('site_id', null)` in MySQL always returns 0 rows (NULL comparison issue).  
**Resolution:** All null siteId contexts now use `whereNull('site_id')`.

---

### F-04 — HIGH | FIXED

**Title:** AIRecommendationScoreService — loadUsageMap/loadProgressMap missing whereNull guard  
**Severity:** HIGH  
**Status:** FIXED  
**Evidence:** Same NULL comparison issue as F-03 in recommendation scoring maps.  
**Resolution:** `whereNull` guard applied to both methods.

---

### F-05 — HIGH | FIXED

**Title:** UserLearningProfileService::resolveLastActivity — missing whereNull guard  
**Severity:** HIGH  
**Status:** FIXED  
**Evidence:** Platform-admin context query returned no results due to missing whereNull on site_id.  
**Resolution:** `whereNull` guard applied.

---

### PA-07 — PARTIAL | FIXED

**Title:** KnowledgeGapService JOIN does not exclude soft-deleted articles  
**Severity:** MEDIUM  
**Status:** FIXED  
**Resolution:** JOIN now excludes `deleted_at IS NOT NULL`.

---

### PA-08 — PARTIAL | FIXED

**Title:** ContentReadinessService — article count re-queried after batch  
**Severity:** LOW  
**Status:** FIXED  
**Resolution:** Single article count query, no re-query.

---

### PA-09 — PARTIAL | FIXED

**Title:** KnowledgeGapServiceInterface — missing invalidate() method  
**Severity:** LOW  
**Status:** FIXED  
**Resolution:** Added `invalidate(int $userId, ?int $siteId): void` to interface + `KnowledgeGapService` with 15-min cache `edu_gap.{userId}.{siteId}`.

---

## EDU-13.5 PRODUCTION HARDENING AUDIT (2026-06-04)

### Security Findings — All Fixed

| ID | Description | Status |
|----|-------------|--------|
| F-02 | Mobile routes lacked auth:sanctum | FIXED |
| F-03 | MobileEducationController::show() — no cross-tenant site_id check | FIXED |
| F-04 | EducationSyncApiController — no authorizeSite() guard | FIXED |
| C-04 | EducationWalkthroughPolicy not registered | FIXED |

### Data Integrity Findings — All Fixed

| ID | Description | Status |
|----|-------------|--------|
| F-01 | WalkthroughTriggerService::markCompleted() missing site_id | FIXED (BREAKING CHANGE) |
| F-05 | EducationArticle revision numbering not atomic | FIXED |
| F-06 | EducationFeatureUsageRepository::track() — race condition on insert | FIXED |
| F-07 | EducationRecommendationRepository::create() — no transaction | FIXED |
| F-08 | DocumentationGeneratorService — no transaction | FIXED |
| F-09 | Missing covering index on edu_recommendations | FIXED |
| F-15 | EducationArticle hash not deterministic | FIXED |

### Performance Findings — All Fixed

| ID | Description | Status |
|----|-------------|--------|
| F-12 | EducationGuideComposer — no caching | FIXED (60s cache) |
| F-13 | TrackFeatureUsageMiddleware on all web routes | FIXED (admin only) |
| F-14 | Sync API not paginated | FIXED (default 50, max 200) |
| F-16 | EducationFeatureUsageRepository — N+1 in neverUsedFeatures | FIXED |

---

## EDU-13 REGRESSIONS (2026-06-04)

### BLOCKER-1 | FIXED (EDU-13.6)

**Title:** EducationGuideComposer passes null siteId to triggerForRoute  
**Status:** FIXED  
**Resolution:** Resolves `$siteId = auth()->user()->site_id`. Skips `triggerForRoute` when `$siteId === null`.

### BLOCKER-2 | FIXED (EDU-13.7)

**Title:** Admin complete route closure has no policy gate  
**Status:** FIXED  
**Resolution:** `EducationWalkthrough::findOrFail()` + `abort_if(!can('update', $wt), 403)` + `user->site_id` siteId fallback.

---

## PHASE 8.x PLATFORM AUDIT (2026-05-25 to 2026-05-27)

### Phase 8.1 — Package Boundary

| Finding | Status |
|---------|--------|
| App\ imports found in packages/modules-tickets/src/ | FIXED — commit c23ae02 |
| App\ imports found in packages/modules-forms/src/ | FIXED — commit c23ae02 |
| Hardcoded model references in packages | FIXED — config-driven pattern |

### Phase 8.3 — Observability

| Finding | Status |
|---------|--------|
| Bearer tokens potentially leaking in telemetry | FIXED — TelemetrySanitizer regex redaction |
| No trace IDs on HTTP requests | FIXED — CorrelationIdMiddleware (first in web group) |
| Cross-tenant telemetry possible | FIXED — all tenant APIs scoped by tenant_id |

### Phase 8.4 — Governance

| Finding | Status |
|---------|--------|
| No idempotency gate on recovery strategies | FIXED — idempotency gate added |
| Cascade failures could take down whole platform | FIXED — CascadingFailureDetector + blast radius analyzer |

---

## OPEN FINDINGS (Not Yet Resolved)

### OPF-001 — MEDIUM | RESOLVED (EDU-16, 2026-06-06)

**Title:** STATUS_VALIDATION_FAILED / STATUS_PROVIDER_ERROR never written  
**Resolution:** EDU-16 audit confirmed all 4 generation commands already had `logFailure()` calls wired for both constants from EDU-15.2. KI-001 confirmed resolved. `generation_success_rate` will now report correctly when failures occur.

### OPF-002 — LOW | OPEN

**Title:** Tailwind v4 Lightning CSS optimizer shadow-var warnings  
**Status:** OPEN — Non-breaking  
**Evidence:** `shadow-[var(--ui-shadow-*)]` produces optimizer notices on `npm run build` (exit 0).  
**Plan:** Resolve in next frontend phase.

### OPF-003 — LOW | OPEN

**Title:** No Redis configured for shared cache (multi-node)  
**Status:** OPEN — Infrastructure decision pending  
**Evidence:** Circuit breaker state stored in Cache, which defaults to file driver in development. Multi-node deployments require Redis.  
**Plan:** Phase 8.4b.

### OPF-004 — LOW | OPEN

**Title:** No DB indexes on site_id columns  
**Status:** OPEN  
**Evidence:** Phase 8.4a identified missing indexes on site_id across multiple tables.  
**Plan:** Phase 8.4a migration batch.
