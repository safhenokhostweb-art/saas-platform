# HANDOVER — Education Module
> فایل اختصاصی ماژول Education.
> اگر فقط یک چت باز است: این فایل + HANDOVER.md را بخوان.
> اگر چند چت همزمان باز است: فقط این فایل را بخوان.
> Last Updated: 2026-06-07

---

## وضعیت فعلی

**فاز:** EDU-17 — Live AI Provider Integration  
**وضعیت:** ✅ CERTIFIED COMPLETE (2026-06-07)  
**پیش‌نیاز:** EDU-16 ✅ CERTIFIED COMPLETE (2026-06-06)  
**ماژول Education:** به بلوغ کامل رسیده است.  
**توصیه بعدی:** SUBSCRIPTION_CHECKOUT_PAYMENT

---

## آخرین کار انجام‌شده — EDU-17 (2026-06-07)

### EDU-17 — CERTIFIED COMPLETE

AI provider layer به‌صورت کامل پیاده‌سازی شد:

| بخش | وضعیت |
|-----|--------|
| `ClaudeAiContentProvider` — real Claude API HTTP adapter | ✅ |
| `CircuitBreakerAiContentProvider` — CLOSED/OPEN/HALF_OPEN state machine | ✅ |
| `PersianEducationPromptBuilder` — Persian-language prompt construction | ✅ |
| `EDUCATION_AI_PROVIDER` env wired to `EducationServiceProvider` binding | ✅ |
| `TenantAiQuotaManager` integration — quota check before circuit | ✅ |
| 57 tests (0 failed, 77 assertions) | ✅ |
| `education:validate` score: 100/100 | ✅ |

**Commit Evidence:**

| Phase | Commit |
|-------|--------|
| Phase 2 — Circuit Breaker | `da0cbbc` |
| Phase 3 — Persian Prompt Builder | `da0cbbc` |
| Phase 4 — TenantAiQuota + Integration | `18e218c` |
| Phase 5 — Tests | `18e218c` |

**Technical Debt — M-01 (LOW):**  
`education:validate` does not yet include EDU-17-specific checks (ClaudeAiContentProvider, CircuitBreaker config, PersianEducationPromptBuilder registration, EDU-17 test presence). Add in a future maintenance pass. EDU-17 remains CLOSED.

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

## EDU-17 — COMPLETE (2026-06-07)

تمام ۵ task کامل شده‌اند:

| # | Task | Status |
|---|------|--------|
| 1 | Claude + OpenAI provider adapters | ✅ (prev session) |
| 2 | EDUCATION_AI_PROVIDER env wiring | ✅ (prev session) |
| 3 | CircuitBreakerAiContentProvider | ✅ (prev session) |
| 4 | TenantAiQuotaManager integration | ✅ 2026-06-07 |
| 5 | Mocked HTTP tests (46 pass) | ✅ 2026-06-07 |

**فعال‌سازی:**
```env
EDUCATION_AI_PROVIDER=claude
EDUCATION_CLAUDE_API_KEY=sk-ant-...
EDUCATION_AI_QUOTA_ENABLED=true   # اختیاری — quota enforcement
EDUCATION_AI_QUOTA_DEFAULT_DAILY=50
```

---

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
