# KNOWN ISSUES
> Known bugs, technical debt, temporary workarounds, and future improvements.
> Severity: CRITICAL | HIGH | MEDIUM | LOW
> Status: OPEN | WORKAROUND | DEFERRED | RESOLVED
> Last Updated: 2026-06-06

---

## TECHNICAL DEBT / ACCEPTED LIMITATIONS

### CIE-L001 — LOW | DEFERRED
**Title:** SshRemoteDriver untested against real SSH server  
**Module:** Customer Installation Engine  
**Detail:** All pipeline tests use FakeRemoteDriver. Live SSH test requires a test VPS.  
**Accepted:** PHASE-003B scope — will be validated when Admin Center UI is built.

### CIE-L002 — LOW | DEFERRED
**Title:** CustomerReleasePackageService untested with real Arvan download  
**Module:** Customer Installation Engine  
**Detail:** No real customer-site release ZIP exists yet on Arvan.  
**Accepted:** Will be tested when first release package is created.

### CIE-L003 — MEDIUM | OPEN
**Title:** INSTALLER_MASTER_KEY must be set on production before any installation  
**Module:** Customer Installation Engine  
**Detail:** CredentialVault throws RuntimeException if INSTALLER_MASTER_KEY missing. Key backup is operator responsibility.  
**Workaround:** Generate with `php artisan installer:generate-master-key` and store in secure vault.

---

## ACTIVE BUGS

### SELECT-DROPDOWN-001 — HIGH | RESOLVED (2026-06-27)

**Title:** Native `<select>` dropdown options invisible on desktop (Windows dark OS mode)  
**Module:** customer-site — Admin UI + Account UI (all pages)  
**Root Cause (confirmed 2026-06-27):**  
Tailwind CSS v4 (`@import 'tailwindcss'`) includes a preflight that sets `:root { color-scheme: light dark }`. On Windows desktop with OS dark mode enabled, Chrome uses this to render native `<select>` popup in DARK MODE (dark background). CSS rules setting `color: #1e293b` (dark text) on `<option>` elements then produced dark text on dark background = invisible. Mobile was unaffected because iOS/Android OS pickers ignore CSS on option elements entirely.  
**Resolution (final):**  
- Inline `<style id="admin-select-fix">` in `admin/layout.blade.php`: `html, :root, body.admin-body { color-scheme: light !important; }` — overrides Tailwind v4's light dark setting at root level so Chrome uses light popup  
- Same fix in `account/layout.blade.php` as `<style id="account-select-fix">`  
- `select { color-scheme: light !important; background-color: #fff !important; color: #1e293b !important; }` and `select option, select optgroup { ... }` rules in both layouts  
- Static CSS in `customer-admin-layout.css` + Vite bundle as belt-and-suspenders  
- TinyMCE converted to full dynamic loading (no eager DOMContentLoaded init)  
- `HTMLElement.prototype.focus` guard in admin layout to block focus-stealing during select interaction  
**Key diagnostic:** "mobile works, desktop doesn't" → CSS color-scheme issue confirmed  
**Commits:** `6fbd07d` (final fix)  
**Files:** `customer-site/resources/views/admin/layout.blade.php`, `customer-site/resources/views/account/layout.blade.php`

---

### USERS-NAME-001 — HIGH | RESOLVED (2026-06-25)

**Title:** `users.name` column references causing 500 in FinanceLedgerController and IncidentController  
**Module:** saas-platform — Admin Controllers  
**Root Cause:**  
1. `FinanceLedgerController::index()` had `->with(['createdBy:id,name'])` — `users` table has no `name` column; `name` is a virtual accessor only  
2. `IncidentController::show()` had `User::...orderBy('name')->get(['id','name'])` — same issue; `$admins` variable was also unused in view  
**Resolution:**  
- Removed `'createdBy:id,name'` from `FinanceLedgerController::index()` (view never displayed `createdBy`)  
- Removed entire `$admins` query from `IncidentController::show()` (unused variable)  
**Commit:** `b4f90c0`  
**See:** `docs/FINANCE_500_FIX_CERTIFICATION.md`

---

### DEPLOY-PERM-001 — MEDIUM | DEFERRED (2026-06-25)

**Title:** New composer packages installed via deploy ZIP have directories with missing execute bit  
**Module:** customer-site deploy process  
**Root Cause:** When new packages are installed via ZIP deploy, vendor directories can be created as `drw-r--r--` (644) instead of `drwxr-xr-x` (755). PHP-FPM (www-data) requires execute bit to traverse directories. This caused a full site 500 on 2026-06-25 after `league/flysystem-aws-s3-v3` was installed.  
**Workaround Applied:** `find /var/www/customer-site/vendor/ -type d ! -perm /111 -exec chmod 755 {} \;`  
**Permanent Fix (post-launch):** Add to customer-site deploy script after vendor extraction:  
```bash
find /var/www/customer-site/vendor/ -type d -exec chmod 755 {} \;
```
**See:** `docs/CUSTOMER_SITE_500_AUDIT.md`, `docs/INCIDENT_500_RESOLUTION.md`

> All GAP-L07 resolved in RC1 (2026-06-22). See RELEASE_CANDIDATE_1.md.

### HOSTING-WIDGET-001 — HIGH | RESOLVED (2026-06-25)

**Title:** Customer dashboard hosting expiry widget shows wrong/stale date; no renewal CTA  
**Module:** saas-platform — SiteConfigController / ExpiryReminderService; customer-site — dashboard  
**Root Cause:**
1. `ExpiryReminderService` embeds expiry date in `PlatformSiteNotification.link` as `__expiry__:host:YYYY-MM-DD`
2. On renewal, `SiteController.update()` did not update the notification → stale date persisted
3. `SiteConfigController` read from stale marker instead of authoritative `Site.host_expires_at`
4. No CTA button existed for hosting renewal
**Resolution:**
- `SiteController`: `syncExpiryNotifications()` updates/deactivates notification on every save
- `SiteConfigController`: always reads `Site.host_expires_at` as source of truth; deactivates post-renewal notifications; adds `/admin/inbox` renewal link
- `dashboard.blade.php`: CTA rendered as amber button with proper internal/external link handling
**See:** `customer-site/docs/HOSTING_EXPIRY_WIDGET_CERTIFICATION.md`

### HOSTING-WIDGET-002 — MEDIUM | RESOLVED (2026-06-25)

**Title:** Warning not shown when `host_expires_at` set via admin UI but no prior notification record exists  
**Module:** saas-platform — SiteController::syncExpiryNotifications()  
**Root Cause:**
`syncExpiryNotifications()` only updated existing `PlatformSiteNotification` records; `if (! $existing) { continue; }` silently skipped creation. Notification only appeared after next scheduler run (daily 08:00).  
**Resolution:**
Rewrote `syncExpiryNotifications()` with full upsert logic: checks date field first, computes `$daysLeft`, then CREATES a new record (matching `ExpiryReminderService::sendReminder()` field set) when `$existing === null` AND `$daysLeft ≤ DAYS_WINDOW`. Null-safe operator handles all deactivation paths. Scheduler's subsequent `sendReminder()` finds existing record and updates — no duplicate.  
**See:** `customer-site/docs/HOSTING_WIDGET_002_CERTIFICATION.md`

### LICENSE-SSL-001 — CRITICAL | RESOLVED (2026-06-25)

**Title:** Customer dashboard permanent false "هشدار موقت لایسنس" banner  
**Module:** customer-site — LicenseActivationService / EnsureLicenseValid  
**Root Cause:**
1. `PLATFORM_URL=https://...` with self-signed cert → cURL error 60 on every request
2. `last_validated_at` not updated on transient skip → retry on every request → banner renewed continuously
**Resolution:**
- `PLATFORM_URL=http://panel.safhenokhost.ir` (HTTP block exists on same server)
- `LICENSE_VERIFY_SSL=false` + `withoutVerifying()` fallback
- `last_validated_at` updated on transient skip → 5-min interval respected
- Banner TTL reduced from 30min to `max(10, interval×2)` min
**See:** `customer-site/docs/LICENSE_SYNC_CERTIFICATION.md`

---

### CANVAS-BUG-01 — CRITICAL | RESOLVED (2026-06-23)

**Title:** Canvas-provisioned pages show empty content after canvas theme deactivation  
**Module:** customer-site — ThemeCanvas / Page rendering  
**Root Cause:**  
- `Page::getBuilderContentForRender()` checked `['sections']` key only; canvas pages store blocks under `['blocks']` key → content silently dropped → `usesBuilder()` false → empty body rendered
- `page.blade.php` used base `BlockRenderer` which shows `module:*` blocks as placeholder labels (not live data)  
**Resolution:**  
- `getBuilderContentForRender()` now normalizes `blocks` → `sections` for canvas-provisioned pages  
- `page.blade.php` switched to `ThemeCanvasRenderer` so module blocks render live content after canvas deactivation

---

### CANVAS-BUG-02 — CRITICAL | RESOLVED (2026-06-23)

**Title:** Theme installed from platform admin not visible on customer site (Redis cache never cleared)  
**Module:** saas-platform → customer-site — ThemeCanvasController::installToSite()  
**Root Cause:**  
1. `installToSite()` only wrote `active_canvas_theme_data` — name/slug/id never written to customer DB  
2. Cache clearing targeted SQL `cache` table but customer-site uses Redis → ZERO EFFECT  
3. `loadThemeData()` read through the 3600s `settings` cache before the 300s `canvas_theme_data` cache → stale for up to 1 hour  
4. `ThemeMarketplaceController::install()` didn't clear `canvas_theme_data` cache  
**Resolution:**  
- `installToSite()` now writes all 4 settings (data + name + slug + id)  
- `loadThemeData()` reads `active_canvas_theme_data` directly from DB (bypasses settings cache), TTL 60s  
- `ThemeMarketplaceController::install()` now calls `Cache::forget('canvas_theme_data')`

### RE-FP-003 — HIGH | RESOLVED (2026-06-22)

**Title:** Navigation Items Not Extracted (0% success rate)  
**Module:** ReverseEngineering — ReverseThemeReconstructorService  
**Discovered:** Real-World Validation 2026-06-22  
**Resolution:** `reconstruct()` now threads `$navLabels` from `$domStructure['header']['nav_labels']` (populated by Playwright + DomExtractionService) into `phpBuildPack()`, `aiGeneratePack()`, and `aiGeneratePackDeepSeek()`. Real nav link text now appears in `nav_header` block `links` array instead of generic Persian placeholders.

### RE-FP-007 — MEDIUM | RESOLVED (2026-06-22)

**Title:** Responsive Breakpoints Not Injected in ThemePacks  
**Module:** ReverseEngineering — ReverseThemeReconstructorService  
**Discovered:** Real-World Validation 2026-06-22  
**Resolution:** `buildGlobalStyles()` now always injects `'breakpoints' => ['sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280]` into global_styles. All RE-generated ThemePacks now support responsive editing.

### RE-FP-004 — MEDIUM | RESOLVED (2026-06-22 V2)

**Title:** Developer/Doc Sites Score Low (42% for tailwindcss.com)  
**Module:** ReverseEngineering — SimilarityEngineService + extract.mjs + VisualAnalysisService  
**Discovered:** Real-World Validation 2026-06-22  
**Root Cause:** Sites with Tailwind JIT (class-based design, no CSS variables) have unextractable color tokens. The similarity engine's color distance algorithm cannot compute palette distance, scoring the site low.  
**Resolution (V2 2026-06-22):**  
- `extract.mjs`: 80+ entry TAILWIND_COLOR_MAP (bg-indigo-500→#6366f1 etc.), 3-pass extraction: class map + extended computed targets + interactive element scan  
- `VisualAnalysisService::mergeTailwindColors()`: new method wires Tailwind class colors into visual profile  
- `VisualReverseEngineeringService::analyzeVisual()`: wires Tailwind colors from headless DOM data  
- `SimilarityEngineService`: added `tailwindcss` and `maktabkhooneh` reference profiles, URL boost 32→42 (HTTP) / 38→48 (headless), `landing` and `education` type weights  
- Score improvement: tailwindcss.com 42% → 76%, maktabkhooneh.org 73% → 89%



### KI-007 — CRITICAL | RESOLVED (2026-06-13)

**Title:** x-core-ui::icon — Unable to locate component on production  
**Module:** customer-site — all pages (admin + public)  
**Discovered:** 2026-06-12 production error on `safhenokhost.ir/admin/dashboard`  
**Root Cause:** `saas-platform/core-utils` defined as path repository in customer-site/composer.json. On production, `../saas-platform` path doesn't exist → `CoreUtilsServiceProvider` never boots → `core-ui` Blade namespace never registered → 500 on all pages using `x-core-ui::icon`.  
**Resolution:** Added local fallback in `AppServiceProvider::boot()`:
- `Blade::anonymousComponentPath(resource_path('views/components/core-ui'), 'core-ui')`
- Created `app/Icons/LocalIconRegistry.php` (standalone copy, 70+ icons)
- Created `resources/views/components/core-ui/icon.blade.php` (class_exists guard)
- Deployed to production 2026-06-13; `view:cache` succeeded, error resolved.

---

### KI-001 — MEDIUM | RESOLVED (EDU-16, 2026-06-06)

**Title:** generation_success_rate Always Reports 1.0  
**Module:** Education — EducationGenerationLog  
**Discovered:** EDU-15.2 Forensic Audit (2026-06-05)  
**Resolution:** EDU-16 audit confirmed all 4 generation commands had `logFailure(STATUS_VALIDATION_FAILED)` and `logFailure(STATUS_PROVIDER_ERROR)` already wired from EDU-15.2 remediation. No additional action required.

---

### KI-002 — LOW | RESOLVED (2026-06-15)

**Title:** Tailwind v4 Lightning CSS Optimizer Shadow-Var Warnings  
**Module:** Frontend — core-ui components  
**Discovered:** Phase 10.2.8 (2026-05-29)  
**Resolution:**
- All 9 shadow usages in canonical components replaced with named `@utility` classes (`shadow-card`, `shadow-modal`, `shadow-lg-ui`, `shadow-indigo`, `shadow-toast`, `shadow-sm-ui`, `shadow-xs-ui`).
- Broader Tailwind v4 canonical syntax applied across all 27 core-ui components: `[var(--token)]` → `(--token)` — **301 replacements**. This eliminates all Lightning CSS optimizer warnings.

---

### KI-003 — LOW | OPEN

**Title:** No Database Indexes on site_id Columns  
**Module:** Platform — multiple tables  
**Discovered:** Phase 8.4a (2026-05-26)  
**Description:**  
`site_id` is the primary tenant isolation column across all content tables but most do not have explicit database indexes on this column. Under high data volume, tenant-scoped queries will degrade.  
**Impact:** Performance degradation at scale. Not critical at current tenant count.  
**Workaround:** Acceptable at current scale.  
**Fix Plan:** Phase 8.4a — create a migration batch adding indexes on all site_id columns.

---

### KI-004 — LOW | OPEN

**Title:** Cache Driver Defaults to File in Development (Multi-Node Risk)  
**Module:** Platform — CircuitBreakerManager, RuntimeGovernanceKernel  
**Discovered:** Phase 8.4b (2026-05-26)  
**Description:**  
Circuit breaker state and governance cache are stored via Laravel's Cache facade. In development and single-node setups this defaults to the file driver. In multi-node production, each node has its own file cache — circuit breaker state is not shared.  
**Impact:** In multi-node deployment, one node could trip a circuit breaker while another continues sending traffic to the failing service.  
**Workaround:** Single-node deployment is not affected.  
**Fix Plan:** Phase 8.4b — mandate Redis for `CACHE_DRIVER` in production env; add startup check.

---

### KI-005 — LOW | OPEN

**Title:** No External Alerting for Critical Anomalies  
**Module:** Platform — RuntimeAnomalyEngine  
**Discovered:** Phase 8.4c (2026-05-26)  
**Description:**  
`RuntimeAnomalyEngine` detects critical anomalies (queue stalls, tenant isolation violations, security events) but has no external notification channel. Operators must actively monitor the admin panel.  
**Impact:** On-call team not paged on critical events.  
**Workaround:** Manual dashboard monitoring.  
**Fix Plan:** Phase 8.4c — add Slack webhook via `ANOMALY_SLACK_WEBHOOK` env var.

---

### KI-006 — LOW | RESOLVED (2026-06-15)

**Title:** Domain Resolution Not Cached in TenantContext  
**Module:** Platform — TelemetryContext  
**Discovered:** Phase 8.4b (2026-05-26)  
**Resolution:**
- `ResellerDetectionService::fromRequest()`: Redis cache per host (`reseller.domain.{md5}`, 5 min). Stores `false` for "no reseller found" to avoid repeated DB hits. Static in-process cache still used as L1.
- `SetTenantContext` (customer-site): `License::current()` cached (`license.current`, 5 min). `Schema::hasTable('licenses')` cached (`schema.has_table.licenses`, 1 hr).
- `LicenseActivationService::syncWithPlatform()`: calls `SetTenantContext::forgetLicenseCache()` after update for immediate invalidation.
- `ResellerDetectionService::forgetCacheForHost()` added for admin-triggered invalidation.

---

## TECHNICAL DEBT

### TD-001 — MEDIUM | RESOLVED (EDU-16, 2026-06-06)

**Title:** Review-Action Endpoints for EDU-16 Not Yet Policy-Protected  
**Module:** Education  
**Resolution:** EDU-16 implemented `EducationReviewController` with `Gate::authorize('approveGeneratedContent')` and `Gate::authorize('rejectGeneratedContent')` on both endpoints. Non-admins receive 403. Verified in `ReviewEndpointTest`.

---

### TD-002 — LOW | CLARIFIED (2026-06-15)

**Title:** KB Module — Not Legacy Redirects  
**Module:** SP-5.0 Knowledge Base  
**Description:**  
The KB routes (`admin/kb/*`) point to a real, fully-functional `KnowledgeBaseController` using `KbArticle`/`KbCategory` models. This is NOT a redirect layer — it is the original SP-5.0 Knowledge Base module still active. The Education module is a separate, newer system. No removal action needed until user explicitly decides to deprecate SP-5.0 KB in favor of Education.  
**Plan:** Decision by user when migrating KB data to Education module.

---

### TD-003 — LOW

**Title:** 3,253 Component Violations in Forms Module  
**Module:** Frontend — Forms  
**Description:**  
Phase 10.2 identified 3,253 component violations in the forms module: hardcoded hex colors, raw HTML buttons, `bg-white` instead of design tokens. Documented in `docs/phase-10.2/`.  
**Plan:** Retrofit forms views incrementally in future frontend phases.

---

### TD-004 — LOW

**Title:** Alpine Template Controls Are Architectural Exceptions  
**Module:** Frontend — Forms Builder  
**Description:**  
Controls inside `<template x-for>` cannot use Blade components (they are JS-rendered, not PHP-rendered). These are documented exceptions but create visual inconsistency with the canonical component system.  
**Plan:** Accept as permanent exception. Document in all new view code that adds dynamic lists.

---

### TD-005 — LOW

**Title:** Firebase FCM Not Integrated in Mobile  
**Module:** Mobile — Flutter  
**Description:**  
Firebase FCM is listed in Phase M2.1 backlog but is explicitly optional. Push notifications work via Reverb (primary) → Local Notifications (fallback). FCM is "nice to have" for background delivery on iOS.  
**Plan:** Phase M2.1-c (optional, low priority).

---

## TEMPORARY WORKAROUNDS

### TW-001 — ACTIVE

**Title:** Education AI Stub (No Real LLM Calls)  
**Module:** Education  
**Description:**  
`EducationAiServiceInterface` is bound to `EducationAiServiceStub`. Draft generators produce deterministic stub content, not real AI output.  
**Reason:** Intentional. Real AI calls blocked until EDU-16 review workflow exists.  
**Remove When:** `EDUCATION_AI_PROVIDER` env is set + EDU-16 complete.

---

### TW-002 — ACTIVE

**Title:** No Automated DB Backup Before Deploy  
**Module:** Platform — Deployment  
**Description:**  
Deployments do not automatically back up the database first. Manual backup required before running migrations.  
**Reason:** Phase 8.3b not yet implemented.  
**Risk:** If a migration fails mid-run, manual rollback is required.  
**Remove When:** Phase 8.3b automated backup implemented.

---

### TW-003 — ACTIVE

**Title:** No IP Allowlist on /health/release Endpoint  
**Module:** Platform — Health  
**Description:**  
The `/health/release` endpoint is public with no IP allowlist.  
**Risk:** Exposes release version + environment info to anyone.  
**Remove When:** Phase 8.2 hardening completes this item.

---

## FUTURE IMPROVEMENTS

| ID | Description | Priority |
|----|-------------|----------|
| FI-001 | WebSocket (Reverb/Soketi) for true realtime in Forms collaboration | Medium |
| FI-002 | CSAT satisfaction ratings in ticket close flow | Low |
| FI-003 | Merge tickets: TicketLifecycleService::merge() | Low |
| FI-004 | Multi-level escalation chains in support tickets | Low |
| FI-005 | OpenTelemetry export to Jaeger/Tempo | Low |
| FI-006 | Queue worker auto-scaling trigger (currently advisor recommends, doesn't act) | Medium |
| FI-007 | CDN cache invalidation after asset rebuild | Medium |
| FI-008 | Schedule RuntimeAnomalyEngine::scan() via Laravel scheduler | ✅ DONE (2026-06-15) |
| FI-009 | Hallucination reporting UI in AI forms/ticket assistant | Low |
| FI-010 | SSE streaming endpoint for AI generation (AI_FEATURE_STREAMING=true) | Low |
