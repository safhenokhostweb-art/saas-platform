# AUDIT HISTORY
> All findings recorded permanently. Resolved findings marked Fixed — never deleted.
> Format: Finding ID | Severity | Status | Evidence | Resolution
> Last Updated: 2026-06-06 (memory sync)

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
