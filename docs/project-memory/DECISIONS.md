# ARCHITECTURAL DECISIONS
> ADR-style log. Every important decision recorded permanently.
> Format: Decision ID | Date | Context | Decision | Reason | Impact
> Last Updated: 2026-06-27

---

## ADR-014

**ID:** ADR-014  
**Date:** 2026-06-27  
**Title:** Universal Deployment Engine — Driver-Based Architecture

**Context:**  
ARCHITECTURE_UPGRADE_001: The existing deployment pipeline only supported SSH/VPS. Supporting shared hosting (cPanel, DirectAdmin), SFTP, and Docker would have required adding `if/switch` conditionals throughout the 20-stage pipeline.

**Decision:**  
Implement a Driver-Based Deployment Architecture with `DeploymentDriverInterface`. Every driver declares its `DriverCapabilities`. The pipeline queries `capabilities()` — never checks `driverType()`. Adding a new hosting type requires only a new driver class.

**Reason:**  
Open/Closed Principle: pipeline is open for extension (new drivers), closed for modification. Prevents hosting-type conditionals from spreading across 20 stage classes.

**Impact:**  
- `DeploymentDriverInterface` is the canonical contract for all future deployment work
- `DeploymentTarget` model replaces the "server" concept — 11 fields including `connection_driver`, `capabilities`, `path`, `domain`
- `SshDeploymentDriver` is the only live driver; 4 stubs (cPanel, DirectAdmin, SFTP, Docker) declare architecture
- All pipeline stages that use supervisor/cron/redis must check `capabilities()` before executing
- `FakeRemoteDriver` now implements `DeploymentDriverInterface` for full test coverage
- Backward compatible: `CustomerRemoteServer` + `SshRemoteDriver` unchanged

**Never violate:**  
No `if ($driverType === DriverType::SSH)` or `switch` in pipeline stages. Always `capabilities()`.

---

## ADR-001

**ID:** ADR-001  
**Date:** 2026-06-03  
**Title:** AI Draft Generators Must Never Persist Data

**Context:**  
EDU-15 introduced three draft generator classes (`ArticleDraftGenerator`, `WalkthroughDraftGenerator`, `LearningPathDraftGenerator`) responsible for producing AI-generated content.

**Decision:**  
`EducationAiContentGenerationService` and all draft generator classes must never write to the database. They return DTOs only.

**Reason:**  
Maintain Single Responsibility Principle (SRP) and clean service boundaries. Persistence side effects inside a generation service make it untestable in isolation and violates the contract that "generation" and "storage" are separate concerns.

**Impact:**  
Persistence remains the calling command's responsibility. Commands call `EducationDraftPersistenceService::persist()`. This enables testing generation logic without a database.

---

## ADR-002

**Date:** 2026-06-03  
**Title:** No App\ Imports Inside Any Package

**Context:**  
Packages (`core-utils`, `modules-tickets`, `modules-forms`, `modules-education`) must be distributable and independently deployable.

**Decision:**  
Zero `App\` namespace imports allowed inside any `packages/` directory. Model references use `config('module.model_class')` pattern.

**Reason:**  
Phase 8.1 audit found App\ imports causing tight coupling between packages and the host application. This was repaired (commit c23ae02). The rule prevents regression.

**Impact:**  
All packages reference host models via configuration keys. ServiceProviders bind interfaces to concrete classes at boot time using config-driven resolution.

---

## ADR-003

**Date:** 2026-06-04  
**Title:** markCompleted() Requires site_id — Breaking Interface Change

**Context:**  
EDU-13.5 hardening found `WalkthroughTriggerService::markCompleted()` could silently attach progress to the wrong site without a site_id parameter.

**Decision:**  
`WalkthroughTriggerServiceInterface::markCompleted()` signature changed to require 3 arguments: `(int $walkthroughId, int $userId, int $siteId)`.

**Reason:**  
Without site_id, progress records could be created with null site_id or attached to the wrong tenant's scope.

**Impact:**  
Breaking change. All callers must be updated. `EducationGuideComposer` and the admin `complete` route closure were the two affected callers — both fixed in EDU-13.6/13.7.

---

## ADR-004

**Date:** 2026-06-04  
**Title:** KnowledgeGapService — Use whereNull Guard for Platform Context

**Context:**  
EDU-14A.1 audit (Finding F-03) found that `KnowledgeGapService` did not distinguish between `site_id = null` (platform admin context) and `site_id = {id}` (tenant context) when querying gaps.

**Decision:**  
All service methods that accept a nullable `?int $siteId` must use `whereNull('site_id')` when `$siteId === null`, never `where('site_id', null)` (which is a no-op in MySQL with NULL comparisons).

**Reason:**  
`where('site_id', null)` compiles to `site_id = NULL` which always returns 0 rows. Platform-admin context (siteId=null) must query platform-level records explicitly.

**Impact:**  
Applied to `KnowledgeGapService`, `AIRecommendationScoreService` (`loadUsageMap`, `loadProgressMap`), and `UserLearningProfileService` (`resolveLastActivity`). All services with nullable site_id must follow this pattern.

---

## ADR-005

**Date:** 2026-06-05  
**Title:** Concurrent Draft Protection — Two-Layer Dedup Strategy

**Context:**  
EDU-15.2 audit (Finding RC-01) identified that concurrent command runs could create duplicate AI drafts. Application-level `draftExists()` check has a TOCTOU race condition.

**Decision:**  
Two-layer protection in `EducationDraftPersistenceService::persist()`:
1. Layer 1: `lockForUpdate()` re-check inside `DB::transaction()` — catches null-site_id edge cases
2. Layer 2: Catch `QueryException(1062)` unique constraint violation — catches non-null site_id races

Plus a database-level unique index `edu_articles_ai_draft_unique` on `(auto_generated, module_slug, site_id, title(191))`.

**Reason:**  
Application-level checks alone are insufficient under concurrent scheduler runs. Database constraints are the final safety net.

**Impact:**  
New migration: `2026_06_05_000002_add_ai_draft_dedup_index_to_edu_articles.php`. Must be run on all environments before enabling high-frequency scheduling.

---

## ADR-006

**Date:** 2026-06-05  
**Title:** GenerateContentCommand / GenerateGapContentCommand Must Call executeJob() Directly

**Context:**  
EDU-15.2 audit (Finding F-01) found that both commands were using `ContentGenerationJobDto(moduleSlug: $module ?? 'unknown')`, setting `module_slug='unknown'` for all articles when run without `--module`.

**Decision:**  
Both commands must consume their respective queue services (`ContentDeficitQueueService`, `KnowledgeGapQueueService`) directly and call `executeJob()` per queue item, preserving the real `moduleSlug` from each queue item DTO.

**Reason:**  
The `--module` flag is optional/filtering, not the source of truth for `module_slug`. The queue item's `moduleSlug` is the authoritative value.

**Impact:**  
`module_slug` is now always accurate in `edu_articles`. Tests updated with regression guard asserting `module_slug != 'unknown'`.

---

## ADR-007

**Date:** 2026-06-05  
**Title:** Test Isolation — withTenantSite() Helper for Cross-Site Security Tests

**Context:**  
EDU-15.2 audit (Finding T-01) found `GeneratedContentSecurityTest` was testing cross-site isolation but the `$siteId` field was only stored as a private property the controller could not read. Tests were false positives.

**Decision:**  
Add a `withTenantSite(int $siteId): static` test helper that injects the `tenant_site` request attribute via a one-time middleware, matching production `AuthenticateTenantApi` middleware behavior exactly.

**Reason:**  
Security tests must exercise the exact same code path as production. Mocking or bypassing middleware produces false confidence.

**Impact:**  
All cross-site isolation tests now accurately verify tenant boundary enforcement. Pattern must be used by all future security tests.

---

## ADR-008

**Date:** 2026-05-28  
**Title:** Alpine Expressions on Blade Components Must Use x-bind, Never :attr

**Context:**  
Phase 10.2.7 discovered a fatal crash: Blade's `:attr="expr"` syntax evaluates `expr` as PHP at render time. Alpine.js variable names (`historyIndex`, `form.is_active`) are not PHP variables → Fatal Error PHP 8.

**Decision:**  
NEVER use `:attr="alpineVar"` on Blade components. Always use `x-bind:attr="alpineVar"` for Alpine expressions on Blade components.

**Reason:**  
`:attr` on a Blade component is evaluated server-side as PHP. `x-bind:attr` passes the expression through `$attributes` and is evaluated client-side by Alpine.

**Impact:**  
All Blade components that accept dynamic Alpine bindings must use `x-bind:`. CI governance rule added. Documented as architectural exception pattern.

---

## ADR-009

**Date:** 2026-06-01  
**Title:** Dual Messaging Domain Isolation — End Users Never in Platform DB

**Context:**  
Phase M3.2 introduced support messaging. Two separate domains were needed: tenant-side (customer-site) and platform-side (saas-platform).

**Decision:**  
End-user messages are ONLY stored in `customer-site` database. Platform DB (`saas-platform`) stores only admin/platform_staff/customer_admin conversations. Model `boot()` methods throw `InvalidArgumentException` if `end_user|user|tenant_user` participant types are used in Platform models.

**Reason:**  
Regulatory, architectural, and security isolation. Platform disconnection must not destroy tenant support runtime. Customer A data must never be visible to Customer B.

**Impact:**  
`PlatformConversationParticipant` and `PlatformMessage` enforce forbidden types at the model layer — impossible to accidentally insert tenant user data into platform DB.

---

## ADR-010

**Date:** 2026-05-29  
**Title:** Tailwind v4 — No postcss.config.js, No tailwind.config.js

**Context:**  
Phase 10.2.8 migrated from Tailwind v3.4.19 to v4.3.0.

**Decision:**  
`tailwind.config.js` deleted. `postcss.config.js` emptied. Configuration moves entirely to CSS `@theme` block inside `app.css`. Build via `@tailwindcss/vite` plugin, not PostCSS.

**Reason:**  
Tailwind v4 uses Lightning CSS engine with a Vite-native plugin. The PostCSS chain is no longer needed and causes conflicts with the new optimizer.

**Impact:**  
6 `@source` paths added to `app.css`. Build exit 0, 161KB CSS. Optimizer produces non-breaking shadow-var warnings (cosmetic only).

---

## ADR-011

**Date:** 2026-05-26  
**Title:** Education Module Uses Stub AI, Not Real Provider

**Context:**  
The education module was designed before AI provider integration was complete. Real LLM calls were not yet governed or rate-limited.

**Decision:**  
`EducationAiServiceInterface` is bound to `EducationAiServiceStub` until `EDUCATION_AI_PROVIDER` env is set and EDU-16 review workflow is complete.

**Reason:**  
Deploying auto-generation without human review creates risk of unreviewed AI content being published to tenants. The stub ensures the pipeline is tested without live AI calls.

**Impact:**  
No real AI calls until the review workflow (EDU-16) is in place. `EDUCATION_AI_PROVIDER` env acts as the feature gate.

---

## ADR-013

**Date:** 2026-06-06  
**Title:** Education State Machine — No pending_review State

**Context:**  
EDU-16 design validation found that `pending_review` was semantically identical to `draft`. `EducationGeneratedContentController` mapped `pending_review = draftCount`; `scopePendingReview()` queried `whereIn(REVIEWABLE_STATUSES)` with two states that had identical business meaning. No actor creates a "draft but not yet pending review" distinction.

**Decision:**  
Remove `pending_review` entirely. Simplified state machine: `draft → published (approve) | archived (reject)`. Review audit is tracked via `reviewed_at IS NOT NULL` filter on the existing columns.

**Reason:**  
Adding a state that provides zero additional semantic value creates complexity with no benefit. A draft is already awaiting human review by definition. The review action is the transition, not a state change to "pending review" followed by another state change.

**Impact:**  
- `STATUS_PENDING_REVIEW` constant removed from `EducationArticle`  
- `REVIEWABLE_STATUSES` constant removed  
- `scopePendingReview()` now queries `where(STATUS_DRAFT)` only  
- No `pending_review` key in any API response  
- Migration `2026_06_06_000001_add_review_fields_to_edu_articles.php` adds `reviewed_by_id`, `reviewed_at`, `review_note` columns + composite index — no status enum change needed

---

## ADR-012

**Date:** 2026-06-04  
**Title:** EducationGuideComposer — Skip triggerForRoute When siteId Is Null

**Context:**  
EDU-13.6 found a regression: `EducationGuideComposer` was passing `null` siteId to `triggerForRoute()`, causing walkthrough guides to fail for platform admins.

**Decision:**  
`EducationGuideComposer` resolves `$siteId = auth()->user()->site_id`. If `$siteId === null` (platform admin), skip `triggerForRoute()` entirely.

**Reason:**  
Platform admins do not belong to a site. Walkthroughs are tenant-scoped features. Attempting to load walkthroughs for a platform admin with null site_id would either fail or return incorrect data.

**Impact:**  
Platform admins see the admin panel without education guide overlays. Tenant users continue to receive contextual walkthroughs. No regression for tenant context.

---

## ADR-014

**Date:** 2026-06-14  
**Title:** Module Operational Boundary — Platform Admin Is Stats-Only for Tenant Modules

**Context:**  
Audit revealed that two tenant-operational features were available in the saas-platform admin panel:
1. Forms module: package routes loaded admin CRUD in both saas-platform and customer-site
2. Menu management: `admin/sites/{site}/menus/*` routes in saas-platform stored menus in the platform DB, but customer-site reads menus from its own DB (making these routes useless and architecturally wrong)

**Decision:**  
- Tenant-operational modules (forms, menus, payment gateways for shop, subscriptions, etc.) are managed **exclusively** from customer-site admin panel
- Platform admin only sees **aggregate statistics** for modules (which sites have them enabled, version info)
- `FORMS_PLATFORM_MODE=true` env flag disables forms admin CRUD in saas-platform; only the platform-overview stats page is accessible
- Menu management routes removed from `admin/sites/{site}/menus/*` in saas-platform

**Reason:**  
- Each customer site is an independent SaaS tenant. The platform admin's role is infrastructure management, module licensing, and oversight — not operational management of tenant content
- Menus in saas-platform DB were never consumed by customer-site (which reads from its own DB), making them dead data
- Forms CRUD in platform admin duplicated functionality already fully available in customer-site admin

**Impact:**  
- `packages/modules/forms/routes/web.php`: admin CRUD block wrapped in `if (!config('forms.platform_mode', false))`
- `packages/modules/forms/config/forms.php`: `platform_mode` key added
- saas-platform `.env`: `FORMS_PLATFORM_MODE=true`
- saas-platform `routes/web.php`: menu routes removed; `/admin/forms` now maps to read-only overview
- saas-platform `FormController`: converted to stats-only (shows sites with forms module enabled)
- **No impact** on customer-site (full forms + menu management unchanged)

---

## ADR-015

**Date:** 2026-06-14  
**Title:** Correct Module Placement Map

**Context:**  
Clear definition of which modules belong where, to prevent future architectural drift.

**Decision — Module Placement Rules:**

| Module | Code Lives In | Admin Managed By | Rationale |
|--------|--------------|-----------------|-----------|
| Forms | `packages/modules/forms` | customer-site only | Tenant creates their own forms |
| Menus | App\ (each) | customer-site only | Each tenant manages their own nav |
| Payment Gateway (shop) | customer-site App\ | customer-site only | Tenant's own payment methods |
| Payment Gateway (platform) | saas-platform App\ | saas-platform only | Platform collects money from customers |
| Education | `packages/modules/education` | saas-platform (content) + customer-site (portal) | AI content managed centrally, portal is tenant-facing |
| Tickets | `packages/modules/tickets` | customer-site only | Tenant manages support with their users |
| Subscription | customer-site App\ | customer-site only | Tenant's subscription plans for end-users |
| Theme Builder | saas-platform App\ | saas-platform only | Platform creates themes, assigns to tenants |
| Infrastructure | saas-platform App\ | saas-platform only | Server/domain/backup management |
| Blog / Shop / Courses | customer-site App\ | customer-site only | Tenant content |

**Reason:**  
Prevent platform admin from doing operational work on behalf of tenants. Platform admin = infrastructure + licensing + oversight. Tenant admin = all operational content.

**Impact:**  
This map is the authoritative reference for any future module development decisions.

---

## ADR-016

**Date:** 2026-06-20
**Title:** Theme Creation vs. Theme Customization — Permanent Access Boundary

**Context:**
TB3 Sprints 1–8 built the Theme Builder, Visual Page Builder, Template Library, and AI Generator. The architecture required a formal decision separating what platform admins can do from what customers (tenant admins) can do regarding themes.

**Decision:**
Two permanently separated access tiers for theme functionality:

**CREATION (Platform Admin only — saas-platform):**
- Create new themes from scratch
- Edit theme definitions in the theme catalog
- Run the AI Theme Generator (all 4 input modes)
- Create and manage Theme Packs
- Publish themes to the Marketplace
- Administer Marketplace listings, featured flags, and license revocations

**CUSTOMIZATION (Customer / Tenant Admin — customer-site):**
- Edit all blocks, sections, header, footer on any installed theme
- Change colors, typography, spacing, responsive settings
- Add, remove, reorder blocks
- Add, clone, delete pages
- Replace images and media
- Save page templates (scoped to their own site)
- Browse the Marketplace, purchase, and install themes
- Export/import their customized canvas data

**Reason:**
Themes are platform intellectual property. Customers buy a license to use and customize a theme, not to build or sell competing themes. This is the same model as Elementor / ThemeForest: install → full customization freedom, but no authoring rights.

**Impact:**
- `ThemeAiController`, `ThemeAiGeneratorController`, `ThemeBuilderController`, `ThemePackController`, `ThemeMarketplaceAdminController` → registered only in `saas-platform/routes/web.php`
- `ThemeDesignController`, `ThemeDesignMediaController`, `ThemeMarketplaceController` → registered only in `customer-site/routes/web.php`
- Full architecture documented in `docs/CUSTOMER_THEME_CUSTOMIZATION_ARCHITECTURE.md`
- Compliance audit as of 2026-06-20: 10/10 — all rules satisfied in current codebase

---

## ADR-017

**Date:** 2026-06-28
**Title:** AI Governance Policy — Lead Enterprise Architect Model

**Context:**
The project operates under an AI-assisted development model. Without a formal governance policy, there is a risk of the AI interrupting implementation with questions about design choices, option preferences, or decisions that the AI should make autonomously.

**Decision:**
The AI operates as the Lead Enterprise Architect. It must:
1. Analyze all technical alternatives internally
2. Select the best enterprise-grade solution
3. Implement immediately without asking for option selection
4. Document the decision briefly in the response
5. Continue to the next task

The AI must NEVER ask: "Which option do you prefer?", "Should I use A or B?", "Can I continue?", or any equivalent.

**Permitted exceptions (only these 3):**
1. Business decisions (pricing, commercial plans, licensing policy, customer-facing commercial options, contractual decisions)
2. Irreversible destructive operations (`DROP DATABASE`, `DROP TABLE` on production, `force-push` to main, delete production backups, destroy recovery repository, permanent data removal)
3. Legal or security decisions (security policy changes, encryption policy, privacy policy, compliance, certificates, keys)

**Reason:**
Interrupting implementation with design questions wastes the project owner's time, breaks flow, and adds no value — the AI has sufficient context and expertise to make enterprise-grade technical decisions autonomously.

**Impact:**
- `PROJECT_CONSTITUTION.md` is the vendor-independent permanent architectural authority
- `AGENTS.md` is the vendor-neutral orchestration and runtime enforcement layer
- `CLAUDE.md`, `CODEX.md`, `CLINE.md`, `CONTINUE.md`, `DEEPSEEK.md`, and legacy entrypoints are compatibility wrappers
- `docs/AI_GOVERNANCE_POLICY.md` and `docs/AI_DECISION_POLICY.md` retain backward-compatible paths without duplicating active governance
- `docs/AI_MEMORY_AUDIT.md` — audit confirming policy is synchronized across all memory files
- This ADR is the permanent architectural record of the governance model

---

## ADR-018

**Date:** 2026-07-02
**Title:** Project Constitution and Vendor-Neutral Agent Orchestration

**Context:**
Permanent project principles, agent execution instructions, capability assumptions, compatibility wrappers, and recovery loading were previously combined in one governance layer. The workspace also retained legacy Cline and Continue rule copies outside the canonical repository.

**Decision:**
Adopt `PROJECT_CONSTITUTION.md` as the highest permanent project authority and limit `AGENTS.md` to vendor-neutral orchestration. Agent-specific files are thin wrappers selecting a declarative capability profile. Project memory and ADRs retain their existing canonical locations. Workspace and `customer-site` entrypoints delegate to the canonical governance files without copying rules.

**Reason:**
Separating permanent architecture from tool orchestration keeps the project valid without any coding agent, removes vendor coupling, prevents rule drift, and allows a future agent to be added with one wrapper.

**Impact:**
- Authority hierarchy: Constitution → orchestration → wrapper → project memory → current task
- ADR-017 remains active and unchanged as the execution-governance decision
- Existing wrapper and compatibility paths remain available
- No application code, business logic, installation pipeline, deployment pipeline, or runtime behavior changes
- Validation and file inventory are recorded in `docs/GOVERNANCE_V2_REPORT.md`

---

## ADR-019

**Date:** 2026-07-03
**Title:** Mandatory Git and Production Synchronization at Task Completion

**Context:**
Completed work could previously remain only in a local workspace or be deployed without being committed and pushed, creating source-of-truth drift between local repositories, Git remotes, and production servers.

**Decision:**
Every completed task must commit and push all task-owned changes to the configured Git remote, synchronize those changes to every affected production server, and verify post-deployment health. Documentation-only changes require Git and server synchronization but do not require unnecessary runtime restarts. Unavailable synchronization keeps the task incomplete and must be recorded as a blocker.

**Reason:**
GitHub and production must remain recoverable, auditable delivery targets rather than optional post-task steps.

**Impact:**
- Task completion now includes Git commit, Git push, server synchronization, and health verification.
- Unrelated dirty workspace or server changes must remain isolated from the task release.
- Selective deployment is required when a full canonical deployment would overwrite unrelated live changes.

---

## ADR-020

**Date:** 2026-08-25
**Title:** Adopt the Commercial Product Architecture Contract as the Product-Ecosystem Constitution

**Context:**
The platform's product-class model (themes, modules, motion graphics, and future capabilities such as icon systems and design systems) had no single, permanent, binding reference for versioning authority, master-vs-customer-instance doctrine, update safety, and marketplace/licensing reuse. A draft document, `docs/architecture/COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md`, had been written covering this ground with concrete code-level evidence, but it declared itself "Accepted — Permanent Reference" without ever passing through this project's own ADR process, creating an unauthorized competing authority alongside `PROJECT_CONSTITUTION.md`. A subsequent audit of `customer-site` (this task) also found two of its factual claims did not match the actual `customer-site` implementation, and surfaced two previously undocumented gaps: `customer-site`'s module entitlement adapter (`NullModuleLicenseAdapter`) is fail-open, contradicting `docs/architecture/entitlement/CUSTOMER_PROJECTION.md`'s fail-closed claim; and `SiteThemeTemplate` plus `TenantThemeResolver`'s 20 hardcoded presets are local, versionless theme assets outside any product/marketplace governance.

**Decision:**
Formally adopt `docs/architecture/COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` as the binding, permanent product-ecosystem architecture reference for saas-platform and customer-site, subordinate to `PROJECT_CONSTITUTION.md` (per ADR-018's authority hierarchy) rather than competing with it. The document was corrected in place before adoption: §3.3's master/customer-instance claim was scoped correctly (the pattern is implemented saas-platform-side; `customer-site` never persists master authoring data locally at all — a stricter form of the same doctrine), and two new findings were recorded as §9.8 (module entitlement fail-open, flagged for an explicit owner decision rather than silently patched) and §9.9 (ungoverned local theme assets in `customer-site`). Two additional binding sections were added to satisfy the platform's stated engineering-governance requirements: §8A (No Classic Development — every solution must be evaluated against "can this be sold, updated, and maintained commercially?") and §12 (a pre-task compliance checklist, run before implementation work on any product class, with unresolved answers reported before coding).

**Reason:**
A permanent product-ecosystem contract is only trustworthy if it entered through the same governance process as every other permanent rule in this workspace, and only useful if its factual claims are verified against both repositories it governs, not just the one it was written from.

**Impact:**
- `docs/architecture/COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` is now a formally adopted, ADR-traceable architecture document — no separate or duplicate constitution file was created.
- `PROJECT_CONSTITUTION.md` is unchanged; this ADR does not amend it, only adds a subordinate, product-scoped extension beneath it.
- Two open items require an explicit owner decision before further product-class work relies on them: (§9.8) whether `customer-site` module entitlement is intentionally platform-gated-only or a real local gap; (§9.9) whether `SiteThemeTemplate`/`TenantThemeResolver` presets are an intentional customer-site-local exemption or should graduate into the platform's Theme product pipeline.
- No application code, business logic, migration, or runtime behavior was changed by this ADR or its referenced document.
