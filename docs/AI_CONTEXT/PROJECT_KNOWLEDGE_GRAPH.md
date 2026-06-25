# PROJECT KNOWLEDGE GRAPH
> Feature → Controller → Service → Model → DB → Documentation mapping.
> Generated: 2026-06-24 | Line limit: ≤400

---

## HOW TO READ
- `→` means "calls/uses"
- `(saas)` = saas-platform repo | `(cust)` = customer-site repo
- Lines link directly to existing docs — no content duplicated

---

## 1. THEME BUILDER CORE (TB3)

```
Feature: Visual Reverse Engineering
(saas) Web route: /admin/theme-builder/reverse-engineer
Controller: ThemeReverseEngineeringController
  → VisualReverseEngineeringService (7-phase pipeline)
    → DomExtractionService (DOMXPath, 12 component types)
    → LayoutReconstructionService (column counts, spacing, breakpoints)
    → VisualAnalysisService (colors, typography, buttons, radius, shadows)
    → CommerceAnalysisService (product cards, price blocks, discounts)
    → SimilarityEngineService (4 dims × 6 ref sites, RGB distance)
    → ReverseThemeReconstructorService (Claude sonnet + fallback)
    → ReverseEngineeringQualityService (10 checks, PASS/FAIL/PARTIAL)
  → PlaywrightExtractionService (Node extract.mjs bridge)
    → scripts/headless/extract.mjs (multi-viewport, SPA hydration)
Model: ReverseEngineeringJob | DB: reverse_engineering_jobs
Docs: VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md

Feature: JSON Import Engine
(saas) Web route: /admin/theme-builder/json-import
Controller: ThemeJsonImportController
  → JsonImportPipelineService (5-source orchestrator)
    → ThemeStructureAnalyzer
    → WidgetMapper (35+ widget types)
    → ThemePackGenerator (quality scoring 100 pts)
  → Adapters: Elementor, Bricks, Shopify, Webflow, Custom
Docs: JSON_IMPORT_ENGINE_CERTIFICATION.md

Feature: ZIP Template Import Engine
(saas) Web route: /admin/theme-builder/zip-import
Controller: ThemeZipImportController
  → ZipTemplateImportService (7-phase orchestrator)
    → ZipTemplateExtractor (100MB/500 file limits, path traversal guard)
    → ZipTemplateAnalyzer (6 origins, JSON priority)
  → 5-dim similarity scoring
Docs: ZIP_IMPORT_ENGINE_ARCHITECTURE.md, ZIP_IMPORT_ENGINE_CERTIFICATION.md

Feature: AI Theme Generator (TB3 Sprint 8)
(saas) Route: /admin/theme-builder/ai-generate
  → AiThemeGenerator (4 input modes, 11 sections, ThemePack output)
  → AI providers: Claude, OpenAI, DeepSeek, Custom
Model: AiGenerationJob | DB: ai_generation_jobs
Docs: TB_AI_STUDIO_ARCHITECTURE.md, TB3_SPRINT8_CERTIFICATION.md

Feature: Theme Marketplace
(saas) Routes: /admin/theme-marketplace/* + /api/theme-marketplace/*
Controller: ThemeMarketplaceAdminController + ThemeMarketplaceApiController
  → ThemeMarketplaceService (CRUD, quality scoring, installs)
  → ThemeMarketplaceQualityService (5-dimension scoring)
  → MarketplaceThemeSeedCommand (12 themes, all categories)
  → ThemeDemoDataService (block scanner, 14 types)
Docs: MARKETPLACE_THEME_SEED_PLAN.md, TB3_SPRINT10_AUDIT.md
```

---

## 2. PLATFORM LIFE CYCLE & INFRASTRUCTURE

```
Feature: Site Lifecycle (5-state machine)
(saas) Routes: /admin/lifecycle/*, /admin/sites/{site}/lifecycle
Controller: LifecycleAdminController
  → SiteLifecycleService (state machine: active→grace→suspended→archived→deleted)
  → SiteArchiveService (ZIP creation)
  → SiteLifecycleNotificationService (SMS + dashboard)
Models: Site (lifecycle columns), SiteLifecycleLog, SiteLifecycleInvoice
DB: sites.lifecycle_status, site_lifecycle_logs, site_lifecycle_invoices
Commands: check-expired, check-grace-period, check-suspended, check-archived (daily)
(cust) Middleware: CheckSiteLifecycle (503 on suspended)
(cust) Views: suspended.blade.php, lifecycle/pay-failed.blade.php
Docs: CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md

Feature: Resource Governance
(saas) Route: /admin/resource-governance
Controller: ResourceGovernanceController
  → SiteResourceCalculatorService (metrics)
  → SiteResourceGovernorService (limits)
  → SiteResourceAlertService (alerts)
  → SiteResourceReportService (reports)
Models: SiteResourceUsage, SiteResourceLimit, SiteBandwidthUsage, SiteStorageSnapshot
DB: site_resource_usage, site_resource_limits, site_bandwidth_usage, site_storage_snapshots
Commands: resource:calculate (6am), resource:cleanup (Sunday 2am)
Docs: RESOURCE_GOVERNANCE_ARCHITECTURE.md

Feature: Monitoring & Health Center (MHC)
(saas) Routes: /admin/infrastructure/monitoring/*, /admin/incidents/*
Controllers: MonitoringAdminController, IncidentAdminController
  → IncidentLifecycleService (4-state: open→investigating→resolved→closed)
  → UptimeCalculator
  → SchedulerHealthTracker
  → HealthCheckService (cache/DB/queue/scheduler)
  → SiteHealthChecker (4-level severity)
  → InfrastructureErrorCollector
  → NotificationCenterService (SMS on resolve)
Models: InfraIncident, InfraIncidentNote, InfraPlatformHealthLog, InfraBackupRun, InfraSchedulerRun
Commands: monitor:platform (2min), monitor:sites (5min), runtime:scan-anomalies (5min)
Health: /health/ready
Docs: docs/project-memory/HANDOVER.md (lines 380-396)

Feature: Date Localization (cross-cutting)
(saas + cust) CoreJalali → diffForHumansFaStatic() — Persian relative time
  → 57 CRITICAL/HIGH fixes across 46 files
  → NumberHelper::formatFa() — Persian number formatting
Docs: GLOBAL_DATE_LOCALIZATION_AUDIT.md, DATE_LOCALIZATION_FIX_REPORT.md

Feature: Icon System (core-ui)
(saas) packages/saas-platform/core-utils:
  → CoreUtilsServiceProvider (registers blade namespace)
(cust) Local fallback (KI-007 fix):
  → app/Icons/LocalIconRegistry.php (70+ icons)
  → resources/views/components/core-ui/icon.blade.php
  → AppServiceProvider::boot() (anonymous component path)
```

---

## 3. CUSTOMER-SITE MODULES

```
Feature: SMS Campaign Manager
(cust) Routes: /admin/sms/campaigns/*
Controller: SmsCampaignController
  → SmsCampaignService (lifecycle: draft→scheduled→running→completed)
  → SmsService (Kavenegar + Ghasedak real HTTP implementations)
  → SendSmsCampaignJob (chunk of 50 for bulk)
Models: SmsCampaign, SmsCampaignRecipient
DB: sms_campaigns, sms_campaign_recipients
Views: index, create, show, edit
Docs: docs/project-memory/HANDOVER.md (lines 135-165)

Feature: Customer Menu Optimization
(cust) app/Helpers/AdminMenu.php:
  → MODULE_DESCRIPTION_ONLY constant (24 module groups)
  → getDynamicPlatformMenuKeys() (filter)
  → getStaticGroups() (11 semantic groups)
  → isVisible() (crm/inventory/seo_advanced/sms_campaigns)
Audit: 54 sidebar items → restructured
Docs: CUSTOMER_MENU_AUDIT.md, CUSTOMER_MENU_CERTIFICATION.md

Feature: Installment Purchase
(saas + cust) Routes: /installment/plans, /account/installments, /admin/installments/*
Controllers: InstallmentController (front), InstallmentAccountController, InstallmentAdminController
  → InstallmentService (compound interest)
Models: InstallmentPlan, InstallmentOrder, InstallmentPayment
DB: installment_plans, installment_orders, installment_payments

Feature: AI Content Translation
(saas) Route: /admin/translations/*
  → ContentTranslationService (Claude Haiku-4-5, hash invalidation)
  → HasTranslations trait (Post, Product, Course)
  → SetLocale middleware + language-switcher
DB: content_translations, translation_queue
Command: translations:process --batch=20 (every 15 min)

Feature: Smart Image Upload
(cust) SmartImageUploadService (15 contexts, WebP GD, AI hook)
(cust) smart-image-picker Blade component (drag-drop, XHR progress, metadata badges)
Applied to: products, posts, courses admin forms

Feature: Access List / Excel Import (FORM-EXT)
(cust) Routes: /admin/access-lists/*, /gate/{slug}
Controllers: AccessListController (admin), AccessGateController (public)
  → AccessListImportService (PhpSpreadsheet, batch 500, FA/EN auto-detect)
DB: access_lists, access_list_entries, access_gates, access_verifications
```

---

## 4. CANVAS THEME SYSTEM

```
Feature: Canvas Theme Rendering (customer-site)
(cust) Routes: GET / (home), GET /page/{slug}
Controller: ThemeCanvasPageController
  → ThemeCanvasRenderer (extends BlockRenderer)
    → renderBlock() overrides module:* types with live data
    → Module blocks: blog_posts, shop_product_grid, courses_grid, etc.
  → Fallback: HomeController / FrontPageController when no canvas data
  → Cache: 60s TTL (direct DB read, bypasses 3600s settings cache)
View: layouts/canvas-theme.blade.php (full RTL, no admin sidebar)

Feature: Canvas Theme Installation (platform→customer)
(saas) Controller: ThemeCanvasController
  → installToSite() writes all 4 settings (data + name + slug + id)
  → Cache::forget('canvas_theme_data') on install
(cust) loadThemeData() — direct DB read, 60s TTL
Docs: Handover lines 40-52 (2 critical bugs fixed 2026-06-23)
```

---

## 5. CORE PACKAGES

```
Package: core-ui
(saas) packages/saas-platform/core-utils/
  → CoreUtilsServiceProvider
  → Blade components: icon, button, card, form-*, modal, etc.
(cust) composer.json path-repository: ../saas-platform
(cust) Local fallback (production): LocalIconRegistry + anonymous component

Package: modules-* (feature packages)
(saas) packages/saas-platform/modules-*/
  → Each module: migrations, Models, Controllers, Services, views
  → No App\ imports allowed inside packages
Docs: modules/module-template.md, modules/billing-module.md
```

---

## 6. API ENDPOINTS (theme marketplace)

```
(saas) /api/theme-marketplace
  GET  /                     → all themes (category, license_type, pricing, badges, quality_score)
  GET  /{id}                 → single theme detail
  GET  /my-licenses          → per-license (thumbnail, version, is_outdated)
  GET  /{id}/download        → install (increment + version tracking)

(saas) /api/license
  POST /validate             → license validation
  POST /activate             → activation
  POST /deactivate           → deactivation

(cust) /api/update
  GET  /check                → update availability
  POST /apply                → apply update
```

---

## 7. MOBILE (FLUTTER) MODULES

```
Feature: Mobile App (Android + Windows)
(cust-flutter) Phase M1.0-M3.0
  → Mobile API Gateway (phase-m1.0/mobile-api-gateway.md)
  → OTA Runtime (phase-m2.1/ota-runtime.md)
  → Android native (phase-m2.1/android-runtime.md)
  → Windows native (phase-m2.1/windows-runtime.md)
  → Offline sync (phase-m2.1/offline-sync.md)
  → Self-hosted realtime (phase-m1.0/self-hosted-realtime.md)
Docs: phase-m1.0/FINAL-FOUNDATION-REPORT.md, phase-m3.0/FINAL-DEPLOYMENT-REPORT.md
```

---

## 8. DEPLOYMENT & HEALTH

```
Infrastructure: 87.107.12.81 (Nginx, PHP 8.4, MySQL 8, Redis 7)

Deploy:
  saas-platform: deploy.sh / deploy.bat / deploy.ps1
  customer-site: docs/deployment-guide-customer-site.md
  Zero-downtime: phase-8.2/zero-downtime-deployment-flow.md
  Rollback: phase-8.2/rollback-runbook.md

Health:
  Endpoint: /health/ready
  Checks: Cache (Redis), DB (MySQL), Queue (Horizon), Scheduler
  Full: docs/infrastructure/DEPLOYMENT_ARCHITECTURE.md

Security:
  SSL: Let's Encrypt (production), ArvanCloud planned
  CORS: docs/CORS_SECURITY_AUDIT.md
  Storage: docs/PRODUCTION_STORAGE_SECURITY_REPORT.md
  Monitoring: docs/MONITORING_FINAL_AUDIT.md
