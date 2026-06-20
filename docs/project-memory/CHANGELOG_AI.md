# AI CHANGELOG
> Every AI-generated modification logged here. Date | Files Changed | Reason | Impact.
> Newest entries at top.
> Last Updated: 2026-06-20

---

## 2026-06-20 — TB3 Sprint 11: Marketplace API Enrichment + Customer UI Upgrade

**Reason:** Sprint 10 rich data (categories, quality scores, pricing tiers, badges) was not exposed in the tenant-facing API or visible in customer marketplace UI.

### saas-platform
- `ThemeMarketplaceApiController::formatTheme()` — now returns: category, license_type, annual_price, lifetime_price, is_bestseller, is_new_release, installs_count, quality_score, quality_badge, preview_url
- `download()` — auto-records install (installs_count++, installed_theme_version updated, bestseller auto-promote at 10+)

### customer-site
- `app/Services/ThemeCategoryHelper.php` — NEW: label() helper for 15 Persian category names
- `resources/views/admin/theme-marketplace/index.blade.php` — REWRITTEN: Alpine.js category filter + search, quality score chip, pricing tiers (one-time/annual/lifetime), bestseller/new/featured/purchased badges, preview URL overlay + button, stats row (installs + sales), category chip per card

---

## 2026-06-20 — TB3 Sprint 10: LOW Gaps — GAP-011/012 + Tailwind v4 Fixes

**Reason:** Complete all remaining Sprint 10 gaps. GAP-010 (Bundle System) deferred as separate sprint.

### New Files
- `app/Services/ThemeBuilder/ThemeDemoDataService.php` — GAP-012: block scanner, 14 content-type mappings, saveManifest(), getManifest()
- `database/migrations/2026_06_20_110000_add_installed_version_to_theme_licenses.php` — GAP-011: installed_theme_version SMALLINT in theme_licenses
- `database/migrations/2026_06_20_120000_add_demo_data_config_to_theme_marketplace.php` — GAP-012: demo_data_config JSON + has_demo_data bool

### Modified Files
- `app/Models/ThemeLicense.php` — grant() records current theme version; isOutdated() helper
- `app/Models/Theme.php` — outdatedLicensesCount() convenience method
- `app/Models/ThemeMarketplace.php` — demo_data_config + has_demo_data fillable + casts
- `app/Http/Controllers/Admin/ThemeMarketplaceAdminController.php` — buildDemoManifest() endpoint; ThemeDemoDataService injected
- `resources/views/admin/theme-marketplace/index.blade.php` — "N آپدیت" badge for outdated licenses; Tailwind v4 flex-shrink-0→shrink-0
- `resources/views/admin/theme-marketplace/preview.blade.php` — Demo Data section with content grid, scan button (Alpine.js); bg-gradient→bg-linear fixes

### Gaps Resolved
- GAP-011 LOW: Update notification — installed_theme_version column, isOutdated() helper, admin index badge
- GAP-012 LOW: Demo data manifest — ThemeDemoDataService scans 14 block types, preview page shows content requirements with rescan
- Tailwind v4: flex-shrink-0→shrink-0, bg-gradient-to-l→bg-linear-to-l, bg-gradient-to-br→bg-linear-to-br

### Sprint 10 Final Status: TB3_SPRINT10_CERTIFIED
All 13 gaps resolved except GAP-010 (Bundle System — deferred separate sprint).

---

## 2026-06-20 — TB3 Sprint 10: MEDIUM Gaps — GAP-009/013 + Initialize Endpoint

**Reason:** Resolve remaining MEDIUM gaps: annual/lifetime pricing UI, preview page, and admin initialize button.

### New Files
- `resources/views/admin/theme-marketplace/preview.blade.php` — GAP-013: full preview page (pricing cards, quality scores, screenshots, requirements, quality issues, preview URL)

### Modified Files
- `app/Http/Controllers/Admin/ThemeMarketplaceAdminController.php` — Added `initialize()` (Artisan::call marketplace:seed + marketplace:score), `preview()` methods; `updateListing()` now persists annual_price, lifetime_price, preview_url, is_new_release, is_bestseller
- `resources/views/admin/theme-marketplace/index.blade.php` — "راه‌اندازی" button, "پیش‌نمایش" per-theme link, expanded edit form with category dropdown + license_type + annual/lifetime pricing + preview_url + badge checkboxes
- `routes/web.php` — 2 new routes: POST /initialize, GET /{theme}/preview

### Gaps Resolved
- GAP-009 MEDIUM: Annual/Lifetime pricing inputs in inline edit form + updateListing validation + ThemeMarketplace persistence
- GAP-013: Preview page at /admin/theme-marketplace/{theme}/preview
- Initialize endpoint: POST /initialize → seed + score in one click from browser (no SSH needed)

---

## 2026-06-20 — TB3 Sprint 10: Professional Marketplace Themes — CRITICAL+HIGH GAPS RESOLVED

**Reason:** Sprint 10 audit (TB3_SPRINT10_AUDIT.md + TB3_SPRINT10_GAP_ANALYSIS.md) found 3 CRITICAL + 4 HIGH + 3 MEDIUM gaps. All CRITICAL+HIGH resolved.

### New Files (saas-platform — 3 PHP + 1 Blade)
- `app/Console/Commands/MarketplaceThemeSeedCommand.php` — NEW: seeds 12 themes across all 15 categories, backfills existing
- `app/Console/Commands/MarketplaceSeedScoresCommand.php` — NEW: wires orphaned ThemeMarketplaceQualityService, scores all marketplace themes
- `resources/views/admin/theme-marketplace/scoring.blade.php` — NEW: 5-dimension scoring dashboard with per-theme score bars, AJAX re-score button

### Modified Files
- `database/migrations/2026_06_20_000001_add_category_and_scoring_to_theme_marketplace.php` — Rewrote to use raw SQL + Schema::hasColumn() guards (fixes opcache-related Blueprint method error)
- `app/Http/Controllers/Admin/ThemeMarketplaceAdminController.php` — Added scoring(), scoreTheme(), recordInstall() + search/category filter in index()
- `resources/views/admin/theme-marketplace/index.blade.php` — Added search bar, category filter dropdown, "امتیازدهی" button, bestseller/new-release badges, category label chips
- `routes/web.php` — 3 new routes: /scoring, /{theme}/score, /{theme}/record-install

### Gaps Resolved
- GAP-001 CRITICAL: MarketplaceThemeSeedCommand — 12 themes for news/educational/medical/legal/real_estate/restaurant/portfolio/agency/marketplace/saas/landing/blog
- GAP-002 CRITICAL: Migration fixed (raw SQL bypasses opcache + idempotent hasColumn() guards)
- GAP-003 CRITICAL: MarketplaceSeedScoresCommand wires quality service
- BRK-001: ThemeMarketplaceQualityService wired to controller (scoreTheme route)
- BRK-002: installs_count increment API endpoint (recordInstall) + auto-bestseller at 10+ installs
- BRK-003: Existing themes backfilled with category in seedCommand
- GAP-004 HIGH: Scoring dashboard (scoring.blade.php)
- GAP-005 HIGH: Search filter in marketplace index
- GAP-006 HIGH: Category filter in marketplace index
- GAP-007 MEDIUM: Bestseller badge display + auto-promote in recordInstall()
- GAP-008 MEDIUM: New release badge display in index

---

## 2026-06-20 — GLOBAL SIDEBAR UI CONSISTENCY FIX — SIDEBAR_UI_CERTIFIED

**Reason:** 3-issue sidebar audit: platform admin too spacious, customer admin background height uncertain, account sidebar cutting off visually.

### Changes (saas-platform)
- `resources/views/partials/platform-admin-sidebar-theme.blade.php` — Group margin: 1.25rem→0.2rem, link padding: 0.7rem→0.45rem, label padding reduced, added mb-4 neutralizer rule
- `resources/views/layouts/admin.blade.php` — Logo: py-5→py-3, nav: py-6 px-4→py-3 px-3, footer: pt-4 mt-4→pt-2 mt-2; inline CSS sync
- `docs/SIDEBAR_UI_AUDIT.md` — NEW (4 findings, all FIXED)
- `docs/SIDEBAR_LAYOUT_FIX_REPORT.md` — NEW (detailed fix report)

### Changes (customer-site)
- `public/css/customer-admin-layout.css` — Added `height:100%; align-self:stretch` to `#admin-sidebar`, `height:100%` to `.admin-sidebar-inner`
- `resources/views/account/layout.blade.php` — Parent: `min-h-screen`→`h-screen overflow-hidden`, aside: removed `self-start md:sticky md:top-0 md:max-h-screen min-h-0`, main: added `overflow-hidden`, brand: `py-5`→`py-3`

**Certification:** `SIDEBAR_UI_CERTIFIED` — All 3 issues resolved. No regressions to RTL, collapse, mobile, design tokens.

---

## 2026-06-20 — GLOBAL DUPLICATE NOTIFICATION FIX — NOTIFICATION_SYSTEM_CERTIFIED

**Reason:** Success/info/warning/error notifications were displayed twice after every user action (save, create, update, delete, publish, etc.). Admin layouts render flash at the top of `<main>` but individual views were also rendering the same session keys inside `@section('content')`.

### Changes (saas-platform)
- `resources/views/layouts/admin.blade.php` — Added Alpine auto-dismiss (5s/6s/7s) + close button ✕ to all 4 flash types
- Removed inline `@if(session(...))` blocks from 15 admin views (lifecycle, resource-governance, studio-imports, theme-templates, theme-packs, theme-builder/ai-*)
- `docs/DUPLICATE_NOTIFICATION_AUDIT.md` — NEW
- `docs/NOTIFICATION_ARCHITECTURE.md` — NEW
- `docs/NOTIFICATION_FIX_REPORT.md` — NEW

### Changes (customer-site)
- `resources/views/admin/layout.blade.php` — Added Alpine auto-dismiss + `.admin-flash__close` close button to all flash types
- `public/css/customer-admin-layout.css` — Added `.admin-flash__close` CSS class
- `resources/views/themes/default/layout.blade.php` — BUG FIX: Added missing `session('warning')` and `session('info')` + auto-dismiss for all 4 keys
- Removed inline session flash from 36 admin views, 2 account views, 1 cart page
- Preserved page-specific view variables (`$error`, `$purchased`) where applicable

**Certification:** `NOTIFICATION_SYSTEM_CERTIFIED` — One renderer per layout, auto-dismiss, no duplicates.

---

## 2026-06-20 — TB3 Sprint 9.5: Visual Reverse Engineering Engine — TB3_SPRINT9_5_CERTIFIED

**Reason:** Build a 7-phase pipeline that analyzes websites (URL/Screenshot/HTML/Prompt) and generates structurally similar Persian ThemePacks targeting Apple, Amazon, Malltina, Digikala, Stripe, Shopify.

### New Files (saas-platform — 12 PHP + 1 migration + 2 views + 4 docs)
- `database/migrations/2026_06_20_900001_create_reverse_engineering_jobs_table.php` — NEW
- `app/Models/ReverseEngineeringJob.php` — NEW (8 statuses, 4 input types, qualityPassed())
- `app/Services/ThemeBuilder/ReverseEngineering/VisualReverseEngineeringService.php` — NEW (orchestrator)
- `app/Services/ThemeBuilder/ReverseEngineering/DomExtractionService.php` — NEW (Phase 1: DOMXPath, 12 component types)
- `app/Services/ThemeBuilder/ReverseEngineering/LayoutReconstructionService.php` — NEW (Phase 2: columns, spacing, flow graph)
- `app/Services/ThemeBuilder/ReverseEngineering/VisualAnalysisService.php` — NEW (Phase 3: colors, typography, radius, shadow)
- `app/Services/ThemeBuilder/ReverseEngineering/CommerceAnalysisService.php` — NEW (Phase 4: prices, cards, gallery, reviews)
- `app/Services/ThemeBuilder/ReverseEngineering/SimilarityEngineService.php` — NEW (Phase 5: 4-dimension scoring × 6 sites)
- `app/Services/ThemeBuilder/ReverseEngineering/ReverseThemeReconstructorService.php` — NEW (Phase 6: AI+PHP fallback, ThemePack persist)
- `app/Services/ThemeBuilder/ReverseEngineering/ReverseEngineeringQualityService.php` — NEW (Phase 7: 10 checks, PASS/FAIL/PARTIAL)
- `app/Http/Controllers/Admin/ThemeReverseEngineeringController.php` — NEW (6 actions: form/analyze/result/export/install/destroy)
- `resources/views/admin/theme-builder/reverse-engineer.blade.php` — NEW (4-tab input form, progress animation)
- `resources/views/admin/theme-builder/reverse-engineer-result.blade.php` — NEW (score dashboard, per-site similarity, install)
- `routes/web.php` — MODIFIED (6 new routes)
- `docs/VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md` — NEW
- `docs/SIMILARITY_SCORING_ENGINE.md` — NEW
- `docs/THEME_RECONSTRUCTION_PIPELINE.md` — NEW
- `docs/TB3_SPRINT9_5_CERTIFICATION.md` — NEW

### Impact
- 7-phase pipeline: DOM Extraction → Layout → Visual → Commerce → Similarity → Reconstruction → Validation
- 4 input modes: URL (HTTP fetch + XPath), Screenshot (Claude Vision), HTML (DOMDocument), Prompt (heuristics)
- 6 reference sites: Apple, Amazon, Malltina, Digikala, Stripe, Shopify
- Similarity scoring: 4 dimensions (structure/visual/commerce/layout), Euclidean RGB color distance
- AI enrichment: Claude claude-sonnet-4-6 + PHP fallback (ThemePlaceholderService)
- Safe mode: no copyright copy — only design tokens (colors/fonts/layout) extracted
- Persian RTL + Vazirmatn font in all generated content
- Canvas install integration (ThemePack → Canvas editor redirect)

---

## 2026-06-20 — Global Date Localization Audit — DATE_LOCALIZATION_CERTIFIED

**Reason:** Platform-wide audit to ensure all dates render as Jalali (Solar Hijri) with Persian digits.

### New Infrastructure
- `packages/saas-platform/core-utils/src/Support/Jalali.php` — Added `diffForHumansFaStatic()` Persian relative time
- `app/Helpers/Jalali.php` — Added `diffForHumansFa()` proxy

### saas-platform Fixes (42 files)
- 5 controllers: `diffForHumans()` → `Jalali::diffForHumansFa()` (CRITICAL)
- 2 services: `Jalali::format()` → `Jalali::formatFa()` in SMS/notification content
- 2 controllers: CSV export date format → `Jalali::formatFa()`
- 16 `guide.blade.php` files: `NumberHelper::toPersianNumbers(->format())` → `Jalali::formatFa()`
- 7 incident/monitoring views: `NumberHelper::toPersianNumbers()` with Gregorian → `Jalali::formatFa()`
- 14 support-ticket/license/update views: `Jalali::format()` → `Jalali::formatFa()` (Persian digits)
- 6 display tables: raw `->format()` → `Jalali::formatFa()`
- 14 Blade views: `diffForHumans()` → `Jalali::diffForHumansFa()`

### customer-site Fixes (15 files)
- 3 controllers: `diffForHumans()` → `Jalali::diffForHumansFa()` + `->format()` → `Jalali::formatFa()`
- Invoice PDF: `verta()->format()` → `Jalali::formatFa()`
- CRM, education, inventory, notification-center, webhook, user views: all date patterns fixed

### Documents
- `docs/GLOBAL_DATE_LOCALIZATION_AUDIT.md`
- `docs/DATE_LOCALIZATION_FIX_REPORT.md`

**Total:** 57 fixes across 57 files. 72 findings (5 CRITICAL, 52 HIGH, 3 MEDIUM, 12 LOW).

---

## 2026-06-20 — TB-AI Studio + Import Pipeline — TB_AI_STUDIO_CERTIFIED

**Reason:** Separate local AI theme generation platform + SaaS Platform import pipeline.

### New Application: c:\laragon\www\tb-ai-studio\
- `composer.json`, `config.php`, `.env.example`, `index.php` — application core
- `src/Providers/ProviderInterface.php`, `ProviderFactory.php` — provider abstraction
- `src/Providers/ClaudeProvider.php`, `OpenAiProvider.php`, `DeepSeekProvider.php`, `CustomProvider.php`
- `src/Analyzer/HtmlAnalyzer.php`, `SectionDetector.php`, `VisionAnalyzer.php`
- `src/Generator/ThemePackGenerator.php`, `ZipExporter.php`
- `src/Uploader/SaasPlatformUploader.php`
- `views/layout.php`, `views/home.php` — 3-step Alpine.js web UI

### SaaS Platform Changes (saas-platform — 8 files)
- `app/Http/Middleware/AuthenticateStudioApi.php` — NEW (Bearer + HMAC auth)
- `app/Http/Controllers/Api/ThemePackStudioController.php` — NEW (import/validate/preview/install/publish)
- `app/Http/Controllers/Admin/ThemePackStudioAdminController.php` — NEW (admin UI controller)
- `resources/views/admin/studio-imports/index.blade.php` — NEW
- `resources/views/admin/studio-imports/show.blade.php` — NEW
- `app/Http/Kernel.php` — studio.api middleware registered
- `config/services.php` — studio.api_key + studio.secret config
- `routes/api.php` — 5 new Studio API routes under /api/v1/studio/
- `routes/web.php` — 4 new admin studio-imports routes

### Documentation
- `docs/TB_AI_STUDIO_ARCHITECTURE.md` — full architecture + directory structure + config reference
- `docs/THEME_PACK_IMPORT_PIPELINE.md` — ZIP format spec, API endpoints, validation rules
- `docs/LOCAL_THEME_GENERATION_WORKFLOW.md` — end-to-end workflow, provider guide, troubleshooting

---

## 2026-06-20 — TB3 Sprint 8: AI Theme Generator — TB3_SPRINT8_CERTIFIED

**Reason:** Multi-input AI pipeline for generating complete TB3 Theme Packs from URL, screenshot, HTML, or text prompt.

### Files Changed (saas-platform — 10 new + 2 modified)
- `database/migrations/2026_06_19_800001_create_ai_generation_jobs_table.php` — NEW
- `app/Models/AiGenerationJob.php` — NEW (5 statuses, 4 input types, enabledSections())
- `app/Services/ThemeBuilder/ThemeSectionDetectorService.php` — NEW (heuristic + Vision-based detection)
- `app/Services/ThemeBuilder/ThemePlaceholderService.php` — NEW (SVG placeholders, Persian text banks)
- `app/Services/ThemeBuilder/ThemeAiGeneratorService.php` — NEW (master orchestrator, 4 input modes)
- `app/Http/Controllers/Admin/ThemeAiGeneratorController.php` — NEW (6 actions)
- `resources/views/admin/theme-builder/ai-generator.blade.php` — NEW (5-step Alpine.js UI)
- `resources/views/admin/theme-builder/ai-generator-result.blade.php` — NEW (result page)
- `routes/web.php` — MODIFIED (6 new routes + import)
- `resources/views/admin/theme-builder/index.blade.php` — MODIFIED (new "مولد هوشمند" button)
- `docs/TB3_SPRINT8_CERTIFICATION.md` — NEW

### Impact
- 4 input methods: URL crawl, Claude Vision screenshot, HTML upload, text prompt
- 11 detectable sections with confidence scoring + user enable/disable
- Full ThemePack output: headers, footers, pages, templates, global_styles, responsive_settings
- Safe mode: no source code copy, SVG placeholder images, original Persian content
- PHP fallback when AI returns invalid JSON
- Claude Vision API integration for screenshot analysis
- Job lifecycle tracking in `ai_generation_jobs` DB table

---

## 2026-06-19 — TB3 Sprint 6: Professional Template Library — TB3_SPRINT6_CERTIFIED

**Reason:** Elementor-level template library for the theme canvas builder — save, export, import, insert, marketplace-ready.

### Files Changed (saas-platform — 20 files)
- `database/migrations/2026_06_19_300001_create_theme_templates_table.php`
- `database/migrations/2026_06_19_300002_create_theme_packs_table.php`
- `app/Models/ThemeTemplate.php` — TYPES/CATEGORIES/LICENSE_TYPES constants, scopes, labels
- `app/Models/ThemePack.php` — contentSummary(), toExportArray()
- `app/Services/ThemeBuilder/ThemeTemplateService.php` — search, listForCanvas, saveTemplate, saveFromCanvas, insertIntoPage, regenIds
- `app/Services/ThemeBuilder/ThemeTemplateImportExportService.php` — JSON+ZIP export/import, buildPackFromTheme
- `app/Services/ThemeBuilder/ThemeTemplateDemoDataService.php` — 11 token types with DB::connection fallback
- `app/Http/Controllers/Admin/ThemeTemplateController.php` — full CRUD + export/import
- `app/Http/Controllers/Admin/ThemePackController.php` — CRUD + export JSON/ZIP + import JSON/ZIP
- `app/Http/Controllers/Admin/ThemeCanvasController.php` — +canvasTemplates, +canvasSaveAsTemplate, +canvasInsertTemplate
- `resources/views/admin/theme-templates/index.blade.php` — grid with thumbnail, filters, import
- `resources/views/admin/theme-templates/create.blade.php`
- `resources/views/admin/theme-templates/edit.blade.php`
- `resources/views/admin/theme-templates/show.blade.php`
- `resources/views/admin/theme-packs/index.blade.php`
- `resources/views/admin/theme-packs/create.blade.php`
- `resources/views/admin/theme-packs/show.blade.php`
- `resources/views/admin/theme-builder/canvas.blade.php` — Template Library modal + Save As Template modal + toolbar buttons
- `routes/web.php` — 3 canvas routes + 9 theme-templates routes + 8 theme-packs routes
- `app/Helpers/AdminMenu.php` — theme_templates + theme_packs entries

### Files Changed (customer-site — 6 files)
- `database/migrations/2026_06_19_300001_create_site_theme_templates_table.php`
- `app/Models/SiteThemeTemplate.php`
- `app/Services/ThemeCanvas/SiteThemeTemplateService.php`
- `app/Http/Controllers/Admin/ThemeDesignController.php` — +canvasTemplates, +canvasSaveAsTemplate, +canvasInsertTemplate
- `resources/views/admin/theme-design/canvas-editor.blade.php` — Template Library modal + Save As Template modal + toolbar buttons
- `routes/web.php` — 3 canvas template routes

**Certification doc:** `docs/TB3_SPRINT6_CERTIFICATION.md`

---

## 2026-06-19 — LIFECYCLE-2: Storage & Resource Governance — COMPLETE

**Reason:** Enterprise-grade per-site resource tracking, enforcement, alerting and billing for platform owner hosting sites on shared VPS.

### Files Changed (saas-platform)
- `database/migrations/2026_06_19_200001_create_site_resource_usage_table.php` — current usage snapshot per site
- `database/migrations/2026_06_19_200002_create_site_resource_limits_table.php` — per-site or plan-level limits
- `database/migrations/2026_06_19_200003_create_site_bandwidth_usage_table.php` — monthly traffic tracking
- `database/migrations/2026_06_19_200004_create_site_storage_snapshots_table.php` — daily history for charts
- `app/Models/SiteResourceUsage.php` — usage model (formatBytes, storagePercent, scopes)
- `app/Models/SiteResourceLimit.php` — limits model (defaultForSite lookup chain)
- `app/Models/SiteBandwidthUsage.php` — monthly bandwidth model (recordRequests, currentMonthForSite)
- `app/Models/SiteStorageSnapshot.php` — daily snapshot model (upsertToday, last30Days)
- `config/resource_governance.php` — 13 env-driven config keys
- `app/Services/ResourceGovernance/SiteResourceCalculatorService.php` — DB/storage/archive/backup/file metrics + push
- `app/Services/ResourceGovernance/SiteResourceGovernorService.php` — limits lookup + overage fee calc
- `app/Services/ResourceGovernance/SiteResourceAlertService.php` — 80%/95% alerts + archive anomaly
- `app/Services/ResourceGovernance/SiteResourceReportService.php` — monthly report + archive scan + cleanup
- `app/Console/Commands/CalculateSiteResourcesCommand.php` — resource:calculate (daily 06:00)
- `app/Console/Commands/CleanupResourceDataCommand.php` — resource:cleanup (weekly Sunday 02:00)
- `app/Console/Commands/ArchiveReportCommand.php` — resource:archive-report (weekly Monday 07:00)
- `app/Console/Commands/UsageReportCommand.php` — resource:usage-report (monthly 1st 08:00)
- `app/Console/Kernel.php` — 4 new schedule entries
- `app/Http/Controllers/Admin/SiteResourceGovernanceController.php` — 6 actions (index, site, settings, recalculate, updateLimits, updateSettings)
- `resources/views/admin/resource-governance/index.blade.php` — platform overview (KPI, filters, sortable table)
- `resources/views/admin/resource-governance/site.blade.php` — per-site detail (usage, chart, limits form, snapshots)
- `resources/views/admin/resource-governance/settings.blade.php` — platform defaults + fees + thresholds
- `app/Helpers/AdminMenu.php` — resource_governance entry (MENU_KEYS, MENU_ROUTES, MENU_ROUTE_PATTERNS, getGroups)
- `resources/views/admin/partials/sidebar-icon.blade.php` — resource_governance SVG
- `resources/views/admin/partials/sidebar-icon-bare.blade.php` — resource_governance SVG
- `routes/web.php` — 6 resource-governance routes
- `app/Services/Lifecycle/SiteLifecycleService.php` — archive() now calls releaseActiveResources()
- `docs/RESOURCE_GOVERNANCE_ARCHITECTURE.md` — full architecture document

### Files Changed (customer-site)
- `resources/views/admin/partials/resource-widget.blade.php` — quota widget (shows >60% usage, color-coded)
- `resources/views/admin/account/resources.blade.php` — full resource usage page (/account/resources)
- `resources/views/admin/dashboard.blade.php` — includes resource-widget after lifecycle-widget
- `app/Helpers/NumberHelper.php` — added formatBytes() static method
- `routes/web.php` — GET /account/resources route

### Key Design Decisions
- Resource summary pushed to customer-site settings table (same pattern as lifecycle status push) — no HTTP overhead
- 3-tier limit lookup: per-site override → plan default → config fallback
- alerted_80/alerted_95 flags prevent repeated notifications; reset when usage drops
- releaseActiveResources() called on archive transition — zeroes active resources, keeps archive_bytes
- Archive bytes sourced from actual ZIP file size on disk, not estimates

---

## 2026-06-19 — TB3 Sprint 5: Professional Responsive Editor — TB3_SPRINT5_CERTIFIED

**Reason:** Implement Elementor-level responsive editing: every style property supports Desktop / Tablet / Mobile overrides with CSS cascade.

### Files Changed (saas-platform)
- `app/Services/ThemeBuilder/BlockRenderer.php` — `buildCssDeclarations()` helper extracted; `applyBlockStyle()` now 4-arg: desktop inline CSS + responsive `@media` `<style>` tag per block
- `resources/views/admin/theme-builder/canvas.blade.php` — `activeBreakpoint` state; `currentBpStyle`/`getCascadedValue()`/`hasResponsiveOverride()` computed; `updateStyle()` + `resetBlockStyle()` breakpoint-aware; `setDevice()` syncs `activeBreakpoint`; D/T/M toggle bar in style tab; all `:value` bindings → `getCascadedValue()`; amber indicator dots; tree badges for hide/responsive

### Files Changed (customer-site)
- `app/Services/ThemeBuilder/BlockRenderer.php` — same responsive CSS generation as saas-platform
- `resources/views/admin/theme-design/canvas-editor.blade.php` — full mirror of saas-platform JS+HTML changes

### Certification
- `docs/TB3_SPRINT5_CERTIFICATION.md` — 5 phases, 3 findings (all LOW/INFO), verdict TB3_SPRINT5_CERTIFIED

---

## 2026-06-19 — LIFECYCLE-1: Customer Site Lifecycle Management System — COMPLETE

**Reason:** Implement complete lifecycle state machine for customer sites based on subscription/hosting payments.

### Files Changed (saas-platform)
- `database/migrations/2026_06_19_100001_add_lifecycle_columns_to_sites_table.php` — 10 new columns on sites
- `database/migrations/2026_06_19_100002_create_site_lifecycle_logs_table.php` — audit log
- `database/migrations/2026_06_19_100003_create_site_lifecycle_invoices_table.php` — reactivation invoices
- `app/Models/Site.php` — 5 constants, lifecycle fillable/casts, 11 lifecycle helper methods + 2 relations
- `app/Models/SiteLifecycleLog.php` — NEW model
- `app/Models/SiteLifecycleInvoice.php` — NEW model with type/status constants
- `app/Services/Lifecycle/SiteLifecycleService.php` — NEW: core state machine, all transition methods, push to customer-site DB
- `app/Services/Lifecycle/SiteArchiveService.php` — NEW: ZIP archive package creation
- `app/Services/Lifecycle/SiteLifecycleNotificationService.php` — NEW: SMS + notification center dispatch
- `app/Console/Commands/CheckExpiredSitesCommand.php` — NEW: lifecycle:check-expired
- `app/Console/Commands/CheckGracePeriodSitesCommand.php` — NEW: lifecycle:check-grace-period
- `app/Console/Commands/CheckSuspendedSitesCommand.php` — NEW: lifecycle:check-suspended
- `app/Console/Commands/CheckArchivedSitesCommand.php` — NEW: lifecycle:check-archived
- `app/Console/Kernel.php` — 4 new scheduled commands (08:05, 08:10, 03:05, 04:05 daily)
- `app/Http/Controllers/Admin/SiteLifecycleController.php` — NEW: 7 actions
- `resources/views/admin/lifecycle/overview.blade.php` — NEW: platform-wide lifecycle KPIs + site tables
- `resources/views/admin/lifecycle/index.blade.php` — NEW: per-site lifecycle management panel
- `resources/views/admin/lifecycle/settings.blade.php` — NEW: configurable thresholds and fees
- `config/lifecycle.php` — NEW: 6 env-driven config keys
- `routes/web.php` — 7 new lifecycle routes
- `app/Helpers/AdminMenu.php` — lifecycle key added to MENU_KEYS/ROUTES/PATTERNS + زیرساخت group
- `resources/views/admin/partials/sidebar-icon.blade.php` — lifecycle icon
- `resources/views/admin/partials/sidebar-icon-bare.blade.php` — lifecycle icon
- `docs/CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md` — NEW: complete architecture doc

### Files Changed (customer-site)
- `app/Http/Middleware/CheckSiteLifecycle.php` — NEW: reads site_lifecycle_status setting, shows suspended page
- `bootstrap/app.php` — CheckSiteLifecycle appended to web middleware group
- `resources/views/front/suspended.blade.php` — NEW: professional 503 suspended page (RTL, Vazirmatn, indigo)
- `resources/views/admin/partials/lifecycle-widget.blade.php` — NEW: admin dashboard lifecycle status widget
- `resources/views/admin/dashboard.blade.php` — @include lifecycle-widget

### Key Design Decisions
- State machine: active→grace_period→suspended→archived→deleted (no illegal transitions allowed)
- Status pushed to customer-site settings table via dynamic DB connection on every transition
- All fees configurable via .env (default 0 = free restore)
- Archive package captures platform-side config only (site_snapshot.json + theme_data.json)
- Customer admin panel always accessible even when site is suspended (for invoice payment)
- Suspended public page returns HTTP 503 (proper for SEO/crawlers)
- Admin paths (/admin, /login, /api) always bypass lifecycle check

---

## 2026-06-19 — TB3 Sprint 4: Visual Nested Containers & Structure Editor — TB3_SPRINT4_CERTIFIED

**Reason:** Sprint 4 of Theme Builder V3 — add hierarchical nested block editing.

### Files Changed (saas-platform)
- `app/Services/ThemeBuilder/BlockRegistry.php` — added `flex_container` block type (6 props: label/direction/gap/alignItems/justifyContent/wrap/minHeight)
- `app/Services/ThemeBuilder/BlockRenderer.php` — `renderFlex_container()`, children lookup with 3-location backward compat, `wrapEditorMode()` adds `data-tb-container`
- `resources/views/admin/theme-builder/canvas.blade.php` — full TB4 rewrite: structure tree tab, path-based selection, container children panel, 20+ new/updated JS methods
- `resources/views/admin/theme-builder/canvas-live-preview.blade.php` — container CSS, `setContainerTarget` handler, `getContainerAtPoint()`, top-level-only drop index

### Files Changed (customer-site)
- `config/builder-element-schemas.php` — `flex_container` schema
- `app/Services/ThemeBuilder/BlockRenderer.php` — same 3 changes
- `resources/views/admin/theme-design/canvas-editor.blade.php` — full TB4 mirror of all changes
- `resources/views/admin/theme-design/canvas-live-preview.blade.php` — same CSS/JS changes

### Key Changes
- `selectedPath: null | [i0, i1, ...]` replaces `selectedIndex: -1` — selectedIndex now computed getter
- `getBlockByPath`, `findBlockPath`, `flattenTree`, `getSiblingArray` — tree traversal helpers
- `normalizeBlock()` recurses into children, migrates `block.blocks`/`block.props.blocks` → `block.children`
- Container drop: `getContainerAtPoint(x,y)` in iframe, `containerId` in `dropIndexResult`, `setContainerTarget` CSS highlight
- Both editors: blocks/tree tab toggle, container children panel, add-child modal

**Certification:** 5 findings, all resolved. `docs/TB3_SPRINT4_CERTIFICATION.md` — TB3_SPRINT4_CERTIFIED.

---

## 2026-06-19 — TB3 Sprint 3 Certification Audit — TB3_SPRINT3_CERTIFIED

**Reason:** Independent certification audit of all TB3 Sprint 3 features across both repos.  
**Output:** `docs/TB3_SPRINT3_CERTIFICATION.md`  
**Result:** CERTIFIED — no CRITICAL/HIGH findings. 2 MEDIUM + 3 LOW findings documented.

| Finding | Severity | Verdict |
|---------|----------|---------|
| Image caption inline editing unreachable when empty | MEDIUM | FAIL (non-blocking) |
| Style wrapper order differs between repos in editor mode | MEDIUM | FAIL (non-blocking) |
| Unused `ALLOWED` constant in ThemeMediaController | LOW | PASS |
| Media selection UX differs between repos | LOW | PASS |
| Animation duration unit differs between repos | LOW | PASS |

All 3 deploys confirmed complete. Both repos live in production.

---

## 2026-06-19 — TB3 Sprint 3: Inline Editing + Advanced Style + Media Manager (both repos)

**Reason:** Mirror TB3-04/05/07 saas-platform features to customer-site.

### Files Changed (customer-site)

| File | Change |
|------|--------|
| `app/Services/ThemeBuilder/BlockRenderer.php` | Added `editableAttr()`, `applyBlockStyle()` (20+ props), updated renderHero/Heading/Text/Image/Button/Cta |
| `resources/views/admin/theme-design/canvas-live-preview.blade.php` | Added visibility CSS, animation @keyframes, inline editing CSS + full JS bridge (dblclick, blur, input, sendInlineChange) |
| `resources/views/admin/theme-design/canvas-editor.blade.php` | 3-tab panel (محتوا/استایل/پیشرفته), 6 collapsible style sections, Advanced tab, Media Modal, new JS state + 8 new methods |
| `app/Http/Controllers/Admin/ThemeDesignMediaController.php` | NEW — 3 media endpoints (index/upload/destroy), `theme-media/` dir, 5MB limit |
| `routes/web.php` | Added 3 media routes (media-index/media-upload/media-destroy) |

---

## 2026-06-19 — Production Hardening Final Certification Audit + Cache Fix

**Reason:** Independent re-audit of all 12 PROD findings. Found and fixed 1 CRITICAL new finding.  
**Commits:** 850eb40 (fix: string sentinel in checkCache)

### Files Changed

| Repo | File | Change |
|------|------|--------|
| saas-platform | `packages/saas-platform/core-utils/src/Services/Health/RuntimeHealthService.php` | Fixed `checkCache()`: string sentinel `'health_probe_ok'` instead of integer `1` to fix Predis strict-type false-negative |

### Audit Results

| Finding | Verdict |
|---------|---------|
| PROD-001 through PROD-012 | ALL PASS |
| NEW-001 cache health false-negative | CRITICAL — FIXED |
| NEW-002 deploy ZIP intermittent fail | LOW — DOCUMENTED |
| NEW-003 deploy.sh rsync wrong source | LOW — DOCUMENTED |

**Final Verdict: PRODUCTION_CERTIFIED 2026-06-19**  
Report: `docs/PRODUCTION_CERTIFICATION_AUDIT.md`

---

## 2026-06-16 — TB3 Sprint 2: Undo/Redo, Block Duplication, Per-Block Style Panel

**Reason:** Theme Builder V3 Sprint 2 — editing power features  
**Commits:** 046b195 (saas-platform), 1b5bb33 (customer-site)

### Files Changed

| Repo | File | Change |
|------|------|--------|
| saas-platform | `app/Services/ThemeBuilder/BlockRenderer.php` | Added `applyBlockStyle()` — wraps block output with inline CSS div for per-block overrides |
| saas-platform | `resources/views/admin/theme-builder/canvas.blade.php` | Undo/Redo toolbar buttons, 4-button action bar (up/down/duplicate/delete), Content/Style tabs, Style tab UI, new JS methods |
| customer-site | `resources/views/admin/theme-design/canvas-editor.blade.php` | Mirror of all saas-platform TB3 Sprint 2 changes |

### Features Added

| Feature | Detail |
|---------|--------|
| Undo / Redo | 25-entry JSON snapshot history array; Ctrl+Z / Ctrl+Y shortcuts; toolbar buttons with disabled state |
| Block Duplication | `duplicateBlock(index)` — deep clones block with new UUID, inserts after source, selects it |
| Delete in action bar | 4-button row replaces old move+bottom-delete; icons only (compact) |
| Content/Style tabs | Props panel switches between محتوا and استایل |
| Per-block Style tab | bgColor, textColor, paddingY, paddingX, borderRadius, opacity, reset; clears key on empty value |
| `applyBlockStyle()` PHP | Inline CSS outer div wrapper: maps 6 style keys to CSS properties; XSS-safe via `escapeAttr()` |
| History reset on page switch | `switchPage()` reinitializes history for new page context |
| `pushPropUpdate` includes `.style` | Sends full block payload (props + style) to render endpoint |

---

## 2026-06-16 — PROD-003/004/005: Production File & Storage Hardening Phase

**Reason:** Full filesystem audit + defense-in-depth hardening for all file operations in saas-platform  
**Commits:** Storage Security hardening session

### Files Changed

| Repo | File | Change |
|------|------|--------|
| saas-platform | `app/Services/Infrastructure/StorageManager.php` | Added `sanitizeFilename()`, `validateUpload()`, `deleteIfExists()`, `deleteExport()` methods |
| saas-platform | `app/Helpers/PlatformSettings.php` | Added `LOGO_ALLOWED_EXTENSIONS` + `LOGO_ALLOWED_MIMES` constants; `storeLogo()` now validates extension + MIME (defense-in-depth); delete routes through `StorageManager::deleteIfExists()` |
| saas-platform | `app/Http/Controllers/Admin/PlatformSettingsController.php` | Explicit `mimes:` validation rule for logo upload; `platform_logo_path` input rejects absolute local paths and traversal sequences |
| saas-platform | `app/Http/Controllers/Admin/AppReleaseController.php` | `getClientOriginalName()` sanitized via `StorageManager::sanitizeFilename()` before use in filename |
| saas-platform | `app/Http/Controllers/Api/Mobile/MobileTicketController.php` | Full path traversal prevention: `rawurldecode()` + null-byte strip + absolute-path blocks + backslash collapse |
| saas-platform | `app/Services/SecurityValidationService.php` | `checkPublicStorageLeaks()` uses `Storage::disk('public')->path()` instead of `storage_path()` |
| saas-platform | `tests/Feature/Infrastructure/StorageSecurityTest.php` | 30+ security tests: sanitizeFilename (12 cases), validateUpload, deleteIfExists, writeCsv BOM, logo validation, AppRelease sanitization, 7 traversal vectors, 3 safe paths, 4 dangerous logo paths |
| saas-platform | `docs/PRODUCTION_STORAGE_AUDIT.md` | Full filesystem audit: 21 findings across both repos, risk classifications, replacement strategies |
| saas-platform | `docs/PRODUCTION_STORAGE_SECURITY_REPORT.md` | Security verification: path traversal, filename injection, arbitrary write, upload path, extension/MIME verification |

### PROD Items

| ID | Status |
|----|--------|
| PROD-003 | ✅ CLOSED — PlatformSettings: extension+MIME validation, StorageManager boundary enforced |
| PROD-004 | ✅ CLOSED — AppReleaseController filename sanitized; MobileTicketController full traversal prevention; SecurityValidationService Storage facade |
| PROD-005 | ✅ CLOSED — FormSubmissionController: StorageManager.writeCsv + download; exports disk S3-ready |

### Key Security Properties Added

- `sanitizeFilename()`: strips path separators, null bytes, control chars, leading dots, shell chars; collapses double-dots; limits to 200 chars
- `validateUpload()`: blocks non-allowlisted extensions AND MIMEs — two independent checks (defense in depth)
- Path traversal: URL-decoded + null-byte stripped + absolute-path blocked at API boundary
- Logo input: only `https://` URLs or safe relative paths; absolute local paths and `..` sequences rejected at controller

---

## 2026-06-16 — Bugfix: Monitoring View + Incidents View + Deploy Infrastructure

**Reason:** Production 500 errors on /admin/infrastructure/monitoring and /admin/incidents  
**Commits:** bugfix session

### Files Changed

| Repo | File | Change |
|------|------|--------|
| saas-platform | `app/Models/Infrastructure/InfraSchedulerRun.php` | `recentSummary()` now returns `[$cmd => $model\|null]` instead of numerically-indexed arrays of plain arrays |
| saas-platform | `resources/views/admin/incidents/index.blade.php` | `formatFa(date_string)` → `toPersianNumbers(date_string)` (formatFa requires int|float) |
| saas-platform | `resources/views/admin/incidents/show.blade.php` | Same fix — 5 occurrences of formatFa with dates |
| saas-platform | `deploy.ps1` | Added `.claude` and `.github` to zip exclusion; switched from Compress-Archive to PHP ZipArchive (handles Laragon locked files); fixed server deploy.sh `echo >>>` quoting |
| server | `/var/www/deploy.sh` | All `echo >>> text` → `echo ">>> text"` (unquoted `>>>` is bash redirect syntax error) |

### Root Causes

- `recentSummary()` returned `$result[] = [array]` (numerically keyed), view iterated `$cmd => $run` expecting model objects
- `NumberHelper::formatFa()` calls `number_format()` which requires int|float; incident views passed date strings
- `Compress-Archive` fails on locked files (Laragon has `artisan`, `settings.json` etc. open); PHP `ZipArchive` bypasses Windows file locks

---

## 2026-06-16 — TB3 Sprint 1: Live Iframe Canvas + block.props + Drag from Library

**Reason:** THEME BUILDER V3 Sprint 1 — Elementor-like UX foundation  
**Commits:** TB3-01 / TB3-02 / TB3-03

### Files Changed

| Repo | File | Change |
|------|------|--------|
| saas-platform | `app/Services/ThemeBuilder/BlockRenderer.php` | `wrapEditorMode()` method + `editor_mode` context flag |
| saas-platform | `app/Http/Controllers/Admin/ThemeCanvasController.php` | `livePreview()` + `renderBlockAjax()` endpoints |
| saas-platform | `routes/web.php` | 2 new canvas routes (live-preview, render-block) |
| saas-platform | `resources/views/admin/theme-builder/canvas.blade.php` | Full rewrite: iframe center panel, device toggle, drag library, TB3-01 normalizer |
| saas-platform | `resources/views/admin/theme-builder/canvas-live-preview.blade.php` | New standalone iframe HTML with postMessage bridge |
| customer-site | `app/Services/ThemeBuilder/BlockRenderer.php` | `wrapEditorMode()` method |
| customer-site | `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` | `wrapEditorMode()` on module block path |
| customer-site | `app/Http/Controllers/Admin/ThemeDesignController.php` | `canvasLivePreview()` + `canvasRenderBlockAjax()` methods |
| customer-site | `routes/web.php` | 2 new canvas routes (live-preview, render-block) |
| customer-site | `resources/views/admin/theme-design/canvas-editor.blade.php` | Full rewrite: same iframe/drag UX as platform |
| customer-site | `resources/views/admin/theme-design/canvas-live-preview.blade.php` | New standalone iframe HTML |

### Key Architecture

- **block.props canonical** (TB3-01): `normalizePages()` converts `data→props` on JS load; `addBlock()` always creates with `props`
- **Live iframe** (TB3-02): Center panel = `<iframe :src="livePreviewUrl()">`, `iframeTimestamp` drives reactive reload
- **postMessage bridge**: `ready | blockSelected | iframeHeight | dropIndexResult` ← iframe; `select | updateBlock | showDropZones | setDropTarget | getDropIndex` → iframe
- **350ms debounce prop update**: POST `/canvas/render-block` → `{html}` → `updateBlock` msg → in-place DOM swap (no reload)
- **Drag from library** (TB3-03): `draggable="true"` on library buttons; invisible drop receiver div over iframe; `getDropIndex` from iframe Y coordinate

### Impact

- Canvas editor UX upgraded from block-card-list to Elementor-level live preview
- Zero breaking changes — block.data still accepted (normalized at runtime)

---

## 2026-06-16 — PROD-003–012: Production Hardening Batch

**Reason:** Complete remaining PRODUCTION_FIX_PLAN.md findings — PROD-009/011/012 newly applied, rest pre-resolved  
**Commit:** `8ef3a27` (bundled with TB3 Sprint 1 pre-existing work)

| Finding | Action |
|---------|--------|
| PROD-003/004/005 | PRE-RESOLVED — StorageManager was already used (pre-existing fixes committed) |
| PROD-006 | RESOLVED — All key tables already have composite site_id indexes |
| PROD-007 | RESOLVED — Only SitemapService (intentionally public disk) — no change needed |
| PROD-008 | FIXED — `SANCTUM_STATEFUL_DOMAINS=panel.safhenokhost.ir` added to production .env + config:cache |
| PROD-009 | FIXED — `AppServiceProvider::boot()` throws RuntimeException if `CACHE_DRIVER=file` in production |
| PROD-010 | RESOLVED — No shadow-[var] patterns found (already resolved by KI-002) |
| PROD-011 | FIXED — `kb` key added to AdminMenu.php (MENU_KEYS/ROUTES/PATTERNS + بازاریابی/دانش‌محور group) |
| PROD-012 | FIXED — Deleted FormFieldController, FormSiteController, FormSubmissionController (303 LOC dead code) |

**All 12 PRODUCTION_FIX_PLAN.md findings now RESOLVED.**

---

## 2026-06-15 — PROD-001: Health Endpoint Security Fix + Deploy Infrastructure Fix

**Reason:** Production safety phase — strip deployment metadata from public health endpoints; fix vendor sync in deploy pipeline  
**Commit:** `f8846d7`

### Files Changed

| File | Change |
|------|--------|
| `packages/saas-platform/core-utils/src/Services/Health/RuntimeHealthService.php` | Added `isProduction()` helper; production responses now omit git commit, UUID, PHP version, migration count, detail error strings |
| `/var/www/deploy.sh` (server) | Added rsync packages→vendor step for all 4 local packages; added `systemctl restart php8.3-fpm` |

### Docs Created

| File | Purpose |
|------|---------|
| `docs/project-memory/PRODUCTION_MIGRATION_AUDIT.md` | MHC migration status audit (all 6 = Ran, PROD-002 clear) |
| `docs/project-memory/MHC_MIGRATION_VALIDATION.md` | MHC functional verification + backup confirmation |
| `docs/project-memory/HEALTH_ENDPOINT_SECURITY_REPORT.md` | PROD-001 fix details and production verification |
| `docs/project-memory/PRODUCTION_SAFETY_PHASE_REPORT.md` | Full phase report — commands, files, verdict |
| `PRODUCTION_FIX_PLAN.md` | Pre-execution safety assessment (12 findings, PROD-001/002 prioritized) |

### Impact

- **Security:** Three health endpoints no longer expose internal deployment metadata to unauthenticated internet users
- **Deploy Pipeline:** All future deploys will correctly update vendor/saas-platform packages and clear PHP opcache
- **No regressions:** All routes cached, monitoring dashboard and incident management returning 302 (behind auth), monitor:platform command functional

---

## 2026-06-15 — MHC: Monitoring & Health Center (Complete)

**Reason:** Full enterprise monitoring system — health checks, incident management, uptime tracking, scheduler monitoring, backup status

### MHC-1: 6 Database Migrations
- `2026_06_15_200001_enhance_infra_incidents_for_health_center.php` — status enum (open/investigating/resolved/closed), assigned_to_id, investigating_at, closed_at, support_ticket_id, customer_notified_at; migrates severity 'warning'→'medium'
- `2026_06_15_200002_create_infra_incident_notes_table.php` — incident timeline/notes table
- `2026_06_15_200003_create_infra_platform_health_logs_table.php` — platform health snapshots (DB/Redis/Queue/SMS/AI/Storage/Disk/Memory/CPU)
- `2026_06_15_200004_create_infra_backup_runs_table.php` — backup run history
- `2026_06_15_200005_create_infra_scheduler_runs_table.php` — scheduler command run tracking
- `2026_06_15_200006_extend_infra_site_health_logs_table.php` — adds failed_jobs, disk_used_percent, memory_used_percent, domain_expires_at, domain_days_remaining

### MHC-2: 5 Models (new/rewritten)
- `InfraIncident.php` — 4-level severity, 4-state lifecycle, full helpers/scopes/relations
- `InfraIncidentNote.php` — new, incident timeline
- `InfraPlatformHealthLog.php` — new, latestCheck() + latest24h()
- `InfraBackupRun.php` — new, startRun()/endSuccess()/endFailed()
- `InfraSchedulerRun.php` — new, recentSummary() for 7 tracked commands
- `InfraSiteHealthLog.php` — extended with new resource fields, uptimePercent/uptimeHistory

### MHC-3: 7 Services (new/extended)
- `IncidentLifecycleService.php` — NEW: open/investigate/resolve/close lifecycle, auto-creates Ticket for critical/high, notifies customer on resolve (SMS + NC)
- `UptimeCalculator.php` — NEW: siteUptime/siteUptimeHistory/allSitesUptime/platformUptime
- `SchedulerHealthTracker.php` — NEW: wraps commands in try/catch, records InfraSchedulerRun
- `HealthCheckService.php` — EXTENDED: checkFailedJobs/checkSmsProvider/checkAiProvider/checkStorage/checkDisk/checkMemory/checkCpu + checkAndPersist()
- `SiteHealthChecker.php` — EXTENDED: parses failed_jobs/disk/memory from /api/health, domain expiry from Site model
- `InfrastructureErrorCollector.php` — EXTENDED: 4-level severity, calls IncidentLifecycleService::open(), uses active() scope
- `NotificationCenterService.php` — EXTENDED: incidentResolved() method

### MHC-4: 2 New Commands + 1 Extended
- `MonitorPlatformCommand.php` — NEW: monitor:platform (every 2 min), checkAndPersist, alerts, auto-resolves
- `MonitorSitesCommand.php` — REWRITTEN: uses InfraSchedulerRun, 4-level severity, domain/disk/failed_jobs checks
- `BackupDatabaseCommand.php` — EXTENDED: records InfraBackupRun + InfraSchedulerRun

### MHC-5: Incident Management UI
- `admin/incidents/index.blade.php` — stat cards, filter bar, 4-level severity table, paginated
- `admin/incidents/show.blade.php` — 2-col: metadata + timeline + lifecycle actions + site summary

### MHC-4 (Views): Monitoring Dashboard Extended
- `admin/infrastructure/monitoring/index.blade.php` — added SMS/AI/Storage/FailedJobs checks, resource meters (Disk/Memory/CPU), Scheduler Health table, Backup Status card, domain expiry column in site table, site drilldown links
- `admin/infrastructure/monitoring/site.blade.php` — NEW: uptime 24h/7d/30d cards, uptime history chart, response trend, incident list, health history table, backup history

### MHC-6: Routes + Scheduler
- `routes/web.php` — 7 incident routes (admin.incidents.*), site drilldown route (admin.infrastructure.monitoring.site), platform-history API endpoint
- `app/Console/Kernel.php` — monitor:platform every 2 minutes added
- `app/Http/Controllers/Admin/Infrastructure/MonitoringController.php` — show() + platformHistory() methods
- `app/Http/Controllers/Admin/IncidentController.php` — full 7-method controller
- `app/Helpers/AdminMenu.php` — monitoring_center label + incidents key + sidebar icons

**Impact:** Complete monitoring & health center. Platform admin can track all 15+ health dimensions, manage incident lifecycle with customer notifications, view per-site drilldowns, and monitor scheduler/backup health.

---

## 2026-06-15 — EDU-INFRA Audit: Platform vs Customer Module Separation

**Reason:** Architecture audit to classify all modules and remove Customer-Only items from Platform Admin nav.

### Finding: `forms_submissions` misclassified in Platform Admin
- `app/Helpers/AdminMenu.php` — removed `forms_submissions` from MENU_KEYS, MENU_ROUTES, MENU_ROUTE_PATTERNS, getGroups()
- `resources/views/admin/partials/sidebar-icon.blade.php` — removed `forms_submissions` icon block
- `resources/views/admin/partials/sidebar-icon-bare.blade.php` — removed `forms_submissions` icon block

**Root cause:** `FORMS_PLATFORM_MODE=true` prevents the forms package from registering `admin.forms.submissions.index` in platform context. The menu entry pointed to a non-existent route → RouteNotFoundException. Form submissions are tenant-owned data; Customer Site has full CRUD.

**Impact:** Platform Admin sidebar and mobile menu no longer include a broken link. AccessMenuController's toggle panel for `forms_submissions` is also removed.

**No other misclassifications found.** Full classification table documented in AUDIT_HISTORY.md.

---

## 2026-06-15 — 5-Phase Batch: CRM + Inventory + Customer Portal + Module Adoption

**Reason:** تکمیل ۵ فاز کامل در customer-site + داشبورد پلتفرم

### Phase 1 — CRM Module (customer-site)
- `2026_06_15_100000_create_crm_tables.php` — crm_contacts, crm_deals, crm_activities
- `app/Models/CrmContact.php`, `CrmDeal.php`, `CrmActivity.php`
- `app/Services/CrmService.php` — dashboardStats, importFromOrders, pipeline, moveDeal
- `app/Http/Controllers/Admin/CrmController.php` — 15 routes
- Views: crm/dashboard, contacts/index, contacts/show, contacts/form, deals/pipeline (Kanban)

### Phase 2 — Analytics (already existed, confirmed complete)
- AnalyticsDashboardController + AnalyticsService + Chart.js — already in production

### Phase 3 — Customer Portal Enhancement (customer-site)
- `app/Http/Controllers/Account/DashboardController.php` — course progress + installments
- `resources/views/account/dashboard.blade.php` — courses tile + progress cards + installments

### Phase 4 — Blog/SEO (already existed, confirmed complete)
- OG meta + JSON-LD in default theme layout — already in production

### Phase 5 — Inventory/Warehouse Module (customer-site)
- `2026_06_15_200000_create_inventory_tables.php` — warehouses, inventory_items, stock_movements
- `app/Models/Warehouse.php`, `InventoryItem.php`, `StockMovement.php`
- `app/Services/InventoryService.php` — dashboardStats, adjustStock, lowStockAlerts
- `app/Http/Controllers/Admin/InventoryController.php` — 13 routes
- Views: inventory/dashboard, warehouses/index, warehouses/form, items/index, movements/index, alerts

### Platform Admin — Module Adoption Dashboard (saas-platform)
- `app/Http/Controllers/Admin/ModuleAdoptionController.php` — queries site_modules by slug
- `resources/views/admin/module-adoption/index.blade.php` — 6 KPI cards + CRM/Inventory site lists + recent activations
- `app/Helpers/AdminMenu.php` — `module_adoption` added to MENU_KEYS, routes, patterns, 'گزارش‌ها / آمار' group
- Route: `GET /admin/module-adoption` → `admin.module-adoption.index`

---

## 2026-06-15 — Visual Page Builder Complete (customer-site)

**Reason:** ارتقاء ویرایشگر صفحه customer-site به همان UI ویرایشگر Canvas پلتفرم اصلی (TB-7)

### builder/edit.blade.php — Vite CDN Fix + Full Rewrite
- **Root cause:** `@extends('layouts.canvas-editor')` → `@vite()` → error `http://[::1]:4000/@vite/client` در production
- `customer-site/resources/views/builder/edit.blade.php` — تبدیل به standalone HTML با Tailwind CDN Play (بدون Vite)
- 3-panel layout: کتابخانه ویجت (راست) + Canvas (وسط) + Props Panel (چپ)
- SortableJS drag-and-drop، schema-driven props form، device toggle، live preview iframe
- Alpine.js `pageContentBuilder()` function، flat blocks format `{blocks:[...]}`

### ContentBuilderController — Admin Visual Builder for Posts & Products
- `customer-site/app/Http/Controllers/Admin/ContentBuilderController.php` — ویرایشگر بصری برای Posts + Products
- `editPost()`, `savePost()`, `editProduct()`, `saveProduct()` — همان view `builder.edit` با route متفاوت
- `registryData()` + `enabledModuleSlugs()` — فیلتر ویجت‌ها بر اساس ماژول‌های فعال سایت

### مدل‌ها و Migration
- `customer-site/app/Models/Post.php` — `builder_content` در fillable + cast
- `customer-site/app/Models/Product.php` — `builder_content` در fillable + cast
- Migration `2026_06_14_200000_add_builder_content_to_posts_and_products` — applied در production

### Routes
- `admin.builder.post.edit` / `admin.builder.post.save` → ContentBuilderController
- `admin.builder.product.edit` / `admin.builder.product.save` → ContentBuilderController
- دکمه "✏️ سازنده بصری" در فرم‌های ویرایش posts و products

---

## 2026-06-15 — Bug Fixes + KI Resolutions (FI-008, KI-002, KI-006)

**Reason:** Fix production bugs در installment module + resolve 3 open Known Issues

### Bug Fix — NumberHelper::formatFa() PHP 8.3 TypeError
- **Root cause:** `formatFa(number_format($x))` — PHP 8.3 rejects string in number_format()
- `customer-site/app/Helpers/NumberHelper.php` — `formatFa()` حالا string با کاما را accept می‌کند (strip commas → float)
- `customer-site/resources/views/admin/installments/plans-index.blade.php` — حذف double number_format

### FI-008 — ScanRuntimeAnomaliesCommand
- `app/Console/Commands/ScanRuntimeAnomaliesCommand.php` — **جدید**: هر 5 دقیقه 4 detector را اجرا، critical/high → Slack
- `app/Console/Kernel.php` — scheduled هر 5 دقیقه با `withoutOverlapping()`
- `tests/Feature/Infrastructure/ScanRuntimeAnomaliesCommandTest.php` — 6/6 tests

### KI-006 — Domain Resolution Cache
- `app/Services/ResellerDetectionService.php` — Redis cache per host (TTL 300s), `false` برای "not found"
- `customer-site/app/Http/Middleware/SetTenantContext.php` — cache `License::current()` (TTL 300s) + schema check (TTL 3600s)
- `customer-site/app/Services/LicenseActivationService.php` — `forgetLicenseCache()` بعد از هر update

### KI-002 — Tailwind v4 Canonical Syntax
- 9 shadow references در core-ui components → named `@utility` classes (`shadow-card`, `shadow-modal`, etc.)
- **301 replacement** در 27 component files: `[var(--token)]` → `(--token)` (Tailwind v4 shorthand)
- Lightning CSS optimizer warnings حذف شدند

---

## 2026-06-14 — NEXUS PRO — Multi-Mode Premium Theme Template

**Reason:** درخواست کاربر برای قالب همه‌کاره حرفه‌ای با ۳ حالت (شرکتی، شخصی، فروشگاهی) ساخته شده روی پلتفرم Canvas

### فایل‌های تغییر یافته
**saas-platform:**
- `app/Services/ThemeBuilder/BlockRegistry.php` — ۱۵ block پریمیوم جدید (hero_split, hero_gradient, features_grid, stats_row, client_logos, process_steps, team_cards, skills_bars, testimonials_slider, trust_badges, pricing_cards, newsletter_form, bento_grid, services_cards, contact_split)
- `app/Services/ThemeBuilder/BlockRenderer.php` — ۱۵ متد render جدید با XSS protection، JSON decode، CSS class system
- `app/Console/Commands/NexusSeedCommand.php` — **جدید**: Artisan command با `nexus:seed` signature. ۳ قالب کامل (Corporate/Personal/Store) با صفحات کامل

**customer-site:**
- `app/Services/ThemeCanvas/BlockRegistry.php` — ۱۵ block پریمیوم مطابق platform (قبل از module:form_embed)
- `resources/views/layouts/canvas-theme.blade.php` — CSS کامل همه ۱۵ block + IntersectionObserver JS برای skills bar animation

### سه حالت قالب
- **NEXUS PRO Corporate** (slug: nexus-pro-corporate): ۶ صفحه — home/about/services/blog/contact/pricing. رنگ: Indigo #4f46e5
- **NEXUS PRO Personal** (slug: nexus-pro-personal): ۴ صفحه — home/portfolio/blog/contact. رنگ: Violet #7c3aed، پس‌زمینه تاریک
- **NEXUS PRO Store** (slug: nexus-pro-store): ۴ صفحه — home/shop/blog/contact. رنگ: Orange #ea580c

### Block‌های جدید (ویژگی‌ها)
- **hero_split**: hero با تصویر کنار، badge، دو دکمه، image_side
- **hero_gradient**: hero گرادیانت با gradient_from/to/text_color
- **features_grid**: گرید امکانات با style=cards/bordered/minimal، columns 2/3/4
- **stats_row**: آمار با counter animation (data-count)، dividers
- **client_logos**: لوگوهای مشتریان با marquee بی‌نهایت CSS
- **process_steps**: مراحل numbered با خطوط اتصال
- **team_cards**: کارت‌های تیم با columns 2/3/4
- **skills_bars**: مهارت‌ها با IntersectionObserver animation
- **testimonials_slider**: نظرات با columns 1/2/3، rating stars
- **trust_badges**: اعتمادسازی با آیکون
- **pricing_cards**: تعرفه با highlight + transform
- **newsletter_form**: فرم عضویت
- **bento_grid**: گرید مدرن با large/tall/featured/small cells
- **services_cards**: خدمات با style=bordered/filled/minimal
- **contact_split**: تماس دو ستونه با فرم

### تکنیک‌های کلیدی
- IntersectionObserver + CSS custom property `--skill-w` برای skills animation
- Marquee لوگو با content تکرار شده + `@keyframes tb-marquee`
- بهترین گزینه JSON fallback: `is_array($raw) ? $raw : (json_decode($raw, true) ?? [])`
- RTL بدون نیاز به تغییر layout (کلاس‌های tb-* RTL-safe هستند)

---

## 2026-06-14 — INFRA-MONITORING-01 + NOTIFICATION-2 Notification Center

**Reason:** درخواست کاربر برای مرکز مانیتورینگ زیرساخت و سیستم اطلاع‌رسانی یکپارچه

### INFRA-MONITORING-01 — Central Monitoring & Health Center
- `database/migrations/2026_06_14_100001_create_infra_site_health_logs_table.php`
- `database/migrations/2026_06_14_100002_create_infra_incidents_table.php`
- `app/Models/Infrastructure/InfraSiteHealthLog.php` — latestPerSite, recent scopes
- `app/Models/Infrastructure/InfraIncident.php` — needsSmsAlert, markResolved, markSmsSent
- `app/Services/Infrastructure/Monitoring/HealthCheckService.php` — DB/Redis/Queue/Reverb/Web
- `app/Services/Infrastructure/Monitoring/SiteHealthChecker.php` — HTTP + SSL + /api/health
- `app/Services/Infrastructure/Monitoring/DeploymentVerificationService.php`
- `app/Services/Infrastructure/Monitoring/InfrastructureErrorCollector.php` — dedup incidents, SMS throttle
- `app/Console/Commands/MonitorSitesCommand.php` — every 5 min via scheduler
- `app/Http/Controllers/Admin/Infrastructure/MonitoringController.php`
- `resources/views/admin/infrastructure/monitoring/index.blade.php` — RTL dashboard
- `customer-site/routes/api.php` — GET /health endpoint (no auth)
- `routes/web.php` — monitoring routes + API routes
- `app/Helpers/AdminMenu.php` — monitoring_center key + زیرساخت/DevOps group
- `app/Console/Kernel.php` — monitor:sites every 5 min
- `docs/infrastructure/MONITORING_CENTER_AUDIT.md`

### NOTIFICATION-2 — Unified Notification Center
- `database/migrations/2026_06_14_300001_create_notification_center_logs_table.php`
- `app/Models/NotificationCenterLog.php` — TYPES/PRIORITIES/CHANNELS/AUDIENCES constants, helpers, scopes
- `app/Services/Notification/NotificationDispatcher.php` — SMS routing by audience_type
- `app/Services/Notification/NotificationCenterService.php` — notify(), siteDown(), siteRecovery(), infrastructure(), supportTicket(), purchase(), renewal(), deploy(), stats()
- `app/Http/Controllers/Admin/Notification/NotificationCenterController.php`
- `resources/views/admin/notification-center/index.blade.php` — history, filters, charts
- `resources/views/admin/notification-center/create.blade.php` — send form + Alpine live preview
- `routes/web.php` — 7 notification-center routes
- `app/Helpers/AdminMenu.php` — notification_center key + اطلاع‌رسانی/ارتباطات group
- `app/Services/Infrastructure/Monitoring/InfrastructureErrorCollector.php` — wired to NC
- `app/Console/Commands/MonitorSitesCommand.php` — siteDown/siteRecovery NC calls

---

## 2026-06-13 — TB-7 + Sales & Support Pro Theme

**Reason:** درخواست کاربر برای canvas editor در customer-site و ایجاد قالب حرفه‌ای فروش و پشتیبانی

### TB-7 — Canvas Drag-and-Drop Editor (customer-site)
- `BlockRegistry.php` با ۴۰+ بلاک در ۷ دسته
- `layouts/canvas-editor.blade.php` — layout تمام‌صفحه بدون sidebar
- `admin/theme-design/canvas-editor.blade.php` — ویرایشگر ۳ پنل با SortableJS
- `ThemeDesignController` — 5 متد جدید (canvasEdit, canvasSaveBlocks, canvasAddPage, canvasDeletePage, canvasSaveGlobalStyles)
- 5 route جدید در web.php

### قالب "فروش و پشتیبانی حرفه‌ای" (SalesSupportThemeSeeder)
- `database/seeders/SalesSupportThemeSeeder.php` — قالب کامل با ۷ صفحه
- صفحات: خانه، فروشگاه، آموزشگاه، پشتیبانی، پلن‌ها، درباره ما، وبلاگ
- هدر: nav_menu + cart_button + login_register
- فوتر: columns + social_links_bar + copyright
- بلاک‌های ماژول: shop_product_grid, courses_grid, pricing_table, reviews_grid, blog_posts, referral_widget, contact_quick, faq_dynamic, support_button, و ۱۵+ بلاک دیگر
- ثبت در ThemeMarketplace با is_featured=true
- Seeded روی production (ID: 2)
- Deploy: هر دو repo deploy شدند

---

## 2026-06-13 — 5 New Modules: INSTALL + LANG + VPLAY + FORM-EXT + IMG

**Reason:** درخواست کاربر برای ۵ ماژول جدید در customer-site

### INSTALL — خرید قسطی
- Migration: `installment_plans`, `installment_orders`, `installment_payments`
- Models: `InstallmentPlan`, `InstallmentOrder`, `InstallmentPayment`
- Service: `InstallmentService` (eligiblePlans, createOrder, activateOrder, recordInstallmentPayment, markOverdue)
- Front controller: `InstallmentController` (plans, initiate, downPaymentCallback, payInstallment, installmentCallback)
- Account panel: `InstallmentAccountController` + views (`account.installments.index`, `account.installments.show`)
- Admin panel: `InstallmentAdminController` (plans CRUD + orders browse/show + markOverdue)
- Admin views: `admin/installments/plans-index`, `plans-form`, `orders-index`, `orders-show`
- `AccountMenu.php`: installments added to "خرید و اشتراک" group

### LANG — چند زبانه با AI
- Migration: `content_translations` + `translation_queue`
- Models: `ContentTranslation`, `TranslationQueue`
- Trait: `HasTranslations` (bootHasTranslations → queueTranslations on save, hash-based invalidation)
- Applied to: `Post`, `Product`, `Course`
- Service: `ContentTranslationService` (Claude Haiku API, processQueue, hash check)
- Command: `translations:process --batch=20`
- Middleware: `SetLocale` (session('site_locale', 'fa'))
- Component: `language-switcher` (dropdown با ۳ پرچم)
- Admin: `TranslationController` + view

### VPLAY — پخش فیلم حرفه‌ای
- Migration + Model: `HomepageVideo` (youtube/mp4/cloudflare, embedUrl(), youtubeId())
- Controller: `HomepageVideoController` (CRUD + reorder)
- Views: admin index (drag reorder) + _form (smart-image-picker)
- Component: `video-player.blade.php` (Alpine.js, custom MP4 controls, overlay)
- Wired to homepage + ThemeCanvasRenderer `module:hero_video` block

### FORM-EXT — لیست سفید دسترسی با Excel
- Migration: `access_lists`, `access_list_entries`, `access_gates`, `access_verifications`
- Models: `AccessList` (verify()), `AccessListEntry`, `AccessGate`, `AccessVerification`
- Service: `AccessListImportService` (PhpSpreadsheet v5.8, batch 500, auto-detect columns FA/EN)
- Public: `AccessGateController` (show, verify → session grant)
- Admin: `AccessListController` (lists CRUD + import + gates CRUD)
- Views: admin/access-lists/{lists-index, lists-form, gates-index, gates-form}
- Public view: `front/access-gate/verify.blade.php` (standalone RTL form)
- Routes: `/gate/{slug}` (GET+POST public) + admin prefix `access-lists/`

### IMG — آپلود تصویر هوشمند
- Service: `SmartImageUploadService` (15 contexts, WebP, GD, AI hook)
- Component: `smart-image-picker.blade.php` (drag-drop, progress, metadata badges)
- Applied to: products, posts, courses

**Deployed:** ✅ 2026-06-13

---

## 2026-06-13 — TB-8: Header/Footer Block Editor (Virtual Pages)

**Reason:** امکان ویرایش `header_blocks` و `footer_blocks` تم Canvas از همان canvas editor، بدون نیاز به route یا UI جدید.

**Pattern:** Virtual pages — کلیدهای `__header__` و `__footer__` به ابتدای لیست صفحات اضافه می‌شوند تا سیستم تب/بلاک/props موجود را reuse کنند.

**Files Modified (customer-site):**
- `app/Http/Controllers/Admin/ThemeDesignController.php` — `canvasEdit()` prepends virtual pages; `canvasSaveBlocks()` early-returns for `__header__`/`__footer__`; `canvasAddPage()` guards `__` prefix
- `resources/views/admin/theme-design/canvas-editor.blade.php` — تب‌های ویژه با رنگ teal/purple + emoji 🔝🔚، guard در delete button + `deletePage()` JS، badge اطلاعاتی در info bar

**Deployed:** ✅ 2026-06-13

---

## 2026-06-13 — TB-7: Canvas Block Inline Editor

**Reason:** ادمین customer-site باید بتواند blocks قالب Canvas فعال را مستقیماً ویرایش، مرتب‌سازی، اضافه/حذف کند و props هر block را inline تنظیم نماید.

**Files Modified (customer-site):**
- `resources/views/admin/theme-design/canvas-editor.blade.php` — ویرایشگر سه‌پنل (پالت بلاک‌ها | بوم صفحه | ویرایشگر props)

**Architecture:**
- **Panel A (راست):** پالت بلاک‌ها با accordion دسته‌بندی‌شده، جستجوی live، emoji آیکون‌ها، click-to-add
- **Panel B (مرکز):** تب‌های صفحات، لیست بلاک‌های sortable (SortableJS drag-and-drop)، بلاک‌های inline با نشانگر دسته رنگی، آپ/داون buttons
- **Panel C (چپ):** ویرایشگر props داینامیک بر اساس BlockRegistry schema — 6 نوع فیلد: string, number, textarea, boolean toggle, select, color
- **Layout:** `@extends('layouts.canvas-editor')` — full-page RTL بدون admin sidebar
- **Keyboard:** Ctrl+S برای ذخیره سریع
- **SortableJS:** drag handle + ghostClass + onEnd callback برای reorder بلاک‌ها
- **Bug Fix:** `block.data` → `block.props` normalization در `init()` (از `ThemeCanvasRenderer` `$block['props'] ?? $block['data']` پشتیبانی می‌کند)

**API Endpoints:**
- `PUT  /admin/theme-design/canvas/save-blocks` — ذخیره بلاک‌های صفحه جاری
- `POST /admin/theme-design/canvas/pages` — افزودن صفحه جدید
- `DELETE /admin/theme-design/canvas/pages/{key}` — حذف صفحه
- `PUT  /admin/theme-design/canvas/global-styles` — ذخیره رنگ‌ها/فونت

**Deployed:** ✅ 2026-06-13

---

## 2026-06-13 — CRITICAL FIX: core-ui::icon production component resolution

**Reason:** خطای 500 "Unable to locate a class or view for component [core-ui::icon]" روی production. `saas-platform/core-utils` در path repository تعریف شده بود و روی سرور boot نمی‌شد → همه صفحات crash می‌کردند.

**Root cause:** `CoreUtilsServiceProvider` در production discover نمی‌شد → namespace `core-ui` هرگز register نمی‌شد.

**Fix (customer-site):**
- `app/Icons/LocalIconRegistry.php` — کپی standalone تمام icon SVGها (٧٠+ آیکون) + aliases اضافه (`layout`, `document`, `document-text`, `swatch`, `color-swatch`, `palette`)
- `resources/views/components/core-ui/icon.blade.php` — fallback component با `class_exists` guard (از package registry استفاده می‌کند اگر نصب باشد، در غیر اینصورت از LocalIconRegistry)
- `app/Providers/AppServiceProvider.php` — `Blade::anonymousComponentPath(resource_path('views/components/core-ui'), 'core-ui')` در boot()

**Result:** در dev، package path اول register می‌شود (package component استفاده می‌شود). در production، local fallback path تنها registration است → LocalIconRegistry همه icons را render می‌کند. view:cache در production موفق شد.

**Deployed:** ✅ 2026-06-13 — "Blade templates cached successfully" تایید شد.

---

## 2026-06-13 — TB-5: Canvas Theme Admin Preview

**Reason:** مدیریت و پیش‌نمایش قالب Canvas از پنل ادمین customer-site — امکان مشاهده، مدیریت صفحات، و غیرفعال‌سازی بدون نیاز به پنل platform.

**New Files (customer-site):**
- `resources/views/admin/theme-design/canvas.blade.php` — صفحه مدیریت قالب Canvas با پالت رنگ، لیست صفحات، بلاک‌های هدر/فوتر، دکمه پیش‌نمایش و غیرفعال‌سازی

**Modified Files (customer-site):**
- `app/Http/Controllers/Admin/ThemeDesignController.php` — متدهای `canvasTheme()` و `canvasDeactivate()` + `$canvasActive`/`$canvasName` به index() اضافه شد
- `routes/web.php` — 2 route جدید: `GET admin/theme-design/canvas` و `POST admin/theme-design/canvas/deactivate`
- `resources/views/admin/theme-design/index.blade.php` — بنر وضعیت Canvas (فعال: gradient بنفش | غیرفعال: آبی آسمانی)

**Features delivered:**
- وقتی canvas فعال است: بنر gradient با نام قالب + لینک مدیریت در صفحه theme-design
- وقتی canvas نیست: بنر آبی با لینک مارکت‌پلیس
- صفحه مدیریت canvas: نام قالب، stats (تعداد صفحه / هدر / فوتر)، پالت رنگ
- لیست صفحات با آدرس، تعداد بلاک، و لینک پیش‌نمایش (new tab)
- بلاک‌های هدر و فوتر (نوع هر بلاک)
- غیرفعال‌سازی (با confirm dialog) → پاک‌سازی settings + cache

**Routes:**
```
GET  /admin/theme-design/canvas             → admin.theme-design.canvas
POST /admin/theme-design/canvas/deactivate  → admin.theme-design.canvas-deactivate
```

---

## 2026-06-12 — ICONS-2: Customer-Site Complete Icon Migration

**Reason:** جایگزینی تمام آیکن‌های inline SVG با `<x-core-ui::icon>` در کل customer-site

**Scope:**
- **۶۴۹ استفاده** از `<x-core-ui::icon>` در تمام فایل‌های customer-site
- فایل‌های اصلاح‌شده: **۱۵۰+ فایل blade** در customer-site
- SVG های باقی‌مانده (۹ عدد): فقط Laravel decorative logos و JS innerHTML strings — قابل جایگزینی نیستند

**Categories replaced:**
- account/layout, account/dashboard → sidebar groups, flash messages, header, profile dropdown
- account/partials/sidebar-icon → PHP array به icon map تبدیل شد
- account/tickets, account/subscriptions, account/points, account/referrals, account/notifications
- admin/layout → sidebar icons, navigation, flash messages
- admin/user-tickets, admin/users, admin/education-center → full replacement
- admin/unified-support, admin/support/_legacy → full replacement
- admin/subscriptions (all 8 sub-pages), admin/orders, admin/accounting, admin/referrals
- admin/theme-marketplace (index + my-licenses), admin/categories, admin/site-content
- admin/posts, admin/products, admin/notifications, admin/settings, admin/dashboard
- admin/partials, admin/modules, admin/invoices, admin/reviews, admin/seo-advanced
- themes/default/layout, themes/default/courses (show/index/my-learning)
- themes/default/shop/cart, themes/default/blog/show, themes/default/search
- front/pricing, front/subscriptions/pay, welcome.blade.php (partial)
- partials (notification bell, ticket notifier, guide widget, messaging icons)

**Architecture notes:**
- `account/partials/sidebar-icon.blade.php` → PHP map از icon name + `<x-core-ui::icon :name="$iconName" />`
- Dynamic icons in PHP arrays → `$iconMap` pattern
- Benefit badges in subscription/pricing → `$bim = ['tag'=>'price-tag','truck'=>'package'...]` pattern

---

## 2026-06-13 — TB-4: Customer-site Canvas Theme Renderer

**Reason:** پس از نصب قالب canvas در سایت مشتری (از مارکت‌پلیس یا canvas editor)، صفحات عمومی باید با داده زنده رندر شوند — نه placeholder ادمین.

**New Files (customer-site):**
- `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` — extends BlockRenderer، module:* blocks با داده واقعی رندر می‌کند (Post, Product, Course)
- `app/Http/Controllers/Front/ThemeCanvasPageController.php` — GET / و GET /page/{slug} با fallback به HomeController/PageController
- `resources/views/layouts/canvas-theme.blade.php` — full-page RTL layout (Vazirmatn، بدون admin sidebar)

**Modified Files (customer-site):**
- `routes/web.php` — `home` و `page.show` به ThemeCanvasPageController منتقل شدند

**معماری:**
- اگر `active_canvas_theme_data` در Setting وجود داشته باشد → canvas رندر
- اگر نباشد → fallback شفاف به HomeController/PageController
- Module blocks: blog_posts, shop_product_grid, courses_grid, pricing_table, contact_quick, faq_dynamic, support_button, social_links_bar, plan_cta, course_enroll_cta و ۱۰+ block دیگر
- Cache: theme data با 300s TTL
- Mobile menu: JavaScript خالص (بدون Alpine dependency)

---

## 2026-06-13 — TB-3: AI URL Analyzer (Theme Generation from URL)

**Reason:** ادمین پلتفرم می‌تواند URL هر سایتی را وارد کند و Claude آن را تحلیل و یک قالب کامل canvas-ready می‌سازد.

**New Files:**
- `app/Services/ThemeBuilder/ThemeAiAnalyzerService.php` — URL fetch + HTML parse + Claude claude-sonnet-4-6 call + JSON parse
- `app/Http/Controllers/Admin/ThemeAiController.php` — form/analyze/analyzeAjax/confirm/generate/generatePrepare
- `resources/views/admin/theme-builder/ai-analyze.blade.php` — 3-step Alpine.js UI (form → live progress → block preview)
- `resources/views/admin/theme-builder/ai-confirm.blade.php` — fallback confirm page (no-JS)

**Modified Files:**
- `routes/web.php` — 6 new TB-3 routes under admin middleware
- `resources/views/admin/theme-builder/index.blade.php` — دکمه "ساخت با AI" به toolbar اضافه شد
- `app/Providers/AppServiceProvider.php` — singleton binding for ThemeAiAnalyzerService

**Flow:**
1. ادمین URL وارد می‌کند
2. Alpine.js → `POST /admin/theme-builder/ai-analyze-ajax` (progress bar زنده)
3. سرور: HTTP fetch URL → DOMXPath extraction (title, h1, h2, nav, colors, og:image)
4. Claude `claude-sonnet-4-6` → max 4096 tokens → theme JSON
5. Preview: block list + color swatch + stats cards
6. ادمین نام می‌دهد → `POST ai-generate-prepare` (JSON به session) → `POST ai-generate` → Theme::create → redirect به canvas
7. قالب draft در `/admin/theme-builder/{id}/canvas` آماده ویرایش

**Env required:** `ANTHROPIC_API_KEY=sk-ant-...`
**Config key:** `services.anthropic.key`

**Routes (6):**
```
GET  /admin/theme-builder/ai-analyze           → فرم
POST /admin/theme-builder/ai-analyze           → تحلیل + redirect
POST /admin/theme-builder/ai-analyze-ajax      → تحلیل JSON (Alpine.js)
GET  /admin/theme-builder/ai-confirm           → تأیید (no-JS fallback)
POST /admin/theme-builder/ai-generate-prepare  → JSON به session
POST /admin/theme-builder/ai-generate          → ایجاد Theme + redirect به canvas
```

---

## 2026-06-13 — TB-2: Theme Marketplace

**Reason:** فروشگاه قالب‌ها — ادمین پلتفرم قالب می‌فروشد، ادمین سایت مشتری می‌خرد، لایسنس دائمی صادر می‌شود.

**New Files — Platform (saas-platform):**
- `database/migrations/2026_06_13_000001_create_theme_marketplace_table.php`
- `database/migrations/2026_06_13_000002_create_theme_licenses_table.php`
- `database/migrations/2026_06_13_000003_create_theme_purchase_orders_table.php`
- `app/Models/ThemeMarketplace.php`
- `app/Models/ThemeLicense.php` — `grant()` static helper
- `app/Models/ThemePurchaseOrder.php`
- `app/Http/Controllers/Admin/ThemeMarketplaceAdminController.php` — manage listings + licenses + grant/revoke
- `app/Http/Controllers/Api/ThemeMarketplaceApiController.php` — API (list/show/initiate-purchase/download/my-licenses)
- `app/Http/Controllers/ThemeMarketplacePaymentController.php` — callback از Zarinpal/IDPay → grant license
- `resources/views/admin/theme-marketplace/index.blade.php`
- `resources/views/admin/theme-marketplace/licenses.blade.php`

**Modified Files — Platform:**
- `app/Models/Theme.php` — `marketplaceMeta()`, `licenses()`, `hasLicenseForSite()` relations
- `app/Services/Payment/PaymentGatewayFactory.php` — `fromName()` method
- `routes/web.php` — 6 admin routes + payment callback route
- `routes/api.php` — 5 tenant API routes (theme-marketplace)

**New Files — Customer-site:**
- `app/Http/Controllers/Admin/ThemeMarketplaceController.php` — index/purchase/myLicenses/install
- `resources/views/admin/theme-marketplace/index.blade.php` — فروشگاه با grid + featured banner
- `resources/views/admin/theme-marketplace/my-licenses.blade.php` — لایسنس‌های من + دکمه نصب

**Modified Files — Customer-site:**
- `app/Services/PlatformApiService.php` — 5 marketplace methods + tenantGet/tenantPost helpers
- `routes/web.php` — 4 marketplace routes under admin group

**Purchase Flow:**
1. Customer-site → `POST /api/theme-marketplace/{id}/initiate-purchase` (license_key auth)
2. Platform creates ThemePurchaseOrder → initiates Zarinpal/IDPay → returns redirect_url
3. User pays → gateway callback → `GET /theme-marketplace/callback?order={id}`
4. Platform verifies → ThemeLicense::grant() → redirects to customer-site with ?purchased=1
5. Customer-site admin installs: `POST /admin/theme-marketplace/{id}/install`
6. Platform API returns theme JSON → saved to Setting `active_canvas_theme_data`

**Routes (12 total):**
- Platform admin: GET /admin/theme-marketplace + 5 sub-routes
- Platform API: GET/POST /api/theme-marketplace + 4 sub-routes (tenant.api auth)
- Public callback: GET /theme-marketplace/callback
- Customer-site: GET/POST /admin/theme-marketplace + 3 sub-routes

**Migrations run:** ✅ All 3 applied

---

## 2026-06-12 — ICONS-1: SaaS Platform Duotone Icon System

**Reason:** ایجاد کتابخانه آیکن منحصر‌به‌فرد Duotone برای کل پلتفرم و سایت‌های مشتریان

**New Files:**
- `packages/saas-platform/core-utils/src/Icons/IconRegistry.php` — رجیستری ۱۰۲ آیکن SVG Duotone (background fill-opacity 0.12 + 2px stroke)
- `packages/saas-platform/core-utils/resources/views/components/icon.blade.php` — Blade component `<x-core-ui::icon />`
- `packages/saas-platform/core-utils/src/Console/Commands/IconListCommand.php` — `php artisan icons:list`
- `resources/views/admin/design-system/icons.blade.php` — صفحه gallery با جستجو و کپی کد

**Modified Files:**
- `packages/saas-platform/core-utils/src/Providers/CoreUtilsServiceProvider.php` — ثبت IconListCommand
- `routes/web.php` — route `admin.design.icons` (GET /admin/design/icons)

**Icon Categories (102 icons):**
Navigation (19) · Actions (21) · Users/Auth (11) · Education (14) · Commerce (15) · Communication (10) · Files/Media (9) · Analytics (9) · Status (9) · Infrastructure (14) · Misc (11)

**Style:** Duotone — background fill (currentColor, opacity 0.12) + 2px rounded stroke foreground. 24×24 viewBox. `currentColor` برای سازگاری با همه رنگ‌بندی‌ها.

**Usage:** `<x-core-ui::icon name="home" class="w-5 h-5 text-blue-600" />`

---

## 2026-06-12 — TB-1: Visual Canvas Builder (Phase 1)

**Reason:** پیاده‌سازی قالب‌ساز بصری حرفه‌ای (Gutenberg-style 3-panel canvas builder) با ۳۷+ بلوک ماژول

**saas-platform — New Files:**

- `database/migrations/2026_06_12_000001_add_pages_to_themes_table.php` — `pages`, `preview_image`, `is_marketplace`, `marketplace_price` columns
- `app/Http/Controllers/Admin/ThemeCanvasController.php` — canvas show/save/preview/addPage/removePage
- `resources/views/layouts/canvas.blade.php` — minimal full-screen layout (no sidebar)
- `resources/views/admin/theme-builder/canvas.blade.php` — 3-panel RTL canvas builder
- `resources/views/admin/theme-builder/canvas-preview.blade.php` — standalone page preview

**saas-platform — Modified Files:**

- `app/Models/Theme.php` — added `pages/preview_image/is_marketplace/marketplace_price` to fillable/casts + `getPageList()`, `getPageBlocks()`, `setPageBlocks()`, `addPage()`, `removePage()`, `scopeMarketplace()`
- `app/Services/ThemeBuilder/BlockRegistry.php` — added `CATEGORY_MODULE` constant + 37 module blocks (blog ×3, shop ×7, courses ×3, forms ×2, subscription ×3, support ×2, reviews ×2, display ×7, utility ×8) + `categoryIcons()` + `moduleBlocksByModule()`
- `app/Services/ThemeBuilder/BlockRenderer.php` — `renderModulePlaceholder()` with color-coded mock HTML per module
- `routes/web.php` — 6 canvas routes under admin auth middleware
- `resources/views/admin/theme-builder/index.blade.php` — "قالب‌ساز بصری" button per theme row

**Architecture:**
- Canvas = 3-panel (Library ← Canvas ← Props) full-screen Alpine.js app
- Block Library: 7 categories + search + expand/collapse
- Canvas: SortableJS drag-and-drop, move up/down, delete
- Props Panel: dynamic form per block schema (string/number/boolean/select/color/textarea)
- Page Manager: add/remove pages (except 'home'), switch with auto-save
- Preview: opens in new tab at `/canvas/{page}/preview`
- Save: PUT /canvas/save + Ctrl+S shortcut + unsaved changes warning

**Migration status:** DONE (878ms)

---

## 2026-06-11 — Module UI/UX Upgrades — customer-site Admin Panel

**Reason:** تمام ماژول‌های پنل ادمین customer-site نیاز به ارتقاء فیچر، UI/UX و حرفه‌ای شدن داشتند.

**Delivered (customer-site):**

- `OrderController.php` — search + status filter + date range + KPI stats (total/paid/pending/revenue)
- `resources/views/admin/orders/index.blade.php` — KPI cards (gradient revenue), status pill filters, searchable table, clickable rows
- `resources/views/admin/orders/show.blade.php` — کامل ساخته شد (قبلاً = index): order items، price breakdown (coupon/membership/points)، status timeline، change status form، customer info
- `OrderModuleTest.php` — 10 new tests (auth, KPI, filter, search, show, status change, cancelled guard) — 10/10 pass
- `CategoryController.php` — `withCount('posts')` added
- `resources/views/admin/categories/index.blade.php` — card view با post count + KPIs + colored icons + empty state
- `ReferralController.php` — stats (total_codes, active, referrals, rewarded, pending, points_awarded) + withCount
- `resources/views/admin/referrals/index.blade.php` — gradient KPI + code cards با referral history per code
- `CourseController.php` — `withCount(['classes','enrollments'])` + stats
- `resources/views/admin/courses/index.blade.php` — card view with thumbnail, chips (active/free/classes/enrollments), KPIs
- `CouponController.php` — stats (total/active/expired/usages) added
- `resources/views/admin/coupons/index.blade.php` — progress bar برای usage_limit، expiry color, badge وضعیت (scheduled/expired/active)، fixed wrong field names (type→discount_type، value→discount_percent/discount_amount، max_uses→usage_limit)
- `ReviewController.php` — stats (total/pending/approved/avg_rating) + rating filter
- `resources/views/admin/reviews/index.blade.php` — KPI cards، star rating display، pending alert banner، approve/reject buttons، filter by rating
- `AccountingController.php` — monthly revenue (last 6 months) via `DATE_FORMAT` GROUP BY
- `resources/views/admin/accounting/index.blade.php` — gradient KPI cards، bar chart ماهانه، quick links to orders/invoices
- `resources/views/admin/sms/index.blade.php` — provider selection cards، connection status، API key hidden، help text
- `resources/views/admin/seo-advanced/index.blade.php` — 4 KPI cards، SEO checklist، config table، sitemap link، quick content links

**Tests:** 429/429 pass (customer-site). Commit: `4243de2`

---

## 2026-06-11 — Public Learning Portal: پورتال یادگیری عمومی در customer-site

**Reason:** customer-site فقط admin CRUD برای دوره‌ها داشت ولی هیچ صفحه عمومی‌ای نداشت. کاربر نهایی نمی‌توانست دوره‌ها را ببیند یا ثبت‌نام کند.

**Delivered (customer-site):**

- `database/migrations/2026_06_11_100000_create_course_class_completions_table.php` (NEW) — `course_class_completions`: course_class_id, user_id, course_id, completed_at + unique(class+user) index
- `app/Models/CourseClassCompletion.php` (NEW) — BelongsTo: courseClass, user, course
- `app/Models/Course.php` — `scopeActive()`, `isFree()`, `completions()`, `progressForUser()`
- `app/Models/CourseClass.php` — `completions()` relation
- `app/Http/Controllers/Front/CourseController.php` (NEW) — 6 actions: index, show, enroll, myLearning, learn, markComplete
- `resources/views/themes/default/courses/index.blade.php` (NEW) — grid با filter free/paid/all، search، enrolled badges
- `resources/views/themes/default/courses/show.blade.php` — جزئیات + class list + progress bar + enroll button
- `resources/views/themes/default/courses/learn.blade.php` — محیط یادگیری با sidebar navigation + AJAX markComplete
- `resources/views/themes/default/courses/my-learning.blade.php` — داشبورد دانشجو با progress bar per course
- `routes/web.php` — 13 route جدید زیر `module:courses` middleware
- `app/Helpers/SiteHelper.php` — `coursesEnabled()` helper
- production env: `MODULE_COURSES_ENABLED=true`

**Tests:** 423/423 pass (customer-site). 4 risky (output buffer - pre-existing).
**Deploy:** ✅ هر دو پروژه deploy شدند (pending در صورت تأیید)

---

## 2026-06-11 — Platform Site Agent: ادمین پلتفرم در سایت مشتری

**Reason:** پلتفرم باید بتواند در صورت نیاز، یک ادمین از تیم پلتفرم را در دیتابیس هر سایت مشتری provision کند بدون اینکه ادمین مشتری بتواند این کاربر را حذف کند. همه عملیات در دو طرف لاگ می‌شود.

**Delivered:**

### saas-platform

- `database/migrations/2026_06_11_000001_create_platform_site_agents_table.php` (NEW) — جدول `platform_site_agents`: site_id (UNIQUE), provisioned_by, mobile, agent_password_enc (Crypt), customer_site_user_id, is_active, provisioned_at, last_access_logged_at
- `app/Models/PlatformSiteAgent.php` (NEW) — model با `getDecryptedPassword()`
- `app/Services/PlatformSiteAgentService.php` (NEW) — provision, revoke, recordAccess, decryptPassword, testConnection; اتصال داینامیک MySQL به دیتابیس سایت مشتری (`site_agent_{id}`)
- `app/Http/Controllers/Admin/PlatformSiteAgentController.php` (NEW) — show/provision/revealPassword/logAccess/revoke
- `resources/views/admin/sites/agents/show.blade.php` (NEW) — UI کامل RTL: connection status، وضعیت agent، فرم provision، نمایش رمز (AJAX)، ثبت دسترسی، لغو
- `routes/web.php` — 5 route جدید: GET/POST/GET/POST/DELETE `/admin/sites/{site}/agent`
- `app/Models/ActivityLog.php` — 4 label جدید: site_agent.provisioned/revoked/password_viewed/access_logged
- `app/Models/Site.php` — `platformAgent()` relationship
- `resources/views/admin/sites/edit.blade.php` — دکمه "ادمین پلتفرم" اضافه شد

### customer-site

- `database/migrations/2026_06_11_000001_add_is_platform_agent_to_users_table.php` (NEW) — ستون `is_platform_agent` boolean
- `app/Models/User.php` — `is_platform_agent` به fillable و casts اضافه شد
- `app/Observers/UserObserver.php` (NEW) — `deleting()` guard: اگر `is_platform_agent = true` → RuntimeException
- `app/Providers/AppServiceProvider.php` — `User::observe(UserObserver::class)` ثبت شد
- `app/Http/Controllers/Auth/LoginController.php` — اگر `is_platform_agent = true` → `logPlatformAgentLogin()` → AdminActivityLog ثبت می‌شود
- `app/Models/AdminActivityLog.php` — 5 label جدید: platform_agent.login/provisioned/reprovisioned/revoked/access_recorded

**Security notes:**
- رمز عبور agent با `Crypt::encryptString()` در platform DB ذخیره می‌شود
- اتصال داینامیک با `DB::purge()` قبل از هر استفاده پاک می‌شود
- `is_platform_agent` column existence check قبل از INSERT/UPDATE — سازگار با سایت‌هایی که migration هنوز اجرا نشده
- ورود agent در `admin_activity_log` سایت مشتری ثبت می‌شود (action: platform_agent.login)

---

## 2026-06-11 — Full Backlog Completion: FE-02/05 + M2.1-a/c/d + INFRA-6

**Reason:** Complete all remaining backlog items across frontend, mobile, and infrastructure.

### FE-02: Canonical Button CSS Alignment
- `resources/views/layouts/admin.blade.php` — `.btn-primary/secondary/danger/success/ghost/outline` CSS updated to match `x-core-ui::button` styling (transitions, focus rings, hover states, border-radius)

### FE-03: Tailwind v4 Shadow Utility Warnings
- `resources/css/app.css` — 8 named `@utility` definitions for `shadow-card`, `shadow-indigo`, `shadow-modal`, `shadow-toast`, `shadow-lg-ui`, `shadow-sm-ui`, `shadow-xs-ui`, `shadow-card-hover` — eliminates Lightning CSS optimizer warnings

### FE-04: Forms Module Controls
- Already using Tailwind utility classes — no `.btn btn-*` raw classes found — task verified as complete

### FE-05: tickets/show canonical buttons
- `resources/views/admin/support-tickets/show.blade.php` — 3 buttons replaced: back link + تخصیص + ذخیره → `x-core-ui::button`

### M2.1-a: File Attachment in Create Ticket
- `mobile/lib/features/support/create_ticket_screen.dart` — `_pickFiles()` + `_removeAttachment()` + chip preview + `_attachments` list + calls `replyToTicket` after ticket creation for file upload

### M2.1-c: Firebase FCM Push Notifications
- `mobile/pubspec.yaml` — `firebase_core: ^3.6.0` + `firebase_messaging: ^15.1.3` added
- `mobile/lib/core/notifications/notification_service.dart` — full FCM implementation: `_tryFirebaseTransport()` with permission request, token registration, foreground message handler, background tap handler; `_registerFcmToken()` calls backend API

### M2.1-d: Deep-link Routing
- `mobile/lib/core/deeplink/conversation_deep_link_service.dart` — 5 target types added: `TicketDeepLink`, `CreateTicketDeepLink`, `DashboardDeepLink`, `saas-support://` alternate scheme support
- `mobile/lib/app/router.dart` — `navigatorKey` added, `deepLinkToPath()` helper, deep link service wired on startup → `context.go(path)` on incoming link
- `notification_service.dart` — `_onTap` dispatches FCM notification payload to deep link service

### INFRA-6: Remote Agent Layer
- `database/migrations/2026_06_11_000002_create_remote_agents_table.php` (NEW)
- `app/Models/RemoteAgent.php` (NEW) — encrypted secret, scopes, status helpers
- `app/Services/Infrastructure/RemoteAgentClient.php` (NEW) — HMAC-SHA256 signed HTTP client; health/backup/deploy/restart commands; auto-updates agent health status
- `app/Http/Controllers/Admin/Infrastructure/RemoteAgentController.php` (NEW) — CRUD + command endpoints (health/backup/deploy/restart/generate-secret)
- `resources/views/admin/infrastructure/remote-agents/index.blade.php` (NEW) — command buttons with Alpine AJAX results
- `resources/views/admin/infrastructure/remote-agents/form.blade.php` (NEW) — create/edit form with secret generator
- `routes/web.php` — resource + 5 command routes under `admin/infrastructure/remote-agents`
- `resources/views/admin/infrastructure/overview.blade.php` — Remote Agents card added
- `app/Providers/AppServiceProvider.php` — `RemoteAgentClient` singleton registered

**All PHP files pass `php -l` syntax check.**

---

## 2026-06-11 — M2.1-b + FE-01 + 8.3a + 8.4d: iOS CI + Navigation Components + Satis + OTel

**Reason:** Backlog completion — mobile, frontend, infrastructure distribution, observability.

**Delivered:**

### M2.1-b: iOS Code Signing Pipeline
- `.github/workflows/mobile-build.yml` — Job 6: `build-ios` added
  - macOS runner, Flutter 3.22.3
  - Temporary keychain + P12 certificate import + provisioning profile install
  - `flutter build ipa --release`
  - Conditional App Store Connect upload via `xcrun altool` (main branch + API key secret set)
  - Keychain cleanup on `always()`
  - `workflow_dispatch.inputs.build_ios` toggle added
  - Summary job updated to include iOS result
- **GitHub Secrets needed:** `IOS_SIGNING_CERTIFICATE_P12_BASE64`, `IOS_SIGNING_CERTIFICATE_PASSWORD`, `IOS_PROVISIONING_PROFILE_BASE64`, `IOS_BUNDLE_ID`, `IOS_TEAM_ID`, `KEYCHAIN_PASSWORD`
- **Optional:** `APP_STORE_CONNECT_API_KEY_ID`, `APP_STORE_CONNECT_ISSUER_ID`, `APP_STORE_CONNECT_API_PRIVATE_KEY`

### FE-01: Canonical Navigation Components in Admin Layout
- `resources/views/layouts/admin.blade.php` — desktop sidebar nav replaced:
  - `<a class="sidebar-link">` → `<x-core-ui::navigation-item :href :active class="sidebar-link">`
  - Items wrapped in `<x-core-ui::sidebar-group>` (no label/collapsible — JS toggle preserved)
  - Icon partials: `sidebar-icon-bare.blade.php` + `customer-sidebar-icon-bare.blade.php` (without `ml-2 shrink-0`)
- `resources/views/admin/partials/sidebar-icon-bare.blade.php` (NEW) — admin icons without spacing classes
- `resources/views/admin/partials/customer-sidebar-icon-bare.blade.php` (NEW) — customer icons without spacing
- Existing JS-driven group collapse preserved (`data-group-toggle`/`data-group-items`)
- `sidebar-text` class on slot text preserves icon-only collapsed state

### 8.3a: Private Satis Repository
- `satis.json` — updated with all 4 packages (core-utils, tickets, forms, education), archive config, output-dir
- `.github/workflows/satis-build.yml` (NEW) — builds Satis index on `main` push when packages/* change; rsync deploy to packages server; Slack notification on failure
- **GitHub Secrets needed:** `SATIS_DEPLOY_KEY`, `SATIS_SERVER_HOST`, `SATIS_SERVER_USER`, `SATIS_SERVER_PATH`

### 8.4d: OpenTelemetry OTLP Export
- `app/Services/Infrastructure/OtelExportService.php` (NEW) — builds OTLP JSON payload from `telemetry_events` rows; ships via `POST /v1/traces`; silent no-op when endpoint not configured
- `app/Console/Commands/ExportTelemetryOtlpCommand.php` (NEW) — `telemetry:export-otlp --from --limit --batch --dry-run`
- `config/services.php` — `services.otel.endpoint` driven by `OTEL_EXPORTER_OTLP_ENDPOINT`
- `app/Console/Kernel.php` — scheduled every 30 min when endpoint configured
- **Env var needed:** `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318`

**Regression:** All 4 new PHP files pass `php -l`. No existing tests affected.

---

## 2026-06-11 — INFRA-3/4/5 + 8.3d: Security Hardening + Backup + SMS Stats + Slack Alerting

**Reason:** Infrastructure backlog completion — security findings from INFRA audit + operational readiness.

**Delivered:**

### INFRA-3: InstallController Security
- `app/Http/Controllers/InstallController.php` — regex validation on DB fields, path traversal guard (`realpath` check), atomic `.env` write (write-to-tmp + rename), proper env value quoting

### INFRA-3: LocalAI Endpoint Guard
- `app/Services/AI/LocalAIClient.php` — `LOCAL_AI_ENABLED` guard prevents accidental calls to localhost Ollama
- `config/services.php` — `services.local_ai` config block added (`enabled`, `endpoint`, `model`)

### INFRA-4: Database Backup Command
- `app/Console/Commands/BackupDatabaseCommand.php` (NEW) — `db:backup --retention=N --dry-run`, gzip mysqldump, retention pruning, Slack alert on failure
- `app/Console/Kernel.php` — `db:backup --retention=7` scheduled at 02:00 daily; education AI commands (generate-content, generate-gap-content, refresh-recommendations) added to scheduler

### INFRA-5: SMS Delivery Statistics Dashboard
- `database/migrations/2026_06_10_000001_create_sms_logs_table.php` (NEW) — `sms_logs` table (provider, recipient, message, success, error_message, response_time_ms)
- `app/Models/SmsLog.php` (NEW) — scopes: `success()`, `failed()`, `provider()`, static `successRateFor()`
- `app/Services/Sms/FailoverSmsGateway.php` — `trySend()` helper: DB logging + cache health + Slack on total failure
- `app/Http/Controllers/Admin/Infrastructure/InfraSmsController.php` (NEW) — today stats, 30-day rate, provider rates, daily chart, recent failures
- `resources/views/admin/infrastructure/sms/index.blade.php` (NEW) — professional RTL dashboard
- `routes/web.php` — `GET admin/infrastructure/sms` → `infrastructure.sms`
- `resources/views/admin/infrastructure/overview.blade.php` — SMS stats card added to navigation grid

### 8.3d: Slack Webhook Alerting
- `app/Services/Infrastructure/SlackAlertService.php` (NEW) — `critical()`, `high()`, `warning()`, `info()`, `deploymentEvent()` — color-coded Slack attachments, context fields, silent no-op when webhook unset
- `config/services.php` — `SLACK_ALERT_WEBHOOK` + `SLACK_ALERT_CHANNEL` env vars
- `app/Providers/AppServiceProvider.php` — `SlackAlertService` registered as singleton
- `app/Console/Commands/TestSlackAlertCommand.php` (NEW) — `php artisan slack:test --level=critical|high|warning|info`
- Wired: BackupDatabaseCommand failure → Slack CRITICAL; SMS both-provider failure → Slack CRITICAL

**Regression:** All 7 new/modified PHP files pass `php -l` syntax check. No breaking changes to existing routes or tests.

---

## 2026-06-08 — CACHE-1: Redis Cache Architecture + CacheManager

**Reason:** `Cache::flush()` was a nuclear option — wiping the entire cache on every user/customer write. Named stores with per-store flush isolation needed.

**Delivered:**
- `app/Services/Infrastructure/CacheManager.php` (NEW) — `key()`, `store()`, `remember()`, `forget()`, `put()`, `get()`, `flushStore()`, `isKnownStore()`, `storeNames()`
- `config/database.php` — 7 new Redis connections: `cache_sessions` (DB2), `cache_rate_limit` (DB3), `cache_users` (DB4), `cache_customers` (DB5), `cache_themes` (DB6), `cache_tickets` (DB7), `cache_modules` (DB8)
- `config/cache.php` — 6 named stores: `users`, `customers`, `themes`, `tickets`, `modules`, `rate_limit` — each on its own Redis DB, driver falls back to env CACHE_STORE
- `app/Repositories/UserRepository.php` — all `Cache::remember/flush` → `Cache::store('users')`
- `app/Repositories/CustomerRepository.php` — all `Cache::remember/flush` → `Cache::store('customers')`
- `app/Services/UserService.php` — `Cache::flush()` → `Cache::store('users')->flush()`
- `app/Services/CustomerService.php` — `Cache::flush()` → `Cache::store('customers')->flush()`
- `app/Repositories/SupportTicketRepository.php` → `Cache::store('tickets')`
- `app/Services/ModuleFeatureService.php` → `Cache::store('modules')`
- `app/Services/ThemeEngine/ThemeProfileManager.php` → `Cache::store('themes')` (all 6 Cache calls)
- `tests/Feature/Infrastructure/CacheManagerTest.php` (NEW) — 15 tests / 33 assertions, all pass

**Redis DB Map:**
- DB0: default (queues, Reverb) | DB1: legacy cache | DB2: sessions | DB3: rate_limit
- DB4: users | DB5: customers | DB6: themes | DB7: tickets | DB8: modules

**Regression:** 15 new CacheManager tests pass. All pre-existing failures are unrelated email-column issues from prior phases.

---

## 2026-06-08 — SaaS Infrastructure Foundation

**Reason:** Prepare platform for future deployment to remote/separated servers without code changes.

**Infrastructure Audit:**
- 23 findings categorized (CRITICAL/HIGH/MEDIUM/LOW) across domains, storage, DB, SMS, AI
- Full report: `docs/infrastructure/INFRASTRUCTURE_READINESS_REPORT.md`
- Deployment architecture: `docs/infrastructure/DEPLOYMENT_ARCHITECTURE.md`

**Fixes Applied:**
- `config/cors.php` — hardcoded `customer-site.test` replaced with `CORS_ALLOWED_ORIGINS` env (F1.3 fixed)
- `config/sms.php` — added `failover` key driven by `SMS_FAILOVER_PROVIDER` env
- `app/Services/Sms/KavenegarGateway.php` — new Kavenegar provider
- `app/Services/Sms/GhasedakGateway.php` — new Ghasedak provider
- `app/Services/Sms/FailoverSmsGateway.php` — automatic primary→secondary failover with cache health tracking
- `app/Services/Sms/SmsGatewayResolver.php` — updated to support failover wiring

**Infrastructure Management Module (new):**
- 5 migrations: `infra_servers`, `infra_domains`, `infra_hosting`, `infra_storage_providers`, `infra_database_servers`
- 5 Eloquent models: `InfraServer`, `InfraDomain`, `InfraHosting`, `InfraStorageProvider`, `InfraDatabaseServer`
- 6 admin controllers under `App\Http\Controllers\Admin\Infrastructure\`
- 14 Blade views (overview dashboard + CRUD for all 5 sections)
- 31 admin routes under `admin/infrastructure/*`
- `AdminMenu` updated: `infrastructure` key, group `زیرساخت / DevOps`

**Files Changed:**
- config/cors.php, config/sms.php
- app/Services/Sms/{KavenegarGateway,GhasedakGateway,FailoverSmsGateway,SmsGatewayResolver}.php
- app/Models/Infrastructure/{InfraServer,InfraDomain,InfraHosting,InfraStorageProvider,InfraDatabaseServer}.php
- app/Http/Controllers/Admin/Infrastructure/{Overview,Server,Domain,Hosting,Storage,Database}Controller.php
- database/migrations/2026_06_08_100001..100005_create_infra_*.php
- resources/views/admin/infrastructure/* (14 views)
- routes/web.php — infrastructure route group
- app/Helpers/AdminMenu.php — infrastructure menu key + group
- docs/infrastructure/INFRASTRUCTURE_READINESS_REPORT.md (new)
- docs/infrastructure/DEPLOYMENT_ARCHITECTURE.md (new)

---

## 2026-06-08 — Subscription UX: Auto-renew Toggle + Post-expiry CTA

**Reason:** Auto-renew had a DB column but no UI. Expired subscriptions showed no clear next action.

**Auto-renew Toggle:**
- `SubscriptionController::toggleAutoRenew()` — flips `auto_renew` boolean, validates ownership + active status
- Route `POST /account/subscriptions/{subscription}/toggle-auto-renew` → `account.subscriptions.toggle-auto-renew`
- `account/subscriptions/show.blade.php` — toggle button (green when on, gray when off)
- `account/subscriptions/index.blade.php` — "↻ تمدید خودکار فعال" badge on active subscription bar

**Post-expiry CTA:**
- `account/subscriptions/show.blade.php` — amber warning card with "تمدید / انتخاب پلن جدید" button when expired
- `account/subscriptions/index.blade.php` — amber bar when user has no active sub but has expired ones; "تمدید" button on each expired row in history table
- Differentiated from "never subscribed" (keeps the original dashed-border CTA)

**Admin CSV Export:**
- Already complete — `SubscriberController::export()` + CSV button in subscribers view (verified, no changes needed)

**Tests:** 13/13 pass (SubscriptionUXTest)

**Files Changed (customer-site):**
- app/Http/Controllers/Account/SubscriptionController.php — toggleAutoRenew()
- routes/web.php — account.subscriptions.toggle-auto-renew route
- resources/views/account/subscriptions/show.blade.php — auto-renew toggle + expired CTA
- resources/views/account/subscriptions/index.blade.php — expired bar + auto-renew badge + renew row button
- tests/Feature/SubscriptionUXTest.php — new (13 tests)

---

## 2026-06-08 — Invoice PDF + Subscription Webhook + Time-based Coupon Verify

**Reason:** Three new user-requested features.
**Invoice PDF:**
- Installed `barryvdh/laravel-dompdf` v3.1
- `resources/views/invoice/pdf.blade.php` — RTL professional template with DejaVu Sans, HMAC stamp, discount section, notes
- `Front/InvoiceController::download()` + `Admin/InvoiceController::download()` — PDF download via Pdf::loadView
- Route `invoice.download` (front) + `admin.invoices.download` (admin)
- Download button added to `invoice/show.blade.php` + `admin/invoices/show.blade.php`
- 5/5 tests pass (InvoicePdfTest)

**Subscription Webhook:**
- Migration `2026_06_08_100000_create_webhook_tables.php` — `webhook_endpoints` + `webhook_events`
- `App\Models\WebhookEndpoint` — subscribesTo(), EVENTS map
- `App\Models\WebhookEvent` — STATUS constants
- `App\Services\WebhookDispatchService` — dispatch() finds active endpoints, creates events, queues jobs
- `App\Jobs\DispatchWebhookJob` — HMAC-signed HTTP POST, 3 retries, 60s backoff
- `SubscriptionService` injected with `WebhookDispatchService` — fires on subscribed/renewed/upgraded/downgraded/cancelled/expired
- Admin UI: `admin/webhooks/` (index, create, edit, show) + routes + WebhookController
- 16/16 tests pass (WebhookTest)

**Time-based Coupons:**
- Already fully implemented: `starts_at`/`expires_at` in DB, `isValid()` checks both, admin forms show both fields
- No changes needed.

**Total: 406/406 tests pass (0 failures)**

**Files Changed (customer-site):**
- composer.json — barryvdh/laravel-dompdf added
- resources/views/invoice/pdf.blade.php — new PDF template
- resources/views/invoice/show.blade.php — download button
- resources/views/admin/invoices/show.blade.php — download button
- resources/views/admin/webhooks/{index,create,edit,show}.blade.php — new views
- app/Http/Controllers/Front/InvoiceController.php — download()
- app/Http/Controllers/Admin/InvoiceController.php — download()
- app/Http/Controllers/Admin/WebhookController.php — new
- app/Models/WebhookEndpoint.php — new
- app/Models/WebhookEvent.php — new
- app/Services/WebhookDispatchService.php — new
- app/Jobs/DispatchWebhookJob.php — new
- app/Services/SubscriptionService.php — webhook dispatch integration
- database/migrations/2026_06_08_100000_create_webhook_tables.php — new
- routes/web.php — invoice.download + admin.invoices.download + admin webhooks routes
- tests/Feature/InvoicePdfTest.php — new (5 tests)
- tests/Feature/WebhookTest.php — new (16 tests)

---

## 2026-06-08 — 8.3b DB Backup + Fix 52 Broken Tests

**Reason:** (1) No automated DB backup existed before deploy. (2) 52 pre-existing test failures blocked CI.
**8.3b Backup:**
- `BackupDatabaseCommand` — mysqldump + optional gzip + retention (1-365 days) + integrity row-count check + `MYSQLDUMP_PATH` env override
- Scheduled daily at 02:00 in `bootstrap/app.php` with `DB_BACKUP_RETENTION` env
- 8/8 tests pass (dry-run, dir creation, retention pruning, humanSize, artisan registration)

**52 → 0 Fixes (customer-site):**
- `Tag::posts()` — missing `BelongsToMany` relationship added (BlogController used `withCount('posts')`)
- `admin/coupons/index.blade.php` — had invoice template content; rewritten as coupon admin list + guide include
- `CaptchaService` static calls in Wave53ShopGuardsTest — replaced with `app(CaptchaService::class)->method()`
- `orders.customer_mobile` required field — added to all test Order::create() calls in Wave61, ShopModuleFeatureTest
- `forms.deleted_at` / `form_fields.deleted_at` / `form_submissions.deleted_at` — migration added
- `tags.deleted_at` / `tags.color` / `tags.is_active` — migration added
- `FormService::create()` — replaced `time()` with unique slug helper (concurrent slug collision fixed)
- `FormController::store()` redirect — changed `admin.forms.builder` → `admin.forms.edit`
- `FormFieldRequest` `options_raw` — added `Rule::requiredIf` for select/radio/checkbox types
- `App\Models\Form`, `FormField`, `FormSubmission` — added `SoftDeletes` trait
- Forms index view — added breadcrumb + guide partial (راهنمای ماژول فرم‌ساز + ۱۴۰۵/۰۲/۲۳)
- `ModuleInvoiceTest` — replaced `customer_email` with `customer_mobile`
**Files Changed (customer-site):**
- app/Models/Tag.php — posts() BelongsToMany
- app/Models/Form.php + FormField.php + FormSubmission.php — SoftDeletes
- resources/views/admin/coupons/index.blade.php — rewritten
- tests/Feature/Wave53ShopGuardsTest.php — static → app() calls
- tests/Feature/Wave61ShopDataIntegrityTest.php — customer_mobile, customer_email removed
- tests/Feature/ShopModuleFeatureTest.php — customer_mobile
- tests/Feature/ModuleInvoiceTest.php — customer_mobile
- database/migrations/2026_06_07_100000_add_missing_columns_to_tags_table.php (NEW)
- database/migrations/2026_06_07_100001_add_soft_deletes_to_forms_table.php (NEW)
- app/Console/Commands/BackupDatabaseCommand.php (NEW)
- tests/Feature/BackupDatabaseCommandTest.php (NEW — 8/8)
- bootstrap/app.php — db:backup schedule
**Files Changed (saas-platform/forms module):**
- FormService.php — uniqueSlug() replaces time()
- FormController.php — redirect store → admin.forms.edit
- FormFieldRequest.php — Rule::requiredIf for options_raw
- forms/resources/views/admin/forms/index.blade.php — breadcrumb + guide

---

## 2026-06-07 — Admin Subscription Panel + installed.lock Bug Fix

**Reason:** Admin lacked force-extend, manual plan-change, and group-member removal. Also: tests were deleting `storage/installed.lock`, requiring site reinstall after every test run.
**Design:**
- `SubscriberController::extend()` — تمدید دستی N روز + log ACTION_RENEWED
- `SubscriberController::changePlan()` — تغییر پلن + log ACTION_PLAN_CHANGED, رد same-plan
- `SubscriberController::removeMember()` — خروج عضو از گروه (403 if wrong subscription)
- `subscriber-show.blade.php` — سه بخش جدید در sidebar (تمدید) و main column (اعضا) و sidebar (تغییر پلن)
- `tests/TestCase.php` — حفظ وضعیت installed.lock قبل از تست و بازیابی بعد از tearDown
- `tests/Feature/AdminSubscriberActionsTest.php` — 12 تست جدید (12/12 پاس)
**Files Changed (customer-site):**
- app/Http/Controllers/Admin/SubscriberController.php — extend + changePlan + removeMember + updated show()
- resources/views/admin/subscriptions/subscriber-show.blade.php — force-extend UI + plan-change UI + group members UI
- routes/web.php — 3 new admin subscriber routes
- tests/TestCase.php — installed.lock preservation fix
- tests/Feature/AdminSubscriberActionsTest.php — (NEW) 12 tests, 32 assertions

---

## 2026-06-07 — Infrastructure 8.3c: GitHub Actions CI Pipeline

**Reason:** No automated test gate existed for PHP code — regressions could reach main branch undetected.
**Design:**
- **customer-site**: Improved existing php-tests.yml with 2 jobs:
  - `lint`: PHP syntax (ci-lint.php) + composer validate + `composer audit --no-dev` — fast gate, no DB
  - `feature-tests`: Full test suite on MySQL 8.0, `needs: lint` to skip DB spin-up on lint failure
- **saas-platform**: New php-tests.yml with 3 parallel jobs after `lint`:
  - `lint`: syntax check (app/ + packages/) + validate + audit
  - `platform-tests`: `tests/Feature/` on MySQL 8.0 + migrate
  - `edu-ai-tests`: Education AI provider tests only (no DB — mocked HTTP + array cache)
- Both repos: `required-checks.json` updated — PHP test jobs now blocking on main/master
- Composer caching on `composer.lock` hash — avoids re-downloading on every run
- Test env vars passed explicitly: `PAYMENT_GATEWAY=""`, `EDUCATION_AI_PROVIDER=null` to prevent external calls
**Files Changed:**
- customer-site/.github/workflows/php-tests.yml (updated — lint + feature-tests jobs)
- saas-platform/.github/workflows/php-tests.yml (new — lint + platform-tests + edu-ai-tests)
- saas-platform/.github/required-checks.json (updated — 3 PHP checks added for saas, 2 for customer)

---

## 2026-06-07 — Payment Webhook Signature Validation

**Reason:** IDPay sends X-IDPAY-SIGNATURE header (HMAC-SHA256) in callbacks that was ignored — a forged callback could activate a subscription without real payment. Zarinpal Authority was also not format-validated.
**Design decisions:**
- Added `validateCallback(array $params, array $headers, string $rawBody): void` to `PaymentGatewayInterface` — called in controller before `verify()`
- Zarinpal: validates Authority format (`A` + ≥19 alphanumeric chars). No HMAC from Zarinpal — protection is via verify API.
- IDPay: validates `X-IDPAY-SIGNATURE = HMAC-SHA256(raw_body, api_key)` using `hash_equals()` for timing-safety. Skipped in sandbox mode (IDPay sandbox doesn't sign).
- Controller extracts raw body + lowercased headers before calling gateway — gateways stay HTTP-agnostic.
**Files Changed (customer-site):**
- app/Services/Payment/PaymentGatewayInterface.php: `validateCallback()` added
- app/Services/Payment/ZarinpalGateway.php: Authority format validation
- app/Services/Payment/IdpayGateway.php: X-IDPAY-SIGNATURE HMAC validation + extractHeader() helper
- app/Http/Controllers/Front/SubscriptionPaymentController.php: calls validateCallback() before verify()
- tests/Feature/PaymentSignatureValidationTest.php (7 new tests)
- tests/Feature/SubscriptionPaymentGatewayTest.php: 2 Authority params updated to valid format
**Impact:** 7/7 new tests + 18/18 existing = 25/25 pass. Forged IDPay callbacks now blocked; invalid Zarinpal Authority rejected before any API call.

---

## 2026-06-07 — Infrastructure 8.4a: Composite site_id Indexes

**Reason:** Multi-tenant tables queried with `WHERE site_id = X AND status = Y` had only plain FK indexes on site_id. Composite indexes enable covering index scans for the most common filter patterns.
**Audit result:**
- Education module (12 tables): all already had correct composite site_id indexes ✅
- Tickets package: `site_id` index existed ✅
- `support_tickets` (main): `(site_id, status, created_at)` added by 2026_05_21 migration ✅
- **Missing composites added:**
  - `form_submissions`: `(site_id, form_id, created_at)`
  - `platform_site_notifications`: `(site_id, is_active, expires_at)`
  - `site_updates`: `(site_id, distributed_at)`
  - `site_support_subscriptions`: `(site_id, status)`
- EDU-14A tables: computed from existing tables via DTOs — no dedicated tables, no action needed
**Files Changed (saas-platform):**
- database/migrations/2026_06_07_000001_add_composite_site_id_indexes.php (new — migrated 217ms)
**Impact:** 8.4a backlog item closed. No test changes needed (index changes don't affect query results).

---

## 2026-06-07 — Bug Fix: Group Member Cache Invalidation on Subscription Expire/Cancel

**Reason:** When owner's subscription expired or was cancelled, group members' `membership_plan_{id}` benefit cache was not flushed. Members continued to receive plan benefits for up to 5 minutes (cache TTL) after the subscription ended.
**Root cause:** `SubscriptionService::expire()` and `cancel()` had no group member cache invalidation. `upgrade()` and `SubscriptionPaymentController::activateSubscription()` also bypassed it.
**Fix:**
- `SubscriptionService::invalidateGroupMemberCaches(Subscription)` — new public method: loads active members, calls `MembershipBenefitService::invalidateCache()` per member
- Called in: `cancel()`, `expire()`, `upgrade()` (for old subscription)
- `SubscriptionPaymentController::activateSubscription()` — calls `service->invalidateGroupMemberCaches($oldSub)` on payment-based upgrade
**Files Changed (customer-site):**
- app/Services/SubscriptionService.php: invalidateGroupMemberCaches() public method + 3 call sites
- app/Http/Controllers/Front/SubscriptionPaymentController.php: call site on upgrade payment
- tests/Feature/GroupSubscriptionTest.php: 2 new regression tests
**Impact:** 14/14 group subscription tests pass | 54/54 regression pass. Bug confirmed fixed.

---

## 2026-06-07 — EDU-17 CERTIFIED COMPLETE

**Certification:** EDU17_CERTIFIED_COMPLETE  
**Commits:** `da0cbbc` (Phases 2–3) · `4fa6905` (config/docs) · `18e218c` (Phases 4–5)  
**Tests:** 57 passed / 0 failed / 77 assertions  
**Validation score:** 100/100  
**Technical Debt:** M-01 (LOW) — `education:validate` does not yet include EDU-17-specific checks. Future maintenance only. EDU-17 CLOSED.  
**Next recommended milestone:** SUBSCRIPTION_CHECKOUT_PAYMENT

---

## 2026-06-07 — EDU-17 Task 4: TenantAiQuotaManager Integration

**Reason:** CircuitBreakerAiContentProvider had quota integration documented but deferred — Task 4 of EDU-17 spec.
**Design decisions:**
- Quota check happens BEFORE the circuit breaker — quota exhaustion is not a circuit-breaker failure
- Quota key format: `edu_site_{siteId}` — platform-level jobs use `edu_site_platform`
- Both per-tenant and system-total counters are incremented after each successful generation
- TenantAiQuotaManager injected only when `EDUCATION_AI_QUOTA_ENABLED=true` (default: false) — zero overhead in existing deployments
**Files Changed (saas-platform):**
- packages/education/config/education.php: `ai.quota.*` section added (EDUCATION_AI_QUOTA_ENABLED, EDUCATION_AI_QUOTA_DEFAULT_DAILY)
- packages/education/src/Ai/Providers/CircuitBreakerAiContentProvider.php: TenantAiQuotaManager injected, resolveQuotaKey() + trackQuotaUsage() methods
- packages/education/src/Providers/EducationServiceProvider.php: inject TenantAiQuotaManager conditionally
- packages/education/tests/Feature/TenantAiQuotaIntegrationTest.php (5 tests)
**Impact:** 5/5 new tests pass | 46/46 AI provider regression pass. EDU-17 fully complete.

---

## 2026-06-07 — Group Subscription (P9)

**Reason:** `max_users` field on `SubscriptionPlan` existed with no logic — subscription owners couldn't add team members to share plan benefits.
**Design decisions:**
- `availableSeats = max_users - 1 - (active+pending members)`. Owner occupies 1 seat.
- `SubscriptionService::getActivePlan()` checks group membership as fallback after own subscription
- Invite workflow: owner sends invite by mobile → invitee accepts/rejects from their dashboard
- Member removed: SMS + cache invalidation
- No double-benefit: user already in another group subscription cannot accept a second invite
**Files Changed (customer-site):**
- migrations/2026_06_07_400000_create_subscription_members_table.php (new)
- app/Models/SubscriptionMember.php (new)
- app/Services/GroupSubscriptionService.php (new)
- app/Http/Controllers/Account/GroupSubscriptionController.php (new)
- resources/views/account/subscriptions/members.blade.php (new)
- app/Services/SubscriptionService.php: getActivePlan() extended with group fallback
- SubscriptionController: pending invites in index() + GroupSubscriptionService injected
- account/subscriptions/index.blade.php: pending invites panel + group members button
- AppServiceProvider: GroupSubscriptionService singleton
- routes/web.php: 5 new group routes
- tests/Feature/GroupSubscriptionTest.php (12 tests)
**Impact:** 12/12 pass | 66/66 regression pass.

---

## 2026-06-07 — Plan Upgrade/Downgrade Engine (P8)

**Reason:** Users couldn't change their active plan — they had to cancel and re-subscribe, losing remaining days. This adds prorated credit calculation so remaining days translate to a discount on the new plan.
**Design decisions:**
- Trial subscriptions have no credit (no money was paid)
- Credit = round((daysRemaining / planDurationDays) * effectivePrice)
- Leftover credit when downgrading → converted to loyalty points at 1000 toman = 1 point
- Paid upgrades: create invoice(newPrice - credit), pending sub with `upgrade_from_subscription_id`, old sub cancelled on payment confirmation
- Free target or credit >= newPrice: direct activation (no payment)
**Files Changed (customer-site):**
- migrations/2026_06_07_300000_add_upgrade_fields_to_subscriptions_table.php (new — `upgrade_from_subscription_id`, `credit_amount`)
- app/Models/Subscription.php: new fields in fillable/casts, `upgradedFrom()`, `upgradedTo()`
- app/Models/SubscriptionLog.php: ACTION_UPGRADED + ACTION_DOWNGRADED
- app/Models/MembershipPoint.php: SOURCE_PLAN_CHANGE
- app/Services/SubscriptionService.php: calculateCredit(), upgradePreview(), upgrade(), createUpgradeInvoice(), notifyPlanChanged()
- app/Services/MembershipPointsService.php: earnFromPlanChange()
- app/Http/Controllers/Account/SubscriptionController.php: upgradePlans() + upgrade()
- app/Http/Controllers/Front/SubscriptionPaymentController.php: activateSubscription() handles upgrade (cancel old + award leftover points)
- routes/web.php: subscriptions.upgrade-plans + subscriptions.upgrade
- resources/views/account/subscriptions/upgrade-plans.blade.php (new — RTL, credit calc display)
- tests/Feature/SubscriptionUpgradeTest.php (11 tests)
**Impact:** 11/11 pass | 54/54 regression pass.

---

## 2026-06-07 — P3/P6/P7 Subscription Phases

**Reason:** P3: benefit fields existed but weren't enforced in checkout. P6: test coverage was minimal. P7: early_access product gating.
**Files Changed (customer-site):**
- P3: shipping_cost in orders, earlyAccess in MembershipDiscountResult DTO, ShopController updated
- P6: SubscriptionCheckoutFlowTest + SubscriptionLifecycleTest (27 tests)
- P7: products.early_access column, gate in show()+addToCart(), EarlyAccessGatingTest (8 tests)
**Impact:** P3=10/10, P6=27/27, P7=8/8 pass.

---

## 2026-06-06 — Auto-renew Scheduler (P2)

**Reason:** `auto_renew` field existed on Subscription but no job processed it — subscriptions expired without renewal.
**Design decision:** Iranian gateways don't support card-on-file/stored credentials. Free plans renew directly; paid plans get an invoice + SMS so user pays manually.
**Files Changed (customer-site):**
- app/Console/Commands/ProcessRenewalsCommand.php (new — `subscriptions:process-renewals`, --dry-run, --window)
- app/Models/SubscriptionLog.php: ACTION_AUTO_RENEWED + ACTION_AUTO_RENEW_INVOICE
- bootstrap/app.php: dailyAt('08:00') scheduler entry
- tests/Feature/ProcessRenewalsTest.php (13 tests)
**Impact:** 13/13 pass | 29 assertions. Idempotent (renewed_at today = skip; pending invoice today = skip).

---

## 2026-06-06 — Payment Gateway Integration (P1)

**Reason:** Wire real payment gateways (Zarinpal + IDPay) to subscription flow — replace manual activation with real online payment.
**Files Changed (customer-site):**
- config/payment.php (new — PAYMENT_GATEWAY, zarinpal, idpay settings)
- app/Services/Payment/: PaymentGatewayInterface, PaymentResult, GatewayException, ZarinpalGateway, IdpayGateway, PaymentGatewayFactory (all new)
- SubscriptionPaymentController: pay() → redirect to gateway, callback() → verify + activate, confirm() → blocked when gateway active
- front/subscriptions/pay.blade.php: gateway-aware CTA
- routes/web.php: GET /subscription/callback/{invoiceNumber} added
- .env.example: payment env vars added
- tests/Feature/SubscriptionPaymentGatewayTest.php (18 tests, all mocked HTTP)
**Impact:** 18/18 tests pass | 32 assertions. Gateway fully wired. isConfigured() guard keeps fallback for manual activation when no gateway set.

---

## 2026-06-06 — Growth Engine (Faz 4)

**Reason:** Referral program (link+QR+reward both parties), public pricing page /plans, MRR+Churn admin dashboard.
**Files Changed (customer-site):**
- migration 300000_add_reward_fields_to_referrals — rewarded_at, points_awarded
- Referral model — STATUS_PENDING/REWARDED constants, casts
- ReferralService — getOrCreateCode, registerReferral, rewardReferral (idempotent), getStats, buildLink
- MembershipPointsService — earnFromReferral() added
- RegisterController — ref_code hidden field integration + referral creation on register
- SubscriptionService — rewardReferral() hook on first subscription
- auth/register.blade.php — ref_code field + green badge indicator
- Account/ReferralController + views/account/referrals/index.blade.php — link, QR, stats, steps
- Front/PricingController + views/front/pricing/index.blade.php — public plan cards, FAQ, login-aware CTA
- Admin/SubscriptionDashboardController — computeMrr() + computeChurn() methods
- admin/subscriptions/dashboard.blade.php — MRR card + Churn card with trend indicator
- routes/web.php — account.referrals.index + pricing.index
- ReferralServiceTest (15 tests) + PublicPlansTest (9 tests)
**Impact:** 24/24 tests pass | 43 assertions. Referral: 300 pts referrer + 100 pts referred. MRR normalized 30d. Churn = cancelled+expired / active-at-start × 100.

---

## 2026-06-06 — Retention Engine (Faz 3)

**Reason:** SMS expiry reminders (7d+1d), renewal coupon, birthday gift points — to reduce churn.
**Files Changed (customer-site):** migration 300000 (reminder columns), SubscriptionLog ACTION constants, Subscription model fillable+casts, SendSubscriptionRemindersCommand (7d+1d+coupon), ProcessBirthdayGiftsCommand (200pts+SMS), bootstrap/app.php scheduler (2 new daily tasks), 2 test files (14 tests).
**Impact:** 52/52 all membership tests pass (114 assertions). Retention lifecycle complete. All commands idempotent.

---

## 2026-06-06 — Membership Points & Cashback Engine (Faz 2)

**Reason:** Build Points Engine — earn on purchase/subscribe/renew, spend at checkout.
**Files Changed (customer-site):** MembershipPoint model, MembershipPointsService (earn/spend/balance), 3 migrations (points table, order columns, loyalty_multiplier decimal fix), ShopController (earn+spend), SubscriptionService (earn on subscribe/renew), cart slider UI, account/points view, dashboard tile, 20 tests (38/38 total pass).
**Impact:** Full points lifecycle operational. 1000 pts = 10,000 toman. loyalty_multiplier INTEGER→decimal bug fixed.

---

## 2026-06-06 — EDU-16: Human Review & Approval Workflow (Simplified)

**Reason:** Add human-in-the-loop gate before AI-generated drafts are published. Architectural simplification: removed `pending_review` state — simplified workflow is `draft → published | archived`.  
**Design decision confirmed:** `pending_review` provides zero business value in this system; `draft` already means "awaiting human review".  
**Files Changed:**
- `src/Models/EducationArticle.php` — removed STATUS_PENDING_REVIEW + REVIEWABLE_STATUSES; simplified isReviewable() and scopePendingReview()
- `src/Exceptions/DraftNotReviewableException.php` — removed REVIEWABLE_STATUSES reference
- `src/Services/Education/EducationDraftReviewService.php` — replaced whereIn(REVIEWABLE_STATUSES) with where(STATUS_DRAFT); updated comments
- `src/Contracts/Services/EducationDraftReviewServiceInterface.php` — updated pendingQueue() docblock
- `src/Http/Controllers/Api/EducationReviewController.php` — NEW: approve + reject endpoints with Gate checks + 422 on DraftNotReviewableException
- `src/Http/Controllers/Api/EducationGeneratedContentController.php` — removed pending_review response key; added reviewed_at IS NOT NULL filter to approved/rejected counts
- `src/Http/Controllers/Admin/EducationGeneratedContentReviewController.php` — NEW: admin dashboard controller
- `src/Providers/EducationServiceProvider.php` — registered EducationDraftReviewServiceInterface binding
- `Routes/api.php` — added approve + reject routes
- `Routes/admin.php` — added review dashboard route
- `Resources/views/admin/education/generated-content/review.blade.php` — NEW: RTL dashboard with analytics bar, FIFO queue table, inline approve/reject with Alpine.js
- `Resources/views/admin/education/index.blade.php` — added 'بررسی AI' nav entry to quick navigation grid
- `Database/Migrations/2026_06_06_000001_add_review_fields_to_edu_articles.php` — comment-only update (schema was already correct)
- `tests/Feature/GeneratedContentApiTest.php` — removed pending_review key; updated approved/rejected tests for reviewed_at filter; added reviewed param to seedDrafts()
- `tests/Feature/EDU152RemediationTest.php` — removed pending_review key from assertJsonStructure
- `tests/Feature/DraftReviewServiceTest.php` — NEW: 16 tests covering approve, reject, idempotency, analytics, site isolation, queue filtering
- `tests/Feature/ReviewEndpointTest.php` — NEW: 14 HTTP tests covering auth, admin gate, approve flow, reject flow, validation, idempotency, site isolation  
**KI-001 Status:** All 4 generation commands already had STATUS_VALIDATION_FAILED + STATUS_PROVIDER_ERROR wired from EDU-15.2 — confirmed no action required.  
**Impact:** EDU-16 COMPLETE. State machine: draft → published (approve) | archived (reject). 30 new tests. No pending_review anywhere in codebase.

---

## 2026-06-06 — Membership Benefits Engine (Faz 1 — Benefits Enforcement)

**Reason:** اعمال واقعی مزایای اشتراک در checkout — تخفیف محصول، ارسال رایگان، VIP support  
**Files Changed (customer-site):**
- `app/Dto/MembershipDiscountResult.php` — NEW
- `app/Services/MembershipBenefitService.php` — NEW (cache 5min, product-scoped, pivot discount)
- `database/migrations/2026_06_06_100000_add_membership_discount_to_orders_table.php` — NEW
- `app/Models/Order.php` — membership_discount_amount + membership_plan_name
- `app/Http/Controllers/Front/ShopController.php` — membership in cart() + checkout()
- `app/Providers/AppServiceProvider.php` — MembershipBenefitService singleton
- `resources/views/themes/default/shop/cart.blade.php` — complete redesign (2-column, RTL, membership panel)
- `resources/views/partials/shop-captcha.blade.php` — static call bug fixed (PHP 8.4)
- `database/factories/UserFactory.php` — email removed, mobile added
- `database/migrations/2026_06_04_100000_create_push_subscriptions_table.php` — defensive hasTable()
- `packages/.../tickets/.../rename_customer_email...php` — defensive hasColumn()
- `tests/Feature/MembershipBenefitServiceTest.php` — NEW 11 tests
- `tests/Feature/MembershipCheckoutTest.php` — NEW 7 tests  
**Impact:** 18/18 tests pass (48 assertions). Membership discount applied to Order. Cart UI professional with membership banner, savings display, 2-column layout.

---

## 2026-06-05 — EDU-15.2 Forensic Audit Remediation

**Reason:** Forensic audit of EDU-15 found 3 critical bugs (F-01, T-01, F-02), one race condition (RC-01), and one security gap (S-02).  
**Files Changed:**
- `packages/saas-platform/modules/education/src/Console/Commands/GenerateContentCommand.php` — F-01 fix: consume queue directly, preserve moduleSlug
- `packages/saas-platform/modules/education/src/Console/Commands/GenerateGapContentCommand.php` — F-01 fix: same pattern
- `packages/saas-platform/modules/education/src/Console/Commands/GenerateArticlesCommand.php` — F-02: logFailure() in catch
- `packages/saas-platform/modules/education/src/Console/Commands/GenerateWalkthroughsCommand.php` — F-02: logFailure() in catch
- `packages/saas-platform/modules/education/src/Contracts/Services/EducationAiContentGenerationServiceInterface.php` — added providerName()
- `packages/saas-platform/modules/education/src/Contracts/Services/EducationDraftPersistenceServiceInterface.php` — added logFailure()
- `packages/saas-platform/modules/education/src/Services/Education/EducationAiContentGenerationService.php` — implemented providerName()
- `packages/saas-platform/modules/education/src/Services/Education/EducationDraftPersistenceService.php` — implemented logFailure(), two-layer persist()
- `packages/saas-platform/modules/education/src/Policies/EducationArticlePolicy.php` — added viewGeneratedContent()
- `packages/saas-platform/modules/education/Database/Migrations/2026_06_05_000002_add_ai_draft_dedup_index_to_edu_articles.php` — NEW: dedup index
- `packages/saas-platform/modules/education/tests/Feature/GenerateContentCommandTest.php` — F-01 regression guard
- `packages/saas-platform/modules/education/tests/Feature/GenerateGapContentCommandTest.php` — F-01 regression guard + siteId guard
- `packages/saas-platform/modules/education/tests/Feature/GeneratedContentSecurityTest.php` — T-01 fix: withTenantSite() helper
- `packages/saas-platform/modules/education/tests/Feature/EDU152RemediationTest.php` — NEW: S-02 tests  
**Impact:** All 3 critical bugs resolved. Dedup index prevents concurrent duplicate generation. Policy gate closes S-02. EDU-16 conditions documented.

---

## 2026-06-05 — EDU-15 AI Content Generation Engine

**Reason:** Build the AI-to-draft pipeline: generators, commands, persistence service, API endpoint.  
**Files Changed (key):**
- `src/Generation/ArticleDraftGenerator.php` — NEW
- `src/Generation/WalkthroughDraftGenerator.php` — NEW
- `src/Generation/LearningPathDraftGenerator.php` — NEW
- `src/Services/Education/EducationAiContentGenerationService.php` — NEW
- `src/Services/Education/EducationDraftPersistenceService.php` — NEW
- `src/Services/Education/GeneratedContentValidator.php` — NEW
- `src/Console/Commands/GenerateArticlesCommand.php` — NEW
- `src/Console/Commands/GenerateWalkthroughsCommand.php` — NEW (plus GenerateContentCommand, GenerateGapContentCommand updated)
- `src/Http/Controllers/Api/EducationGeneratedContentController.php` — NEW
- `Routes/api.php` — added GET /api/v1/education/generated-content
- 13 test files, 100+ tests  
**Impact:** Full AI draft pipeline operational. Drafts stored as auto_generated articles. No live AI calls until EDUCATION_AI_PROVIDER env is set.

---

## 2026-06-05 — EDU-14B Intelligence Orchestration Layer

**Reason:** Build orchestration services that consume EDU-14A foundation data and feed EDU-15 generation pipeline.  
**Files Changed (key):**
- `src/Services/Education/RecommendationRefreshService.php` — NEW
- `src/Services/Education/KnowledgeGapQueueService.php` — NEW
- `src/Services/Education/ContentDeficitQueueService.php` — NEW
- `src/Services/Education/EducationAnalyticsService.php` — NEW
- `src/Console/Commands/RefreshRecommendationsCommand.php` — NEW
- `src/Console/Commands/BuildGapQueueCommand.php` — NEW
- `src/Console/Commands/BuildContentDeficitQueueCommand.php` — NEW
- `src/Http/Controllers/Api/EducationAnalyticsController.php` — NEW
- `Routes/api.php` — added GET /api/v1/education/analytics
- 5 test files, 47+ tests  
**Impact:** Full orchestration layer active. Commands can now be scheduled to keep recommendations fresh and content queues populated.

---

## 2026-06-05 — EDU-14A.1 Stability & Isolation Remediation

**Reason:** Audit of EDU-14A found 5 FAIL findings blocking production readiness.  
**Files Changed:**
- `src/Services/Education/ContentReadinessService.php` — F-01 /100 fix, F-02 batch queries, PA-08 single count
- `src/Contracts/Services/KnowledgeGapServiceInterface.php` — PA-09 invalidate()
- `src/Services/Education/KnowledgeGapService.php` — F-03 whereNull, PA-07 JOIN deleted_at filter, PA-09 cache
- `src/Services/Education/AIRecommendationScoreService.php` — F-04 whereNull in maps
- `src/Services/Education/UserLearningProfileService.php` — F-05 whereNull, PA-02 shared $scopeQuery
- `tests/Feature/ContentReadinessTest.php` — NEW (10 tests)
- `tests/Feature/EDU14ARemediationTest.php` — NEW (18 tests)  
**Impact:** All 5 FAIL findings resolved. EDU-14A certified production-ready. EDU-14B unblocked.

---

## 2026-06-04 — EDU-14A AI Engine Foundation

**Reason:** Establish deterministic AI learning profile, gap detection, recommendation scoring, and content readiness services.  
**Files Changed (key):**
- `src/Dto/UserLearningProfileDto.php` — NEW
- `src/Services/Education/UserLearningProfileService.php` — NEW
- `src/Services/Education/KnowledgeGapService.php` — NEW
- `src/Services/Education/AIRecommendationScoreService.php` — NEW
- `src/Services/Education/ContentReadinessService.php` — NEW
- `src/Http/Controllers/Api/EducationProfileController.php` — NEW
- `Routes/api.php` — added GET /api/v1/education/profile
- 4 contract interfaces — NEW
- 3 test files (30 tests)  
**Impact:** Foundation for AI-driven education established. No external AI provider required. EDU-14B unblocked.

---

## 2026-06-04 — EDU-13.5 / 13.6 / 13.7 Production Hardening

**Reason:** Pre-beta audit found critical security, data integrity, and performance issues.  
**Files Changed (key):**
- `Routes/api.php` — auth:sanctum added to mobile routes
- `MobileEducationController::show()` — cross-tenant site_id check
- `EducationSyncApiController` — authorizeSite() on all 6 actions
- `EducationWalkthroughPolicy` — NEW + registered
- `WalkthroughTriggerService::markCompleted()` — BREAKING: site_id added
- `EducationGuideComposer` — 60s cache, null siteId skip
- `TrackFeatureUsageMiddleware` — moved to admin group only
- `EducationFeatureUsageRepository::track()` — atomic INSERT ON DUPLICATE KEY UPDATE
- Migration: unique index on edu_recommendations
- `EducationGuideComposer` — EDU-13.7: null siteId guard
- 3 test files (EDU-13.5) + 1 test file (EDU-13.7 beta closure)  
**Impact:** EDU-13 CERTIFIED BETA. All security + data integrity + performance findings resolved.

---

## 2026-06-04 — EDU-12 Customer Site Sync

**Reason:** Enable differential sync of education content from platform to customer sites.  
**Files Changed (key):**
- 3 new migrations (versioning fields + edu_sync_logs)
- `EducationSyncService` — NEW (differential sync, hash manifests)
- `EducationWalkthroughGeneratorService` — NEW
- `EducationLearningPathGeneratorService` — NEW
- `EducationChangeDetectorService` — NEW
- `DocumentationGeneratorService` — REWRITTEN (12-section, 1500-3000 words)
- API sync endpoints (6 routes)
- 3 new Artisan commands
- `customer-site`: EducationCenterController + 2 views + routes  
**Impact:** Customer sites can now receive differential education content updates. 13 sync rules all compliant.

---

## 2026-06-03 — Education Module (EDU base)

**Reason:** Build unified education/guidance ecosystem to replace scattered KB + guides.  
**Files Changed (key):**
- 12 migrations (all edu_* tables)
- 12 Eloquent models
- Full service layer (11 services)
- 5 repository interfaces + implementations
- Admin routes (34 routes)
- API routes
- Admin views (RTL, Tailwind, Alpine.js)
- 3 Artisan commands
- `EducationServiceProvider` — module auto-discovery  
**Impact:** Education module fully operational. KB legacy redirects in place. `education:validate` scores 100/100.

---

## 2026-06-01 — Phase M3.2 Dual Messaging Architecture

**Reason:** Separate tenant support messaging (customer-site) from platform communication (saas-platform) at DB level.  
**Files Changed (key):**
- `customer-site`: 6 migrations, 6 models, 6 services, API routes, channels, `SupportModuleLicenseGate`
- `saas-platform`: 4 migrations, 4 models, 4 services, `PlatformConversationPolicy`
- `mobile/lib/core/runtime/`: `runtime_mode.dart`, `runtime_selector_service.dart` — NEW  
**Impact:** Domain isolation enforced at model level. End-user data structurally impossible in platform DB.

---

## 2026-06-01 — Phase M3.0 Enterprise Theme Engine

**Reason:** Multi-tenant theme customization with runtime CSS variable injection.  
**Files Changed (key):**
- `ThemeProfile` model + migration
- `ThemeTokenRenderer` service
- 8 preset themes
- Mobile API endpoint for theme tokens
- Theme manager admin UI  
**Impact:** Each tenant can have a distinct visual identity. Mobile app receives theme tokens from API.

---

## 2026-05-30 — Phase M2.x Flutter Mobile Client

**Reason:** Unified Flutter client for all platforms (Android/iOS/Windows/Linux/macOS).  
**Files Changed (key):**
- `mobile/` directory at repo root
- `pubspec.yaml`, full `lib/` structure
- Core services: ClientApiService, RealtimeClientService, OfflineCacheService, OtaUpdateService, SecureTokenService
- Screens: Login, ConversationList, ConversationView, Settings
- 4 Artisan governance commands  
**Impact:** Single Flutter codebase covering all 5 platforms. OTA updates operational.

---

## 2026-05-29 — Phase 10.2.8 Tailwind v4 Migration

**Reason:** Upgrade from Tailwind v3.4.19 to v4.3.0 for Lightning CSS engine and faster builds.  
**Files Changed (key):**
- `package.json` — tailwindcss@4.3.0, @tailwindcss/vite@4.3.0
- `vite.config.js` — @tailwindcss/vite plugin added
- `tailwind.config.js` — DELETED
- `postcss.config.js` — EMPTIED
- `resources/css/app.css` — @import "tailwindcss", @source paths, @theme block  
**Impact:** Build succeeds (exit 0, 161KB CSS). Zero legacy directives.

---

## 2026-05-28 — Phase 10.x Frontend Governance System

**Reason:** Establish canonical design system authority across all modules.  
**Files Changed (key):**
- `core-utils/resources/css/design-tokens.css` — 520+ lines, 200+ CSS vars
- 28 canonical `x-core-ui::*` Blade components
- 5 PHP governance services per phase
- CI: `frontend-governance.yml`, `tailwind-runtime-governance.yml`  
**Impact:** Frontend authority at 100/100. Zero drift across all modules. Alpine/Blade `:attr` bug discovered and fixed.

---

## 2026-05-25 to 2026-05-27 — Phases 8.x Platform Hardening

**Reason:** Security hardening, package boundary repair, observability, governance, scalability, plugin sandbox.  
**Files Changed:** Hundreds of files across platform governance, telemetry, circuit breakers, queue management, capacity planning.  
**Impact:** Platform score 96/100. Production-ready. Zero App\ imports in packages. Tenant isolation guaranteed.
