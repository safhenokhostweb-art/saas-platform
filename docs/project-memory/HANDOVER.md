# HANDOVER DOCUMENT
> Reading this file alone is sufficient to resume development from any future session.
> If context is lost, read this file first, then PROJECT_STATE.md.
> Last Updated: 2026-06-06 (memory sync)

---

## PROJECT SUMMARY

**SaaS Platform** — A multi-tenant Laravel 10 SaaS built for managing customer support, forms, education content, and platform communication. One Laravel installation serves multiple customer sites via domain-based tenant resolution (`site_id` scoping).

**Two repositories:**
- `c:\laragon\www\saas-platform` — Platform admin, API, all packages
- `c:\laragon\www\customer-site` — Per-tenant customer-facing application

**Stack:** Laravel 10, PHP 8.4, MySQL, Redis, Tailwind CSS v4.3, Alpine.js, Flutter (mobile)

**Architecture:** Package-based modules at `packages/saas-platform/modules/`. No `App\` imports allowed inside packages.

---

## CURRENT STATE (2026-06-19)

**Last completed phase:** TB3 Sprint 3 Certification Audit — `TB3_SPRINT3_CERTIFIED` (2026-06-19)  
**Before that:** TB3 Sprint 3 — Inline Editing (TB3-04), Advanced Style Panel (TB3-05), Media Manager (TB3-07) — COMPLETE (2026-06-19)  
**Status:** Both repos updated, deployed, and certified. Certification doc at `docs/TB3_SPRINT3_CERTIFICATION.md`.  
**Next recommended milestone:** TB3 Sprint 4 — fix 2 MEDIUM findings (caption placeholder + wrap order), then nested containers or mobile preview.

### Production Certification Summary (2026-06-19)

**Independent re-audit of all 12 PROD findings — PRODUCTION_CERTIFIED.**

| New Finding | Severity | Resolution |
|-------------|----------|------------|
| NEW-001 Cache health false-negative | CRITICAL | FIXED — string sentinel in `checkCache()` (`RuntimeHealthService.php`) |
| NEW-002 ZIP creation intermittent fail | LOW | Documented — non-structural |
| NEW-003 deploy.sh rsync wrong source | LOW | Documented — primary zip sync works |

**Health endpoint confirmed:** `/health/ready` returns `ready: true` with all 4 checks passing.  
**Scheduler confirmed:** `monitor:platform` (2min), `monitor:sites` (5min), `runtime:scan-anomalies` — all running.  
**Incident lifecycle confirmed:** create → investigate → resolve → SMS notification wired.  
**Full report:** `docs/PRODUCTION_CERTIFICATION_AUDIT.md`

### TB3 Sprint 2 Summary (2026-06-16)

| Feature | Detail |
|---------|--------|
| Undo/Redo | 25-entry JSON snapshot history; Ctrl+Z/Y shortcuts; toolbar buttons |
| Block Duplication | Clone with new ID; inserted after source; auto-selected |
| 4-button action bar | up / down / duplicate / delete (icon-only, compact) |
| Content/Style tabs | Props panel switches context |
| Per-block Style tab | bgColor, textColor, paddingY, paddingX, borderRadius, opacity, reset |
| `applyBlockStyle()` PHP | Inline CSS outer div in BlockRenderer; XSS-safe `escapeAttr()` |

Both `saas-platform` and `customer-site` updated in sync.  
Commits: `046b195` (saas-platform), `1b5bb33` (customer-site)

### Production Safety Summary (2026-06-16)

All 12 PRODUCTION_FIX_PLAN.md findings resolved:
- PROD-001: Health endpoints strip deployment metadata in production (`RuntimeHealthService`)
- PROD-002: MHC migrations confirmed ran (batch 10) — no action needed
- PROD-003/004/005: StorageManager already handles all uploads/CSV — pre-existing
- PROD-006: Composite `site_id` indexes already exist on all key tables
- PROD-007: `SitemapService` intentionally uses `public` disk — no change
- PROD-008: `SANCTUM_STATEFUL_DOMAINS=panel.safhenokhost.ir` set on production server
- PROD-009: `AppServiceProvider::boot()` throws if `CACHE_DRIVER=file` in production
- PROD-010: Shadow-var CSS warnings already resolved by KI-002
- PROD-011: `kb` key added to AdminMenu.php (Knowledge Base now in sidebar)
- PROD-012: 3 orphaned form controllers deleted (303 LOC removed)

### MHC — Monitoring & Health Center (2026-06-15)

**6 Migrations:** enhance_incidents (4-state lifecycle), incident_notes, platform_health_logs, backup_runs, scheduler_runs, extend_site_health_logs  
**5 Models:** InfraIncident (rewritten), InfraIncidentNote, InfraPlatformHealthLog, InfraBackupRun, InfraSchedulerRun  
**7 Services:** IncidentLifecycleService (new), UptimeCalculator (new), SchedulerHealthTracker (new), HealthCheckService (extended), SiteHealthChecker (extended), InfrastructureErrorCollector (extended), NotificationCenterService (extended)  
**2 Commands:** MonitorPlatformCommand (new, every 2min), MonitorSitesCommand (rewritten, 4-level severity)  
**Incident UI:** admin/incidents/index.blade.php + admin/incidents/show.blade.php  
**Monitoring UI:** admin/infrastructure/monitoring/index.blade.php (extended), admin/infrastructure/monitoring/site.blade.php (new)  
**Routes:** 7 incident routes (admin.incidents.*), site drilldown (admin.infrastructure.monitoring.site), platform-history API  
**Scheduler:** monitor:platform every 2 minutes  

**Key invariants:**
- Incident severity: critical / high / medium / low (NOT warning — migrated)
- Incident status: open / investigating / resolved / closed (4-state)
- `InfraIncident::active()` = open OR investigating
- Customer SMS sent on resolve via `IncidentLifecycleService::notifyCustomerOnResolve()`
- Platform incidents auto-created via `IncidentLifecycleService::open()` from `InfrastructureErrorCollector`

### KI/FI Resolution Batch (2026-06-15)

**Bug Fix — NumberHelper TypeError:**
- `customer-site/app/Helpers/NumberHelper.php` — `formatFa()` accepts pre-formatted strings (strips commas)
- Affected: all 5 installment views (account/admin/front), 19 occurrences total

**FI-008 — Runtime Anomaly Scanner:**
- `app/Console/Commands/ScanRuntimeAnomaliesCommand.php` — NEW
- Scheduled every 5 minutes in `Kernel.php`, Slack alert for critical/high
- 6/6 tests

**KI-006 — Domain Resolution Cache:**
- `app/Services/ResellerDetectionService.php` — Redis cache per host (TTL 300s)
- `customer-site/app/Http/Middleware/SetTenantContext.php` — License + schema caching
- `customer-site/app/Services/LicenseActivationService.php` — cache invalidation on update

**KI-002 — Tailwind v4 Syntax:**
- 301 replacements: `[var(--token)]` → `(--token)` across 27 core-ui components
- Shadow arbitrary values replaced with named `@utility` classes
- Lightning CSS warnings eliminated

### NEXUS PRO Summary (2026-06-14)

**Platform (saas-platform):**
- `app/Services/ThemeBuilder/BlockRegistry.php` — +15 premium block definitions
- `app/Services/ThemeBuilder/BlockRenderer.php` — +15 render methods
- `app/Console/Commands/NexusSeedCommand.php` — **NEW**: `php artisan nexus:seed`

**Customer Site:**
- `app/Services/ThemeCanvas/BlockRegistry.php` — +15 matching block definitions
- `resources/views/layouts/canvas-theme.blade.php` — +CSS for all 15 blocks + JS skills animation

**Themes in marketplace:**
- `nexus-pro-corporate` — Navy/Indigo, 6 صفحه شرکتی
- `nexus-pro-personal` — Dark Violet, 4 صفحه شخصی/پورتفولیو
- `nexus-pro-store` — Orange, 4 صفحه فروشگاهی

**New block types available:**
`hero_split`, `hero_gradient`, `features_grid`, `stats_row`, `client_logos`, `process_steps`, `team_cards`, `skills_bars`, `testimonials_slider`, `trust_badges`, `pricing_cards`, `newsletter_form`, `bento_grid`, `services_cards`, `contact_split`

### 5-Module Summary (2026-06-13)

**INSTALL — خرید قسطی:**
- Migration/Models: `installment_plans`, `installment_orders`, `installment_payments`
- `InstallmentService`, `InstallmentController` (front), `InstallmentAccountController` (account panel)
- Admin: `InstallmentAdminController` (plans CRUD + orders browse)
- Routes: `/installment/plans`, `/account/installments`, `admin/installments/*`
- Compound interest formula implemented in `InstallmentPlan::calculate()`

**LANG — چند زبانه AI:**
- Migration: `content_translations` (hash check) + `translation_queue`
- `HasTranslations` trait applied to Post, Product, Course
- `ContentTranslationService` — Claude Haiku-4-5, lazy translate on queue, hash invalidation
- Command: `translations:process --batch=20` (every 15 min via scheduler)
- `SetLocale` middleware + `language-switcher` component + `/lang/{locale}` route

**VPLAY — پخش فیلم حرفه‌ای:**
- `HomepageVideo` model (youtube/mp4/cloudflare, autoplay/loop/muted)
- `HomepageVideoController` + admin CRUD + drag-reorder
- `video-player` Blade component (Alpine.js, custom MP4 controls bar RTL)
- Wired to homepage and `ThemeCanvasRenderer` (`module:hero_video` block)

**FORM-EXT — لیست سفید Excel:**
- Migration: `access_lists`, `access_list_entries`, `access_gates`, `access_verifications`
- `AccessListImportService` (PhpSpreadsheet v5.8, batch 500, auto-detect FA/EN headers)
- `AccessGateController` (public `/gate/{slug}` show+verify, session grant)
- Admin: `AccessListController` (lists CRUD + Excel import + gates CRUD) — customer-site only, NOT saas-platform admin
- Public verify page: standalone RTL minimal form

**IMG — آپلود تصویر هوشمند:**
- `SmartImageUploadService` (15 contexts, WebP GD, AI hook via `LOCAL_AI_ENABLED`)
- `smart-image-picker` Blade component (drag-drop, XHR progress, metadata badges)
- Applied to: products, posts, courses admin forms

---

### TB-4 Customer-site Canvas Theme Renderer — COMPLETE (2026-06-13)

**فایل‌های جدید (customer-site):**
- `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` — extends BlockRenderer، module:* blocks را با داده زنده رندر می‌کند
- `app/Http/Controllers/Front/ThemeCanvasPageController.php` — GET / و GET /page/{slug} با fallback
- `resources/views/layouts/canvas-theme.blade.php` — full-page RTL Vazirmatn بدون admin sidebar
- `routes/web.php` — `home` و `page.show` به ThemeCanvasPageController منتقل شدند

**معماری:**
- `ThemeCanvasRenderer` از `BlockRenderer` ارث می‌برد
- `renderBlock()` override می‌کند تا `module:*` types را قبل از schema validation جداسازی کند
- Module blocks: `module:blog_posts`, `module:shop_product_grid`, `module:courses_grid`, `module:pricing_table`, `module:contact_quick`, `module:faq_dynamic`, `module:support_button` و ده‌ها block دیگر
- Module blocks با داده زنده: Post::published(), Product::active(), Course::active()
- Controller fallback: اگر `active_canvas_theme_data` در Setting خالی باشد → HomeController/PageController
- Page resolution: پشتیبانی از هر دو فرمت pages (indexed و associative)
- Cache: theme data با Cache::remember 300 ثانیه کش می‌شود

**URLs:**
```
GET  /          → ThemeCanvasPageController@index (canvas home → HomeController fallback)
GET  /page/{slug} → ThemeCanvasPageController@show (canvas page → FrontPageController fallback)
```

---

## LAST COMPLETED SPRINT

### TB-5 Canvas Theme Admin Preview — COMPLETE (2026-06-13)

**فایل‌های جدید (customer-site):**
- `resources/views/admin/theme-design/canvas.blade.php` — مدیریت قالب Canvas (پالت رنگ، صفحات، هدر/فوتر blocks، preview link، deactivate)

**فایل‌های تغییریافته (customer-site):**
- `app/Http/Controllers/Admin/ThemeDesignController.php` — `canvasTheme()` + `canvasDeactivate()` + `$canvasActive`/`$canvasName` در index()
- `routes/web.php` — 2 route: `GET admin/theme-design/canvas` + `POST admin/theme-design/canvas/deactivate`
- `resources/views/admin/theme-design/index.blade.php` — بنر وضعیت Canvas در بالای صفحه

**URLs:**
```
GET  /admin/theme-design/canvas             → مدیریت قالب Canvas
POST /admin/theme-design/canvas/deactivate  → غیرفعال‌سازی قالب Canvas
```

---

### TB-3 AI URL Analyzer — COMPLETE (2026-06-13)

**ThemeAiAnalyzerService:**
- `analyzeUrl(url, notes)` → fetches URL, DOMXPath extracts (title/h1/h2/nav/footer/og/colors) → Claude `claude-sonnet-4-6` → parsed theme JSON
- `createFromJson(json, name)` → `Theme::create()` with full blocks structure

**ThemeAiController:** form / analyze / analyzeAjax / confirm / generate / generatePrepare

**UI:** 3-step Alpine.js (form → progress bar → block preview + color swatch → ذخیره و باز کردن canvas)

**Env:** `ANTHROPIC_API_KEY` / `services.anthropic.key`

---

### TB-2 Theme Marketplace — COMPLETE (2026-06-13)

**New Tables:** `theme_marketplace`, `theme_licenses`, `theme_purchase_orders`  
**Platform:** `ThemeMarketplaceAdminController` (manage listings, grant/revoke licenses), `ThemeMarketplaceApiController` (tenant API), `ThemeMarketplacePaymentController` (Zarinpal/IDPay callback → grant license)  
**Customer-site:** `PlatformApiService` + marketplace methods (tenantGet/tenantPost with license_key Bearer), `ThemeMarketplaceController`, 2 views (index + my-licenses)  
**Purchase Flow:** customer-site API call → platform creates ThemePurchaseOrder → payment gateway → callback → ThemeLicense::grant() → redirect back → install  
**Routes:** 12 total (6 admin, 5 API, 1 public callback)  
**Migrations:** ✅ All 3 applied

**URLs (platform admin):**
```
GET  /admin/theme-marketplace              → مدیریت فروشگاه
PUT  /admin/theme-marketplace/{id}/listing → به‌روزرسانی قیمت/اطلاعات
GET  /admin/theme-marketplace/licenses     → مدیریت لایسنس‌ها
GET  /theme-marketplace/callback           → callback درگاه (public)
```

**URLs (customer-site admin):**
```
GET  /admin/theme-marketplace              → مرور فروشگاه
POST /admin/theme-marketplace/{id}/purchase → شروع خرید
GET  /admin/theme-marketplace/my-licenses  → قالب‌های من
POST /admin/theme-marketplace/{id}/install → نصب قالب
```

---

### Public Learning Portal (customer-site) — COMPLETE (2026-06-11)

**CourseClassCompletion:** جدول جدید برای tracking پیشرفت کلاس‌ها  
**Front\CourseController:** index, show, enroll (رایگان)، myLearning، learn، markComplete  
**4 view:** courses/index (grid+filter+search), courses/show (detail+class list+progress), courses/learn (sidebar+AJAX complete), courses/my-learning (progress per course)  
**Routes:** 13 route زیر `module:courses` middleware  
**Env:** `MODULE_COURSES_ENABLED=true` در production  
**Tests:** 423/423 pass

**URLs:**
```
GET  /courses                              → لیست دوره‌ها
GET  /courses/{slug}                       → جزئیات دوره
POST /courses/{slug}/enroll               → ثبت‌نام (auth)
GET  /my-learning                          → داشبورد دانشجو
GET  /courses/{slug}/learn/{classOrder?}  → محیط یادگیری
POST /courses/{slug}/learn/{classId}/complete → تکمیل جلسه (AJAX)
```

---

### Infrastructure Hardening Batch — COMPLETE (2026-06-11)

**INFRA-3:** InstallController path-traversal guard + atomic `.env` write + LocalAI `LOCAL_AI_ENABLED` gate  
**INFRA-4:** `BackupDatabaseCommand` (`db:backup --retention=N`) + scheduled at 02:00 + Slack on failure  
**INFRA-5:** `sms_logs` table + `SmsLog` model + `InfraSmsController` + dashboard view + route  
**8.3d:** `SlackAlertService` singleton (`critical/high/warning/info/deploymentEvent`) + `slack:test` command + wired to backup failure + SMS dual-failure  
**scheduler:** Education AI commands (generate-content, generate-gap-content, refresh-recommendations) added to Kernel.php

**Key new files:**
- `app/Console/Commands/BackupDatabaseCommand.php`
- `app/Console/Commands/TestSlackAlertCommand.php`
- `app/Services/Infrastructure/SlackAlertService.php`
- `app/Models/SmsLog.php`
- `app/Http/Controllers/Admin/Infrastructure/InfraSmsController.php`
- `resources/views/admin/infrastructure/sms/index.blade.php`
- `database/migrations/2026_06_10_000001_create_sms_logs_table.php`

**Env vars required:**
- `SLACK_ALERT_WEBHOOK=https://hooks.slack.com/...`
- `SLACK_ALERT_CHANNEL=#alerts`
- `LOCAL_AI_ENABLED=false` (default)

---

### CACHE-1: Redis Cache Architecture — COMPLETE (2026-06-08)

Named stores, isolated flush, CacheManager service. Full implementation.

**Delivered:**
- `CacheManager.php` — `key()`, `store()`, `remember/forget/put/get/flushStore`
- 6 named Redis stores on separate DBs (DB3–DB8): users, customers, themes, tickets, modules, rate_limit
- Eliminated all `Cache::flush()` — replaced with per-store flush
- 7 services/repositories refactored to use named stores
- 15/15 tests pass

**Redis DB Map:** DB0=queues, DB1=legacy, DB2=sessions, DB3=rate_limit, DB4=users, DB5=customers, DB6=themes, DB7=tickets, DB8=modules

---

### EDU-17: Live AI Provider Integration — CERTIFIED COMPLETE (2026-06-07)

All 5 tasks delivered. AI provider layer fully operational.

**Commits:** `da0cbbc` (Phase 2/3) · `18e218c` (Phase 4/5)  
**Tests:** 57 passed / 0 failed / 77 assertions  
**Validation score:** 100/100

**Delivered:**
- `ClaudeAiContentProvider` — real Claude API HTTP adapter
- `CircuitBreakerAiContentProvider` — CLOSED/OPEN/HALF_OPEN state machine
- `PersianEducationPromptBuilder` — Persian-language prompt construction
- `TenantAiQuotaManager` integration — quota check before circuit, key: `edu_site_{siteId}`
- `EDUCATION_AI_PROVIDER` env drives provider selection in `EducationServiceProvider`

**Technical Debt M-01 (LOW):** `education:validate` does not yet check EDU-17-specific components. Future maintenance pass only — EDU-17 is CLOSED.

---

## CURRENT MODULE

**Education Module** at `packages/saas-platform/modules/education/`  
Namespace: `SaaSPlatform\Modules\Education`

**Service layer (EDU-14A/14B/15):**
```
src/Services/Education/
├── UserLearningProfileService.php       — learning profile (cached 15min)
├── KnowledgeGapService.php              — gap scoring (cache: edu_gap.{userId}.{siteId})
├── AIRecommendationScoreService.php     — scores existing recommendations
├── ContentReadinessService.php          — module content health (0-100)
├── RecommendationRefreshService.php     — expires stale, regenerates
├── KnowledgeGapQueueService.php         — in-memory gap queue for generation
├── ContentDeficitQueueService.php       — in-memory deficit queue for generation
├── EducationAnalyticsService.php        — combined analytics snapshot
├── EducationAiContentGenerationService.php — stub (no live AI until env set)
├── EducationDraftPersistenceService.php — persist drafts with dedup
└── GeneratedContentValidator.php        — validates generated content DTOs
```

**Draft generators (no DB writes — return DTOs only):**
```
src/Generation/
├── ArticleDraftGenerator.php
├── WalkthroughDraftGenerator.php
└── LearningPathDraftGenerator.php
```

**API endpoints active:**
```
GET  /api/v1/education/articles              — mobile articles list
GET  /api/v1/education/articles/{id}         — single article
GET  /api/v1/education/search                — full-text search
GET  /api/v1/education/learning-paths        — learning paths
POST /api/v1/education/articles/{id}/progress — mark progress
GET  /api/v1/education/profile               — learning profile + gaps + AI-scored recs (EDU-14A)
GET  /api/v1/education/analytics             — combined snapshot (EDU-14B)
GET  /api/v1/education/generated-content     — draft overview, admin only (EDU-15/S-02)
GET|POST /api/v1/education/sync/{siteId}/... — customer site sync (EDU-12)
```

---

## RECENT DECISIONS

1. **AI generators return DTOs only** — no DB writes inside generators (ADR-001)
2. **Persistence = command responsibility** — commands call `EducationDraftPersistenceService::persist()` (ADR-001)
3. **Two-layer dedup in persist()** — `lockForUpdate` + `QueryException(1062)` catch (ADR-005)
4. **whereNull for null siteId** — never `where('site_id', null)` in MySQL (ADR-004)
5. **x-bind:attr on Blade components** — never `:attr="alpineVar"` (ADR-008)
6. **Education AI is stub until EDU-16** — `EDUCATION_AI_PROVIDER` env gates live calls (ADR-011)

---

## RECENT FINDINGS (ALL RESOLVED)

| ID | Severity | Description | Status |
|----|----------|-------------|--------|
| F-01 | HIGH | GenerateContentCommand set module_slug='unknown' | FIXED |
| T-01 | CRITICAL | Security test false positives (withTenantSite() fix) | FIXED |
| F-02 | MEDIUM | logFailure() not implemented in interface | FIXED |
| RC-01 | MEDIUM | No dedup index on edu_articles | FIXED (migration added) |
| S-02 | MEDIUM | /generated-content had no policy | FIXED (viewGeneratedContent policy) |

**Open findings:** See AUDIT_HISTORY.md — OPF-001 through OPF-004.

---

## OPEN TASKS

All major epics are complete. Next sprint options:

### TB3 Sprint 2 (Theme Builder V3)
- [ ] TB3-04: Block duplication (right-click or clone button)
- [ ] TB3-05: Nested columns / grid layouts (2-col, 3-col, sidebar+content)
- [ ] TB3-06: Per-block style editor (background, padding, border, shadow via right panel)
- [ ] TB3-07: Undo/redo history (JS state array)
- [ ] TB3-08: Block locking / hide in mobile

### Low Priority Backlog
- M2.1-e: AppImage packaging (Linux desktop)
- M2.1-f: System tray icon assets
- M-01: `education:validate` EDU-17 component checks (LOW debt)

---

## NEXT ACTION

**All production safety findings resolved.** Platform is production-ready.

Next: TB3 Sprint 2 or user-directed feature.

Verify production health:
```bash
ssh -p 9011 root@87.107.12.81 "cd /var/www/saas-platform && php artisan monitor:platform"
```

---

## KEY RULES (Never Violate)

1. **No `App\` imports in packages** — use `config('module.key')` pattern
2. **Every content table has site_id** — structural tenant isolation
3. **whereNull for null siteId** — never `where('site_id', null)` in MySQL
4. **Generators return DTOs only** — no DB writes inside generation services
5. **x-bind:attr on Blade components** — never `:attr="alpineVar"` (crashes PHP)
6. **Persian UI everywhere** — RTL, Vazirmatn font, Persian numerals
7. **All views: professional, beautiful** — cards, icons, proper spacing (platform law)
8. **Cross-browser: Chrome/Firefox/Safari/Edge** — flex+margin fallbacks, standard colors
9. **Firebase is optional** — Reverb + Redis is primary. System works without Firebase
10. **Education AI is stub until EDU-16 + env** — no live LLM calls before review workflow exists

---

## PLATFORM MATURITY SCORES

| Area | Score | Notes |
|------|-------|-------|
| Security | 96/100 | Phase 8.0 |
| Plugin Governance | 98/100 | Phase 8.6 |
| Observability | 82/100 | Needs Redis + Slack + OTel |
| Runtime Governance | 98/100 | Phase 8.4 |
| Scalability | 90/100 | Phase 8.5 |
| Frontend Authority | 100/100 | Phase 10.1 |
| Forms Module | 97/100 | Phase 9.x |
| AI Provider Integration | 96/100 | Phase 9.5-A |
| Mobile Client | TBD | Phase M2.0 |
| Education Module | ~93/100 | Post EDU-15.2 |

---

## REFERENCE MAP

| What | Where |
|------|-------|
| All project phases | `docs/` — one folder per phase |
| Education module | `packages/saas-platform/modules/education/` |
| Design tokens | `packages/saas-platform/core-utils/resources/css/design-tokens.css` |
| Blade components | `packages/saas-platform/core-utils/resources/views/components/` |
| Admin views | `resources/views/admin/` |
| Mobile app | `mobile/lib/` |
| Customer site | `c:\laragon\www\customer-site` |
| AI config | `config/ai.php` |
| Session memory | `C:\Users\Farshad-IT\.claude\projects\c--laragon-www-saas-platform\memory\` |
| Project memory | `docs/project-memory/` ← YOU ARE HERE |
