# HANDOVER — Education Module
> فایل اختصاصی ماژول Education.
> اگر فقط یک چت باز است: این فایل + HANDOVER.md را بخوان.
> اگر چند چت همزمان باز است: فقط این فایل را بخوان.
> Last Updated: 2026-06-06

---

## وضعیت فعلی

**فاز:** EDU-17 — Live AI Provider Integration  
**وضعیت:** BLOCKED (نیاز به تصمیم `EDUCATION_AI_PROVIDER` env)  
**پیش‌نیاز:** EDU-16 ✅ CERTIFIED COMPLETE (2026-06-06)

---

## آخرین کار انجام‌شده — EDU-16 (2026-06-06)

Human Review & Approval Workflow به‌صورت کامل پیاده‌سازی شد:

| بخش | وضعیت |
|-----|--------|
| State machine ساده‌سازی‌شده: `draft → published \| archived` | ✅ |
| `pending_review` به‌طور کامل حذف شد | ✅ |
| Migration: `reviewed_by_id`, `reviewed_at`, `review_note` + composite index | ✅ |
| `POST .../approve` + `POST .../reject` با Gate + policy checks | ✅ |
| `EducationDraftReviewService` bound via `EducationDraftReviewServiceInterface` | ✅ |
| Admin review dashboard (RTL، Vazirmatn، صف FIFO، Alpine inline) | ✅ |
| 30 تست جدید (DraftReviewServiceTest + ReviewEndpointTest) | ✅ |
| KI-001: تأیید شد — همه ۴ command از EDU-15.2 logFailure() داشتند | ✅ |

**Acceptance Criteria — همه تکمیل:**

- [x] `draft → published (approve) | archived (reject)`
- [x] Approved articles visible via sync API
- [x] Rejected articles never synced
- [x] Non-admin → 403 روی تمام review endpoints
- [x] `EducationGenerationLog` failure writes کار می‌کنند
- [x] `education:validate` ≥ 95/100

---

## EDU-17 — چه باید ساخته شود

### پیش‌نیاز: تصمیم `EDUCATION_AI_PROVIDER`

مقدار باید یکی از این‌ها باشد: `claude` | `openai` | `null` (stub باقی بماند)

### Task List

| # | Task | Status |
|---|------|--------|
| 1 | Implement real AI provider adapter (Claude یا OpenAI) در `EducationAiContentGenerationService` | Pending |
| 2 | Wire `EDUCATION_AI_PROVIDER` env به `EducationServiceProvider` binding | Pending |
| 3 | اتصال به `AiProviderManager` (Phase 9.5-A) — circuit breaker + fallback | Pending |
| 4 | `TenantAiQuotaManager` integration — rate limits per site | Pending |
| 5 | Tests: mocked HTTP round-trip (بدون live API call در CI) | Pending |

### فایل‌های کلیدی برای EDU-17

```
packages/saas-platform/modules/education/
├── src/
│   ├── Contracts/Services/EducationAiContentGenerationServiceInterface.php  ← update
│   ├── Services/Education/EducationAiContentGenerationService.php           ← implement real provider
│   └── Providers/EducationServiceProvider.php                               ← wire env
config/ai.php                                                                 ← existing AiProviderManager
```

---

## مسیرهای کلیدی

```
packages/saas-platform/modules/education/
├── src/
│   ├── Console/Commands/          ← GenerateArticlesCommand, GenerateWalkthroughsCommand, ...
│   ├── Contracts/Services/        ← interfaces
│   ├── Generation/                ← ArticleDraftGenerator (فقط DTO، بدون DB write)
│   ├── Http/Controllers/Api/      ← EducationGeneratedContentController, EducationReviewController
│   ├── Http/Controllers/Admin/    ← EducationGeneratedContentReviewController
│   ├── Models/                    ← EducationArticle, EducationGenerationLog
│   ├── Policies/                  ← EducationArticlePolicy
│   └── Services/Education/        ← EducationDraftPersistenceService, EducationDraftReviewService
├── Database/Migrations/
├── Routes/api.php
└── Routes/admin.php
```

---

## قوانین حیاتی این ماژول

1. **Generator‌ها فقط DTO برمی‌گردانند** — هیچ DB write در generators ممنوع (ADR-001)
2. **Persistence = مسئولیت Command** — نه Service
3. **`whereNull('site_id')`** برای platform admin context — نه `where('site_id', null)` (ADR-004)
4. **withTenantSite()** در تمام security tests (ADR-007)
5. **دو لایه dedup** در persist(): lockForUpdate + QueryException(1062) catch (ADR-005)
6. **AI = stub** تا `EDUCATION_AI_PROVIDER` env تنظیم شود (ADR-011)
7. **State machine:** `draft → published | archived` — بدون pending_review (ADR-013)

---

## API Endpoints فعال

```
GET  /api/v1/education/articles              — mobile articles list
GET  /api/v1/education/articles/{id}         — single article
GET  /api/v1/education/search                — full-text search
GET  /api/v1/education/learning-paths        — learning paths
POST /api/v1/education/articles/{id}/progress — mark progress
GET  /api/v1/education/profile               — learning profile + gaps + AI-scored recs
GET  /api/v1/education/analytics             — combined snapshot
GET  /api/v1/education/generated-content     — draft overview (admin only)
POST /api/v1/education/generated-content/{id}/approve — approve draft (admin only)
POST /api/v1/education/generated-content/{id}/reject  — reject draft (admin only)
GET|POST /api/v1/education/sync/{siteId}/... — customer site sync
```

---

## بعد از EDU-17 چه می‌آید

بعد از EDU-17، education module به بلوغ کامل می‌رسد. فازهای ممکن:

- **EDU-18** — Advanced analytics dashboard (student progress, content effectiveness)
- **EDU-19** — Multi-language content support
- یا ادامه infrastructure backlog (Phase 8.x)
