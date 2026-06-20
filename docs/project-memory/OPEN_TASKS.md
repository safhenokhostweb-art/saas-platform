# OPEN TASKS
> Current epics, features, tasks, status, dependencies, and acceptance criteria.
> Last Updated: 2026-06-20

---

## COMPLETE: GLOBAL DATE LOCALIZATION AUDIT — 2026-06-20 ✅

**Status:** DATE_LOCALIZATION_CERTIFIED (2026-06-20)

| Phase | Status |
|-------|--------|
| diffForHumansFaStatic() in CoreJalali | ✅ DONE |
| diffForHumansFa() proxy in App\Helpers\Jalali | ✅ DONE |
| Fix 5 CRITICAL findings in controllers (saas-platform) | ✅ DONE |
| Fix 52 HIGH findings in Blade templates (both repos) | ✅ DONE |
| Fix NumberHelper+Gregorian anti-pattern in 16 guide.blade.php | ✅ DONE |
| Fix diffForHumans() in all Blade files — both repos | ✅ DONE |
| Fix ExpirySmsService + ExpiryReminderService | ✅ DONE |
| Fix CSV exports — CustomerController + UserController | ✅ DONE |
| Fix customer-site CRM, EDU, inventory, webhooks, users | ✅ DONE |
| Reports: GLOBAL_DATE_LOCALIZATION_AUDIT.md + DATE_LOCALIZATION_FIX_REPORT.md | ✅ DONE |
| Deploy saas-platform | ✅ DONE |
| Deploy customer-site | ✅ DONE |

---

## IN PROGRESS: TB3 Sprint 10 — Professional Marketplace Themes — 2026-06-20 🔄

**Status:** CRITICAL+HIGH GAPS RESOLVED. LOW gaps pending.

| Phase | Status |
|-------|--------|
| GAP-001 CRITICAL: MarketplaceThemeSeedCommand (12 themes / 15 categories) | ✅ DONE |
| GAP-002 CRITICAL: Migration fix (raw SQL + hasColumn guards) | ✅ DONE |
| GAP-003 CRITICAL: MarketplaceSeedScoresCommand (wire quality service) | ✅ DONE |
| BRK-001: ThemeMarketplaceQualityService wired to controller | ✅ DONE |
| BRK-002: installs_count increment endpoint + auto-bestseller | ✅ DONE |
| BRK-003: Backfill existing themes with category field | ✅ DONE |
| GAP-004 HIGH: Scoring Dashboard (/admin/theme-marketplace/scoring) | ✅ DONE |
| GAP-005 HIGH: Marketplace search (name/slug filter) | ✅ DONE |
| GAP-006 HIGH: Category filter dropdown in admin index | ✅ DONE |
| GAP-007 MEDIUM: Bestseller badge + auto-promote logic | ✅ DONE |
| GAP-008 MEDIUM: New release badge display | ✅ DONE |
| GAP-009 MEDIUM: Annual/Lifetime License Pricing UI | ✅ DONE (form inputs + validation + persist) |
| GAP-010 LOW: Theme Bundle System | ⏳ PENDING |
| GAP-011 LOW: Theme Update Notification System | ⏳ PENDING |
| GAP-012 LOW: Demo Data Install/Reset | ⏳ PENDING |
| GAP-013 LOW: Marketplace Preview Page | ✅ DONE (/admin/theme-marketplace/{theme}/preview) |

**Admin "راه‌اندازی" button** now triggers `marketplace:seed` + `marketplace:score` from browser.
**Remaining LOW:** GAP-010 (bundles), GAP-011 (update alerts), GAP-012 (demo data).

---

## COMPLETE: TB3 Sprint 9.5 — Visual Reverse Engineering Engine — 2026-06-20 ✅

**Status:** CERTIFIED COMPLETE (2026-06-20) — TB3_SPRINT9_5_CERTIFIED

| Phase | Status |
|-------|--------|
| Migration: reverse_engineering_jobs | ✅ DONE |
| Model: ReverseEngineeringJob (8 statuses, 4 input types) | ✅ DONE |
| Phase 1: DomExtractionService (DOMXPath, 12 components) | ✅ DONE |
| Phase 2: LayoutReconstructionService (columns, spacing, flow graph) | ✅ DONE |
| Phase 3: VisualAnalysisService (colors, typography, buttons, radius, shadow) | ✅ DONE |
| Phase 4: CommerceAnalysisService (prices, cards, gallery, reviews) | ✅ DONE |
| Phase 5: SimilarityEngineService (4 dims × 6 sites, Euclidean RGB) | ✅ DONE |
| Phase 6: ReverseThemeReconstructorService (AI+PHP fallback, ThemePack) | ✅ DONE |
| Phase 7: ReverseEngineeringQualityService (10 checks, PASS/FAIL/PARTIAL) | ✅ DONE |
| Orchestrator: VisualReverseEngineeringService | ✅ DONE |
| Controller: ThemeReverseEngineeringController (6 actions) | ✅ DONE |
| View: reverse-engineer.blade.php (4-tab form, progress animation) | ✅ DONE |
| View: reverse-engineer-result.blade.php (score dashboard, install) | ✅ DONE |
| Routes (6 endpoints under /admin/theme-builder/reverse-engineer) | ✅ DONE |
| Docs: VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md | ✅ DONE |
| Docs: SIMILARITY_SCORING_ENGINE.md | ✅ DONE |
| Docs: THEME_RECONSTRUCTION_PIPELINE.md | ✅ DONE |
| Docs: TB3_SPRINT9_5_CERTIFICATION.md | ✅ DONE |
| Deploy saas-platform | ✅ DONE |

---

## COMPLETE: TB-AI Studio + Import Pipeline — 2026-06-20 ✅

**Status:** CERTIFIED COMPLETE (2026-06-20) — TB_AI_STUDIO_CERTIFIED

| Phase | Status |
|-------|--------|
| TB-AI Studio: composer.json, config.php, .env.example | ✅ DONE |
| Providers: Claude, OpenAI, DeepSeek, Custom + ProviderFactory | ✅ DONE |
| Analyzers: HtmlAnalyzer, SectionDetector, VisionAnalyzer | ✅ DONE |
| Generator: ThemePackGenerator + ZipExporter | ✅ DONE |
| Uploader: SaasPlatformUploader (Bearer + HMAC) | ✅ DONE |
| UI: 3-step Alpine.js (layout.php + home.php) | ✅ DONE |
| Platform: AuthenticateStudioApi middleware | ✅ DONE |
| Platform: ThemePackStudioController (5 API endpoints) | ✅ DONE |
| Platform: ThemePackStudioAdminController + 2 Blade views | ✅ DONE |
| Platform: routes/api.php + routes/web.php + Kernel.php + services.php | ✅ DONE |
| Docs: TB_AI_STUDIO_ARCHITECTURE.md | ✅ DONE |
| Docs: THEME_PACK_IMPORT_PIPELINE.md | ✅ DONE |
| Docs: LOCAL_THEME_GENERATION_WORKFLOW.md | ✅ DONE |
| Deploy saas-platform | ✅ DONE |

**Pending (developer action required):**
- `composer install` in `c:\laragon\www\tb-ai-studio\`
- Set `STUDIO_API_KEY` in production `.env`

---

## COMPLETE: TB3 Sprint 8 — AI Theme Generator — 2026-06-20 ✅

**Status:** CERTIFIED COMPLETE (2026-06-20) — TB3_SPRINT8_CERTIFIED

| Phase | Status |
|-------|--------|
| Migration: ai_generation_jobs | ✅ DONE |
| Model: AiGenerationJob | ✅ DONE |
| ThemeSectionDetectorService (11 sections, heuristic + Vision) | ✅ DONE |
| ThemePlaceholderService (SVG placeholders, Persian text banks) | ✅ DONE |
| ThemeAiGeneratorService (URL/Screenshot/HTML/Prompt pipelines) | ✅ DONE |
| ThemeAiGeneratorController (detect/generate/result/export/install) | ✅ DONE |
| ai-generator.blade.php (5-step Alpine.js UI, 4-tab input) | ✅ DONE |
| ai-generator-result.blade.php (result + actions) | ✅ DONE |
| Routes (6 new routes, import in web.php) | ✅ DONE |
| Theme Builder index — new "مولد هوشمند" button | ✅ DONE |
| TB3_SPRINT8_CERTIFICATION.md | ✅ DONE |
| Deploy both repos | ✅ DONE |

---

## COMPLETE: TB3 Sprint 6 — Professional Template Library — 2026-06-19 ✅

**Status:** CERTIFIED COMPLETE (2026-06-19) — TB3_SPRINT6_CERTIFIED

| Phase | Status |
|-------|--------|
| Migrations (theme_templates + theme_packs + site_theme_templates) | ✅ DONE |
| Models (ThemeTemplate + ThemePack + SiteThemeTemplate) | ✅ DONE |
| Services (ThemeTemplateService + ImportExport + DemoData + SiteThemeTemplateService) | ✅ DONE |
| Admin views — theme-templates (index/create/edit/show) | ✅ DONE |
| Admin views — theme-packs (index/create/show) | ✅ DONE |
| Canvas API endpoints — 3 endpoints × 2 repos | ✅ DONE |
| Canvas UI — Template Library modal + Save As Template modal × 2 repos | ✅ DONE |
| Routes + AdminMenu | ✅ DONE |
| Deploy both repos | ✅ DONE |
| TB3_SPRINT6_CERTIFICATION.md | ✅ DONE |

---

## COMPLETE: LIFECYCLE-1 — Customer Site Lifecycle Management System — 2026-06-19 ✅

**Status:** COMPLETE (2026-06-19) — both repos updated

| Sub-task | Status |
|----------|--------|
| 3 migrations (sites lifecycle columns, logs, invoices) | ✅ DONE |
| SiteLifecycleLog + SiteLifecycleInvoice models; Site model methods | ✅ DONE |
| SiteLifecycleService (state machine + push to customer-site DB) | ✅ DONE |
| SiteArchiveService (ZIP archive package) | ✅ DONE |
| SiteLifecycleNotificationService (SMS + dashboard) | ✅ DONE |
| 4 scheduler commands (check-expired/grace/suspended/archived) | ✅ DONE |
| Kernel.php — 4 daily schedule entries | ✅ DONE |
| SiteLifecycleController (7 actions) | ✅ DONE |
| 3 admin views (overview/index/settings) | ✅ DONE |
| Routes in web.php (7 lifecycle routes) | ✅ DONE |
| AdminMenu + sidebar icons | ✅ DONE |
| config/lifecycle.php | ✅ DONE |
| customer-site: CheckSiteLifecycle middleware | ✅ DONE |
| customer-site: bootstrap/app.php middleware registration | ✅ DONE |
| customer-site: suspended.blade.php (professional 503 page) | ✅ DONE |
| customer-site: lifecycle-widget.blade.php (dashboard component) | ✅ DONE |
| customer-site: dashboard.blade.php — @include widget | ✅ DONE |
| CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md | ✅ DONE |

**⚠️ PENDING PRODUCTION STEP:**
```bash
# Run migrations on production:
php artisan migrate
```

---

## COMPLETE: TB3 Sprint 3 — Inline Editing + Advanced Style + Media Manager — 2026-06-19 ✅

**Status:** COMPLETE (2026-06-19) — both repos updated and deployed

| Sub-task | Status |
|----------|--------|
| TB3-04: `editableAttr()` PHP + dblclick inline editing in iframe JS | ✅ DONE (both repos) |
| TB3-05: 3-tab panel (محتوا/استایل/پیشرفته), 6 style sections, Advanced tab | ✅ DONE (both repos) |
| TB3-07: Media Manager — upload/library/delete, ThemeMediaController, 3 routes | ✅ DONE (both repos) |
| `applyBlockStyle()` expanded to 20+ CSS properties | ✅ DONE (both repos) |
| Visibility classes, animation @keyframes in iframe CSS | ✅ DONE (both repos) |
| Deploy both repos | ✅ DONE |

---

## COMPLETE: TB3 Sprint 2 — Undo/Redo + Duplication + Per-Block Style — 2026-06-16 ✅

**Status:** COMPLETE (2026-06-16) — both repos updated and deployed

| Sub-task | Status |
|----------|--------|
| Undo/Redo history array (25 entries, Ctrl+Z/Y) | ✅ DONE |
| Block duplication (duplicateBlock + new ID + insert after) | ✅ DONE |
| 4-button action bar (up/down/duplicate/delete) | ✅ DONE |
| Content/Style tabs in props panel | ✅ DONE |
| Per-block style tab (bgColor, textColor, paddingY, paddingX, borderRadius, opacity, reset) | ✅ DONE |
| `applyBlockStyle()` in PHP BlockRenderer | ✅ DONE |
| Mirror to customer-site canvas-editor.blade.php | ✅ DONE |
| Commit + Deploy (both repos) | ✅ DONE |

**Potential Sprint 3 items:** Nested columns, typography overrides, block copy/paste across pages, export theme ZIP

---

## COMPLETE: Production Safety Hardening — CERTIFIED 2026-06-19 ✅

**Status:** PRODUCTION_CERTIFIED — Independent re-audit complete. All 12 PROD findings verified. 1 new critical finding (cache health) discovered and fixed during audit.

| Finding | Severity | Resolution |
|---------|----------|-----------|
| PROD-001 Health endpoint disclosure | CRITICAL | PASS — confirmed metadata stripped in production |
| PROD-002 MHC migrations | CRITICAL | PASS — all migrations applied batch 10 |
| PROD-003/004/005 Storage ops | HIGH | PASS — StorageManager boundary enforced, all exceptions documented |
| PROD-006 site_id indexes | MEDIUM | PASS — verified |
| PROD-007 OPcache / PHP-FPM restart | MEDIUM | PASS — `systemctl restart php8.3-fpm` in deploy.sh |
| PROD-008 Sanctum domains | MEDIUM | PASS — `SANCTUM_STATEFUL_DOMAINS` confirmed in production .env |
| PROD-009 Cache driver assertion | MEDIUM | PASS — AppServiceProvider throws if `CACHE_DRIVER=file` in production |
| PROD-010 Redis active | LOW | PASS — Redis 7.0.15 running, DB1 accessible |
| PROD-011 KB menu | LOW | PASS — AdminMenu.php confirmed |
| PROD-012 Dead code / migrations | LOW | PASS — nothing to migrate |
| NEW-001 Cache health false-negative | CRITICAL | FIXED — string sentinel replaces strict int compare in checkCache() |
| NEW-002 ZIP creation intermittent | LOW | DOCUMENTED — non-structural, workaround via direct SCP |
| NEW-003 deploy.sh rsync source | LOW | DOCUMENTED — redundant safety net, primary sync (zip) works |

**Certification Report:** `docs/PRODUCTION_CERTIFICATION_AUDIT.md`

---

## COMPLETE: TB3 Sprint 1 — Live Iframe Canvas — 2026-06-16 ✅

**Status:** COMPLETE (2026-06-16)

| Sub-task | Status |
|----------|--------|
| TB3-01: block.props canonical (data→props normalizer in both repos) | ✅ DONE |
| TB3-02: `wrapEditorMode()` in BlockRenderer + ThemeCanvasRenderer | ✅ DONE |
| TB3-02: `livePreview` + `renderBlockAjax` endpoints (both repos) | ✅ DONE |
| TB3-02: `canvas-live-preview.blade.php` (platform + customer-site) | ✅ DONE |
| TB3-02: Platform `canvas.blade.php` rewritten (iframe + device toggle) | ✅ DONE |
| TB3-02: Customer-site `canvas-editor.blade.php` rewritten (iframe) | ✅ DONE |
| TB3-03: Library items draggable + drop overlay + drop zones | ✅ DONE |
| Deploy to production | ✅ DONE |

**Pending (Sprint 2):** TB3-04 (nested columns), TB3-05 (block styles), TB3-06 through TB3-15 — awaiting user go-ahead.

---

## COMPLETE: MHC — Monitoring & Health Center — 2026-06-15 ✅

**Status:** COMPLETE (2026-06-15)  
**Scope:** Full enterprise monitoring + incident management for saas-platform

| Sub-task | Status |
|----------|--------|
| MHC-1: 6 migrations | ✅ DONE |
| MHC-2: 5 models (InfraIncident rewritten, 4 new) | ✅ DONE |
| MHC-3: 7 services (3 new, 4 extended) | ✅ DONE |
| MHC-3: 2 commands (MonitorPlatform new, MonitorSites rewritten) | ✅ DONE |
| MHC-5: Incident UI (index + show with timeline/lifecycle) | ✅ DONE |
| MHC-4: MonitoringController extended (show + platformHistory) | ✅ DONE |
| MHC-4: monitoring/index.blade.php extended (SMS/AI/Storage/Scheduler/Backup) | ✅ DONE |
| MHC-4: monitoring/site.blade.php created (uptime + drilldown) | ✅ DONE |
| MHC-6: Routes (incident routes + site drilldown + platform-history API) | ✅ DONE |
| MHC-6: Kernel.php (monitor:platform every 2 min) | ✅ DONE |
| MHC-6: customer-site /api/health extended (failed_jobs + disk + memory) | ✅ DONE |
| AdminMenu: incidents key + monitoring_center label + sidebar icons | ✅ DONE |
| Deploy to production | ✅ DONE |

---

## COMPLETE: EDU-INFRA Audit — Platform vs Customer Module Separation — 2026-06-15 ✅

**Status:** COMPLETE (2026-06-15)  
**Scope:** Full architecture audit of all modules across both repositories.

### Findings
| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| ARC-001 | CRITICAL | `forms_submissions` in Platform Admin menu — route never registered in platform mode | FIXED |
| ARC-002 | LOW | KB routes registered but absent from AdminMenu — tech debt, not misclassification | NOTED |
| ARC-003 | LOW | Orphaned `FormFieldController`/`FormSiteController`/`FormSubmissionController` in saas-platform with no routes | NOTED |

### Changes Made
1. `app/Helpers/AdminMenu.php` — removed `forms_submissions` from MENU_KEYS, MENU_ROUTES, MENU_ROUTE_PATTERNS, getGroups()
2. `resources/views/admin/partials/sidebar-icon.blade.php` — removed `forms_submissions` icon block
3. `resources/views/admin/partials/sidebar-icon-bare.blade.php` — removed `forms_submissions` icon block

### Result
All 30+ modules correctly classified. Full classification table in `AUDIT_HISTORY.md`.

---

## COMPLETE: 5-Phase Business Features Batch — 2026-06-15 ✅

| Phase | Description | Status |
|-------|-------------|--------|
| CRM | crm_contacts + crm_deals + crm_activities + CrmService + CrmController + 5 views + Kanban pipeline | ✅ DONE |
| Analytics | Already existed (AnalyticsDashboardController + Chart.js) | ✅ CONFIRMED |
| Customer Portal | DashboardController + account/dashboard enhanced with course progress + installments | ✅ DONE |
| Blog/SEO | Already existed (OG meta + JSON-LD + sitemap) | ✅ CONFIRMED |
| Inventory | warehouses + inventory_items + stock_movements + InventoryService + InventoryController + 6 views | ✅ DONE |
| Platform Admin | Module Adoption overview (6 KPI cards, site lists, recent activations) in saas-platform | ✅ DONE |

---

## COMPLETE: 5-Module Batch — 2026-06-13 ✅

| Module | Description | Status |
|--------|-------------|--------|
| INSTALL | خرید قسطی — migrations, models, InstallmentService, checkout, account panel, admin panel | ✅ DONE |
| LANG | چند زبانه AI — HasTranslations trait, ContentTranslationService (Claude Haiku), queue, middleware, admin | ✅ DONE |
| VPLAY | پخش فیلم حرفه‌ای — HomepageVideo, admin CRUD, video-player component, canvas block | ✅ DONE |
| FORM-EXT | لیست سفید Excel — AccessList/Gate models, AccessListImportService, public verify, admin UI (customer-site only) | ✅ DONE |
| IMG | آپلود هوشمند — SmartImageUploadService, smart-image-picker component, applied to posts/products/courses | ✅ DONE |

---

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

## COMPLETED: Public Learning Portal (customer-site) ✅ (2026-06-11)

**پورتال یادگیری عمومی در customer-site**

- `CourseClassCompletion` migration + model (course_class_completions جدول)
- `Front\CourseController` — 6 action: index, show, enroll, myLearning, learn, markComplete
- 4 view کامل: courses/index, courses/show, courses/learn, courses/my-learning
- Routes زیر `module:courses` middleware (13 route)
- `SiteHelper::coursesEnabled()` helper
- `MODULE_COURSES_ENABLED=true` در production env
- 423/423 tests pass
- Deploy شد به سرور production

**URL های جدید:**
- `GET /courses` — لیست دوره‌های فعال
- `GET /courses/{slug}` — جزئیات دوره
- `POST /courses/{slug}/enroll` — ثبت‌نام (auth لازم، رایگان = فوری)
- `GET /my-learning` — داشبورد دانشجو
- `GET /courses/{slug}/learn/{classOrder?}` — محیط یادگیری
- `POST /courses/{slug}/learn/{classId}/complete` — تکمیل جلسه (AJAX)

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
| INFRA-6 | Remote Agent Layer (Scenario E) | ✅ DONE (2026-06-11) |

---

## MOBILE BACKLOG

| ID | Task | Priority |
|----|------|----------|
| M2.1-a | File attachment upload flow | ✅ DONE (2026-06-11) |
| M2.1-b | iOS code signing pipeline | ✅ DONE (2026-06-11) |
| M2.1-c | Firebase FCM injection | ✅ DONE (2026-06-11) |
| M2.1-d | Deep-link routing | ✅ DONE (2026-06-11) |
| M2.1-e | AppImage packaging (Linux) | Low |
| M2.1-f | System tray icon assets | Low |

---

## FRONTEND BACKLOG

| ID | Task | Priority |
|----|------|----------|
| FE-01 | Wire x-core-ui::navigation-item/sidebar-group into layout files | ✅ DONE (2026-06-11) |
| FE-02 | Retrofit admin views to x-core-ui::button | ✅ DONE (2026-06-11) |
| FE-03 | Resolve Tailwind v4 shadow-var optimizer warnings | ✅ DONE (2026-06-11) |
| FE-04 | Remaining raw controls in forms/analytics, forms/create, forms/submissions | ✅ DONE (2026-06-11) |
| FE-05 | Retrofit tickets/show to x-core-ui::button | ✅ DONE (2026-06-11) |

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
