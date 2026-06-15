# KNOWN ISSUES
> Known bugs, technical debt, temporary workarounds, and future improvements.
> Severity: CRITICAL | HIGH | MEDIUM | LOW
> Status: OPEN | WORKAROUND | DEFERRED | RESOLVED
> Last Updated: 2026-06-06

---

## ACTIVE BUGS

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
