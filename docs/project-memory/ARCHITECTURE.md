# ARCHITECTURE
> Record of module structure, service boundaries, interfaces, and dependency flow.
> Last Updated: 2026-06-05

---

## Platform Overview

**Type:** Multi-tenant SaaS platform  
**Model:** Single Laravel install serving multiple customer sites via domain resolution  
**Isolation unit:** `site_id` — every tenant row is scoped by `site_id`  
**Admin context:** Platform admin bypasses tenant scope via `withoutTenantIsolation()`

### Two Applications

| Application | Repo | Purpose |
|-------------|------|---------|
| `saas-platform` | `c:\laragon\www\saas-platform` | Platform admin, API, package host |
| `customer-site` | `c:\laragon\www\customer-site` | Per-tenant web UI, served from domain |

---

## Package Architecture

Three local Composer packages (all `@dev` path repositories):

```
packages/
  saas-platform/
    core-utils/          # Shared design system, Blade components, base services
    modules/
      education/         # Enterprise Education Module (current active development)
    modules-tickets/     # Support tickets module
    modules-forms/       # Form builder module
```

**Namespace convention:** `SaaSPlatform\Modules\{ModuleName}`  
**Rule:** No `App\` imports allowed inside any package. Config-driven references only (`config('module.model_class')`).

---

## Tenant Isolation Flow

```
HTTP Request
    │
    ▼
TelemetryContext (singleton)
    │   resolves Reseller from domain
    ▼
AuthenticateTenantApi (middleware)
    │   validates: Authorization: Bearer <license_key> + domain
    │   attaches: tenant_site (Site model) to request attributes
    ▼
SiteTenantScope (Eloquent global scope)
    │   auto-scopes all queries to site_id
    ▼
Controller / Service
```

**Cross-tenant detection:** `TenantBoundaryViolationTelemetry` logs any violation. Model-level boot guards on Platform models throw `InvalidArgumentException` for forbidden participant types.

---

## Education Module Structure

```
packages/saas-platform/modules/education/
├── Database/
│   └── Migrations/          # 15+ migrations (edu_* tables)
├── Routes/
│   ├── admin.php            # 34 admin routes
│   └── api.php              # Mobile + sync API routes
├── src/
│   ├── Console/Commands/    # 18 Artisan commands
│   ├── Contracts/
│   │   └── Services/        # All service interfaces
│   ├── Dto/                 # Data Transfer Objects
│   ├── Generation/          # Draft generators (EDU-15)
│   │   ├── ArticleDraftGenerator.php
│   │   ├── WalkthroughDraftGenerator.php
│   │   └── LearningPathDraftGenerator.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/       # Admin panel controllers
│   │   │   └── Api/         # API controllers
│   │   └── Resources/       # Laravel API Resources
│   ├── Models/              # 17 Eloquent models
│   ├── Policies/            # EducationArticlePolicy, EducationWalkthroughPolicy, EducationLearningPathPolicy
│   ├── Providers/
│   │   └── EducationServiceProvider.php
│   ├── Repositories/        # 5 repository interfaces + implementations
│   └── Services/
│       └── Education/       # 11 service implementations
└── tests/
    └── Feature/             # 13+ test files, 100+ tests
```

---

## Education Module Database Schema

### Core Content Tables
| Table | Key Columns | Notes |
|-------|-------------|-------|
| `edu_categories` | id, site_id, name, slug, parent_id | Hierarchical |
| `edu_articles` | id, site_id, category_id, module_slug, title, body, auto_generated, status, documentation_hash | Main content |
| `edu_article_revisions` | id, article_id, revision_number | Locked with `lockForUpdate` |
| `edu_walkthroughs` | id, site_id, feature_key, title, trigger_route | Step-by-step guides |
| `edu_walkthrough_steps` | id, walkthrough_id, sort_order | |
| `edu_media` | id, article_id, video_provider, auto_generated | Video/image assets |
| `edu_release_notes` | id, article_id, revision_id, version | Links to revisions |
| `edu_learning_paths` | id, site_id, module_slug, role_target | Role-based paths |
| `edu_learning_path_steps` | id, path_id, article_id, sort_order | |

### Progress & Engagement Tables
| Table | Notes |
|-------|-------|
| `edu_user_progress` | site_id REQUIRED |
| `edu_bookmarks` | site_id REQUIRED |
| `edu_walkthrough_progress` | site_id REQUIRED |
| `edu_sync_logs` | Per-site sync history |

### AI Engine Tables (EDU-14A)
| Table | Notes |
|-------|-------|
| `edu_features` | Feature registry |
| `edu_feature_usage` | Unique index: (user_id, site_id, feature_key). Atomic INSERT ON DUPLICATE KEY UPDATE |
| `edu_recommendations` | Covering unique index (EDU-13.5 migration) |
| `edu_generation_logs` | AI generation audit log (STATUS_ constants) |

### Indexes Added (EDU-15.2)
- `edu_articles_ai_draft_unique` on `(auto_generated, module_slug, site_id, title(191))`
- `edu_articles_ai_draft_lookup` on `(auto_generated, site_id, module_slug)`

---

## Education Module Service Graph

```
Commands (CLI triggers)
    │
    ├── GenerateContentCommand ──────────────────┐
    ├── GenerateGapContentCommand ───────────────┤
    ├── GenerateArticlesCommand ─────────────────┤──► EducationAiContentGenerationServiceInterface
    ├── GenerateWalkthroughsCommand ─────────────┤         │
    └── BuildContentDeficitQueueCommand ─────────┘         │ (generates DTO only, no DB writes)
                                                            ▼
                                            [ArticleDraftGenerator]
                                            [WalkthroughDraftGenerator]
                                            [LearningPathDraftGenerator]
                                                            │
                                                            ▼
                                            EducationDraftPersistenceServiceInterface
                                                (persist, draftExists, logFailure)
                                                            │
                                                            ▼
                                                    edu_articles (DB)

Intelligence Pipeline (EDU-14A/14B):
UserLearningProfileService
    └── KnowledgeGapService ──► KnowledgeGapQueueService ──► GenerateGapContentCommand
    └── AIRecommendationScoreService ──► RecommendationRefreshService
ContentReadinessService ──► ContentDeficitQueueService ──► GenerateContentCommand
EducationAnalyticsService ──► GET /api/v1/education/analytics
```

---

## Education Module API Endpoints

| Method | Route | Auth | Controller | Added |
|--------|-------|------|-----------|-------|
| GET | `/api/v1/education/articles` | sanctum | MobileEducationController | Base |
| GET | `/api/v1/education/articles/{id}` | sanctum | MobileEducationController | Base |
| GET | `/api/v1/education/search` | sanctum | MobileEducationController | Base |
| GET | `/api/v1/education/learning-paths` | sanctum | MobileEducationController | Base |
| POST | `/api/v1/education/articles/{id}/progress` | sanctum | MobileEducationController | Base |
| GET | `/api/v1/education/profile` | sanctum | EducationProfileController | EDU-14A |
| GET | `/api/v1/education/analytics` | sanctum | EducationAnalyticsController | EDU-14B |
| GET | `/api/v1/education/generated-content` | sanctum+policy | EducationGeneratedContentController | EDU-15 |
| GET | `/api/v1/education/sync/{siteId}/manifest` | sanctum | EducationSyncApiController | EDU-12 |
| POST | `/api/v1/education/sync/{siteId}/diff` | sanctum | EducationSyncApiController | EDU-12 |
| GET | `/api/v1/education/sync/{siteId}/articles` | sanctum | EducationSyncApiController | EDU-12 |
| GET | `/api/v1/education/sync/{siteId}/walkthroughs` | sanctum | EducationSyncApiController | EDU-12 |
| GET | `/api/v1/education/sync/{siteId}/learning-paths` | sanctum | EducationSyncApiController | EDU-12 |
| POST | `/api/v1/education/sync/{siteId}/trigger` | sanctum | EducationSyncApiController | EDU-12 |

---

## Messaging Architecture (M3.2)

**Domain A — Tenant Support Runtime** (`customer-site`)
- Tables: `support_conversations`, `support_messages`, `support_presence`, `support_read_receipts`
- Services: `TenantConversationService`, `TenantMessageService`, `TenantPresenceService`
- Channel: `private-tenant.{slug}.support.*` (Reverb)
- License gate: `SupportModuleLicenseGate` (deny-by-default, 5-min cache)

**Domain B — Platform Communication Runtime** (`saas-platform`)
- Tables: `platform_conversations`, `platform_messages`, `platform_attachments`
- Guard: Model boot() throws `InvalidArgumentException` for `end_user|user|tenant_user` participant types
- Policy: `PlatformConversationPolicy` — Customer A never sees Customer B messages

**Flutter RuntimeSelectorService:**
- Resolution: login response `runtime` field (`platform|tenant`)
- Fallback: infer from `user_type`
- Stored: `SecureKey.runtimeContext` in Keychain/KeyStore

---

## Frontend Architecture

**Design System Authority:** `core-utils/resources/css/design-tokens.css`  
- 520+ lines, 200+ CSS vars (`--ui-*` prefix)
- Both `app.css` files import canonical token file

**Blade Components:** `x-core-ui::*` namespace (28+ canonical components)  
- Registered via `CoreUtilsServiceProvider` (first priority)
- Tailwind v4.3.0 via `@tailwindcss/vite` plugin

**Alpine.js Rule:** NEVER use `:attr="alpineVar"` on Blade components — always `x-bind:attr` for Alpine expressions (`:attr` evaluates as PHP, crashing on Alpine variable names).

---

## Queue & Cache Architecture

- Queue driver: configurable (Redis recommended for production)
- Cache: configurable (Redis for multi-node)
- Telemetry: `telemetry_events` table + `TelemetryEventBus`
- Tenant cache namespace: all keys namespaced by `tenant_id`
- Circuit breakers: `CircuitBreakerManager` (CLOSED/OPEN/HALF_OPEN states)

---

## Key Invariants

1. No `App\` imports in any package — config-driven model resolution only
2. Every content table has `site_id` — tenant isolation is structural
3. AI draft generators: return DTO only, no DB writes (SRP)
4. Persistence is always the command's responsibility
5. All governance actions emit telemetry; all recoveries logged
6. Billing + admin access preserved at ALL tenant degradation levels
7. Firebase is NEVER required — Reverb + Redis + Local Notifications only
8. Persian-language UI throughout (RTL, Vazirmatn font, Persian numerals)
