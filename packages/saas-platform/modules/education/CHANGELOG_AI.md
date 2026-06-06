# Education Module — AI-Assisted Development Changelog

Entries in reverse chronological order. Each entry records what changed,
why it changed, and the invariants it enforces or introduced.

---

## 2026-06-06 — Finding 001: Migration 000018 SQLite Fix

**Change:** Replaced MySQL-only `DB::statement('ALTER TABLE ... MODIFY COLUMN ...')`
with `Schema::table()->change()` in migration
`2026_06_03_000018_make_edu_category_id_nullable_in_edu_articles.php`.

**Why it was broken:** The original author used raw DDL to avoid a Laravel <10 bug where
`Schema::change()` required Doctrine DBAL which mishandled foreign-key columns. In
Laravel 10+, `change()` is implemented natively with no DBAL dependency. The raw DDL
workaround became MySQL-only and was never updated.

**Why it broke tests:** Orchestra Testbench defaults to SQLite in-memory. SQLite's
`ALTER TABLE` does not support `MODIFY COLUMN`. When `RefreshDatabase` ran migration 000018,
it threw `SQLSTATE[HY000]: General error: 1 near 'MODIFY': syntax error`, killing the
entire migration cycle and preventing every module test from running.

**Fix:** `Schema::change()` in Laravel 12 generates `MODIFY COLUMN` on MySQL/MariaDB and a
table-rebuild (CREATE → copy → DROP → RENAME) on SQLite. Both preserve FK constraints
and column indexes. The original FK to `edu_categories` is preserved on both drivers.

**Invariants preserved:**
- `edu_category_id` remains nullable after this migration on all drivers
- FK constraint to `edu_categories` is preserved
- `down()` restores NOT NULL constraint correctly on MySQL/MariaDB
- All module tests can now run on SQLite (RefreshDatabase succeeds)

**Test added:** `Migration000018CompatibilityTest.php` — 5 assertions proving:
1. RefreshDatabase completes without error
2. Column is nullable in schema
3. Auto-generated articles can be created without a category
4. Normal articles with null category persist correctly
5. `down()` method is present and callable

---

## 2026-06-05 — EDU-15.2 Hardening: Aggregate Path Documentation

**Change:** Added hardening comment to `EducationAiContentGenerationService::executeJobs()`.
Created `EDU152AggregatePathTest.php` (7 tests documenting the F-02 analytics gap for this path).

**Why:** `executeJobs()` (called by `generateFromDeficits()`/`generateFromGaps()`) silently
swallows exceptions without writing `EducationGenerationLog` failure entries. This is correct
by architectural contract (service MUST NOT save to DB), but a future developer routing
persistence through this path would silently break F-02 analytics accuracy.

**Invariant:** Tests assert `edu_generation_logs` count = 0 after failures through the
aggregate path. If someone adds `logFailure()` to `executeJobs()`, the tests will fail
explicitly, forcing documentation and interface updates.

---

## 2026-06-05 — EDU-15.2 Remediation: F-02, RC-01, S-02

### F-02: Generation Analytics Accuracy

**Change:** Added `providerName(): string` to `EducationAiContentGenerationServiceInterface`
and all four commands. Added `logFailure()` to `EducationDraftPersistenceServiceInterface`
and implementation. All four commands call `logFailure()` with correct status in typed
catch blocks.

**Invariant:** Every `InvalidGeneratedContentException` → `STATUS_VALIDATION_FAILED` row.
Every `AiProviderException` → `STATUS_PROVIDER_ERROR` row. `generation_success_rate` is
accurate.

### RC-01: Duplicate Draft Race Condition

**Change:** `EducationDraftPersistenceService::persist()` now has two-layer duplicate
protection: (1) `lockForUpdate()` re-check inside `DB::transaction()`, (2) `QueryException`
1062 catch for MySQL. Migration `2026_06_05_000002` adds unique index with driver branching
(MySQL prefix 191, SQLite full column).

**Invariant:** Two concurrent scheduler executions cannot create duplicate auto-generated
drafts for any (title, module, site) combination.

### S-02: Authorization

**Change:** Added `viewGeneratedContent($user): bool` to `EducationArticlePolicy`.
Controller calls `Gate::authorize('viewGeneratedContent', EducationArticle::class)`.

**Invariant:** Only `is_admin = true` users can access draft metrics. All others receive 403.

---

## 2026-06-05 — EDU-15: AI Content Generation Engine

**Changes:** AiContentProviderInterface, NullAiContentProvider (default), ClaudeAiContentProvider
(stub), OpenAiContentProvider (stub), ArticleDraftGenerator, WalkthroughDraftGenerator,
LearningPathDraftGenerator, EducationAiContentGenerationService, GeneratedContentValidator,
EducationDraftPersistenceService, EducationGenerationLog model + migration, 4 commands,
GET /api/v1/education/generated-content, generationAnalytics() extension, 13 test files.

**Key invariant:** Status is ALWAYS 'draft' on persist — auto-publish is architecturally
impossible.

---

## 2026-06-04/05 — EDU-14A/14B: Intelligence Foundation & Orchestration

**Changes:** UserLearningProfileService, KnowledgeGapService, AIRecommendationScoreService,
ContentReadinessService, RecommendationRefreshService, KnowledgeGapQueueService,
ContentDeficitQueueService, EducationAnalyticsService, 3 commands, GET /api/v1/education/analytics.

---

## 2026-06-03 — EDU-1 through EDU-13: Core Module

Articles, walkthroughs, learning paths, categories, media, release notes, bookmarks,
user progress, sync infrastructure, feature discovery, recommendations, AI stubs.
