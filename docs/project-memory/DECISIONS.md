# ARCHITECTURAL DECISIONS
> ADR-style log. Every important decision recorded permanently.
> Format: Decision ID | Date | Context | Decision | Reason | Impact
> Last Updated: 2026-08-29

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

---

## ADR-021

**Date:** 2026-08-27
**Title:** ThemeRelease as the Authoritative Commercial Release Ledger for Theme Products

**Context:**
`docs/architecture/THEME_PRODUCTIZATION_ARCHITECTURE.md` (Phase 4 design) proposed a three-layer Theme Product model — catalog identity (`Theme`), an append-only release ledger, and structured dependency declarations — required to close the gap ADR-020's contract already flagged in its own §9.1 (Theme versioning is not automatic) and §9.3 (no unified cross-product dependency concept). That document explicitly declares itself "PROPOSED / PENDING APPROVAL" and states no production dependency may exist on it "until it is explicitly approved via a follow-up ADR." No such ADR had been recorded before this audit, even though implementation had already begun: `ThemeRelease` (model), `ThemeReleaseService`, and the `theme_releases`/`themes.current_release_id` migrations exist in the working tree (untracked, Phase 1a — the ledger, immutability guard, rollback, and restore-to-draft primitives), and a further wiring change connecting `ThemeBuilderService::publish()` to `ThemeReleaseService` (Phase 1b — `Theme::releases()`/`currentRelease()` relations, a release-readiness gate, transactional publish) was written and deliberately committed to `git stash@{0}` ("Preserve ThemeRelease Phase 1a wiring... excluded from first SaaS release") rather than the working tree, so it ships with neither `d71b576` nor any later commit.

This split left the working tree in a broken, previously undetected state: `ThemeReleaseService::release()` calls `$theme->releases()->create(...)` and `$theme->currentRelease()->first()`, but `App\Models\Theme` in the working tree has neither relation — both were stashed away with the rest of Phase 1b. Running `php artisan test --filter=ThemeReleaseServiceTest` against the current working tree confirms this concretely: 12 of 13 tests fail with `BadMethodCallException: Call to undefined method App\Models\Theme::releases()`. `ThemePublishReleaseIntegrationTest.php` (also untracked, Phase 1b) asserts against the stashed `ThemeBuilderService::publish()` signature (`publish(Theme, ?version, ?changelog, ?releaseNotes, ?publishedBy)` with a pages-based release-readiness gate) that does not exist in the working tree's `ThemeBuilderService::publish(Theme $theme): Theme`, so it cannot pass either. `ThemeReleaseHardeningTest.php` (Phase 1c hardening) tests `ThemeReleaseService` directly and is affected by the same missing relations.

Separately, the audit confirmed by direct inspection: the release snapshot (`header_blocks`, `footer_blocks`, `pages`, `global_styles`, `settings`) is complete and covers the field `ThemeRevision::saveRevision()` already omits (`pages`); immutability is enforced at the model layer via `ThemeRelease::booted()` with its bypass boundary (quiet saves, bulk query-builder writes) explicitly documented rather than silently assumed; `rollback()` (pointer-only, never mutates release content or theme draft columns) and `restoreToDraft()` (content-only, never mutates the pointer or any release row) are two distinct, non-conflated operations backed by dedicated tests; customer-instance isolation is proven by test (`test_releasing_the_master_theme_does_not_affect_a_customer_instance`) rather than assumed; `theme_releases.compatibility`/`.dependencies` are reserved JSON columns, intentionally unpopulated in this phase per the migration's own docblock — no dependency data has been fabricated; and `Theme.version` (legacy int, bumped by the pre-existing `ThemeBuilderService::publish()` path) remains the field `ThemeLicense::isOutdated()`/`outdatedLicensesCount()` compares against — `ThemeRelease.version` (semver string) is a separate, not-yet-cross-referenced version concept, matching the general cross-product drift risk ADR-020's contract already names in its §9.3.

**Decision:**
1. **`ThemeRelease` is the authoritative commercial release ledger for the Theme product class**, per the contract's (ADR-020, §5) "ledger wins" rule: `theme_releases` is the one append-only, single-writer (`ThemeReleaseService`) release record; `Theme.version`/`status`/`published_at` are denormalized pointers, never a competing source of truth. This settles the model `THEME_PRODUCTIZATION_ARCHITECTURE.md` Phase 2 proposed; that document's Phase 2 (three-layer model), its restoration semantics (rollback vs. restore-to-draft as distinct operations), its snapshot contract, and its dependency-column shape (open `dependency_type` enum: `module | motion_asset | icon_pack | block_pack`, structured `version_constraint`, `is_required`) are hereby formally approved as designed, satisfying that document's own precondition for implementation to proceed.
2. **`ThemeRevision` remains a separate, unrelated draft/checkpoint tool** (`ThemeBuilderService::saveRevision()`/`restoreRevision()`), not a release mechanism and not superseded by `ThemeRelease`. Its own per-theme integer version counter is intentionally independent of release semver and must stay that way — the two must never be merged into one concept.
3. **`Theme.version` (legacy int) stays in place as a transitional, backward-compatible pointer only**, because `ThemeLicense::isOutdated()`/`outdatedLicensesCount()` (customer-facing license-drift detection, GAP-011) reads it today and must not be broken by this ADR. It is authorized to keep being bumped by the legacy publish path in parallel with `ThemeRelease` creation, in the same transaction, for as long as `ThemeLicense` has no ledger-based equivalent. Migrating `ThemeLicense.installed_theme_version` to reference the release ledger (e.g., an `installed_release_id` or semver comparison against `ThemeRelease.version`) is authorized future work, not performed here.
4. **Release immutability, rollback, and restore-to-draft semantics are ratified exactly as implemented in Phase 1a/1b**: immutable-after-creation except `status`; rollback repoints `current_release_id` only; restore-to-draft copies snapshot content into live draft columns only; neither operation ever deletes a row. The documented bypass boundary (quiet saves, bulk writes never instantiate a model and so never fire the guard) is accepted as a permanent, convention-enforced limit, not a defect to close in this phase.
5. **The release-readiness boundary is, for this phase, exactly the one precondition already given concrete meaning by the rendering path**: a theme with no authored `pages` cannot render on a live site and is therefore not release-ready. The full quality gate (contract §11: accessibility, rendering diff, responsive checks) remains future work and must not be read as satisfied by this minimal gate.
6. **Customer-data isolation is enforced structurally by `site_id` separation** (master `site_id = null` vs. customer-instance `site_id = <site>`, per contract §6), proven by the existing cross-instance test; releasing or rolling back a master theme must never write to a customer-instance `Theme` row, `Setting`, or `ThemeInstanceRevision` — this ADR does not change that boundary, it confirms it already holds by construction and by test.
7. **Marketplace authority transition** (reading `theme_release_dependencies`/`theme_releases.dependencies` live instead of `ThemeMarketplace.requirements`/`.supported_modules_json`) is authorized as the target end-state but explicitly **not enacted now** — those fields are not touched, dropped, or bypassed by this ADR.
8. **This ADR does not authorize resolving the working-tree/stash split.** The Phase 1b wiring in `stash@{0}` is not merged, applied, or discarded by this decision. Until it (or an equivalent) is applied, `ThemeReleaseService` is non-functional against the working tree's `Theme` model, and this must be treated as a blocker recorded here, not silently patched as a side effect of unrelated work.

**Reason:**
The contract (ADR-020) already requires that no product class's release mechanism be built ad hoc, and `THEME_PRODUCTIZATION_ARCHITECTURE.md` already required its own follow-up ADR before anything built against it could be considered authorized. Recording the decision now — after verifying the actual code, not just the design document — closes that governance gap honestly, including the parts that do not reflect well on the current state (the broken working tree), rather than either blocking on it silently or quietly implementing around it.

**Impact:**
- `docs/architecture/THEME_PRODUCTIZATION_ARCHITECTURE.md` Phase 2's model is now ADR-authorized; its "PROPOSED / PENDING APPROVAL" banner should be updated to reflect this ADR when that document is next touched (not done here — doc-only follow-up, no architectural content changes needed).
- No application code, migration, or test file was created, modified, or applied by this ADR. `stash@{0}` was inspected (`git stash list`, `git show`) but not popped, applied, or dropped.
- **Blocker recorded, not resolved:** the working tree's `ThemeReleaseService` cannot function until `Theme::releases()`/`Theme::currentRelease()` (currently only in `stash@{0}`) exist in the working tree. This must be resolved — by applying the stash, or by re-authoring the same relations directly — before any Phase 1c implementation task that exercises `ThemeReleaseService` end-to-end.
- Future work authorized by this ADR (not performed here): wiring `ThemeBuilderService::publish()` to `ThemeReleaseService` (resolve the stash split first); backfilling all 27 existing themes with a real v1.0.0 release; migrating `ThemeLicense.installed_theme_version` off `Theme.version` and onto the release ledger; populating `theme_releases.dependencies` with real, non-fabricated data; cutting the Marketplace read path over to the dependency ledger.

---

## ADR-022

**Date:** 2026-08-28
**Title:** Legacy Theme Metadata — Deprecation, Compatibility Boundary, and the One Field Retired

**Context:**
ADR-021 authorized `ThemeRelease` as the commercial version authority and named `ThemeLicense.installed_theme_version`/`Theme.version` as a temporary transitional pointer. The subsequent phases (Phase 1d backfill, Phase 1e structured dependencies, Phase 1f Marketplace cutover, Phase 1g/1h/1i consumer audits and migrations) made every internal decision path — Marketplace API presentation, customer-site display, and the SaaS Admin "N outdated" badge (`Theme::outdatedLicensesCountByLedger()`, Phase 1i) — ledger-first, with the legacy fields (`Theme.version`, `ThemeLicense.installed_theme_version`, `ThemeLicense::isOutdated()`, `ThemeMarketplace.requirements`, `.version_notes`) retained only as fallback for when ledger data is unavailable.

This phase (1j) audited whether any of those legacy fields can now actually be removed. The audit could not prove the absence of an external consumer: `ThemeMarketplaceApiController`'s public response (`formatTheme()`, `myLicenses()`) still serves `version`, `installed_theme_version`, `is_outdated`, `requirements`, and `version_notes` to `customer-site` — and `customer-site` is deployed independently per tenant, so a given tenant's live deployment cannot be assumed to be running the Phase 1h code that prefers the ledger-aware fields over these. Per this task's own instruction ("if an external consumer cannot be proven absent, classify the field as deprecated, not removable"), none of these five fields were removed.

One field was re-verified and found genuinely dead: `ThemeMarketplace.supported_modules_json` is never included in any API response (`formatTheme()` never returns it, confirmed by direct reading), never read by any controller, view, or test in either repository, and its only two writers (`MarketplaceThemeSeedCommand`, `MalltinaThemeSeedCommand`) duplicated data that already exists in `Theme.settings['supported_modules']` and, once a theme is released, in `theme_release_dependencies` (Phase 1e). No unique information is lost by removing it.

**Decision:**
1. **`ThemeMarketplace.supported_modules_json` is retired**: both seed-command writers stopped writing it, removed from `ThemeMarketplace::$fillable`, and the column is dropped by a forward migration (`2026_08_28_090000_drop_supported_modules_json_from_theme_marketplace_table`). This is the one field this phase actually removes.
2. **`Theme.version`, `ThemeLicense.installed_theme_version`, `ThemeLicense::isOutdated()`, `Theme::outdatedLicensesCount()`, `ThemeMarketplace.requirements`, and `.version_notes` are formally marked `@deprecated`** in code (docblocks on the model methods; inline comments on every API response field that serves them) but **not removed, and their writes are not stopped**. `ThemeBuilderService::publish()`'s legacy version bump and `ThemeLicense::grant()`/`download()`'s `installed_theme_version`/`Theme.version` writes continue exactly as before.
3. **Stopping those writes was considered and rejected as unsafe**, not merely deferred: since the API still serves the values they produce to potentially-unupgraded `customer-site` deployments, freezing the writes would silently make an already-deprecated-but-still-served API field wrong instead of retiring it cleanly — a worse outcome than leaving it alone. A field may only stop being written once it has also stopped being served, never before.
4. **Deprecated fields remain presentation-only and may never gate a business/entitlement decision.** This was already true before this ADR (verified: `isOutdated()`/`Theme.version` do not affect license validity or purchase eligibility anywhere) and is now the explicit, binding rule for these fields going forward.
5. **Retirement criteria for the deprecated fields** (all five, plus `outdatedLicensesCount()`): they may be removed once (a) `customer-site`'s Phase 1h ledger-aware consumption has been confirmed deployed to every tenant site — not just present in this repository's `main`/current branch — and (b) a subsequent ADR records that confirmation and authorizes the removal. Absent that confirmation, they remain classified "must remain for API compatibility," not "safe to remove," indefinitely.

**Reason:**
A multi-tenant platform where the API consumer (`customer-site`) is deployed independently per tenant cannot treat "the current repository no longer reads field X" as proof that X is safe to stop serving — only proof that the *platform's own* code no longer depends on it. Conflating those two facts would risk silently breaking tenants running an older deployment. Marking fields deprecated in code (not just in a document) makes the boundary visible to the next engineer at the exact call site, not only in project memory.

**Impact:**
- `database/migrations/2026_08_28_090000_drop_supported_modules_json_from_theme_marketplace_table.php` — new, additive-safe (verified zero non-duplicate data lost).
- `app/Models/ThemeMarketplace.php`, `MarketplaceThemeSeedCommand.php`, `MalltinaThemeSeedCommand.php` — stopped referencing `supported_modules_json`.
- `app/Models/Theme.php`, `app/Models/ThemeLicense.php`, `app/Http/Controllers/Api/ThemeMarketplaceApiController.php` — `@deprecated` docblocks/comments added; no behavior changed.
- `ThemeRelease`/`theme_release_dependencies` ledger history, the Marketplace API's ledger-derived fields, `customer-site`'s Phase 1h presenter, and the Phase 1i Admin badge are all unaffected — this ADR only formalizes and narrows what remains legacy, it does not touch anything already ledger-first.
- This closes the Phase 1a–1j Theme Productization migration series. Any future work resuming it should read ADR-020, ADR-021, and this ADR in order before touching Theme version/dependency code.

---

## ADR-023

**Date:** 2026-08-28
**Title:** Retirement of the Legacy Theme Version Authority — No Active Customer Fleet Confirmed

**Context:**
ADR-022 (§5) set an explicit retirement precondition for `Theme.version`, `ThemeLicense.installed_theme_version`, `ThemeLicense::isOutdated()`, and `Theme::outdatedLicensesCount()`: they could only be removed once (a) `customer-site`'s ledger-aware consumption was confirmed deployed to *every tenant site*, not just present in this repository, and (b) a follow-up ADR recorded that confirmation. That precondition existed because a multi-tenant platform cannot infer a live tenant's deployed code from its own current branch.

The business owner has now explicitly confirmed, for this task (Phase 2b — Commercial Theme Catalog Cleanup, Canonicalization & Quality Remediation): there are currently **no active real customers** and **no active production customer Theme deployments** of any kind. There is no tenant fleet whose deployed `customer-site` version is unknown or unconfirmable — because there is no tenant fleet. This does not satisfy ADR-022 §5(a) in the sense originally written (confirmed-deployed-everywhere); it makes §5(a) moot — the set of deployments the precondition was protecting is empty.

**Decision:**
1. **ADR-022 §5's retirement precondition is superseded, not overridden, by this fact.** The precondition remains the correct rule for any future state where a live customer/tenant fleet exists; it is inapplicable only because that state does not currently exist. If active customers or production Theme deployments are onboarded in the future, ADR-022 §5's original confirm-before-removing-anything-new discipline applies again to whatever legacy/compatibility surface exists at that time — this ADR does not repeal that discipline, it records one confirmed exception window.
2. **The following compatibility paths are authorized for retirement now** and were removed in this phase: `Theme.version` (column dropped), `ThemeLicense.installed_theme_version` (column dropped), `ThemeLicense::isOutdated()` (method removed), `Theme::outdatedLicensesCount()` (method removed). `Theme::outdatedLicensesCountByLedger()` no longer falls back to a legacy comparison — an outdated state it cannot resolve from the ledger is left uncounted, never fabricated. Migration: `2026_08_28_110000_drop_legacy_version_authority_from_themes_and_theme_licenses`.
3. **`ThemeMarketplace.requirements` and `.version_notes` are deliberately NOT retired.** Their original ADR-022 retention reason (tenant API-compatibility) no longer applies under the no-active-customer confirmation above, but a separate, still-valid reason does: the admin Theme publish UI has no changelog/release-notes input yet, so these fields remain the only place a marketplace listing's requirements text and version notes are authored. They are retained for that reason now, not the original one; `ThemeMarketplaceApiController`'s inline comments were updated in this phase to say so.
4. **This phase also removed two items proven architecturally incompatible with the canonical catalog, unrelated to version-authority retirement**: `database/seeders/ProfessionalThemesSeeder.php` (stored page content under `settings.layouts.*`, a shape the rendering pipeline never reads — every theme it seeded was unrenderable) and `app/Console/Commands/RegeneratePlatformThemesCommand.php` (built entirely around that seeder, and was the only call path that could have triggered a latent data-loss bug in `ThemeCleanupService::deleteAutoGeneratedThemes()`, fixed in this phase — see Impact).
5. **A single canonical catalog reconstruction command is established**: `app/Console/Commands/ThemeCatalogRebuildCommand.php` (`theme-catalog:rebuild`), orchestrating the five proven-good seed sources (`marketplace:seed`, `nexus:seed`, `acool:seed`, `malltina:seed`, `SalesSupportThemeSeeder`) against an explicit expected-slug membership list, idempotent and deterministic by construction, with `--fresh` to prune any theme outside that membership. This is now the sole authoritative way to (re)build the commercial Theme catalog from an empty database.

**Reason:**
ADR-022 was deliberately conservative because it could not rule out an unknown tenant depending on the legacy fields. That uncertainty is the only thing that made the fields non-removable — the fields were never valuable in themselves once `ThemeRelease` existed. Once the business owner supplies the one fact ADR-022 was missing (there is no tenant fleet to protect), continuing to carry the legacy pair forward would be technical debt kept for a hypothetical that this task was explicitly instructed not to assume.

**Impact:**
- `app/Models/Theme.php`, `app/Models/ThemeLicense.php`, `app/Services/ThemeBuilder/ThemeBuilderService.php`, `app/Http/Controllers/Api/ThemeMarketplaceApiController.php`, `app/Services/ThemeBuilder/ThemeReleaseService.php` — legacy version-authority reads/writes removed.
- `app/Services/ThemeBuilder/ThemeCleanupService.php` — `deleteAutoGeneratedThemes()`/`hasAutoGeneratedThemes()` narrowed from `source IN (generator, seeder)` to `source = generator` (plus an explicit legacy-slug allowlist); the prior query would have matched and deleted the entire canonical catalog, since every canonical theme has `source = seeder`. This was a latent, previously untriggered defect, not a regression introduced by this phase.
- `database/seeders/DatabaseSeeder.php` — no longer calls the removed `ProfessionalThemesSeeder`.
- Full regression suite (`tests/Feature/ThemeBuilder`, `tests/Unit/ThemeBuilder`, `tests/Unit/ThemeCategoryLabelTest.php`, `tests/Feature/Modules/MotionGraphics/Phase6/ThemeTemplateBridgeIntegrationTest.php`) — 139/139 passing against the post-migration schema.
- `theme:quality-gate` run against all 28 canonical themes post-migration — 28 passed, 0 failed.
- `ThemeRelease`/`ThemeReleaseDependency` ledger, `ThemeQualityReport`, `ThemeInstanceRevision`, customer-instance isolation, and Motion/module dependency architecture are unaffected — none of this phase's removals touched ledger, quality-gate, or customer-instance infrastructure.
- The Commercial Quality Gate (`ThemeCommercialQualityGate`) remains **not wired into the real publish flow** — unchanged by this phase, reserved for a future isolated phase per this task's own explicit instruction.

---

## ADR-024

**Date:** 2026-08-28
**Title:** Mandatory Commercial Theme Publish Gate

**Context:**
ADR-020's contract (§11) already required a full quality gate for commercial product releases. Phase 2a built `ThemeCommercialQualityGate` (8 checks: structural, render, dependency, accessibility, responsive, asset, performance, commercial-metadata) plus `publishIfCompliant()` — an atomic, fail-closed integration point that evaluates a theme, persists the result unconditionally (`ThemeQualityReport`, pass or fail — an audit fact either way), and only creates a `ThemeRelease` through `ThemeReleaseService` (the sole ledger writer) if no blocking check failed. Phase 2a deliberately did not wire it anywhere — its own docblock named that as "the next hardening step," reserved because the real 28-theme catalog had not yet been proven to pass it and the entire pre-existing test suite had not been checked against it. Phase 2b closed that gap: the catalog was cleaned up and re-verified at 28/28 passing.

This task (Phase 2c) audited every real, production-invocable path that could create a `ThemeRelease` or advance `current_release_id`. Two existed: `ThemeBuilderService::publish()` (the interactive publish flow, reached via `ThemeBuilderController::publish()` and its one route) and `ThemeReleaseBackfillCommand` (the one-time/idempotent ledger-continuity tool for catalog data that predates the release ledger). Both called `ThemeReleaseService::release()` directly, bypassing `ThemeCommercialQualityGate` entirely — meaning a theme with a broken render, a missing accessible label, or a missing required module dependency could still become the site-serving current release. No other controller, command, or seeder was found to create a `ThemeRelease` or mutate `current_release_id`; `ThemeReleaseService::rollback()` only repoints to an already-immutable, previously-approved release (introduces no new content) and `attachDependencies()` only adds metadata to an existing release — neither is a new commercial publish event, so neither is gated.

**Decision:**
1. **No commercial ThemeRelease may become current/published without a recorded passing commercial quality report.** This is now enforced structurally, not by convention: the only two production callers that can create a `ThemeRelease` (`ThemeBuilderService::publish()` and `ThemeReleaseBackfillCommand`) both call `ThemeCommercialQualityGate::publishIfCompliant()` exclusively — neither calls `ThemeReleaseService::release()` directly anymore.
2. **A blocking quality failure is a controlled, structured outcome, not a generic error.** `publishIfCompliant()` throws `ThemeQualityGateFailedException`, carrying the full `ThemeQualityReport` (including every blocking failure's category/code/message). `ThemeBuilderController::publish()` catches it and returns either a 422 JSON payload (`quality_report_id`, `blocking_failures`) or a redirect with the failure messages — it contains no quality logic itself, only presentation of an already-decided outcome. `ThemeReleaseBackfillCommand` catches it per-theme and continues the batch, exactly as it already did for any other failure.
3. **The failing report is always persisted, never discarded.** `evaluate()` (called first, unconditionally, inside `publishIfCompliant()`) writes the `ThemeQualityReport` row before any pass/fail branch is taken — a rejected publish attempt is exactly as much an audit fact as an approved one.
4. **Blocking/warning/unverified severity is unchanged by this phase.** No check was reclassified to make publishing easier or harder. The only blocking codes across all 8 checks remain: `structural.no_pages`, `structural.no_rendered_content`, `structural.invalid_field_shape`, `render.missing_block_type`, `render.exception`, `dependency.malformed`, `dependency.required_module_missing`, `accessibility.image_missing_alt`, `accessibility.form_field_missing_label`, `asset.broken_image_reference`, and a check-internal exception (`gate.check_exception`, fails closed rather than treating a crashed validator as a pass). `render.unsupported_block_type` (the ~59-instance backlog across the catalog) remains a warning, not a blocker — promoting it was explicitly out of scope for this phase and was not done.
5. **Rollback and dependency-attachment are deliberately excluded from the gate**, for the reasons given in Context — this is a scope boundary, not an oversight.
6. **Seed-command catalog bootstrap (`marketplace:seed`, `nexus:seed`, `acool:seed`, `malltina:seed`, `SalesSupportThemeSeeder`) is unchanged and remains outside this ADR's scope.** These commands set `Theme.status = published` directly at row-creation time as part of building the canonical catalog (pre-existing behavior, unchanged since before Phase 2b); `Theme.status` is a denormalized display pointer (ADR-021), not the commercial release authority, and `ThemeBuilderService::resolveForSite()`'s use of `status` to select which theme serves a live site is a separate, pre-existing characteristic this ADR does not alter. Every theme these commands create is, in the current catalog, also backfilled a real gated release via `theme-release:backfill` (decision #1) before this task's own validation run — but a hypothetical future seed command that creates a `status=published` Theme row and is never backfilled would still be servable by `resolveForSite()` without ever having passed the gate. This gap is recorded honestly here as a known limitation of the `status` column's serving role, not fixed in this phase — fixing it would mean changing what "servable" means platform-wide, out of this phase's scope of "make the Quality Gate mandatory for ThemeRelease."

**Reason:**
A quality gate that exists, is tested, and is correct is not a commercial guarantee until nothing can reach production without going through it. ADR-020 already required this outcome; Phase 2a built the mechanism; this ADR is the record that the mechanism is now load-bearing everywhere a commercial release can be created, not merely available to a caller that chooses to use it.

**Impact:**
- `app/Services/ThemeBuilder/ThemeBuilderService.php` — `publish()` now calls `ThemeCommercialQualityGate::publishIfCompliant()` instead of `ThemeReleaseService::release()` directly; constructor now also injects `ThemeCommercialQualityGate`.
- `app/Http/Controllers/Admin/ThemeBuilderController.php` — `publish()` catches `ThemeQualityGateFailedException` and returns structured diagnostics; no quality logic added.
- `app/Console/Commands/ThemeReleaseBackfillCommand.php` — routes through `publishIfCompliant()` instead of `ThemeReleaseService::release()` directly; a theme that fails the gate is left un-backfilled and reported, not silently granted a release.
- `app/Services/ThemeBuilder/QualityGate/ThemeCommercialQualityGate.php` — docblock updated to reflect that it is now wired in (was previously explicit that it was not).
- No schema change: `theme_quality_reports.theme_release_id` (nullable FK, added Phase 2a) already provides the report↔release linkage this ADR relies on.
- `ThemeReleaseService::release()` itself is unchanged — it remains the sole ledger writer and still enforces only its own minimal `PagesAuthoredReadinessCheck`; the full commercial bar is enforced one layer up, at the gate, not duplicated into the writer.

---

## ADR-025

**Date:** 2026-08-28
**Title:** Theme.status Is Never a Commercial Authority — Theme::isCommerciallyEligible() Is the Sole Serving/Licensing Rule

**Context:**
ADR-024 closed the *publish-side* bypass (no `ThemeRelease` without a passing gate) but explicitly recorded, in its §6, a remaining *serving-side* gap: `Theme.status = published` — set directly by all five canonical seed commands at row-creation time, long before any release exists — was still independently sufficient to make a theme servable via `ThemeBuilderService::resolveForSite()` and purchasable via `ThemeMarketplaceApiController`. A theme could therefore be `status=published` with `current_release_id=null` (or pointing at a release that never had a passing report) and still be resolved for a live site, listed in the Marketplace, purchased, licensed, and downloaded — none of which had ever gone through a Commercial Quality Gate evaluation at all.

This task audited every production consumer of `Theme.status`, `current_release_id`, `ThemeRelease.status`, and Marketplace/license/install decision points (full list in the accompanying Phase 2d report) and classified each as authoring/workflow state, a commercial-eligibility decision, or display-only metadata. A second, more subtle gap was found during this audit: the 28 real canonical releases already in the database were all created by `theme-release:backfill` in Phase 2b — *before* Phase 2c wired the Commercial Quality Gate into that command — so none of them had a `ThemeQualityReport` linked to their release despite having genuinely passed evaluation twice (Phase 2b and Phase 2c both independently confirmed 28/28 passing). A strict eligibility rule requiring that linkage would have made every real theme ineligible overnight; this was resolved by a one-time, honest reconciliation (see Impact), not by weakening the rule.

**Decision:**
1. **`Theme.status` is permanently redefined as authoring/workflow metadata only.** `isPublished()`/`isDraft()` remain (used by the Theme Builder's own edit/publish/unpublish flow and admin listing badges) but are documented as never a commercial signal, and no commercial decision may read them.
2. **`Theme::isCommerciallyEligible()` is the one authoritative commercial-eligibility rule**, requiring all of: a non-null `current_release_id`; the referenced `ThemeRelease.theme_id` actually equal to this theme's id (defends against a corrupted/mismatched pointer); that release's own `status = published`; and at least one `ThemeQualityReport` with `status = passed` linked to that exact release (`theme_quality_reports.theme_release_id`). `commercialIneligibilityReasons()` gives the same answer as a reason-code list for diagnostics. **No commercial Theme may be resolved for a site, listed as marketplace-ready, purchased, newly licensed (customer purchase, admin manual grant, or bundle grant), or downloaded/installed without this method returning true.**
3. **Reads stay reads.** Resolving/listing/purchasing a Theme never creates a release, never runs the Quality Gate, and never fabricates a passing report — an ineligible theme is simply excluded/rejected, exactly as it was before it had any release at all.
4. **Rollback requires no new evidence.** `ThemeReleaseService::rollback()` is unchanged: repointing `current_release_id` to an already-immutable release is sufficient, and `isCommerciallyEligible()` composes with it automatically — if the target release already has a linked passing report (created via `publishIfCompliant()` or the reconciliation in #5), rollback makes the theme eligible again with no extra step; if the target release predates the gate and was never reconciled, the theme correctly fails closed until it is.
5. **A one-time reconciliation closes the pre-existing-catalog gap honestly, not by weakening the rule.** `ThemeCommercialQualityGate::linkExistingReleaseIfCompliant()` and the new `theme-release:link-quality-reports` command evaluate a theme's *current* content for real, today, and link the resulting report to its *existing* immutable release if it passes — never creating a new release (none is needed; the release's content already matches, since nothing has republished since) and never fabricating a report for a release that does not currently pass. This was run once against the real catalog; see the Phase 2d report for the result. On a genuinely fresh database this reconciliation has nothing to do — Phase 2c's gated `theme-release:backfill` already creates a correctly-linked release for every theme in one atomic step.
6. **Catalog visibility and admin authoring/preview remain unrestricted by commercial eligibility.** The Theme Builder canvas/edit/list views, `Site.theme_id` assignment, and `ThemeCanvasController::installToSite()` (a platform-admin operational tool that writes directly to a tenant's own database, entirely outside the commercial license/ledger system) are unchanged — these are authoring/operational actions, not commercial-serving decisions, per the same distinction ADR-024 §6 already drew.
7. **Seed commands are unchanged.** They still set `status = published` at creation for authoring/workflow reasons (so the admin catalog UI shows a complete definition) — this is now harmless by construction, since `status` carries no commercial weight; removing it from the seed payloads was considered and rejected as unnecessary churn with no eligibility effect either way.

**Reason:**
ADR-024 made the *creation* of a commercial release fail-closed; without this ADR, the *serving* side could still treat an unapproved theme as if the gate had never existed, which would have made ADR-024's guarantee cosmetic. A single boolean-producing authority, never re-derived ad hoc per controller, is the only way to guarantee every current and future serving/purchase/licensing path agrees.

**Impact:**
- `app/Models/Theme.php` — `isCommerciallyEligible()`/`commercialIneligibilityReasons()` added; `isPublished()`/`isDraft()` docblocks clarify they are authoring-only.
- `app/Models/ThemeRelease.php` — `qualityReports()` relation added (supports the eligibility check).
- `app/Services/ThemeBuilder/QualityGate/ThemeCommercialQualityGate.php` — `linkExistingReleaseIfCompliant()` added (reconciliation only; does not change `publishIfCompliant()`).
- `app/Console/Commands/ThemeReleaseQualityLinkCommand.php` — new, one-time reconciliation command.
- `app/Services/ThemeBuilder/ThemeBuilderService.php` — `resolveForSite()` migrated from `isPublished()`/`->published()` to `isCommerciallyEligible()`.
- `app/Http/Controllers/Api/ThemeMarketplaceApiController.php` — `index()` (listing filter), `show()`, `initiatePurchase()` migrated; `download()` gained a new eligibility check it did not have before.
- `app/Http/Controllers/ThemeMarketplacePaymentController.php` — paid-purchase callback re-checks eligibility immediately before granting a license (payment status itself is left honestly as paid either way).
- `app/Http/Controllers/Admin/ThemeMarketplaceAdminController.php` — `grantLicense()` (manual/comp grant) gained an eligibility check.
- `app/Models/ThemeBundle.php` — `grantLicensesToSite()` now grants only eligible themes in a bundle and returns the real count.
- No schema change beyond what ADR-024 already added.

---

## ADR-026

**Date:** 2026-08-28
**Title:** Canonical Theme Block Rendering Coverage — Registered Must Mean Renderable

**Context:**
The Commercial Quality Gate's `render.unsupported_block_type` finding (ADR-020's contract §11) reported ~59 occurrences across the 28 canonical themes, all traceable to exactly 8 block types with no `BlockRenderer` implementation: `nav_header`/`nav_footer` (used by 24 and 23 themes respectively — a self-authored site header/footer, the single largest gap), `site_footer`/`hvac_hero` (acool-hvac-service only — a data-richer footer variant and an industry-named hero variant), and `about_section`/`service_cards`/`project_cards`/`contact_info` (acool-hvac-service's own real business content sections). None were obsolete, prototype, or malformed data — every one was real, well-formed content actually authored by a real canonical theme (classification A in the task's own scheme; no B/C/D/E cases were found among these 8).

Building the `render.unsupported_block_type` count down to zero required a Builder↔Registry↔Renderer contract test (`CanonicalBlockRegistryContractTest`) that recursively walks every block tree (header/footer/pages, including nested children/tabs/accordion panels) — a check `RenderIntegrityCheck` itself never performed (it only inspected each location's top-level block list). That test proved the original `render.unsupported_block_type` check was checking the wrong thing: "is this type present in `BlockRegistry::all()`" is a weaker guarantee than "will `BlockRenderer::renderBlock()` actually produce real markup for it." Ten additional canonical types (`nav_menu`, `social_icons` — misnamed methods `Str::studly()` could never actually dispatch to — plus `breadcrumb`, `faq`, `search_bar`, `email_capture`, `price_box`, `counter`, `login_register`, `blog_posts`, `testimonials`, `countdown`) were registered and authorable but had no renderer at all, invisibly falling through to `renderFallback()` without ever being flagged, because the check's registry-membership test considered them "known."

**Decision:**
1. **`render.unsupported_block_type` now means "no real renderer exists," not "not in the registry."** `RenderIntegrityCheck::isRenderable()` mirrors `BlockRenderer::renderBlock()`'s own dispatch decision exactly (a `module:`-prefixed type is the one sanctioned extension escape hatch, everything else needs a real `render{Type}` method) — the check can never again silently diverge from what actually happens at render time. The check now also recurses into nested block trees (children/tabs/accordion/slides), closing the blind spot that let `counter`/`login_register`/`countdown` (all nested inside sections/columns/grids) go unreported.
2. **All 18 genuinely-missing canonical types now have real renderers** in `BlockRenderer.php` (`nav_header`, `nav_footer`, `hero_trust`, `about_section`, `service_cards`, `project_cards`, `contact_info`, `breadcrumb`, `faq`, `search_bar`, `email_capture` — delegates to the existing `newsletter_form`, `counter`, `login_register`, `blog_posts`, `testimonials`, `price_box`, `countdown`) plus 2 naming-bug fixes (`nav_menu`, `social_icons`, renamed to match `Str::studly()`'s actual output). None fabricate data: `breadcrumb`/`price_box` render an honest minimal state (or accept a `setContext()`-injected real value, mirroring `nav_menu`'s existing `menu_html` pattern) rather than inventing a page hierarchy or a product price; `blog_posts`/`testimonials` render an honest "will show once available" placeholder (mirroring the pre-existing `team` block's own established precedent) rather than fabricating posts/reviews no static Theme Builder render has access to.
3. **`hvac_hero` and `site_footer` are retired, not aliased.** Their content was genuinely a richer/differently-named variant of `hero_trust`/`nav_footer`, not a distinct concept — `AcoolHvacSeedCommand.php` (the canonical source) was migrated to the reusable, industry-neutral registered names directly; the old names are not registered and cannot silently reappear (enforced by `CanonicalBlockRegistryContractTest`). `service_cards` was deliberately kept distinct from the pre-existing `services_cards` despite the near-duplicate name — their schemas are genuinely different (real array vs. JSON-string-textarea authoring, different field names) and forcing one into the other would have been lossy, violating the task's explicit "never merge semantically different blocks" rule.
4. **The existing `module:`-prefix convention is confirmed as the one sanctioned core-vs-extension-owned distinction** (a statically-registered type is core/theme-authored; a `module:`-prefixed type is resolved by an installed Module's own renderer at runtime via `setContext(['module_renderer' => ...])`) — no new mechanism was introduced; `BlockRegistry`'s `CATEGORY_MODULE` was already the coarser, complementary signal for dynamic-content-sourced core types (e.g. `product_grid`). This remains the extension point future Block Packs use.
5. **Customer-site parity**: `customer-site` maintains its own independent `BlockRenderer.php` (confirmed duplication, not sharing a package with saas-platform — a pre-existing architecture predating this phase, not resolved here per the task's explicit "no unrelated large renderer extraction" instruction). It already had real, better-than-saas-platform's implementations of `blog_posts`/`testimonials`/`faq`/`search_bar`/`countdown` (pulling genuine live Post/Review data, appropriately, since that context has real data saas-platform's standalone preview does not). It was missing `breadcrumb`/`email_capture`/`price_box`/`counter`/`login_register` entirely, and its `nav_header`/`hvac_hero`/`about_section`/`service_cards`/`project_cards`/`contact_info`/`site_footer` implementations were a hardcoded, HVAC-brand-specific, fully-inline-styled ad-hoc build (hardcoded "Acool HVAC" fallback text, a hardcoded "HVAC TECH" SVG illustration, hardcoded HVAC-brand logo names as a fallback) that ignored the theme's own design tokens — a real defect this phase's own "no hardcoded customer content"/"Theme design tokens" requirements directly cover. All 7 were replaced with markup matching saas-platform's implementations exactly (same `tb-*` classes, same real-data-only behavior); the 5 missing ones were added. `resources/views/themes/default/layout.blade.php` gained the CSS these `tb-*` classes need — which customer-site's stylesheet had never defined for the entire "premium blocks" class family (`services_cards`/`stats_row`/`contact_split`/etc. were already relying on classes with no matching CSS rule before this phase; this ADR's CSS addition incidentally also gives those pre-existing types real styling on customer-site for the first time).

**Reason:**
A registry entry only proves a block type is authorable in the Builder UI — it says nothing about whether the platform can actually render it. Conflating the two let a real, silent product defect (a customer paying for a theme whose header/footer/business-content sections render as raw fallback markup) hide behind a passing-looking Quality Gate. Fixing the 8 originally-reported types without also fixing the check's own blind spot would have left the exact same class of defect free to recur the next time a theme author used a registered-but-unrendered type.

**Impact:**
- `app/Services/ThemeBuilder/BlockRenderer.php` (saas-platform) — 18 new/renamed renderer methods.
- `app/Services/ThemeBuilder/BlockRegistry.php` — `nav_header`/`nav_footer`/`hero_trust`/`about_section`/`service_cards`/`project_cards`/`contact_info` registered; `hvac_hero`/`site_footer` never registered.
- `app/Services/ThemeBuilder/QualityGate/Checks/RenderIntegrityCheck.php` — `unsupported_block_type` detection corrected to real renderability, and now recurses into nested block trees.
- `app/Console/Commands/AcoolHvacSeedCommand.php` — canonical source migrated off the two retired type names and the `links`/`background` field-name inconsistency (aligned to `nav_links`/`bg_color`, matching every other canonical theme).
- Real dev DB: `acool:seed` re-run to apply the canonical source fix; a new release (1.0.1) published through the Mandatory Commercial Publish Gate (ADR-024) reflecting the corrected content — the prior 1.0.0 release remains immutable, unmodified history. All 28 canonical themes remain commercially eligible (ADR-025) throughout.
- `tests/Feature/ThemeBuilder/CanonicalBlockRegistryContractTest.php`, `tests/Unit/ThemeBuilder/CanonicalBlockRenderersTest.php` — new; the former is the durable, reusable invariant test the task required so this class of defect cannot silently recur.
- customer-site: `app/Services/ThemeBuilder/BlockRenderer.php` (7 methods replaced, 5 added, 1 helper added), `resources/views/themes/default/layout.blade.php` (CSS added). No schema/migration changes on either repository.
- Final catalog result: `render.unsupported_block_type` = 0 across all 28 canonical themes (verified against the corrected, recursive check); 28/28 pass the full Quality Gate; 28/28 remain commercially eligible.

---

## ADR-027

**Date:** 2026-08-28
**Title:** Empty-Render Integrity — A Registry-Driven Accidental/Optional/Placeholder Contract

**Context:**
A real Quality Gate run found `render.empty_output` = 4, all four the same block type (`client_logos`) on the home page of 4 canonical themes — each had a real, authored `title` ("مشتریان"/"برندهای ما"/"مشتریان صنعتی ما"/"سازمان‌هایی که به ما اعتماد کردند") but no `logos` array, and `BlockRenderer::renderClientLogos()` unconditionally `return ''`-ed on an empty logos list, silently discarding the title along with everything else. This was genuinely accidental — the author's intent (there IS a client-logos section here) was visible in the data; the renderer just had no honest way to say "not yet filled in" the way `renderTeam()`, `renderBlogPosts()`, `renderTestimonials()`, `renderPriceBox()`, and `renderCountdown()` already do for the same situation.

A second, related defect was found while fixing the first: customer-site's own independent `BlockRenderer::renderClientLogos()` did not merely return empty — it substituted 9 hardcoded HVAC-brand names ("Carrier", "Trane", "Lennox", "Daikin", "Mitsubishi", "Rheem", "York", "Goodman", "Honeywell") as fabricated fallback partner logos. This directly violates the "never fabricate business/customer data merely to produce HTML" rule this whole Theme Productization series has enforced since Phase 2b, and would have shown a real tenant's storefront visitors implied partnerships with real HVAC brands that theme never actually had.

A third, adjacent case was audited for symmetry: `renderPromoBanner()` also `return ''`-s when its one content field (`text`) is empty — but unlike `client_logos`, a promo banner has no OTHER field that could signal "I want a banner here, just haven't filled in the details yet." An empty promo banner is the ordinary way an author leaves an optional accent unused on a given page, not a sign of missing required content. No canonical theme currently exercises this state, but the same underlying category — genuinely-intentional emptiness — needed a durable, explicit representation, not just a one-off fix.

**Decision:**
1. **`BlockRegistry::emptyOutputPolicy(string $type)`** is the one durable, registry-driven contract `RenderIntegrityCheck` reads — no block-type name is ever hardcoded into the check itself. Three states, declared per-type via an optional `emptyOutputPolicy` registry key:
   - `EMPTY_POLICY_FLAGGED` (the default for any type that declares none): content is fully author-supplied; true emptiness means required commercial content is missing. **Now BLOCKING**, not merely a warning — verified safe against the real 28-theme catalog (0 occurrences after the fixes below), and matches this phase's explicit "preferably blocking when it represents invalid commercial content" requirement.
   - `EMPTY_POLICY_PLACEHOLDER`: this type's renderer contractually never returns true emptiness — it substitutes an honest, non-fabricated placeholder when real (often runtime-sourced) content isn't available. Declared for `client_logos`, `team`, `blog_posts`, `testimonials`, `price_box`, `countdown`. If a type declared this way is ever observed to render truly empty, that is a **broken contract, still BLOCKING** — it means the renderer regressed, not that content is legitimately absent.
   - `EMPTY_POLICY_OPTIONAL`: a deliberately optional accent with no other field that could carry authorial intent. Declared for `promo_banner`. Empty output produces an explicit, non-blocking, informational finding (`render.intentionally_empty`) — visible in the report, never hidden by suppression, and never conflated with a defect.
2. **`renderClientLogos()` (both repositories) fixed at the root cause**: when no `logos` are authored, it now renders the real title (if any) plus an honest "logos will show once added" hint — never `''`, never fabricated company names. `renderPromoBanner()` is unchanged in behavior (empty stays empty) but is now formally declared `EMPTY_POLICY_OPTIONAL` so its behavior is an explicit, documented contract rather than an unlabeled edge case.
3. **`RenderIntegrityCheck`'s recursion (ADR-026) already covers nested empty output** — the empty-output branch runs inside the same `checkBlockList()` call the unsupported-type check uses, so a `client_logos` (or any type) nested inside a section/grid/columns/tabs/accordion/slider is caught exactly like a top-level one; no additional recursion logic was needed.
4. **No canonical Theme data changed.** This is a pure shared-renderer + registry fix — the 4 affected themes' authored `client_logos` blocks are unchanged (still just a title, still no logos array); only the platform's interpretation of that existing, valid data improved. Per the ADR-026 precedent, a shared-renderer improvement that doesn't change any Theme's own snapshot data does not require a new `ThemeRelease` — the existing releases remain fully accurate historical records of what was authored.
5. **Customer-site parity**: the same `client_logos` fix was ported (fabrication removed, honest placeholder substituted); `renderPromoBanner()` was already behaviorally correct there (documented, not changed). No new shared-package extraction was undertaken — the pre-existing renderer duplication between the two repositories (recorded in ADR-026) remains and should be revisited if drift risk grows, but was out of this phase's scope.

**Reason:**
"Silent empty HTML is not acceptable" only becomes an enforceable, durable rule if the Quality Gate can tell an accidental gap apart from a legitimate one without a maintainer having to remember to update a hardcoded exception list every time a new runtime-backed or genuinely-optional block type is added. Encoding the distinction as a registry attribute means any future Block Pack or Theme Builder block automatically inherits the correct default (flagged/blocking) unless its author deliberately opts into a different, explicit contract — the safe default cannot be silently bypassed by omission.

**Impact:**
- `app/Services/ThemeBuilder/BlockRegistry.php` — `EMPTY_POLICY_*` constants and `emptyOutputPolicy()` added; `client_logos`/`team`/`blog_posts`/`testimonials`/`price_box`/`countdown` declared `placeholder`; `promo_banner` declared `optional`.
- `app/Services/ThemeBuilder/QualityGate/Checks/RenderIntegrityCheck.php` — `render.empty_output` is now policy-driven and BLOCKING for the default/placeholder-broken cases; `render.intentionally_empty` added as a new, explicit, non-blocking finding code.
- `app/Services/ThemeBuilder/BlockRenderer.php` (saas-platform) — `renderClientLogos()` fixed; `renderPromoBanner()` documented, unchanged.
- customer-site: `app/Services/ThemeBuilder/BlockRenderer.php` (`renderClientLogos()` fabrication removed), `resources/views/themes/default/layout.blade.php` (CSS for the new placeholder markup).
- `tests/Feature/ThemeBuilder/EmptyOutputIntegrityTest.php` (saas-platform), `tests/Unit/ThemeBuilder/EmptyOutputIntegrityTest.php` (customer-site) — new.
- Final catalog result: `render.empty_output` = 0, `render.unsupported_block_type` = 0, `render.intentionally_empty` = 0 (no canonical theme currently exercises an unused optional accent) across all 28 canonical themes; 28/28 pass the full Quality Gate; no new `ThemeRelease` was required.

---

## ADR-028

**Date:** 2026-08-29
**Title:** Real Browser Visual & Responsive Quality Verification — Automatic Preview, Chromium-Verified Contrast/Overflow/Console Evidence, and the UNVERIFIED-vs-BLOCKING Publish Policy

**Context:**
Before this phase, `ResponsiveReadinessCheck` only ran a real headless-browser capture when a caller manually supplied both a `PlaywrightExtractionService` instance and a live preview URL — a precondition no production caller ever satisfied, so every canonical theme's responsive/contrast evidence was permanently `unverified`. Accessibility contrast was not evaluated at all (`AccessibilityBaselineCheck` carried a standing, honest `accessibility.contrast_not_verified` disclosure — real, but permanently unhelpful). Meanwhile a fully real, already-in-production preview mechanism already existed and satisfied every requirement for a deterministic, isolated, no-customer-data preview target: `URL::temporarySignedRoute('admin.theme-builder.canvas.page-preview-signed', ...)` → `ThemeCanvasController::preview()`, already used by `CaptureThemeScreenshotsCommand` — access-controlled purely by Laravel's `signed` route middleware (HMAC + short TTL), rendering through the exact same `BlockRenderer`/`GlobalStyleEngine` production classes, no session/auth/tenant scoping needed.

Wiring real Playwright/Chromium execution behind the Quality Gate's default check list (container-resolved by `ThemeCommercialQualityGate`) creates a real hazard if left unguarded: an ordinary PHPUnit test's `Theme` row lives inside an uncommitted `RefreshDatabase` transaction that a real child browser process reaching the app over actual HTTP cannot see — a genuine launch there would not just be slow, it would silently verify the *wrong* page (a 404, or stale committed data) and record that as evidence. A related, more fundamental gap was found and fixed in the same pass: `quality-check.mjs`'s navigation check only asked "did `page.goto()` not throw," which is `true` even for a 404/500 response — meaning a broken preview could previously have been recorded as a successful, passing render.

A real, non-mocked, end-to-end run of the new pipeline immediately surfaced genuine defects that had never been visible before: `BlockRenderer::renderModulePlaceholder()` hardcoded `#6b7280`/`#9ca3af` gray text regardless of its own per-category background color, failing WCAG AA (3.96:1 measured) against lighter categories like `blog`'s `#dbeafe`; `renderStatsRow()` never set any text color at all, relying entirely on an inherited body default that fails badly against an author-chosen dark `background` (1.28:1 measured on `acool-hvac-service`'s stats section); the Builder's own internal preview stylesheet (`canvas-preview.blade.php`) defined `.tb-cols-2/3/4` as fixed-column CSS grids with no responsive breakpoint at all — unlike customer-site's independently-written equivalent, which already collapses columns below 767px/480px — causing catastrophic horizontal overflow (599px on mobile) on multi-column sections; and one theme's own authored color choice (`nexus-pro-store`'s `#ea580c` orange newsletter banner with white text) measured 3.56:1, just short of the 4.5:1 bar.

**Decision:**
1. **`ThemeQualityPreviewUrlFactory`** is the one place a Theme becomes a real, deterministic, short-lived preview URL automatically — no caller ever supplies one. It reuses the exact production signed route above (home page by default, falling back to the theme's first authored page), a 10-minute TTL, and no new preview infrastructure of any kind.
2. **`PlaywrightExtractionService::runQualityCheck()`** (new) invokes a new, purpose-built script (`scripts/headless/quality-check.mjs`) — kept deliberately separate from the pre-existing `extract.mjs` (a Visual RE competitor-scraping engine answering "what does this other site look like," not "does our own theme render correctly"). Both scripts share the exact same Windows-safe `proc_open` process-spawning logic via a single extracted `runScript()` private method — no duplicated platform-specific pipe-handling code. `quality-check.mjs` captures, for one viewport/one navigation: HTTP status (a 400+ response is now correctly `navigation.ok: false`, not a false pass), DOM root presence, horizontal overflow (`scrollWidth` vs `clientWidth`), console errors, uncaught page exceptions, failed resource requests (flagged same-origin-vs-cross-origin), a full-page screenshot, and browser-computed WCAG contrast sampling — the last via `resolveEffectiveBackground()`, which walks the DOM ancestor chain for a real, fully-opaque solid background and reports a sampled node `unresolvable: true` (never guessed) the moment any ancestor has a background-image, gradient, or partial-transparency layering.
3. **`ResponsiveReadinessCheck`** (rewired) and **`ColorContrastCheck`** (new) both consume this pipeline automatically for desktop/tablet/mobile (contrast: desktop only) and translate evidence into the existing `ThemeQualityCheckResult` contract with explicit severity rules, never flattened to generic pass/fail:
   - **BLOCKING**: browser-observed navigation failure or non-2xx status, missing DOM root, catastrophic horizontal overflow (>20% of viewport width), an uncaught JS exception, a failed same-origin ("required local") resource, and any browser-computed WCAG AA contrast failure (`accessibility.contrast_insufficient`).
   - **WARNING**: minor overflow (≤20% of viewport width), console errors/warnings, a failed cross-origin ("optional remote") resource.
   - **UNVERIFIED, never a fabricated pass**: browser infrastructure unavailable (`PlaywrightExtractionService::isAvailable()` false), a Node/Chromium *process-level* failure (crash, timeout, invalid JSON — reported via `.capture_failed`/`accessibility.contrast_unavailable`, explicitly distinguished from a real browser-observed defect), and any contrast sample whose background could not be reliably resolved (`accessibility.contrast_partially_unresolvable`).
4. **Publish-gate policy (the actual decision this ADR records):** "browser infrastructure unavailable" and "a real browser-executed check found a defect" are never conflated. UNVERIFIED is `SEVERITY_INFO` under the pre-existing `ThemeQualityCheckResult` contract (ADR-020) and therefore never blocks — the same rule every other honestly-unverifiable finding in this Gate already followed; no new mechanism, no environment-name branching in any service, and no risk of "CI/browser temporarily down ⇒ commercial publishing permanently impossible." A real, browser-observed catastrophic failure or WCAG AA violation is `SEVERITY_BLOCKING` exactly like any other check's real defect, and blocks exactly the same way (`ThemeCommercialQualityGate::evaluate()`/`publishIfCompliant()` required zero changes — the existing severity-based blocking logic already generalizes correctly to browser-sourced findings).
5. **Test-suite isolation**: the base `Tests\TestCase` now binds `PlaywrightExtractionService` to report `isAvailable(): false` by default for every test in the suite — a test-harness-only container binding (not an environment-name check inside any application service), matching this project's established convention (`CaptureThemeScreenshotsCommandTest` already mocks this service rather than launching a real browser). This is what keeps hundreds of ordinary Feature/Unit tests fast and correct; any test proving real integration overrides the binding itself with a Mockery double, per that same established convention — never a real launch inside PHPUnit.
6. **Screenshot evidence is quality evidence, never Marketplace marketing imagery.** `ResponsiveReadinessCheck` stores real screenshots at a deterministic, non-timestamped path — `theme-quality-evidence/{slug}/{version}/{viewport}.png` — distinct from `CaptureThemeScreenshotsCommand`'s `theme-screenshots/` path and never touching `Theme.thumbnail`/`ThemeMarketplace.screenshots`. One file per theme+version+viewport means re-running the gate overwrites in place; storage cannot grow without bound.
7. **Five real, root-cause defects found by this phase's real evidence were fixed** (all narrow, additive, evidence-scoped — not a visual redesign): `renderModulePlaceholder()` and `renderStatsRow()` (`BlockRenderer.php`) now derive readable text color from their own background instead of a hardcoded gray; `canvas-preview.blade.php` gained the exact same responsive grid breakpoints (`@media` at 768px/480px collapsing `.tb-cols-3/4` then `.tb-cols-2/3/4`) customer-site's independent stylesheet already had; `nexus-pro-store`'s seed data had its one non-compliant color pairing (`#ea580c`→`#c2410c`, same orange family, ~5.18:1 with white) corrected, applied to both the canonical seed source and the already-seeded dev-DB theme row (no `ThemeRelease` touched); `canvas-preview.blade.php` was entirely missing `client_logos`' marquee CSS (`.tb-logos-marquee-wrap`/`.tb-logos-marquee`/`.tb-logo-item`) — ported directly from customer-site's already-correct implementation (`overflow:hidden` clipping the doubled-track scroller), which explained 100% of the remaining catastrophic horizontal overflow (599px/221px/98px on 2 themes); and porting that CSS surfaced one more real, cross-repo-consistent defect — `.tb-logo-item`'s muted-text fallback was `#9ca3af` (gray-400, 2.54:1 on white, failing AA) in *both* repositories, inconsistent with the very next rule's own `#6b7280` (gray-500) fallback for the same visual context — corrected to `#6b7280` (~4.83:1) in both.
8. **Only Chromium is verified.** No claim of Firefox/Safari/WebKit coverage is made anywhere in code, findings, or this record — the architecture (a single `runScript()` process-spawning method, one script per concern) is extensible to another engine later without redesign, but that is future work, not a claim made now.

**Reason:**
A Quality Gate that reports "UNVERIFIED" forever is honest but useless; a Quality Gate that silently upgrades "we didn't check" to "it passed" the moment browser tooling is unavailable is dishonest and defeats the entire fail-closed architecture this series has built since ADR-020. The only durable design is the one already implicit in `ThemeQualityCheckResult`'s three-state contract (pass/fail/unverified with independent severity): make unavailability structurally incapable of blocking (INFO severity), and make a real, executed browser finding block exactly like any other real finding (BLOCKING severity) — no new policy surface, no hardcoded environment checks, and no incentive to ever treat "we didn't look" as "it's fine."

**Impact:**
- New: `app/Services/ThemeBuilder/QualityGate/ThemeQualityPreviewUrlFactory.php`, `app/Services/ThemeBuilder/QualityGate/Checks/ColorContrastCheck.php`, `scripts/headless/quality-check.mjs`.
- `app/Services/ThemeBuilder/ReverseEngineering/PlaywrightExtractionService.php` — `runQualityCheck()` added; shared `runScript()` extracted from `extract()`, no behavior change to `extract()` itself.
- `app/Services/ThemeBuilder/QualityGate/Checks/ResponsiveReadinessCheck.php` — rewired for automatic preview acquisition and full evidence-to-finding mapping (was previously dead-by-construction).
- `app/Services/ThemeBuilder/QualityGate/Checks/AccessibilityBaselineCheck.php` — the standing `accessibility.contrast_not_verified` disclosure removed; contrast is now `ColorContrastCheck`'s sole, real responsibility.
- `app/Services/ThemeBuilder/QualityGate/ThemeCommercialQualityGate.php` — `ColorContrastCheck` registered in the default check list.
- `app/Services/ThemeBuilder/BlockRenderer.php` — `readableTextColorOn()` helper added; `renderModulePlaceholder()` and `renderStatsRow()` fixed.
- `resources/views/admin/theme-builder/canvas-preview.blade.php` — responsive grid breakpoints added for `.tb-cols-2/3/4`; `client_logos` marquee CSS added (previously entirely absent), with a compliant muted-text color.
- customer-site: `resources/views/layouts/canvas-theme.blade.php` — `.tb-logo-item`'s muted-text fallback corrected from `#9ca3af` to `#6b7280` (the only change made to this repository; its marquee-clipping CSS was already correct and was the reference implementation the saas-platform fix was ported from).
- `app/Console/Commands/NexusSeedCommand.php` — one seed color corrected; the already-seeded `nexus-pro-store` dev-DB row patched to match (no new `ThemeRelease`; existing release history untouched).
- `tests/TestCase.php` — default `PlaywrightExtractionService` fake binding for the whole suite.
- New tests: `tests/Feature/ThemeBuilder/ResponsiveReadinessCheckTest.php` (10), `ColorContrastCheckTest.php` (6), `ThemeQualityPreviewUrlFactoryTest.php` (4), `ThemeQualityGateBrowserIntegrationTest.php` (3) — 23 total, all mocking `PlaywrightExtractionService` per this project's established convention; real end-to-end proof was obtained separately via manual, non-mocked `artisan tinker`/Playwright runs against real signed preview URLs (documented in the Phase 2g final report).
- Real, non-mocked, full 28-canonical-theme browser run (desktop/tablet/mobile + contrast, home page), run to completion three times as fixes landed: 28/28 pass with zero blocking findings on the final run. Full before/after results recorded in the Phase 2g final report rather than duplicated here.
- Customer-site parity: confirmed genuinely divergent, independently-forked renderer and CSS pipeline (not a shared package) — this phase's Quality Gate verifies saas-platform's own Builder preview only. In two of the five defects found, customer-site's independent implementation was already correct and served as the reference the saas-platform fix was ported from; the muted-text contrast defect existed identically in both. A residual structural parity risk remains and is not resolved by this ADR (see the Phase 2g final report for the full assessment).

---

## ADR-029

**Date:** 2026-08-29
**Title:** Shared Theme Runtime Authority — One Canonical BlockRegistry/BlockRenderer/CSS/Token Contract for saas-platform and customer-site

**Context:**
ADR-028 (Phase 2g) proved the parity risk it flagged as theoretical was real: two of the five real defects that phase's browser evidence found (a missing responsive grid breakpoint, an entirely absent `client_logos` marquee clip) existed ONLY in saas-platform's own internal Builder-preview stylesheet — customer-site's independently-maintained equivalent was already correct — while a sixth, muted-text contrast defect existed identically in both, because both had independently copy-pasted the same wrong value. saas-platform's own Quality Gate could prove its Builder preview was correct; it could prove nothing about whether a real tenant site would render the same theme the same way. This phase (2h) existed to close exactly that gap: not by adding more parity tests on top of two forked implementations, but by removing the fork.

Auditing both repositories' `BlockRenderer`/`BlockRegistry`/runtime-CSS/token code confirmed the scale of the drift: saas-platform's `BlockRenderer` (1732 lines, 53 canonical `render*()` methods) and customer-site's (2016 lines, ~80 methods including live-data listing blocks, underscore/camelCase alias duplicates from earlier ad hoc fixes, and its own `ElementRegistry`/`ElementRenderer` "Element SDK" dispatch layer checked before any `render*()` method) had never shared a single line — each was a full, independent reimplementation of the same block vocabulary. customer-site additionally has its own `App\Services\ThemeCanvas\BlockRegistry` (701 lines, no `emptyOutputPolicy()` concept at all — ADR-027 never reached it) and its own `ElementSchemaRegistry`, neither of which saas-platform has any equivalent of. GlobalStyleEngine (saas-platform) had no customer-site counterpart at all; customer-site's own `canvas-theme.blade.php` hand-rolled a materially smaller, independently-drifted subset of the same `--tb-*` CSS variables (no `--tb-accent`, no dark mode, no `custom_css`, a different `--tb-text-muted` default) directly in a `@php` block.

An existing architectural pattern already solved exactly this class of problem for other domains: saas-platform hosts shared packages under `packages/saas-platform/{core-utils,modules/*}`, and customer-site consumes them via Composer path repositories with `symlink: true` (already the mechanism for tickets, forms, CRM, seo-advanced, and Motion Graphics). No new package architecture was needed — only a new package, `saas-platform/modules-theme-runtime`, following the identical convention.

**Decision:**
1. **New shared package `packages/saas-platform/modules/theme-runtime`** (namespace `SaaSPlatform\ThemeRuntime`), registered in both repositories' `composer.json` (customer-site via the standard `../saas-platform/packages/saas-platform/modules/theme-runtime` symlinked path repo). It owns:
   - **`BlockRegistry`** — saas-platform's canonical registry (the more complete, empty-output-policy-aware one) migrated verbatim, made non-`final`, with an additive `registerHostType()`/`resetHostTypes()` extension point so a host can register a genuinely host-specific type (a live-data listing block, say) without forking the class. A host registration can never override a canonical definition (canonical types always win the merge).
   - **`BlockRenderer`** — saas-platform's canonical structural renderer (all 53 methods: layout primitives, hero/CTA variants, stats/features/pricing/team/testimonial/trust/gallery/nav/footer sections, FAQ/breadcrumb/counter/price-box/login-register shells, the `client_logos`/`blog_posts`/`testimonials` honest-placeholder defaults, `renderFallback`) migrated verbatim. It owns zero host-specific data access. Two extension seams, both already-established patterns formalized rather than redesigned: `setContext(['module_renderer' => callable])` for `module:*` types (documented by the new `Contracts\ModuleRendererContract`), and ordinary method overriding for genuinely data-driven canonical types (`renderBlogPosts()`/`renderTestimonials()` render an honest placeholder by default; a host with real data overrides the method — exactly the mechanism `renderPriceBox()`'s pre-existing `$this->context['price_box_html']` injection already demonstrated).
   - **`ThemeTokenNormalizer`** — a new, purpose-built class (not a port of `GlobalStyleEngine`) turning a Theme's raw `global_styles` into the canonical `--tb-*` variable set. Deliberately excludes saas-platform's host-specific `DesignSystemService` (per-site/reseller override) — a host passes its own platform-level defaults in as a plain array, or omits them and gets the normalizer's own sensible defaults.
   - **`Browser\PlaywrightExtractionService`** — Phase 2g's quality-check engine, moved here (constructor now takes an explicit `$scriptRoot` instead of calling `base_path()`) so customer-site can run the identical real-Chromium verification against its own runtime. The Node scripts/`node_modules/playwright` installation stays physically at saas-platform's `scripts/headless/` (Node module resolution ties them to that location); a host without its own install points `theme-runtime.script_root` config at saas-platform's via a cross-repo relative/absolute path — the same sibling-checkout assumption customer-site's own Composer symlinks already make explicit. (saas-platform's separate, app-level `extract()` competitor-scraping tooling is unrelated Visual RE tooling and was NOT moved — it stays saas-platform-only.)
   - **`resources/css/theme-runtime-contract.css`**, included by both hosts via `@include('theme-runtime::css.contract')` — the structural/responsive/accessibility CSS actually implicated in Phase 2g's drift: box-sizing reset, `.tb-container`/`.tb-section`/padding scale, the `.tb-grid`/`.tb-grid-cols-1..6` responsive contract, `.tb-stats-grid`, the `client_logos` marquee (including its `overflow:hidden` clip), FAQ/accordion/tabs, and image/divider primitives.
2. **A real naming-drift bug was found and fixed while building the CSS contract**: saas-platform's own Phase 2g fix had added responsive breakpoints for a class named `.tb-cols-N`, but every `BlockRenderer` (both repos, confirmed identical) actually emits `tb-grid-cols-N` — the Phase 2g rule never matched anything and was dead code; the theme's real overflow reduction in that phase came entirely from the marquee fix. The shared contract now uses the correct `tb-grid-cols-N` selector matching what is actually emitted.
3. **`.tb-container`'s `max-width` is token-driven (`var(--tb-container, 80rem)`)**, not hardcoded — customer-site already supported this for per-theme brand customization; saas-platform's own copy had hardcoded `1200px` and would have silently regressed that capability if the contract had copied saas-platform's value instead of customer-site's.
4. **Both hosts' `App\Services\ThemeBuilder\BlockRenderer`/`BlockRegistry` are now thin subclasses** (`extends \SaaSPlatform\ThemeRuntime\BlockRenderer` / `BlockRegistry`) rather than deleted-and-replaced, so every existing type-hint, container binding, and test double in either app keeps working unchanged. saas-platform's subclasses are now genuinely empty. customer-site's `BlockRenderer` subclass still defines its own ~80 methods (its richer `renderBlock()`/`renderBlockUnsafe()` dispatch — schema validation, exception→placeholder safety, its own `ElementRegistry`/`ElementRenderer` Element-SDK layer — was deliberately preserved, not replaced, as a genuine customer-site-runtime safety concern the Builder preview doesn't need); a canonical method customer-site does NOT redeclare (`renderModulePlaceholder`, confirmed via reflection in `ThemeRuntimeSharedAuthorityTest`) now resolves through real inheritance rather than being duplicated. **customer-site's own `App\Services\ThemeCanvas\BlockRegistry` was NOT unified with the shared registry in this phase** — its 701 lines of independently-evolved schema data (including customer-site-only live-data types) were not individually audited for safe merging; this is the largest explicitly-identified remaining item (see Impact).
5. **`GlobalStyleEngine` (saas-platform) is now a thin adapter**: `getCssVariables()`/`renderCssBlock()` delegate entirely to `ThemeTokenNormalizer`, supplying only its own `DesignSystemService` output as the platform-defaults layer. **customer-site's `canvas-theme.blade.php` now calls the identical normalizer** (`app(ThemeTokenNormalizer::class)->renderCssBlock($globalStyles)`) in place of its previous hand-rolled, materially incomplete `:root{}` block — the same `global_styles` input now produces byte-identical `--tb-*` output on both hosts.
6. **A real Blade-compilation bug was found and fixed while wiring the CSS contract**: `__DIR__` inside a `.blade.php` file resolves to Laravel's compiled-view cache directory (`storage/framework/views/...`), not the original source location — Blade compiles every view, package ones included, into one shared flat cache. The CSS contract partial cannot resolve its own sibling asset path via `__DIR__`; it now calls a static path-resolution method on `ThemeRuntimeServiceProvider` (a real, uncompiled PHP class file, where `__DIR__` is stable) instead.
7. **customer-site real browser runtime verification** (`php artisan theme-runtime:verify {snapshot}`, new): exports a real canonical Theme's `header_blocks`/`footer_blocks`/`pages`/`global_styles` from saas-platform to a JSON file, temporarily installs it as customer-site's `active_canvas_theme_data` Setting (customer-site is a single-tenant deployment — this is its one real per-installation theme slot, not a fake customer), runs the exact real-Chromium `quality-check.mjs` engine against the real local `/` route at desktop/tablet/mobile, reports BLOCKING/OK per viewport, and unconditionally restores the original Setting value (and clears both cache keys the read path uses) in a `finally` block regardless of success or failure. No customer database is touched. Results are explicitly labeled "CUSTOMER RUNTIME VERIFICATION" and must never be presented as equivalent to saas-platform's own Quality Gate result — they answer a different question.
8. **Publish-gate policy is unchanged and NOT extended to require customer-runtime verification.** Per this phase's own explicit instruction and ADR-028's established UNVERIFIED-vs-BLOCKING policy, customer-runtime verification is real, valuable evidence collected alongside the Commercial Quality Gate — it does not (yet) gate commercial publishing, because the infrastructure crosses a repository boundary via a sibling-checkout assumption that is not guaranteed reliable in every deployment/CI environment. Making it mandatory before that assumption is proven deterministic would risk the exact "browser temporarily unavailable ⇒ publishing permanently blocked" failure mode ADR-028 was written to prevent.
9. **The permanent rule**: canonical Theme block rendering semantics have ONE shared authority (`saas-platform/modules-theme-runtime`). Platform preview and customer runtime may provide different host context/data adapters (real tenant data vs. an honest placeholder; a richer safety-wrapped dispatch vs. a plain one) — they must never fork canonical structural rendering, the block registry's schema/empty-output-policy semantics, the responsive/accessibility CSS contract, or the global-style-to-token normalization. A new canonical block type or structural CSS rule is added to the shared package; a genuinely host-specific concern (tenant data retrieval, auth, site context, URL generation) is added to a host's own thin subclass or adapter — never duplicated into both.

**Reason:**
Two independently-evolved implementations of "the same" rendering logic cannot be kept honestly in sync by testing harder — Phase 2g's own real browser evidence already proved a parity TEST catches drift only after it has already shipped to production in one host or the other, and only for whatever specific pages/viewports happen to be tested. The only durable fix is removing the second implementation, so there is structurally nothing left to drift apart. Where a host genuinely needs different behavior (real data vs. a preview placeholder, a safety-wrapped dispatch vs. a plain one), that difference is expressed as an explicit, narrow override or adapter — visible in a diff, not an entire independently-maintained file quietly diverging line by line.

**Impact:**
- New package: `packages/saas-platform/modules/theme-runtime` (`BlockRegistry.php`, `BlockRenderer.php`, `ThemeTokenNormalizer.php`, `Browser/PlaywrightExtractionService.php`, `Contracts/ModuleRendererContract.php`, `Providers/ThemeRuntimeServiceProvider.php`, `config/theme-runtime.php`, `resources/css/theme-runtime-contract.css`, `resources/views/css/contract.blade.php`).
- Both repos' `composer.json`: new `saas-platform/modules-theme-runtime` path repository + require entry (customer-site's symlinked, mirroring its existing tickets/forms/crm/seo-advanced/core-utils entries).
- saas-platform: `app/Services/ThemeBuilder/BlockRenderer.php` and `BlockRegistry.php` reduced to empty subclasses; `GlobalStyleEngine.php` reduced to a thin adapter; `resources/views/admin/theme-builder/canvas-preview.blade.php` now includes the shared CSS contract, with its own remaining rules being genuinely Builder-preview-only visual styling (hero/CTA/button/background-utility classes) not yet audited for sharing.
- customer-site: `app/Services/ThemeBuilder/BlockRenderer.php` now extends the shared base (its own ~80 methods unchanged); `resources/views/layouts/canvas-theme.blade.php` now calls the shared token normalizer and includes the shared CSS contract, with its own now-redundant copies of the migrated rules (grid/cols, padding scale, FAQ/accordion/tabs, divider) removed — its more polished, real, host-owned rules (per-block visual CSS, the marquee's fade-gradient pseudo-elements, `.tb-stats-grid`'s superior implementation) were deliberately left in place rather than downgraded to the shared contract's simpler baseline; new `app/Console/Commands/ThemeRuntimeQualityCheckCommand.php` (`theme-runtime:verify`).
- New tests: `tests/Feature/ThemeBuilder/ThemeRuntimeSharedAuthorityTest.php` (saas-platform), `tests/Unit/ThemeBuilder/ThemeRuntimeSharedAuthorityTest.php` (customer-site) — prove real inheritance (not coincidental duplication) on both sides, that a host registration never overrides a canonical type, and that both hosts' token normalization is identical.
- Full regression (both repos) and a real, non-mocked 28-canonical-theme browser run (SaaS Quality Gate) plus a real, non-mocked customer-runtime browser verification pass across representative/all canonical themes: results recorded in the Phase 2h final report rather than duplicated here.
- **Explicitly NOT completed in this phase** (largest safe boundary reached, not full elimination — see the Phase 2h final report for the complete list): customer-site's own `App\Services\ThemeCanvas\BlockRegistry` (701 lines) remains a separate, unaudited registry; customer-site's ~60+ overlapping `render*()` method bodies were not individually diffed and deleted (they remain safe, functioning overrides — PHP inheritance means duplication here is redundant but not incorrect); the large body of per-block-type visual CSS (product/blog cards, hero variants, pricing/bento/testimonial cards) remains host-local on both sides; customer-runtime verification is not wired into any publish gate.

---

## ADR-030

**Date:** 2026-08-29
**Title:** Customer-Site Renderer Deduplication, Real-Reason Override Classification, and the Element SDK Alternate-Authority Finding

**Context:**
ADR-029 (Phase 2h) established the shared package but explicitly left customer-site's ~60+ overlapping `render*()` methods undiffed, its own 701-line `BlockRegistry` unmerged, and made no claim about the Element SDK layer beyond "checked before render*() dispatch." This phase audited every overlapping method by reflection-based source comparison (not manual code reading alone), classifying each and acting on the evidence rather than assumption.

Of the 61 overlapping method names, 32 were byte-identical (pure duplication, deleted). A further batch were functionally identical but for cosmetic formatting or a default-argument-vs-explicit-argument difference (`editableAttr('x', 'text')` vs `editableAttr('x')`) — also deleted. Several were genuine, real defects specific to customer-site's independent fork: `renderStatsRow()` hardcoded "Why Choose Acool?"/"Delivering excellence in HVAC services..." English business copy directly into the generic canonical `stats_row` type — the exact ADR-027 anti-pattern (fabricated business content baked into shared block markup), missed because it lived in an independent fork ADR-027's audit never reached — plus unconditional white text with no contrast safety and genuinely dead, unused code from a copy-paste. `renderTrustBadges()` had the identical hardcoded-business-content defect ("Why Choose Us"). `renderClientLogos()` was a parallel, non-marquee implementation predating (and never receiving) Phase 2g's fix. All three were deleted outright, not merged — real defects, not divergent-but-valid behavior. `buildCssDeclarations()`'s two independent copies differed in a way that mattered: customer-site's skipped `escapeAttr()` on `bgColor`/`textColor`/`borderColor` before interpolating them into an inline `style` attribute — a real safety gap the canonical version didn't have. `applyBlockStyle()`'s two copies used different CSS class names for the same concept (`tb-anim-` vs `tb-animate-`) — a silent, real markup divergence with no functional justification. `renderNewsletterForm()`'s host copy was missing the canonical version's `visuallyHiddenLabel()` call entirely — a real accessibility regression. A `renderFlex_container()`/`renderNav_menu()`/`renderSocial_icons()` family of underscore-cased methods (12 of them) turned out to be **permanently unreachable dead code**: `Str::studly()`-based dispatch never produces an underscore, so these methods — some containing genuinely richer logic than their reachable camelCase sibling (`renderSocial_icons()` had a `context['social_links']` fallback and `rel="noopener noreferrer"` the live `renderSocialIcons()` lacked) — could never execute. The richer logic was promoted into the canonical shared method before the dead code was deleted, not discarded.

One dead-code deletion caused a real, test-caught regression: `renderProductGrid()` (the live, dispatchable name) was a one-line forwarder to `renderProduct_grid()` (the "dead-looking" underscore name) — the naming convention was inverted for this one pair relative to every other case audited. Deleting the underscore method broke real Product-listing rendering; `BlockRendererLicenseGateTest` caught it immediately. The real logic (including a commercial-licensing gate added to the working tree after this repository's last commit, itself nearly lost by restoring from git HEAD instead of the actual pre-edit file) was recovered and merged directly into the one dispatchable method name, eliminating the pointless forwarder-plus-target pair.

**A genuinely new, previously-undocumented architectural finding**: `config/builder.php`'s `builder.elements` array actively registers 13 `App\Builder\Elements\*` classes with `App\Builder\ElementRegistry`, checked inside `renderBlockUnsafe()` *before* any `render*()` dispatch. Ten of those registered types — `heading`, `text`, `image`, `button`, `divider`, `section`, `container`, `grid`, `columns`, `hero` — collide with canonical shared `BlockRenderer` method names. For these ten types, on customer-site, the Element SDK is the actual live renderer; neither this class's own `render*()` methods nor the now-inherited canonical ones are ever reached. This is a **second, active, alternate canonical authority** for a meaningful slice of the most fundamental block types, not the "dormant extension infrastructure" it was assumed to be at the start of this phase. A spot-check of `HeroElement::render()` found its output markup-equivalent to (and, for `button_url`, more defensive than) the canonical `renderHero()` — so this is not currently a known correctness bug, but it is a real, undissolved second authority this phase did not have the scope to reconcile with the shared package.

**Decision:**
1. **32+ byte-identical and functionally-identical duplicate methods deleted** from customer-site's `BlockRenderer` — real inheritance now serves every one. 12 permanently-dead underscore-named methods deleted; the one with real, richer logic (`renderSocialIcons()`'s context-fallback + `rel="noopener"`) was promoted into the canonical shared method first.
2. **Three real, hardcoded-business-content/broken defects removed outright** (`renderStatsRow`, `renderTrustBadges`, `renderClientLogos`) — customer-site now inherits the canonical, already-contrast-fixed, marquee-correct implementations.
3. **Three real cross-repo behavior divergences resolved toward canonical**: `buildCssDeclarations()`/`editableAttr()`/`applyBlockStyle()` changed from `private` to `protected` in the shared class (a `private` method is invisible to a subclass — the literal reason customer-site had to keep full copies of these at all) and customer-site's copies deleted, adopting the canonical `tb-animate-` class name and the `escapeAttr()`-sanitized color handling. `renderFlex_container()` renamed to `renderFlexContainer()` in the shared class (a real, previously-silent dispatch bug affecting both hosts, not currently exercised by any of the 28 canonical themes' authored content). `spacingClass()` gained the `xs` mapping the shared CSS contract already had a rule for but the canonical method didn't recognize.
4. **`#[HostOwnedOverride(reason:, category:)]`** (new PHP attribute, `SaaSPlatform\ThemeRuntime\Attributes\HostOwnedOverride`) is the durable classification mechanism Section 8 required — co-located metadata on the method itself, not a separately-maintained allowlist file that can drift out of sync. `ThemeRuntimeCanonicalParityTest` (new) fails for any method overlapping a canonical shared name without this attribute; it caught one real, previously-unaudited overlap (`renderSearchBar`) the moment it was written, before the deletion pass. Every currently-retained override (real tenant-data queries; real `route()`-based URL generation; a real, unmerged `_style` custom-styling capability; a richer FAQ empty-state; the schema-validating dispatch wrapper) now carries one, with the honest reason recorded — several explicitly marked as "not byte-diffed this phase, conservatively retained" rather than falsely presented as fully audited.
5. **customer-site's `ThemeCanvas\BlockRegistry::all()` now merges the canonical registry in** (`self::$blocks + CanonicalBlockRegistry::all()`) rather than remaining fully disconnected — every canonical type is now reachable through it, with the host's own local definition winning for any name defined in both (zero behavior change to already-shipped schemas; the ~40 overlapping schema definitions were not individually field-diffed against canonical, matching the same conservative-retention posture as the renderer overrides). `emptyOutputPolicy()` added, delegating to the canonical authority (this registry had no such concept before). One deliberate pre-existing host removal (`module:gallery_widget` — no backend content type exists for it on this host) is explicitly excluded from the merge so it is not silently resurrected.
6. **The Element SDK finding is reported, not fixed.** Reconciling ten actively-used alternate-authority Element classes against the canonical renderer (or vice versa) is a body of work comparable to this phase's own renderer audit, and was not safe to attempt in the time remaining. `ElementSdkCanonicalBoundaryTest` (new) locks the exact known-collision set (`heading, text, image, button, divider, section, container, grid, columns, hero`) so a *new* collision fails loudly, and flags (via `markTestIncomplete`) if any of the known ones are ever resolved.
7. **`module:*` extension dispatch and customer-site's Motion bridge (`renderModuleMotion` et al.) were untouched** — verified still green by the full regression suite; none of this phase's changes touched `ThemeCanvasRenderer.php`'s module-dispatch methods.

**Reason:**
"Inheritance exists, therefore the fork is resolved" was ADR-029's own explicitly-flagged incompleteness — a subclass that redeclares every method it inherits from has not actually adopted the shared authority, it has merely proven the shared authority *could* apply. Verifying that claim required byte-level evidence per method, not a assumption that duplication is safe merely because it compiles and passes tests. The Element SDK finding is the clearest demonstration of why: it was invisible to every test and every code-reading pass in ADR-029 specifically because it operates through configuration and a dispatch-order side effect, not a class hierarchy — the kind of drift risk this whole initiative exists to eliminate could recur exactly this way inside the now-shared package too if a future host adds a config-driven interception layer without updating the parity tests.

**Impact:**
- `packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php` — `buildCssDeclarations`/`applyBlockStyle`/`editableAttr` visibility `private`→`protected`; `renderFlex_container`→`renderFlexContainer` rename; `spacingClass` gained `xs`; `renderSocialIcons` absorbed customer-site's richer logic.
- `packages/saas-platform/modules/theme-runtime/src/Attributes/HostOwnedOverride.php` — new.
- customer-site: `app/Services/ThemeBuilder/BlockRenderer.php` reduced from ~2016 to ~980 lines; `app/Services/ThemeCanvas/BlockRegistry.php` now merges the canonical registry.
- New tests: `tests/Unit/ThemeBuilder/ThemeRuntimeCanonicalParityTest.php`, `tests/Unit/ThemeCanvas/ElementSdkCanonicalBoundaryTest.php`, `tests/Unit/ThemeCanvas/BlockRegistryCanonicalMergeTest.php` (customer-site).
- Full regression (both repos) and a real, non-mocked 28-canonical-theme browser re-run (both the SaaS Quality Gate and customer-runtime verification): results recorded in the Phase 2i final report rather than duplicated here.
- **Explicitly NOT completed**: the Element SDK's ten-type alternate authority is unreconciled; ~15 customer-site overrides remain conservatively retained without a full field-by-field schema/markup diff against canonical; customer-site's own ~40 canonical-name registry entries were not individually verified identical to the canonical schema.

---

## ADR-031

**Date:** 2026-08-29
**Title:** Element SDK Reconciliation — Delegation, Not Deletion, Closes the Second-Authority Gap

**Context:**
ADR-030 (Phase 2i) reported, but explicitly did not attempt to fix, a real finding: `config/builder.php` actively registers 13 `App\Builder\Elements\*` classes with `App\Builder\ElementRegistry`, checked inside `BlockRenderer::renderBlockUnsafe()` *before* any `render*()` dispatch. Ten of those (`heading, text, image, button, divider, section, container, grid, columns, hero`) collide with canonical shared method names — for these types, the Element SDK, not either `BlockRenderer`, was the real live renderer on customer-site, entirely outside the shared authority Phase 2h/2i built.

Auditing the full pipeline (`ElementRegistry` → `ElementValidator` → `ElementRenderer` → `Element::render()`) found it is a genuinely more capable dispatch than plain method dispatch: real schema-driven prop validation/coercion, dynamic-token resolution (`{{site.name}}`) via `DynamicDataResolver`, per-element CSS/JS asset collection (`HasAssets`), and — in three of the ten colliding classes — a real capability the canonical shared `BlockRenderer` lacked entirely: `isValidUrl()`, rejecting `javascript:`-scheme and malformed URLs before they reach an `href`/`src`. This is legitimate infrastructure, not a legacy accident, and Phase 2i's own instruction not to redesign Motion Graphics generalizes here: delete nothing that provides real, working value.

**Decision:**
1. **Canonical authority remains `SaaSPlatform\ThemeRuntime`; the Element SDK is not removed from the dispatch path.** Instead, each of the 10 colliding `Element` classes' `render()` now delegates its structural markup to a new public `BlockRenderer::dispatchStructural(string $type, array $props, string $inner, string $id): ?string` — the same `Str::studly()` method-dispatch logic `renderBlock()` already used internally, now exposed as a stable extension seam. A class's own markup is kept only as a `?? $fallback` safety net if canonical ever lacks a matching method. `ElementSdkCanonicalBoundaryTest::test_every_known_collision_delegates_structural_rendering_to_canonical()` inspects each class's real source for the delegation call — a future edit that quietly reverts one to an independent implementation fails loudly, not silently.
2. **The Element SDK's real safety/capability advantages were promoted into canonical, not lost.** `BlockRenderer::isValidUrl()`/`escapeUrl()` (new, promoted from the SDK's `WithSanitization` trait) now gate every URL-bearing canonical prop (13 call sites: hero/CTA button URLs, button URL, image URL, social/gallery/card links) — canonical is now at least as safe as either previous implementation, not merely consolidated. `renderButton()`'s `style` prop, previously interpolated into a `class` attribute with zero validation (a real, if narrow, attribute-injection gap the Element SDK's own allowlist check didn't have either — introduced when the fix was first written this phase and caught by the new security suite before it shipped), is now allowlisted. The `_style.background/layout/effects/typography` custom-styling capability — found identically duplicated a *third* time, in the Element SDK's `SectionElement`/`HeadingElement`/`TextElement` in addition to customer-site's own `BlockRenderer` overrides already flagged in ADR-030 — is now canonical, resolving three of ADR-030's `#[HostOwnedOverride]` entries outright (customer-site's own `renderSection`/`renderHeading`/`renderText` overrides deleted).
3. **A real, previously-invisible data-loss bug was found and fixed by the structural parity test this ADR also completes** (see below): `ElementRenderer::render()` unconditionally overwrote `$props['_style']` from `$context['style']` — silently discarding real authored per-block styling whenever a Theme stored it embedded in `props._style` (as canonical Theme content actually does) rather than as a sibling `block.style` key. A second layer of the same bug: `ElementValidator::validate()` rebuilds props from *only* the target element's own declared schema keys, treating `_style` (a cross-cutting runtime convention, not a per-element authored field) as an unknown key to strip. Both are fixed: `ElementRenderer::render()` now captures the real incoming style before validation strips it, and restores it after.
4. **`BlockRenderer::escape()`/`escapeAttr()` widened from `string` to `mixed`** — a security test (`test_malformed_style_object_does_not_throw`) found a corrupted/malformed `_style` value (an array where a color string is expected) threw an uncaught `TypeError` instead of degrading gracefully; a real tenant page must never 500 on corrupted authored data. This LSP-required a follow-on fix: `ThemeCanvasRenderer::escape()/escapeAttr()` (customer-site) redeclared these with the old narrower `string` signature — byte-identical, redundant duplicates of the now-inherited canonical implementation — and were deleted rather than widened.
5. **customer-site's `ThemeCanvas\BlockRegistry` gained a decoration path, not a full canonical rewrite.** Of 96 overlapping registry keys with canonical, 39 are byte-identical (already fully authoritative via the Phase 2i merge) and 57 differ — of those, all `module:*`-prefixed ones (the established extension-owned escape hatch) are expected, legitimate per-host definitions, not a canonical-authority violation. Spot-checking three representative non-module differences (`hero`, `stats_row`, `button`) across the full structural/content/simple spectrum found a *consistent* pattern: customer-site's local definition adds real Persian editor-UI `label` fields (and, for `button`, a genuinely richer `select`-with-`options` type canonical lacks entirely) while omitting canonical's `icon` field — real "registry decoration" (Section 7's own preferred model), not incompatible schemas. The remaining ~36 non-module entries were not individually field-diffed this phase — the evidence strongly suggests the same decoration pattern, but this is not yet proven for every entry (see Impact).
6. **The 28-theme structural parity contract Phase 2i did not complete is now built and passing.** `CanonicalStructuralParityTest` (customer-site) loads every real canonical Theme snapshot, extracts every distinct authored block type (recursively, including nested tabs/accordion/slider content), and — for every type customer-site does not explicitly `#[HostOwnedOverride]` — renders it through both the canonical class directly and customer-site's own `renderBlockUnsafe()` (the real end-to-end path, Element SDK included), asserting identical output. Zero unexplained divergences remain after the fixes in Decision 3. saas-platform's own output is not independently re-fetched: its `BlockRenderer` is a verified-empty subclass of the same canonical class (`ThemeRuntimeSharedAuthorityTest`), so canonical-direct output *is* saas-platform's Builder-preview output, by construction — comparing against it is a complete, not partial, proxy for "SaaS vs. customer-runtime" parity.

**Reason:**
Section 1's own instruction — "do not assume markup-equivalent means architecturally safe" — was proven correct by this phase's own findings: a spot-check in ADR-030 found `HeroElement`'s markup "structurally equivalent," which was true and also incomplete, because the real risk wasn't in the happy-path markup at all — it was in a context-merging line nobody was looking at, discovered only once a real structural-equivalence *test* (not a one-off manual read) ran real authored data through the real pipeline. Deleting the Element SDK to remove the second authority would have destroyed real, working validation/dynamic-data/asset infrastructure to solve a problem that delegation solves just as completely, with less destroyed value.

**Impact:**
- `packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php` — `dispatchStructural()` (new public delegation seam), `isValidUrl()`/`escapeUrl()` (new, applied at 13 call sites), `renderButton()` style allowlist fix, `escape()`/`escapeAttr()` widened to `mixed`, `renderSection()`/`renderHeading()`/`renderText()` absorbed `_style` handling, `renderGrid()` column clamp added for parity.
- customer-site: `app/Builder/Elements/{Heading,Text,Image,Button,Divider,Section,Container,Grid,Columns,Hero}Element.php` (all 10 now delegate); `app/Builder/Rendering/ElementRenderer.php` (the `_style` data-loss fix); `app/Services/ThemeBuilder/BlockRenderer.php` (`renderSection`/`renderHeading`/`renderText` deleted, now inherited); `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` (redundant `escape`/`escapeAttr` deleted); `tests/fixtures/builder-snapshots/text-default.html` (snapshot updated to reflect the deliberate `prose`-class/conditional-wrapper fix from ADR-030, now finally exercised end-to-end through the Element SDK path).
- New tests: `tests/Feature/ThemeBuilder/ThemeRuntimeSecurityTest.php` (saas-platform, 11); customer-site: `tests/Unit/ThemeCanvas/ElementSdkCanonicalBoundaryTest.php` (rewritten, 3), `tests/Unit/ThemeCanvas/CanonicalStructuralParityTest.php` (new, 1 — covers all 28 themes' real content), `tests/Unit/ThemeCanvas/ElementSdkExtensionSafetyTest.php` (new, 6).
- Full regression (both repos) and real, non-mocked 28-canonical-theme browser re-runs (both SaaS Quality Gate and customer-runtime): results in the Phase 2j final report.
- **Explicitly NOT completed**: ~36 of customer-site's non-module registry entries were not individually field-diffed against canonical (a consistent, low-risk decoration pattern is evidenced but not proven for every entry); the registry decoration is still implemented as "local wins the merge," not a formal canonical-schema-plus-host-metadata composition API; ~11 customer-site renderer overrides (real tenant-data/host-context adapters, plus a handful genuinely not re-audited this phase) remain, each still carrying its `#[HostOwnedOverride]` reason.

---

## ADR-032

**Date:** 2026-08-29
**Title:** Semantic Accessible-Foreground Tokens — Real WCAG AA Contrast Remediation for the 17 Flagged Canonical Themes

**Context:**
ADR-031 (Phase 2j) closed the Element SDK authority gap and left the 17 real, browser-measured WCAG AA contrast failures ADR-030 first found explicitly reserved for a dedicated phase. Root-causing all 17 (grouped by exact foreground/background pairing across every theme, not assumed unique per theme) found the failures reduced to a small number of recurring, shared-CSS/token patterns, not 17 independent palette mistakes:

1. `.tb-hero`/`.tb-cta`/`.tb-bg-primary`/`.tb-btn-primary` hardcoded `color: #fff` regardless of the Theme's own (fully author-configurable) `--tb-primary`. Roughly a third of the 17 themes chose a mid-luminance brand color (teal, orange, coral, cyan, pink) where white text measures as low as 2.06:1.
2. `.tb-stat-value`, `.tb-service-link`, `.tb-nav a:hover`, `.tb-team-role`, and seven other rules used `var(--tb-primary)` directly as literal TEXT color on a light surface — a color that is a perfectly good, vivid *background* hue is very often too light to serve as body-sized *text* at 4.5:1. This affected more themes and more distinct CSS rules than pattern 1.
3. The shared `--tb-text-muted` default (`#6b7280`, gray-500) measured as low as 3.94:1 against several themes' actual off-white surfaces — a canonical token default that was simply not dark enough, plus four hardcoded (non-token) `color:#6b7280` literals in placeholder text inside the shared `BlockRenderer` itself.
4. `renderStatsRow()`'s existing accessible-color logic (ADR-028) was real but *ineffective* for two of its own three text elements: it set `color` on the wrapper `<div>`, but `.tb-stat-value`/`.tb-stat-label` carry their own explicit CSS color rules — which win over an ancestor's merely-inherited inline color by CSS specificity, not inheritance. `renderContactInfo()`/`renderTrustBadges()` had no such logic at all, so acool-hvac-service's own dark-navy `background` prop (already used correctly by the now-fixed stats_row) produced illegible dark-gray-on-navy text elsewhere on the same page.
5. One genuinely dark theme (`nexus-pro-personal`, `background_color:#0f0f1a`/`text_color:#f1f5f9`) put its own correctly-light global text color onto a `.tb-testimonial-card` still styled with the light-surface default `--tb-surface` — an inherited-context mismatch, not a broken token.
6. Two isolated real seed-data values: one theme's configured `text_color` (`#2c3e50`) was not itself dark enough to serve as the "dark candidate" when deriving on-primary text for that theme's own primary color (a self-referential edge case pattern 1's original algorithm didn't anticipate); one theme had a second, unfixed instance of a color ADR-030 had already corrected once elsewhere in the same theme.
7. **A structural parity gap, not a contrast bug per se**: saas-platform's own Builder-preview CSS (`canvas-preview.blade.php`) had never been token-bound at all — `.tb-hero`/`.tb-bg-primary` were hardcoded to a fixed indigo regardless of the Theme being previewed. This meant the SaaS Quality Gate's own real-browser contrast check was structurally incapable of ever detecting patterns 1-2, because it never rendered a theme's actual primary color in the first place — explaining exactly why "SaaS 28/28" coexisted with "customer-runtime 17/28" for this whole class of defect.

**Decision:**
1. **`ThemeTokenNormalizer` gains real WCAG-ratio-based derivation** (`pickReadableOn()`, `deriveAccessibleForeground()` — actual relative-luminance/contrast-ratio math per the WCAG formula, not a brightness/luminance-threshold heuristic) and seven new semantic tokens computed from it: `--tb-on-primary`/`--tb-on-secondary`/`--tb-on-accent` (text painted on a background filled with that brand color) and `--tb-primary-text`/`--tb-secondary-text`/`--tb-accent-text` (that brand color used *as* text on a light surface) and `--tb-surface-text` (text on `--tb-surface`, independent of `--tb-text`, which is tuned for `--tb-background` instead). Each is a pure function of the Theme's own already-configured colors — no new authoring surface, no new required Theme fields. An already-accessible brand color is returned byte-for-byte unchanged (`deriveAccessibleForeground()`'s first check); only a genuinely failing one is minimally darkened/lightened in HSL space (hue preserved, only lightness moved, in small steps, capped at the true black/white extreme) — the "smallest adjustment that reaches reliable AA" the phase required, never a palette redesign.
2. **The on-primary/secondary/accent derivation's "dark candidate" is a fixed near-black (`#111827`), never the Theme's own configured `--tb-text`.** A Theme's body-text color is tuned for its own body background, not verified against every possible brand color — using it anyway was the exact bug that left 4 themes at 3.35-4.19:1 (just under AA) on the first remediation pass; `#111827` cleared all four with margin.
3. **Both hosts' runtime CSS updated to consume the new tokens** in place of the hardcoded/naive values (patterns 1, 2, and the saas-platform parity gap): customer-site's `canvas-theme.blade.php` (the file the real browser check actually measures) and `resources/views/themes/default/layout.blade.php` (the secondary, non-canvas layout, fixed for consistency though not itself exercised by canonical-theme verification), and — closing the structural parity gap — saas-platform's own `canvas-preview.blade.php`, now genuinely token-bound for the first time.
4. **`renderStatsRow()` now applies its derived color as an inline style on every text-bearing child element directly** (title, value, suffix, label), not just the wrapper — defeating the CSS-specificity issue that made the ADR-028 fix silently ineffective for two of three text elements. `readableTextColorOn()` (the `BlockRenderer`-level entry point `renderStatsRow` calls) now delegates to `ThemeTokenNormalizer::pickReadableOn()` instead of its own brightness-threshold heuristic — one real algorithm, not two.
5. **Canonical token default**: `--tb-text-muted` darkened from `#6b7280` to `#4b5563` — verified by real contrast math to clear every one of the actually-measured failing backgrounds with margin (6.2:1+). The four hardcoded `color:#6b7280` literals inside `BlockRenderer`'s own placeholder-text methods were converted to `var(--tb-text-muted,#4b5563)`.
6. **`nexus-pro-personal` (a genuinely dark theme) now explicitly declares its own `colors.text_muted` (`#94a3b8`) at its canonical seed source** — the shared default is tuned for light themes and cannot correctly serve a dark theme's inverted brightness direction simultaneously; this is a real, theme-specific override of an explicitly-supported customization point, not an architectural gap. `.tb-testimonial-text` changed from `var(--tb-text)` to `var(--tb-surface-text)` — the correct semantic token for content sitting on `--tb-surface`, fixing the inherited-context mismatch for every theme, not just this one.
7. **Two isolated real seed-data corrections**, both applied at the canonical seed source and synced to the already-seeded dev-DB rows (not patched only in the database): `nexus-pro-store`'s second, previously-unfixed `module:support_button` instance of the same `#ea580c` color ADR-030 already corrected once for this theme (`#c2410c`, the identical already-validated replacement); no other theme required a direct color edit — every other one of the 17 was resolved entirely by the shared token/CSS fixes above, with zero per-theme palette changes.
8. **`theme-catalog:rebuild` confirmed idempotent** after the seed changes — re-run twice, 28/28 canonical membership verified both times, zero duplicate/error state. New `ThemeRelease` v1.0.1 published through the Mandatory Commercial Publish Gate for the two themes whose actual authored content changed (`nexus-pro-personal`, `nexus-pro-store`) — the v1.0.0 immutable history for both remains untouched; no release was created for the other 26 themes, whose own snapshot data did not change (only the shared renderer/CSS they already depend on did), consistent with the ADR-026/030 precedent that a shared-authority fix alone does not require a new release.
9. **The permanent rule**: canonical accessible-foreground derivation is a single shared authority (`ThemeTokenNormalizer`), consumed identically by both hosts' CSS — never a per-block, per-theme, or per-host color hack. A Theme's own primary/secondary/accent/background/text colors remain the Theme author's explicit choice and are never silently rewritten; only the *derived, secondary* on-color/text-on-surface tokens are computed, and only when the direct choice would genuinely fail WCAG AA. Scope is explicitly bounded: real, browser-computed color-contrast checks on resolvable text/background pairs only — this is not a claim of full WCAG compliance, and complex/unresolvable backgrounds (images, gradients, transparency compositing) remain honestly `UNVERIFIED`, per ADR-028's original contrast-verification scope, unchanged by this phase.

**Reason:**
Seventeen failing themes were not seventeen independent design mistakes — they were a small number of shared architectural gaps (a hardcoded color, a token default with no safety margin, an inline style silently overridden by CSS specificity, a token tuned for the wrong background) each manifesting differently depending on which brand color a given theme happened to choose. Fixing the shared gap once, and verifying the fix against every real theme's real color rather than assuming uniformity, is what let 15 of 17 themes resolve with zero code touching their own content at all — the two genuine per-theme corrections were real content facts (an already-known bad color reused a second place, a dark theme needing its own muted-text direction), not palette redesign.

**Impact:**
- `packages/saas-platform/modules/theme-runtime/src/ThemeTokenNormalizer.php` — real WCAG contrast-ratio math (`pickReadableOn`, `deriveAccessibleForeground`, `relativeLuminance`, `contrastRatio`, HSL helpers); 7 new semantic tokens; `--tb-text-muted` default darkened.
- `packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php` — `readableTextColorOn()` delegates to the normalizer's real math; `renderStatsRow()` applies color per-child-element; 4 hardcoded `#6b7280` literals converted to the token.
- customer-site: `resources/views/layouts/canvas-theme.blade.php` (13 rules token-bound/corrected), `resources/views/themes/default/layout.blade.php` (4 rules, consistency).
- saas-platform: `resources/views/admin/theme-builder/canvas-preview.blade.php` — `.tb-hero`/`.tb-cta`/`.tb-btn-primary`/`.tb-bg-primary` token-bound for the first time (closing a real SaaS-side verification blind spot).
- `app/Console/Commands/NexusSeedCommand.php` — `nexus-pro-personal`'s `colors.text_muted` added; `nexus-pro-store`'s second `#ea580c` instance corrected. Both synced to the live dev-DB rows; both re-published as `ThemeRelease` v1.0.1 through the Mandatory Publish Gate.
- New test: `tests/Unit/ThemeBuilder/ThemeTokenNormalizerContrastTest.php` (saas-platform, 12) — real contrast math, brand-color preservation, no-silent-failure, determinism.
- Real, non-mocked 28-canonical-theme browser verification (both SaaS and customer-runtime, desktop/tablet/mobile) and the Phase 2j structural parity contract re-run: results in the Phase 2k final report.

---

## ADR-033

**Date:** 2026-08-29
**Title:** Marketplace Merchandising Completeness — A Distinct Concept from Commercial Eligibility

**Context:**
Every prior Theme-productization phase (ADR-020 through ADR-032) hardened *technical* commercial correctness: structural rendering, dependencies, empty-output, contrast, shared runtime authority. None audited whether the 28 canonical themes were actually *presentable* in the Marketplace — a real, previously-unaudited gap. A fresh audit (not trusting prior phases' incidental counts) found 5 of 28 themes with real gaps: `nexus-pro-corporate`/`nexus-pro-personal`/`nexus-pro-store` had no `ThemeMarketplace` row at all (never created by `NexusSeedCommand`); `acool-hvac-service` and `sales-support-pro` had a row but no thumbnail/preview image/screenshots (screenshot capture had simply never been run for them). Separately, auditing structured dependencies found `nexus-pro-personal`/`nexus-pro-store` had zero dependency rows despite their own release snapshots already containing correct `settings.supported_modules` data — the backfill command had simply never been run for these two releases — and `sales-support-pro` had a real schema inconsistency (`supported_modules` nested under `settings.meta` instead of the top-level `settings` key every other canonical theme uses), which is why it also produced zero extractable dependencies despite genuinely using 10 real modules across 21 module-block instances.

**Decision:**
1. **Three genuinely distinct concepts, never conflated**: (a) **commercial eligibility** — `Theme::isCommerciallyEligible()`, fail-closed, driven by the release ledger + linked passing Quality Report (ADR-024/025), unaffected by anything in this ADR; (b) **Marketplace listability** — has a `ThemeMarketplace` row at all; (c) **Marketplace merchandising completeness** — real description, thumbnail, preview image, and screenshots present. A theme can be fully eligible and even listable while still being merchandising-incomplete (exactly the 5 themes found here) — this is expected, not a defect, and must never be blocking.
2. **`CommercialMetadataCheck` (existing, not a parallel system) strengthened**, not replaced: added `metadata.missing_thumbnail`/`metadata.missing_preview_image`/`metadata.missing_marketplace_description` — real, previously entirely unchecked gaps — all at `SEVERITY_WARNING`, consistent with this check's own established contract that marketing-presentation gaps never block a real, working commercial release.
3. **All gaps resolved via real, existing infrastructure — nothing fabricated.** The 3 missing `ThemeMarketplace` rows were created using each theme's own already-real `name`/`description` (the exact `Theme.description` already authored at seed time, reused verbatim as `marketplace_description` — not new marketing copy); `sales_count`/`is_bestseller`/`is_featured` set to their honest zero/false state, matching the established seeding convention, never inferred. All 5 themes' thumbnails/screenshots were generated by running the existing `theme:capture-screenshots` command unmodified — real Playwright renders of each theme's actual output through the same production render path, architecturally the same QUALITY-EVIDENCE-vs-MARKETING-ASSET distinction ADR-030 already established (this is the marketing path; quality evidence lives at a separate `theme-quality-evidence/` disk path and was never touched).
4. **Dependency gaps resolved by running the existing, already-idempotent `theme-release:backfill-dependencies` command** — no new dependency-inference logic was written. `nexus-pro-personal`/`nexus-pro-store` needed no seed change at all (their release snapshots were already correct); `sales-support-pro` needed a real, minimal seed-source correction (`SalesSupportThemeSeeder`: `supported_modules` added at the top level of `settings`, the nested `meta.supported_modules` copy left untouched rather than removed, since other code may already depend on it) — this is a genuine schema-consistency fix, not a fabricated dependency, since the underlying module list itself was already real, authored data.
5. **Release semantics respected**: `sales-support-pro` required a new `ThemeRelease` (v1.0.1, Mandatory Publish Gate) because its actual settings *content* changed and dependencies are extracted from a release's immutable snapshot, not its live draft. The `ThemeMarketplace` row creation and screenshot/thumbnail generation for all 5 themes did **not** require a new release — merchandising data is explicitly separate from release-snapshot content (Theme release data vs. Marketplace merchandising data are never the same ledger).
6. **`theme-catalog:rebuild` re-verified idempotent** after all fixes — run twice, 28/28 canonical membership confirmed both times, zero duplicate `ThemeMarketplace` rows, zero duplicate dependency rows.

**Reason:**
A theme being technically correct and commercially eligible is necessary but not sufficient for it to actually sell — the Marketplace still needs something to show a buyer. Treating "eligible" and "merchandising-complete" as the same fact would either wrongly block valid releases over a missing thumbnail, or wrongly let a genuinely broken release hide behind good marketing copy — this ADR keeps the two facts structurally separate so neither can masquerade as the other.

**Impact:**
- `app/Services/ThemeBuilder/QualityGate/Checks/CommercialMetadataCheck.php` — 3 new WARNING-only findings.
- `app/Console/Commands/NexusSeedCommand.php` — `ThemeMarketplace::updateOrCreate()` added for all 3 modes (seed-source, for future fresh installs) and applied directly to the 3 already-seeded dev-DB theme rows (existing themes are skip-on-exists at the seed level, so a live-DB sync was required in addition, mirroring the ADR-030/032 precedent for existing-row corrections).
- `database/seeders/SalesSupportThemeSeeder.php` — top-level `settings.supported_modules` added.
- Real, non-mocked runs (not simulated): `theme:capture-screenshots` for 5 themes (14 real screenshots captured), `theme-release:backfill-dependencies` (3 releases, 18 dependency rows extracted from already-real snapshot data), `theme-catalog:rebuild` (×2, idempotency proof).
- New `ThemeRelease` v1.0.1 for `sales-support-pro` only (Mandatory Publish Gate); no release created for the other 4 merchandising-only fixes.
- New test: `tests/Feature/ThemeBuilder/MarketplaceCompletenessTest.php` (6) — the eligibility/listability/completeness distinction, warning-only enforcement, no-fabrication guarantees.
- Final catalog result: 28/28 themes commercially eligible AND Marketplace merchandising-complete (real description, thumbnail, preview image, ≥2 real screenshots, accurate structured dependencies) — recorded in the Phase 2l final report.

---

## ADR-034

**Date:** 2026-09-02
**Title:** Extension Identity/Namespace + Fail-Closed Collision Contract (Phase 3E-B)

**Context:**
A Phase 3E-A read-only architecture audit (Extension SDK for the customer-site Theme Builder) found three independent block/element registration systems — canonical `SaaSPlatform\ThemeRuntime\BlockRegistry`, customer-site's own `App\Services\ThemeCanvas\BlockRegistry`, and customer-site's `App\Builder\ElementRegistry` (the Element SDK) — with **inconsistent collision precedence** and **zero collision detection**: `ThemeCanvas\BlockRegistry` merges as `self::$blocks + canonical::all()` (host wins), the opposite of canonical's own `registerHostType()` convention (`$blocks + $hostTypes`, canonical wins), and neither merge rejects or even logs a genuine collision — a duplicate type string is silently resolved by PHP array-merge key order. This is unacceptable before exposing real third-party extension registration. The audit also confirmed the existing Element SDK (13 registered elements: 10 delegating to canonical structural rendering, 3 host-only — spacer/row/column) should be **evolved**, not replaced, and that the existing generic/opaque block schema and pure, type-blind `ThemeCustomizationDiff`/`ThemeUpgradeReconciler` already handle unknown/extension block types safely with zero extension-specific reconciliation hook required.

Auditing `ThemeCanvas\BlockRegistry::all()` directly (not from the prior audit's summary) found its host-wins precedence is a **deliberate, already-shipped Phase 2i decision**, not an accidental bug: ~40 locally-defined types share a canonical name, explicitly never re-verified schema-field-by-field against canonical (the file's own comment calls a precedence flip "an unreviewed schema change reaching a live tenant runtime"). Flipping that precedence now would risk exactly the live-behavior change this phase is required to avoid. Separately, `registerHostType()` was confirmed called from real application code nowhere in either repository — only from one saas-platform test (`ThemeRuntimeSharedAuthorityTest::test_registering_a_host_type_never_overrides_a_canonical_definition`), which asserted the OLD silent-precedence contract (register succeeds, canonical definition simply stays unchanged, no error raised).

**Decision:**
1. **Canonical `registerHostType()` (`packages/saas-platform/modules/theme-runtime/src/BlockRegistry.php`) now fails CLOSED on collision** — throws a new `BlockTypeAlreadyRegisteredException` (named static factories `canonicalCollision()`/`hostCollision()`) instead of silently keeping the canonical value. Registry state is never mutated by a rejected call. Re-registering the exact same type with an identical definition is treated as an idempotent no-op (not an error) — required because a persistent worker (Octane) can legitimately re-run a service provider's `boot()` in the same process (the same lesson Phase 3D-F's dependency-resolver singleton→transient fix already established for a different subsystem). Safe to change: confirmed zero real call sites exist; the one saas-platform test exercising collision was updated in this phase to assert the new contract, plus new tests added for idempotent re-registration and non-colliding registration.
2. **`ThemeCanvas\BlockRegistry`'s existing ~40-type grandfathered host-wins overlap is explicitly left unchanged** — flipping it was evaluated and rejected as out of this phase's safe scope (would require the individually-unaudited schema diff Phase 2i's own comment already flagged as a separate, larger task, and would violate this phase's "no rendering behavior change" constraint). Instead, a small, additive **new** governance layer enforces the fail-closed contract prospectively for anything new, without touching this pre-existing, load-bearing merge.
3. **New extension identity namespace: `ext:{vendor-slug}:{block-name}`** (`App\Services\ThemeCanvas\Extension\ExtensionBlockTypeIdentity`), reusing the existing `module:` prefix convention's proven shape rather than inventing a new one — verified beforehand that no code anywhere parses a block `type` string expecting a fixed number of colons (only ever a simple `str_starts_with($type, 'module:')` prefix check), so a second colon is safe. Vendor slug: lowercase ASCII `[a-z0-9]+(-[a-z0-9]+)*`, 2-40 chars. Block name: lowercase ASCII `[a-z0-9]+(_[a-z0-9]+)*`, 1-60 chars. Deliberately not Unicode-aware — these are developer-facing identifiers, not user content.
4. **Reserved namespaces, explicitly enforced** (`ExtensionBlockTypeRegistry::assertNotReserved()`, directly testable independent of the vendor/block validation that already prevents these from being constructed by accident): `module:` (existing host/module system — never repurposed), `core:` (reserved for future host use, unused today), and bare/unprefixed (existing canonical + host + Element SDK types). An extension registration may only ever land in `ext:`.
5. **New `App\Services\ThemeCanvas\Extension\ExtensionBlockTypeRegistry`** — the single governance authority for extension identity, deliberately NOT a fourth competing registry: it registers no rendering/editor/asset behavior (that is a later phase's concern), only answers "may this identity be claimed?" by consulting all three existing registries (canonical, `ThemeCanvas\BlockRegistry::all()`, `Builder\ElementRegistry::has()`) as the reserved keyspace before adding to its own small table. Fails closed on any canonical, host, or cross-extension collision; same identical-definition idempotency allowance as (1); never mutates state on rejection.
6. **`App\Builder\ElementRegistry::register()`/`load()` minimally hardened**: rejects re-registering an existing type under a different class (duplicate-registration guard), and rejects claiming the `ext:` namespace or an identity already registered as an extension. Deliberately does **not** check against canonical/host bare names — 10 of the Element SDK's 13 current elements intentionally share a bare canonical name by design (Phase 2j: they delegate structural rendering to that same canonical implementation), so guarding against that overlap would break all of them, not fix a defect.
7. **No rendering, editor, asset, dependency, or reconciliation behavior changed.** No canonical block definition changed. No persisted block `type` renamed. No Theme JSON/DB migration. `ThemeCustomizationDiff`/`ThemeUpgradeReconciler` untouched — confirmed (Phase 3E-A) to already handle unknown/extension-typed blocks correctly with zero type-value branching, so no reconciliation hook was added or is needed.

**Reason:**
A commercial Extension SDK cannot be safely exposed to third parties on top of a foundation where two of its three existing registries already disagree about who wins a collision and neither one can even detect one. The fix has to happen at the identity/namespace layer, before any renderer/editor/asset contract is built on top of it — otherwise every later phase inherits an ambiguous foundation. Where an existing, already-shipped precedence decision (the ~40-type `ThemeCanvas\BlockRegistry` overlap) could not be corrected without risking exactly the kind of unreviewed live-behavior change this phase must avoid, the safer, still-effective fix was to make all NEW registration paths fail closed and leave the grandfathered case explicitly documented rather than silently touched.

**Impact:**
- `packages/saas-platform/modules/theme-runtime/src/BlockRegistry.php` — `registerHostType()` now fails closed; new `isCanonicalType()`/`isHostRegisteredType()`; `resetHostTypes()` also clears the new canonical-only snapshot cache.
- `packages/saas-platform/modules/theme-runtime/src/BlockTypeAlreadyRegisteredException.php` (new).
- `tests/Feature/ThemeBuilder/ThemeRuntimeSharedAuthorityTest.php` (saas-platform) — existing collision test updated to assert the new fail-closed contract (registry state unchanged, exception thrown); 3 new tests (idempotent re-registration, rejected differing re-registration, non-colliding registration).
- customer-site: `app/Services/ThemeCanvas/Extension/{ExtensionBlockTypeIdentity,ExtensionBlockTypeRegistry,ExtensionIdentityRegistrationException}.php` (new); `app/Builder/ElementRegistry.php` (minimal `assertClaimable()` guard added to `register()` and `load()`).
- New test: `tests/Feature/ThemeCanvas/Extension/ExtensionIdentityCollisionContractTest.php` (customer-site) — 25 tests covering the full identity/namespace/collision matrix (canonical collision, host collision, extension-vs-extension collision, reserved-namespace rejection for `module:`/`core:`/bare, malformed identity rejection, idempotency, rejected-registration state-unchanged, deterministic order-independence, backward compatibility of every existing persisted type string).
- No production rendering, editor, dependency, or reconciliation code touched. No Theme JSON/DB migration. No Motion/PWA/Push change. This phase establishes identity/registry governance only — a renderer contract, editor controls, assets, and actual third-party extension consumption remain future phases.

---

## ADR-035

**Date:** 2026-09-02
**Title:** One Rich-HTML Sanitization Authority — Element SDK Sanitizer Duplication Removed (Phase 3E-C)

**Context:**
Phase 3E-A's read-only audit found two independent, pre-Phase-3C-style sanitizers still live in customer-site's Element SDK: `App\Builder\Traits\WithSanitization::sanitizeHtml()` and `App\Builder\Validation\ElementValidator::sanitizeRichHtml()`, both a plain `strip_tags($s, '<p><br><a><strong><em><ul><ol><li><h2><h3>')` with no attribute stripping and no href/URL-scheme validation — structurally the exact same stored-XSS class Phase 3C fixed in the canonical shared `BlockRenderer::sanitizeHtml()` (an event handler or `javascript:` href on an otherwise-whitelisted tag passed straight through). Re-auditing at implementation time confirmed the live call graph precisely: `TextElement`/`ButtonElement`/`HeroElement`/`ImageElement` all call the local `WithSanitization`/`isValidUrl()` copy, but because all four types also have a canonical `render*()` counterpart, `dispatchStructural()` always resolves successfully and the local, vulnerable computation is presently dead code — reachable output today was already safe via canonical. `ElementValidator::sanitizeRichHtml()` had zero call sites among the 13 registered elements. This does not reduce the severity of the finding: it is a live landmine for the next element author, and for the Extension SDK identity namespace Phase 3E-B just established (`ext:*`), any future extension renderer calling this public utility would inherit the unpatched behavior with no warning.

**Decision:**
1. **New `SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer`** (`packages/saas-platform/modules/theme-runtime/src/Security/HtmlSanitizer.php`) — a small, stateless, fully `public` static class holding `isValidUrl()`/`escapeUrl()`/`sanitizeRichHtml()`, extracted **verbatim** (not reimplemented) from canonical `BlockRenderer`'s existing Phase 3C-hardened protected methods. Public and static specifically because the Element SDK's `App\Builder\Elements\*` classes are static and do not (and must not, per Phase 2j's own boundary) inherit from `BlockRenderer` — this is the "narrowly-scoped public sanitizer" the smallest-safe-API-change guidance called for, not a broadening of `BlockRenderer`'s own surface.
2. **Canonical `BlockRenderer`'s `isValidUrl()`/`escapeUrl()`/`sanitizeHtml()` are now thin protected delegating wrappers** to `HtmlSanitizer` — zero behavior change (proven by the full pre-existing `ThemeRuntimeSecurityTest` suite passing unmodified), but the actual regex/logic now exists in exactly one place system-wide, including within canonical itself.
3. **`WithSanitization::sanitizeHtml()`/`isValidUrl()` now delegate to `HtmlSanitizer`** — the vulnerable local copy is gone, not patched-in-place; `escape()`/`escapeAttr()` (plain `htmlspecialchars()`) were left untouched — trivial, correct, not part of this vulnerability class.
4. **`ElementValidator::sanitizeRichHtml()` now delegates to `HtmlSanitizer`** — same fix, same reasoning, for the second independent duplicate.
5. **No local DOM parsing, URL validation, or permissive fallback allowlist was introduced anywhere.** No canonical block definition, rendering output, registry, identity contract, reconciliation, dependency, Motion, or PWA/Push code was touched.

**Reason:**
A commercial Extension SDK (Phase 3E-B) is being built on top of this Element SDK; every future extension renderer that reaches for "the sanitizer" must find exactly one, already-hardened implementation, never a second copy someone forgot Phase 3C ever happened to. Currently-dead vulnerable code is still a defect — the fix belongs at the point where the duplication was created (Element SDK delegates to canonical), not by asserting the current call graph happens to route around it today.

**Impact:**
- `packages/saas-platform/modules/theme-runtime/src/Security/HtmlSanitizer.php` (new).
- `packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php` — `isValidUrl()`/`escapeUrl()`/`sanitizeHtml()` reduced to delegating wrappers.
- customer-site: `app/Builder/Traits/WithSanitization.php`, `app/Builder/Validation/ElementValidator.php` — both duplicate sanitizers now delegate to `HtmlSanitizer`.
- New tests: `tests/Unit/ThemeBuilder/HtmlSanitizerTest.php` (saas-platform, 12) direct coverage of the extracted authority; `tests/Unit/Builder/ElementSdkSanitizationDelegationTest.php` (customer-site, 13) — proves both Element SDK sanitizers now produce byte-identical output to canonical for the same malicious payloads, plus two stored-XSS integration tests through the real `App\Builder\Rendering\ElementRenderer` schema-validate→render pipeline.
- Full pre-existing `ThemeRuntimeSecurityTest` (saas-platform) re-run unmodified and green — proves canonical's observable behavior is byte-for-byte unchanged by the extraction.
- **The permanent rule**: canonical Theme Runtime (`SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer`) is the one rich-HTML/URL sanitization authority for the entire platform. No host, Element SDK class, or extension renderer may maintain its own independent copy of tag/attribute/URL-scheme sanitization logic — every consumer delegates here.

---

## ADR-036

**Date:** 2026-09-02
**Title:** Declarative Element/Control Definition Contract (Phase 3E-D)

**Context:**
Phase 3E-A found Element SDK metadata (type, label, category, icon, props/schema, editor control hints, container status, dynamic-data/asset capability) scattered across `ElementInterface`'s static methods, `getSchema()`'s ad hoc array shape, three optional capability interfaces (`HasAssets`/`HasDynamicData`/`HasValidation`), and a hardcoded editor container-type list — usable but not formalized into one declarative contract an extension author (Phase 3E-B's `ext:` namespace) could target. Re-auditing at implementation time confirmed the real current schema-field vocabulary is only `string`/`integer`/`boolean`/`enum` (no element currently uses `array`), and that `canvas-editor.blade.php`'s hardcoded `isContainerType()` list belongs to an entirely different registry (`ThemeCanvas\BlockRegistry`, the header/footer/pages theme_data blob editor) than the Element SDK (`App\Builder\*`, the page-builder domain) this phase formalizes — the two were never the same system (a Phase 3E-A finding), so replacing that specific hardcoded list was correctly out of this phase's reachable scope, not merely deferred.

An existing max-nesting-depth guard was found (`ElementSchemaRegistry::validate()`, `config('builder-element-schemas.limits.children_max_depth')`, default 20) — but it belongs to the same other (ThemeCanvas) domain, not the Element SDK. The Element SDK's own save path (`PageBuilderController::update()`) has no depth guard today, and never did — declaring `is_container`/`allowed_children` metadata does not create or worsen that pre-existing, independent gap (nesting was already fully unrestricted before this phase), so no new depth enforcement was added; the gap is documented and deferred, per this phase's own explicit permission to do so absent evidence of a newly-real risk.

**Decision:**
1. **New `App\Builder\Definition\ThemeBuilderControlDefinition`** — one control's editor-widget type + validation rule, built via `fromSchemaField()` from the EXACT field-rule shape every element's `getSchema()` already returns (`type`/`required`/`default`/`max`/`values`). A new, optional `control` key (additive, backward compatible) names the V1 editor widget explicitly (`text`/`textarea`/`number`/`toggle`/`select`/`color`/`media`/`url`/`spacing`/`responsive`) when the existing validation `type` alone is ambiguous (e.g. a URL or media reference is validation-type `string`); otherwise the control type is inferred from the validation type. Unknown control types and select controls with no options fail closed at definition-construction time only — never during ordinary prop validation. `repeater`/`group` were evaluated and explicitly deferred: no current element's schema has any repeating/grouped sub-structure to justify them.
2. **New `App\Builder\Definition\ThemeBuilderElementDefinition`** — `fromElementClass()` is pure introspection over the existing `ElementInterface` contract plus the already-established optional capability interfaces; it changes no element's own `render()`/`getSchema()`. `toLegacySchema()` converts back to the exact array shape `ElementValidator::validate()` already consumes, so `ElementValidator::validateDefinition()` (new) delegates entirely to the existing, unmodified `validate()` — coercion/defaults/unknown-key-stripping/non-throwing behavior is byte-identical, not reimplemented.
3. **New `App\Builder\Contracts\HasContainerCapability`** (optional interface, matching the existing `HasAssets`/`HasDynamicData`/`HasValidation` pattern exactly) — `isContainer()`/`getAllowedChildren()`. Implemented on the 6 elements that were already, in practice, containers (`Section`/`Container`/`Grid`/`Columns`/`Row`/`Column`) — a purely additive declaration; no `render()` method changed. `Row` declares `allowedChildren: ['column']` (the already-established structural convention); the other 5 declare `null` (unrestricted) — no new restriction was invented without evidence one already existed. The element only ever declares this capability; nesting enforcement, where it exists, remains host-owned (unchanged in this phase).
4. **`App\Builder\ElementRegistry::definitions()`/`definition()`** (new) — deterministically maps every currently-registered class through `ThemeBuilderElementDefinition::fromElementClass()`. Purely additive: `register()`/`load()`/`assertClaimable()` (Phase 3E-B's fail-closed identity/collision contract) are completely untouched, so every 3E-B guarantee continues to apply unchanged to what may register.
5. **No parallel Builder framework, no second schema format, no new registry.** No canonical block definition, rendering output, `ThemeCanvas\BlockRegistry`, `ExtensionBlockTypeRegistry`, reconciliation (`ThemeCustomizationDiff`/`ThemeUpgradeReconciler`/`ThemeBlockIndex` — untouched, definitions are never consulted during upgrade/reconciliation), Motion, dependency resolver, or PWA/Push code was touched. `canvas-editor.blade.php`'s hardcoded container list was left alone — it is out of the Element SDK's own domain, not a deferred fix.

**Reason:**
An extension author (Phase 3E-B) needs one declarative shape to target, not three scattered metadata sources plus tribal knowledge of a hardcoded list. The safest way to formalize that shape without risking the 13 already-shipped elements is to make the new contract a pure, introspective adapter over what already exists — same pattern 3E-C used for sanitization (extract the single authority, delegate to it) applied here to definition/metadata instead of security logic.

**Impact:**
- New: `app/Builder/Definition/{ThemeBuilderControlDefinition,ThemeBuilderElementDefinition,ElementDefinitionException}.php`, `app/Builder/Contracts/HasContainerCapability.php`.
- Modified (additive only): `app/Builder/ElementRegistry.php` (`definitions()`/`definition()`), `app/Builder/Validation/ElementValidator.php` (`validateDefinition()`), `app/Builder/Elements/{Section,Container,Grid,Columns,Row,Column}Element.php` (implement `HasContainerCapability`), `ImageElement.php`/`ButtonElement.php`/`SectionElement.php` (one `'control'` hint added to an existing `url`/`background_image` schema field each — metadata only, no validation/rendering change).
- New test: `tests/Unit/Builder/ThemeBuilderElementDefinitionTest.php` (24 tests) covering construction, rejection (duplicate keys, unknown control type, select-without-options), validator delegation and existing non-throwing coercion behavior, container/allowed-children metadata for all 6 real containers and 7 real non-containers, all 13 persisted type strings unchanged, canonical-delegating and host-only renderer output unchanged, unknown-type safe fallback with no definition required, dynamic-data/asset capability accuracy, and continued 3E-B identity-contract enforcement.
- No persisted type renamed. No Theme JSON/DB migration. No `schema_version` added to any persisted block instance. No reconciliation change. No Motion/PWA/Push change.

---

## ADR-037

**Date:** 2026-09-02
**Title:** Extension Dependency Adapter — Third-Party SDK Plugs Into the Existing Generic Dependency Architecture (Phase 3E-E)

**Context:**
The generic Theme dependency architecture (Phase 3E-A/3D-F, customer-site `App\Services\ThemeCanvas\Dependency\`) already exists and was re-confirmed unchanged before this phase: `ThemeDependencyTypeResolverContract` (the one registration point every adapter implements), `ThemeDependencyPresence` (an adapter's raw, unguessed fact), `ThemeDependencyAvailabilityResolver` (the single place constraint policy is applied, uniformly, to whichever adapter answers), `ThemeDependencyAvailabilityResult` (AVAILABLE/MISSING/VERSION_INCOMPATIBLE/UNVERIFIABLE — never a bare bool), `ThemeVersionConstraintEvaluator` (the one constraint grammar), and `ThemeUpgradeDependencyGate` (the one place a reconciliation result gains BLOCKING conflicts for unmet required dependencies). Exactly one real adapter existed before this phase: `ModuleDependencyResolver` (`dependency_type = 'module'`, backed by `TenantContext::hasModule()`). No `extension_dependencies` table, second constraint evaluator, or second gate existed or was needed — the existing `theme_release_dependencies` schema (`type`/`identifier`/`version_constraint`/`is_required` as already-separate fields, not a combined string) was confirmed sufficient without a migration.

**Decision:**
1. **New `App\Services\ThemeCanvas\Dependency\ExtensionDependencyResolver`** — a second real adapter for `dependency_type = 'extension'`, registered in `AppServiceProvider`'s existing `ThemeDependencyAvailabilityResolver` binding array alongside `ModuleDependencyResolver`, unchanged. No second dependency system: the same contract, the same resolver, the same evaluator, the same gate.
2. **Dependency identity is the extension PRODUCT — vendor AND package, never a bare vendor** — a vendor may publish several independent products, so a vendor slug alone was rejected as too coarse during review. New `App\Services\ThemeCanvas\Extension\ExtensionProductIdentity`: `{vendor-slug}/{package-slug}` (e.g. `acme/gallery-pro`), reusing `ExtensionBlockTypeIdentity::isValidVendorSlug()` verbatim (exposed `public`, same rule, no second copy) for the vendor half and a new, equally strict package-slug grammar for the second. This identity concept is deliberately separate from, and never derived from, the Phase 3E-B persisted block identity `ext:{vendor}:{block}` — one product may provide many block types; a release depends on the product, never on one block instance. A bare block name (no slash) or a bare vendor slug are both explicitly invalid product identifiers, proven directly.
3. **Package presence and tenant entitlement are two independent facts, each with its own provider — neither is a substitute for the other.** `InstalledExtensionProvider` (config-driven, `config('extensions.installed')`, keyed by product identity) answers ONLY "is this package's code present." The new `ExtensionEntitlementProviderContract` (V1 implementation: `ConfigExtensionEntitlementProvider`, config-driven, `config('extensions.entitlements')`) answers ONLY "is the CURRENT tenant entitled to this specific product" — keyed by the License model's own persistent `license_key`, deliberately NOT by `TenantContext::domain()` (which is ultimately derived from the current request's Host header — transient request data, not a stable identity) and deliberately NOT by generic `TenantContext::hasTenant()` alone. A first version of this adapter conflated "a package exists" + "some tenant/license exists at all" into availability; review correctly identified that as the actual defect — a real, valid license proves this installation has *some* active subscription, not that the subscription includes *this* product, and `ExtensionDependencyResolver::resolve()` now requires BOTH providers to independently agree before reporting presence. Both providers resolve fresh on every call and cache nothing of their own, proven directly: the same resolver/provider instance denies tenant B immediately after affirming tenant A, with no leakage in either direction.
4. **Registry presence is explicitly NOT proof of dependency availability.** Neither `ElementRegistry::has()` nor `ExtensionBlockTypeRegistry::has()` is consulted anywhere in `ExtensionDependencyResolver` or either provider — a block type being registered proves a PHP class exists in this process, not that this tenant may use the product it belongs to. Directly tested: a real `ext:acme:hero_banner` registration in `ExtensionBlockTypeRegistry` does not satisfy an `acme/gallery-pro` product dependency that is absent from `InstalledExtensionProvider`.
5. **No reconciliation change.** `ThemeCustomizationDiff`/`ThemeUpgradeReconciler`/`ThemeBlockIndex` were not touched — confirmed by a test asserting the reconciler's own source file contains no reference to the `Dependency` namespace at all. An unknown `ext:*` block still survives reconciliation regardless of its dependency's availability, proven directly.
6. **`ConfigExtensionEntitlementProvider` is explicitly a V1 bridge, not a Marketplace/licensing system.** No purchasing, orders, fulfillment, billing, or licenses table exists or is implied — a future real entitlement source (a Marketplace purchase record, a license table) replaces this one implementation behind the unchanged `ExtensionEntitlementProviderContract`, with no change required anywhere upstream (`ExtensionDependencyResolver`, the generic resolver/gate, or Theme upgrade). Default behavior for any tenant/product pair not explicitly configured is unavailable — fail closed, never silently granted. Marketplace fulfillment and the Motion adapter remain explicitly deferred — no `module:motion` rename, migration, or Motion-specific resolver.

**Reason:**
An extension's dependency availability is exactly the same kind of fact a module's is — "does this installation currently have the real thing, for the real, currently-active tenant" — so it belongs in the same generic system, answered by a new adapter, not a parallel Extension-SDK-specific dependency stack. Within that adapter, "the code is here" and "this tenant may use it" are genuinely different facts that happen to often be true together for a bundled, license-gated module (where one check already covers both) — collapsing them for a THIRD-PARTY extension would have let any tenant with any active subscription silently use any installed extension, which is not a decision this phase is authorized to make on the Marketplace's behalf.

**Impact:**
- New: `app/Services/ThemeCanvas/Dependency/{ExtensionDependencyResolver,InstalledExtensionProvider,ExtensionEntitlementProviderContract,ConfigExtensionEntitlementProvider}.php`, `app/Services/ThemeCanvas/Extension/ExtensionProductIdentity.php`, `config/extensions.php` (`installed` and `entitlements`, both empty by default — no real extension exists yet).
- Modified (additive only): `app/Services/ThemeCanvas/Extension/ExtensionBlockTypeIdentity.php` (`isValidVendorSlug()` exposed `public`), `ExtensionIdentityRegistrationException.php` (two new factories for malformed package slug/identifier), `app/Providers/AppServiceProvider.php` (`ExtensionEntitlementProviderContract` bound `bind()`-not-`singleton()` to `ConfigExtensionEntitlementProvider`; `ExtensionDependencyResolver` added to the existing adapter array).
- New test: `tests/Unit/ThemeCanvas/Dependency/ExtensionDependencyResolverTest.php` (21 tests) — product-identity construction/rejection (vendor-only and block-name-only both explicitly invalid), two independent products under one vendor never satisfying each other, package-presence-vs-entitlement fully crossed (installed-without-entitlement, entitled-without-installed, both-present), constraint-satisfied/incompatible/unverifiable-fails-closed via the unmodified generic evaluator, cross-tenant entitlement isolation on the same provider instance, generic tenant existence alone proven insufficient, registry-presence-is-not-entitlement (both registries), unaffected module resolver, real `ThemeUpgradeDependencyGate` blocking/passing generically, no reconciliation-file reference, and unknown-extension-block preservation independent of dependency state.
- No `extension_dependencies` table or any DB migration. No second constraint evaluator or gate. No persisted type renamed. No Theme JSON migration. No Motion or PWA/Push change. No Marketplace purchasing/fulfillment code.

---

## ADR-038

**Date:** 2026-09-02
**Title:** Unified Registry Ownership Boundary — One Derived Classification, No Fourth Registry (Phase 3E-F)

**Context:**
Re-auditing the three registration systems found by Phase 3E-A (canonical `SaaSPlatform\ThemeRuntime\BlockRegistry`, `App\Services\ThemeCanvas\BlockRegistry`, `App\Builder\ElementRegistry`) confirmed all three remain exactly as documented through 3E-B/3E-D: no drift. The 13 registered Element SDK types split cleanly — 10 (`heading`/`text`/`image`/`button`/`divider`/`section`/`grid`/`columns`/`hero`/`container`) exist as real keys in canonical `BlockRegistry`'s own array (directly re-verified by grep, not assumed) and delegate rendering to it; 3 (`spacer`/`row`/`column`) do not exist there at all and render locally. `ElementRegistry` itself stored no explicit "which kind of type is this" flag anywhere — the distinction was only ever implicit in which of the 13 classes happened to call `dispatchStructural()`.

The task's own suggested first mechanism for classifying the 3 host-only types — registering them via canonical `BlockRegistry::registerHostType()` (the real, established host-registration contract from ADR-030) — was evaluated and rejected: `App\Services\ThemeCanvas\BlockRegistry::all()` merges `self::$blocks + SaaSPlatform\ThemeRuntime\BlockRegistry::all()`, and canonical `all()` itself already merges in every host-registered type. Registering `spacer`/`row`/`column` there would therefore silently add all three as new addable block types to `ThemeCanvas\BlockRegistry::all()` — and from there, into `canvas-editor.blade.php`'s `BLOCK_LABELS`/`BLOCK_SCHEMAS`, changing observable ThemeCanvas admin-editor behavior (a completely different domain from the Element SDK, per 3E-A/3E-D) as an unintended side effect of an Element-SDK-only classification concern. This cross-domain leak — not merely a style preference — is why `registerHostType()` was NOT used for this phase's host-only types.

**Decision:**
1. **One deterministic ownership classification, computed, never stored**: `ThemeBuilderElementDefinition::$ownership` (`canonical_adapter` / `host_only` / `extension`), derived by a pure function of the type string against data that already exists — `ext:`-prefixed → extension; a type canonical `BlockRegistry` itself defines → canonical adapter; anything else registered in `ElementRegistry` → host-only. No new mutable registry, no separately-maintained flag anywhere to drift out of sync. `ElementRegistry::ownershipOf($type)` surfaces it.
2. **`spacer`/`row`/`column` are deliberately NOT registered anywhere new.** Their host-only classification falls out of the existing facts alone (not canonical, not `ext:`, already in `ElementRegistry`) — zero registration calls, zero new state, zero risk of the `ThemeCanvas` leak described above. Directly tested: neither `CanonicalBlockRegistry::isHostRegisteredType()` nor `ThemeCanvas\BlockRegistry::all()` gained anything for any of the three.
3. **The 10 canonical-delegating elements were never separately host-registered either** — proven directly (`isHostRegisteredType()` false for all 10) — their Element SDK class is a Builder/editor rendering adapter around the canonical implementation, not a second ownership claim on that type name.
4. **`ThemeCanvas\BlockRegistry` is completely unchanged in this phase** — the cross-domain risk discovered while evaluating decision #2 is itself the answer to "can a small read-only adapter safely reduce its duplication now": no, not without first individually auditing the ~40 grandfathered host/canonical-name overlaps ADR (Phase 2i) already flagged as a separate, larger, deliberately-deferred task. `canvas-editor.blade.php`'s hardcoded container list is untouched for the same reason it was deferred in 3E-D: it belongs to the ThemeCanvas domain, and this phase created no new shared, safe read model for that domain to consume.
5. **The extension registration path needs no fourth registry.** A synthetic `ext:acme:demo_block` test fixture proves the intended V1 sequence end-to-end using only existing pieces: `ExtensionBlockTypeRegistry::registerExtensionType()` claims the identity first (3E-B's fail-closed collision rules, unmodified); `ThemeBuilderElementDefinition::fromControls()` then represents it declaratively and classifies as `extension` — never through `ElementRegistry::register()`, which already rejects `ext:`-prefixed types by design (3E-B). No third-party plugin loader was built.
6. **Registry presence remains explicitly orthogonal to dependency availability/entitlement (3E-E).** Re-proven directly: classifying/registering an extension's block identity has zero effect on `ExtensionDependencyResolver`, which consults neither `ElementRegistry` nor `ExtensionBlockTypeRegistry` at all.

**Reason:**
A cleaner authority boundary only has value if it cannot be trivially answered by looking at existing, already-correct data — building a fourth store to hold that same answer a second time would be the exact anti-pattern 3E-A's audit condemned in the first place (three registries silently able to disagree). Deriving the classification, and rejecting the one registration path that would have leaked across an already-established domain boundary, keeps this phase a pure clarification of existing state rather than a new source of drift.

**Impact:**
- Modified (additive only): `app/Builder/Definition/ThemeBuilderElementDefinition.php` (new `$ownership` property + `OWNERSHIP_*` constants, computed by a new private `classify()`), `app/Builder/ElementRegistry.php` (`ownershipOf()`).
- New test: `tests/Unit/Builder/RegistryOwnershipBoundaryTest.php` (12 tests) — full 13-element classification, host-only/canonical-adapter non-leakage into canonical host registry and `ThemeCanvas\BlockRegistry`, extension/host/canonical bare-name collision rejection, order-independence, the synthetic `ext:acme:demo_block` V1-boundary proof (including failed-registration state-unchanged), and dependency/registry orthogonality.
- No fourth registry. No `ThemeCanvas\BlockRegistry` change. No `canvas-editor.blade.php` change. No persisted type renamed. No Theme JSON/DB migration. No reconciliation change. No dependency-entitlement semantic change. No Motion/PWA/Push change.

---

## ADR-039

**Date:** 2026-09-02
**Title:** First Complete V1 Extension Runtime Registration Path (Phase 3E-G)

**Context:**
By 3E-F every individual authority existed (canonical `BlockRegistry`, `ExtensionBlockTypeRegistry`'s fail-closed identity/collision contract, `ThemeBuilderElementDefinition`/`ThemeBuilderControlDefinition`'s declarative shape, `ExtensionProductIdentity`'s vendor+package identity, `ExtensionDependencyResolver`'s orthogonal entitlement check) but no single coordinated path proved a real extension package — product identity, one-or-more `ext:*` blocks, declarative definitions, renderers, assets — could register coherently and atomically. Auditing `ElementRenderer`/`ElementRegistry` while building this path surfaced a real, previously-unknown gap: `ElementInterface` formally declared only 7 methods, but `ElementRenderer::render()` and `ElementRegistry::all()` also called `supportsDynamicData()`/`getRequiredModule()`/`getDefaultSize()` unconditionally (unlike `HasAssets`/`HasDynamicData`/`HasContainerCapability`, which were already correctly interface-gated) — every one of the 13 built-in elements was safe only because all 13 extend `BaseElement`, which supplies those three as defaults. Initially left as documentation only; a targeted follow-up review correctly judged this could not stand as merely documented once 3E-G established a real, public third-party registration contract — a standalone extension author would otherwise learn about the undeclared requirement only from an undefined-method fatal at render time. Fixed in this same ADR, not deferred.

**Decision:**
1. **New `App\Builder\Definition\ThemeBuilderExtensionDefinition`** — the immutable package-level manifest: `ExtensionProductIdentity` (never a bare vendor), package version (plain metadata, never entitlement authority, never passed to the dependency evaluator by this class), an integer SDK contract (`SDK_CONTRACT_V1 = 1`; an unsupported value is rejected at construction, no semver negotiation), and one-or-more `ThemeBuilderExtensionBlock`s. Every block's vendor must match the product's own vendor — checked, not merely assumed, so a buggy/malicious provider returning a cross-vendor block is caught. Intra-package duplicate block types are rejected at construction.
2. **New `App\Builder\Extension\ThemeBuilderExtensionRegistrar`** — validates every block of a package against current registry state FIRST (a new read-only `ExtensionBlockTypeRegistry::isClaimable()`, plus an `ElementRegistry::get()` pre-check for class-mismatch), and only mutates anything if every single block would succeed. True atomicity via validate-then-mutate, not rollback — a package where block 2 of 2 fails leaves zero of that package's blocks registered, proven directly.
3. **New narrowly-scoped `ElementRegistry::registerExtensionElement()`** — the only path that can put an `ext:`-prefixed type into `ElementRegistry`, usable only by the registrar, and only after `ExtensionBlockTypeRegistry` already holds that exact identity claim. The ordinary `register()`/`load()` boot path still rejects `ext:*` exactly as 3E-B established — this is a second, explicit door, not a weakening of the first.
4. **Real bug found and fixed while wiring the two authorities together**: `ExtensionBlockTypeRegistry::assertAvailable()`'s `ElementRegistry::has($type)` defense-in-depth check was written in 3E-B when an `ext:` type could never legitimately appear there. Once the registrar makes that true and correct, the check's original ordering caused it to reject a package's own valid idempotent re-registration as a false "host collision." Fixed by resolving `self::$registered`'s own owner/definition equality FIRST — the authoritative idempotency signal — before ever consulting `ElementRegistry`, which now only fires for a genuine inconsistency (a type `ElementRegistry` holds that this registry never claimed).
5. **Package presence remains config-only for V1 (decision A of the two offered), deliberately not coupled to runtime registration.** A block successfully registering in-process (e.g. under test, or a provider present in a non-production environment) must never be conflated with "this package is authoritatively installed" (`InstalledExtensionProvider`, 3E-E) — proven directly: registering `acme/demo-suite`'s blocks has zero effect on `ExtensionDependencyResolver`, which still reports it unavailable absent independent `config('extensions.installed')`/`entitlements` entries.
6. **Loading is config-driven and deterministic** — new `config/theme_builder_extensions.php` (`providers`, empty by default), read once at one boot boundary (`AppServiceProvider::boot()`) by new `App\Builder\Extension\ThemeBuilderExtensionLoader`. No filesystem/Composer scanning, no class name ever accepted from an HTTP request. An invalid provider class fails closed. Idempotent by construction — no "already booted" state of its own, since the underlying registries' own idempotency (point 4) already makes repeating the exact same boot safe.
7. **The renderer contract is the existing one, not a new stack.** A block's renderer must implement `ElementInterface` (checked); once registered, it is dispatched by the completely unmodified `ElementRenderer::render()` — the same prop validation/coercion, the same exception-safe fallback, the same `HasAssets` collection any built-in element gets. A synthetic extension fixture's rich-text field is proven to go through the canonical `HtmlSanitizer` (3E-C) exactly like `TextElement` does, with a real stored-XSS payload (event handler + `javascript:` href + disallowed tag) neutralized identically — no "trusted extension HTML" bypass exists.
8. **No fourth registry.** Every new component (`ThemeBuilderExtensionDefinition`, `ThemeBuilderExtensionBlock`, `ThemeBuilderExtensionRegistrar`, `ThemeBuilderExtensionLoader`) is a coordinator, manifest, or loader — none owns a competing mutable type map; all mutation still happens through `ExtensionBlockTypeRegistry`/`ElementRegistry`'s own (one narrowly extended) methods.
9. **`ElementInterface` corrected to match what the runtime actually requires — and nothing more.** `getRequiredModule()`/`getDefaultSize()` were added as mandatory: both are universal editor/availability metadata every element genuinely has an answer for (even if "none"/"full"), the same tier as the already-mandatory `getIcon()`/`getCategory()`, and `BaseElement` already satisfied both without any change. `supportsDynamicData()` was deliberately NOT added — it is exactly the concept the existing optional `HasDynamicData` interface exists for; `ElementRenderer::render()`'s unconditional call to it was itself the bug, now fixed to gate it with `is_subclass_of($class, HasDynamicData::class)`, matching the pattern `ThemeBuilderElementDefinition::fromElementClass()` already used correctly. **`BaseElement` inheritance is not, and must never become, a hidden requirement for implementing `ElementInterface`** — proven directly by a standalone fixture class implementing only the interface (plus the one optional capability it actually uses) with no undefined-method failure anywhere in the pipeline. Zero observable behavior change for any of the 13 built-in elements (required-module, default-size, and dynamic-data behavior all independently re-verified identical).

**Reason:**
An architecture proven correct piece-by-piece is not the same as an architecture proven correct end-to-end — the multi-block atomicity requirement in particular could not be satisfied by any single existing authority alone (each is individually atomic per-call, but naively looping per-block calls across a package would have allowed a partial registration on failure). Building the smallest coordinator that sequences existing authorities correctly, and fixing the one real ordering bug that emerged only once they were actually wired together, is the right size for this phase — not a rewrite of any of them.

**Impact:**
- New: `app/Builder/Definition/{ThemeBuilderExtensionDefinition,ThemeBuilderExtensionBlock,ThemeBuilderExtensionRegistrationException}.php`, `app/Builder/Extension/{ThemeBuilderExtensionProviderContract,ThemeBuilderExtensionRegistrar,ThemeBuilderExtensionLoader}.php`, `config/theme_builder_extensions.php` (empty `providers` list).
- Modified (additive/bugfix): `app/Services/ThemeCanvas/Extension/ExtensionBlockTypeIdentity.php` (`fromType()` parser), `ExtensionBlockTypeRegistry.php` (`isClaimable()` + the ordering fix in point 4), `app/Builder/ElementRegistry.php` (`registerExtensionElement()`), `app/Providers/AppServiceProvider.php` (one `ThemeBuilderExtensionLoader::boot()` call at the end of `boot()`).
- New tests: `tests/Unit/Builder/Extension/ThemeBuilderExtensionRegistrarTest.php` (25 tests, using test-only fixtures `DemoCardElement`/`DemoContainerElement`/`DemoSuiteProvider` — no production demo extension) covering the full matrix: multi-block registration, product identity/version/SDK-contract retention and rejection, vendor-mismatch/malformed-identity/intra-package-duplicate rejection, canonical/host/extension collision rejection, duplicate-package idempotency vs. differing-definition rejection, atomic failure (zero partial blocks), `ElementRegistry` visibility and `extension` ownership classification, renderer dispatch/prop coercion, the stored-XSS regression, container-metadata survival, asset collection/dedup, idempotent repeated boot, dependency/entitlement orthogonality, unknown-block reconciliation preservation, and invalid-provider/invalid-renderer rejection.
- Corrected: `app/Builder/Contracts/ElementInterface.php` (`getRequiredModule()`/`getDefaultSize()` added; `supportsDynamicData()` deliberately not added), `app/Builder/Rendering/ElementRenderer.php` (`supportsDynamicData()` call now gated by `is_subclass_of(HasDynamicData::class)`). `app/Builder/Elements/BaseElement.php` required no change — it already satisfied the corrected contract.
- New test: `tests/Unit/Builder/ElementInterfaceContractTest.php` (11 tests) — the interface declares exactly the right mandatory set (present: `getRequiredModule`/`getDefaultSize`; absent: `supportsDynamicData`/`getAssets`/`isContainer`/`getAllowedChildren`/`validate`), `ElementRenderer`'s source is gated correctly, `BaseElement` and all 13 built-ins satisfy the contract, a standalone non-`BaseElement` fixture calls with zero undefined-method failure, required-module/default-size/dynamic-data behavior all unchanged for the 13 built-ins, and the dynamic-data resolver is proven never invoked for a class that does not implement `HasDynamicData`. The two 3E-G test fixtures (`DemoCardElement`/`DemoContainerElement`) had their now-unnecessary `supportsDynamicData()` implementations removed, so they demonstrate exactly the corrected public contract, nothing extra.
- No fourth registry. No `ThemeCanvas\BlockRegistry`/`canvas-editor.blade.php` change. No persisted type renamed. No Theme JSON/DB migration. No reconciliation change. No Motion/PWA/Push change. No Marketplace fulfillment.

---

## ADR-040

**Date:** 2026-09-03
**Title:** Production Builder Extension Adapter — Option B Convergence Slice (Phase 3E-H-A/3E-H-B)

**Context:**
A read-only audit (3E-H-A, this same day) set out to browser-verify the Extension SDK (3E-B–3E-G) through the real customer-facing Builder and found the premise itself was subtly wrong. `App\Builder\ElementRegistry`/`ElementRenderer` are NOT dormant diagnostics-only infrastructure — `App\Services\ThemeBuilder\BlockRenderer::renderBlockUnsafe()` already checks `ElementRegistry::has($type)` and dispatches to `App\Builder\Rendering\ElementRenderer::render()` for the 13 built-in `App\Builder\Elements\*` types, reached from the real `PageBuilderController::preview()` path — a fact already named "a second, active, alternate canonical authority" by ADR-030 and partially reconciled by ADR-031's `dispatchStructural()` delegation (10 of 13 types). What the audit found genuinely missing: the two production **authoring/validation gates** the Builder actually uses — `config('builder-elements.elements')` (the editor palette) and `config('builder-element-schemas.elements')` (the pre-render validation gate `ElementSchemaRegistry` reads) — were never made extension-aware. A `ThemeBuilderExtensionRegistrar`-registered `ext:*` type would successfully claim its identity and its renderer class, yet remain both unauthorable (absent from the palette) and unrenderable (`ElementSchemaRegistry::validate()` fails closed on "Unknown element type" *before* `renderBlockUnsafe()`'s `ElementRegistry::has()` check is ever reached). The audit also found these two config files have their own pre-existing, unrelated drift from each other (10 palette types missing a schema entry, 27 schema types absent from the palette) — a real, separate defect, explicitly out of this phase's scope.

Three convergence options were compared (full detail in the 3E-H-A report): (A) migrate the whole production Builder to `ElementRegistry` — high risk, ~42 non-trivial live-data types would need porting against the live product; (B) a thin, additive adapter feeding registered extension definitions into the two existing production gates, touching nothing else; (C) extract one shared neutral schema/definition contract and migrate incrementally. B was chosen as the immediate, low-risk unblock, explicitly framed as the first slice of C, not a final architecture — mirroring how this same codebase already converged two other narrow slices this week (3E-C's sanitizer, ADR-031's canonical-rendering delegation) rather than attempting one large rewrite.

**Decision:**
1. **New `App\Builder\Extension\ProductionBuilderAdapter`** — a pure, stateless read-model, NOT a registry: `palette(): array` and `schemas(): array`, each a transformation over the already-committed `ElementRegistry::definitions()`, computed fresh on every call, owning no state and mutating nothing.
2. **Extension-only filter, by design.** Only definitions whose `ownership === OWNERSHIP_EXTENSION` (3E-F) are surfaced. The 10 canonical-delegating and 3 host-only built-ins are deliberately excluded — they already have richer, hand-authored entries in both config files, and merging `ElementRegistry`'s auto-derived shape for them would risk shadowing or downgrading 12 of the 13 existing types. Only genuinely new `ext:*` identities — which by 3E-B construction can never collide with a bare production key — are ever added.
3. **Existing config remains sole, unconditional authority for every existing type.** Both integration points use `config(...) + ProductionBuilderAdapter::...()` — PHP's `+` keeps the LEFT (config) operand on any key collision, so no existing behavior can change even in principle, and the merge is also structurally collision-free by construction (§2).
4. **Two integration points, both minimal, both merges not replacements**: `PageBuilderController::buildRegistryData()`/`getElementsForSite()` (the palette) and `ElementSchemaRegistry::__construct()` (the render-time validation gate). No other production file touched.
5. **Deliberate, honest control-vocabulary mapping, not invented widgets.** Production's palette editor-type vocabulary (verified exhaustively against `config/builder-elements.php`: `string/select/color/richtext/image/textarea/boolean/number/info`) has no `url`/`spacing`/`responsive` equivalent — these three V1 control types degrade to their closest faithful representative (`url`→`string`, `spacing`→`select`-if-enum-else-`string`, `responsive`→`string`), documented as a cosmetic-only degrade: the underlying VALUE validation is driven separately by `schemaValidationType()` (mapping to production's narrower `string/integer/boolean/array` validation vocabulary — `enum` safely passes this coarse gate permissively, exactly as every existing type already does for anything outside that set, with the real enum-membership check still enforced downstream by the unmodified `ElementValidator`).
6. **`is_container` is reflected honestly in the schema gate (`children: 'blocks'` vs `'none'`) but NOT wired into `BlockRenderer::renderBlockUnsafe()`'s own, separate, untouched hardcoded container-type array.** A registered container extension type (e.g. `ext:acme:demo_container`) will therefore pass validation of nested child blocks but not yet have those children actually rendered — an honestly-reported, explicit remaining gap for a future 3E-H-C, not silently faked here.
7. **Full render reachability proven directly, end-to-end, through the real production class**: a registered `ext:*` block passes `ElementSchemaRegistry::validate()`, reaches `App\Services\ThemeBuilder\BlockRenderer::renderBlock()` (the actual class `PageBuilderController::preview()` instantiates), and renders real output — including a stored-XSS payload neutralized identically to every built-in type. A real HTTP `PUT` to `PageBuilderController::update()` (the actual `builder.page.update` route) is proven to accept and persist an `ext:*` block in `Page::builder_content_v2` with its type unchanged.
8. **Dynamic-data and asset-collection wiring remain explicitly untouched and unresolved** — 3E-H-A found both effectively dead in production (no resolver ever set on the real call site; no consumer of `ElementRenderer::getCollectedAssets()` found); this phase does not fix either, only documents them as remaining 3E-H-C-scoped debt. The pre-existing 10+27-key palette/schema-gate drift found in 3E-H-A is likewise explicitly not touched.

**Reason:**
The Extension SDK's own registration/collision/definition contracts (3E-B–3E-G) were all individually correct; what was missing was the last, smallest possible bridge to the two real gates the live product actually reads before any of that infrastructure matters. Building that bridge as a pure additive read-model — never a new registry, never a config-file replacement — keeps the fix proportional to the actual gap, while explicitly refusing to fake capabilities (container rendering, dynamic data, assets) the underlying production runtime does not yet genuinely provide.

**Impact:**
- New: `app/Builder/Extension/ProductionBuilderAdapter.php`.
- Modified (additive merge only): `app/Http/Controllers/Front/PageBuilderController.php` (`buildRegistryData()`/`getElementsForSite()`), `app/Services/ThemeBuilder/ElementSchemaRegistry.php` (`__construct()`).
- New test: `tests/Unit/Builder/Extension/ProductionBuilderAdapterTest.php` (18 tests) — baseline palette/schema-gate parity with no extension registered, registered-extension visibility and correct metadata/control mapping, canonical-adapter/host-only exclusion, atomic failure leaving zero partial palette/schema entries, idempotent repeated registration, unknown/malformed/unregistered `ext:*` still rejected, existing-config precedence, all pre-existing schema-gate types provably unaffected, and — the core proof — real render reachability + stored-XSS neutralization through the actual `App\Services\ThemeBuilder\BlockRenderer` class, plus a real HTTP `PUT` through the actual `builder.page.update` route persisting an `ext:*` block unchanged.
- No fourth/fifth registry. No existing production palette/schema entry changed. No `ThemeCanvas\BlockRegistry`/`canvas-editor.blade.php` change. No persisted type renamed. No Theme JSON/DB migration. No reconciliation change. No Motion/PWA/Push change. No Marketplace fulfillment. No container-rendering, dynamic-data, or asset-pipeline architecture change — all three remain explicitly open items for 3E-H-C. No browser verification performed in this phase.

## ADR-041

**Date:** 2026-09-03
**Title:** Save/Render Compatibility & Runtime Gap Closure (Phase 3E-H-C)

**Context:**
3E-H-B (ADR-040) proved a registered extension block reaches the two real production gates and renders through the real `App\Services\ThemeBuilder\BlockRenderer`, but left 5 runtime gaps open before browser verification could safely start: extension container children never actually rendered (the render-time container check was an untouched hardcoded 7-type array, separate from the schema gate's own honest `is_container` reporting); `HasAssets`-declared CSS/JS were collected per-call by `App\Builder\Rendering\ElementRenderer` but never read back by anything; an extension block persisted while its provider was loaded had no defined behavior once that provider was no longer booted (a real scenario — a tenant's extension subscription lapses, or a deploy omits a provider); a registered-but-throwing extension renderer's failure path was unverified; and there was no proof a temporarily-unavailable block's provider could be re-enabled without a migration/rewrite step.

**Decision:**
1. **Container detection made additively extension-aware, in two places, both by OR-ing a new condition onto the existing check — never replacing it.** `BlockRenderer::renderBlockUnsafe()`'s child-render gate and `wrapEditorMode()`'s canvas-selection-attribute gate both become `in_array($type, $containerTypes, true) || self::isExtensionContainer($type)`, where `isExtensionContainer()` is a new pure, static, side-effect-free helper: `str_starts_with($type, 'ext:') && ElementRegistry::definition($type)?->isContainer === true`. Every one of the 7 hardcoded bare types is completely unaffected (the new clause only ever evaluates for `ext:`-prefixed types); an unregistered/unavailable `ext:*` type is never treated as a container (`definition()` returns null), which is the same safe default any other unrenderable block already gets.
2. **Asset collection is made request-scoped and consumable, without a new asset manager.** `BlockRenderer` now lazily resolves and reuses ONE `App\Builder\Rendering\ElementRenderer` instance for the lifetime of the `BlockRenderer` instance (previously a fresh, immediately-discarded instance was resolved on every single block), so `HasAssets` contributions from every extension block on a page accumulate and dedupe (via `ElementRenderer`'s own existing `in_array` check) across the whole render. A new `BlockRenderer::getCollectedAssets(): array` exposes it. `BlockRenderer` is resolved fresh per request everywhere it is used (never bound as a singleton), so this carries no cross-request/cross-tenant leakage. Three consumers were bridged, all additive: `PageBuilderController::preview()`'s JSON response gains an `assets` key; `themes/default/page.blade.php` and `themes/default/home.blade.php` (the two real page-content render call sites for `Page::builder_content_v2`) now `@push` a `<link rel="stylesheet">`/`<script defer>` per collected asset into the existing `@stack('head')`/`@stack('scripts')` slots already present in `themes/default/layout.blade.php`. Script policy: `HasAssets::getAssets()` only ever returns asset URL strings (never inline code), so this bridge has no arbitrary-code-execution surface — CSS and `<script src>` references only. The `layout.blade.php` header/footer `BlockRenderer` calls (ThemeCanvas domain, same-file stack-ordering makes a `@push`-after-`@stack('head')` structurally impossible without reordering that file) are explicitly left unbridged — no `ext:*` requirement was identified there, and ThemeCanvas-domain views are out of this phase's scope.
3. **Unknown/unavailable-extension preservation gets one explicit, server-side, fail-closed reconciliation step in `PageBuilderController::update()`**, run just before persisting: any incoming block whose `type` is `ext:`-prefixed and NOT currently resolvable via `ElementRegistry::has()` is never trusted as submitted. It is looked up (recursively, by `id`, across the page's own currently-persisted block tree — not the client's claim) in the content that was persisted *before* this request. If found with the same `id` and `type`, the server's own last-known-good copy is kept verbatim (client-submitted props for a type the server cannot currently validate are never trusted). If not found — a brand-new, client-asserted unavailable `ext:*` block — it is dropped from this save; every other block in the same request (including edits to unrelated blocks) still saves normally. Every non-`ext:` type, and every currently-registered `ext:*` type, is completely unaffected by this new step (it is a no-op for both). This is the one durable new behavior introduced this phase, and the one place a future extension marketplace's "uninstall" flow must eventually reconcile against, rather than reinventing.
4. **Render-time fallback for both failure modes was proven, not built** — both already existed and needed no code change. An unregistered/unavailable `ext:*` type fails `ElementSchemaRegistry::validate()` before `renderBlockUnsafe()` is ever reached, producing `BlockRenderer`'s own `[invalid] {type}` placeholder. A *registered* extension whose `render()` throws is caught inside `App\Builder\Rendering\ElementRenderer::render()`'s own try/catch, producing that class's own, differently-formatted "Element failed to render" placeholder — a second, inner placeholder boundary, distinct from `BlockRenderer::renderBlock()`'s outer one (first identified in 3E-H-A). Both are safe (no exception/stack trace ever reaches the response, neighboring blocks in the same `renderBlocks()` call are unaffected since each block's render is independently wrapped) and deterministic; converging the two boundaries into one remains explicitly out of scope for this phase (recorded as 3E-I debt, same as 3E-H-A/B already noted).
5. **Re-enable-without-migration was proven, not built.** Because extension registration (`ExtensionBlockTypeRegistry`/`ElementRegistry::registerExtensionElement()`) is already idempotent by class-identity (3E-G), and because the new reconciliation step (§3) preserves a temporarily-unavailable block's `type`/`props`/`children` byte-for-byte in the database throughout, simply re-registering the same provider makes the same persisted block render again with no migration, revision-rewrite, or type-rewrite of any kind.
6. **Dynamic data remains deferred**, exactly as 3E-H-A/B left it — none of the 5 gaps closed this phase required it. Extension SDK V1 still does not claim production dynamic-data capability.

**Reason:**
Every gap closed here was closed with the smallest change that made an already-correct, already-declared capability (container metadata, `HasAssets`, idempotent registration, existing placeholder safety nets) actually reachable by the real runtime — never a new capability invented for this phase, and never a second registry, asset manager, or recursion engine. The one genuinely new piece of logic (§3's save-time reconciliation) exists solely to enforce a trust boundary the client cannot be relied on to enforce itself: content the server itself persisted may be preserved verbatim; content the server has never seen may not be smuggled in as if it had been.

**Impact:**
- Modified: `app/Services/ThemeBuilder/BlockRenderer.php` (additive OR-condition container detection in `renderBlockUnsafe()`/`wrapEditorMode()`; new `isExtensionContainer()`; reused `ElementRenderer` instance + new `getCollectedAssets()`), `app/Http/Controllers/Front/PageBuilderController.php` (`update()` gains `reconcileExtensionBlocks()`/`indexBlocksById()`/`reconcileExtensionBlocksLevel()`; `preview()` response gains `assets`), `resources/views/themes/default/page.blade.php` and `home.blade.php` (collected extension assets pushed into the existing `head`/`scripts` stacks).
- New tests: `tests/Unit/Builder/Extension/RuntimeGapClosureTest.php` (17 tests, 53 assertions — extension-container child rendering with built-in and extension children, unregistered-container safety, unchanged bare-container/non-container classification, asset collection/dedup/no-leakage/determinism, `preview()` asset emission, the full unavailable→preserve→reject-new-injection→re-enable lifecycle through the real `builder.page.update`/editor-load routes, throwing-vs-unregistered fallback distinction, neighboring-block survival, and the public `page.blade.php` asset-emission bridge itself); new test-only fixture `tests/Unit/Builder/Extension/Fixtures/ThrowingDemoElement.php`.
- Verified unaffected by full regression (1022 tests): the pre-existing `BuilderScenarioTest::test_save_builder_content_and_reload_preserves_integrity` failure (present since 3E-B, unrelated) and 7 other pre-existing failures (`SiteHelperOwnerSiteTest` x2, `PaymentConnectionWarningHostIntegrationTest`, `Wave70bFormsHardeningTest` x2, `Wave70cUxUniformityTest` x2) were independently confirmed to fail identically when run in isolation without any 3E-H-C file present — none are caused by this phase.
- No fourth/fifth registry. No new asset manager. No `ThemeCanvas`/header-footer/reconciliation/Motion/Marketplace/PWA/Push change. No dynamic-data wiring. No existing container type's classification changed. No existing 38/55 palette/schema-gate drift touched. No browser verification performed in this phase — that remains 3E-H-D.
