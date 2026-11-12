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

## ADR-042

**Date:** 2026-09-03
**Title:** Customer-Site Interactive Browser Verification Harness (Phase 3E-H-D0)

**Context:**
3E-H-D attempted real browser verification of the production Builder/Extension SDK path and found no interactive browser-automation harness existed anywhere for customer-site — no Playwright/Dusk/Cypress config, dependency, or test directory. saas-platform does have a Playwright installation (`scripts/headless/extract.mjs`/`quality-check.mjs`), but it is a narrow, purpose-built DOM/screenshot/contrast extractor for the Theme Reverse-Engineering commercial quality gate (`ThemeQualityGateBrowserIntegrationTest`) — it has no login flow, no form-filling, no click/drag interaction capability, and belongs to a different domain (theme QA on saas-platform), not customer-site's own UI. 3E-H-D was reported blocked (category A — harness absence) rather than faked with PHP-only or diagnostics-substitute "browser" evidence, and a dedicated phase (3E-H-D0) was commissioned to build the harness itself before any Extension SDK browser scenario could be attempted.

While building and hardening that harness against the real Builder UI, a second, unrelated, genuine, previously-unknown production defect was found and fixed (see §F–I below) — surfaced only because the harness now exercises the real JSON-consuming browser code paths no prior verification method (PHPUnit HTTP tests, manual admin use) had exercised the same way.

**Decision:**
A. **customer-site now owns the canonical interactive Playwright browser harness** (`tests/browser/`, `playwright.config.js`, `@playwright/test` devDependency) for this project — Theme Builder, Extension SDK, responsive, and future critical-UI regression verification all belong here going forward.
B. **Intentionally separate from saas-platform's Theme Reverse-Engineering Playwright extractor.** No shared code, no shared scripts, no dependency between the two — different domain, different purpose, different repo.
C. **Browser verification exercises the real, unmodified application stack**: a real Laravel app (built frontend assets, not a mocked/stubbed UI), the real `/login` form (real `mobile`/`password` fields, real session, real middleware — no auth bypass, no `storageState`-only shortcut for the first login), the real `/builder/edit/{id}` route, the real Alpine.js-driven Builder UI, the real `PUT /builder/page/{id}` save endpoint and real `POST /builder/preview` canvas-refresh endpoint, against an **isolated** browser-test database (`customer_site_browser`, distinct from both the development database and the PHPUnit `customer_site_test` database) and a dedicated, harness-managed environment (`OWNER_SITE=true` to bypass the unrelated license gate the same way existing PHPUnit Extension SDK tests already do; `SESSION_DRIVER=file` so login persists across the built-in dev server's per-request process model).
D. **The harness is Chromium-based (H-D's stated scope), headless-by-default and CI-capable, Windows/Linux portable (no hardcoded absolute paths, explicit `process.platform` branches only where genuinely needed), storage-conscious (screenshots on failure only, trace on first retry only, video off, all runtime artifacts gitignored under `tests/browser/.runtime/`), deterministic, and independent of any manually-started Laragon/dev server** — `global-setup.js`/`global-teardown.js` provision an isolated database, seed deterministic fixtures via a new CLI-only `browser-harness:setup` Artisan command, and start/stop a dedicated PHP server process themselves (spawning PHP's built-in server directly rather than via `artisan serve`, whose own child-process wrapper does not reliably terminate on a forceful Windows kill — a real leak found and fixed while building this). `BrowserHarnessSetupCommand` refuses to run at all outside a database name containing `"browser"` or inside a `production` environment, so a misconfigured environment can never point it at development or production data.
E. **D0 proves the browser harness itself — real login, real Builder route, one real built-in-block UI interaction (add/edit/save/reload), verified via the real UI, not via `page.evaluate()` state-reading or a diagnostics substitute.** It deliberately does **not** constitute Extension SDK browser verification: no `ext:*` fixtures, no container/asset/unavailable-lifecycle/stored-XSS/responsive Extension scenarios exist in this phase's test file. That work remains Phase 3E-H-D, to be built on top of this harness.
F. **A pre-existing, real, previously-unknown production defect was discovered incidentally while hardening the D0 smoke test**: `routes/web.php` carried a literal UTF-8 BOM (`EF BB BF`) before its opening `<?php` tag. Because route files are `require`'d during every request's boot, PHP echoed those 3 bytes as the literal first bytes of **every** HTTP response body. HTML responses tolerated this silently (invisible in a rendered page); every JSON response did not — `fetch(...).then(r => r.json())` (and any other strict `JSON.parse()`) threw a `SyntaxError` on the leading BOM. Confirmed concretely for the Builder's own live-canvas preview fetch (`POST /builder/preview?mode=builder`) and for the save-response success path, both silently failing in a way no PHPUnit HTTP test (which asserts on the response body's *content*, not on `fetch().json()` succeeding in a real browser) had ever caught. This affected the whole application's JSON responses, not anything Extension-SDK-specific.
G. **The fix removed only the 3 BOM bytes** from `routes/web.php`. Verified byte-for-byte: the fixed file is identical to the previously-committed one except for those 3 leading bytes — no route added, removed, reordered, or reworded; no other file touched by this micro-fix.
H. **Real authenticated browser/application verification after the fix proved the defect is gone**: through a real login session and the real HTTP endpoints, both `POST /builder/preview?mode=builder` and `PUT /builder/page/{id}` now return bodies starting with `{` (not `EF BB BF`) that pass `json_decode()`/`JSON.parse()` cleanly; the real Playwright smoke test's live-canvas iframe now genuinely renders the saved block's real content after a real reload (previously masked by a deliberate, now-removed test-side workaround); no BOM-related console `SyntaxError` occurs.
I. **Extension SDK real-browser verification remains Phase 3E-H-D**, to be built on top of this now-proven, now-cleaned-up harness.

**Reason:**
Real browser verification requires a real, reusable, honestly-scoped harness — not a diagnostics substitute, not PHP-only proof standing in for browser proof, and not a second competing framework alongside saas-platform's unrelated extractor. Building that harness first, and letting it surface a real defect that no prior verification method could see, is exactly the value such a harness is for; fixing that defect narrowly (3 bytes, zero semantic change) rather than deferring it kept the harness's own smoke test honest about what it actually proves, rather than quietly excluding the very failure mode it exists to catch.

**Impact:**
- New (customer-site): `tests/browser/` (`smoke.spec.js`, `global-setup.js`, `global-teardown.js`, `fixtures/env.js`, `fixtures/viewports.js`, `helpers/auth.js`, `helpers/builder.js`, `scripts/ensure-database.php`, `README.md`), `playwright.config.js`, `app/Console/Commands/BrowserHarnessSetupCommand.php`.
- Modified (customer-site): `package.json`/`package-lock.json` (`@playwright/test` devDependency, `test:browser*` scripts), `.gitignore` (browser-artifact ignores), `resources/views/builder/edit.blade.php` (4 semantically-neutral `data-testid` additions — save button, per-block-type palette buttons, per-prop-key field wrapper, widget-count span — no markup restructuring, no behavior change), `routes/web.php` (BOM removal only, per §F–G above).
- No Extension SDK product behavior changed. No `ThemeCanvas` convergence. No Motion change. No Marketplace change. No PWA/Push change. No deployment. No dynamic-data wiring. No existing 38/55 palette/schema-gate drift touched. No reconciliation architecture change.
- **Recorded as an explicit upcoming requirement, not performed now**: after the Theme Builder / shared runtime / Extension SDK architecture has been fully browser-verified, a dedicated end-to-end audit of all parallel/duplicate/legacy/transitional/superseded Theme-building paths must classify each as CANONICAL, REQUIRED_VISIBLE, REQUIRED_BACKEND_ONLY, TRANSITIONAL, or OBSOLETE — proving actual call graph, runtime consumers, and persistence/release/compatibility/commercial responsibility before any removal, never removing a component merely for looking old or duplicated, and never leaving two competing Theme-building workflows exposed for the same responsibility in the final commercial product. Out of scope for D0 and for this ADR's own decision; a dedicated future phase.

## ADR-043 — Layered Container Capability Authority

**Date:** 2026-09-04
**Title:** Layered Container Capability Authority

**Context:**
Container capability had become duplicated across runtime PHP and both Canvas editors, including divergent hardcoded lists.

**Decision:**
1. Shared ThemeRuntime BlockRegistry owns declarative container capability for canonical/shared block types.
2. Canonical container capability is represented explicitly with is_container metadata rather than inferred from schema fields.
3. Customer host/Extension capability remains additive through ElementRegistry / ThemeBuilderElementDefinition.
4. Container capability uses a layered semantic authority: shared canonical capability + customer host/extension capability.
5. Registries remain separate because they have different domain responsibilities; convergence does NOT mean registry merger.
6. Canvas client-side editors consume server-provided declarative is_container metadata instead of maintaining authoritative hardcoded type arrays.
7. Unknown types fail closed as non-container unless a registered host/extension definition explicitly declares otherwise.
8. Existing persisted Theme/Page JSON and block type identities require no migration.
9. allowed_children enforcement is explicitly NOT introduced by this decision.
10. Production Builder remains flat; this ADR does not introduce nested authoring there.
11. This decision prepares Phase 3F-C, where Extension SDK definitions may be bridged into Canvas authoring without adding another container-detection system.

**Impact:**
- shared renderer authority remains unchanged
- existing Canvas nested-tree UX remains unchanged
- Extension SDK identity/collision/registration contracts remain unchanged
- registration does not imply entitlement
- no ThemeCanvas/Production Builder merger
- no UI de-exposure
- no data migration
- no Marketplace/Motion/PWA/Push change

**Verification:**
- dedicated container capability tests in both repos
- real customer Canvas nested-authoring browser verification
- 3E-H-D browser regression
- existing reconciliation/runtime regressions

## ADR-044 — Extension SDK Canvas Authoring Bridge

**Date:** 2026-09-04
**Title:** Extension SDK Canvas Authoring Bridge

**Context:**
The Extension SDK was authorable only through the Production Builder while the canonical whole-Theme Canvas already owned nested authoring, Theme customization, release/update integration, and reconciliation.

**Decision:**
1. Extension SDK definitions remain owned by the existing: ThemeBuilderExtensionProviderContract, ThemeBuilderExtensionLoader, ThemeBuilderExtensionRegistrar, ExtensionBlockTypeRegistry, ElementRegistry, ThemeBuilderElementDefinition.
2. Canvas authoring consumes extension definitions through a stateless CanvasBuilderAdapter read-model.
3. CanvasBuilderAdapter is NOT a registry and owns no mutable registration state.
4. ThemeCanvas BlockRegistry and ElementRegistry remain separate domain authorities; they are not merged.
5. Extension palette/control/default/container metadata is derived from the existing declarative Extension SDK definitions.
6. Canvas uses its existing nested tree, children serialization, selection, ordering, and drag/drop implementation for extension blocks.
7. No extension-specific persistence format is introduced.
8. Persisted identity remains: ext:{vendor-slug}:{block_name}.
9. Existing renderer inheritance remains authoritative: shared ThemeRuntime → customer BlockRenderer → ThemeCanvasRenderer.
10. A generic ExtensionBlockReconciler is the canonical save-boundary trust mechanism for preserving existing unavailable extension blocks while rejecting new unregistered ext:* content.
11. Unknown-new extension types fail closed.
12. Unavailable persisted extension content is preserved and restored when the provider returns, without migration/rewrite.
13. Extension assets reuse the established HasAssets collection pipeline; Canvas only emits the collected assets in its existing preview/public surfaces.
14. Registration does NOT imply entitlement.
15. This ADR authorizes customer-site Canvas authoring only.
16. SaaS master Theme authoring of ext:* remains intentionally unsupported because no shared commercial Extension SDK/catalog contract currently exists.
17. saas-platform must never depend on customer-site application classes to obtain extension definitions.
18. ThemeRelease extension-dependency authoring therefore remains a future architecture concern, not solved by this ADR.
19. No Marketplace fulfillment, Motion, PWA, Push, registry merger, new nesting implementation, or persisted-data migration is introduced.

**Reason:**
Every piece of new logic is either a stateless read-model over an already-existing authority (CanvasBuilderAdapter) or an extraction of logic that already existed and worked (ExtensionBlockReconciler, generalized from its prior PageBuilderController-local form) — the bridge required zero new registries, zero new persistence formats, and zero changes to the shared rendering chain, because rendering and render-time validation for ext:* content already reached Canvas through the existing class-inheritance chain before this phase began.

**Verification:**
- real Canvas palette authoring
- built-in container → ext child
- ext container → built-in child
- ext container → ext child
- save/reload/publish/public render
- assets
- XSS
- unavailable/re-enable lifecycle
- unknown-new ext:* rejection
- throwing-renderer isolation
- generic reconciliation tests
- Production Builder regression

**Known deferred findings (not solved by this ADR):**
- SaaS master Extension commercial contract absent
- entitlement gating absent from authoring
- heading.level Canvas/schema mismatch
- tablet Canvas header overlap
- Canvas control-label accessibility gap

**Classification update:** ExtensionBlockReconciler is now CANONICAL. Its previous PageBuilderController-local reconciliation concern is no longer TRANSITIONAL because the mechanism has been extracted and reused by both authoring surfaces (Production Builder and Canvas). No other classification changed.

**Impact:**
- customer-site only — saas-platform application code untouched by the implementation this ADR records
- no ThemeCanvas/Production Builder merger, no UI de-exposure, no data migration
- no Marketplace/Motion/PWA/Push change

## ADR-045 — Commercial Extension Product and Release Contract

**Date:** 2026-09-04
**Title:** Commercial Extension Product and Release Contract (Phase 3G-B-1)

**Context:**
Phase 3G-A (read-only architecture audit) found saas-platform has no commercial model for Extensions at all — `grep`-ing for `ExtensionLicense|ExtensionProduct|class Extension` returns zero files. Everything "Extension" (`ExtensionProductIdentity`, `ExtensionBlockTypeIdentity`, `ExtensionDependencyResolver`, `ThemeBuilderExtensionDefinition`) is a customer-site-local runtime/authoring concept, with entitlement backed by a config array explicitly documented in its own class as a "V1 bridge, not a Marketplace/licensing system." ADR-044 (the prior HEAD) ended with this gap named explicitly: "SaaS master Theme authoring of ext:* remains intentionally unsupported because no shared commercial Extension SDK/catalog contract currently exists" and "ThemeRelease extension-dependency authoring therefore remains a future architecture concern."

Before implementing, this phase re-audited `Theme`/`ThemeRelease`, `Module`/`ModuleRelease`, and Motion's `MotionGraphic`/`MotionGraphicVersion` (`packages/saas-platform/modules/motion-graphics`) directly against code, plus `Product`'s exact role via `OrderFulfillmentService::fulfillItem()`. All three existing product classes independently converge on the same shape: a **master identity row** (`Theme`, `Module`, `MotionGraphic`) holding a denormalized `belongsTo` pointer to its **current release**, plus a **separate immutable release-ledger row** (`ThemeRelease`, `ModuleRelease`, `MotionGraphicVersion`) that `belongsTo` the master and is guarded against in-place mutation after creation. `Product` was confirmed, by reading its three real fulfillment paths, to be a pure sellable-SKU/checkout wrapper with no consistent domain-linkage pattern of its own — `TYPE_MODULE_ADDON` uses a direct `Product.module_id` FK, `TYPE_MOTION_ASSET` uses a *reverse* FK on an unrelated bespoke-commission table, and `TYPE_LICENSE_TEMPLATE` (Theme) uses no FK at all, only an opaque `sku` string passed to `LicenseServerService`. `Product` was never, in any of its three existing uses, the master domain identity itself — proving the master-row content class cannot be `Product` for Extension either, without contradicting how the other three product classes already work.

**Decision:**
1. **Extension is a distinct commercial product class** — not modeled as `Module` (whose `ModuleLicense`/`ModuleEntitlement` are hard-FK'd to `module_id` and whose type vocabulary is about platform-shipped, tenant-activated modules, not third-party authored packages) and not modeled as `Product` alone (proven insufficient above — Product carries no master identity/version/release semantics anywhere in this codebase).
2. **Extension reuses generic Product commerce plumbing.** `Product::TYPE_EXTENSION` is added as pure additive vocabulary (a fourth string constant + label, exactly like `TYPE_MOTION_ASSET`, which also carries no dedicated FK column on `Product` itself). `OrderFulfillmentService` is deliberately left unmodified in this phase — a `Product` row of `type = extension` is legal domain vocabulary today but has no fulfillment path yet, matching the Product Owner policy that purchase fulfillment stays unsupported until entitlement authority is settled (a later phase).
3. **Runtime Extension SDK (customer-site `App\Builder\*`) and the commercial Extension contract (saas-platform `App\Models\Extension`/`ExtensionRelease`) remain wholly separate.** No file in this phase is added to or imported from customer-site. saas-platform never depends on customer-site `App\*`.
4. **Master identity and release identity are two separate rows, not one** — `App\Models\Extension` (master: `vendor_slug`, `package_slug`, `name`, `description`, `current_release_id` pointer) and `App\Models\ExtensionRelease` (immutable release ledger: `extension_id`, `version`, `sdk_contract_version`, `manifest`, `status`, `published_by`, `published_at`), mirroring `Theme`/`ThemeRelease` byte-for-byte in relationship shape and mutation-guard style. A release row is never itself the identity namespace owner — `extensions` alone owns `UNIQUE(vendor_slug, package_slug)`.
5. **Product identity grammar**: `{vendor-slug}/{package-slug}`, deliberately reproduced with byte-identical rules (pattern, length bounds) from customer-site's already-proven `ExtensionBlockTypeIdentity::isValidVendorSlug()`/`ExtensionProductIdentity` grammar, in a new saas-platform-only class (`App\Services\ThemeBuilder\Extension\ExtensionProductIdentity`) — never imported, never a divergent second grammar. This duplication is explicitly temporary: the shared `extension-sdk` package (3G-B-2) is where a single authoritative copy will live; until then, both copies must be kept in parity by the party who last touched either one, and this file's own docblock says so.
6. **Persisted element identity remains `ext:{vendor-slug}:{block_name}`, entirely unchanged and untouched** — this phase does not read, write, or migrate any Theme/Page JSON. Product identity is never inferred from that string (a release depends on the *product*, never on one block instance — consistent with ADR-037's original reasoning, now given a real master row on the commercial side to depend on).
7. **Identity immutability**: once an `Extension` has any `ExtensionRelease`, `vendor_slug`/`package_slug` become immutable — enforced by a model-level `updating` guard (`RuntimeException`), mirroring `ThemeRelease`'s own guard style. `name`/`description` remain freely editable (catalog/display metadata, never release content) — consistent with the Commercial Product Architecture Contract §3.7 ("marketplace fields live on the catalog record, never the release record"). No rename/alias support is built.
8. **Extension releases are immutable, append-only commercial release records.** `ExtensionRelease::booted()` mirrors `ThemeRelease::booted()` exactly: only `status` may transition after creation (`published → superseded`, or `→ deprecated`); every other column throws on `updating`; `deleting` always throws. Status vocabulary is reused verbatim from `ThemeRelease` — `STATUS_PUBLISHED`/`STATUS_SUPERSEDED`/`STATUS_DEPRECATED` — not `ModuleRelease`'s fuller `draft→validated→built→published→deprecated` machine, because (verified directly) `ThemeRelease` itself has no draft state and is only ever created already-published by its sole writer; `ExtensionRelease` has the same shape (no build/artifact pipeline exists for Extension in this phase), so it correctly inherits Theme's simpler vocabulary rather than Module's, per the instruction not to claim vocabulary that doesn't actually fit.
9. **`ExtensionReleaseService` (`App\Services\ThemeBuilder\Extension\ExtensionReleaseService`) is the sole writer** of both `extensions` and `extension_releases` — `createExtension()` and `release()`, mirroring `ThemeReleaseService`'s constructor-less, transactional, fail-closed shape. `current_release_id` is excluded from `Extension::$fillable`, exactly as `Theme::$fillable` excludes it, so it can only ever move through this service. `release()` supersedes the prior published release (if any) inside the same transaction, exactly like `ThemeReleaseService::release()`, and rejects a duplicate `(extension_id, version)` before writing.
10. **SaaS master authoring will consume declarative release manifests, never customer-site executable provider classes** — `ExtensionRelease.manifest` is persisted as JSON in this phase with deliberately minimal, honest validation (must be a non-empty, object-shaped array — not a schema check). The canonical `ExtensionManifest` DTO/schema is explicitly deferred to 3G-B-2; this phase does not invent a second, competing manifest schema.
11. **SDK contract version** is stored as a plain positive integer (`sdk_contract_version`) with no semver negotiation and no dependency on customer-site's `ThemeBuilderExtensionDefinition::SDK_CONTRACT_V1` — no reverse import exists or is introduced. A shared compatibility authority is deferred to 3G-B-2.
12. **Version validation** reuses the exact semver regex already load-bearing in `ThemeReleaseService::assertValidSemver()` (`/^\d+\.\d+\.\d+$/`) — no third semver implementation was written; this is the same single-line rule copied verbatim into `ExtensionReleaseService`, since no shared, importable semver utility class exists anywhere in the codebase today (confirmed by search — every release-adjacent service that checks version syntax carries its own private copy of essentially this same pattern; extracting a shared utility is out of this phase's narrow scope).
13. **`ThemeReleaseDependency` will later be reused, unchanged, for Extension dependencies** (`dependency_type = 'extension'`, `dependency_identifier = '{vendor}/{package}'`) — re-confirmed in this phase by reading `ThemeReleaseService::assertValidDependencies()` directly: `type` is validated as a non-empty string with zero whitelist, so no schema or code change to that table/model is required when 3G-B-3 adds the extractor. **No extension-specific Theme dependency table is created in this phase, or planned.**
14. **Customer runtime registration remains customer-site-owned**, entirely untouched by this phase — `ElementRegistry`, `ExtensionBlockTypeRegistry`, `ThemeBuilderExtensionRegistrar`, `InstalledExtensionProvider`, `ConfigExtensionEntitlementProvider` are not read, imported, or modified.
15. **Registration ≠ installation ≠ entitlement** remains the standing rule from ADR-037/044, unaffected and unimplemented further by this phase — this phase adds no entitlement persistence of any kind.
16. **saas-platform must never depend on customer-site `App\*`** — verified true of every file this phase adds; no new file references any customer-site namespace.
17. **Marketplace will consume Extension commercial authority, never own it** — no Marketplace code exists or is added; `extensions`/`extension_releases` are the future authority a Marketplace phase would read from.
18. **Extension commercial identity/release authority belongs to saas-platform** — `App\Models\Extension`/`App\Models\ExtensionRelease`, full stop; customer-site holds no copy of either row and this phase adds no API surface for customer-site to fetch them (deferred, along with the shared package, to a later phase).
19. **Customer-specific Extensions must not silently become master Theme dependencies** — not mechanically enforced by this phase (no dependency extraction exists yet), but the master `extensions` table this phase introduces is explicitly global/commercial-catalog only; no `site_id`/ownership-type column was added, so a future customer-specific-Extension concept (if ever needed) cannot be confused with a row in this table by construction.
20. **Historical Extension releases must remain resolvable even when no longer sold** — guaranteed the same way `ThemeRelease` already guarantees it: `deleting` always throws, `status` transitions to `deprecated` rather than removing the row, and `extension_releases.extension_id` uses `restrictOnDelete` (mirroring `theme_releases.theme_id`) so a master row with real release history cannot be casually hard-deleted either.

**Product Owner policies recorded (not all implemented yet):**
- New authoring requires installed **and** entitled (deferred — no authoring surface exists yet in either repo for `ext:*` in SaaS Canvas).
- Loss of entitlement never deletes persisted `ext:*` content (already true today at the customer-site runtime layer per ADR-041 §3; unaffected by this phase).
- Extension Theme dependencies may be required or optional; **required is the V1 default** (to be enforced when 3G-B-3/3G-B-5 implement extraction and the publish gate).
- Theme purchase does **not** automatically bundle Extension entitlement in V1 (no bundling mechanism exists or is implied by this phase).
- Runtime behavior after entitlement expiry remains deferred to the dedicated entitlement phase — this phase makes no change to `ExtensionEntitlementProviderContract`, `ConfigExtensionEntitlementProvider`, `ModuleLicenseServiceInterface`, or `NullModuleLicenseAdapter`. The `NullModuleLicenseAdapter` unconditional fail-open finding (customer-site, `AppServiceProvider::register()`, re-confirmed live in 3G-A) is carried forward unresolved — real, but out of this phase's scope.

**Deferred items (explicitly out of scope for 3G-B-1):**
shared `extension-sdk` package · Theme dependency extractor · Theme publish-gate integration · customer entitlement persistence · fulfillment (`OrderFulfillmentService` case for `TYPE_EXTENSION`) · SaaS Canvas `ext:*` authoring · Extension runtime expiry policy · bundling.

**Reason:**
Every existing product class in this codebase (Theme, Module, Motion) independently arrived at the same master-row-plus-release-ledger shape, and `Product` was proven, not assumed, to be a SKU wrapper incapable of carrying that role a fourth time. Building `Extension`/`ExtensionRelease` as the fourth instance of an already-three-times-proven pattern — reusing `ThemeReleaseDependency`'s open dependency vocabulary and `Product`'s open type vocabulary rather than duplicating either — is the smallest correct foundation; inventing anything else (a fifth vocabulary, a Product-owned identity, an extension-specific dependency table) would have contradicted evidence already in the codebase rather than filled a genuine gap.

**Impact:**
- New: `app/Models/Extension.php`, `app/Models/ExtensionRelease.php`, `app/Services/ThemeBuilder/Extension/ExtensionProductIdentity.php`, `app/Services/ThemeBuilder/Extension/ExtensionReleaseService.php`.
- New migrations: `create_extensions_table`, `create_extension_releases_table`, `add_current_release_id_to_extensions_table` — all additive, no existing table altered destructively, no existing data rewritten.
- Modified (additive only): `app/Models/Product.php` (`TYPE_EXTENSION` constant + `typeLabel()` case).
- New tests: `tests/Unit/ThemeBuilder/Extension/ExtensionProductIdentityTest.php`, `tests/Feature/ThemeBuilder/Extension/ExtensionReleaseServiceTest.php`.
- No customer-site file touched. No `ThemeReleaseDependency`/`ThemeReleaseService` dependency logic touched. No `OrderFulfillmentService` change. No entitlement code touched. No SaaS Canvas/palette change. No `ext:*` JSON migration. No Marketplace change.
- **Classification**: `Extension`/`ExtensionRelease` are CANONICAL (the new commercial master/release authority). `ExtensionRelease.manifest`'s raw-JSON validation is explicitly TRANSITIONAL until the shared `ExtensionManifest` DTO lands in 3G-B-2. Nothing is OBSOLETE.

## ADR-046 — Extension Element Type Ownership: Global Uniqueness and Non-Transferability Between Extension Products

**Date:** 2026-09-05
**Title:** Extension Element Type Ownership: Global Uniqueness and Non-Transferability Between Extension Products (Phase 3G-B-3 ownership closure)

**Context:**
Phase 3G-B-3 built `ThemeExtensionBlockTypeExtractor` (Theme snapshot → used `ext:*` types) and `ExtensionElementOwnershipResolver` (type → owning Extension product, via `ExtensionRelease.manifest`), because `ext:{vendor-slug}:{block_name}` — the persisted block identity — contains no package slug and therefore can never be parsed back into a `{vendor-slug}/{package-slug}` product identity. That audit surfaced a real, until-then-unenforced gap: nothing in `ExtensionReleaseService`/`ExtensionManifest` prevented two different Extension products (even two packages under the same vendor) from each publishing a manifest that claims the identical `ext:*` element type. The resolver was built defensively to detect this (0/1/2+ owners → unresolved/resolved/ambiguous, never an arbitrary pick), but detection alone leaves the door open for new ambiguous data to keep being created. This ADR closes that gap at the only point new ambiguity can ever be introduced: Extension release publication.

Before implementing, this phase re-audited the actual application database (not the ephemeral PHPUnit test database) for existing collisions: the `extensions`/`extension_releases` tables do not exist there at all — migrations for them have only ever run inside RefreshDatabase-backed test runs. There is therefore no historical data of any kind to reconcile, and no possibility of a pre-existing collision. This ADR is adopted as a forward-looking publication invariant, not a data-migration/cleanup decision.

**Decision:**
1. **The invariant:** once an `ext:{vendor}:{block}` element type has appeared in a successfully published `ExtensionRelease` belonging to Extension product P, that element type is permanently owned by P. No other Extension product may ever successfully publish a release claiming the same type.
2. **`ext:*` persisted identity does not, and will never, contain a package slug** — this remains the entire reason ownership must be resolved declaratively (via manifest data) rather than parsed from the type string. This ADR does not change that grammar.
3. **`ExtensionManifest` (the shared, validated DTO) is the sole authoritative declaration of element membership** for ownership purposes — never a raw/unvalidated manifest array, never an inference from the type string.
4. **Ownership is product-level and historical, never release-level and never current-release-only.** A product's very first successfully published release containing a type establishes that product as the type's permanent owner. Every subsequent release of that same product — whether it re-declares the type, drops it, or re-introduces it later — never changes who owns it.
5. **Historical releases remain ownership evidence forever.** `ExtensionRelease` rows are never deleted (pre-existing immutable-ledger guarantee) and superseded/deprecated status is never treated as "this evidence no longer counts" — a release that is superseded or deprecated today was still a real, successfully validated publication, and its manifest content remains just as authoritative as a currently-published one for ownership purposes.
6. **Ownership cannot transfer between Extension products, ever** — not when a product's current release changes, not when an element disappears from a later release, not when a release becomes superseded or deprecated, and not by any other mechanism this or any prior phase introduces. There is no "release" or "reclaim" operation.
7. **The same Extension product may freely redeclare its own historical types** in any later release (Extension A: 1.0 has `ext:acme:grid`, 2.0 drops it, 3.0 has it again — always allowed, ownership never left A).
8. **A different Extension product attempting to claim an already-owned type must fail closed** at publication time — `ExtensionReleaseService::release()` rejects the entire publication (`InvalidArgumentException`), atomically, before any row is written. No partial release is ever created because one of several declared elements conflicts.
9. **Enforcement point:** `ExtensionReleaseService::assertNoElementOwnershipConflict()`, called from inside `release()`, reusing `ExtensionElementOwnershipResolver` unchanged (no second, competing ownership-scanning implementation). For every element type in the new manifest: zero prior owners or the publishing product itself as sole owner → allowed; any other product present among the resolved/candidate owners → rejected.
10. **Concurrency:** the ownership invariant is global (spans every Extension product, not just one), so a plain per-row/per-extension database lock cannot make the check-then-write critical section atomic — the conflicting row does not exist yet at scan time for a first-ever claim, so there is nothing to lock. `release()` therefore acquires a single global `Cache::lock('extension-release-element-ownership-publish-lock', 10)` (5-second wait, `LockTimeoutException` surfaced as a retryable `RuntimeException`) around the ownership check *and* the write transaction together, reusing the exact `Cache::lock()` pattern already established by `App\Services\Modules\ModuleDeploymentRequestService` for the same class of problem. Backed by Redis in every real environment (this app's actual default cache store), giving genuine cross-process mutual exclusion — not an application-only timing assumption. The tradeoff, accepted deliberately: all Extension release publications across every product are serialized one-at-a-time platform-wide. This is judged acceptable because publishing a new Extension release is a rare, low-frequency administrative/commercial action, never a hot path.
11. **No DB migration, no new ownership ledger table.** The element types live inside immutable `ExtensionRelease.manifest` JSON; a normal unique DB constraint cannot express a set-membership invariant across JSON documents without a new denormalized table. Given zero existing production data and a rare-write access pattern, service-level fail-closed enforcement under a global lock is the smallest correct design — introducing a dedicated ledger table remains available as a future option if publication volume or query needs ever justify it, but is not required now.
12. **`ExtensionElementOwnershipResolver`'s ambiguity handling is retained, unchanged, as defense-in-depth** — this ADR prevents *new* valid publications from creating ambiguity, but a resolver that only ever expects clean data would be a false guarantee against legacy/manual/direct-DB/imported data. `0/1/2+ owners → unresolved/resolved/ambiguous` remains the resolver's entire contract; it is never deleted or weakened.
13. **A stored manifest that fails to re-parse via `ExtensionManifest::fromArray()` contributes zero ownership evidence and zero conflict evidence** — it is never treated as a valid authority for either detecting an existing owner or blocking a new publication. This was already the resolver's behavior and is unchanged by this ADR.
14. **Runtime customer-site registry collision rules remain entirely separate and untouched** — `ExtensionBlockTypeRegistry`'s canonical/host/extension collision governance (customer-site, per-installation) answers a different question (can THIS installation's registry claim this type at registration time) and is neither read nor modified by this ADR. This ADR governs only saas-platform's global commercial catalog.
15. **saas-platform's commercial publication path owns this invariant exclusively** — no customer-site dependency of any kind is introduced or required to enforce it.
16. **This ADR does not define Theme dependency version-selection policy.** `ExtensionDependencyProposal.versionConstraint` remains `null` — the Theme snapshot still records only which `ext:*` types are used, never a chosen Extension release/version, so there is still no truthful source for a version requirement. That remains a separate, later decision.

**Deferred items (explicitly out of scope for this closure):**
truthful Extension version-selection for Theme dependencies · Theme publish-gate Extension verification · `ThemeReleaseService` dependency attachment/wiring · SaaS Canvas `ext:*` authoring · entitlement · fulfillment · Marketplace · bundling · runtime expiry policy · `NullModuleLicenseAdapter` fix (carried forward unresolved, unrelated to this ADR).

**Reason:**
The alternative — leaving element-type ownership entirely unenforced — would let a future Extension publication silently create genuinely ambiguous commercial data (two products both legitimately, validly claiming the same block type), which the later Commercial Publish Gate and Theme dependency resolution could never safely resolve without guessing. Closing the gap now, before any real Extension has ever been published (confirmed: zero rows exist anywhere), costs nothing in migration/backfill risk and prevents the defect class from ever being created. A DB-level unique constraint was rejected only because the ownership predicate spans immutable JSON manifest content rather than a single indexed column — service-level enforcement under a real distributed lock is the correct minimal design for the current (zero-data, low-write-volume) state of this system, not a shortcut around correctness.

**Impact:**
- Modified: `app/Services/ThemeBuilder/Extension/ExtensionReleaseService.php` (ownership-conflict check + global publication lock added to `release()`; no other method's behavior changed).
- No other production file changed. `ThemeExtensionBlockTypeExtractor`, `ElementOwnershipResult`, `ExtensionElementOwnershipResolver`, `ExtensionDependencyProposal`, `ThemeExtensionDependencyExtractionResult`, `ThemeExtensionDependencyExtractor` are all unchanged from their reviewed 3G-B-3 shape.
- New tests: `tests/Feature/ThemeBuilder/Extension/ExtensionReleaseOwnershipInvariantTest.php`.
- No DB migration. No new table. No `ext:*` JSON migration. No customer-site file touched. No entitlement/fulfillment/Marketplace/Canvas change. No Theme publish-gate change. No version-constraint behavior change.
- **Classification**: the ownership invariant enforcement in `ExtensionReleaseService::release()` is CANONICAL. `ExtensionElementOwnershipResolver`'s ambiguity handling remains CANONICAL defense-in-depth (not superseded, not obsolete — legacy/manual data can still violate the invariant this ADR only prevents going forward). Nothing is OBSOLETE.

## ADR-047 — Theme Extension Dependency Version Selection and Exact-Version Authority

**Date:** 2026-09-05
**Title:** Theme Extension Dependency Version Selection and Exact-Version Authority (Phase 3G-B-4)

**Context:**
Phase 3G-B-3 (ADR-046) established that an `ext:*` element type's owning Extension PRODUCT can be resolved declaratively from `ExtensionRelease.manifest`, but left `ExtensionDependencyProposal.versionConstraint` permanently `null` — the Theme snapshot recorded only which element *types* were used, never which Extension *release/version* authoring had selected. This phase audited every existing version/compatibility authority (`Theme`, `ThemeRelease`, `ThemeReleaseService`, `ThemeReleaseDependency`, `Extension`, `ExtensionRelease`, `ExtensionReleaseService`, `ExtensionManifest`, the `ThemeReleaseDependencyBackfillCommand` precedent) before writing any code, and found: (1) `Extension.current_release_id` is catalog state only — it has never been linked to any Theme-authoring event and carries no guarantee a given Theme was authored or tested against it; (2) `Theme.settings` (an existing, `array`-cast, `$fillable` JSON column) is already the load-bearing location where authoring records dependency-relevant data — proven by `ThemeReleaseDependencyBackfillCommand`, which reads `settings.supported_modules`/`settings.motion.required_assets` today; that same command's own docblock confirms no version has ever been captured there for any dependency type; (3) `ThemeReleaseService::release()` already copies `'settings' => $theme->settings` verbatim, with no key allowlist, into every `ThemeRelease.snapshot` — so a new `settings` key is frozen into release history automatically, with zero change to that service; (4) no Extension cross-release compatibility policy exists anywhere — `ExtensionSdkContract` is a plain integer contract-version vocabulary, never a semver range declaration.

**Decision:**
1. Theme Extension dependency version truth comes from an explicit authoring selection, never `Extension.current_release_id`.
2. Canonical authoring persistence: `Theme.settings['extension_selections']` — an existing JSON column, no migration.
3. Shape: `{ "{vendor}/{package}": { "version": "X.Y.Z" } }`, keyed by the canonical Extension product identity.
4. The selection is stored once per Theme/product, never duplicated per `ext:*` block — multiple blocks from the same product share one selection.
5. Persisted `ext:*` block identity remains unchanged: `ext:{vendor}:{block}`.
6. Extension product identity remains unchanged: `{vendor}/{package}`, validated via `SaaSPlatform\ExtensionSdk\ExtensionProductIdentity`.
7. V1 dependency constraint is exact: `=X.Y.Z`.
8. Exact-version is required because no Extension cross-release compatibility policy currently exists — a `>=`/`^` range would fabricate a guarantee nothing in the architecture backs.
9. `>=`, `^`, wildcard (`*`), `latest`, `current`, or any other inferred compatibility claim is forbidden unless a future explicit compatibility contract establishes it.
10. `Extension.current_release_id` is catalog state, not historical Theme authoring truth.
11. Changing `current_release_id` must never silently alter an existing Theme selection — proven: a recorded selection resolves identically before and after the Extension's current release moves on.
12. Superseded/deprecated historical `ExtensionRelease` rows remain resolvable for an already-recorded Theme selection — status is never consulted when validating an existing selection (identical reasoning to ADR-046's historical-ownership-evidence policy).
13. A selected release must belong to the selected product (structurally guaranteed: lookup is always scoped to the `Extension` resolved from the product-identity key itself, never a raw release id) and its validated manifest must contain every `ext:*` element used by that Theme and attributed to that product, or the selection fails closed as invalid.
14. A used Extension product with no recorded selection is `unresolvedVersionSelections` — never substituted with current/latest/wildcard.
15. An invalid or nonexistent selection (wrong version, missing element) fails closed into `invalidVersionSelections` — never silently ignored or downgraded to unresolved.
16. Unused recorded selections (a product selected but not currently used by any block) do not automatically become `ThemeRelease` dependencies — reported diagnostically via `unusedSelections`, never mutated/deleted.
17. Dependency extraction remains usage-driven — a selection alone, without actual element usage, produces no dependency.
18. ADR-046's ownership authority remains independent and unchanged: ownership `unresolved`/`ambiguous` diagnostics are never bypassed or resolved by the presence of plausible-looking version-selection metadata.
19. `ThemeReleaseService` integration (deriving/attaching real `ThemeReleaseDependency` rows from this authority at publish time) is explicitly **not** part of this ADR's implementation — confirmed unmodified.
20. Commercial Publish Gate integration (enforcing this authority as a blocking check) is deferred to a later phase (3G-B-5).
21. SaaS Canvas becomes the future writer of `Theme.settings['extension_selections']` and the user-facing selection mechanism in a later phase (3G-B-6) — this ADR defines only the backend read/validate contract that UI will feed.
22. customer-site runtime registration/entitlement remains entirely separate — untouched, unconsulted.
23. No entitlement, fulfillment, bundling, Marketplace, or runtime-expiry semantics are defined by this ADR.
24. No DB migration and no `ext:*` JSON migration were required or performed.

**Reason:**
`Theme.settings` already carried the exact ownership/lifecycle properties this data needs — mutable during authoring, automatically frozen immutable at release time by code that already exists — so introducing a new key there closes the version-truth gap with zero schema risk and zero change to the already-reviewed, already-tested `ThemeReleaseService`/`ExtensionReleaseService`/ADR-046 machinery. Exact-version was chosen over a range because nothing in the Extension SDK contract or commercial model makes any cross-release compatibility promise today; asserting one would be fabrication, not truth.

**Impact:**
- Modified: `app/Services/ThemeBuilder/Dependency/ThemeExtensionDependencyExtractionResult.php` (three new diagnostic fields: `unresolvedVersionSelections`, `invalidVersionSelections`, `unusedSelections`), `app/Services/ThemeBuilder/Dependency/ThemeExtensionDependencyExtractor.php` (now also consults version selections; a product only reaches `resolvedDependencies` once both ownership and version are truthfully resolved).
- New: `app/Services/ThemeBuilder/Dependency/ExtensionVersionSelectionResolver.php`, `app/Services/ThemeBuilder/Dependency/ExtensionVersionSelectionResult.php`.
- New tests: `tests/Feature/ThemeBuilder/Dependency/ExtensionVersionSelectionResolverTest.php`; `ThemeExtensionDependencyExtractorTest.php` extended with version-truthfulness/immutability coverage.
- No other production file changed. `ThemeReleaseService`, `ExtensionReleaseService`, `ThemeCommercialQualityGate`/`DependencyIntegrityCheck`, `ThemeMarketplaceApiController`, `ThemeExtensionBlockTypeExtractor`, `ExtensionElementOwnershipResolver`, `ExtensionDependencyProposal`, and the shared `extension-sdk` package are all unchanged.
- No DB migration. No `ext:*` JSON migration. No customer-site file touched. No entitlement/fulfillment/Marketplace/Canvas change. No Publish Gate change.
- **Classification**: `ExtensionVersionSelectionResolver` and the evolved `ThemeExtensionDependencyExtractor`/`ThemeExtensionDependencyExtractionResult` are CANONICAL. All are REQUIRED_BACKEND_ONLY — no controller, route, or UI entry point exists yet; SaaS Canvas Extension authoring (3G-B-6) is their intended future consumer. Nothing is OBSOLETE.

## ADR-048 — Declarative Extension Asset Contract and Version-Safe Distribution

**Date:** 2026-09-05
**Title:** Declarative Extension Asset Contract and Version-Safe Distribution (Phase 3G-B-6A / 3G-B-6B)

**Context:**
Phase 3G-B-6 gave SaaS Theme Canvas real commercial-Extension authoring (palette, exact-version selection, save-boundary validation, ownership-fail-closed instance gating — ADR-045/046/047), but `ExtensionElementDefinition.hasAssets` was audited (3G-B-6 closure) to be a plain capability flag with no actual CSS/JS content anywhere in the shared contract — the only real asset source in either codebase was customer-site's executable `App\Builder\Contracts\HasAssets::getAssets()`, which SaaS must never call (ADR-045's customer/provider execution boundary). 3G-B-6A closed the declarative-shape half of that gap; 3G-B-6B then audited every existing commercial-product file-distribution precedent in saas-platform before writing any code (Module package/artifact columns, Motion Graphics' deliberately file-agnostic reference pattern, Theme releases' pure-JSON snapshot, `public/vendor/` conventions, configured filesystem disks) and found: (1) `ExtensionRelease` was metadata-only (`manifest` JSON, no file/path/disk column); (2) the one existing "immutable release artifact" precedent, `ModuleRelease.package_path/package_sha256/package_key/package_size_bytes`, materializes a whole module's source tree to a remote customer VPS over SFTP — not reusable as-is for serving browser-consumable static files from this app's own webroot; (3) no `AssetPublisher`/storage→`public/` publishing utility, and no `extensions`/`modules` filesystem disk, existed anywhere to reuse; (4) the plain `/vendor/{vendor}/...` path shape 3G-B-6A had adopted (matching customer-site's own `HasAssets::getAssets()` convention) was provably NOT version-safe: two different releases (of the same or different products) could declare the identical literal path with different physical content, making a Theme's historical `=1.0.0` selection non-deterministic once a later release republished that same path.

**Decision — declarative contract (3G-B-6A, unchanged by 3G-B-6B):**
1. `ExtensionElementDefinition.assets = {css: string[], js: string[]}`, element-level (the same level `has_assets` already lived at) — plain data, never an executable reference, never a `rendererClass` analog.
2. `has_assets` is kept (backward-compatible shape) but strictly validated for consistency with `assets`: `true` requires ≥1 entry across both arrays; `false` requires both empty. Construction/parsing throws otherwise.
3. Every asset string must be a root-relative public path: exactly one leading `/`, ASCII `[A-Za-z0-9_\-./]` only (no scheme, no quotes/markup, no whitespace, no backslashes), no `.`/`..` path segments, ending in the matching `.css`/`.js` extension.
4. A manifest with no `assets` key at all (every pre-3G-B-6A row) parses as `{css: [], js: []}` — no migration required, no compatibility break.
5. Validated automatically inside the existing single validation path: `ExtensionElementDefinition::fromArray()`/`make()` → `ExtensionManifest::fromArray()` → `ExtensionReleaseService::release()`. No second validation path was added.
6. SaaS Canvas/preview emission (`ExtensionCanvasPaletteAdapter::assetsUsedIn()`) reads ONLY the selected release's already-validated manifest: deduplicated, deterministically type-ordered union of assets for `ext:*` types actually present in the block tree being rendered. Unused declared assets emit nothing; two Extensions' assets never mix, because each type's assets come only from its own element definition. Never executes customer/provider code. Canvas itself understands nothing about storage internals — it only ever emits the manifest's own path strings into `<link>`/`<script>` tags.

**Decision — version-safe distribution (3G-B-6B, the correctness fix and the final B-6 closure):**
7. Every declared asset path MUST be namespaced under `/vendor/{vendor}/{package}/{version}/` — the declaring release's OWN product identity and OWN semver version, structurally enforced inside `ExtensionManifest::make()` (`assertAssetsNamespacedUnderRelease()`), the same manifest-structural layer that already enforces element-type vendor matching. This makes cross-product and cross-release path collision structurally impossible, not merely conventionally avoided — no active commercial Extension data existed yet, so this correctness fix was made without a backward-compatibility carve-out, per this phase's own explicit instruction that correctness wins in that situation.
8. Physical asset source: the smallest new "release asset bundle" contract this codebase needed (audited: no existing generic mechanism was reusable — see Context). `ExtensionReleaseService::release()` gained one new optional parameter, `array $assetSources = []` (declared path => absolute local source file path) — never a second registry, never a package manager, never a customer-provider execution bridge.
9. Materialization authority: `App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer` (new, SaaS-app-layer only — the extension-sdk package stays Laravel-free and file-I/O-free). For every path a validated manifest declares, requires a real, readable source file in `$assetSources` and copies it to `public_path($path)` via stage-to-temp-file-then-atomic-`rename()`, called from `release()` strictly BEFORE the release row is created (outside the ownership lock/transaction, since it is a filesystem operation, not a database one).
10. Fail-closed publication: a declared asset with no provided source, or an unreadable source, throws and no `ExtensionRelease` row is ever created (proven: `$extension->releases()->count()` stays `0`, `current_release_id` stays unset, no file appears at the target path). There is no partially-published release.
11. Retry/idempotency: materializing the same release's declared asset against identical source content a second time is a safe no-op (checksum match). Materializing against DIFFERENT content at an already-materialized path throws (defense-in-depth; structurally unreachable in normal operation once point 7 holds, since two releases can never share a literal path).
12. Historical immutability (mandatory, proven): publishing a newer release (e.g. `2.0.0`, with its own different asset content at its own different version-namespaced path) never mutates, moves, or reuses an older release's (`1.0.0`) already-materialized files — both coexist independently on disk and via HTTP.
13. `Extension.current_release_id` is never consulted for asset resolution — identical policy to ADR-047's version-selection authority. A Theme's asset resolution follows only its own recorded `extension_selections` pin, proven unaffected by a newer release becoming current.
14. Extra/unreferenced entries in `$assetSources` (files not declared by any element's `assets`) are silently ignored — not an error, not materialized.
15. Backward compatibility: a manifest declaring no assets requires and materializes nothing; `release()`'s new parameter defaults to `[]`, so every pre-3G-B-6A caller/test is unaffected.

**Reason:**
Auditing first, per this phase's own instruction, surfaced a real defect (the plain-path collision) before it could ever reach production data — no active commercial Extension existed yet, so the fix (baking product+version into the required path prefix) was free of migration risk and was applied immediately rather than deferred. Reusing the exact manifest-structural validation layer that already polices element-type vendor matching, and reusing `release()`'s own existing transaction/lock boundary as the sole publication gate, closed the materialization gap without inventing a second release ledger, a second asset registry, or a customer-provider execution bridge — satisfying this phase's explicit prohibition on parallel systems.

**Impact:**
- Modified (extension-sdk, portable/Laravel-free): `packages/saas-platform/modules/extension-sdk/src/ExtensionElementDefinition.php` (assets shape + validation), `ExtensionManifest.php` (namespace-prefix enforcement), `ExtensionSdkException.php` (three new factory methods).
- Modified (SaaS app layer): `app/Services/ThemeBuilder/Extension/ExtensionReleaseService.php` (`$assetSources` param, materializer call before the release transaction), `app/Http/Controllers/Admin/ThemeCanvasController.php` (asset emission wired into `preview()`/`livePreview()`), `resources/views/admin/theme-builder/canvas-preview.blade.php` and `canvas-live-preview.blade.php` (`<link>`/`<script>` emission), `app/Services/ThemeBuilder/Extension/ExtensionCanvasPaletteAdapter.php` (`elementAssetsFor()`/`assetsUsedIn()`), `app/Services/ThemeBuilder/Dependency/ThemeExtensionBlockTypeExtractor.php` (instance-level extraction, unrelated ownership-instance closure landed alongside this phase).
- New: `app/Services/ThemeBuilder/Extension/ExtensionAssetMaterializer.php`.
- New tests: `tests/Unit/ExtensionSdk/ExtensionElementAssetsTest.php`, asset-namespace tests in `ExtensionManifestTest.php`, `tests/Feature/ThemeBuilder/Extension/ExtensionCanvasAssetEmissionTest.php`, `ExtensionAssetDistributionTest.php`, asset tests added to `ExtensionReleaseServiceTest.php`; browser proof `tests/browser/theme-canvas-extension-assets.spec.ts` (real two-release-backed, not fixture-only).
- No DB migration — `ExtensionRelease.manifest` (existing JSON column) represents the asset source contract; no new column was needed. No `ext:*` block JSON migration. No Theme content migration. No customer-site file changed (read-only compatibility audit only: customer's `HasAssets::getAssets()` already returns this exact `{css,js}` root-relative shape and could later adapt into this declarative contract, but that adaptation — and whether it too adopts version-namespacing — is a future customer-side decision, not made here).
- **Classification**: `ExtensionAssetMaterializer` and the extended `ExtensionManifest`/`ExtensionElementDefinition`/`ExtensionCanvasPaletteAdapter` asset surface are CANONICAL. Physical distribution beyond this SaaS instance's own `public/` (e.g. CDN, multi-node sync) is explicitly OUT OF SCOPE and not addressed by this ADR — nothing here assumes or precludes it.

**Addendum (Phase 3G-B-6C) — source confinement and failed-publication cleanup:**
Point 8 above left `$assetSources` values (absolute local source file paths) unconstrained — audited and found genuinely too permissive: any readable absolute path on the host, including outside the application entirely, could be materialized. `ExtensionReleaseService::release()` has no HTTP/API caller yet (audited: `SeedE2ECanvasFixtures`/tests only), but this was closed proactively, before any upload UI is ever built on it:
16. Every asset source MUST resolve, via `realpath()` (never a raw string-prefix check on the unresolved input), to a regular, readable file strictly inside the ONE trusted staging root `ExtensionAssetMaterializer::stagingRoot()` — `storage_path('app/extension-asset-staging')`, the smallest addition consistent with the one existing precedent for a locally-staged commercial artifact (`ModulePackageBuilderService`'s own `storage_path('app/modules/packages')`). Arbitrary local filesystem sources — system files, unrelated app files, anything outside that root — are forbidden and rejected with `InvalidArgumentException`, regardless of the path string supplied.
17. Resolving both the root and the candidate source through `realpath()` before comparison collapses `../` traversal segments and follows symlinks to their real target BEFORE the confinement check runs — a symlink placed inside the staging root that points outside it is rejected exactly like any other outside-the-root source, on any OS (Windows dev-environment note: creating a symlink to prove this in a test requires a privilege this session's shell did not hold, so that one test self-skips here rather than false-failing; the `realpath()`-first logic itself is unconditionally symlink-safe by construction, independent of that).
18. A directory, a missing path, or an unreadable path at the source is rejected the same way a missing source already was — never silently skipped.
19. `ExtensionAssetMaterializer::materialize()` now returns exactly the declared paths THIS call newly created (never an already-idempotent pre-existing match, never a path belonging to another release). If anything after materialization fails to publish the release row (lock timeout, the ADR-046 ownership re-check, any DB write failure), `ExtensionReleaseService::release()` calls `cleanupNewlyMaterialized()` with exactly that list before rethrowing the original failure — removing only the files this specific failed attempt created.
20. Cleanup never deletes a pre-existing idempotent file, another release's file, or a whole vendor/package directory — only the exact newly-created paths, one `unlink()` each. A cleanup failure is logged (existing `Log::warning` convention) and never masks or replaces the original release failure, which is always what propagates to the caller.
21. No new locking was introduced or found necessary: the existing global ownership `Cache::lock()` plus the version-namespaced path (point 7) already make two normal concurrent `release()` attempts unable to corrupt the same namespace — confirmed by audit, not re-designed.
22. No DB migration and no `ext:*`/Theme JSON migration were required for either closure.

**Addendum impact:** Modified `app/Services/ThemeBuilder/Extension/ExtensionAssetMaterializer.php` (confinement + newly-created-path tracking + `cleanupNewlyMaterialized()`), `ExtensionReleaseService.php` (calls cleanup on any post-materialization failure). New tests in `ExtensionAssetDistributionTest.php` covering source confinement (valid/outside-root/traversal/symlink/directory/missing/arbitrary-system-file) and failure cleanup (A-E: new-file removed, pre-existing idempotent file preserved, multiple new files all removed, successful release keeps its files, an older release's files survive a later release's failure). `SeedE2ECanvasFixtures.php` and all affected test fixtures updated to stage their source files inside the trusted root. No customer-site change. No browser rerun — public asset URLs/emission behavior are unchanged; the existing 12/12 Chromium evidence stands.

## ADR-049 — Canonical Customer Media Foundation

**Date:** 2026-09-10

**Decision:**

1. `customer-site` owns customer media through `CustomerMedia` and `CustomerMediaService`; it is neither a platform master-Theme asset authority nor a Motion or support-attachment authority.
2. `CustomerMedia.site_id` is required and is taken exclusively from `site.platform_site_id`; this is **per-site**, never per customer/account/platform. Every read, mutation, URL lookup, purge, and usage registration is server-side scoped to that site.
3. A media UUID is the stable authoring identity. Storage keys are opaque service-generated values under `customer-media/{site_id}/...`; controllers and Builder clients do not generate paths.
4. V1 allows JPEG, PNG, GIF, WebP, MP4, WebM, MP3, OGG, WAV, and PDF within centrally defined size limits. SVG is rejected pending an audited canonical sanitizer.
5. `CustomerMediaUsage` is the single usage ledger. It records a consumer type/id and a deterministic JSON reference path; its FK is restrictive so usage evidence cannot cascade away. Future Builder data uses `{media_id: UUID}`; legacy URL/path payloads remain read-only transitional data until migrated by a bounded adapter. Forged UUIDs from another site fail closed at the same site-scoped lookup boundary.
6. Every `ThemeInstanceRevision` registers nested canonical media IDs as immutable usage. Historical revisions therefore block deletion and rollback cannot silently lose a referenced asset.
7. An unused asset is soft-deleted before physical purge. Used assets are rejected. A physical deletion failure leaves a retryable soft-deleted record rather than a misleading successful deletion.
8. `ThemeDesignMediaController` is TRANSITIONAL: its endpoints must become compatibility adapters over this service in MEDIA-4. `saas-platform`'s `ThemeMediaController` is KEEP_SEPARATE pending master-authoring review; it must never write customer media.

**Impact:** MEDIA-1/2 only. No Media Library UI, Builder picker conversion, module migration, Theme release mutation, import/export behavior, or platform master-asset migration is authorized by this ADR.

---

## ADR-050 — Testing & Delivery Governance, and Retirement of the Superseded Master Development Directive

**Date:** 2026-09-11

**Context:**

A production diagnosis (Theme Builder / Page Builder edits not reaching the
public page) repeatedly stalled on undefined questions: what must be tested,
which environment is authoritative, and what "done" means. Auditing existing
governance before writing anything new surfaced two problems: (1)
`customer-site/docs/MASTER-DEVELOPMENT-DIRECTIVE.md` separately claimed to be
"source of truth for every coding task in both repositories," directly
conflicting with `PROJECT_CONSTITUTION.md`'s own claim to the same authority,
even though `customer-site/AGENTS.md` already defers all governance to this
repository; (2) no canonical document defined mandatory test/browser/delivery
gates, so this determination was repeatedly re-litigated per task.

**Decision:**

1. `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md` is adopted as canonical
   for both repositories, subordinate to `PROJECT_CONSTITUTION.md`, at the
   same tier as `ARCHITECTURE.md`/`KNOWN_ISSUES.md` in `AGENTS.md`'s
   Documentation Ownership table. It defines test layers L0–L7, a Feature
   Acceptance Matrix, Browser Acceptance Rules, the Builder
   authoring→save→reload→preview→public-render contract, regression
   classification, an Authorization Policy for commit/push/deploy, commit/
   push/deployment gates, a delivery state machine, and a final-report
   contract. It references existing working mechanisms
   (`RELEASE_GATE.md`, `KNOWN-TEST-FAILURES.md`, `BUILDER-QA.md`,
   `tests/browser/`) rather than duplicating them.
2. `customer-site/docs/MASTER-DEVELOPMENT-DIRECTIVE.md` is marked historical/
   superseded in place (not rewritten or deleted) per Constitution §12.

**Preservation matrix** (old directive rule → current canonical authority →
preserved?):

| Old directive rule | Current canonical authority | Preserved? |
|---|---|---|
| Backward compatibility mandatory | Constitution §12 | Yes |
| Every significant change needs unit/feature/regression tests | Governance doc §1–2 (L1/L2/L4) | Yes |
| Destructive DB operations require explicit authorization | Constitution §5 | Yes (general terms) |
| Customer entitlements must never be lost / no automatic revocation | Constitution §9 + §12 | Partial — substance present, not restated as crisply |
| Every important action must be audited | Constitution §7 (security-sensitive ops) | Partial — narrower scope than the original |
| Update `docs/CHANGELOG.md` / `docs/PROJECT-GUIDE.md` per change | — | Not preserved; left as an open item, not asserted as covered |
| "Analyze → Analysis Report → wait for explicit approval" before code changes, commits, merges, deployments | Governance doc §9 Authorization Policy (points 2–6) | **Yes — intentionally superseded with an explicit canonical replacement**, modernized to task-scoped explicit authorization in conversation rather than a mandatory separate written report |
| Never work directly on `main`; dedicated branch per change; `git merge --no-ff`; never delete branches/tags without instruction | Governance doc §9 Authorization Policy (points 1, 7) | **Yes — intentionally superseded with an explicit canonical replacement**: a branch is required only when the task or a repository policy calls for one; force-push/rebase/reset/stash/clean require explicit per-action authorization |

No rule was silently discarded: each row above is either preserved through
an existing authority or intentionally superseded through the specific,
named replacement in §9 of the governance document.

**Reason:** A change this project keeps re-deriving under pressure (as in
the 2026-09-11 incident) belongs in a document that's loaded automatically,
not re-argued per task. Retiring the conflicting directive removes the
"two supreme authorities" problem without discarding the safety intent
behind its rules.

**Impact:** Documentation only. No product/runtime code changed. The two
"not preserved" rows above (CHANGELOG/PROJECT-GUIDE update convention;
crisper entitlement/audit-everything wording) remain open — a future task
that touches those areas should either follow the general Constitution
coverage or raise a follow-up ADR, not assume silence means the rule no
longer applies.

**Never violate:** Do not reintroduce a second document that claims
top-level authority over both repositories without an explicit superseding
ADR (Constitution §13).

---

## ADR-051 — Builder Media Convergence (MEDIA-4)

**Date:** 2026-09-11

**Decision:**

1. The Customer Media Library (`CustomerMedia` / `CustomerMediaService`,
   [[ADR-049]]) is Core: per-site, always available, and is now the only
   place customer media is written, listed, or deleted from — including
   from the Theme Builder.
2. `CustomerMediaService` is the sole customer media write authority.
   `ThemeDesignMediaController` is compatibility-only over it: it performs
   no independent storage, upload, or delete logic and adds no second
   write path.
3. The canonical new Builder media identity is `{ media_id: UUID }`.
   Legacy URL/path values already stored in Builder props are read-only
   compatibility forms — resolved and rendered unchanged, never rewritten
   in place.
4. `CustomerMediaResolver` is the single runtime resolver for customer
   media refs in Canvas rendering. Every media-capable block prop and
   repeater field resolves through it, once, before render; individual
   block renderers never perform their own media lookup. It enforces
   current-site ownership on every lookup and fails safe: a foreign,
   deleted, malformed, or missing reference resolves to `''` (rendered as
   "no image selected"), never an error and never a guess.
5. The Theme Builder picker and the main Media Library page read and write
   the same `CustomerMedia` records — media uploaded from either surface
   is immediately visible and reusable from the other.
6. Customer media UUIDs must never become master Theme or portable product
   identity. They are site-scoped authoring data only.
7. Media usage/deletion safety continues to run through the existing
   `CustomerMediaUsage` ledger and `ThemeInstanceRevision` integration
   ([[ADR-049]] points 5–6); MEDIA-4 does not change that mechanism.
8. An exposed media control must have real runtime effect — persistence of
   a value alone (e.g. a forged foreign `media_id` reaching a draft) is not
   sufficient; enforcement happens at resolve/publish time per point 4.
9. Unsupported pre-existing domains are carried forward unchanged by this
   ADR: no video renderer, no editable Canvas for `nav_header`/`nav_footer`,
   no image field on `client_logos`.
10. No second customer media repository, storage location, or runtime
    resolution authority may be introduced. Any future consumer of
    customer media (Builder or otherwise) integrates through
    `CustomerMediaService` and `CustomerMediaResolver`.

**Impact:** `customer-site` only — `ThemeDesignMediaController`,
`ElementSchemaRegistry`, `BlockRegistry`, `ThemeCanvasRenderer`,
`CustomerMediaResolver` (new), `HeroElement`, and the canvas-editor Builder
UI. Completes the `ThemeDesignMediaController` TRANSITIONAL status recorded
in [[ADR-049]] point 8.

---

## ADR-052 — Single Theme Install Authority and Structural Header/Footer Presets

**Date:** 2026-09-12

**Context:** A real, reproduced defect: `ThemeCanvasController::installToSite()`
wrote `active_canvas_theme_data`/`_name`/`_slug`/`_id` directly into the
customer's database over a raw cross-server MySQL connection, then
raw-inserted real `is_protected` `Page` rows for every installed page slug
(`provisionThemePagesIntoDb()`). Those Page rows never had any rendering
role — `ThemeCanvasPageController::show()` always resolves a canvas-owned
page first — but they occupied the same slugs the installed Canvas theme
owned. Any later operation that goes through customer-site's own
`ThemeInstanceRevisionService::record()` (self-heal backfill, and any
normal customer save/publish) calls `CanvasReservedSlugs::collidingWithExistingPages()`
and fails closed against exactly those rows, so a site installed this way
was permanently unable to save or publish its own Canvas theme afterward.
This was two uncoordinated install authorities — raw SQL from
saas-platform, and the revision service everything else in customer-site
already uses — disagreeing about the same namespace.

**Decision:**

1. There is exactly one Theme install authority: customer-site's own
   `ThemeInstanceRevisionService::record()`, the same single write path
   every other way the active Canvas theme can change (publish, import,
   rollback, upgrade-reconcile) already uses. `installToSite()` no longer
   writes to the customer's database directly or over a raw connection —
   it makes one authenticated HTTP call (`X-Provision-Secret`, mirroring
   the existing `ThemePurchaseOrderObserver` pattern) to a new canonical
   customer-site endpoint, `POST /builder/api/theme/install`
   (`PageBuilderController::installTheme()`), which calls `record()` with
   `type=install` and real provenance (`source_theme_id`,
   `source_theme_version`, `source_release_id` resolved from the Theme's
   `currentRelease`).
2. Install is therefore subject to the exact same `CanvasReservedSlugs`
   collision guard as every other lineage-changing write, with no
   special-casing: a target site with a conflicting `Page` row rejects the
   install (HTTP 409, surfaced to the platform admin) instead of silently
   applying a state the customer's own revision system would later refuse.
3. Canvas-owned pages remain embedded in the installed `active_canvas_theme_data`
   only. Install never creates `Page` rows for those slugs — the legacy
   `provisionThemePagesIntoDb()` raw-SQL page insert is no longer called
   from `installToSite()` (it remains, unchanged in behavior, only for the
   unrelated `syncPagesForSite()` re-sync action, which is out of this
   ADR's scope and tracked as follow-up debt). This preserves the existing
   namespace invariant both directions already relied on:
   `ACTIVE_CANVAS_PAGE_SLUGS ∩ PAGE_BUILDER_PUBLIC_SLUGS = ∅`.
4. The Universal Multipurpose Theme (`universal-multipurpose-qa`, seeded by
   `UniversalMultipurposeQaThemeSeeder`) is a permanent integration and
   regression surface, not a one-off QA fixture: its install round-trip
   (install → revision → provenance → 16 pages → public render → a normal
   post-install save/publish → public render again) is covered by real,
   permanent tests against real migrated databases —
   `saas-platform/tests/Feature/Admin/ThemeCanvasInstallToSiteHttpTest.php`
   (the outbound HTTP contract) and
   `customer-site/tests/Feature/ThemeCanvas/ThemeInstallRoundTripTest.php`
   (the real endpoint, service, and public render) — with no fake install
   adapter and no direct-DB shortcut on either side.
5. A Header or Footer preset is "structurally distinct" only by its
   ordered block-TYPE signature (e.g. `[nav_header, cart_icon]` vs.
   `[social_icons, nav_header]`) — different props on the same single
   `nav_header`/`nav_footer` block is a reskin, not a structure. The
   Universal Multipurpose Theme ships 6 Header and 6 Footer presets with 6
   genuinely unique signatures each (verified by exact ordered type-list
   comparison in `UniversalMultipurposeQaThemeSeederTest`, not merely a
   block-count or preset-count check), built entirely from existing
   canonical block types (`nav_menu`, `cart_icon`, `search_bar`,
   `social_icons`, `contact_info`, `language_switcher`, `newsletter_form`)
   — no new block type or parallel builder was introduced to reach this.

**Impact:** `saas-platform` — `ThemeCanvasController::installToSite()`
(rewritten), `UniversalMultipurposeQaThemeSeeder` (`headerPresets()`/
`footerPresets()` rewritten for genuine structural distinctness),
`UniversalMultipurposeQaThemeSeederTest` (strengthened assertion). Local
`.env` only: `CUSTOMER_PROVISION_SECRET` set to a throwaway local value in
both repos so the new endpoint is reachable in local dev/testing —
production deployments must set a real shared secret, unchanged from the
existing webhook contract. `customer-site` — new
`PageBuilderController::installTheme()` + `POST /builder/api/theme/install`
route, reusing the existing `provision.secret` middleware; no change to
`ThemeInstanceRevisionService`, `CanvasReservedSlugs`, or
`ThemePageProvisionService` (the unrelated purchase-order page-provisioning
webhook). `syncPagesForSite()`'s continued use of the raw-SQL page insert
is known, pre-existing, out-of-scope debt, not touched by this ADR.

---

## ADR-053 — Master Draft Checkpoint Completeness and Canonical Canvas Operational Parity (UI-C2D/C2E)

**Date:** 2026-09-12

**Context:** `ThemeRevision` (`ThemeBuilderService::saveRevision()`/
`restoreRevision()`) is the master-authoring draft checkpoint tool —
separate from `ThemeRelease` (immutable commercial ledger) and
customer-site's `ThemeInstanceRevision` (per ADR-021 §2, a boundary this
ADR does not touch). Audited against the actual live write paths
(`ThemeCanvasController::save()`/`addPage()`/`removePage()`), its snapshot
omitted `pages` — the same gap ADR-021 already documented for this exact
method when it verified `ThemeRelease`'s snapshot was complete by
comparison. The same omission was found, independently, in `cloneTheme()`,
`exportTheme()`, and `importTheme()` — all four operations copied
`header_blocks`/`footer_blocks`/`global_styles`/`settings` but silently
dropped a Theme's authored pages. `settings` (which already carries
`extension_selections`, per ADR-047) was already copied whole by all four,
so Extension selections were never actually missing — only `pages` was.
Separately, `saveRevision`/`restoreRevision`/`cloneTheme`/`exportTheme`/
`importTheme`/`unpublish` were reachable only from the legacy raw-JSON
editor (`edit.blade.php`) and its index-page action links — never from the
canonical Theme Canvas — leaving the raw editor as the sole surface for
checkpoint history, clone, export, import, and unpublish.

**Decision:**

1. **The canonical checkpoint snapshot now includes `pages`** in both
   `saveRevision()` (capture) and `restoreRevision()` (restore) — verified
   by an exact-restore round-trip test (state A → checkpoint → state B →
   restore → exact state A across pages/header/footer/global_styles/
   settings). Restore remains content-only: it never touches Theme
   identity, never creates or mutates a `ThemeRelease`, and
   `ThemeInstanceRevision` remains architecturally unreachable from this
   app (a separate app/database, per ADR-021).
2. **`cloneTheme()`, `exportTheme()`, and `importTheme()` now also carry
   `pages`** — the same fix, applied consistently across every operation
   that reads or reconstructs master-authoring state, not just the
   revision tool the original audit named.
3. **`exportTheme()`'s existing `version` key is the one canonical export
   format/schema authority** — bumped to `2` for this phase; `importTheme()`
   still accepts a version-1 file (no `pages` key) by defaulting it to
   `[]`, exactly like it already defaulted `header_blocks`/`footer_blocks`.
   No second export format was introduced.
4. **`importTheme()` now validates before writing anything**: a supported
   version, a well-formed `pages` shape, every block type resolving to
   either `BlockRegistry::all()` (the one canonical registry — reused, not
   duplicated) or a real `ext:*` Extension ownership/version selection
   (validated via the existing `ExtensionCanvasPaletteAdapter::
   validateBlocksAgainstSelections()` — also reused, not duplicated). Any
   failure throws before `Theme::create()` runs; a rejected import creates
   no row, proven by test. Import is wrapped in `DB::transaction()`.
5. **`cloneTheme()`/`exportTheme()`/`importTheme()` never copy commercial
   or customer state**: no `current_release_id`, no release provenance, no
   `site_id` in the export payload, no customer media UUID (structurally
   impossible — the master `Theme` model has no relation to customer-site's
   `CustomerMedia` at all), no license/order data. A clone always gets a
   new slug and resets to `Theme::STATUS_DRAFT`, never inheriting the
   source's published/release state.
6. **The canonical Theme Canvas now exposes checkpoint history (create/
   list/restore with confirmation), clone, export, import, and unpublish**
   behind one "بیشتر" (More) menu — no raw JSON exposed to accomplish any
   of it, no second editor. `unpublish()` was already correctly scoped
   (`Theme.status` only, verified unchanged by this phase) and is now also
   reachable from the Canvas; a new read-only `GET .../revisions` JSON
   endpoint backs the checkpoint list. The raw editor's own existing
   actions are unchanged and gained no new unique responsibility.

**Reason:** The same class of bug (pages silently dropped from a
Theme-authoring snapshot) existed independently in four places because
each operation had its own ad hoc field list instead of a single shared
notion of "master-authoring state." Fixing only the one operation named by
the prior audit would have left the other three still capable of losing a
theme's pages on clone/export/import. Exposing the fixed operations from
the canonical Canvas — reusing the existing routes/controller methods,
adding only JSON-response branches and one read endpoint — is what makes
raw-editor de-exposure (UI-C3) a real future possibility instead of a
permanent architectural dead end.

**Verification:** `ThemeBuilderCheckpointAndActionsTest` (15 tests: exact
checkpoint restore, extension-selection preservation via `settings`,
release/customer-state non-mutation, clone equivalence + new identity,
export/import structural equivalence, legacy version-1 import
compatibility, import validation rejecting unsupported version/unknown
block type/invalid Extension selection/malformed `pages`, atomic
no-partial-row-on-failure, unpublish release-ledger non-mutation);
`ThemeCanvasActionSurfaceTest` (7 tests, real HTTP through real routes/
middleware: Canvas view renders the new action surface, revision listing,
JSON-branch restore/clone/import, unpublish); real browser suite
`theme-canvas-checkpoint-actions.spec.ts` (8 tests: checkpoint create/
list/exact-restore, clone, export download, import via real file upload,
unpublish via real UI with a real prior release, responsive reachability
at 1366/768/390 — stable across 2 consecutive full runs). Full
`tests/Feature/ThemeBuilder`/`tests/Unit/ThemeBuilder` regression:
564 passed, 1 pre-existing environment self-skip, 0 failures.

Two pre-existing, environment-caused browser-suite failures were found and
are NOT part of this phase's scope: (a) `theme-canvas-extension-
authoring.spec.ts`'s toolbar-reachability check uses a stale
`div.h-14.bg-white.border-b` locator — the toolbar has used `min-h-14`
since before this phase (confirmed against the pre-existing committed
file); (b) two `theme-canvas-global-styles.spec.ts`/`theme-canvas-
zones.spec.ts` publish tests fail because `PlaywrightExtractionService`
(used by `ColorContrastCheck`/`ResponsiveReadinessCheck` in the Commercial
Quality Gate) spawns its own headless browser from
`scripts/headless/.playwright-browsers`, which does not exist in this
environment — reproduced in isolation, unrelated to any file this phase
touched. A third, `theme-canvas-zones.spec.ts`'s dirty-indicator text
check, has a pre-existing 2-way locator ambiguity (`getByText('ذخیره
نشده')` matches both the existing dirty-indicator span and the existing
Global Styles dirty span) — confirmed to fail identically with this
phase's own contribution removed. This phase's checkpoint-modal empty-state
text was renamed to avoid adding a third match to that ambiguity, but the
underlying pre-existing 2-way collision was left as recorded, out-of-scope
debt.

**Impact:** `saas-platform` only. `app/Services/ThemeBuilder/
ThemeBuilderService.php` (`saveRevision`/`restoreRevision`/`cloneTheme`/
`exportTheme` gained `pages`; `importTheme` rewritten with validation +
transaction; new `supportedImportVersions()`/`unknownBlockTypes()`).
`app/Http/Controllers/Admin/ThemeBuilderController.php` (JSON branches on
`restoreRevision`/`cloneTheme`/`import`; new `listRevisions()`).
`app/Http/Controllers/Admin/ThemeCanvasController.php` (`show()` now also
passes `sites`). `routes/web.php` (one new `GET .../revisions` route).
`resources/views/admin/theme-builder/canvas.blade.php` (new More-actions
menu, Checkpoints modal, Clone modal; no changes to `edit.blade.php`).
`ThemeRelease`, `ThemeInstanceRevision`, `CanvasReservedSlugs`, the shared
`BlockRegistry`/`BlockRenderer`, and `ExtensionCanvasPaletteAdapter` are
all unchanged — reused, not duplicated. No migration.

---

## ADR-054 — Raw/JSON Theme Editor De-Exposure (UI-C3, Cross-Repo)

**Date:** 2026-09-12

**Context:** With ADR-053 (UI-C2D/C2E) having brought the canonical
saas-platform Theme Canvas to full behavioral parity with the raw/JSON
editor (`ThemeBuilderController::edit()`/`edit.blade.php`) — pages,
header, footer, global styles, Extension selections, checkpoint, clone,
export, import, publish, unpublish — this phase audited both repositories
for every Theme-related authoring surface before removing anything, per
the workspace's own "prove CANONICAL/REQUIRED/TRANSITIONAL/OBSOLETE before
deleting" discipline.

**Cross-repo inventory findings:**
1. **customer-site has no raw/legacy Theme editor at all.** Its canonical
   Canvas (`ThemeDesignController`, `admin.theme-design.canvas-edit`) is
   the sole authoring surface; every write (save-blocks, publish, install,
   rollback, upgrade) already goes through `ThemeInstanceRevisionService`.
   Import is file-upload based, never a raw-JSON textarea. Customer-site
   required **zero code changes** for this phase — it was already fully
   converged.
2. **saas-platform's only genuine competing raw-JSON *editor* for an
   existing `Theme`'s content is `ThemeBuilderController::edit()`/
   `update()`** (`edit.blade.php`). Six other controllers surfaced during
   the audit (`ThemeJsonImportController`, `ThemeZipImportController`,
   `ThemeReverseEngineeringController`, `ThemePackController`,
   `ThemePackStudioAdminController`, `ThemeTemplateController`) are
   legitimate, non-competing capabilities: the first three are creation
   *pipelines* that ingest external input (a JSON file, a ZIP, a scraped
   URL) and redirect into the canonical Canvas once a new `Theme` exists;
   the latter three operate on entirely different models (`ThemePack`,
   `ThemeTemplate` — block/section-level snippets, not full-Theme editing)
   and never touch `Theme.header_blocks`/`.footer_blocks`/`.pages`
   directly. None were changed.
3. **`ThemeManagerController`/`ThemeProfile` ("Enterprise Theme Engine")
   is a color/design-token system** (`--ui-*`/`--ds-*` CSS custom
   properties), not a Theme-content authoring surface — confirmed
   unrelated to this phase's scope, and its admin-nav label
   ("پروفایل‌های رنگی پلتفرم") was already disambiguated from Theme
   Builder in an earlier phase (UI-C1, `ThemeBuilderCanonicalEntryTest`).
4. **UI-C1 (an earlier phase) had deliberately kept the raw editor linked
   from the Theme list**, merely relabelled honestly as advanced/JSON
   rather than a primary Builder — the correct, safe interim step before
   Canvas had full parity. This phase supersedes that interim policy now
   that ADR-053 closed the parity gap; `ThemeBuilderCanonicalEntryTest`'s
   test D was updated accordingly (was: assert the link stays visible;
   now: assert it does not).

**Decision:**
1. **Route policy for `admin.theme-builder.edit` (GET) and
   `admin.theme-builder.update` (PUT): TEMPORARY_COMPATIBILITY.** Both
   remain fully functional, still write through the exact same
   `ThemeBuilderService::updateTheme()` / `ExtensionCanvasPaletteAdapter`
   validation gate as before (no second persistence authority, no
   authorization change), but are no longer linked from any normal,
   discoverable surface. `edit.blade.php` gained an explicit banner
   pointing to the canonical Canvas, so anyone who does reach it by direct
   URL is told plainly it is an advanced/compatibility page.
2. **The Theme list's per-row "ویرایش پیشرفته (JSON)" link is removed.**
   The Canvas button ("🎨 قالب‌ساز") is the only edit-worded action per
   row; export/clone/delete remain as secondary, clearly non-editing
   actions.
3. **`cloneTheme()` and `import()`'s non-JSON (classic form-submit)
   success redirects now target the canonical Canvas of the new Theme**,
   not the raw editor — a normal admin cloning or importing a Theme from
   the list must never land in the raw editor as a side effect. The raw
   editor's own internal loop (its own save/publish/unpublish/restore
   buttons redirecting back to itself) is unchanged — that is the
   editor's legitimate, self-consistent navigation while someone is
   deliberately using it directly, not a newly-introduced discoverable
   path.
4. **No route was removed or deleted.** Per this workspace's own
   evidence-first policy: proving zero unique *product* responsibility
   remains is a de-exposure justification, not a deletion justification —
   the compatibility route stays reachable for advanced/manual use,
   scripts, or support diagnostics.
5. **Page Builder's boundary is unchanged and was not touched.** Both
   repos already keep Page-owned content (`PageBuilderController`/`Page`)
   structurally separate from Theme-owned Canvas content; this phase's
   audit confirmed that separation, did not merge it, and defers any
   Page Builder discoverability work to a future UI-C4.

**Reason:** The workspace's cross-repo Theme architecture converges on
exactly two canonical authoring surfaces — saas-platform's master Theme
Canvas and customer-site's installed-Theme Canvas — each already the
single write path for its own side. The only asymmetry was saas-platform
still *offering* its superseded raw editor as an equally-visible choice
after its replacement reached full parity; removing that offer (without
removing the underlying, still-valid compatibility route) is the correct,
minimal close of that asymmetry.

**Verification:** `ThemeBuilderCanonicalEntryTest` (6 tests, updated);
`ThemeBuilderCheckpointAndActionsTest`/`ThemeCanvasActionSurfaceTest`
(22 tests, re-run, confirming UI-C2D/C2E parity survived unaffected);
full `tests/Feature/ThemeBuilder`/`tests/Unit/ThemeBuilder` regression
(566 passed, 1 pre-existing environment self-skip, 0 failures); new real
browser suite `theme-list-canonical-entry.spec.ts` (7 tests: one canonical
primary action per row, Canvas entry preserves all UI-C2D/C2E operational
actions, back-navigation, raw-editor-route-still-reachable-but-unlinked,
responsive at 1366/768/390 — stable across 2 consecutive full runs);
customer-site full regression (141 tests, 0 failures, 0 files changed);
customer-site `save-to-public-render.spec.js` (3 tests) as the focused
customer-runtime check; saas-platform `UniversalMultipurposeQaThemeSeederTest`
as the focused Universal Multipurpose Theme regression check.

**Impact:** `saas-platform` only —
`app/Http/Controllers/Admin/ThemeBuilderController.php` (two redirect
targets), `resources/views/admin/theme-builder/index.blade.php` (one link
removed), `resources/views/admin/theme-builder/clone.blade.php` (one link
retargeted), `resources/views/admin/theme-builder/edit.blade.php`
(compatibility banner added), `tests/Feature/ThemeBuilder/
ThemeBuilderCanonicalEntryTest.php` (test D updated for the new policy),
new `tests/browser/theme-list-canonical-entry.spec.ts`. `customer-site`:
no files changed. No migration. No dependency or lockfile change. No
route deleted; no authorization rule changed.

## ADR-055 — Platform Commercial Authority Confirmed; Universal Theme Made a Real Commercial Product; Media Localization Closed

**Date:** 2026-09-18

**Context:** The coordinator asked for a Phase 6.5 audit assuming
customer-site had accumulated a parallel master-Theme-authoring
architecture that needed end-to-end removal, plus a root-cause fix for why
the coordinator could not inspect/acquire the Universal Theme
(`universal-multipurpose-qa`) through the real platform-admin →
license/entitlement → customer-site commercial control plane.

**Audit finding — the assumed premise was false, and this is itself the
finding:** a full read/trace audit of every Theme-related route,
controller, service, and admin-nav entry in customer-site (routes/web.php,
`ThemeDesignController`, `ThemeMarketplaceController`, `ThemeBundleController`,
`PageBuilderController`, `AdminMenu.php`) found **zero** surfaces that
create, publish, or release a master Theme product. The one prior parallel
authority (`ThemePageProvisionService::provision()`) was already removed in
an earlier pass and is now a stub (`unprotect()` only), confirmed by both
that file's own docblock and the removal comment at routes/web.php's
`builder.api.theme.*` group ("THEME_INSTALL_MUTATION_AUTHORITIES = 1").
Every other Theme surface in customer-site is either CANONICAL_CUSTOMER_INSTANCE
(edits this site's own already-installed Theme instance) or a pure
saas-platform HTTP pass-through (`PlatformApiService`). No code was deleted
in customer-site for this reason — there was nothing obsolete left to
remove. `NoParallelMasterThemeAuthoringTest` (new, 4 tests) codifies this
conclusion as a permanent fitness check rather than a one-time finding.

**Real root cause of "cannot inspect the Universal Theme through the real
control plane":** `universal-multipurpose-qa` had only ever existed as a
`Theme::STATUS_DRAFT`, `is_marketplace=false` row created by
`UniversalMultipurposeQaThemeSeeder` for test bootstrapping — it had never
been run through `ThemeCommercialQualityGate::publishIfCompliant()`, so it
had no `ThemeRelease`, `Theme::isCommerciallyEligible()` was permanently
false, and `ThemeMarketplaceApiController::index()`'s real catalog query
(`is_marketplace + status=published + isCommerciallyEligible()`) could
never surface it. This is a genuinely different defect from the assumed
"customer-site has a parallel authoring system" premise — the gap was on
the platform side, in never having actually published the theme.

**Running the real Commercial Quality Gate against it for the first time
surfaced further real, previously-undiscovered defects** (this environment's
Redis had been down, so `ResponsiveReadinessCheck`'s real Playwright-backed
viewport check had been silently self-reporting `unverified` instead of
actually running — restoring Redis made the gate run for real):
1. `RenderIntegrityCheck` false positive: it recursed into a `slider`
   block's `slides` array as if each entry were a nested Block object
   requiring a `type` — but both hosts' real `renderSlider()`
   implementations consume `slides` as flat per-slide data
   (image/title/subtitle/link), never a block tree (unlike `tabs`/
   `accordion`, which genuinely do contain nested block trees). Fixed by
   removing that incorrect recursion (`RenderIntegrityCheck.php`).
2. The seeder's `builder-coverage` regression page mapped every remaining
   registered block type to its own registry `defaultProps` — correct for
   almost every type, but `icon`'s own contractual default (`name: ''`) is
   deliberately empty (no generic icon name makes sense platform-wide),
   which is right for the Builder's "freshly inserted, nothing authored
   yet" UX but produced real empty output on this coverage page. Fixed
   with one minimal, non-marketing override (`name: 'star'`).
3. **Real catastrophic horizontal overflow** (232px @768, 610px @390) on
   the theme's own home page, once the browser check could actually run:
   root-caused to the `gallery` block (`.tb-gallery-img`) and
   `team_cards`' `.tb-team-avatar` having **zero CSS in either host** —
   images rendered at raw intrinsic pixel size with no bounding rule.
   Also found: saas-platform's own Theme Builder preview
   (`resources/views/admin/theme-builder/canvas-preview.blade.php`) — the
   same render path both `ThemeCommercialQualityGate` and every Platform
   Admin's own live preview use — had silently fallen far behind
   customer-site's per-block visual CSS (ADR-029 makes this host-owned by
   design, but the copies had drifted to the point of zero parity for
   `hero_split`/`hero_gradient`/`nav_header`/`nav_footer`/`promo_banner`/
   `countdown`/wishlist/loyalty/installment-badge, all added to
   customer-site across Phase 6 without an equivalent saas-platform-side
   update). Fixed: real gallery/team-avatar CSS added to both hosts;
   saas-platform's preview brought back to visual parity with
   customer-site's for every block added since Phase 2.

**Real commercial delivery, proved through canonical boundaries, no
fixture-only acceptance:** `UniversalThemeCommercialDeliveryTest` (new, 3
tests) drives the actual authenticated HTTP `admin.theme-builder.publish`
route (real `ThemeCommercialQualityGate` run, real `ThemeRelease` created),
confirms real Marketplace catalog visibility, grants a real `ThemeLicense`
via the same free-acquisition path any free theme uses (not a raw
`ThemeLicense::create()`), and installs to two independent sites through
the existing, unmodified `ThemeCanvasController::installToSite()` →
`ThemeInstallGateway` → customer-site `/builder/api/theme/install` HTTP
boundary (already covered end-to-end by `ThemeCanvasInstallToSiteHttpTest`
+ customer-site's `ThemeInstallRoundTripTest`) — confirming per-site
license isolation and that neither install mutates the master Theme or its
immutable release.

**MASTER_THEME_MEDIA_LOCALIZATION_GAP — closed, not deferred again.**
Previously master-theme stock imagery stayed hotlinked to saas-platform's
own storage domain forever after customer install, because
`CustomerMediaResolver::resolveValue()` correctly treats any plain string
URL as "legacy, pass through unchanged" (it has no way to distinguish a
customer's own externally-hosted image from an un-imported master-theme
asset). Closed in customer-site, at the one point where it is safe to act
— `PageBuilderController::installTheme()`, strictly before the immutable
`ThemeInstanceRevision` is created — via new `ThemeMediaLocalizationService`,
which walks the exact same canonical media-prop inventory
`CustomerMediaResolver::MEDIA_PROPS`/`MEDIA_REPEATERS` already defines (no
second, drifting list) and rewrites any URL on a fixed,
operator-controlled `services.trusted_media_hosts` allowlist into a real
`CustomerMediaService`-owned `{media_id}` reference. Security posture: only
https, no userinfo, redirects never followed, bounded timeout/byte size,
real MIME+dimension validation on the downloaded bytes (never a declared
Content-Type alone) via the new `CustomerMediaService::importFromRemoteBytes()`
(the same class's stated "only write authority" invariant is preserved —
this is a second entry point into it, not a bypass of it), per-site
deduplication by source URL, and graceful per-image failure (one bad image
never blocks an install). `ThemeMediaLocalizationTest` (new, 4 tests)
covers: real localization + revision rewrite, untrusted hosts left
completely untouched (`Http::assertNothingSent()`), repeater dedup, and
graceful degradation on fetch failure.

**Decision:**
1. No customer-site deletion was performed — the audit is the outcome, and
   `NoParallelMasterThemeAuthoringTest` makes it a permanent regression
   guard instead of a one-time claim.
2. `universal-multipurpose-qa` is no longer test-only: seeded with
   `is_marketplace=true` and a real (already-seeded, non-fabricated)
   marketing thumbnail; reaching `Theme::isCommerciallyEligible()` still
   requires a real `ThemeBuilderService::publish()` call — the seeder makes
   it eligible to be published, it does not bypass the gate.
3. `RenderIntegrityCheck`'s slider-slide false positive is fixed at the
   check itself, not worked around in seed data.
4. `gallery`/`team_cards` avatar CSS and saas-platform preview parity are
   fixed once, canonically, in both hosts' own per-block CSS files
   (ADR-029's host-owned-copy model), not per-page.
5. Media localization is CLOSED for the install path (the one point every
   real acquisition mode — free grant, purchase, admin assign — already
   funnels through). Upgrade/reconciliation re-localization is intentionally
   out of scope for this ADR (existing localized media is never re-fetched
   thanks to source-URL dedup; a later release adding new hotlinked assets
   reaching a customer only via the upgrade path, not a fresh install, is a
   smaller, explicitly tracked follow-up, not a hole in this fix — every
   real acquisition mode proved in `UniversalThemeCommercialDeliveryTest`
   is a fresh install).

**Reason:** Evidence-first governance means an assumed defect that a real
trace disproves is reported as disproven, not silently implemented around;
the actual, real defect (an unpublished theme, a broken quality check, and
two hosts' CSS drifting apart) is what a root-cause pass is for, and fixing
it once at the correct owning layer (the check, the shared-pattern CSS, the
one install boundary) is what keeps a cross-repo, two-host architecture
from re-accumulating the same class of gap.

**Verification:** saas-platform `tests/Feature/ThemeBuilder` +
`tests/Feature/ThemeCanvas` + `tests/Unit/ThemeBuilder` full regression
(591 passed, 1 pre-existing environment self-skip, 0 failures, 1804
assertions) plus `ThemeCanvasInstallToSiteHttpTest` (3 tests, run
separately, all passing); customer-site full `tests/Feature/ThemeCanvas`
regression (247 tests, 1007 assertions, 1 pre-existing deprecation, 0
failures). `ThemeCommercialQualityGate::evaluate()` run directly against
the real seeded Universal Theme: FAILED → PASSED after the three defect
fixes above, with only non-blocking warnings remaining (unsupported-type
fallbacks for `product_grid`/`language_switcher`/etc. — pre-existing,
tracked, non-blocking per ADR-026).

**Impact:** `saas-platform` —
`packages/saas-platform/modules/theme-runtime/src/BlockRenderer.php`
(unchanged this phase), `app/Services/ThemeBuilder/QualityGate/Checks/RenderIntegrityCheck.php`
(slider-slide recursion removed), `database/seeders/UniversalMultipurposeQaThemeSeeder.php`
(`is_marketplace=true`, thumbnail, `builder-coverage` icon override),
`resources/views/admin/theme-builder/canvas-preview.blade.php` (CSS parity
restored), new `tests/Feature/ThemeBuilder/UniversalThemeCommercialDeliveryTest.php`.
`customer-site` — `resources/views/layouts/canvas-theme.blade.php`
(gallery/team-avatar CSS), `app/Services/ThemeCanvas/CustomerMediaResolver.php`
(MEDIA_PROPS/MEDIA_REPEATERS made public), `app/Services/CustomerMediaService.php`
(`importFromRemoteBytes()`, `findBySourceUrl()`), new
`app/Services/ThemeCanvas/ThemeMediaLocalizationService.php`,
`app/Http/Controllers/Front/PageBuilderController.php` (one call site
added before `record()`), `config/services.php` (`trusted_media_hosts`),
new `tests/Feature/ThemeCanvas/ThemeMediaLocalizationTest.php` and
`tests/Feature/ThemeCanvas/NoParallelMasterThemeAuthoringTest.php`. No
migration. No route deleted. No authorization rule changed.

---

## ADR-036

**Title:** Standardized Block SDK (`ThemeBlockSDK`) Over the Unchanged ThemeCanvasRuntime Protocol

**Status:** Accepted (Phase 9, Step 1)

**Context:**

A repository audit of the theme-canvas block system found the runtime bridge
(`packages/saas-platform/modules/theme-runtime/resources/views/canvas/runtime.blade.php`)
exposed three capabilities that **no code in either repository ever used**:

- `registerBlock(type, lifecycle)` / `initBlock(el)` — grep for `registerBlock`
  matched only its own definition and export.
- `trackScript(src)` — defined and exported, zero call sites.
- `ctx.props` — built by `initBlock` from `data-tb-block-props`, but that
  attribute is read by the runtime and **emitted by no renderer anywhere**,
  so `ctx.props` was permanently `{}` for every block on every page.

The one block script that does exist (the carousel, `BlockRenderer.php`)
ignores the runtime entirely: it self-guards with `dataset.tbCarouselInit`,
queries its own DOM, and duplicates listener/`setInterval` bookkeeping. There
was no base class, factory, or abstract block concept anywhere
(`BaseBlock`/`defineBlock`/`createBlock`/`block-sdk`: zero matches). A new
developer had to learn the internal message protocol, the element-addressing
attributes, and the lifecycle fan-out before writing one line of block code.

Phase 9 exists to turn "raw lifecycle implementation" into a developer-facing
ecosystem. Step 1 is the SDK that every later ecosystem piece rests on.

**Decision:**

1. **The SDK is a consumer layer, never a second bridge.**
   `ThemeCanvasRuntime.version` stays `1`. The SDK registers against the
   surfaces the runtime already exposes and re-implements none of it —
   not `replaceNode`, not `lifecycle()`, not the event bus. Deleting
   `block-sdk.js` reverts the ecosystem to raw runtime access with no other
   change. Any future need that cannot be expressed this way is a signal to
   change the runtime deliberately, not to grow a parallel one.

2. **One distributable file, one include seam, fail-fast.**
   `resources/js/block-sdk.js` follows the established `canvas-identity.js`
   pattern exactly: a plain distributable asset with a CommonJS export,
   resolved by `ThemeRuntimeServiceProvider::blockSdkContractPath()` and
   inlined verbatim by `resources/views/canvas/block-sdk.blade.php`. Node
   tests and Blade hosts therefore consume one source and cannot drift.
   `create()` throws when the runtime contract is missing or is not version
   1, matching the editor bootstrap's fail-fast gate policy.

3. **Bootstrap position 2.**
   ```
   1. runtime    ThemeCanvasRuntime  version 1
   2. block-sdk  ThemeBlockSDK       version 1   (NEW)
   3. identity   CanvasIdentity      version 1
   4. host structural services partial
   5. interaction runtime
   ```
   The SDK hard-depends on the runtime and nothing below it depends on the
   SDK, so it inserts without disturbing the existing runtime/identity pair.
   `canvas-preview.blade.php` (static preview, render-only) includes
   `runtime` + `block-sdk` without `identity`; that surface never authors.

4. **Both authoring forms.** `defineBlock({ ... })` for a declarative
   object and `defineBlock(class extends ThemeBlockSDK.BaseBlock)` for a
   block that owns real per-instance state. The class form is the one the
   reference block uses, because per-instance state without closure
   bookkeeping is the actual ergonomic win.

5. **One lifecycle vocabulary, two runtime idioms bridged.**
   The runtime has two registration shapes: `registerBlock(type, {init})`
   (per element) and `register(id, {mount, update, refresh, destroy})`
   (document phases). The SDK calls both and filters the document phases by
   block type and `data-tb-block-id`, so a developer writes only
   `init` / `onUpdate` / `onRefresh` / `onDestroy`. Phase-to-handler
   resolution is an explicit map, never string concatenation.

6. **The runtime's WeakMap cleanup is the teardown mechanism.** The runtime
   releases every `ctx.on()` subscription (`cleanupElement` /
   `elementSubscriptions`) *before* destroy handlers run. The SDK
   deliberately holds no second subscription registry, and `onDestroy` is
   documented as unable to rely on `ctx.on`. A block test asserts the
   teardown ordering directly.

7. **`data-tb-block-props` is emitted by PHP (the missing props channel).**
   `BlockRenderer::blockPropsAttr()` writes it on the SAME wrapper that
   already carries `data-tb-block-id`/`-type`, in both editor and public
   mode, so the two wrappers stay a pair. Two constraints, both tested: the
   nested `blocks` child tree is excluded (it is already rendered into
   `$inner`; re-serialising it would duplicate whole subtrees into a DOM
   attribute), and empty props emit nothing, so every block that has no props
   of its own renders byte-identically to before.

8. **`ctx.setProp` is local by contract.** It updates the instance prop map,
   re-publishes the frozen `ctx.props` view, re-serialises
   `data-tb-block-props` on the live element, and emits `state:update`. It
   never round-trips through the editor's `inlinePropChange` action and never
   persists to a theme. Documented on the surface so a developer does not
   infer persistence.

9. **Standard event schema.** `ThemeBlockSDK.EVENTS` is a frozen,
   published table. Pattern `/^[a-z0-9_]+(:[a-z0-9_]+)+$/`: block-local
   `block:<type>:<verb>`, cross-block `state:update|hydrate|reset`,
   SDK-reserved `block:ready|update|refresh|destroy|error`, and the
   runtime-owned `theme:content-updated` / `theme:canvas-lifecycle` /
   `theme:interaction` documented as not-to-be-redefined. `ctx.emit`
   validates the name, warns once per offending name, and always stamps
   `{ blockId, type }` onto the payload.

10. **Dependencies.** `deps.js` resolves through the runtime's own
    `trackScript()` (the first real consumer of it); `deps.css` injects one
    deduped `<link>` because the runtime has no stylesheet loader. A
    dependency that fails to load *resolves* rather than rejects — an
    optional asset must not take a block down — and `block:error` carries the
    failure. Editor-side asset distribution stays with the existing
    `ExtensionCanvasPaletteAdapter` / `ExtensionElementDefinition` pipeline;
    Step 1 does not change it.

11. **Error containment.** A throwing or rejecting `init` marks the element
    `data-tb-block-error="1"`, leaves it un-`tb-block-ready`, emits
    `block:error`, and never affects a sibling block. Every lifecycle
    handler is individually contained for the same reason.

12. **The reference block is documentation, not product.** `reference_counter`
    ships as a renderer + a packaged client asset, is self-guarding against
    double registration, and is **deliberately not in `BlockRegistry`**:
    registering it would change the registry cardinality that
    `UniversalMultipurposeQaThemeSeederTest` pins and would place a demo
    block in the editor palette. Dispatch is unaffected — `renderBlock()`
    resolves `render{Studly}()` by method existence. A test asserts the
    absence so the decision cannot be undone by accident.

**Alternatives rejected:**

- *Change the runtime instead of wrapping it.* Rejected: the runtime is
  version-pinned protocol shared with customer-site, and a protocol bump to
  absorb conveniences would put a third-party-breaking change ahead of any
  ecosystem benefit.
- *A second `data-tb-block-assets` attribute for dependencies.* Rejected: the
  declarative asset authority already exists server-side
  (`ExtensionElementDefinition.assets` ? `ExtensionCanvasPaletteAdapter`);
  a parallel attribute would create a second, competing declaration path.
- *Client-only props (write `data-tb-block-props` from JS).* Rejected: it
  would ship a permanently-empty props channel and misrepresent server-side
  block configuration that already exists.
- *`ctx.setProp` persisting through `inlinePropChange`.* Rejected for this
  step: it would require the SDK to own editor write-back, a separate
  authority from the canvas editor's own prop pipeline.

**Verification:** `tests/js/block-sdk.test.mjs` (27 tests) exercises the SDK
against the **real** `runtime.blade.php` source with only its two `@json()`
protocol arrays substituted — a stub would let the two drift, which is exactly
the failure this ADR exists to close. `tests/js/canvas-editor-bootstrap.test.mjs`
(5 tests) pins both packaged contract paths, the include order, and the
three-way fail-fast gate. `tests/Unit/ThemeBuilder/BlockSdkPropsChannelTest.php`
(11 tests) pins the props channel in both modes, the child-tree exclusion, the
byte-identical empty case, escaping, and the reference block. Full JS suite:
46/46. `tests/Unit/ThemeBuilder` + `tests/Unit/Architecture` +
`tests/Feature/ThemeBuilder`: 3 pre-existing failures only
(`TypographyScaleWiringTest` ×2 and `Phase3AContractsTest` ×1), each verified
identical with this phase's changes stashed — see KNOWN_ISSUES.

**Impact:** `packages/saas-platform/modules/theme-runtime` —
`resources/js/block-sdk.js` (new), `resources/js/blocks/reference-counter.js`
(new), `resources/views/canvas/block-sdk.blade.php` (new),
`resources/views/canvas/editor-bootstrap.blade.php` (include order + third
gate), `src/Providers/ThemeRuntimeServiceProvider.php`
(`blockSdkContractPath()`, `referenceBlockScriptPath()`),
`src/BlockRenderer.php` (`blockPropsAttr()`, `wrapEditorMode()` /
`wrapPublicStructural()` optional `$props`, `renderReferenceCounter()`).
`saas-platform` — `resources/views/admin/theme-builder/canvas-preview.blade.php`
(runtime + SDK includes). Tests: `tests/js/block-sdk.test.mjs` (new),
`tests/js/canvas-editor-bootstrap.test.mjs` (extended),
`tests/Unit/ThemeBuilder/BlockSdkPropsChannelTest.php` (new). No migration.
No route change. No protocol change. No customer-site change required to adopt
the SDK (it is additive), though customer-site should include it when it starts
authoring blocks through the SDK.

---

## ADR-056

**Title:** Per-Instance Lifecycle Serialization in the Block SDK (SDK-002); `onUpdate` Reachability Ruled On

**Status:** Accepted (Phase 9.2)

**Context:**

SDK-002 was filed as "the SDK releases the instance before the block's
cleanup can run": N block replacements through `replaceNode()` left N+1
carousel initialisations, one teardown, and one stranded `window` `resize`
listener per replacement. The reproduction in
`tests/js/block-sdk-lifecycle.test.mjs` (per-phase trace) pinned the
timeline precisely:

- the FIRST replacement destroys cleanly (listeners go to 0);
- every destroy from the SECOND replacement on is a no-op, because the
  instance's `state.handle` is already null.

The handle was not nulled by any release path. It was nulled by a **race the
SDK itself created between a block's async init and the update phase**. The
runtime's `replaceNode()` runs `lifecycle('destroy')` and
`lifecycle('update')` synchronously back-to-back, and the SDK's own update
hook calls `runtime.initBlock(root)` and then fans out `onUpdate`
immediately. A block whose `init()` returns a promise (the carousel resolves
`ctx.ready()`) finishes its body one microtask LATER — after `onUpdate` has
already bound the controller. The deferred init continuation then re-ran,
hit the element's `data-tb-carousel-init` guard, got `null` back from
`initCarousel()`, and **overwrote the live handle with `null`**. From then
on every `destroy` found nothing to stop: the window listener and the
autoplay timer leaked, each pinning its detached DOM subtree.

The SDK's fan-out had no ordering concept at all: phases were delivered in
arrival order, whatever an in-flight init might collide with.

**Decision:**

1. **The SDK serialises lifecycle phases per instance**
   (`gateHandler`/`gateInit` in `block-sdk.js`). `initBlock()` opens a
   per-instance gate when init returns a promise; the gate closes when the
   init continuation settles (success OR failure). While it is open:
   - an `update`/`refresh` phase is **dropped, not queued** — the pending
     continuation already represents the post-init state that phase was
     about to produce, and blocks' own guard attributes keep re-runs
     idempotent;
   - a `destroy` phase is **held** until the continuation settles, then
     delivered — a block must never be released with its init body
     unexecuted.

2. **`releaseInstance()` is ordered strictly AFTER `onDestroy` completes.**
   The destroy fan-out now routes through the gate and releases the
   instance inside the delivery callback. This is the structural invariant
   the lifecycle previously left to chance: the SDK cannot lose its
   reference to a block instance before the block's teardown has finished.
   No try/catch bandage, no block-side workaround — the guarantee lives in
   the orchestration layer.

3. **Blocks may keep writing plain `this.state.x = ...`.** The carousel
   gained only (a) an idempotence guard in its init continuation
   (`state.element === el && state.handle` → return), so a re-entered
   continuation can never overwrite a live handle with a guard-refused
   `null`, and (b) `onUpdate` still tears a superseded controller down
   before rebinding (destroy-before-null ordering). Neither change is
   required for correctness under the SDK gate; both are defence in depth
   that costs nothing.

4. **`onUpdate` reachability, ruled on:** on the real replace path
   (`replaceNode()` → destroy → replace → update), the update phase fans
   out to the NEW element's fresh instance, whose init has just been kicked
   off — so `onUpdate` IS reached on replacements, delivered (or dropped)
   through the gate. The old claim that "nothing is left to call" described
   only the instance of the OLD element, correctly released during destroy.
   Blocks that need replacement-driven re-init still work either way: init
   runs for the new element, and `onUpdate` additionally fires for any
   plain `update` phase that is not tied to a replacement. The hook stays
   documented and callable; its old "unreachable on the replace path"
   caveat in the carousel comment was stale and has been corrected.

**Alternatives rejected:**

- *Make the runtime deliver update asynchronously (queue a microtask).*
  Rejected: the runtime is the version-pinned protocol layer (ADR-036);
  changing its delivery semantics to fix an SDK bug inverts the authority
  relationship and would make every consumer's phase ordering
  timing-dependent.
- *Drop `onUpdate` from the SDK contract since "it is unreachable".*
  Rejected on the evidence above — it is reachable; the unreachability
  claim was an artefact of testing update phases against already-released
  instances.
- *Have the carousel null-check and re-create its controller in
  `onDestroy`.* Rejected: the handle is already null by the time destroy
  runs — that patch would have papered over the race while leaving the SDK
  free to interleave any future phase with any in-flight init.
- *Queue out-of-order update/refresh phases behind the gate.* Rejected:
  queuing converts one stale delivery into two (continuation + queued
  phase) and forces blocks to be re-entrant; dropping matches what the
  continuation already did and keeps the handler count one.

**Verification:** `tests/js/block-sdk-lifecycle.test.mjs` — the SDK-002
`test.todo` is now a real assertion: 10 replacements through the real
runtime + real SDK + real carousel leave exactly one `window` `resize`
listener and one live autoplay timer, with every destroy dropping the count
to zero. `tests/js/block-sdk.test.mjs` adds the gate contract test: an
update during a pending init is dropped, a destroy during a pending init is
held, `onDestroy` runs strictly after the init continuation, and only then
is the instance released (`instanceCount` → 0). The pre-existing
`replaceNode` ordering test (destroy → init → update for sync blocks) and
the full JS suite (61/61) stay green.

**Impact:** `packages/saas-platform/modules/theme-runtime` —
`resources/js/block-sdk.js` (per-instance phase gate: `gateHandler`,
`gateInit`, gated destroy fan-out), `resources/js/blocks/carousel.js`
(init idempotence guard, corrected lifecycle comments).
`tests/js/block-sdk.test.mjs` (new gate contract test),
`tests/js/block-sdk-lifecycle.test.mjs` (SDK-002 todo promoted to a real
regression test). No runtime change. No protocol change. No renderer change.
No blade view change (the SDK ships as one distributable source consumed by
the view).

---

## ADR-058

**Title:** Adapter-Based Block Lifecycle: Carousel, Countdown, and the Per-Instance Phase Gate (Phase 9.2 and Phase 9.3)

**Status:** Accepted (Implemented)

**Date:** 2026-09-30

**Context:**

`BlockRenderer` render methods used to inline a byte-identical client script
per rendered block. Two defects followed from that shape, both observed in
production code rather than theorised:

1. **Unreachable teardown.** Each inline copy registered its own anonymous
   `window` listener and autoplay `setInterval` and returned no handle. On the
   editor's `replaceNode()` path every replacement stranded one listener and
   one timer against a detached DOM subtree. This is the SDK-002 leak class.
   The countdown's inline copy had the same defect in a smaller form: it
   assigned its interval to a variable it only ever *read* inside `tick()` to
   clear it on expiry, so nothing could cancel it on teardown.
2. **Per-instance duplication.** A page with N carousels shipped N copies of
   the same controller, and a bug fix in one copy did not reach the others.

Two failure modes were structural, not incidental. The runtime's
`replaceNode()` drives `destroy` and `update` synchronously back-to-back, so a
block whose `init()` returns a promise (the carousel awaits `ctx.ready()`)
finished its body one microtask *after* `onUpdate` had already bound the
controller, and the deferred continuation then overwrote the live handle with
`null`. And the SDK's fan-out had no ordering concept at all: phases were
delivered in arrival order, whatever an in-flight init might collide with.

**Decision:**

Blocks are migrated to an adapter shape: the client logic lives ONCE in a
packaged asset under `resources/js/blocks/`, and the renderer only emits
markup plus configuration.

1. **One asset, inlined once per document.** A block's script is no longer
   per-instance. `renderCarousel()` and `renderCountdown()` delegate to
   `carouselScript()` and `countdownAdapterScript()`, which emit the packaged
   asset on the first block of that type in a document and nothing thereafter.
   A page with N carousels now pays the controller cost once. A **missing
   asset throws** rather than falling back: a page that silently loses its
   only interactivity is the exact failure this refactor exists to surface.

2. **Configuration stays where the markup is.** The countdown reads
   `data-end-date` from its own root; the carousel reads its `data-*`
   contract. Neither moves config into the `ctx.props` channel, which is built
   from `data-tb-block-props` and can only *disagree* with the markup that
   actually shipped. Render snapshots are pinned in both the neutral and
   `MODE_PUBLIC` renders to keep this true.

3. **Two carriers, one controller, per block.** Each migrated asset behaves
   identically whether or not the SDK is present: on canvas surfaces it
   registers a real `BaseBlock` subclass; on public customer pages, where no
   layout loads the canvas runtime and which are therefore the only place a
   customer ever sees this, it self-initialises by scanning the document.
   Both run the same controller behind the same init guard
   (`data-tb-carousel-init`, `data-tb-countdown-init`), so a page mixing
   carriers cannot double-bind. **This is deliberate, not a fallback:**
   making the SDK the sole carrier would have shipped dead carousels on every
   live tenant, and the failure would have been invisible in the admin
   preview, which is the one surface where the SDK *is* loaded.

4. **An element guard, validated before it is set.** The countdown's
   pre-migration inline copy set its init guard and *then* returned on an
   unparseable date, permanently bricking the block even if the value were
   later corrected. Every adapter now validates before guarding: a bad date
   means "not a live countdown", and a corrected attribute can still
   initialise.

5. **Every adapter implements a real `destroy()`.** This is the whole point.
   The carousel adapter (`legacy_carousel.js`) exists for exactly the four
   types that used to ship the inline copy (`slider`, `category_slider`,
   `testimonials_slider`, `client_logos`) and gives them the `destroy()`
   their inline script never had. The countdown's `destroy()` clears its
   interval.

6. **The SDK serialises lifecycle phases per instance** (`gateHandler` /
   `gateInit` in `block-sdk.js`), decided and justified in ADR-056, which this
   ADR depends on and does not restate. While an init is pending: an
   `update`/`refresh` is **dropped** (the pending continuation already is the
   post-init state); a `destroy` is **held** until the continuation settles.
   `releaseInstance()` is ordered strictly AFTER `onDestroy` completes. Blocks
   keep writing plain `this.state.x = ...`; the guarantee lives in the
   orchestration layer, not in a block-side workaround.

**Alternatives rejected:**

- *One adapter file per block type.* Rejected: the four legacy carousel types
  and `product_slider` run byte-identical controller logic. The controller
  stays in `carousel.js` and is exposed as `ThemeCarousel.initCarousel`; the
  adapters add only BaseBlock plumbing, the type list, and the `destroy()`.
- *Queue out-of-order phases behind the gate instead of dropping them.*
  Rejected (ADR-056): queueing converts one stale delivery into two and forces
  blocks to be re-entrant.
- *Make the runtime deliver phases asynchronously.* Rejected: the runtime is
  the version-pinned protocol layer (ADR-036); changing its delivery semantics
  to fix an SDK bug inverts the authority relationship and makes every
  consumer's phase ordering timing-dependent.
- *Move block config into `data-tb-block-props` / `ctx.props`.* Rejected: see
  decision 2.

**Consequences:**

- **Pros:** predictable lifecycle; teardown is reachable, so timers and
  listeners cannot outlive their block; the controller ships once per
  document rather than once per instance; a bug fix in the shared asset
  reaches every block type at once; a missing asset fails loudly instead of
  shipping a dead block.
- **Cons:** a new block must follow the adapter contract (config in markup,
  handle in `this.state`, a real `destroy()`) or it reintroduces the leak
  class. Init may be async, and a block that assigns state in a deferred
  continuation depends on the SDK gate for ordering. The public no-SDK carrier
  must be kept working for every asset, which is a second code path to
  maintain per block.
- **Not changed:** `ThemeCanvasRuntime.version` stays `1`. The SDK remains a
  consumer layer, never a second bridge (ADR-036). The editor's palette,
  include order, and block registry cardinality are untouched: a migrated
  adapter must not add itself to `BlockRegistry`.

**Verification:** `tests/Unit/ThemeBuilder/ProductSliderRenderSnapshotTest.php`
(8 tests: structural hashes re-pinned for the two-`<script>` emission, all
four legacy types asserted to ship controller plus adapter, inline controller
asserted gone); `tests/Unit/ThemeBuilder/CountdownRenderTest.php`;
`tests/js/countdown-lifecycle.test.mjs` (9 tests);
`tests/js/block-sdk-lifecycle.test.mjs` (SDK-002 regression over 10
replacements, plus four legacy-adapter tests); `tests/js/block-sdk.test.mjs`
(gate contract). JS suite 74/74.

**Impact:** `packages/saas-platform/modules/theme-runtime`:
`src/BlockRenderer.php` (`renderCarousel()`, `carouselScript()`,
`legacyCarouselScript()` deleted, `renderCountdown()`,
`countdownAdapterScript()`), `src/Providers/ThemeRuntimeServiceProvider.php`
(`carouselBlockScriptPath()`, `legacyCarouselScriptPath()`,
`countdownAdapterScriptPath()`), `resources/js/block-sdk.js`,
`resources/js/blocks/carousel.js`, `resources/js/blocks/legacy_carousel.js`
(new), `resources/js/blocks/countdown_adapter.js` (new). Tests as listed
above. No migration. No route change. No protocol change. No `customer-site`
change required: the public no-SDK carrier keeps customer pages working.

---

## ADR-059

**Title:** Fast-path Canvas Interaction

**Status:** Accepted (Reference Counter Pilot Implemented)

**Date:** 2026-10-01

**Context:**

The Canvas Inspector historically handled every property edit by debouncing
for 350 ms, POSTing the complete block to `canvas/render-block`, receiving
replacement HTML, and replacing the addressed iframe node. That server render
remains necessary when a property changes markup structure, server-resolved
data, assets, or controller configuration. It imposes avoidable network,
rendering, and DOM-replacement latency for simple property-only changes such
as text, color, and numeric values when a block can repaint its existing DOM
safely.

ADR-036 already established `ctx.setProp` as a local client-state operation,
but the SDK's instance registry was closure-private and the parent editor had
no controlled way to address an instance. Exposing the raw registry would
break SDK encapsulation. Making every block use local prop updates would be
equally unsafe: blocks such as `countdown` intentionally read configuration
from rendered `data-*` attributes under ADR-058 and require replacement HTML.

**Decision:**

Canvas property updates use a two-tier system:

1. **Tier 1 — Slow-path (default).** The editor keeps the existing debounced
   `canvas/render-block` POST, receives canonical server-rendered HTML, and
   sends `updateBlock` to the preview. Every block remains on this path unless
   it is explicitly approved for fast-path behavior.
2. **Tier 2 — Fast-path (explicit opt-in).** For a type listed in the
   editor's `FAST_PATH_BLOCK_TYPES`, `updateProp()` sends a versioned
   `setProp` message directly to the iframe. No `render-block` request or DOM
   replacement occurs. If the iframe handshake is not complete, edits are
   coalesced by block ID and prop key and flushed after `ready`.

The fast-path command crosses the existing boundaries as follows:

- **SDK command:** the browser SDK global is `window.ThemeBlockSDK`.
  `ThemeBlockSDK.setProp(blockId, key, value)` finds the live private instance
  whose `instance.blockId` came from the wrapper's `data-tb-block-id`, then
  delegates to that instance's existing `ctx.setProp`. The `instances` map,
  instance object, and context remain private. A missing instance produces a
  controlled warning and does not break the event loop.
- **Protocol:** `setProp` is an additive, version-1 action in
  `PreviewProtocol::TO_PREVIEW`. PHP and browser validators require non-empty
  string `blockId` and `propKey` values. The editor sends it through
  `ThemeCanvasRuntime.post`; the iframe retains the existing same-origin,
  parent-source, direction, and protocol-version checks before dispatch.
- **Iframe dispatch:** `canvas-live-preview.blade.php` calls
  `window.ThemeBlockSDK.setProp(blockId, propKey, value)` inside a contained
  `try`/`catch`. Other message handlers are unchanged.
- **Block subscription:** an opted-in block subscribes through
  `ctx.on(ThemeBlockSDK.EVENTS.STATE_UPDATE, handler)`, filters the stamped
  `{blockId, type}` identity, synchronizes its client state, and calls its own
  `paint()` method. Runtime WeakMap cleanup continues to own subscription
  teardown.

`ctx.setProp` remains local by contract: it updates the instance prop view,
re-serializes `data-tb-block-props`, and emits `state:update`; it does not
persist a theme. The editor mutates its canonical in-memory document before
choosing either tier, and the existing save/publish paths remain the only
persistence authority.

**Pilot and opt-in constraint:**

`reference_counter` is the sole initial member of `FAST_PATH_BLOCK_TYPES`.
Its packaged adapter promotes `paint()` to an instance method and repaints on
its own `state:update` event. `countdown` is deliberately not opted in: under
ADR-058 its controller reads the rendered `data-end-date` contract, so it
continues through the slow-path. Future blocks may be added only after proving
that every opted-in property can update the existing DOM without server
rendering, structural replacement, asset re-resolution, or lifecycle
reinitialization.

**Consequences:**

- Simple opted-in edits repaint immediately without network latency or losing
  focus through node replacement.
- The canonical server renderer remains the safe default and fallback; this
  ADR does not generalize client rendering across the block catalog.
- Each new opt-in carries a block-owned subscriber and behavioral tests. An
  allowlist entry without a matching subscriber is incomplete and must not
  ship.
- `ThemeCanvasRuntime.version` remains `1`; the new command is an additive
  allowlisted action and does not restructure lifecycle delivery from
  ADR-036/ADR-056.

**Verification:** `tests/js/block-sdk.test.mjs` proves controlled lookup,
private registry encapsulation, `state:update`, protocol validation, and the
`reference_counter` repaint/re-clamp behavior. The focused Chromium test
`tests/browser/reference-counter-fast-path.spec.js` executes the production
Inspector `updateProp()` and `pushFastPropUpdate()` methods, crosses the real
iframe message handler and SDK, observes the counter repaint, and records zero
`/canvas/render-block` requests. `BlockSdkPropsChannelTest` pins the sole
pilot allowlist and subscriber; countdown (9/9) and SDK lifecycle (12/12)
regressions remain green.

**Impact:** shared theme runtime — `resources/js/block-sdk.js`,
`resources/views/canvas/runtime.blade.php`,
`resources/js/blocks/reference-counter.js`, and
`src/Canvas/PreviewProtocol.php`; platform Canvas —
`resources/views/admin/theme-builder/canvas-live-preview.blade.php` and
`resources/views/admin/theme-builder/canvas.blade.php`. No migration, route,
dependency, persistence, `BaseBlock`, `defineBlock`, or countdown behavior
change.

---

## ADR-060

**Title:** Commercial Iranian UI/UX Standard (Constitution §16) and Schema-Driven Repeater Contract

**Status:** Accepted

**Date:** 2026-10-25

**Context:**

The owner set a permanent quality bar for every user-facing interface:
premium, RTL-first, Persian-typographic, mobile-first, conversion-focused
commercial experiences. Generic or fragile output is not acceptable. The
2026-10-25 widget-engine audit (`docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md`)
found concrete violations of that bar:

- The platform Canvas repeater was hard-wired to Bento Grid (`propKey === 'items'`),
  so every other `items` prop was edited, and corrupted, through Bento card fields.
- Repeater child fields were declared under `item_schema`, which
  `InspectorFieldRegistry` does not read, so they normalized to nothing.
- Select options were declared as `{label,value}` objects, which rendered as
  `[object Object]`.
- Author URLs were rendered through `escapeAttr()` instead of the
  scheme-validating `escapeUrl()`.

Constitution §1 requires an explicit decision for any amendment.

**Decision:**

1. `PROJECT_CONSTITUTION.md` gains §16, *Commercial Iranian UI/UX Standard*, with
   enforceable minimum requirements (RTL/typography, tokens, mobile and motion,
   schema-generated authoring controls, honest interactions, safe output).
2. The canonical repeater contract is the one `InspectorFieldRegistry` already
   normalizes: `type: 'repeater'`, child controls under `fields`, persisted shape
   in `storage` (`array` by default, `json` for legacy JSON-string props), and an
   optional `item_label`. A child field may declare `show_if: '<sibling key>'`.
   `item_schema` is not part of the contract.
3. Select options are a `value => label` map, which is the shape
   `normalizeSelect()` allowlists. A select may request a dedicated editor control
   through `ui` (first use: `ui: 'column_picker'` on `columns.ratios`). A field
   renders exactly one control.
4. The platform Canvas renders every repeater from its `fields` alone, with add,
   duplicate, reorder, remove, collapse, undo/redo, and live preview through
   `updateProp()`. Schema-less `type: 'array'` props get a guarded JSON editor that
   never writes an invalid draft.

**Consequences:**

- `bento_grid`, `faq`, `testimonials_slider`, and `list` now declare canonical
  repeater fields. Legacy JSON-string values still load and render. The next edit
  persists the array shape.
- customer-site's schema-driven Canvas already reads `fields`/`storage`, so the two
  hosts now agree on these types.
- Promoting customer-site's remaining local repeater schemas into the canonical
  registry is tracked as roadmap Phase 0.2.
- `Phase01RepeaterContractTest` fails any canonical repeater without `fields` or
  any select with object-list options.

**Impact:** shared theme runtime (`src/BlockRegistry.php`, `src/BlockRenderer.php`),
platform Canvas (`resources/views/admin/theme-builder/canvas.blade.php`),
`PROJECT_CONSTITUTION.md`. No migration, route, dependency, or persistence-format
break.

## ADR-061

**Title:** Nested Repeater Editing, `link`-Typed URL Fields and Strict Booleans

**Status:** Accepted

**Date:** 2026-10-26

**Context:**

Phase 0.2 (ADR-060 follow-up) put customer-site's list definitions into the
canonical registry, including lists nested inside list items: pricing
`plans[].features`, footer `columns[].links`, and the two-level mega menu
`nav_links[].mega_columns[].items`. The platform Canvas showed "unsupported" for
every nested field. Those fields were promoted verbatim, so two weak types came
with them:

- `pricing.plans[].highlight` was free text, and `(bool) 'false'` is true;
- navigational URL fields were `string`, so editors showed plain text inputs.

Six of those fields (`timeline` CTA, `about_section` button, header logo/CTA,
`services_cards`, `promo_banner`) were rendered through `escapeAttr()`, which
let a `javascript:` URL into an `href`. customer-site's own social-icon and
service/project-card overrides had the same gap.

**Decision:**

1. A repeater list is addressed by a path below its root prop:
   `[propKey, index, fieldKey, …]` (`CanvasListPath`, DOM-free). Every write
   replaces the root prop through one `updateProp()`, copying only the
   ancestors on the path. Structural edits (add, duplicate, move, remove) are
   undo steps of their own; field edits coalesce per item field.
2. The platform Canvas renders every list depth from one recursive template
   (`partials/canvas-repeater.blade.php`), up to the registry depth cap
   (`InspectorFieldRegistry` `maxDepth` ≤ 3: depths 0, 1, 2). A list whose
   items are a single text field edits as compact inline rows.
3. Legacy scalar list items (`["a", "b"]`) are presented and written back as
   `{firstField: value}`, the shape renderers already accept. Copying a string
   item never spreads it into characters.
4. Every navigational URL field is `type: 'link'` on both hosts. A link value is
   a URL string or a structured `{url, …}` object. Renderers read it through
   `BlockRenderer::linkValue()` / `linkHref()`, which always validates with
   `escapeUrl()`. Media sources (`video.url`, `header_modern_v1.logoUrl`) and
   data endpoints (`dynamic_*.endpoint_url`) are not links.
5. `pricing.plans[].highlight` is `type: 'boolean'`, default `false`. Boolean
   props are read with `BlockRenderer::flag()`, so legacy `"false"`, `"0"` and
   `""` are false.
6. customer-site resolves an `{type: internal_page}` reference in any top-level
   prop, not only the original allowlist, because its editor offers the page
   picker on every top-level `link` field. Its schema validator gains a `link`
   type that accepts a string, a `{url}` object, or a page reference.

**Consequences:**

- Pricing features, footer column links, the mega menu, FoodMart footer links,
  and SleepShiman filter options and footer links are editable in the platform
  Canvas. Every nested list declares an `item_label`.
- Edited legacy scalar features persist as `{text}` objects. Unedited content is
  untouched.
- `Phase02bNestedListContractTest` fails:
  - a list nested deeper than depth 2;
  - a nested list without `fields` or `item_label`;
  - any URL-named text field outside the non-navigational allowlist;
  - a link field that drops a structured link or lets an unsafe scheme through;
  - any non-strict highlight read.
- customer-site's `CanonicalListSchemaParityTest` pins the same contract from the
  host side.

**Impact:**

- **Shared theme runtime:** `BlockRegistry`, `BlockRenderer`, the FoodMart and
  SleepShiman types.
- **Platform Canvas:** `canvas.blade.php`, the new `canvas-repeater` partial, and
  `canvas-structural-services` (`CanvasListPath`).
- **customer-site:**
  - `BlockRegistry`;
  - the Canvas editor, `InternalLinkResolver`, `ThemeCanvasRenderer` and
    `ThemeBuilder\BlockRenderer`;
  - `ElementSchemaRegistry` and `config/builder-element-schemas.php`.

There is no migration, route, dependency, or persistence-format break: strings
stay strings, and `highlight` text values still read correctly.

## ADR-062

**Title:** One Block Definition Authority: customer-site Overrides Retired

**Status:** Accepted

**Date:** 2026-10-26

**Context:**

customer-site's `ThemeCanvas\BlockRegistry` kept local copies of 65 canonical
block types and merged them over the canonical registry, with the host copy
winning. Phase 0.2 aligned their list fields. An audit of the rest found that
all 65 still differed:

- 47 canonical fields had no label, so an editor showed the raw prop key.
- `section.padding` and `grid`/`columns.gap` were typed `spacing`. The
  Inspector contract reserves that type for a structured four-side value, so
  neither editor rendered any control. Container `blocks` fields showed a label
  with no control.
- Host option lists offered values no renderer implements: `button` `outline`,
  `promo_banner` `success`/`warning`/`danger`, `nav_menu` `sidebar`.
  Canonical had the same problem with `module:reviews_grid` `carousel`.
- Some fields were read by no renderer on either host: host `gallery.columns`,
  `product_grid.title` and `accordion.title`; canonical
  `module:courses_grid.show_instructor` and `module:pricing_table`
  `highlight_plan`/`show_annual_toggle`.
- Host-only fields that customer-site's renderers do read were missing
  canonically: `video` poster/autoplay/loop/controls, `blog_posts`
  layout/source/category/manual ids/autoplay/interval, and `social_icons.style`.
- `renderCta()` and `renderNavMenu()` put `background` and `location` into a
  class attribute unescaped. A CSS colour, which customer-site's colour control
  stores, became a class with no rule, so the chosen colour never showed.

**Decision:**

1. The canonical registry is the only block-definition authority. customer-site
   assembles its registry as canonical plus `HOST_PATCHES`, a list of
   field-level replacements. Each patch states why the host cannot use the
   canonical field. There is one patch today: `module:shop_product_grid.source`
   without `bestselling`, because the host renderer has no sales-backed query.
2. Harmonization rules:
   - **Labels:** a canonical label wins; a host label fills a missing one.
   - **Controls:** keep the richer authoring control (media picker over text,
     select over free text, textarea over a single line) when it keeps every
     stored value valid.
   - **Option lists:** a select offers only what the renderer honours, checked
     against the CSS contracts and a survey of stored theme data.
   - **Dead fields:** a field no renderer reads on either host is removed, not
     promoted.
3. A spacing token is a `select` over `BlockRegistry::SPACING_OPTIONS`, the
   scale `spacingClass()` accepts.
4. `video.url` is an `image` media field with `media_kind: 'video'`. The media
   library stores `{media_id}` for it, and editors do not preview it as an
   `<img>`.
5. `renderCta()` keeps a class for theme tokens. It applies a valid CSS colour
   inline, with a readable text colour, and falls back to `primary` for
   anything else. `renderNavMenu()` allowlists `header`/`footer`.
   `renderVideo()` validates `src`/`poster` with `escapeUrl()` and reads its
   switches with `flag()`.
6. The social-icon style variants live in the shared
   `renderStyledSocialIcons()`, with the host markup unchanged.
   customer-site's override only adds its site-wide `SocialLink` fallback.
   Its `renderVideo()` copy is deleted.
7. Both editors give every field type a control. `blocks` fields are hidden
   from the content tab. `array` fields get the guarded JSON editor, and
   `text`/`rich_text` get a textarea.

**Consequences:**

- customer-site's registry shrinks from 1,413 to 213 lines. A canonical change
  now reaches customer-site's editor and validator directly.
- customer-site's `CanonicalBlockDefinitionParityTest` fails when:
  - an offered type differs from canonical outside a declared patch;
  - a patch is stale or matches canonical;
  - a field has no editor control;
  - a type's defaults fail the live render validator.
- `Phase02cCanonicalHarmonizationTest` pins the canonical side: every field has
  a control, the shadowed types are fully labelled, option lists match
  renderers, and the renderer fixes hold.
- Effective validation contract on customer-site, before and after: unchanged
  except `section`/`grid`/`columns.blocks` (now `mixed`) and
  `stats_row.background_image` (now `media`). Neither rejects any stored value
  found in the survey.
- Rendering changes for stored content:
  - CTAs whose `background` is a CSS colour now show that colour (4 blocks in
    customer-site fixture data);
  - unknown CTA tokens and `nav_menu` locations fall back to the default class;
  - `javascript:`/`data:` video URLs render the empty state.
  Everything else renders byte-identically.

**Impact:**

- **Shared theme runtime:** `BlockRegistry`, `BlockRenderer`.
- **Platform Canvas:** `canvas.blade.php`.
- **customer-site:** `ThemeCanvas\BlockRegistry`, `ThemeCanvasRenderer`, the
  Canvas editor.

There is no migration, route, dependency, or stored-data change. Values of
removed fields stay in documents and are ignored, as they already were.

## ADR-063

**Title:** One Shared Block Stylesheet; Local Vazirmatn; Package Persian Number Formatter

**Status:** Accepted

**Date:** 2026-10-27

**Context:**

Each host carried its own copy of every block's CSS:

- the platform preview's `admin/theme-builder/partials/canvas-css-contract`
  (348 lines);
- customer-site's live `partials/theme-canvas-style-contract` (514 lines).

They had drifted: 37 shared selectors had different values, 23 rules existed
only in the preview, and customer-site's bento grid declared `auto-rows`, a
property that does not exist. The preview therefore showed many blocks
(hero, CTA, stats, buttons, cards) differently from the live page.

The platform Canvas preview, its live iframe and the editor layout loaded
Vazirmatn from Google Fonts, which is unreliable from Iran. Five customer-site
views did too; on three of them the `@import` came after other rules, so
browsers ignored it and the page fell back to Tahoma. RTL hero titles used
negative `letter-spacing`, which crowds Persian letters into each other.

The package had no digit or currency formatter it could use:
`App\Services\PersianFormatter` is host code.

**Decision:**

1. `theme-runtime/resources/css/theme-runtime-blocks.css` is the only source
   of block CSS. Both host partials include it via `theme-runtime::css.blocks`,
   right after their own chrome, and keep only rules that their own markup or
   script needs:
   - the platform keeps `body`;
   - customer-site keeps `body`, `canvas-site-*`, the skip link,
     `*:focus-visible`, `.visually-hidden`, the notifications bar, and the
     skill-bar `width:0` pair that its IntersectionObserver script animates.
2. The sheet was consolidated from customer-site's rules, since those are what
   live pages ship, verbatim and in order. Preview-only rules were added only
   where they style markup the live partial left unstyled. Six preview-only
   rules (`.tb-hero h1|p|.tb-btn`, `.tb-cta h2|p|a`) out-ranked live rules by
   specificity and were dropped, so the preview takes the live values.
3. The platform preview's `body` uses the live rule (`--tb-*` tokens, with
   fallbacks). The hard-coded `direction: rtl` is gone, so `<html dir>`
   governs and LTR themes preview as LTR.
4. Fixes:
   - `.tb-bento-grid` gets `grid-auto-rows`;
   - `.tb-hero-split-title` and `.tb-hero-gradient-title` lose their negative
     tracking, which is kept under `[dir="ltr"]` only.
5. The five local Vazirmatn faces (400–800) live in
   `resources/css/vazirmatn-font-faces.css` and are included via
   `theme-runtime::css.fonts`. customer-site's
   `partials/vazirmatn-font-faces-inline` includes it unchanged. No platform
   preview, editor layout, or customer-site view references a font CDN.
6. `SaaSPlatform\ThemeRuntime\Support\PersianNumber` is a dependency-free
   formatter:
   - `digits()`, `latinDigits()`;
   - `format()` with ٬ and ٫ separators;
   - `rialToToman()`, `toman()`;
   - `compact()` (`۱٫۲ میلیون`, rolling up a unit at a thousand);
   - a directional minus that stays left of the number in RTL text.

**Consequences:**

- **Computed-style parity harness** (real Chromium; every element and
  pseudo-element, full computed style; 1366/768/390):
  - **customer-site live render:** 229 documents (every block type plus all
    29 canonical theme snapshots), 16,381 elements per width.
  - **Pure refactor:** the only diffs are the four previously unstyled blocks
    (project cards, about section, hero trust, contact-info grid) gaining the
    styling the preview already showed. All are in `acool-hvac-service`; the
    other 28 themes are identical.
  - **Fixes:** add only the hero-title tracking, the resulting width of the
    hero-split text column, and `grid-auto-rows`. No bento item changes size.
- **Preview vs live, same markup** (230 platform-rendered documents, 19,017
  elements per width; excluding the `--platform-primary` variable):
  - before: 38,540 distinct computed-style differences on 576 element kinds;
  - after: 48, all on `div.tb-skill-bar-fill`, from the host-only skill-bar
    rule.
- **Phase03SharedStylesheetContractTest** fails when:
  - either host partial regains block rules;
  - the dropped rules return;
  - `auto-rows` returns;
  - RTL hero tracking turns negative again;
  - any covered view references a font CDN.
- `PersianNumberTest` pins the formatter.
- `FoodMartPhase2bStyleContract` gains a third marker for the shared sheet.

**Impact:**

- **Shared theme runtime:**
  - new `resources/css/theme-runtime-blocks.css` and
    `vazirmatn-font-faces.css`;
  - `css/blocks` and `css/fonts` views;
  - two `ThemeRuntimeServiceProvider` paths;
  - `Support\PersianNumber`.
- **Platform:**
  - the Canvas preview partial;
  - `canvas-preview`, `canvas-live-preview` and `layouts/canvas`.
- **customer-site:**
  - the style partial and the font partial;
  - `layouts/canvas-editor`, `builder/edit`, `front/lifecycle/pay`,
    `restore-pay` and `front/suspended`.

There is no migration, route, dependency or data change. The formatter is
not yet wired into any renderer; prices keep their current output.

## ADR-064

**Title:** Theme Tokens Over Hex; One Colour Grammar; Persian Numbers in Shared Renderers

**Status:** Accepted

**Date:** 2026-10-27

**Context:**

The shared stylesheets and renderers still hard-coded colours that should
follow the theme:

- borders (`#e5e7eb` ×27), surfaces and muted text;
- indigo tints such as `rgba(99,102,241,…)`;
- white text on brand fills;
- brand-colour prop fallbacks and registry defaults (`#4f46e5`, `#7c3aed`).

A theme with its own palette therefore kept indigo accents.

Colour props reached `style` attributes through bare `escapeAttr()`, which
does not block a `;` break-out. `renderSection()` turned any non-hex value,
including `var(--tb-…)` and `rgba()`, into a class name. `renderHero()`
interpolated `background` and `align` raw into its class attribute.

The Canvas colour control could not express theme swatches with alpha,
`rgba()` or `transparent`. The two editors kept separate palette logic.

Shared renderers printed prices with `number_format()` (Latin digits) and
counters as typed. Four Persian headings besides the hero titles had
negative letter-spacing.

**Decision:**

1. **Tokens with the old literal as fallback.** Every themable colour in
   `theme-runtime-blocks.css`, `theme-runtime-contract.css` and the
   renderers reads `var(--tb-<token>, <previous literal>)`:
   - borders → `--tb-border`;
   - surfaces → `--tb-surface` or `--tb-background`;
   - muted text → `--tb-text-muted`;
   - text on a brand fill → `--tb-on-primary` / `--tb-on-error`;
   - urgent → `--tb-error`;
   - brand tints → `color-mix(in srgb, var(--tb-primary, #6366f1) N%, transparent)`.
   Gold review stars, the named `.tb-bg-light/dark/gray` classes, the
   installment badge greens, shadows, overlays on media, editor-only
   placeholders and the dark `nav_footer` default stay literal.
2. **Registry defaults.** Brand defaults are tokens with fallbacks:
   - backgrounds and gradients → `var(--tb-primary, #4f46e5)` and
     `var(--tb-secondary, #7c3aed)`;
   - text on primary → `var(--tb-on-primary, #ffffff)`.
   `header_modern_v1.ctaColor` stays hex: its renderer computes contrast from
   it. Stored documents are not rewritten.
3. **One colour grammar,** `HtmlSanitizer::isCssColor()`. It accepts:
   - hex, `rgb()`/`hsl()`-family functions and named colours;
   - `var(--token[, colour])`;
   - `color-mix(in srgb, <colour> N%, transparent)`;
   - `transparent`.
   `BlockRenderer::cssColor()` (every colour prop, both hosts' renderers) and
   `InspectorFieldRegistry::normalizeColor()` both use it.
   `themeBackground()` gives `renderHero()` the 0.2c CTA contract.
   `renderSection()` applies a `var()`/`rgba()` value inline.
4. **One colour control model,** `theme-runtime/resources/js/canvas-color.js`
   (`window.CanvasColor`, included via `theme-runtime::canvas.color`), shared
   by both editors:
   - eight theme swatches stored as `var(--tb-…, fallback)`;
   - an opacity slider: a swatch becomes `color-mix()`, a fixed colour
     `rgba()`;
   - «بی‌رنگ» stores `transparent`;
   - the preview resolves swatches against the theme's own global styles.
   Nested list colours get the swatches and transparent.
5. **Persian numbers:**
   - `BlockRenderer::displayToman()` prints demo prices as «۱٬۲۵۰٬۰۰۰ تومان».
   - `displayNumber()` → `PersianNumber::localize()` prints counters, stat
     values and suffixes, badges, cart and category counts, and plan prices.
     It converts digits, `,`→٬ and `.`→٫ between digits, and `%`→٪, and
     groups 4+-digit integers except likely years and leading-zero numbers.
   - `data-count` stays machine-readable.
   - A host can opt out with the context key `digits => 'latin'`.
   - FoodMart (an English LTR theme) is unchanged.
6. **Negative tracking.** `.tb-nav-header-logo`, `.tb-features-section-title`,
   `.tb-newsletter-title` and `.tb-header-modern__brand` keep it under
   `[dir="ltr"]` only.

**Consequences:**

- `tests/Support/CssParity/` holds the computed-style harness
  (`corpus.php`, `css.php`, `compare.cjs`, `summarize.php`, README):
  - token modes `default`, `none` and a palette file;
  - before/after corpora;
  - colour normalisation.
- `Phase05TokensAndNumbersTest` fails on:
  - a bare brand tint or border literal;
  - negative tracking outside `[dir="ltr"]`;
  - a literal brand default outside the allowlist;
  - colour-prop injection;
  - Latin prices or counters.
- `canvas-color.test.mjs` pins the value model.
- Parity harness, customer-site live render (229 documents, 3 widths):
  - no tokens: 148 diffs, none a colour (tracking and digit widths only);
  - default tokens: only the intended alignment (tints follow
    `--tb-primary`, WCAG-AA muted text, one-step surface fallbacks);
  - custom palette: 45 element kinds now take the theme colour, and none
    lands outside the palette.

  Preview vs live on the same markup: 48 diffs, all from the host-only
  skill-bar rule. Stored colours: 118 values across both repos and both
  databases, 0 rejected.

**Impact:**

- **Shared theme runtime:**
  - `BlockRenderer`, `BlockRegistry`, the SleepShiman trait;
  - `Security\HtmlSanitizer`, `Inspector\InspectorFieldRegistry`,
    `Support\PersianNumber`;
  - both stylesheets and `header-modern.css`;
  - new `canvas-color.js` and the `canvas/color` view.
- **Platform:** `canvas.blade.php` and `partials/canvas-repeater`.
- **customer-site:** `ThemeCanvasRenderer` (module colours) and the Canvas
  editor.

There is no migration, route or dependency change. Stored hex values keep
rendering exactly as before.

## ADR-065

**Title:** One Vendored SVG Icon Catalog and a Shared Icon Picker; Legacy Emoji Keep Rendering

**Status:** Accepted

**Date:** 2026-10-27

**Context:**

Every icon in the shared blocks was a raw emoji or a typed character:

- features, bento, services and service cards, contact info, trust badges
  and trust items, timeline, icon box, the icon block, mega-menu links;
- registry defaults such as ⚡ 🔒 🚚 ☎️ and the demo data;
- hard-coded placeholders: 🖼️ 📦 for a missing photo, 🛒 👤 in the header,
  📞 ✉️ 📍 🕐 in contact sections.

Emoji render differently on every OS, ignore the theme colour, and cannot
be sized or weighted consistently. Both editors offered a plain text input,
and a top-level `icon` field had no control at all. `type: 'icon'` existed
in `InspectorFieldRegistry::TYPES` but nothing used it.

**Decision:**

1. **One catalog, vendored.** `theme-runtime/resources/icons/catalog.json`
   holds 155 Lucide icons (`lucide-static@0.468.0`, ISC; licence in
   `LICENSE-lucide.txt`). Each icon has a name, a category, a Persian label,
   Persian and English keywords, and an SVG body. Only these icons ship, so
   the set is tree-shaken by construction: no package dependency, no font,
   no network request. The five categories are:
   - فروشگاه (commerce);
   - ناوبری و مکان (navigation);
   - ارتباطات (communication);
   - فناوری (tech);
   - عمومی و رابط کاربری (ui).

   The build allowlists every element (`path`, `circle`, `ellipse`, `rect`,
   `line`, `polyline`, `polygon`) and attribute, so a body is inert geometry.
   `Phase06IconSystemTest` re-checks that.
2. **`Support\IconLibrary`.** It provides `has()`, `label()`, `svg()` and
   `html()`. The SVG is a 1em square drawn with `currentColor`, decorative
   (`aria-hidden`) unless it gets a label.
3. **Renderer.** `BlockRenderer::iconHtml()` draws a catalog name as SVG and
   escapes anything else exactly as before. Stored emoji therefore render
   unchanged, with no migration. The icon block labels its SVG in Persian
   (`role="img"`). Placeholders and header and contact glyphs use catalog
   icons in both hosts' renderers.
4. **Schema.** Every icon field is `type: 'icon'`, with a catalog default:
   - top level: `icon_box.icon`, `icon.name`, `trust_item.icon`,
     `action_button.icon`;
   - lists: features, timeline, trust badges, bento, services, service
     cards, contact info, mega-menu items.

   Registry demo data uses icon names. The editors also stop using an icon
   as a list item's title. Sleep Shiman's own blocks keep that theme's glyph
   ids. customer-site's validator maps `icon` to `string`, as before.
5. **One picker.** `resources/js/canvas-icons.js` (`window.CanvasIcons`) is
   the DOM-free model: catalog, categories, ranked search, `kind()`, `svg()`.
   Search normalises Arabic ي/ك to Persian ی/ک and folds digits, ZWNJ and
   diacritics. `canvas/icons.blade.php` loads it with the catalog and the
   picker's own scoped styles (`.tbip-*`, independent of either host's
   Tailwind build). `canvas/icon-picker.blade.php` is the single markup
   both editors include at every depth: the platform top level and
   repeater partial, and the customer top level and its structured,
   nested and deep lists.

   The picker offers:
   - a searchable grid (Persian or English); typing searches every category;
   - category chips;
   - arrow-key navigation (RTL-aware);
   - Esc, which returns focus to the trigger;
   - a clear button;
   - a field for a custom emoji or text, so legacy values stay editable.
6. **Styles.** `.tb-icon-svg` sizes from the slot's existing font-size.
   Feature and service icons sit on a brand-tinted tile
   (`color-mix(--tb-primary 10%)`). Slot colours target the SVG only, so a
   legacy ✓ or emoji keeps its look.

**Consequences:**

- `Phase06IconSystemTest` checks:
  - catalog licence, categories and Persian labels;
  - the inert-body allowlist;
  - that every icon field is an icon control with a catalog default;
  - that no emoji default remains;
  - SVG and legacy rendering in eight block types;
  - the picker include.
- `canvas-icons.test.mjs` pins search, ranking and legacy handling.
- `ProductSliderRenderSnapshotTest` was re-pinned. Mapping the two
  placeholder SVGs back to 🖼️/📦 reproduces both previous pins exactly.
- **Browser** (three widths, save → reload → preview):
  - platform `phase06-icon-picker.spec.ts`;
  - customer-site `phase06-07-icons-forms.spec.js`.
- **Parity harness:**
  - old markup under new CSS: no icon slot changes, so stored emoji look
    as before;
  - across 221 theme documents no stored icon value changed;
  - preview equals live apart from the known skill-bar rule.

**Impact:**

- **Shared theme runtime:** `BlockRenderer`, `BlockRegistry`,
  `InteractionBlockTypes`, the provider, the new `Support\IconLibrary`,
  `resources/icons/`, `canvas-icons.js`, the `canvas/icons` and
  `canvas/icon-picker` views, and `theme-runtime-blocks.css`.
- **Platform:** `canvas.blade.php`, `partials/canvas-repeater` and
  `ThemePlaceholderService` (catalog names).
- **customer-site:** its BlockRenderer placeholders, `ElementSchemaRegistry`
  and the Canvas editor.

There is no migration or Composer change.

## ADR-066

**Title:** Real Newsletter and Search Behind the Shared Blocks; Hosts Supply the Endpoints

**Status:** Accepted

**Date:** 2026-10-27

**Context:**

- `newsletter_form` (and `email_capture`, which delegates to it) reset
  itself on submit and stored nothing. customer-site had no newsletter
  model, route or controller.
- `search_bar` and the `nav_header` search posted to `#`.
- customer-site overrode `renderSearchBar()` to GET its shop listing. There
  were no suggestions, no blog scope and no mobile treatment.
- `search_bar` had no styles at all.

**Decision:**

1. **Hosts own endpoints; the renderer stays host-neutral.**
   `BlockRenderer::siteEndpoints()` reads the context key `site_endpoints`:
   - `newsletter` — POST URL;
   - `csrf` — token for the script-less fallback;
   - `search` — routes per scope (`all`, `products`, `posts`), each
     `{action, params, param}`;
   - `suggest` — a JSON suggestions URL.

   Every URL passes `HtmlSanitizer::isValidUrl()`. Without endpoints (the
   Builder preview, the customer editor) the forms render exactly as
   before: inert, no script.
2. **customer-site** overrides `siteEndpoints()` with a documented
   `#[HostOwnedOverride]`:
   - `newsletter.subscribe`;
   - with the `advanced_search` module: `search.index` with
     `type=products|posts`, plus `search.suggest`;
   - otherwise: the shop and blog `?q=` listings the site actually has.

   Its own `renderSearchBar()` copy is deleted.
3. **Newsletter backend (customer-site).** `POST /newsletter/subscribe`:
   - **Protection:** inside the web group (CSRF), throttled at 6 requests
     per minute.
   - **Accepts:** an email (lower-cased) or an Iranian mobile in any digit
     script or format (`+98`, `0098`, Persian or Arabic digits), normalised
     to `09xxxxxxxxx`.
   - **Repeats:** a unique contact; a repeat answers «قبلاً ثبت شده» and is
     never stored twice. A race on the unique index is handled.
   - **Bots:** a honeypot field gets a normal answer and nothing is stored.
   - **Responses:** JSON for the script; redirect-back for a plain post.
     Every message is Persian.
   - **Data:** `newsletter_subscribers` stores contact, channel, source,
     IP HMAC and `unsubscribed_at`. The migration is create-only.
4. **One live script,** `resources/js/blocks/site-forms.js`, inlined once
   per render and only with endpoints. It uses document event delegation.
   - **Newsletter:**
     - the same contact rules as the server;
     - one request per submit (busy flag and a 1 s repeat guard);
     - debounced re-validation after an error;
     - a CSRF header from the page meta (no token in cached markup);
     - Persian toasts and an `aria-live` status line covering success,
       duplicate, 422, 419, 429 and network errors.
   - **Search:** a page field, or the header's phone icon, opens one dialog:
     - a centred modal on desktop, full screen at ≤640 px;
     - `aria-modal`, a focus trap, Esc, and focus returned to the opener;
     - debounced (250 ms) suggestions with an `AbortController`;
     - scope buttons (همه / محصولات / مطالب);
     - Persian prices («۱۲٬۵۰۰٬۰۰۰ تومان»);
     - arrow-key results and «مشاهدهٔ همهٔ نتایج برای «…»».

     Without JavaScript the form is a plain GET with its scope parameters.
5. **Styles.** The search bar is a real field (icon, rounded, focus ring on
   `--tb-primary`). The header search folds into an icon at ≤640 px.
   Toasts darken the theme's success/error colours for contrast. Two
   existing layout bugs are fixed:
   - the newsletter field was 240 px tall on phones (flex-basis in a column);
   - the header lost its side gutter below 80rem
     (`padding: 0.5rem 0` overrode `.tb-container`).
6. **Open decisions recorded:**
   - customer-site `CurrencyHelper` prints prices through
     `PersianNumber::format()`, so the storefront and the runtime blocks
     share the Persian separator ٬ (U+066C).
   - `header_modern` keeps hex colours for its contrast maths (ADR-064).

**Consequences:**

- **platform** `Phase07SiteFormsTest`: inert without endpoints, the form
  contract with endpoints, unsafe URL fallback, scope fallback, the script
  once per render, and catalog parity of the script's icons.
- **customer-site** `NewsletterSubscriptionTest`: normalisation, duplicates,
  422 messages, honeypot, re-subscribe, plain-post redirect, the
  web/throttle route contract, the real endpoint wiring with and without
  `advanced_search`, and the inert editor.
- `site-forms.test.mjs` covers the pure helpers.
- **Browser:** `phase07-site-forms.spec.ts` (platform; stub origin, three
  widths) and `phase06-07-icons-forms.spec.js` (customer-site; real route).
- `CanonicalStructuralParityTest` gives the canonical renderer the host's
  endpoints (same input, same output).
- **Release gate:** the new migration's `down()` drops its own table, which
  the customer-site release policy flags for owner review
  (`migration_policy.reviewed`).

**Impact:**

- **Shared theme runtime:** `BlockRenderer`, `BlockRegistry` (`scope`,
  `search_scope`), the provider, `blocks/site-forms.js` and
  `theme-runtime-blocks.css`.
- **customer-site:** `NewsletterController`, `NewsletterSubscriber`, the
  migration, the route, its BlockRenderer (`siteEndpoints()`, with
  `renderSearchBar()` removed), `CurrencyHelper` and
  `account/points/index`.

There is no Composer change.

## ADR-067

**Title:** Commercial Header and Footer, RTL-Correct Carousel, One Product Card and One Blog Card

**Status:** Accepted

**Date:** 2026-10-28

**Context:**

- **Header.** customer-site wraps every header block in its own
  `<header class="tb-site-header">` chrome. `nav_header`'s inline
  `position: sticky` could only stick inside that wrapper, so "sticky" did
  nothing on live pages. There was no scroll-shrink, hide-on-scroll or
  transparent mode.
- **Phone menu.** Below 768 px the chrome hid the block entirely and
  showed a fixed list of generic links. The block's own links, mega menus,
  cart and search were unreachable on phones.
- **Mega menu.** The panel was anchored to its own item and could run off
  screen in RTL. Each item shipped its own inline script, and the promo
  slot had no call to action.
- **Cart.** The header cart linked to `/cart`, a 404 on customer-site
  (`/shop/cart`). customer-site had no mini-cart and no JSON cart API:
  add and remove only redirected back.
- **Footer.** Social links printed text or a first letter. There was no
  newsletter, no app badges and no trust-seal slot.
- **Carousel.**
  - The touch handler was reversed in RTL: swiping right went back, though
    the next slide sits to the left.
  - The arrow keys were hard-coded for RTL.
  - There was no mouse drag.
  - The arrows used physical `left`/`right` with glyphs pointing inward.
- **Cards.** The product card had a generic badge, a plain stock text
  line, no rating and no action. Blog cards were a single `<a>` holding
  date and excerpt only, without author, category or reading time.

**Decision:**

1. **Header behaviour lives on the banner element.** `nav_header` gains
   three props:
   - `sticky_hide_on_scroll` (smart);
   - `transparent_over_hero`;
   - `transparent_text_color` (`''` = automatic contrast).

   It emits `data-tb-header` flags. `blocks/site-header.js`, inlined once
   per render, applies them to `closest('header, [role=banner]')`:
   - `.tb-hdr-sticky` with `.is-scrolled`, which shrinks the header past
     8 px;
   - `.tb-hdr-smart.is-hidden`, set on a down-scroll and cleared on an
     up-scroll or on focus;
   - `.tb-hdr-overlay`, transparent only when the first block below is a
     hero, slider or carousel. Automatic text colour comes from the hero's
     background luminance, or is light for photo heroes. The hero is padded
     by the header's unscrolled height.

   Without the script the block keeps its old inline sticky.
2. **Mega menu.**
   - A full-width panel under the header on desktop, with a hover bridge.
   - An accordion on touch devices and narrow screens.
   - Toggling is delegated (click, Esc, outside click, a truthful
     `aria-expanded`), so the per-item inline scripts are gone.
   - The promo slot gains `mega_banner_cta`.
   - customer-site's phone menu copies the block's own links, mega menus
     included, and keeps the block's action icons visible inside its
     chrome.
3. **Mini-cart.**
   - `siteEndpoints()['cart']` carries page, mini, add, remove and count.
     `cartTriggerHtml()` renders the cart page link with a live badge and
     `data-tb-cart-open`. The Builder preview keeps the old link.
   - customer-site adds `GET /shop/cart/mini`. `addToCart` and
     `removeFromCart` answer JSON with the fresh mini-cart when asked;
     variant-required products answer 422 with the product URL.
   - `blocks/cart.js` provides the drawer: it slides in from the reading
     end, with a focus trap, Esc and focus restore. Money HTML is
     allowlisted and titles are text only.
4. **Footer.**
   - `newsletter_*` props reuse the Phase 0.7 form and endpoint.
   - `social_links` gains a `network` select. URLs are also recognised
     (instagram, t.me, wa.me, x/twitter, youtube, aparat, eitaa, bale,
     rubika, linkedin, facebook, github, mailto, tel); host matching is
     anchored, so netflix.com is not x.com. Icons come from 14 Lucide brand
     and communication glyphs added to the catalog. An unknown network
     keeps its text.
   - `app_links`: Google Play, App Store, Bazaar, Myket, direct download.
   - Trust seals: `enamad_id`/`enamad_code` and
     `samandehi_id`/`samandehi_code`. Only a numeric id and an
     alphanumeric code are kept; a pasted official snippet is parsed for
     them. Our own `<a><img referrerpolicy="origin">` points at the two
     official hosts. There is no iframe, because the seals verify the
     Referer.

   An existing footer without these props renders as before; its social
   links now show icons.
5. **Carousel.** One engine (`carousel.js`), direction-aware:
   - previous at the reading start and next at the end, as outward SVG
     chevrons (mirrored in LTR);
   - the arrow key on the next slide's side;
   - pointer drag (touch, pen, mouse) that follows the finger, damps at
     the edges and suppresses the click after a drag;
   - a swipe towards the reading end advances.

   FoodMart, an LTR theme with its own Swiper markup, is out of scope.
6. **One product card,** `productCard(array)`. `productCardHtml()` stays
   as a positional adapter. It shows:
   - a discount badge with the real percentage («۲۱٪ تخفیف») and other
     badges, both through `badgeHtml()`;
   - the Toman price with the old price struck through;
   - a stock pill (in, low, out);
   - a star rating with an accessible label.

   Its action depends on context:
   - with cart endpoints: an instant add-to-cart `<form>` posted by
     `cart.js` (a plain POST without JavaScript), which opens the drawer;
   - «انتخاب گزینه‌ها» for products with variants;
   - «ناموجود» when out of stock;
   - a link in the preview.

   customer-site's `productCardData()` feeds it real products: the
   approved-review average and count, and stock against the low-stock
   threshold.
7. **One blog card,** `blogCardHtml()` plus `blogCardsLayout()` (grid, list
   or slider). It shows a category pill, the title, the excerpt, the author
   avatar or initial, a `<time>` with a Persian date («۵ آبان ۱۴۰۵») and
   the reading time. customer-site feeds real posts (Verta `j F Y`,
   `estimatedReadingMinutes()`). The Builder preview shows three clearly
   labelled sample posts in the chosen layout instead of one line of text.

**Consequences:**

- **Tests:**
  - `Phase1HeaderFooterCarouselTest` (platform);
  - `ThemeCartDrawerAndCardsTest` (customer-site);
  - `site-header-cart.test.mjs`;
  - browser `phase1-2-header-footer-carousel.spec.ts` (stub origin,
    three widths) and customer-site `phase1-2-cart-drawer-cards.spec.js`
    (real routes).
- **Re-pinned:** the product-slider snapshot. Rendering the HEAD package
  and this one with each slide's content and both arrows tokenised hashes
  identically in both modes.
- **Icon search ranking:** a whole-term match now beats a match on one
  word of a longer name, so «star» finds star before star-half.
- **Parity harness** (before is `83a4cdbb`):
  - old markup under the new CSS changes only the mega-menu anchoring,
    the arrows and the carousel grab cursor;
  - preview equals live apart from the known skill-bar rule.

**Impact:**

- **Shared theme runtime:** `BlockRenderer`, `BlockRegistry`, the
  provider, `carousel.js`, the new `site-header.js` and `cart.js`,
  `catalog.json` (169 icons) and `theme-runtime-blocks.css`.
- **customer-site:** `ShopController` (`mini()` plus JSON branches), the
  route, its BlockRenderer (cart endpoints, `cart_icon`, product and blog
  card data), `ThemeCanvasRenderer` and the canvas-theme layout (phone
  menu and SVG theme toggle).

There is no migration or Composer change.

## ADR-068

**Title:** Drawer Quantity Stepper, Derived Product Badges, One Bestselling Query, Jalali Countdown and the Commercial FAQ

**Status:** Accepted

**Date:** 2026-10-28

**Context:**

- **Drawer.** The Phase 1/2 mini-cart could only remove a line, at once
  and without asking. Changing a quantity meant leaving for the cart page.
  customer-site's `updateCart` only redirected back, and the package's
  `cartEndpoints()` kept only page, mini, add and remove.
- **Badges.** The product card showed the real discount and one
  free-text badge. Rating was shown but never earned a badge, and «جدید»
  arrived as free text.
- **Bestselling.** customer-site's registry kept one `HOST_PATCHES` entry
  that hid `module:shop_product_grid`'s «پرفروش» source, because the module
  renderer had no sales query. That renderer also drew its own plain card
  instead of the shared one. The data binding had a 30-day sales query
  (`best_selling_30d`) inline in `ProductDataSource`.
- **Countdown.** `end_date` was a Gregorian string handed to `new Date()`
  in the visitor's browser, so its instant depended on the visitor's time
  zone. There was no Jalali input, the digits were Latin and every style was
  inline.
- **FAQ.** The canonical `renderFaq()` escaped answers (line breaks lost)
  and rendered an empty box with no items. customer-site overrode it with
  an empty-state hint, aliases and sanitized rich answers, flagged as an
  unmerged capability. The accordion had no styling beyond the contract
  sheet. FAQPage JSON-LD already existed page-level on customer-site
  (`CanvasStructuredData`), but only for `faq`, not `module:faq_dynamic`.

**Decision:**

1. **Stepper in the drawer** (`blocks/cart.js`, version 2).
   - `cartEndpoints()` accepts an optional, URL-validated `update`.
     Without it the drawer shows a quantity line and no stepper.
   - Each line has «+», the quantity and «−». At one, «−» becomes a trash
     button.
   - A step repaints the line total, subtotal and header badge at once
     from `unit_amount`, with `faNumber()` producing exactly
     `PersianNumber::format()`'s digits and ٬ separator, plus the host's
     `money_suffix_html` and unit. After 350 ms of quiet, the last quantity
     per line is posted, and the host's mini-cart replaces the estimate. A
     refusal (422) shows the host's message and reloads the host's cart.
   - All cart writes (add, update, remove) go through one serial queue:
     the session cart is not locked, and overlapping writes would drop one.
     Leaving for the cart page or closing the drawer flushes pending steps
     first.
   - Removing (trash, or «−» at one) asks inline: «… از سبد حذف شود؟»
     with «حذف» and «انصراف». Focus goes to «انصراف»; Esc cancels and
     returns focus. Confirming removes the line at once and drops its
     unsent step.
   - customer-site: `updateCart` answers JSON through
     `cartDone()`/`cartFail()` (plain posts still redirect). `miniCart()`
     adds `unit_amount`, `money_suffix_html` and `unit`, from the new
     `CurrencyHelper::displayValueWithContext()`/`fadedZerosHtml()` (which
     `formatPriceWithContext()` now uses itself). `siteEndpoints()['cart']`
     gains `update`.
2. **Derived badges** in `productCard()`. Overlays sit on the image in this
   order, three at most:
   - the real discount;
   - «پرامتیاز» (`warning`) for an approved-review average of at least
     `TOP_RATED_MIN_RATING` (4.5) over at least `TOP_RATED_MIN_REVIEWS` (5)
     reviews;
   - «جدید» (`info`) from the new `is_new` key;
   - the free-text badge, unless it repeats one of the above.

   customer-site's `productCardData()` passes `is_new`. The Builder demo
   cards do too.
3. **One bestselling query,** `Product::scopeBestSelling($days = 30)`:
   completed orders in the window, highest revenue first (the semantics
   `best_selling_30d` already had), then by id. `ProductDataSource` and
   the module renderer both call it. `module:shop_product_grid` and
   `module:shop_product_slider` now render the shared `productCard()` with
   ratings, badges and add-to-cart. A bestselling grid with no sales says
   «در ۳۰ روز گذشته هنوز فروشی ثبت نشده است.» The `HOST_PATCHES` entry is
   gone; the host registry is now the canonical registry exactly.
4. **Countdown with Jalali end dates.**
   - `Support\JalaliDate`: dependency-free Jalali ↔ Gregorian conversion
     (the jalaali-js arithmetic), leap years and month names.
   - `end_date` accepts a Jalali date (`۱۴۰۵/۰۸/۱۵`, any digit script,
     `-`, `/` or `.`), ending at `end_time` (HH:MM) or at 23:59:59 on that
     day. Times are Tehran time: +03:30, since Iran has no daylight saving
     time from 2022. The target ships as an ISO 8601 instant with that
     offset, so every visitor counts to the same moment.
   - A Gregorian value without `end_time` is emitted exactly as stored, so
     existing countdowns keep their instant. An impossible date (for
     example ۱۴۰۴/۱۲/۳۰) shows a format hint and no script.
   - New props: `end_time`, `expired_text`, `style` (card or promo banner)
     and `show_end_date` («پایان: ۱۵ آبان ۱۴۰۵، ساعت ۲۰:۰۰»).
   - The adapter prints Persian digits and marks `.is-expired`. The timer
     has `role="timer"`. The styles moved from inline attributes to the
     shared sheet.
5. **Commercial FAQ,** with one renderer on both hosts.
   - The canonical `renderFaq()` takes over customer-site's override: the
     empty-state hint, `title`/`content` aliases and sanitized rich
     answers. Plain answers keep their line breaks.
   - New props: `exclusive` (every `<details>` shares a `name`, so opening
     one closes the others) and `open_first`.
   - A chevron in each summary, styled cards and an open state are added
     in the shared sheet.
   - `module:faq_dynamic`'s accordion style delegates to `renderFaq()`.
   - FAQPage JSON-LD stays page-level and host-owned, so a page with several
     FAQ blocks still publishes one FAQPage. `CanvasStructuredData` now
     reads `module:faq_dynamic` too, sliced to its `limit`: structured data
     matches what is visible. The aliases are read as well.
6. **Migration rollback.** customer-site `d5c5e74` emptied
   `create_newsletter_subscribers_table::down()`. Rolling back then
   deleted the migration row and left the table in place, untracked.
   `up()`'s `hasTable` guard would then hide any later schema change on
   re-run. `down()` again drops only its own table. The file is restored
   byte for byte to the content of the existing owner review in
   `release-policy.json` (`c2eb08f8…`), so that review covers it with no new
   entry.

**Consequences:**

- **Re-pinned on purpose:**
  - the countdown with-date structural snapshot (inline styles to the
    sheet, `role="timer"`, `hidden` expiry line); the no-date shape is
    unchanged;
  - the product-slider snapshot: three badge spans differ from the HEAD
    render, and nothing else;
  - the countdown JS test now expects Persian digits.
- **Tests:**
  - `Phase3CommerceWidgetsTest` (platform);
  - `ThemePhase3CommerceTest` (customer-site);
  - browser `phase3-commerce-widgets.spec.ts` (stub origin, three widths);
  - customer-site `phase1-2-cart-drawer-cards.spec.js` now exercises the
    stepper and the confirmed removal on real routes, unconditionally.
- **Parity harness** (before is `82194f5d` + customer-site `852949e`):
  - live markup changes in 34 of 229 documents, all FAQ;
  - old markup under the new CSS changes four element kinds, all the FAQ
    restyle (summary, answer, item, wrapper); no other block moves;
  - preview equals live apart from the known skill-bar rule.

**Impact:**

- **Shared theme runtime:** `BlockRenderer`, `BlockRegistry`
  (countdown, faq), the new `Support\JalaliDate`, `cart.js`,
  `countdown_adapter.js` and `theme-runtime-blocks.css`.
- **customer-site:** `ShopController`, `CurrencyHelper`, `Product`,
  `ProductDataSource`, its BlockRenderer (renderFaq override removed,
  `update` endpoint, `is_new`), `ThemeCanvasRenderer` (module product grid
  and slider, faq_dynamic), `CanvasStructuredData`, `BlockRegistry`
  (no patches) and the newsletter migration's `down()`.

There is no new migration or Composer change.

## ADR-069

**Title:** Bento v2 Dynamic Cells on a 12-Column Grid, One Review Card with Rating Breakdown and Microdata, Pricing Billing Switch and Feature Checklist; Bestselling Counts Paid Orders

**Status:** Accepted

**Date:** 2026-10-30

**Context:**

- **Bento.** `bento_grid` was a three-column grid of static text and
  image cards (two columns below 767 px, one below 480 px). A cell could
  not show a product, a campaign countdown or a figure.
- **Reviews.** `testimonials_slider` printed ★/☆ text stars, with no date
  and no verified-buyer mark. customer-site's `module:reviews_grid` had
  the same text stars, no breakdown and no structured data.
- **Pricing.** `pricing_cards` had one price per plan, a generic badge for
  the popular plan, and features as `✓` text with no way to mark a feature
  as missing or explain it.
- **Bestselling.** `Product::scopeBestSelling` counted completed orders
  only, so an order dropped out while paid, processing or shipped. Owner
  decision 2026-10-30: count paid orders.
- **Editor.** The platform repeater's `show_if` understood only a boolean
  field, so kind-specific fields could not follow a select.

**Decision:**

1. **Bento v2.**
   - The grid has 12 columns. Sizes span `small` 4, `half` 6, `large` 8,
     `full` 12, `tall` 4 × 2 rows and `featured` 8 × 2 rows; the old sizes
     keep their desktop widths. At ≤ 1023 px it has 2 columns (`large`,
     `full` and `featured` span both; `featured` drops to one row). At
     ≤ 639 px it has 1 column. Tracks are `minmax(0, 1fr)`, so live content
     cannot widen a column.
   - An item `kind` makes a cell dynamic:
     - `product`: the shared `productCard()`. customer-site's
       `bentoProductHtml()` shows the real `product_id` (or the newest
       product when 0); the Builder shows a labelled sample. A product cell
       is never itself a link, because the card has its own links and form;
     - `countdown`: the Phase 3 Jalali countdown, compact
       (`end_date`/`end_time`);
     - `metric`: icon, a large localized figure («۱۲٬۰۰۰+») and its label.
   - `content` cells render byte-identically to before.
   - The platform editor's `show_if` also accepts `field=value` or
     `field=a|b` (an empty value reads as the field's default), so
     `product_id`, `end_date`/`end_time` and `metric_value` appear only for
     their kind. customer-site's editor shows all fields, as it always has.
2. **One review card,** `reviewCardHtml()`, plus `ratingSummaryHtml()` and
   the shared `starsHtml()` (the product card uses it too, with unchanged
   output).
   - The card shows SVG stars with a spoken rating, the date in Jalali
     («۲ آبان ۱۴۰۵», from Jalali or Gregorian input via the new
     `JalaliDate::parse()`), «خریدار تاییدشده», and the avatar or initial.
   - The summary shows the average, the count and one Persian bar per star
     level («۵ ستاره ۶۰٪»), with counts for screen readers.
   - Microdata only where it is true:
     - authored testimonials get none: they review no item, and reviews of
       the site's own business are not eligible;
     - customer-site `module:reviews_grid` with `product_id` wraps its
       reviews in that Product, with an `AggregateRating` over all its
       approved reviews and each card as a nested `Review`;
     - without a product, each card is a standalone `Review` whose
       `itemReviewed` is its product. The store-wide breakdown carries no
       microdata, because it rates no single item.
   - Verified: the reviewer's account has a paid order containing that
     product. One query covers the whole list.
   - New props: `testimonials_slider` items get `verified` and `date`, and
     the block gets `show_summary`. `module:reviews_grid` gets `product_id`
     and `show_summary`.
3. **Pricing.**
   - The `billing_toggle` switch («ماهانه» / «سالانه» with the
     `yearly_discount_label` pill) appears only when a plan has
     `price_yearly`. Both prices are in the markup, with the yearly one
     `hidden`, so without JavaScript the monthly prices show. A plan without
     a yearly price keeps its monthly price in both modes.
   - The highlighted plan wears a ribbon on its top edge (the badge text, or
     «محبوب‌ترین»), in the primary token. Other badges stay `badgeHtml()`.
   - Features `{text, included, tooltip}` show a Lucide `check` or `x`; an
     excluded one is muted and struck, and says so to screen readers. A
     tooltip is a button with `aria-describedby`. It shows on hover and
     focus, toggles on tap, closes on an outside tap, and Esc dismisses it
     while it is still hovered (WCAG 1.4.13). The bubble spans its feature
     row, so it never leaves the card. Legacy string features count as
     included.
   - `blocks/pricing.js` is inlined once per render, only when a block has
     the switch or a tooltip.
4. **Bestselling counts paid orders:** `Order::SOLD_STATUSES` = paid,
   processing, shipped, completed. Pending and cancelled orders do not
   count; there is no refunded status in this schema. `paid` is included
   because it is a paid order: leaving it out would drop an order from the
   ranking between payment and processing.

**Not done:**

- The saas-platform commit pin in customer-site's `release-policy.json`.
  `git_dependencies` has no commit field: `ReleasePreparer` records the HEAD
  of the clean, pushed `main` checkout. An unread key would be a false pin.
  Enforcing a pin is a deployment-system change, and that is the owner's
  call.

**Consequences:**

- **Re-pinned on purpose:**
  - the testimonials star assertions (`Phase01RepeaterContractTest` and the
    phase01 browser spec) now check the spoken label and SVG stars;
  - the phase01 spec's bento column count is 12 / 2 / 1 and its size labels
    are the new ones. It also checks that kind-specific fields follow
    `show_if`.
- **Tests:**
  - `Phase3bBentoReviewsPricingTest` (platform);
  - `ThemePhase3bReviewsBentoTest` (customer-site; verified buyers, both
    microdata modes, the live bento product);
  - `ThemePhase3CommerceTest` (bestselling across statuses);
  - browser `phase3b-bento-reviews-pricing.spec.ts` (stub origin, three
    widths: column count and each size's share, no clipping, ticking
    countdown, breakdown bars, toggle, tooltip hover, Esc and tap, no
    horizontal overflow).
- **Parity harness:** the before state is saas-platform `30b89909` (the last package without
  Phase 3b) with customer-site `2e37c2d`.
  - **Live markup:** 5 of 229 documents change, all reviews or pricing.
    No bento document changes: content cells are byte-identical.
  - **Old markup under the new CSS:** 16 element kinds, all from the bento
    grid or the pricing restyle:
    - the bento grid moves to 12 / 2 / 1 columns. At 1366 px only the track
      list and the spans change (3 × 405 px tracks become 12 × 89 px, with
      `span 4` and `span 8`), so no item moves or resizes. At 768 px the grid
      has 2 columns instead of 3, as specified;
    - pricing cards become `position: relative`, and the highlighted card
      gets 12 px more top padding for the ribbon. The newsletter form below
      shifts with them;
    - reviews: nothing, because the old markup is untouched.
  - **Preview vs live:** 48 distinct diffs across 230 platform documents,
    all on `div.tb-skill-bar-fill` (the known host-only rule).
- **Release note:** the Phase 3b package code was committed by another
  session (`15323394`) before the editor half (`repeaterFieldVisible()`)
  landed, and was released from `main` as `f5ffc2f5` (Hub release
  `04a89771`, awaiting its artifact, not deployed). In that release the
  platform editor hides the bento kind fields, because the old evaluator
  reads `kind=product` as a field name. A release that includes this
  phase's editor commit is needed..

**Impact:**

- **Shared theme runtime:** `BlockRenderer`, `BlockRegistry` (bento_grid,
  testimonials_slider, pricing_cards, module:reviews_grid),
  `Support\JalaliDate::parse()`, the provider, the new `pricing.js` and
  `theme-runtime-blocks.css`.
- **Platform editor:** `repeaterFieldVisible()` and the repeater partial.
- **customer-site:** `Order::SOLD_STATUSES`, `Product::scopeBestSelling`,
  its BlockRenderer (`bentoProductHtml()`) and `ThemeCanvasRenderer`
  (`module:reviews_grid`).

There is no migration or Composer change.

## ADR-070

**Title:** DevOps Disk Governor Extends LIFECYCLE-2 Resource Governance; Measures and Prunes Through Each Site's Deployment Driver; One Shared Threshold Set; Prune Is Super-Admin Only

**Status:** Accepted

**Date:** 2026-10-31

**Context:**

- The owner asked for a resource governor with thin-provisioned per-tenant
  disk quotas, danger zones (normal below 80 %, warning 80–90 %, critical
  above 90 %), autonomous and on-demand pruning, and a Persian dashboard.
- The request proposed a new `tenant_resource_allocations` table. LIFECYCLE-2
  (`docs/RESOURCE_GOVERNANCE_ARCHITECTURE.md`) already stores per-site limits
  (`site_resource_limits`) and usage (`site_resource_usage`), and alerts at
  80 / 95 %. Its `storage_bytes` is estimated from DB row counts, so nothing
  measured real disk usage.
- The request assumed tenant files at `/var/www/customer-sites/tenant-*`. In
  this codebase, `customer-sites/tenant-{id}/` is the Arvan backup-bucket
  prefix. Web roots are per installation (PROVISION_DOMAIN's document root,
  else `WebRootResolver`: `/var/www/{slug}` or cPanel `public_html`), usually
  on remote hosts that are reachable only through the installation's driver.
- `HealthCheckService` already read panel-host disk, `/proc/meminfo` and
  load average.

**Decision (owner choices 2026-10-31):**

1. **Extend LIFECYCLE-2, no parallel quota store.**
   - `site_resource_limits` gains nullable `disk_soft_limit_bytes`,
     `disk_hard_limit_bytes`, `cpu_cores_limit` and `memory_limit_bytes`.
     Precedence is unchanged: site override, then plan row, then config
     (`resource_governance.disk.*`, default soft limit 5 GB).
   - `site_resource_usage` gains `disk_usage_bytes` (real `du`),
     `danger_level`, `disk_audited_at` and `disk_audit_error`.
     `storage_bytes` stays the DB estimate.
   - New `tenant_prune_audit_logs` keyed by `site_id`, the repository's
     tenant key.
   - A quota edit that creates a per-site row seeds it from the site's
     effective limits, so plan values are never replaced by column defaults.
2. **Measure and prune through the driver.** `TenantTargetResolver` maps
   site → latest completed installation → server → `DriverFactory` driver
   and web root.
   - SFTP, DirectAdmin and Docker cannot execute commands, so those sites
     read "not measurable".
   - Commands are quoted with POSIX rules (`PosixShell`), never
     `escapeshellarg()`, which uses Windows rules on a Windows panel.
3. **One threshold set.** Danger zones read `resource_governance.alert_*`.
   The critical default moved from 95 to 90, so LIFECYCLE-2 storage alerts
   now also fire at 90 %. At or above 80 % is warning; at or above 90 % is
   critical.
4. **Pruner safety model** (`TenantStoragePruner`, `PrunePathGuard`):
   - **Fixed categories only:** `storage/logs`, framework cache, compiled
     views, expired sessions, `storage/app/{livewire-tmp,tmp}`, and
     `storage/backups/backup_*.sql*`. Dumps always keep the newest N. Media,
     `public/` and `storage/app/public|uploads|media|private|theme-assets`
     are protected. The shared system `/tmp` is never touched.
   - **Root checks:** the web root must be normalized, at least two levels
     deep and not a system directory. It is resolved with `realpath` and
     must contain `artisan` and `storage/`.
   - **Target and listing checks:** a symlinked target is skipped. Every
     listed path is re-validated in PHP. Age is re-checked in PHP.
   - **Deletion:** only explicit `rm -f -- <path>` batches, at most
     `max_files_per_run` files per run.
   - **Audit and locking:** each run writes its audit row before deleting
     and holds a per-site lock.
5. **Scheduling.** `devops:govern-resources` (alias `devops:resources:govern`)
   runs every 6 hours. A warning site gets the `routine` profile, a critical
   site gets `aggressive` and one notification when it escalates.
   - It deletes only when `RESOURCE_AUTO_PRUNE_ENABLED=true`. The default
     (false) records dry-run previews, so the owner sees what autonomous
     pruning would do before enabling it.
6. **Authorization.**
   - Viewing, re-measuring and quota edits need admin access, the same level
     as LIFECYCLE-2's `recalculate` and `updateLimits`.
   - Preview and prune are **super-admin only** (`User::isSuperAdmin()`;
     owner decision 2026-10-31). There is deliberately no grantable
     permission, so no role, staff member or legacy `is_admin`-only user can
     prune, even with a hand-made `devops.storage.prune` row.
   - A UI prune needs a single-use token from a preview, bound to site, user
     and profile. The prune re-scans the host itself.

**Not done:**

- `cpu_cores_limit` and `memory_limit_bytes` are recorded for capacity
  planning and are not enforced on any host. The UI says so.
- Hosts must provide GNU `find -printf`, `head -z` (coreutils ≥ 8.25),
  `realpath`, `du` and `df`. A host without them fails the run, and the
  failure is audited.

**Consequences:**

- 90 % is the system-wide critical threshold (owner-confirmed 2026-10-31).
  LIFECYCLE-2 storage alerts move from 95 % to 90 % unless
  `RESOURCE_ALERT_CRITICAL_PCT` is set.
- Autonomous deletion stays off (`RESOURCE_AUTO_PRUNE_ENABLED=false`, owner
  decision 2026-10-31) until the owner enables it.
- The overcommit ratio counts only servers measured in the last 26 hours (the
  `df` from each audit is cached).
- The panel-host tiles describe the panel server. Tenant capacity comes from
  the per-server pool.

**Impact:**

- **Migrations:** `2026_10_31_120000` (adds columns) and
  `2026_10_31_120001` (new table). Their `down()` methods drop only what
  their `up()` creates. The owner reviewed both on 2026-10-31, recorded in
  `.deployment/release-policy.json`.
- **New code:** `app/Services/DevOps/*`, `DevOpsResourceController`, the
  `devops:govern-resources` and `devops:prune-tenant` commands, and
  `resources/views/admin/devops/*`.
- **Edited:** additive keys in `HealthCheckService`, the
  `config/resource_governance.php` `disk`/`prune` sections, the Kernel
  schedule, the admin routes and the `AdminMenu` entry.

## ADR-071

**Title:** One Breakpoint Scale for the Widget Runtime; Column and Grid Slots Are Size Containers; Fluid Title Floors That Keep Every Existing Size From 390px Up

**Status:** Accepted

**Date:** 2026-10-31

**Context:**

- The widget runtime used ten viewport widths (480, 500, 600, 639, 640,
  767, 768, 1023, 1024, 1025) and no container queries
  (`docs/theme-builder/CANVAS_WIDGET_LOCAL_VS_PROD_AUDIT_2026-10-30.md` §3).
  The header's touch-menu switch was 1025px in `site-header.js` and
  1023/1024px in CSS. The `767px`/`768px` pairs matched neither side at
  fractional (zoomed) widths.
- Card blocks inside a `columns` or `grid` layout block followed the
  viewport, not their slot. A four-column product grid in a third-width
  column stayed four columns wide.
- In boxed mode the image slider's progress row, pause button and
  thumbnails had no styles: the thumbnails rendered their images at full
  size and the page scrolled sideways at every width.
- The owner approved WU-D (audit §5) under the zero-diff rule. Existing
  pages must not change at the widths the CSS parity harness measures
  (390, 768, 1366).

**Decision:**

1. **Scale:** sm 480, md 768, lg 1024, xl 1280. Viewport queries use only
   `max-width: 479.98 / 767.98 / 1023.98 / 1279.98px` and
   `min-width: 480 / 768 / 1024 / 1280px`, in both package stylesheets,
   `header-modern.css`, the renderer's inline `<style>` and
   `site-header.js`.
   - Off-scale widths move to the nearest step: 500/600 → 480, 639/640 →
     768, 1025 → 1024.
   - Carousel item counts are the one exception: the mobile count starts
     below 480px, so a 480–767px landscape phone keeps the tablet count
     instead of one stretched card.
   - `BreakpointScaleTest` fails on any new off-scale width.
2. **Legacy `max-width: 768px` rules stay.** Four rules put a 768px iPad in
   phone mode: `renderColumns()` stacking, timeline, commercial and trust
   grids, dynamic widget. Moving them to 767.98px would change existing
   pages at 768px. Their count is pinned per file and may only go down.
   Owner decision 2026-10-31: keep them as they are, for tablet-portrait
   readability and strict zero-diff on existing layouts.
3. **Slots are containers.** Each child of `.tb-columns` and of the
   `grid` layout block (now marked `.tb-layout-grid`) gets
   `container: tb-slot / inline-size`.
   - `@container tb-slot` rules repeat the viewport steps for `.tb-grid`,
     the commercial, trust, dynamic, testimonial and pricing grids, and for
     carousel item counts.
   - A grid that is itself the slot cannot query its own width. It fits as
     many columns as hold a 14rem card, capped at its own count.
   - Top-level blocks have no `tb-slot` ancestor and are untouched.
   - A slot holding an overlay, drawer, modal or header is left
     uncontained (`:not(:has(...))`), because containment would capture
     its fixed-position layers. Browsers without `:has()` drop the rule
     and contain nothing.
4. **Fluid title floors.** For hero, h1, section, CTA and full-width slider
   titles, only the clamp floor moves. It equals the old floor from 390px
   up and shrinks 0.1px per px below it, to a lower bound. Every theme, at
   any type scale, keeps its size at 390px and wider. The hero_section
   heading now grows from 0.72× at 390px to full size at 768px instead of
   jumping at 640px.
5. **Boxed slider controls** get in-flow styles on the page surface. On the
   dark slide they kept the full-width slider's overlay look.

**Consequences:**

- The CSS parity harness found no visual difference on either host, in
  default or `--tokens=none` mode (229 + 230 documents, 3 widths). The only
  diffs are `container-name`/`container-type` on the column slots of one
  snapshot (sales-support-pro).
- Between the measured widths, behaviour changes where an off-scale step
  moved: 481–600px, 641–767px, exactly 1024px, and phones narrower than
  390px. The overflow gate (`tests/Support/CssParity/overflow.cjs`)
  reports zero horizontal overflow on every document at 360, 600, 768,
  1024 and 1280px.
- Nested card content changes on purpose: it now collapses by its slot.
- customer-site host chrome (`layouts/canvas-theme.blade.php`) and its
  account/admin pages keep their own widths. They are host-owned and
  outside this scale.

## ADR-072

**Title:** One Media and Link Field Map Derived From the Registry; One Pre-Render Step for Every Host Render Path; Renderers Never Cast a Media or Link Value

**Status:** Accepted

**Date:** 2026-10-31

**Context:**

- Canvas overhaul Unit 1 audit (read-only SSH, 2026-10-31): every block
  render failure in the production customer-site log since the
  2026-10-31 01:10 deploy was "Array to string conversion" — 65 ×
  `bento_grid` (`BlockRenderer.php:3507`, `(string) $item['bg_image']`)
  and 16 × `nav_header` (`normalizeMegaMenuData()`,
  `(string) $link['mega_banner_image']`). That is the Bento "invalid after
  one render" the owner reported.
- A Media Library pick is stored as `{"media_id": "<uuid>"}`. Two separate
  defects let it reach a renderer unresolved:
  1. `CustomerMediaResolver::MEDIA_PROPS/MEDIA_REPEATERS` were hand-kept
     lists. `bento_grid` named a field `image`; the registry field is
     `bg_image`. A registry scan found 17 of 53 image fields unresolved
     (every FoodMart image, `hero_section`, `image_box`, the mega-menu
     column items). The list format allowed one field per repeater and one
     nesting level.
  2. Only `ThemeCanvasRenderer` resolved media and internal links. The
     default theme layout (header/footer), `home.blade.php`,
     `PageBuilderController` and `ContentBuilderController` render with the
     plain host `BlockRenderer` and resolved neither.
- Internal-page links were resolved at the top level only, and the
  `LINK_PROPS` allowlist ran `resolveValue()` on every value of its fields,
  turning an ADR-061 structured `{url, target}` button link into `''`.
- The canonical renderers cast `image` and `link` values to string or
  passed them to `escapeUrl(string)` in 25 + 52 places. A registry sweep
  found 41 image fields and 52 link fields that broke the whole block on a
  non-string value.
- The editor showed `[invalid] bento_grid` with no cause; the cause was
  only in the server log.

**Decision:**

1. **`App\Services\ThemeCanvas\SchemaFieldMap`** (customer-site) is the
   one answer to "which props of this block type hold a control of type
   X". It walks the registry schema, repeater `fields` at any depth (`*`
   = every item), and keeps each repeater in its stored shape (a JSON
   string stays a JSON string; an unchanged value is returned
   byte-identical; a changed repeater is written in its declared
   `storage` shape). `MEDIA_PROPS`/`MEDIA_REPEATERS` are deleted;
   `CustomerMediaResolver` (render time) and `ThemeMediaLocalizationService`
   (install time) both read the map for `image` controls.
2. **`ThemeBuilder\BlockRenderer::prepareBlock()`** resolves media and
   internal links inside `renderBlock()`, before validation, so every host
   render path and every child block is prepared. `ThemeCanvasRenderer`
   calls it for `module:*` blocks, which bypass the parent dispatch.
   `preloadMediaFor()` moved to the same class.
3. **Links resolve by shape only.** `InternalLinkResolver` replaces any
   top-level `{type: internal_page}` value and every nested `link` control
   (`SchemaFieldMap`), and leaves every other value untouched. `LINK_PROPS`
   is deleted.
4. **Renderers never cast.** Canonical `mediaSrc()` reads an `image`
   value (unresolved → `''`, "no image"); `linkValue()`/`linkHref()` read a
   `link` value. SleepShiman's `ssImage()`/`ssUrl()` funnels and the
   FoodMart reads use them. The host Element SDK validator gains a `link`
   type, and `hero`/`button` declare their URL props as `link`.
5. **Registry `link` contracts win.** In `ElementSchemaRegistry`, a prop
   that the registry declares as a `link` control validates as `link`
   even where `config/builder-element-schemas.php` still says `string`.
6. **`bento_grid.items[].image` is a real control.** `renderBentoGrid()`
   has always rendered it as the card's own picture (the `has-media` card
   the Universal QA theme seeds three of), but the schema had no control,
   so it could not be edited and, once the map replaced the hand list, would
   not be localized on install. It is now an `image` control
   (`show_if: kind=content`) beside `bg_image`.
7. **The editor shows the cause.** Placeholders carry a Persian reason and
   the error (file paths cut to file names, 300 characters max) when
   rendered for the editor (`isEditorRendering()`); public pages never do.

**Consequences:**

- Sweeps (both registries, every depth): `MediaFieldUnresolvedSweepTest`
  and the structured-link case of `LinkFieldUnsafeSchemeSweepTest`
  (platform); `MediaFieldResolutionSweepTest` (customer-site). They were
  red on `e5a8e713` (41 image fields; 52 link fields).
- The CSS-parity corpus is byte-identical before/after on both hosts:
  existing themes render unchanged. Only Media Library and structured-link
  values change behaviour.
- A new `image` or `link` field in the registry is covered automatically;
  there is no list to keep in step.
- The platform editor stores URL strings, not `{media_id}`; the unified
  entity/link selector (overhaul Phase 2) builds on this map.

## ADR-073

**Title:** Visual Design System v1: Rhythm, Elevation and Motion Tokens; One Card Interaction; Section Style Controls That Work; Persian-Safe Typography

**Status:** Accepted

**Date:** 2026-11-01

**Context:**

- The owner's Canvas overhaul brief (2026-10-31) asks for soft modern
  widgets: spacious vertical rhythm, multi-layer shadows, smooth radii,
  hover lift, glass badges and fluid typography, with keyboard
  accessibility and `prefers-reduced-motion`. The owner assigned the whole
  visual system (CSS tokens and markup) to one session; findings from a
  peer's read-only pass were folded in.
- Two controls had no effect:
  - `theme-runtime-blocks.css` loads after the contract sheet, and its
    `.tb-section { padding: 2.5rem 0 }` overrode every `.tb-padding-*`
    class (same specificity), so a section's padding control did nothing.
  - `renderSection()` emits `tb-radius-*`, `tb-shadow-*` and
    `tb-hover-lift`, and the grid blocks emit `tb-gap-*`, but only the
    customer-site default layout defined them. Canvas pages and the
    platform editor ignored them.
- Every card family carried its own hover rule: single-layer shadows of
  different strength, 0.15–0.35 s durations, `scale(1.02)` on bento,
  `translateY(-2px)` on product/blog and `-4px` elsewhere, with no
  keyboard equivalent and no shared reduced-motion guard.
- `--tb-radius-md` defaulted to 0.5rem while its alias `--tb-radius` was
  0.75rem.
- Hero titles used line-heights of 1.05–1.2, which clip Persian ascenders
  and descenders. Testimonials used `font-style: italic`, but Vazirmatn has
  no italic, so browsers synthesise an oblique that breaks Persian joins.

**Decision:**

1. **Tokens** (`theme-runtime-contract.css`, on `:root, [data-theme]`):
   `--tb-ease-out`, `--tb-dur-fast/--tb-dur/--tb-dur-slow`, `--tb-lift`,
   `--tb-space-section`, `--tb-space-header`, `--tb-gap`, `--tb-hairline`
   (8% of `--tb-text`), `--tb-radius-card`, `--tb-elev-rest/-hover` and
   `--tb-focus-ring`. They are declared on `[data-theme]` too, because a
   var() inside a custom property resolves where it is declared, so a
   dark-mode subtree would otherwise inherit the light hairline. Fluid sizes
   use `clamp()` only, with no viewport queries (ADR-071 scale).
2. **Normalizer defaults** (`ThemeTokenNormalizer`): the radius scale is
   sm 0.5 / md 0.75 / lg 1.125rem, and the shadow defaults are soft
   two-layer shadows. A theme's authored `border_radius`/`shadows` still win.
3. **The padding scale is fluid and actually applies.** The base rule only
   targets `.tb-section:not([class*="tb-padding-"])`.
4. **The section style utilities and grid gaps live in the package.**
   `.tb-grid` takes its gap from `--tb-grid-gap`, which `tb-gap-*` sets. The
   fit-columns calc reads that same variable instead of a hard-coded 1.5rem.
5. **One card interaction**, in a single layer at the end of the shared
   sheet: a lift of `--tb-lift` with `--tb-elev-hover` on `:hover` and
   `:focus-within` (the same feedback for mouse and keyboard), a half-lift
   on `:active`, a focus ring on card links, and one media-zoom curve for
   product, blog and image-led bento. Minimal variants and live bento cells
   (product, countdown) do not lift. Buttons gain a brand-tinted shadow,
   a press state and `:focus-visible`.
6. **Glass ribbons** over product photos: blur, an inner highlight and a
   soft drop shadow. Tone fills stay opaque, so the `--tb-on-*` AA pairs
   still hold. The neutral ribbon is a frosted surface.
7. **Persian typography:** hero titles at 1.25–1.3 and headings at 1.4,
   with the tight values kept for `[dir="ltr"]`. Testimonial text is
   upright in RTL and italic only in LTR.
8. **One reduced-motion guard** covers every lift, press and zoom above
   and the logo marquee.

**Consequences:**

- This is an intentional visible change, proven with the CSS parity
  harness (platform corpus, before and after stylesheets). Apart from the
  changed token values, which every element inherits, the diffs are only
  padding, line-height, border-radius, font-weight, box-shadow, header
  margins, grid gaps and the columns they derive, the testimonial
  font-style, the bento hairline and the ribbon backdrop-filter.
- The overflow gate shows 0 overflowing documents at 360/768/1280 before
  and after (230 documents).
- Guard tests stay green: BreakpointScale, CommercialTokenRatchet,
  TypographyScaleWiring, the stylesheet contracts and the token normalizer
  (158 tests).
- Themes that author their own radius/shadow tokens keep their values.
  Themes that relied on the defaults get the new elevation and radii.

## ADR-074

**Title:** One Entity Link Catalog and Picker for Every Canvas Link Field; Links Are References Resolved Live

**Status:** Accepted

**Date:** 2026-11-01

**Context:**

- The owner's brief asks for Elementor-style dynamic links: header, footer,
  nav menu, CTA and card links should pick CMS pages, product categories,
  products and blog posts from the database, with no URL pasting, and should
  follow the dashboard ("two-way sync").
- The customer-site editor offered a page-only picker, and only on
  top-level `link` fields. Every link nested in a repeater (nav links,
  footer columns, card links) took a pasted URL, and a renamed product or
  post silently broke every link to it.
- ADR-072 already resolves references by shape at every repeater depth
  (`SchemaFieldMap` + `InternalLinkResolver`), so the runtime side needed
  only a broader reference type.

**Decision:**

1. **`App\Services\ThemeCanvas\EntityLinkCatalog`** (customer-site) is the
   one catalog: fixed site destinations (home, shop, blog, courses), pages,
   products, product categories, blog posts, blog categories and courses.
   - It searches for the picker and resolves to the live permalink through
     the same public routes menus use (`page.show`, `shop.show`,
     `shop.index?category=`, `blog.show`, `blog.category`, `courses.show`).
   - Only published content resolves (`Page::published`,
     `Product::published`, `Post::published` including the editorial
     status, `Course::active`). Anything missing resolves to `''`.
   - Shop, blog and course routes are always registered but gated by the
     `module:` middleware, so a destination is offered and resolved only
     while `SiteHelper::moduleEnabled()` is true. A disabled module never
     produces a link to a blocked page.
2. **A picked link is stored as `{type: 'entity', entity, id, label}`.**
   The label is only for the editor chip; the URL is resolved at every
   render. `InternalLinkResolver` resolves it, and still resolves the
   legacy `{type: 'internal_page'}` shape. The schema validator's `link`
   type accepts the reference.
3. **One editor control and one picker.** The anonymous component
   `x-canvas-link-field` wraps every link input: the top-level field and
   the item, nested and deep repeater levels. Each level passes its own
   writer, so values persist through that level's usual update path. One
   modal (`entityPicker`) searches live with type tabs and shows each
   result's current URL.
4. **Search endpoint:** `GET admin/theme-design/canvas/link-entities`
   (`ThemeCanvasLinkEntityController`), inside the canvas admin group. It
   queries the live database, so a page or product created in the
   dashboard is offered immediately.

**Consequences:**

- `EntityLinkCatalogTest` covers resolution for every entity type, slug
  renames, unpublish, disabled modules, the admin-only endpoint, a
  reference inside `nav_links` and a public page following a slug change.
  The existing internal-page tests keep passing.
- The page-only picker state and methods (`linkPicker*`,
  `searchLinkPages`, `selectLinkPage`) and the editor's use of the menu
  page-search route are removed. The golden-journeys browser spec targets
  the new picker.
- The platform editor still stores URL strings: platform themes are
  templates without a customer's content. Template-library sections can
  ship `{type: 'entity', entity: 'route', id: 'shop'}` style links that
  resolve on any installed site.

## ADR-075

**Title:** A Ready-Made Template Library Shipped as Code in theme-runtime, with Package-Served Media and Route Entity Links; Template Insert Lands in Its Zone and Renders at Once

**Status:** Accepted

**Date:** 2026-11-01

**Context:**

- Canvas overhaul Unit 5 (owner brief 2026-10-31, scope extended
  2026-11-01) asks for a ready-to-use library in the existing TB6
  template modal: 3+ headers, 3+ footers, 3+ heroes, 3+ bento grids,
  product showcases, blog carousels, trust/testimonials, a pricing
  comparison table, stats counters and an FAQ accordion. Images must be
  real, ship inside the package, and work identically locally and on the
  server. "Inserting any template must cleanly inject its validated schema
  and immediately render perfectly in the editor canvas, resolving all
  internal media assets and entity links."
- Both hosts' TB6 modals listed only database rows, which were empty
  after the 2026-11-01 clean slate. Tenant media ids differ per host, so
  rows cannot carry portable images.
- The insert path had five defects:
  - the platform `insertTemplate` queued operations without
    `autoSaveAndReload()`, so nothing appeared until the next edit;
  - neither editor routed a header or footer template to its zone, so it
    landed in the page body;
  - a 422 was swallowed silently;
  - customer-site had no unknown-type gate;
  - the platform gate built its type list with `array_keys(...) +
    array_keys(...)`, a numeric-key union that dropped extension palette
    types.

**Decision:**

1. **`SaaSPlatform\ThemeRuntime\TemplateLibrary`** reads one JSON document
   per template from `resources/library/templates` (25 templates). Ids are
   `lib:<key>`; each document declares `type`, `category`, `order`,
   `thumbnail` and either `blocks` or `compose`. A `compose` list builds a
   page template from section templates, so each section is authored
   once. Both hosts merge `TemplateLibrary::list()` ahead of their saved
   templates and accept `lib:` ids on insert. The response adds the
   template's `zone`: `page`, `header` or `footer`.
2. **Two whole-value tokens keep documents portable:**
   - `@media:<file>` becomes `/theme-runtime/library/<file>?v=<sha256-12>`,
     a host-relative, content-versioned URL. The media localizer leaves it
     untouched because no host matches a trusted origin, so a platform
     theme installed on a customer site keeps working.
   - `@link:<route>` becomes `{type: 'entity', entity: 'route', id, label,
     url}`. customer-site's `EntityLinkCatalog` (ADR-074; `contact` added)
     resolves it to the live permalink, or to no link while the route's
     module is off. Every other renderer reads the `url` fallback.

   Repeaters are authored as arrays and written in the storage the
   registry declares (ADR-060), so `json`-storage repeaters become strings.
3. **Media ships in the package and is served by the package.**
   - `resources/library/media` holds nine WebP files (~390 KB),
     cover-cropped by `resources/library/tools/derive-media.php` from the
     committed photographs in saas-platform's
     `database/seeders/assets/universal-multipurpose-qa`. Those
     photographs are unbranded and already live on production as theme
     media 59/60. The owner's watermarked brand banners are deliberately
     not used, so platform branding never lands on tenant sites.
   - `routes/library.php` (loaded with `loadRoutesFrom`, so `route:cache`
     works) serves exactly the shipped files by whitelist lookup, never a
     path join, as `image/webp`, `immutable` and `nosniff`. There is no
     publish step and no storage symlink.
4. **Insert lands in its zone and renders at once, in both editors.**
   - Platform: `switchTarget(zone)`, then for header and footer templates
     a confirmed replace of the zone. Id-less legacy chrome gets an id
     first, because structural operations address nodes by id. Inserts go
     through `treeOperations`, then `autoSaveAndReload()`.
   - customer-site: switch to the virtual `__header__`/`__footer__` page,
     with the same confirmed replace. History is pushed before the change,
     as `addBlock()` does, then `autoSaveAndReload()`.
   - Both editors toast server messages (422 included), keep a busy state
     and log caught errors. Cards are keyboard-accessible buttons with a
     library badge and a description.
   - The platform gate uses `array_merge`. customer-site
     `SiteThemeTemplateService::insertBlocks()` fails closed on unknown
     non-`ext:*` types; `ext:*` stays with the save-time reconciler.
5. **Renderer fix found by the library:** `bentoColorToken()` rejected a
   hex fallback inside `var()`. That included the registry's own default
   `glow_color: var(--tb-primary, #6366f1)`, so token-coloured bento cards
   silently fell back to grey. The allowlist now accepts
   `var(--x, #hex)` and still rejects anything beyond one token.

**Consequences:**

- saas-platform `TemplateLibraryTest`:
  - coverage of the owner's set;
  - every prop declared in the registry, select values valid, images are
    library URLs, links are library routes or absolute external URLs,
    declared storage honoured;
  - media served immutably from the whitelist only, and every shipped
    file used;
  - every template goes insert → `canvas.save` into its zone → canvas
    `page-preview` with no placeholder, every image present and every
    route link rendered;
  - the colour fix.
- customer-site `TemplateLibraryInsertTest` covers the same journey
  through `canvas-save-blocks` and `canvas-live-preview`, with every route
  link at its live permalink, a published full page with header and
  footer on `/`, and disabled modules dropping their links.
- Browser specs `canvas-template-library` in both repos click a library
  card in the real editor. A header replaces the Header zone, a hero lands
  on the page, both appear in the iframe with the library images loaded,
  and nothing throws.
- Product and blog templates use the live data blocks (`product_slider`,
  `category_slider`, `product_grid`, `blog_posts`). They show the
  platform's labelled samples in the builder and the site's real catalogue
  on customer-site.
- `insertBlocks()` now returns `{zone, blocks}` (the one existing caller
  test is updated). The modal category list gains navigation, store,
  pricing, FAQ and corporate.
- Visual QA: every template rendered through the canonical renderer had
  0 horizontal overflow, 0 broken images and 0 failed requests at
  360/768/1280.

## ADR-076

**Title:** A Widget Schema Contract and Manifest over the Canonical Registry; Header Logo Image, Menu Placement and Quick-Access Icons; Native Iranian Messenger Marks; data-widget-* Hooks; a Shared Scrolling Page-Tab Strip

**Status:** Accepted

**Date:** 2026-11-02

**Context:**

- Owner brief 2026-11-02 (the "Sentinel Verifier" brief): make the Canvas a
  commercial, schema-driven system and close four gaps:
  - widgets need a stable id and a registry tests can address;
  - the page tabs above the Canvas overflow with no standard scroll;
  - the header has no menu placement and no logo upload, and the
    header/footer lack native Iranian messengers (Eitaa, Bale, Rubika,
    Soroush, Gap);
  - quality depends on manual testing.
  Phases 1 and 2 are implemented here. Phase 3 (the Sentinel verifier
  suite) is planned in COMMERCIAL_WIDGET_ROADMAP.md §16.
- Most of Phase 1 already existed:
  - `BlockRegistry` keys are stable ids with a category, icon, `schema` and
    `defaultProps`;
  - both editors render the Inspector from `schema` alone (ADR-060/061/062),
    and `InspectorFieldRegistry` normalises fields.
  What was missing was a gate: nothing failed when a definition drifted out
  of the vocabulary the editors render. 15 fields had no label, and one
  `ui` hint was honoured by the platform editor only. The registry also
  offered no machine-readable view for browser checks.
- Platform page tabs sat in an `overflow-x-auto flex-none` box. `flex-none`
  never shrinks, so each new page widened the toolbar past the screen.
  customer-site's strip did scroll, but with a hidden scrollbar and no
  control, so a mouse could not reach hidden tabs.
- `nav_header` supported only a text logo, with the site's global logo as
  fallback (BLD-25). Its links were always pushed beside the actions, and it
  had no social icons. The footer listed Eitaa, Bale and Rubika with a
  generic chat glyph, and had no Soroush or Gap.

**Decision:**

1. **`Widget\WidgetSchemaContract`** is the gate for every definition,
   canonical or host-registered:
   - the id is a slug, and the definition has a name;
   - the category is a registry `CATEGORY_*`. Reference-theme packs
     (`foodmart`, `sleepshiman`) are accepted without a palette icon;
   - `InspectorFieldRegistry` accepts the schema;
   - every non-`blocks` field has a label;
   - select defaults are options;
   - `ui` hints are ones both editors render (`select`: `column_picker`,
     `segmented`);
   - `group` is a top-level label;
   - repeaters declare `fields` and never `item_schema`;
   - number defaults sit inside min/max;
   - `slots` are slugs;
   - every default prop has a field.

   `manifest()` publishes id → category, slots and field → control for
   tests and the Sentinel verifier. `WidgetSchemaContractTest` runs it over
   the booted registry (163 types) in saas-platform, and over the host
   registry in customer-site.
2. **Two schema-driven Inspector features, in both editors:**
   - `ui: 'segmented'` renders a select as a radio-button group;
   - a field's `group` opens a sidebar section where it first appears.

   customer-site number inputs now honour schema `min`, `max` and `step`.
   The 15 unlabelled fields got Persian labels.
3. **`Support\SocialNetworks`** is the one network list:
   - it supplies the select options, URL detection, Persian names, icons
     and brand fills;
   - `nav_header` and `nav_footer` build `social_links` from it through one
     registry helper;
   - Soroush Plus (`splus.ir`, `sapp.ir`) and Gap (`gap.im`) are added.
   Iranian messengers draw simplified marks on Lucide's stroke grid
   (`resources/icons/social-marks.json`). These are recognisable shapes,
   not official logos, and kept apart from the ISC Lucide catalog. An opt-in
   `social_style: brand` fills each icon with its network colour (the five
   Iranian messenger colours are owner-specified: Eitaa `#EA5A24`, Bale
   `#00A693`, Rubika `#9C27B0`, Soroush+ `#1D72B8`, Gap `#9B51E0`). Every
   fill keeps a white glyph at 3:1 or better, the WCAG 2.1 AA requirement
   for icons (SC 1.4.11). The default `mono` is the previous look.
4. **`nav_header`:**
   - `logo_image`, `logo_alt`, and `logo_height` / `logo_height_mobile`
     (clamped to 20–120 and 16–96 px). The fallback order is image (with
     `logo_text` beside it), then `logo_text`, then the global logo;
   - `menu_align` (start / center / end). `end` is the original layout and
     adds no class. `center` uses a three-column grid from 1024 px;
   - `social_links` in the actions slot before the CTA, plus
     `social_style` and `social_show_mobile` (hidden below 768 px by
     default);
   - its fields are grouped into four sections, and `bg_color` and
     `text_color` become real colour controls, as in `nav_footer`.
5. **Hooks:**
   - `data-widget-type` on every block wrapper: both package wrappers and
     customer-site's `wrapEditorMode` override;
   - `data-widget-slot` on the header (logo, menu, actions, quick-access)
     and the footer (brand, columns, bottom, social). The slots are
     declared in each definition's `slots`;
   - `data-canvas-page-tab` on every page tab in both editors, including
     the platform's header and footer switches as `__header__` and
     `__footer__`.
6. **`theme-runtime::canvas.tab-strip`** (+ `tab-strip-nav`,
   `resources/js/canvas-tab-strip.js`) is included once by both editors:
   - the page tabs scroll in their own viewport, with edge-aware chevrons;
   - a vertical wheel scrolls the strip;
   - the `aria-current` tab is kept in view when it changes or a page is
     added;
   - RTL scroll uses the standard negative scrollLeft model;
   - `prefers-reduced-motion` is honoured;
   - it adds no new Tailwind utilities, so it works before an asset rebuild.

**Consequences:**

- Adding a widget property is a registry edit. The contract test fails a
  definition that the editors could not render.
- Existing headers and footers render as before:
  - attributes are added;
  - no class is added for default values;
  - mono icons keep their markup, and only the three native messengers'
    glyphs change.
- The registry's footer network option order changes, with the Iranian
  messengers listed first. Stored values are unchanged.
- customer-site's validator derives the new props from the registry with
  no config change. Its media sweep covers `logo_image` automatically
  through `SchemaFieldMap`.
- Follow-ups: a footer logo image; a `network` field for the standalone
  `social_icons` block; replacing a mark with an official glyph when the
  owner supplies a licensed asset.

## ADR-077

**Title:** Sentinel UI Verifier: a Read-Only Playwright Audit in saas-platform, Run by a Workspace Agent Chained after the Integrity Sentinel

**Status:** Accepted

**Date:** 2026-11-02

**Context:**

- The owner approved Phase 3 of the 2026-11-02 brief (roadmap §16.3) and
  the registration of a Sentinel agent in `../agents/agents.json`. The aim
  is an automatic feedback loop that finds UI/UX defects on local and
  production sites and states each fix as a task, instead of relying on
  manual checks.
- The existing `sentinel-verifier` agent audits the deployment proof chain
  from evidence files. Its spec forbids network access and probes, so
  browser checks cannot live there.
- ADR-076 added the hooks a browser check needs:
  - `data-widget-type` and `data-widget-slot`;
  - a registry manifest.

**Decision:**

1. **The suite lives in saas-platform at `tests/sentinel/`** (its own
   Playwright config; every option is an environment variable, `BASE_URL`
   required).
   - **Per page and width** (default 360/390/768/1024/1280/1440), it checks:
     - page horizontal overflow, with the culprit elements and their
       widgets;
     - content sticking out of its widget;
     - the widget feature profiles and dead `href="#"` links;
     - broken images, failed same-origin requests, console and script
       errors;
     - clipped text, ignoring screen-reader-only content;
     - touch targets under 24 px on phones;
   - **Once per page:** `dir="rtl"`, Vazirmatn, and infinite animations
     under reduced motion.
   - **Opt-in:**
     - a read-only editor check, using a signed-in storage state, that
       verifies the page-tab strip stays inside the toolbar;
     - screenshot comparison against baselines per host. Baselines are
       written only with `SENTINEL_UPDATE_BASELINES=1`.
   - Specs only load pages. A PHPUnit guard rejects click, fill, type and
     other write actions in them.
2. **Feature profiles:** `Widget\WidgetFeatureProfile` declares per widget
   type the selectors a live widget must match, each with a code,
   severity, message and task text. They use the ADR-076 slots wherever
   possible. `php artisan sentinel:profiles` writes them to
   `tests/sentinel/widget-profiles.json`, and the test checks the file is
   current and that every type and slot exists in the registry manifest.
3. **The report:** the suite's reporter writes `report.json`
   (`sentinel.ui-report/v1`) and `report.md`. Findings are grouped into
   tasks, one per code, widget type and remedy, listing every page and
   width. The verdict is BLOCKED if there is any blocker, else ATTENTION if
   there is any warning, else READY.
4. **The agent `sentinel-ui-verifier`** is in `../agents`, documented in
   that README.
   - Task `sentinel.ui-audit`. It is subscribed to
     `sentinel.verification.passed` (it runs after the integrity audit
     passes) and to `sentinel.ui-audit-requested`.
   - It resolves the target from `workspace.json` `sentinel_ui.targets`.
     Local is `http://127.0.0.1:8000` / `http://customer-site.test`;
     production is `https://panel.safhenokhost.ir` /
     `https://safhenokhost.ir`.
   - It runs the suite with its output under `agents/var`, recomputes the
     verdict, turns tasks into findings, seals the report as a
     `sentinel-ui` artifact, and emits `sentinel.ui-audit.passed` or
     `.failed`.
   - It never writes to the projects.

**Consequences:**

- Every deployment that passes the integrity Sentinel gets a UI audit with
  a task list. The verifier can also be run by hand against either host.
- Widget hooks that are missing in an older production release make the
  feature audit report `NO_WIDGETS_FOUND` rather than failing. The
  page-level checks still apply.
- The first runs found real defects:
  - the platform landing page (`panel.safhenokhost.ir/`) is LTR, scrolls
    sideways by 93 px, loads Figtree from `fonts.bunny.net` against its own
    CSP, and has no Vazirmatn;
  - the production storefront has a 22 px-tall header phone link on
    phones;
  - local demo content has a header CTA with `href="#"` and a footer
    without links.

  These are reported, not fixed here.
- Follow-ups:
  - more profiles (product grid, blog, pricing);
  - baselines on a known-good release;
  - wiring the Hub's `deployment.completed` evidence into this chain on the
    deployment host. Production Hub hosts have no `agents` checkout, so
    today the chain runs on this workstation.

**Amendment (2026-11-02, owner guardrails and the first fixes):**

- **The agent is a read-only advisor with capped, de-duplicated output:**
  - at most 3–5 findings per scan (`max_tasks`, default 5), most urgent
    first (P1 blocker, P2 warning, P3 UX friction), with the rest kept as
    backlog in the sealed report;
  - a state fingerprint over the target, pages, widths, deployed commit,
    suite commit and `widget-profiles.json` hash. While it is unchanged,
    tasks already reported are not repeated, and a rerun inside the
    600-second cooldown does not start the browser.
  - `force` overrides both.

  The details are in `../agents/README.md`.
- **Widget friction metric:** `Widget\WidgetFrictionProfile` reads the
  registry schema and lists widgets missing an essential author control.
  Today that means carousels without drag/swipe physics, navigation dots,
  arrows or autoplay; 7 widgets, including `slider`, `product_slider` and
  `testimonials_slider`. Per-breakpoint padding and corner radius come from
  both editors' Style tab for every block, so they count as provided. A
  friction widget on an audited page is a `UX_FRICTION` task (P3); the
  inventory ships in `widget-profiles.json`.
- **Fixes from the first audit:**
  - the platform landing page (`resources/views/welcome.blade.php`) is now
    a Persian, RTL page using local Vazirmatn and no third-party origin,
    with 44 px targets, logical properties and a working dashboard link
    (the stock page linked to `/home`, which does not exist). The local
    audit moved from BLOCKED (5 tasks) to READY at all six widths;
  - the header phone link is a 44 px target below 768 px;
  - the `header_modern_v1` and `nav_header` CTA defaults point at
    `/contact` instead of `#`;
  - new `nav_footer` blocks start with a "دسترسی سریع" column linking `/`
    and `/contact`.

  Only routes every storefront has are used. Content already saved on a
  site, such as the local demo's header CTA and footer, is the author's to
  change.

## ADR-078

**Title:** One Motion Widget, a Curated Motion Library with Free/Premium Tiers, and Lifecycle-Safe Publishing

**Status:** Accepted

**Date:** 2026-11-05

**Context:**

- The owner asked for the Motion Graphics ecosystem to be finalized across
  both repositories: every motion selectable from one comprehensive canvas
  widget, a picker fed only by the site's runtime manifest, licensed
  (premium) motions shown only to licensed sites, and two production-grade
  motions (a corporate hero and a business-growth motion).
- An audit on 2026-11-05 found:
  - **Two motion widgets.** The canonical `module:motion` block (picker over
    the site manifest, rendered by customer-site through
    `<x-motion.graphics>`) and a second `motion_graphic` block (e91b1ccb)
    that only ever rendered an empty `<div data-motion-id>`. It also broke
    customer-site's `CanonicalBlockDefinitionParityTest` (`module_entity`
    and `toggle` have no customer canvas control). The `hero_motion` block
    before it had embedded its SVG in `BlockRenderer`, outside licensing
    and versioning; about 300 lines of its CSS were still in the shared
    sheet.
  - **No real asset behind the only catalog record.** `hero-vector-showcase`
    v1 was published with `compiled_assets = []`, later patched by a raw
    `DB::table()` update to an absolute URL that does not exist, with an
    invented size. That bypassed `MotionGraphicVersion`'s immutability
    guard. Re-running its seeder now throws, because it `updateOrCreate`s
    an immutable version. Its engine `svg-css` is not in `allowed_engines`.
  - **Nothing serves `/motion/...`.** The module binds
    `InMemoryMotionAssetStorage`, so no compiled asset URL resolves.
  - **No per-asset licensing.** `SiteAssetPackageActivationService` carried
    `LicenseCompatibilityDecision::notImplemented()`, and only
    `required_assets` were ever installed (`optional_assets` were ignored).
  - **Two hard constraints in the manifest contract:** customer-site's
    reader accepts only root-relative `optimized_reference` values and
    `asset_id` matching `^[a-z0-9-]+$`. Absolute URLs and underscore slugs
    (`hero_motion`) are rejected by design.

**Decision:**

1. **`module:motion` is the only motion widget.** Every motion (hero,
   business, any future library asset) is an asset selected in that
   widget, never a block type of its own. `motion_graphic`
   (registry entry, `renderMotionGraphic()`, editor color case) is retired.
   The orphaned Hero Motion CSS is replaced by sizing rules for
   `.tb-module-motion`. Prop keys and types of `module:motion` are
   unchanged. Stored `motion_graphic` blocks (none found in either local
   database) fall to `renderFallback()`.
2. **The curated library is the source of truth for platform motions.**
   Assets live in the module's
   `assets/library/{slug}/{vN}/` (`animation.svg`, `preview.svg`,
   `metadata.json`, README, AI-editing rules). That directory is the only
   copy: `MotionLibraryAssetController` serves it at
   `/motion/{slug}/{vN}/animation.svg` (the module's `asset_path_prefix`),
   registered outside the `web` group (no session or cookie), for catalog
   slugs and existing version directories only, with immutable caching,
   `nosniff`, a `default-src 'none'` CSP and
   `Cross-Origin-Resource-Policy: cross-origin` so customer sites can embed
   it. A copy under `public/` was rejected: `public/**` is outside the
   release policy's `allowed_release_paths`, so it would never ship, and a
   second copy can drift. References are root-relative; customer-site
   prefixes the platform origin. A new art version is a new directory;
   published files are never edited in place.
3. **`MotionLibraryCatalogService` publishes the library** (used by
   `MotionGraphicsDefaultSeeder`, safe in every environment):
   - a version row is never updated: a changed file becomes version N+1,
     published, with the previous published version retired (status only),
     exactly like `MotionLibraryService::publish()`;
   - unchanged files write nothing (idempotent);
   - `compiled_assets[0]` = `{type, path, url, mime, mime_type, size,
     sha256}` (the pipeline's storage descriptor plus `type`/`mime_type`);
     `source_checksum` = sha256 of the source snapshot, as the pipeline
     computes it;
   - the broken `hero-vector-showcase` record is archived (no published
     pointer, versions retired, history kept).
4. **Two commercial tiers, one licensing system.**
   `runtime_configuration.license_tier` is `free` or `premium`; missing or
   unknown means premium. `MotionAssetLicensePolicy` decides:
   - free: allowed (the site still needs the existing `motion-graphics`
     module license, checked once per manifest by `RealMotionLicenseGateway`,
     unchanged);
   - premium: additionally needs the new `motion-graphics-premium` module,
     a license-only module (no price, not marketplace-visible; granted by an
     admin until the owner prices it), checked through the same
     `ModuleLicenseServiceInterface` as every module.
   The tier crosses the read boundary as `compatibility.license_tier`
   (`DatabaseMotionAssetReader`).
5. **Entitlement is applied twice, by design.**
   - At activation: required assets must be licensed or the activation
     fails atomically (as before); optional assets are installed only when
     available, compatible and licensed, otherwise skipped with a
     `motion.asset.skipped` audit event. So a site package holds only what
     the site may use.
   - At every manifest build: an item whose license lapsed or was revoked
     is skipped, so revocation takes effect without re-activation.
6. **One shared template package, `motion-library:core`,** lists the
   library assets as optional; each site's package receives its entitled
   subset. `MotionGraphicsDemoSiteSeeder` provisions a local/test site
   through the real services (licenses, package, activation, flag) and
   refuses to run in production.
7. **Asset quality bar** (enforced by `CommercialMotionLibraryTest`): 960×540
   `viewBox`; `role="img"` with a Persian title; no script, event handlers,
   `foreignObject`, external URLs, web fonts, filters or SMIL; only
   transform, opacity and stroke-dash animation; an intro that plays once
   and ends in its complete frame, then ambient loops;
   `prefers-reduced-motion:reduce` stops everything; `prefers-color-scheme:dark`
   swaps the `--asset-*` tokens; RTL with Persian digits; under 20 KB.

**Consequences:**

- The canvas picker on customer-site (`canvasMotionAssets`, unchanged)
  lists exactly the site's licensed assets, because it reads the same
  manifest.
- customer-site's `MotionRuntimeRenderer` now uses the asset's display name
  as `alt` (not the uuid) and emits intrinsic `width`/`height` from
  placement dimensions.
- Light/dark inside an `<img>` follows the visitor's OS/browser color
  scheme, not a site-level theme toggle.
- Not done: per-asset (rather than per-tier) purchases, theme-token
  overrides delivered into the SVG, and a real object-storage adapter.
  Each is a separate decision.

## ADR-079

**Title:** Motion Runtime Parameters, Embedded Vazirmatn, and Nested Motion Validation

**Status:** Accepted

**Date:** 2026-11-06

**Context:**

- Three defects in the motion widget after ADR-078:
  1. A `module:motion` block inside a container (`flex_container`,
     `section`, `grid`, …) rendered the whole container as an invalid
     placeholder: `[child 0] Unknown element type: module:motion`.
     customer-site's `ElementSchemaRegistry` skipped every `module:*` type,
     on the assumption that module blocks never enter the validator. That
     holds at top level only; a container validates its whole subtree.
  2. Site owners could pick a motion but could not change anything in it
     (texts, figures, colours, loop, speed).
  3. Text inside the motion SVGs fell back to system fonts. An SVG shown
     through `<img>` cannot load external resources, so the page's
     Vazirmatn never reaches it.
- Constraints:
  - the master `editable_schema_json` is encrypted, hidden, and forbidden
    in the runtime manifest (ADR-078, MANIFEST_SCHEMA.md);
  - `<img>` embedding is what isolates the SVG from the page (no script,
    no CSS leakage either way); inlining the SVG would lose that;
  - versions are immutable, and installed sites must never break when the
    master changes (`docs/motion-persistence/VERSION_BINDING_RULES.md`).

**Decision:**

1. **Nested validation.** `ElementSchemaRegistry` registers `module:*`
   types as leaves (children `none`) with their typed props, so a nested
   motion is validated like any other leaf. Top-level dispatch is
   unchanged. A real prop error (e.g. malformed `motion_params`) now reports
   that error instead of "Unknown element type".
2. **Runtime parameters: a separate, public contract.** Each library asset
   version declares `parameters` in `metadata.json`. Types:
   - `text`: targets `data-p="key"` elements;
   - `color`: overrides one `--asset-*` token, light and dark;
   - `number`: optional percent text, and `--p-key`/`--p-key-rest`
     variables;
   - `boolean` with role `animate` (static frame) or `loop` (one cycle);
   - `select` with role `speed` (slow/normal/fast scale every duration
     through `var(--k)`).
   `MotionSvgComposer` (motion-graphics package) normalizes definitions
   (malformed ones are dropped), validates values (unknown keys, invalid
   values and defaults are dropped; control characters stripped; Latin
   digits become Persian; text is set as DOM text, never markup) and
   composes the SVG deterministically. The catalog stores the normalized
   definitions in `runtime_configuration.parameters`; they travel as
   `parameters` through the projection read model, `RuntimeProjectionDTO`
   and `CustomerRuntimeAssetEntry` into the manifest. The master editable
   schema is never read for this.
3. **Applying parameters keeps `<img>` isolation.** The block stores sparse
   overrides in a new canonical field `module:motion.motion_params`
   (`type: motion_params`). customer-site re-validates them against the
   manifest definitions (`MotionRuntimeParameters`) and appends only valid,
   non-default values, sorted, as the image URL's query. The platform route
   validates again and composes. The same URL always yields the same bytes,
   so `Cache-Control: immutable` stays correct. Queries over 4 KB are refused
   (414).
4. **Inspector.** The customer Canvas renders the selected motion's controls
   from its manifest parameters, grouped (texts and figures, colours,
   animation), with a reset button. Values are stored on every input event:
   `schedulePropUpdate()` is already debounced, and a per-field debounce
   read the input after a re-render had reset it, which lost typed text.
   Choosing a different motion clears the previous motion's overrides. The
   platform Canvas shows a hint for `motion_params`, because master templates
   have no site manifest.
5. **Vazirmatn travels inside the SVG.** A Persian subset of the Vazirmatn
   variable font, limited to weights 400–800 (42.7 KB woff2,
   `assets/fonts/vazirmatn-motion.woff2`, with `OFL.txt` and build notes),
   is embedded by the route as an `@font-face` `data:` URI. The response CSP
   gains `font-src data:`. The SVG source keeps the stack
   `'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
   sans-serif` and root `direction="rtl"`, and never embeds a font itself.
6. **Library v2.** `hero-motion` and `business-growth-motion` get a `v2`
   directory: `data-p` hooks, speed-scaled durations, the font stack, and for
   business growth a donut arc bound to `share_percent`. `v1` is untouched
   and is still served byte-for-byte. The compiled asset's size/sha256 now
   describe the served default bytes (font included); `source_checksum`
   stays the library file's.
7. **Version binding is respected on publish.** The catalog retires an
   older published version only when no active site package item is bound
   to it; every sync re-applies this. Publishing v2 therefore never breaks a
   site still bound to v1. Re-activation upgrades the site (for example
   `MotionGraphicsDemoSiteSeeder`, or the admin activation flow), and the
   next sync retires v1. ADR-078's "previous published version retired"
   is refined by this rule.

**Consequences:**

- A served motion is about 70 KB (about 57 KB of it the font), cached as
  immutable per distinct parameter set.
- Changing the font or the composer's output for an existing version
  changes bytes under an unchanged URL; such a change needs a new library
  version.
- `MotionCustomerRuntimeService` still projects the master's latest published
  version for a bound item, as before this ADR. Until upgraded, a site bound
  to v1 receives v2 visuals and parameters through that existing behaviour.
- Tests:
  - package: `MotionSvgComposerTest`, `CommercialMotionLibraryTest` (v1 and
    v2 rules, font licence);
  - host: `MotionLibraryCatalogTest` (route parameters, font, bound
    versions), `MotionLibraryEndToEndTest`;
  - customer-site: `MotionRuntimeParametersTest`, reader parameter
    normalization, nested `module:motion` validation, and the browser spec
    `canvas-motion-widget.spec.js`, which inserts into a section, picks a
    motion, edits parameters, saves, reloads and checks the render.

## ADR-081

**Title:** Exclusive (Site-Owned) Motions Beside the Public Catalog

**Status:** Accepted

**Date:** 2026-11-07

**Context:**

- The public motion catalog (ADR-078/079) gives every licensed site the same
  motions, split by tier: free for every site, premium with the
  `motion-graphics-premium` license.
- The owner also needs custom motions that are active for one specific site
  and invisible to every other site, in the manifest and at the file URL.
- An audit found most of the model already in place:
  - `motion_graphics.ownership_type` (`global` / `customer_specific`) and
    `owner_site_identifier` (numeric site id), enforced by the model;
  - the runtime projection and `SitePackageReader` return nothing to a site
    that does not own a `customer_specific` asset;
  - the bespoke commission, purchase and assignment tables, with the
    `/api/motion-runtime/bespoke-manifest` endpoint.
- Four connections were missing:
  1. customer-site reads only `/api/motion-runtime/manifest`, so bespoke
     assignments never reached the canvas picker;
  2. the bespoke entries had no `name`, `category` or `parameters`;
  3. the catalog always published `global`, and the only file route is
     public, unauthenticated and immutably cached;
  4. `/manifest` failed with `no_active_package` for a site that has bespoke
     assignments but no package.
- Constraints: one owner site per motion; no customer-site change or
  release; hero-motion and business-growth-motion keep their bytes, URLs,
  versions and manifest checksums.

**Decision:**

1. **One manifest.** `/api/motion-runtime/manifest` appends the site's
   active bespoke assignments after its package items. An asset the package
   already delivers is not repeated. A site with only bespoke assignments
   gets a manifest with `template_reference` `motion-bespoke` and an empty
   `package_id`. `no_active_package` now means neither an active package nor
   an active assignment. `/bespoke-manifest` stays, and both use one
   builder, so every entry carries `name`, `category` and `parameters`.
   Bespoke entries get no tier check: the purchase recorded before an admin
   could assign the commission is the entitlement.
2. **Ownership in metadata.json.** `MotionLibraryCatalogService::EXCLUSIVE_ASSETS`
   lists exclusive library assets (slug => version directory). The existing
   metadata `ownership` block is extended:
   `{"type": "customer_specific", "site_id": N, "customer_restriction": true}`.
   Sync refuses all of these:
   - an exclusive slug that does not declare such an owner;
   - a public slug that does;
   - an owner site that does not exist;
   - a restriction without `type: customer_specific`;
   - the reserved slug `private`;
   - any ownership change of an already published motion (publish a new slug
     instead; changing it would strand bound sites).
   Exclusive assets are package references like the public ones. Activation
   binds an optional asset only for a site the projection lets read it, so
   only the owner installs it and every other site's skip is audited.
3. **Site-scoped signed URLs.** An exclusive version stores the unsigned
   base `/motion/private/{slug}/{vN}/animation.svg`, which no route serves.
   The manifest signs it for the requesting site:
   `/motion/private/{slug}/{vN}/{site}/{expires}/{hmac}/animation.svg`.
   - The HMAC covers slug, version, site and expiry, under a key derived
     from APP_KEY.
   - The signature is in the path, because customer-site appends runtime
     parameters as a query string.
   - Expiry is aligned to 7-day windows (valid one to two windows), so every
     manifest inside a window has the same URL, checksum and browser cache.
   - `MotionPrivateAssetController` re-checks on every request that the
     motion is published, customer_specific, and owned by the signed site,
     that the files declare that owner, and that an active assignment exists
     when one is used. Revocation is therefore immediate.
   - Every refusal is a 404, and caching is `private`.
   - The public route also refuses any file that declares an owner.
4. **Version binding.** An active bespoke assignment keeps its version
   published, like a bound package item (ADR-079 §7).

**Consequences:**

- customer-site needs no change: the signed reference is root-relative and
  passes `MotionRuntimeManifestReader`, which already accepts any
  `optimized_reference` and resolves it against the platform URL.
- The motion-graphics package (with the library files) is not mirrored into
  customer-site, so exclusive files stay on the platform.
- Adding an exclusive motion needs:
  1. the library files with the ownership block, plus its slug in
     `EXCLUSIVE_ASSETS`, then a release;
  2. a catalog sync (`MotionGraphicsDefaultSeeder`, owner-run in production);
  3. either re-activating the owner site's library package, or a bespoke
     commission, purchase and assignment.
  Exclusive assets follow their metadata `license_tier` on the package path
  like any asset, so an exclusive motion that should not need the premium
  license declares `"license_tier": "free"`.
- Sites with only public motions keep a byte-identical manifest: public
  references are untouched, and the checksum payload is unchanged when no
  bespoke entry exists. Package item order in the manifest remains the
  relation's index order, as before.
- Tests: `ExclusiveMotionDeliveryTest` (owner sees it with full fields and a
  signed URL; another site sees it neither in its manifest nor at any
  re-scoped, forged, unsigned, public or expired URL; bespoke-only manifest
  and immediate revocation; locked premium items next to bespoke assets;
  bespoke-bound version not retired; public
  catalog unchanged; catalog refusals) and `MotionPrivateAssetUrlTest`.

## ADR-082

**Title:** Elementor-Grade Inspector Vocabulary: Tabs, Conditions, Per-Device Values, States

**Status:** Accepted

**Date:** 2026-11-08

**Context:**

- The owner decided to rebuild every Canvas widget to the Elementor Pro
  control standard, starting with the header (`nav_header`). The Phase 1
  discovery (2026-11-07) found four gaps in the editors, not in the widgets:
  - both Inspectors listed every schema field on the Content tab; Style and
    Advanced held only the generic wrapper controls (`block.style`), so a
    widget could not own a Style or Advanced control;
  - `InspectorFieldRegistry` already defined `range`, `spacing`,
    `typography` and a `panel` key, but neither editor rendered or read them;
  - the wrapper's mobile override applied below 480px while every widget's
    own phone layout (the header's burger included) switches below 768px;
  - on the header, the generic Style colours painted a div behind the
    header's own background, so they appeared to do nothing.
- Owner decisions (2026-11-08): breakpoints mobile < 768px, tablet < 1024px,
  desktop ≥ 1024px, with the Inspector's mobile override at 768px;
  `header_modern_v1` is frozen; the header hides the generic Style sections.

**Decision:**

1. **Schema vocabulary** (checked by `WidgetSchemaContract`, normalised by
   `InspectorFieldRegistry`):
   - `panel`: `content` | `style` | `advanced`. Absent means content. It is
     never inferred from the control type, so no existing field moves.
   - `condition`: `{field: value | [values], 'field!': value}`. Every entry
     must hold for the field to show. `true`/`false` compare as booleans and
     `''` matches empty values, lists included.
   - `responsive: true` on `number`, `range`, `select`, `spacing` and
     `typography`. Values are stored as `key`, `key_tablet` and `key_mobile`
     (Elementor's convention, so the existing `logo_height_mobile` key
     already fits). A device without its own value takes its optional
     `tablet_default` / `mobile_default`, else the next wider device's.
     `InspectorFieldRegistry::responsiveValue()` and
     `CanvasControls.read()` resolve the same way. Typography merges
     sub-values down the devices.
   - `state_group` + `state` (`normal` | `hover` | `active`): adjacent
     fields in one tab and section, shown as one control with
     Normal / Hover / Active tabs. Each state keeps its own schema key.
   - New control `shadow` (`{x, y, blur, spread, color, inset}`, clamped and
     colour-checked). `range` takes `unit`, or `units` + `unit_limits`
     (several units store `{size, unit}`).
   - `editor.hideGenericStyle`: `true` or a list of the generic Style
     sections (`typography`, `colors`, `spacing`, `border`, `shadow`,
     `opacity`). Their wrapper device bar hides with them.
2. **One implementation for both editors.** `theme-runtime` ships the
   DOM-free model `resources/js/canvas-controls.js` (`window.CanvasControls`
   plus `editorMixin()`, Node-tested) and the controls in
   `canvas.controls`, `canvas.control-field`, `canvas.control-devices`,
   `canvas.control-states` and `canvas.control-inherit`. The styles are
   self-contained `.tbc-*` because the hosts' Tailwind builds do not scan
   the package. Each host spreads the mixin and supplies one prop writer,
   `ctlSetProp(key, value)`. The field loop renders
   `panelFields(selectedTab)`, so the same loop serves all three tabs. It
   sits above the generic sections on Style and Advanced.
3. **Scope variables in shared partials are prefixed `tbc`.** Alpine
   assigns `this.x = …` to the innermost scope that has `x`. A loop variable
   named `device` swallowed the editor's own `setDevice()` write; the
   browser spec caught it.
4. **Breakpoint.** `BlockRenderer::applyBlockStyle()` emits the mobile
   override at `max-width:767.98px`.
   `LayoutContract::BREAKPOINT_MAX_WIDTH_BY_NAME` is `{tablet: 1024, mobile: 768}`.
5. **Header.** `nav_header` declares `editor.hideGenericStyle: true`. Its
   colours and CTA glow move to Style, sticky/overlay behaviour to Advanced,
   and `logo_height*` become `range`. Conditions hide the search options
   until search is on, the CTA link until it has text, the custom links
   while the site menu is the source, the social icon options until there
   is a link, and the smart / overlay-text options until sticky / overlay is
   on. The glow colour has no condition: a colour alone turns the glow on.
   Storage keys and the renderer are unchanged.

**Consequences:**

- Saved mobile wrapper overrides (`responsive.mobile`) now apply from 767px
  down instead of 479px down. This is the owner's breakpoint decision.
  Widget-specific 480px rules (carousel item counts, grids) are not the
  Inspector override and stay.
- A block saved with wrapper values in a section its widget now hides keeps
  rendering them. The Style tab shows a notice with one action that clears
  them; Advanced-tab keys (`customClass`, `hide*`, `animation*`) stay.
- customer-site's `ElementSchemaRegistry` types `range` (numeric, or array
  with several units), `spacing` / `typography` / `shadow` (array) and a
  responsive field's device keys.
- Step 2 of the header program (CSS variables, inline `<style>` moved to the
  shared sheet) builds on this vocabulary. Every new header control is a
  schema field, never an editor edit.
- Tests: `tests/js/canvas-controls.test.mjs` (13),
  `Adr082InspectorControlsTest` (12),
  customer `Adr082InspectorControlsHostTest` (3), and browser specs
  `adr082-inspector-controls` on both hosts. `BreakpointScaleTest`,
  `Phase2BSharedContractsTest`, `Phase02cCanonicalHarmonizationTest`, the
  customer `CanonicalBlockDefinitionParityTest` and `adr076-canvas-tabs-header`
  follow the change.

**Step 2 (2026-11-08): the header layout as shared, variable-driven CSS.**

- `renderNavHeader()` no longer prints the roughly 40-rule `<style>` before
  every header. The rules moved verbatim (same selectors, so the same
  specificity) to `resources/css/theme-runtime-header.css`.
- Every design value there reads a `--tb-hdr-<name>` custom property whose
  fallback is the old literal, 64 in all; suffix `-m` = phones.
  `Widget\HeaderStyleVars::VARS` registers each with its kind and default,
  and `HeaderStyleVarsTest` parses the sheet so the two stay equal.
- Structure (display, position, z-index, the 768 / 1024 breakpoints) stays
  literal. Rubika's wordmark geometry too. The root's inline `background` /
  `color` from `bg_color` / `text_color` is unchanged, so overlay
  `!important` rules and inline priority behave as before.
- Cascade place: the inline `<style>` came after every stylesheet.
  `theme-runtime::css.blocks` now ends with the header sheet, so both host
  partials ship it unchanged and it still comes after every block rule.
  customer-site's legacy storefront layout (`themes/default/layout`: blog,
  platform products) renders `nav_header` without the block sheet. It now
  includes `theme-runtime::css.header` as the last stylesheet in `<head>`;
  nothing in its `<body>` precedes the header.
- `HeaderStyleVars::style($rootId, $values)` emits one scoped
  `<style data-tb-hdr>#tb-<id>{…}</style>`. It takes known names only,
  checks each value against its kind (lengths with allowlisted units, the
  renderer colour grammar, structured shadows, weights, times) and drops
  defaults, so a default header emits nothing. Step 3 feeds it from the
  new Style controls. customer-site's runtime and host decorator already
  handle a header that opens with its own `<style>`.
- Proof: `tests/browser/header-pixel-parity.spec.ts`. Five configurations
  at 360 / 768 / 1280 plus the open drawer, search popover, mega menu and
  scrolled sticky states give 20 screenshots (image content masked, image
  boxes kept) and 20 computed-style dumps of every element in the header.
  The baseline was recorded on the pre-change renderer (commits 6b7062c0 /
  3204761b) and compared at zero pixels and zero threshold. A 0.05rem
  change fails it.

**Step 3 (2026-11-08): the header Style tab.**

- Schema: the Style tab now has 23 more `nav_header` fields.
  - Container: `min_height`, `padding` (logical spacing), `items_gap`,
    `content_width` / `content_max_width`, `border_bottom_width/color`,
    `header_shadow`, `backdrop_blur`.
  - Menu: `menu_typography`, `menu_gap`, `menu_item_padding_x/y`, the
    `menu_text` and `menu_bg` Normal / Hover / Active groups, `pointer`,
    `pointer_animation`, `pointer_color`, `pointer_thickness`.
  - Responsive fields store `key_tablet` / `key_mobile`; `min_height` has
    `mobile_default` 60.
- `Widget\HeaderStyleVars::forHeader()` is the one mapping from these props
  to CSS. Each device writes only its own value: desktop on `#tb-<id>`,
  tablet and phone in the 1023.98px / 767.98px queries. A phone value goes
  to the sheet's `-m` property where one exists (the drawer's for menu gap
  and item padding). The renderer prints the result as
  `<style data-tb-hdr>` before the header root, which also gets the gate
  list `data-tb-hdr-set` and the pointer classes.
- Gating keeps the default look. A rule that would replace a value the
  default cascade sets elsewhere (e.g. the menu link size and weight from
  theme-runtime-blocks.css) only applies while the header lists the value
  in `data-tb-hdr-set` (`HeaderStyleVars::GATED`), or carries a
  `tb-nav-header--pointer-*` class. Header padding was split into four
  logical-side properties whose fallbacks chain to the old ones. A header
  without Style props still matches the step-2 baseline pixel for pixel,
  with no snapshot changed.
- Menu colours, item padding and the pointer apply from 768px up. The phone
  drawer keeps its own surface (drawer colours are a later step).
  Typography applies everywhere, with the phone's own size in the drawer.
- Active state: the header's script marks the item whose URL is the current
  path with `aria-current="page"` (same origin, trailing slash ignored). The
  marking is client-side, so it is cache-safe and works for the site menu
  too. Active colour, background and a full pointer apply only when an
  Active value is set.
- Backdrop blur is a `::before` layer (`backdrop-filter`) behind the content,
  never on the header itself, which would make it the containing block of
  the fixed phone drawer. The browser test opens the drawer with blur on: it
  stays fixed at full height.
- Hardening found by the tests: negative lengths are allowed only for
  letter-spacing. The gated pointer colour and thickness rules exclude the
  double / framed pointers, which read the same values themselves (a
  higher-specificity gated rule had turned the frame into a solid bar).
  Every pointer modifier rule is prefixed `.tb-nav-header` so a style's own
  rule beats the shared line-pointer rule. An empty (automatic) range
  slider is faded so it never reads as 0.
- Tests:
  - `HeaderStyleVarsTest`: gating, per-device emission, invalid values
    dropped, renderer output.
  - `tests/browser/header-style-controls.spec.ts`: container, typography,
    states and hover, active item, all 7 pointers by shape, blur with the
    drawer.
  - the ADR-082 editor specs on both hosts: Style tab to live preview with
    one scoped style per swap.
  - `header-pixel-parity.spec.ts`: unchanged.

**Step 4 (2026-11-08): header behaviour and the mobile drawer.** Steps 3
and 4 ship as one release.

- Advanced tab, sticky group (all fields need `sticky`):
  - `sticky_devices`: all, desktop + tablet, or desktop only.
  - `sticky_offset`: 0–300px.
  - `scroll_threshold`: 0–600px, default 8.
  - `sticky_shrink`: on by default; `sticky_shrink_height` 36–160px;
    `sticky_logo_scale` 30–100%.
  - The look after scrolling: `scrolled_bg_color`, `scrolled_text_color`,
    `scrolled_shadow`.
- Advanced tab, mobile menu group:
  - `burger_breakpoint`: mobile (default), tablet, always, or never.
  - `drawer_style`: side, full, or dropdown; hidden for never.
  - `drawer_side`: start or end; side style only.
- Style tab, drawer group: `drawer_bg`, `drawer_text`,
  `drawer_divider_color`, `drawer_width` (px or %, side style only),
  `drawer_overlay_color`, `burger_color`, `burger_size`.
- Attributes: behaviour reaches the browser as root attributes, and only
  when it differs from the default:
  - `data-tb-scroll-threshold`, `data-tb-sticky-offset` and
    `data-tb-sticky-on` are read by `blocks/site-header.js`;
    `scrollState()` takes the threshold.
  - `data-tb-burger` is read by the header's inline script. It toggles
    `tb-nav-header-root--drawer` (the phone drawer at 768px and up: tablet
    below 1024, or always) and `--inline` (never: the phone menu is a
    plain row of links). An open drawer closes when the mode no longer
    applies.
  - `noshrink` in `data-tb-header` turns off the shrink rules
    (`tb-hdr-no-shrink`).
- Scrolled colours and shadow, burger colour and size: gated through
  `data-tb-hdr-set`, like step 3. The step-3 desktop menu rules exclude
  drawer mode, so a tablet drawer shows drawer styling, not the desktop
  menu's.
- Shrink height and logo scale: variables in the block sheet
  (`HeaderStyleVars::BLOCK_SHEET_VARS`), with the old values as fallbacks.
  The end-side drawer has its own shadow variable (`drawer-shadow-end`),
  so a start-side shadow value never applies to it.
- Default headers emit nothing new. The pixel baseline is unchanged.
- Toggle defaults: a toggle with no stored value now shows its schema
  default, in both editors (`CanvasControls` mixin `ctlChecked`). The
  step-4 editor test found that `sticky_shrink` (default on) displayed as
  off while its condition already read it as on.
- Tests:
  - `HeaderStyleVarsTest`: values, classes, and behaviour attributes only
    when they differ from the default.
  - `site-header-cart.test.mjs`: the threshold.
  - `canvas-controls.test.mjs`: `ctlChecked`.
  - `tests/browser/header-behavior.spec.ts`, real scroll and viewport:
    - the scrolled look;
    - sticky by device;
    - burger tablet / always / never, including the row layout;
    - drawer full / dropdown / end side;
    - burger colour and size.
  - The ADR-082 editor spec on both hosts: Advanced-tab conditions, the
    toggle default, select defaults, and the live `data-tb-burger`.
  - `Adr082InspectorControlsHostTest`: customer-site validation and
    rendering.
  - The customer `CanonicalListSchemaParityTest` sample generator now
    respects a range's `min`, and writes `{size, unit}` for a multi-unit
    range.

**Step 5 (2026-11-08): header accessibility.** Steps 3 and 4 were released
and deployed (saas 65744f4f, cs f472fef; Hub deployments 317 and 318).

- **Empty menu.** A menu without a link renders no `<nav>`, burger, drawer
  or backdrop. The burger used to be tested after the menu was wrapped in
  its `<nav>`, so it always rendered. The live home page's top-bar header
  (links without labels) showed a second burger that opened an empty
  drawer. Child blocks count as menu content; with no authored links, the
  default menu still renders.
- **Modal drawer.** While the drawer is open, its panel is
  `role="dialog"` + `aria-modal="true"`, named by the menu.
  - The rest of the header turns `inert` after focus moves into the
    drawer, and comes back before focus returns. The burger is left out:
    it stays the pointer's way to close where the drawer leaves it
    uncovered.
  - The Tab trap skips hidden items (the collapsed mega panels), which
    used to let Tab escape.
  - The backdrop button is pointer-only (`tabindex="-1"`).
  - When closed, the panel is plain navigation again.
- **Focus ring.** Every header link and button shows a 2px
  `:focus-visible` ring in its own colour (the header or drawer text,
  which already contrast with their surface), or in `focus_color`
  (`--tb-hdr-focus`).
  - Text inputs keep their own focus style.
  - The CTA keeps the shared `.tb-btn` ring unless the header sets a
    colour (gated).
  - Pointer clicks never match `:focus-visible`, so the mouse look and
    the pixel ratchet are unchanged.
- **Mega menu keyboard.**
  - A focused trigger reports `aria-expanded="true"`; the panel already
    opened on `:focus-within`.
  - ArrowDown moves into the panel, the arrows walk its links, and
    ArrowUp from the first link returns to the trigger.
  - Escape closes the focused panel too, including a desktop panel
    opened by focus, which has no `tb-mega-open` class.
  - The suppress class is set before focus returns, so the trigger no
    longer reports open again.
- **Advanced fields.**
  - `menu_aria_label` (default «منوی اصلی») tells two navigations apart.
  - `focus_color`.
  - `z_index` for the sticky banner (1–9999; the sheet default 900 emits
    nothing). Written as `data-tb-z` and applied to the banner host by
    `site-header.js`.
- **Not added.** A skip link belongs to the page layout, and the customer
  layout already has one (`#canvas-main`). The smart header already
  reveals itself on `focusin`.
- **Tests:**
  - `HeaderStyleVarsTest`: empty menu, safe name, `z-index` and
    `focus_color` only when set, dialog / inert / trap script.
  - `tests/browser/header-a11y.spec.ts`, keyboard only:
    - the drawer dialog, the trap, Escape and focus return;
    - inert, burger and backdrop taps;
    - focus rings on desktop, the CTA ring and `focus_color`;
    - the mega menu arrows and Escape;
    - the empty top bar at three widths;
    - `z-index`.
  - customer-site `header-a11y-axe.spec.js`: axe-core (WCAG 2 A/AA +
    best-practice) on the host's header, closed and open, phone and
    desktop with the mega menu open, and the empty top bar: 0
    violations.
    - It found one real defect, now fixed by `inert`: with the drawer
      open, the phone number sat over the backdrop at 4.35:1.
  - `Adr082InspectorControlsHostTest` covers the step-5 props on the
    customer host.
  - The pixel ratchet is unchanged.

## ADR-083

**Title:** PLATFORM-THEME-SPEC-v1: Solar Token Preset, Seven Neutral Commercial Widgets, Spec Templates

**Status:** Accepted

**Date:** 2026-11-12

**Context:**

- The owner commissioned a commercial energy theme modelled on a live
  reference site (https://oxinsolar.org/), as the handoff
  "PLATFORM-THEME-SPEC-v1". Another agent started step 1 and stopped after
  adding two tokens and the preset file. The token test still pinned the
  old registry, and `--tb-input-bg` had no consumer.
- The reference needs seven section types the registry lacks: a hero with
  stats, zigzag rows and steps, an audience/service grid, an event calendar,
  a product grid with two actions, a course grid with a free/paid filter,
  and a download centre.
- The sticky blur header, mega menu, phone drawer and cart drawer already
  exist (ADR-067, ADR-082).

**Decision:**

1. `solar-dark` token preset under `resources/library/tokens`, read by
   `TemplateLibrary::tokenPresets()`. `primary_hover` and `input_bg` join
   `ThemeTokenNormalizer::COMMERCIAL_TOKENS` (opt-in, consumed with the old
   value as fallback).
2. `CommercialBlockTypes` registers seven palette widgets through
   `registerHostType`, rendered by `CommercialBlockRendererTrait`:
   - neutral defaults, no colour props, `.tb-cx-*` rules painted only with
     `--tb-*` tokens;
   - `section_spacing` defaults to `none` (zero-gap rule);
   - the four list widgets use the Phase 3C `data_source` contract, with the
     manual repeater as the static source and as the fallback while a
     resolver is missing or failing.
3. The shell is configured, not rebuilt. The spec header sets `nav_header`
   to sticky, a 60px scroll threshold, a 14px backdrop blur and three-column
   mega menus. The product grid calls the existing `productActionHtml()`, so
   the server cart's drawer, badges and toasts apply. No `localStorage` cart
   was added.
4. Seven library templates (`solar-*`, `spec: PLATFORM-THEME-SPEC-v1`):
   header, footer, and the pages home, about, courses, products (the shop
   route) and contact. They contain no colours or media and use library
   routes or https links. `TemplateLibrary::ROUTES` gains `about`.
5. Spec: `docs/theme-builder/PLATFORM_THEME_SPEC_V1.md`.

**Consequences:**

- Seven new palette entries on both hosts once customer-site's package
  mirror is refreshed.
- `CommercialWidgetResilienceTest` gates the widgets with a malformed-props
  sweep, XSS probes, data-source fallbacks, cart wiring, blueprint colour
  rules and a stylesheet token check. `TemplateLibraryTest` drives every
  spec template through insert, save and canvas render.
- The header's blur and 60px threshold depend on the `renderNavHeader`
  style wiring that merge `08512a47` dropped (released in `007f2471`). The
  next work unit restores it with a three-way merge, keeping the later
  footer, zero-gap and columns work.
- Owner decisions (2026-11-12):
  - `about` is the dynamic page with that slug; customer-site's
    `EntityLinkCatalog` maps it to `page.show` and adds no fixed route;
  - library download cards ship without a file, never linking a tenant's
    domain;
  - `dual_sale_product_grid` stays in `dynamic`;
  - restoring the merge loss is the next, separate work unit.
- The team section and the course registration form were left out.
