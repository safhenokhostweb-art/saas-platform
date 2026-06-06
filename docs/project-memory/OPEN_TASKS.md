# OPEN TASKS
> Current epics, features, tasks, status, dependencies, and acceptance criteria.
> Last Updated: 2026-06-06

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

## CURRENT EPIC: EDU-17 — Live AI Provider Integration

**Status:** BLOCKED (depends on EDU-16 ✅ + EDUCATION_AI_PROVIDER env decision)  
**Depends On:** EDU-16 ✅ + `EDUCATION_AI_PROVIDER` env decision

### Task List

| # | Task | Status |
|---|------|--------|
| 1 | Implement real AI provider adapter (Claude or OpenAI) for education | Pending |
| 2 | Wire `EDUCATION_AI_PROVIDER` env to `EducationServiceProvider` binding | Pending |
| 3 | Connect to existing `AiProviderManager` (Phase 9.5-A) for circuit breaker + fallback | Pending |
| 4 | Set rate limits: `TenantAiQuotaManager` integration | Pending |
| 5 | Test real generation round-trip with mocked HTTP (no live API calls in CI) | Pending |

---

## INFRASTRUCTURE BACKLOG

### Phase 8.x Remaining

| ID | Task | Priority |
|----|------|----------|
| 8.3a | Private Satis/Packagist for package distribution | Medium |
| 8.3b | Automated DB backup before deploy | High |
| 8.3c | GitHub Actions / Envoyer CI pipeline | High |
| 8.3d | Slack webhook alerting for deployment/anomaly events | Medium |
| 8.4a | DB indexes on site_id columns (all tables) | High |
| 8.4b | Cache for domain resolution in TenantContext | Medium |
| 8.4c | External alerting: critical anomaly → Slack | Medium |
| 8.4d | OpenTelemetry export to Jaeger/Tempo | Low |

---

## MOBILE BACKLOG

| ID | Task | Priority |
|----|------|----------|
| M2.1-a | File attachment upload flow | Medium |
| M2.1-b | iOS code signing pipeline | High |
| M2.1-c | Firebase FCM injection (optional, not required) | Low |
| M2.1-d | Deep-link routing | Medium |
| M2.1-e | AppImage packaging (Linux) | Low |
| M2.1-f | System tray icon assets | Low |

---

## FRONTEND BACKLOG

| ID | Task | Priority |
|----|------|----------|
| FE-01 | Wire x-core-ui::navigation-item/sidebar-group into layout files | Medium |
| FE-02 | Retrofit admin views to x-core-ui::button | Low |
| FE-03 | Resolve Tailwind v4 shadow-var optimizer warnings | Low |
| FE-04 | Remaining raw controls in forms/analytics, forms/create, forms/submissions | Low |
| FE-05 | Retrofit tickets/show to x-core-ui::button | Low |

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
