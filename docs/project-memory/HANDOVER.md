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

## CURRENT STATE (2026-06-06)

**Last completed phase:** EDU-16 — Human Review & Approval Workflow  
**Certification status:** EDU-16 COMPLETE  
**Next phase:** EDU-17 — Live AI Provider Integration  
**Next phase status:** BLOCKED (requires EDUCATION_AI_PROVIDER env decision)

---

## CURRENT SPRINT

### EDU-17: Live AI Provider Integration

EDU-16 is complete. The human review gate is live. EDU-17 wires the real AI provider to replace the NullAiContentProvider stub.

**What needs to be built:**

1. Implement real AI provider adapter (Claude or OpenAI) in `EducationAiContentGenerationService`
2. Wire `EDUCATION_AI_PROVIDER` env to `EducationServiceProvider` provider selection
3. Connect to existing `AiProviderManager` (Phase 9.5-A) — circuit breaker + fallback
4. `TenantAiQuotaManager` integration — rate limits per site
5. Tests: mocked HTTP round-trip (no live API in CI)

**EDU-16 completed (2026-06-06):**
- State machine: `draft → published (approve) | archived (reject)` — `pending_review` eliminated
- Migration: `reviewed_by_id`, `reviewed_at`, `review_note` columns + composite index
- API: `POST .../approve` + `POST .../reject` with Gate checks + 422 on bad state
- Admin dashboard: RTL, Vazirmatn, FIFO queue, inline Alpine approve/reject
- 30 new tests (DraftReviewServiceTest + ReviewEndpointTest)
- KI-001: confirmed already resolved in EDU-15.2

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

### Immediate (EDU-17 — BLOCKED on env decision)
- [ ] Decide `EDUCATION_AI_PROVIDER` env value: `claude` | `openai` | keep stub
- [ ] Implement real AI provider adapter in `EducationAiContentGenerationService`
- [ ] Wire `EDUCATION_AI_PROVIDER` env to `EducationServiceProvider` binding
- [ ] Connect to `AiProviderManager` (Phase 9.5-A) — circuit breaker + fallback
- [ ] `TenantAiQuotaManager` integration — rate limits per site
- [ ] Tests: mocked HTTP round-trip (no live API in CI)

### Infrastructure Backlog
- Phase 8.3b: Automated DB backup before deploy
- Phase 8.4a: DB indexes on site_id columns
- Phase 8.4c: Slack webhook for critical anomalies
- Schedule `RuntimeAnomalyEngine::scan()` via Laravel scheduler

---

## NEXT ACTION

**EDU-16 is COMPLETE.** Next is **EDU-17** (blocked on `EDUCATION_AI_PROVIDER` env decision).

Run this to verify current health:
```bash
cd c:\laragon\www\saas-platform
php artisan education:validate --json
```
Score should be ≥ 95/100. If lower, check AUDIT_HISTORY.md for any regressions.

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
