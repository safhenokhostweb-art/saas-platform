# ARCHITECTURE
> Record of module structure, service boundaries, interfaces, and dependency flow.
> Last Updated: 2026-11-28

---

## Unified Block Core & Scoped Skin Architecture (ADR-091) (2026-11-28)

Canvas block families adhere strictly to a single canonical base engine pattern:
- **Header Block Engine:** All Canvas header variants (`header_glass_nimbus`, `header_modern_v1`, `nav_header`) are unified on `nav_header`. Standalone visual block types are forbidden.
- **Visual Presentation via Scoped Skins:** The `header_skin` prop (`default`, `glass_nimbus`, `modern`) switches layout and visual skin via scoped CSS (`.tb-header-skin--glass-nimbus`, `.tb-header-skin--modern`) without duplicating inspector schemas or render dispatch pipelines.
- **Zero-Downtime Backwards Compatibility:** `BlockRenderer::renderBlock` and `ThemeRuntimeDocument` automatically normalize legacy stored block types (`header_glass_nimbus`, `header_modern_v1`) to `type = 'nav_header'` with the matching `header_skin`.
- **Repeater State Integrity:** In `canvas-editor.blade.php`, all 3 repeater tiers use stable `_uid` tracking and in-place field updates to prevent DOM destruction and cursor jumps in Alpine.js.
- **Commercial Asset Gate:** Specialized commercial skins (e.g. `glass_nimbus` / `header-glass-nimbus` preset at 490,000 tomans) are protected via `AssetAccessGate` and `AssetEntitlementClient`.

---

## SleepShiman Home blue theme boundary (2026-09-24)

SleepShiman Home blue adds 22 host-registered types to the existing shared `modules/theme-runtime` package. The same definitions and renderer serve platform preview and customer Canvas/public output. Customer-side allowlisted adapters resolve Products, Product Categories, Blog Posts, and approved Reviews; manual content remains the complete default. Existing revision, asset-localization, logical-repeater selection, and module-presentation seams are reused. No parallel builder, persistence model, or tenant boundary was introduced. Canonical implementation detail: `docs/theme-builder/SLEEPSHIMAN_HOME_BLUE_IMPLEMENTATION.md`.

---

## Ariana MultiPlus theme boundary (2026-10-12)

`ariana-multiplus` (Database\Seeders\ArianaMultiPlusThemeSeeder) introduces ZERO new block types, renderers, migrations, or package changes. It is a pure data composition of existing canonical `theme-runtime` block types (five professional slider families included), with fa-IR/RTL/Vazirmatn global styles and a mobile-number-only content identity (no email anywhere in the persisted document — `contact_split` is deliberately excluded because its shared renderer hardcodes an email placeholder). The seeder is an authoritative canonical source registered in `ThemeCatalogRebuildCommand::CANONICAL_SOURCES`, so `theme-catalog:rebuild` deterministically reconstructs it; commercial eligibility follows the standard `theme-release:backfill` → Commercial Quality Gate path.

---

## Platform Overview

### Default Canvas homepage (2026-10-01)

`SaaSPlatform\ThemeRuntime\DefaultHomepage` loads the portable
`packages/saas-platform/modules/theme-runtime/resources/documents/default-homepage.json`.
New full themes without an explicit `pages` document receive this starter
through `ThemeBuilderService`; existing or explicitly empty documents retain
their content. Header/footer remain in their canonical block lists, with
hero, services, and a registered dynamic product grid in `pages.home.blocks`.
The document declares theme-owned chrome and Persian RTL presentation.
Explicit `layout_slot` metadata annotates existing renderer roots in both
hosts, including non-editable iframe chrome; legacy response annotation is
independently idempotent for each slot. No alternate renderer, persistence
authority, migration, or active-site rewrite is introduced.

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

### Theme runtime data-binding boundary (FoodMart Phase 4B)

The shared theme package owns canonical block definitions and FoodMart DOM. `customer-site` owns data access through `ThemeDataBindingRegistry` and allowlisted `ThemeDataSource` implementations. A block may carry a top-level `binding` object containing only `source`, `query`, and `empty`; each query is restricted to source-specific allowlisted sorts plus bounded IDs/filter/limit fields. Resolved semantic records are injected into renderer context as `theme_bindings[block_id]`. Invalid, undeclared, or cross-connection requests fail closed. The customer-site deployment is database-per-customer, so Product isolation is the selected database itself; binding input cannot select a connection, model, service, or tenant. Unbound FoodMart blocks remain deterministic static-reference mode.

### Canvas runtime and module-presentation boundary (FoodMart completion gate)

Public, live Canvas, and full draft Preview all build through `ThemeRuntimeDocument` and `layouts.canvas-theme`; their only differences are published versus draft state and whether the live-editor bridge is attached. `ThemePresentationCapabilityRegistry` reads presentation declarations from active-theme block definitions and maps a domain capability to one or more renderer-owned block types. Canvas inserts the chosen block through its existing block tree and attaches only the definition's allowlisted binding. It never supplies a generic domain card. Structural repeater operations may declare `liveStructureUpdate=document`; Canvas then saves the draft structural mutation and reloads the canonical iframe document so already-installed theme scripts reinitialize safely. Incremental replacements also emit the theme-neutral `theme:content-updated` lifecycle event.

### Block SDK (Phase 9, Step 1)

`ThemeBlockSDK` is the standardized developer layer over the theme-canvas
runtime bridge. It is a **consumer**, never a second bridge:
`ThemeCanvasRuntime.version` stays `1` and owns the editor protocol,
`replaceNode`, the `lifecycle()` phase fan-out, and the event bus. The SDK
registers against the surfaces the runtime already exposes (`registerBlock`,
`initBlock`, `trackScript`, `events.*`) and re-implements none of them, so
removing it reverts the ecosystem to raw runtime access with no other change.

It ships as one distributable asset
(`packages/saas-platform/modules/theme-runtime/resources/js/block-sdk.js`)
behind the same provider-path + Blade-include seam as the identity contract
(`ThemeRuntimeServiceProvider::blockSdkContractPath()` →
`canvas/block-sdk.blade.php`), so Node tests and Blade hosts consume one
source. The editor bootstrap loads it at position 2 — after the runtime it
wraps, before the identity contract — and its fail-fast gate is the third
counterpart to the identity and runtime gates:

```
1. runtime    ThemeCanvasRuntime  version 1
2. block-sdk  ThemeBlockSDK       version 1
3. identity   CanvasIdentity      version 1
4. host structural services partial
5. interaction runtime
```

`canvas-preview.blade.php` includes `runtime` + `block-sdk` without
`identity`: it renders blocks but never authors them.

Blocks are defined with `defineBlock({...})` or by subclassing
`ThemeBlockSDK.BaseBlock`; both are bridged onto the runtime's two
registration idioms so a developer writes only `init` / `onUpdate` /
`onRefresh` / `onDestroy`. The runtime's WeakMap-backed `ctx.on` cleanup is
the teardown mechanism — the SDK keeps no second subscription registry, and
`onDestroy` cannot rely on `ctx.on`. Props travel one way only:
`BlockRenderer` emits `data-tb-block-props` on the same wrapper that carries
`data-tb-block-id`/`-type` (both modes, child tree excluded, empty props
omitted so existing output is unchanged), and `ctx.setProp` is local client
state that emits `state:update` and never round-trips through the editor.
`ThemeBlockSDK.EVENTS` is the published event schema. `reference_counter` is
the worked example; it is intentionally not in `BlockRegistry`.

See ADR-036 for the full decision record and rejected alternatives.

### Commercial compositional widgets (Phase 3C Milestone 1)

Commercial sections remain ordinary canonical block trees in the shared theme-runtime package. `features_grid`, `stats_row`, `cta_band`, and `trust_badges_row` are structural containers; `stat_item` and `trust_item` are leaves, while features continue to compose the existing `icon_box` primitive. `features_grid` and `stats_row` preserve their pre-existing JSON-prop render path when no child tree is present, providing migration-free compatibility. New schemas use only existing Inspector controls, and direction-sensitive layout is expressed through logical CSS properties and scoped `--tb-*` variables. Section Library entries are document templates inserted through the existing Canvas tree/history pipeline, not a second rendering or persistence engine.

### Phase 3C Milestone 2 dynamic binding

Dynamic widgets remain host-registered shared-runtime definitions rather than additions to the canonical registry table. `WidgetConfig` layers optional `data_source` metadata validation over `WidgetDefinition`; Inspector persistence remains three primitives (`source_type`, `endpoint_url`, `query_params`). Each renderer owns or receives one `DataFetcher`, which normalizes through immutable `DataSourceConfig`, memoizes within that renderer lifecycle, delegates I/O only to injected host adapters, catches adapter failures, and returns an explicit `DataFetchResult`. The shared package performs no tenant database access and no arbitrary HTTP request.

Customer-site supplies both controlled boundaries. Internal product queries reuse `ThemeDataBindingRegistry` and `ProductDataSource`, including bounded `category_id` and `collection_slug`; the selected customer database remains the tenant boundary. External feeds use credential-free HTTPS URLs whose host must exactly match `theme-runtime.dynamic_api_hosts`; redirects are disabled, timeouts and item counts are bounded, and the allowlist is empty by default. Renderer output reads only semantic item fields, escapes text and attributes, validates URLs, never emits endpoint/query configuration, and uses logical CSS geometry for RTL/LTR.

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
