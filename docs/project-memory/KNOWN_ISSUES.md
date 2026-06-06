# KNOWN ISSUES
> Known bugs, technical debt, temporary workarounds, and future improvements.
> Severity: CRITICAL | HIGH | MEDIUM | LOW
> Status: OPEN | WORKAROUND | DEFERRED | RESOLVED
> Last Updated: 2026-06-06

---

## ACTIVE BUGS

### KI-001 — MEDIUM | RESOLVED (EDU-16, 2026-06-06)

**Title:** generation_success_rate Always Reports 1.0  
**Module:** Education — EducationGenerationLog  
**Discovered:** EDU-15.2 Forensic Audit (2026-06-05)  
**Resolution:** EDU-16 audit confirmed all 4 generation commands had `logFailure(STATUS_VALIDATION_FAILED)` and `logFailure(STATUS_PROVIDER_ERROR)` already wired from EDU-15.2 remediation. No additional action required.

---

### KI-002 — LOW | OPEN

**Title:** Tailwind v4 Lightning CSS Optimizer Shadow-Var Warnings  
**Module:** Frontend — resources/css/app.css  
**Discovered:** Phase 10.2.8 (2026-05-29)  
**Description:**  
`shadow-[var(--ui-shadow-*)]` arbitrary value syntax produces optimizer notices from Lightning CSS on every build. Build still exits 0 and CSS is correct.  
**Impact:** Cosmetic only. Clutters `npm run build` output.  
**Workaround:** Ignore warnings. Build is functional.  
**Fix Plan:** Replace arbitrary shadow values with native `@theme` shadow tokens in next frontend phase.

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

### KI-006 — LOW | OPEN

**Title:** Domain Resolution Not Cached in TenantContext  
**Module:** Platform — TelemetryContext  
**Discovered:** Phase 8.4b (2026-05-26)  
**Description:**  
Every request resolves the `Reseller` from the domain via a database query. Under high traffic this creates N unnecessary DB reads per second.  
**Impact:** Extra DB load at high request rates.  
**Workaround:** Acceptable at current traffic.  
**Fix Plan:** Phase 8.4b — add 5-minute cache on domain → Reseller resolution.

---

## TECHNICAL DEBT

### TD-001 — MEDIUM | RESOLVED (EDU-16, 2026-06-06)

**Title:** Review-Action Endpoints for EDU-16 Not Yet Policy-Protected  
**Module:** Education  
**Resolution:** EDU-16 implemented `EducationReviewController` with `Gate::authorize('approveGeneratedContent')` and `Gate::authorize('rejectGeneratedContent')` on both endpoints. Non-admins receive 403. Verified in `ReviewEndpointTest`.

---

### TD-002 — LOW

**Title:** Legacy KB Routes Still Exist  
**Module:** Education  
**Description:**  
`admin/education/kb/*` routes redirect to `edu_*` equivalents. These are legacy redirects from the KB → Education migration. They add routing overhead and could confuse developers.  
**Plan:** Remove after confirming no external links reference them.

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
| FI-008 | Schedule RuntimeAnomalyEngine::scan() via Laravel scheduler | High |
| FI-009 | Hallucination reporting UI in AI forms/ticket assistant | Low |
| FI-010 | SSE streaming endpoint for AI generation (AI_FEATURE_STREAMING=true) | Low |
