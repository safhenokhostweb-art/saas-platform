# Education Module — Handover Document
**Date:** 2026-06-06
**Delivered by:** Lead Architect (AI-assisted)
**Status:** EDU-15.2 COMPLETE — EDU-16 APPROVED TO BEGIN

---

## What Was Built (EDU-15 through EDU-15.2)

The Education module now has a complete AI content generation pipeline:

1. **Detection** (EDU-14A/14B): Identifies content gaps and deficits per module per site.
2. **Generation** (EDU-15): Calls an AI provider, validates output, persists as draft.
3. **Review queue** (EDU-15 API): Admins can see pending drafts, counts, recent items.
4. **Analytics** (EDU-15.2): Accurate success/failure rates per provider.
5. **Concurrency safety** (EDU-15.2): No duplicate drafts possible under concurrent runs.
6. **Authorization** (EDU-15.2): Only admins can access generation metrics.

---

## What EDU-16 Needs to Build

EDU-16 is the **human review workflow**:

```
auto_generated draft (status=draft)
    │
    ├─▶ APPROVE ──▶ status=published, reviewed_at=now, reviewed_by_id=user.id
    │
    └─▶ REJECT  ──▶ status=archived,  reviewed_at=now, reviewed_by_id=user.id, review_note=...
```

### Required EDU-16 deliverables

**1. Migration** (create at start of EDU-16):
```php
Schema::table('edu_articles', function (Blueprint $table) {
    $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('author_id');
    $table->timestamp('reviewed_at')->nullable()->after('published_at');
    $table->string('review_note', 1000)->nullable()->after('reviewed_at');
    $table->index(['auto_generated', 'reviewed_at'], 'edu_articles_review_idx');
});
```

**2. Policy method** on `EducationArticlePolicy`:
```php
public function reviewDraft($user, EducationArticle $article): bool
{
    if (! $user->is_admin) {
        return false;
    }
    // Platform admin can review any draft.
    if (! isset($user->site_id)) {
        return true;
    }
    // Site admin can only review their own site's drafts.
    return $article->site_id === null || $article->site_id === $user->site_id;
}
```

**3. API endpoints** (new controller `EducationDraftReviewController`):
- `GET /api/v1/education/drafts` — paginated draft queue for review
- `POST /api/v1/education/drafts/{id}/approve` — publish + record reviewer
- `POST /api/v1/education/drafts/{id}/reject` — archive + record reviewer + note

**4. No new service required.** `EducationArticleService::publish()` and `::archive()`
already exist. The review controller calls them after policy check.

---

## Key Invariants to Preserve in EDU-16

| Invariant | Where enforced | What breaks it |
|---|---|---|
| Drafts are always `auto_generated=true, status=draft` | `EducationDraftPersistenceService::persist()` | Do not change default status |
| Approving a draft = changing status to `published` | `EducationArticleService::publish()` | Do not invent a new status |
| Rejecting = `archived` | `EducationArticleService::archive()` | Do not use `deleted` for rejection |
| Analytics update automatically | `generationAnalytics()` counts by status | Do not bypass `status` field |
| Reviewer identity captured | Add `reviewed_by_id` in EDU-16 migration | Do not use `meta` JSON for reviewer |
| Site isolation on review endpoint | Use narrow scope (not `scopeForSite`) | `scopeForSite()` shows global+site articles |

---

## Architecture Reference

```
packages/saas-platform/modules/education/
├── src/
│   ├── Ai/Providers/          ← NullAiContentProvider, ClaudeAiContentProvider, OpenAiContentProvider
│   ├── Contracts/
│   │   ├── Ai/                ← AiContentProviderInterface
│   │   └── Services/          ← all service contracts
│   ├── Generation/            ← ArticleDraftGenerator, WalkthroughDraftGenerator, LearningPathDraftGenerator
│   ├── Services/Education/    ← all EDU-14/15 services
│   ├── Console/Commands/      ← 4 generation commands + older commands
│   ├── Http/Controllers/Api/  ← MobileEducationController, EducationGeneratedContentController, ...
│   ├── Models/                ← EducationArticle, EducationGenerationLog, ...
│   └── Policies/              ← EducationArticlePolicy (update: add reviewDraft here for EDU-16)
├── Database/Migrations/       ← 22 migrations (latest: 2026_06_05_000002)
├── tests/Feature/             ← 16 test files
├── CHANGELOG_AI.md            ← full change history
├── PROJECT_STATE.md           ← current state + open items
└── HANDOVER.md                ← this file
```

---

## Database State (post-EDU-15.2)

```
edu_articles
  ├── edu_category_id     BIGINT UNSIGNED NULL (nullable since migration 000018)
  ├── status              VARCHAR(20) DEFAULT 'draft'
  ├── auto_generated      BOOLEAN DEFAULT false
  ├── module_slug         VARCHAR(60) NULL
  └── [UNIQUE INDEX]      edu_articles_ai_draft_unique (auto_generated, module_slug, site_id, title(191))

edu_generation_logs
  ├── status              VARCHAR(30) — 'success' | 'validation_failed' | 'provider_error'
  ├── edu_article_id      BIGINT UNSIGNED NULL (no FK — intentional, log preservation)
  └── [INDEXES]           (site_id, status), (site_id, provider), (created_at)
```

---

## AI Provider Configuration

Set `EDUCATION_AI_PROVIDER` in `.env`:

| Value | Provider | Additional env required |
|---|---|---|
| `null` (default) | NullAiContentProvider — no calls | — |
| `claude` | ClaudeAiContentProvider (stub — wire SDK) | `EDUCATION_CLAUDE_API_KEY` |
| `openai` | OpenAiContentProvider (stub — wire SDK) | `EDUCATION_OPENAI_API_KEY` |

To wire a real provider: implement `generate()` in `ClaudeAiContentProvider` or
`OpenAiContentProvider`. No other code changes required — the provider is injected
via the ServiceProvider's factory binding.

---

## Test Suite (16 files)

| File | Covers |
|---|---|
| `Migration000018CompatibilityTest` | **Finding 001** — SQLite migration fix |
| `AiContentProviderTest` | Provider abstraction layer |
| `ContentGenerationDtoTest` | DTO contracts |
| `GeneratedContentValidatorTest` | Validation rules |
| `EducationAiContentGenerationServiceTest` | Service orchestration |
| `EducationDraftPersistenceTest` | Draft persistence |
| `GenerateContentCommandTest` | `education:generate-content` |
| `GenerateGapContentCommandTest` | `education:generate-gap-content` |
| `GenerationAnalyticsTest` | Analytics accuracy |
| `EDU15TenantIsolationTest` | Tenant scoping |
| `EDU15RetryAndIdempotencyTest` | Idempotency guards |
| `DraftGeneratorTest` | Generator pipeline classes |
| `GeneratedContentApiTest` | API endpoint |
| `GeneratedContentSecurityTest` | Cross-tenant security |
| `EDU152RemediationTest` | F-02 + RC-01 + S-02 |
| `EDU152AggregatePathTest` | Aggregate path analytics gap guard |
