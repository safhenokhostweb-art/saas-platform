# PROJECT STATE
> Primary checkpoint. Read this first at the start of every session.
> Last Updated: 2026-06-25

---

## Current Project

**Name:** SaaS Platform  
**Stack:** Laravel 10, PHP 8.4, MySQL, Redis, Tailwind CSS v4, Alpine.js, Flutter (mobile)  
**Repo root:** `c:\laragon\www\saas-platform`  
**Customer site repo:** `c:\laragon\www\customer-site`  
**Package namespace:** `saas-platform/modules-*`  

---

## Current Module

**CENTRAL_FINANCE_LEDGER_MODULE COMPLETE (2026-06-25) — Full financial ledger operational**

---

## Current Phase

**PLATFORM_INSTALLER_PHASE1_COMPLETE (2026-06-26). Platform Installer scaffolding complete. 3 installation modes (Fresh/Restore/Upgrade), 7 service classes, 1 controller, 1 middleware, 20 installer views, 9 routes, admin menu entry. Reuses RestoreService, ArvanBackupService, DrReadinessScoreService, SlackAlertService. install.lock security mechanism. Standalone RTL dark mode Persian UI wizard. Architecture: PASS, Security: PASS, Reuse: PASS.**

**Previous:** PERSIAN_LOCALIZATION_GLOBAL_STANDARD_DEPLOYED (2026-06-26). Full Persian digit + Jalali date localization implemented across both repos. Centralized PersianFormatter layer, 8 Blade directives, global helpers autoloaded, all number_format() violations fixed, all date inputs converted to Jalali pickers, controllers updated for Jalali→Gregorian conversion. 4 certification docs generated.**

**Previous:** SITE_THEME_PRESET_FIX_DEPLOYED (2026-06-26). Bug fix: site theme presets now apply to the public customer site frontend; custom mode fully operational with 9 comprehensive color fields.

**Previous:** DISASTER_RECOVERY_CENTER_ENTERPRISE_READY (2026-06-26). DR Center Phase 2 Completion deployed — readiness score, backup age, simulation history, Arvan status.

**Previous:** DR_CENTER_OPERATIONAL (2026-06-26). Phase 2 complete. Full DR Center dashboard implemented and deployed.

**Previous:** RESTORE_WIZARD_OPERATIONAL (2026-06-26). Phase 1 complete.

**Previous:** INSTALLER_ARCHITECTURE_DECISION_COMPLETE (2026-06-26). Architecture-only phase. 5 decision documents produced. Zero code changes. LAUNCH FREEZE still active.

**Previous:** FINANCE_LEDGER_OPERATIONAL. 7-phase finance module complete.

**Previous:** LICENSE_SYNC_FALSE_WARNING_ELIMINATED. Customer dashboard no longer shows false "هشدار موقت لایسنس".

**Previous phase:** GO_LIVE_READY_WITH_WARNINGS (97/100). All pre-launch verification complete.

Fixes applied on production server (87.107.12.81):
- customer-site-worker FATAL → RUNNING (critical fix)
- PLATFORM_URL http → https on customer-site
- LOG_LEVEL debug → error on customer-site
- VITE_APP_URL corrected on saas-platform
- deploy.sh hardened to restart FATAL workers

**Previous:** PRE_LAUNCH_POLISH_AND_HARDENING complete. 6 phases done: Customer UX Polish (score 89→92), Admin UX Polish (score 90→93), SSL Hardening, Offsite Backup Audit, Monitoring Hardening (3 gaps implemented in ScanRuntimeAnomaliesCommand), Launch Day Runbook generated.

### TB3 Sprint 10 Summary (in progress)
- **GAP-001 CRITICAL ✅**: `MarketplaceThemeSeedCommand` — 12 themes across all 15 categories
- **GAP-002 CRITICAL ✅**: Migration rewritten with raw SQL + `Schema::hasColumn()` guards
- **GAP-003 CRITICAL ✅**: `MarketplaceSeedScoresCommand` + `scoreTheme` HTTP endpoint
- **BRK-001 ✅**: `ThemeMarketplaceQualityService` wired to `ThemeMarketplaceAdminController`
- **BRK-002 ✅**: `installs_count` increment endpoint + auto-bestseller at 10+ installs
- **BRK-003 ✅**: Backfill existing themes with `category` field
- **GAP-004 HIGH ✅**: Scoring dashboard view (`/admin/theme-marketplace/scoring`)
- **GAP-005 HIGH ✅**: Marketplace search (name/slug filter in index)
- **GAP-006 HIGH ✅**: Category filter dropdown in admin marketplace index
- **GAP-007 MEDIUM ✅**: Bestseller badge display + auto-promote logic
- **GAP-008 MEDIUM ✅**: New release badge display in index view
- **GAP-009 MEDIUM ✅**: Annual/Lifetime pricing UI + form inputs + updateListing() validation + persistence
- **GAP-011 LOW ✅**: Update notifications — installed_theme_version column + isOutdated() + outdatedLicensesCount() + admin badge
- **GAP-012 LOW ✅**: Demo data manifest — ThemeDemoDataService block scanner (14 types), preview page panel, POST /{theme}/demo-manifest
- **GAP-013 LOW ✅**: Preview page — /admin/theme-marketplace/{theme}/preview with full metadata, pricing cards, quality scores, screenshots
- **GAP-010 LOW ⏳**: Theme Bundle System — DEFERRED (requires theme_bundles table + separate purchase flow)

### Also completed in this session
- **SIDEBAR_UI_CERTIFIED**: Platform admin + customer-site sidebar spacing/height fixes
- **DATE_LOCALIZATION_CERTIFIED**: 72 Jalali/Persian date fixes across both repos
- **TB3_SPRINT9_5_CERTIFIED**: Visual Reverse Engineering Engine (7-phase pipeline)
- **Duplicate Notification Fix**: 51 view files cleaned, admin.blade.php auto-dismiss alerts

### TB3 Sprint 9.5 Summary
- 7-phase pipeline: DOM Extraction → Layout → Visual → Commerce → Similarity → Reconstruction → Validation
- 4 input modes: URL (HTTP fetch + DOMXPath), Screenshot (Claude Vision API), HTML (DOMDocument), Prompt (keyword heuristics)
- 6 reference sites for similarity scoring: Apple, Amazon, Malltina, Digikala, Stripe, Shopify
- Similarity engine: 4 dimensions × 6 sites, Euclidean RGB color distance, type-weighted overall score
- AI enrichment: Claude claude-sonnet-4-6 + PHP fallback (ThemePlaceholderService)
- Safe mode: no copyright copy — only design tokens (colors/fonts/layout) extracted
- Admin panel: `/admin/theme-builder/reverse-engineer` — form, result, export, install into Canvas
- `reverse_engineering_jobs` DB table with full phase-by-phase JSON columns

### Previous: TB-AI Studio + Import Pipeline — TB_AI_STUDIO_CERTIFIED (2026-06-20)
- Standalone PHP app at `c:\laragon\www\tb-ai-studio\`
- 4 AI providers: Claude, OpenAI, DeepSeek, Custom
- ZipExporter, SaasPlatformUploader, 5 Studio API endpoints, admin UI

### Previous: TB3 Sprint 8 — AI Theme Generator — CERTIFIED COMPLETE (2026-06-20)
- 4 input methods, 11 sections, ThemePack output, safe mode, PHP fallback, ai_generation_jobs table

TB3-01: block.props canonical everywhere (data→props normalization on load)  
TB3-02: Live iframe center panel replacing block-card-list  
TB3-03: Drag widget from library directly into canvas  

---

## Current Sprint

TB3 Sprint 2 (TB3-04+) — pending, awaiting user decision.

---

## Current Objective

TB3 Sprint 1 کامل شد. Canvas editor هر دو ریپو (platform + customer-site) اکنون دارای:
- live iframe preview با postMessage bridge
- device toggle (desktop/tablet/mobile)
- drag from library with drop zones
- 350ms debounce live prop updates (no reload for edits)
- block.props canonical everywhere

---

## Completed Work

### Platform Infrastructure (Phases 8.x)
- **Phase 8.0** — Security hardening (10 tasks, all complete)
- **Phase 8.1** — Package boundary repair — zero App\ imports in packages
- **Phase 8.2** — Production deployment & release safety
- **Phase 8.3** — Observability & telemetry governance — `telemetry_events` table, TelemetryManager, StructuredLogger, anomaly detection
- **Phase 8.4** — Autonomous runtime governance — circuit breakers, self-healing, policy engine, incident response
- **Phase 8.5** — Distributed scalability — tenant resource isolation, noisy-neighbor protection, queue sharding
- **Phase 8.6** — Plugin sandbox runtime & capability governance

### Frontend System (Phases 10.x)
- **Phase 10.0** — Canonical design-tokens.css, 13 x-core-ui:: components
- **Phase 10.1** — Enterprise Frontend Authority (100/100) — 28 canonical components, governance commands
- **Phase 10.2** — Zero-drift enforcement, CI pipeline
- **Phase 10.2.7** — Forms builder runtime perfection (Alpine/Blade `:attr` bug fixed)
- **Phase 10.2.8** — Tailwind v4.3.0 migration (postcss removed, @theme block, vite plugin)

### Forms Module (Phases 9.x)
- **Phase 9.0** — Enterprise form builder (templates, automations, analytics)
- **Phase 9.1** — Canonical frontend parity (97/100)
- **Phase 9.2** — Runtime stabilization (97/100)
- **Phase 9.4** — Realtime collaboration, AI-assisted operations
- **Phase 9.5-A** — Real AI provider integration (OpenAI + Claude, circuit breaker, quota management)

### Mobile & Messaging (Phases M2.x, M3.x)
- **Phase M2.0** — Flutter single-codebase client (Android/iOS/Windows/Linux/macOS)
- **Phase M2.1/M2.1.5** — OTA updates, offline cache, secure tokens
- **Phase M3.0** — Enterprise Theme Engine (8 presets, ThemeProfile, mobile API)
- **Phase M3.2** — Dual Messaging Architecture (Domain A: tenant, Domain B: platform)

### Education Module (Phases EDU-12 → EDU-15)
- **EDU (base)** — 12 migrations, 12 models, full service layer, 34 admin routes, AI stub
- **EDU-12** — Customer-site sync, differential sync, hash manifests
- **EDU-13** — (see EDU-13.5)
- **EDU-13.5 / 13.6 / 13.7** — Production hardening, security, data integrity, performance — **CERTIFIED BETA**
- **EDU-14A** — AI Engine Foundation: UserLearningProfile, KnowledgeGap, AIRecommendationScore, ContentReadiness
- **EDU-14A.1** — Stability & isolation remediation (all 5 FAIL findings resolved)
- **EDU-14B** — Intelligence Orchestration Layer: 4 services, 3 commands, /analytics API, 47+ tests
- **EDU-15** — AI Content Generation: 3 draft generators, 5 commands, /generated-content API, 13 test files, 100+ tests
- **EDU-15.2** — Forensic audit remediation: F-01 (module_slug bug), F-02 (failure logging), RC-01 (dedup index), S-02 (policy gate)
- **EDU-16** — Human Review Workflow: approve/reject endpoints, admin review dashboard, simplified state machine (draft→published|archived), 30 new tests, pending_review removed

---

## In Progress Work

None active. Last completed: RESTORE_WIZARD_OPERATIONAL (2026-06-26).

---

## Pending Work

| ID | Description | Depends On |
|----|-------------|------------|
| EDU-16 | Human review & approval workflow | EDU-15 ✅ |
| EDU-17 | AI provider live wiring (EDUCATION_AI_PROVIDER env) | EDU-16 |
| Phase 8.3a | Private Satis/Packagist package distribution | — |
| Phase 8.3b | Automated DB backup before deploy | — |
| Phase 8.3c | GitHub Actions / Envoyer CI pipeline | — |
| Phase 8.3d | Slack webhook alerting | — |
| Phase 8.4a | DB indexes on site_id columns | — |
| Phase 8.4b | Cache for domain resolution in TenantContext | — |
| Phase 8.4c | External alerting (critical anomaly → Slack) | — |
| Phase 8.4d | OpenTelemetry export (Jaeger/Tempo) | — |
| M2.1 remaining | iOS code signing, Firebase FCM injection, deep-link routing | — |

---

## Blocked Work

| ID | Description | Blocker |
|----|-------------|---------|
| AI provider live | Real LLM calls in education module | `EDUCATION_AI_PROVIDER` env + EDU-16 must be done first |
| Redis shared cache | Multi-node circuit breaker | Infrastructure decision |

---

## Current Risks

1. **EDU-16 prerequisite**: `STATUS_VALIDATION_FAILED` / `STATUS_PROVIDER_ERROR` constants exist in `EducationGenerationLog` but are never written. Analytics `generation_success_rate` always returns 1.0. Must wire failure log writes before analytics go live.
2. **Dedup index**: `edu_articles_ai_draft_unique` migration exists but must be run on all environments.
3. **`GET /api/v1/education/generated-content`** lacks full policy protection beyond `auth:sanctum` — `viewGeneratedContent` policy was added but review-action endpoints for EDU-16 must also have Gate checks.
4. **Tailwind v4 optimizer warnings**: `shadow-[var(--ui-shadow-*)]` produces Lightning CSS notices on build — non-breaking but should be resolved.

---

## Overall Platform Score

| Dimension | Score |
|-----------|-------|
| Security | 96/100 |
| Plugin Capability Governance | 98/100 |
| Observability | 82/100 |
| Runtime Governance | 98/100 |
| Scalability | 90/100 |
| Frontend Authority | 100/100 |
| Forms Module | 97/100 |
| Education Module (EDU-15) | ~93/100 (post-audit) |

---

## Restore Wizard (Phase 1) — COMPLETE

**Files created (2026-06-26):**
- `database/migrations/2026_06_26_000001_create_restore_logs_table.php`
- `app/Models/Infrastructure/RestoreLog.php`
- `database/factories/RestoreLogFactory.php`
- `app/Services/Infrastructure/RestoreService.php`
- `app/Jobs/RestoreTenantDatabaseJob.php`
- `app/Jobs/RestorePlatformStorageJob.php`
- `app/Http/Controllers/Admin/RestoreWizardController.php`
- `resources/views/admin/disaster-recovery/index.blade.php`
- `resources/views/admin/disaster-recovery/logs.blade.php`
- `resources/views/admin/disaster-recovery/log-show.blade.php`
- `tests/Feature/DisasterRecovery/RestoreWizardTest.php`
- `docs/RESTORE_WIZARD_SECURITY_AUDIT.md`
- `docs/RESTORE_WIZARD_TEST_PLAN.md`
- `docs/RESTORE_WIZARD_CERTIFICATION.md`

**Files modified:**
- `app/Services/Infrastructure/ArvanBackupService.php` (added listFiles, download, lastModified)
- `routes/web.php` (added 9 disaster-recovery routes)
- `app/Helpers/AdminMenu.php` (added disaster_recovery + restore_wizard menu entries)
- `resources/views/admin/partials/sidebar-icon.blade.php` (added 2 icons)

**Production steps required:**
1. `php artisan migrate` — creates restore_logs table
2. `php artisan queue:work --queue=restore` — start restore worker
3. Verify Nginx `fastcgi_read_timeout 300` for SSE

---

## Next Immediate Step

Begin **Phase 2 (DR Center)** or **EDU-16**: Human Review & Approval Workflow.

Steps:
1. Add `status` field transitions: `draft → pending_review → approved | rejected`
2. Implement `POST /api/v1/education/generated-content/{id}/approve`
3. Implement `POST /api/v1/education/generated-content/{id}/reject`
4. Build admin review dashboard view
5. Wire failure log writes (`STATUS_VALIDATION_FAILED`, `STATUS_PROVIDER_ERROR`)
6. Add Gate checks on all review-action endpoints (S-02 condition)
