# Education Module — Project State
**Last updated:** 2026-06-06
**Module version:** EDU-15.2 (hardened) + Finding 001 fix

---

## Current Phase: EDU-15.2 Complete — EDU-16 APPROVED

All EDU-15, EDU-15.1, EDU-15.2, and hardening work is done.
Finding 001 (migration SQLite incompatibility) resolved 2026-06-06.
EDU-16 is now approved to begin.

---

## Phase History

| Phase | Status | Notes |
|---|---|---|
| EDU-1 through EDU-13 | COMPLETE | KB migration, article/walkthrough/path management |
| EDU-13.5 → 13.7 | COMPLETE | Hardening; markCompleted() requires site_id |
| EDU-14A | COMPLETE | AI Engine Foundation: learning profiles, gap detection, scoring |
| EDU-14B | COMPLETE | Intelligence Orchestration: queues, analytics, 3 commands |
| EDU-15 | COMPLETE | AI Content Generation: providers, validators, draft persistence |
| EDU-15.1 Remediation | COMPLETE | F-02 failure logging, RC-01 concurrency, S-02 authorization |
| EDU-15.2 Hardening | COMPLETE | aggregate-path comment, EDU152AggregatePathTest |
| Finding 001 Fix | COMPLETE | Migration 000018 SQLite compatibility |
| **EDU-16** | **NOT STARTED** | Human review workflow (approve/reject/assign) |

---

## Active Architecture

### AI Content Generation Stack

```
ContentDeficitQueueService  ──┐
KnowledgeGapQueueService    ──┤──▶ Commands ──▶ executeJob() ──▶ persist()
                               │                     │               │
                               │            AiContentProvider    DB::transaction
                               │            GeneratedContentValidator
                               │
ArticleDraftGenerator ──┐
WalkthroughDraftGenerator──┤──▶ education:generate-articles / walkthroughs
LearningPathDraftGenerator─┘
```

### API Endpoints (EDU-15)

| Method | Path | Auth | Policy |
|---|---|---|---|
| GET | `/api/v1/education/generated-content` | sanctum | `is_admin` |
| GET | `/api/v1/education/analytics` | sanctum | none |
| GET | `/api/v1/education/profile` | sanctum | none |

### Commands

| Command | Purpose |
|---|---|
| `education:generate-content` | All deficit types, `executeJob()` directly |
| `education:generate-gap-content` | Gap remediation, `executeJob()` directly |
| `education:generate-articles` | Articles only via `ArticleDraftGenerator` |
| `education:generate-walkthroughs` | Walkthroughs only via `WalkthroughDraftGenerator` |

### Database Tables (EDU-15 additions)

| Table | Purpose |
|---|---|
| `edu_generation_logs` | One row per generation attempt (success or failure) |
| (index) `edu_articles_ai_draft_unique` | RC-01 duplicate prevention |
| (index) `edu_articles_ai_draft_lookup` | lockForUpdate() gap-lock support |

---

## EDU-16 Prerequisites (already in place)

- `STATUS_DRAFT`, `STATUS_PUBLISHED`, `STATUS_ARCHIVED` — all three constants exist
- `EducationArticleService::publish()` and `::archive()` — already implemented
- `GET /api/v1/education/generated-content` — draft queue visible to admins
- `generationAnalytics()` — automatically reflects approvals/rejections by status

## EDU-16 Prerequisites (to be created in EDU-16)

- Migration: add `reviewed_at`, `reviewed_by_id`, `review_note` to `edu_articles`
- Policy method: `reviewDraft()` on `EducationArticlePolicy` with site-scoping
- New API endpoints: POST approve, POST reject, GET review queue

---

## Known Open Items (non-blocking)

| ID | Severity | Description |
|---|---|---|
| F004 | Medium | `generationAnalytics()` provider_usage counts all statuses; controller counts success-only |
| F005 | Low | `auth()->id()` in model `updated` hook returns null in CLI context |
| F006 | Low | `edu_generation_logs.edu_article_id` has no FK (intentional, requires null-check in EDU-16 views) |
| F008 | Low | `scopePublished()` exists but is unused in analytics/controller queries |
