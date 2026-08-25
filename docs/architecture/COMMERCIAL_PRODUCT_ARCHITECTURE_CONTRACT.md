# Commercial Product Architecture Contract

**Status:** Accepted — Permanent Reference — formally adopted by ADR-020 (`docs/project-memory/DECISIONS.md`)
**Authority:** Subordinate to `PROJECT_CONSTITUTION.md` (the workspace's sole permanent-principles authority per ADR-018). This document is the specialized product-ecosystem/commercial-architecture extension of that constitution for saas-platform and customer-site; it does not restate or compete with the Constitution's tenant-isolation, security, deployment, or recovery rules.
**Scope:** saas-platform (all products, marketplace, module ecosystem) and its customer-facing runtime in customer-site
**Applies to:** All future architecture, features, and product decisions
**Supersedes:** No prior document — this contract sits *above* existing ADRs and consolidates them. Where a specific ADR (listed in §10) already governs a mechanism in more detail, that ADR remains the implementation authority; this contract is the constitution it must not violate.

> This is not an implementation plan. It contains no code changes, no migrations, no task list. It defines the permanent rules that all future development — themes, modules, motion graphics, icon systems, blocks, design systems, AI-generated assets, and any future commercial capability — must comply with. Any PR, ADR, or feature that contradicts this contract must either change the contract (via the amendment process in §10) or be rejected.

---

## 1. What This Platform Actually Is

This is not a CMS and not a single-tenant Laravel app with a theming layer bolted on. It is a **multi-product commerce platform**: a factory that produces versioned, licensable, independently-evolving commercial goods (themes, modules, motion packs, and future product classes) and distributes them safely to many customer sites without ever touching customer data.

Three things must be true simultaneously for every product class, forever:

1. **A product has one authoritative definition** (the master), owned by the platform, versioned, and reproducible.
2. **A customer site has an instance** of that product — an assignment/activation record plus whatever the customer configured — that is never the same row as the master and never forked from it by copy.
3. **A commercial right** (license/entitlement) to use a version of that product is tracked independently of both the master definition and the operational assignment.

Everything in this contract exists to keep those three things separate, versioned, and safe to update.

---

## 2. Product Taxonomy

Anything that creates customer value is a **Product**. A Product belongs to exactly one **Product Class**. Current and anticipated product classes:

| Product Class | Status today | Master authority | Customer-instance authority |
|---|---|---|---|
| **Module** | Production, most mature | `module_releases` (release identity) + `module.json` (build-time contract) + `modules` (catalog) | `site_modules` (install/activation) + `module_licenses` (commercial right) |
| **Motion Graphics asset / pack** | Production, best example of the target pattern | `motion_graphics` + `motion_graphic_versions` (`packages/saas-platform/modules/motion-graphics`) | `SiteAssetPackage` / `motion_graphic_deployments` |
| **Theme** | Production, commerce layer mature, versioning layer immature | `Theme` row (`site_id = null`) + `ThemeRevision` | `Theme` row (`site_id = <site>`, override) + `ThemeLicense` |
| **Block** | Code-only today, not yet a product | `BlockRegistry` (static PHP array) | none — blocks ship with app deploys |
| **Icon System** | Not yet built | — | — |
| **Design System** | Not yet built | — | — |
| **AI-Generated Asset** | Partially covered under Motion Graphics (`MotionAiGenerationContract`); no general-purpose class yet | — | — |

Row-by-row detail and the gaps this table implies are in §4 and §9. New product classes MUST be added to this table before their first line of implementation code is written (see §10.3 onboarding checklist).

---

## 3. Core Principles

These restate and sharpen the principles supplied for this task, corrected against what the codebase already proves works (Motion Graphics) and what it already proves goes wrong when skipped (Theme live-edit versioning, see §9.1).

### 3.1 Everything valuable is a product

Every Product Class in §2 must expose the same nine attributes. This is not aspirational — it is the acceptance bar for calling something a "product" instead of "a feature we built." See §4 for the concrete field-level contract and current conformance.

### 3.2 Versioning is mandatory, and "authored" is not "released"

A version number existing in a file or a mutable DB column is not a release. A version is **released** only when a dedicated, append-only release record exists for it, produced by a single designated writer, that customer-facing code is required to read instead of the mutable working copy.

This is already law for modules (`module_releases`, per `module-version-authority-adr.md`) and for motion assets (`published_version`, per `MOTION_GRAPHICS_OWNERSHIP.md`). It is **not yet** law for Themes — a `Theme` row can be edited in place by `ThemeCanvasController::save()` with no forced revision snapshot or version bump. **Rule going forward: every product class must adopt the same `draft → validated/built → published → deprecated` state machine already accepted for modules in `module-release-lifecycle.md`.** No product class gets its own bespoke lifecycle vocabulary.

### 3.3 Master product vs. customer instance — never a fork

A customer website never receives a copy of the master product's authoring data. It receives:
- a reference to a specific **published version** of the master, and
- its own instance record (assignment, activation, configuration overrides).

`Theme.site_id = null` (master) vs. `Theme.site_id = <site>` (customer override) and `TemplateAssetPackage` (master) vs. `SiteAssetPackage` (customer instance) are the two existing implementations of this pattern **on the saas-platform side**, where product authorship and per-site activation records both live. `TemplateAssetPackage`/`SiteAssetPackage` are `app/Modules/MotionGraphics/Models/*` classes in `saas-platform`; they do not exist in, and are never queried by, `customer-site`. **Every future product class must follow one of these two shapes** — either a nullable-owner row on the same table (Theme's pattern) or a separate master/instance table pair (Motion's pattern). A product class that stores customer-specific data by literally copying the master's authoring payload into the customer's row is a contract violation.

**`customer-site` implements this doctrine even more strictly: it never persists the master's authoring data locally at all.** Verified in the `customer-site` repository:
- There is no `Theme` Eloquent model in `customer-site`. Theme *catalog/marketplace* data is fetched live over authenticated HTTP from the platform (`PlatformApiService`, cached by `ThemeMarketplaceController`) and never written back as a master row.
- Customer-specific theme state lives entirely in generic `Setting` key/value rows (`active_canvas_theme_data`, `canvas_draft_data`) plus a dedicated `ThemeInstanceRevision` model, whose `source_theme_id`/`source_theme_version` columns are a **reference** to the master, not a copy of it. `TYPE_CUSTOMER_EDIT`/`TYPE_INSTALL`/`TYPE_UPDATE`/`TYPE_ROLLBACK_SNAPSHOT` are tracked as an append-only local ledger.
- Motion assets are resolved the same way: `MotionRuntimeManifestReader` fetches a signed manifest per request/cache-cycle from the platform, validates its schema version and checksum, and explicitly strips forbidden fields (`source_definition`, `ai_metadata`, `credentials`) before anything reaches the render path — failing to `null` (safe placeholder) on any anomaly rather than rendering unvalidated authoring data.

This is the reference pattern for how any future product class's customer-site consumption path should work: fetch-and-validate from the platform, keep only a local operational ledger, never cache or reconstruct the master's authoring payload.

### 3.4 Customer data is never derived from, nor destroyed by, product updates

Product updates operate on product tables. They never touch customer content tables (posts, orders, users, media, business records). This holds today by table separation (confirmed for module/theme update paths); it must become an **enforced invariant**, not an accident of schema design — see §9.5.

### 3.5 Independent evolution

A product class's version numbers, release cadence, and lifecycle are independent of every other product class's. A Motion pack going v1→v2 must never require a Theme rebuild; a Theme going v1→v2 must never require its assigned Motion pack to change. Cross-product dependencies are expressed as **compatibility ranges**, never as coupled version numbers (module-to-module dependency ranges already exist via `ModuleDeploymentDependencyResolver` — this is the pattern to generalize, see §9.3).

### 3.6 Visual and technical quality gate

No product may reach `published` status without passing a defined quality bar appropriate to its class. Today this bar is partially implemented (thumbnail capture, a heuristic 5-dimension marketplace score) and is **not** equivalent to real visual/functional validation. §9.4 defines the gap and the minimum bar going forward.

### 3.7 Marketplace readiness is a catalog concern, not a release concern

Marketplace-facing fields (name, description, category, pricing, licensing, screenshots, changelog-for-customers) live on the **catalog** record of a product class (`modules` for Modules; the `Theme` row's marketplace fields for Themes), never on the release/version record. This split is explicitly decided in `module-release-lifecycle.md` §4 ("`Module` = Platform Catalog… `module_releases` = Package Identity… these three should never overlap"). **This is now binding for every product class**, not just Modules.

---

## 4. The Product Identity Contract

Every Product Class must expose these nine attributes. This table is the audit checklist for any new product class and the standing scorecard for existing ones.

| Attribute | Module (✅ reference impl.) | Motion Graphics (✅ reference impl.) | Theme (⚠ partial) | Block (❌ not a product) |
|---|---|---|---|---|
| **Identity** | `module_slug` | asset `slug` / group | `Theme.slug`/name | type key in `BlockRegistry` array |
| **Ownership** | platform-owned, `modules` catalog | `ownership_type` (`global`/`customer`) + `site_id` | `Theme.site_id` (null = master) | none — shipped with app code |
| **Version** | `module_releases.version` (authoritative) | `current_version` / `published_version` | `Theme.version` (int, only bumped on explicit `publish()`) | none |
| **Lifecycle** | `module_releases.status`: draft→validated→built→published→deprecated | `draft→review→approved→published→retired` | ad hoc (`is_marketplace` flag only) | none |
| **Compatibility** | `module.json` `compatibility` + `PlatformCompatibilityService` | not yet expressed as a range | `Theme.getPayload()['meta']['required_platform_version']` (free-text, unstructured) | none |
| **Documentation** | `README.md` via `module:create` | doc set under `docs/architecture/` | none per-theme | none |
| **Release history** | `module_releases` rows, changelog column | `motion_graphic_versions` (append-only) | `ThemeRevision` (exists, but not force-written on every edit) | none |
| **Update strategy** | `ModuleUpdateOrchestrator`, 7 mandatory guarantees | draft-never-disturbs-published, rollback via new draft from snapshot | none defined | none |
| **Marketplace capability** | `modules` catalog fields (price, screenshots) | not yet marketplace-facing (bundled/addon modes only) | `is_marketplace`, `marketplace_price`, `license_type`, `marketplace_badge`, `installs_count` | none |

**Rule:** a product class may not ship a marketplace listing (§3.7) until every cell in its row is filled. Themes are commerce-ready but lifecycle/versioning-incomplete (§9.1). Blocks are neither (§9.2) and must not be sold or licensed individually until they graduate out of `BlockRegistry` into a real product record.

---

## 5. Versioning Authority — One Rule, Applied Per Product Class

**A version is not real until an append-only, single-writer release record exists for it, and every customer-facing read path is required to query that record — never the mutable working copy.**

Concretely, per product class, there must be exactly:
- one **working/draft** representation (editable, mutable, not customer-visible) — `module.json` for Modules, the in-progress `motion_graphics` row for Motion, the live-edited `Theme` row for Themes;
- one **release ledger** (append-only, one row per released version, immutable once written) — `module_releases`, `motion_graphic_versions`, and (per §9.1) a required `ThemeRevision` row on every publish;
- one **denormalized "current" pointer** for fast reads, which is a cache and never a source of truth — `modules.version`, `published_version`, `Theme.version`.

When the pointer and the ledger disagree, **the ledger wins**, always. This was learned the hard way for Modules (`module-version-authority-adr.md`'s `forms` module drift example: `module.json` said 1.1.0, `modules.version` said 1.0.0, `module_releases` had zero rows). Do not re-learn it per product class — assume it will happen and build the ledger-wins rule in from day one for every new product type.

**No product class may invent its own version-authority vocabulary.** The `draft → validated → built → published → deprecated` state machine accepted in `module-release-lifecycle.md` is the platform standard. A future product class's lifecycle may skip states that don't apply to it (e.g., a simple asset type might not need `validated` separately from `built`), but it may not rename states or invent parallel ones.

---

## 6. Master → Customer Instance Doctrine

- **Tenant = `Site`.** There is no separate `Tenant` model, and there must not be one introduced casually — `site_id` is the platform-wide tenant scoping key, used consistently across ~47 models today. Any new customer-instance table must scope by `site_id`.
- A master product row/table must be constructible with **no `site_id`** (or an explicit `ownership_type = global`). A customer instance must always carry a **non-null** `site_id` and must fail closed on any cross-site read (Motion Graphics' cross-tenant test in `ThemeMotionBridgeIntegrationTest` is the reference proof pattern — every future product class needs an equivalent test).
- A customer instance is a **pointer plus configuration**, never a copy of authoring data. A customer's Theme override row may contain layout/prop customizations, but it must reference Motion assets, Modules, and other sub-products by `{id, version}` pairs (as `TemplateAssetPackage` already freezes `{asset_id, version}`), never by embedding their source.
- Updating the master must never write to a customer-instance row, and must never require reading customer content tables. Activation/assignment of a new version to a customer is always a **separate, explicit, auditable step** (`installTemplateForSite()`, `module:publish` + `composer update` on the customer side) — never implicit in publishing the master.

---

## 7. Update Safety — Platform-Wide Law, Not a Module-Specific Rule

`update-safety-rules.md` currently frames its seven guarantees as module-specific. They are hereby elevated to apply to **every product class**:

1. Customer overrides/customizations survive every update.
2. Published/distributed files are never silently overwritten — drift must be detected, not assumed away.
3. Every migration or state transition must have a defined reverse (or be explicitly marked irreversible with a documented compensating action).
4. A failed update auto-rolls-back to the last known-good state; it never leaves a product half-migrated.
5. Every release carries a schema/contract version so a customer's installed version and the platform's expectations can be diffed before applying an update.
6. The published package/release is the sole canonical authority for what a customer receives — no code path may reconstruct "what version this should be" by inference.
7. Customer runtime customizations (props, toggles, overrides) are preserved across a master version bump, or the update is refused with a clear reason, never silently discarded.

Any new product class's update path must satisfy all seven before it can be marked `published`-capable. Themes do not yet satisfy #2–#5 (§9.1); this contract does not block current Theme commerce, but it blocks Themes from claiming "safe update" in marketplace copy until closed.

---

## 8. Marketplace, Licensing, and Entitlement — Use What Exists, Do Not Rebuild Per Product

Two commercial infrastructures already exist and are hereby declared **the only** commercial infrastructure for the platform — no product class may build a parallel purchase, license, or entitlement system:

- **Commerce/purchase layer:** `ThemePurchaseOrder`, `ThemeLicense`, `ThemeBundle`/`ThemeBundleOrder`, `ModulePurchaseService`, `ModuleLicenseController`, `LicenseServerService`.
- **Entitlement/runtime-gating layer:** `platform_licenses` + `module_licenses` as commercial truth; `ModuleEntitlementResolverContract` as the **sole** runtime commercial-decision service (per `ENTITLEMENT_SOURCE_OF_TRUTH.md`); tenant-side `licenses.modules` as a strictly derivative, fail-closed cache (per `CUSTOMER_PROJECTION.md`); `site_modules.is_active`/deployment state kept strictly separate from commercial right (per `MODULE_ACTIVATION_VS_ENTITLEMENT.md` — a failed deployment must never revoke a paid license, and a valid license alone must never activate a runtime without an explicit operational step).

**Rule for every future product class:** its commercial right is a row in the existing license tables (or a straightforward extension of them — e.g. a `product_type` discriminator), gated at runtime through the existing entitlement resolver contract. Motion Graphics already proves this works: it deliberately added **zero** new entitlement machinery and reused the module-level `ModuleLicenseAdapter` gate. That is the model to replicate, not the exception.

**Corollary:** a product class does not get to ship its own "is this customer allowed to use this" check. If the existing entitlement contract is too coarse for a new product class's needs (e.g., per-asset licensing instead of per-module), the contract itself must be extended — via ADR — not bypassed locally.

---

## 8A. No Classic Development

Never build any of the following as the final answer to a request, even when it is the fastest path to closing a ticket:
- a hardcoded, single-customer solution;
- a one-off implementation with no product identity;
- temporary or "just for now" architecture;
- an unversioned asset;
- an isolated system with no relationship to the entitlement/marketplace layers in §8;
- a manual, human-run update process for something customers depend on.

Every proposed solution must be evaluated against one question before it is built: **"Can this be sold, updated, and maintained commercially?"** If the honest answer is no, either the approach must change or the exception must be recorded explicitly (as §9.6 and §9.9 do for existing cases) — never left implicit. An undocumented exception is indistinguishable from an oversight to the next engineer who finds it.

---

## 9. Conflicts With Current Implementation

These are real, code-verified gaps between this contract and what exists today. None of them block current operation; all of them are backlog items future work must close before the affected product class can be called fully contract-compliant.

### 9.1 Theme versioning is not automatic
`ThemeCanvasController::save()` and `ThemeBuilderService::updateTheme()` mutate a Theme's `header_blocks`/`footer_blocks`/`pages` in place. Only an explicit `publish()` call bumps `Theme.version` or is guaranteed to write a `ThemeRevision`. This means a Theme can accumulate live, unversioned edits between publishes, and `ThemeLicense.installed_theme_version` can silently drift from actual content — the exact failure mode `module-version-authority-adr.md` already diagnosed and fixed for Modules. **Recommendation:** every `save()` that mutates published-affecting content should either force a draft/working-copy distinction (matching Motion's `current_version` vs `published_version` split) or force a revision snapshot per save, not per publish.

### 9.2 Blocks are code, not products
`BlockRegistry` is a static PHP array shipped with app deploys — no version, no changelog, no independent release, no licensing. This directly conflicts with §3.1 ("everything valuable is a product"). Blocks currently derive their commercial gating from the Module they belong to, not from themselves. **This is acceptable only as long as blocks are not sold or licensed individually.** The moment a "premium block pack" becomes a commercial idea, blocks must first graduate into a real product record (their own `module.json`-equivalent + release ledger) — do not bolt licensing onto the static array.

### 9.3 No unified cross-product version concept
Three non-fungible "version" ideas coexist with no cross-reference: `module_releases.version` (module release authority), a Motion asset's `published_version` (per-asset, independently numbered), and a Theme's `Theme.version` (int, publish-triggered). `TemplateAssetPackage` freezes `{asset_id, version}` pairs, but nothing platform-wide expresses "Theme v3 requires Motion pack v2+" as a structured compatibility range the way `ModuleDeploymentDependencyResolver` already does for module-to-module dependencies (`["core-users", "core-billing>=1.0"]`). **Recommendation:** generalize `ModuleDeploymentDependencyResolver`'s dependency-range mechanism into a product-class-agnostic compatibility resolver, and require every product class's release record to declare its dependencies the same way `module.json` does.

### 9.4 Visual/quality validation is thumbnail generation and a heuristic score, not real validation
`CaptureThemeScreenshotsCommand` produces marketing thumbnails via Playwright — it does not diff against a baseline or catch regressions. `ThemeAiPipelineProofCommand` / `ThemeMarketplaceQualityService::scoreAndPersist` produces a 5-dimension heuristic score (design/commerce/performance/completeness/marketplace) — it does not run accessibility (WCAG/axe), real cross-browser rendering, or responsive-breakpoint checks. §3.6/§11 define the real bar; closing this gap is future work, not something this document can retroactively satisfy.

### 9.5 "Customer data is never touched" holds by construction, not by enforcement
No code path was found where a theme or module update writes to customer content tables (posts, orders, media) — but this is true because the tables are separate, not because a guard or automated test asserts it. There is no regression test that would catch a future change accidentally coupling a product-update path to a customer-content table. **Recommendation:** add an explicit, permanent test (or static analysis rule) per product class's update path asserting it never touches the customer content table set.

### 9.6 Motion Graphics runs a structurally separate release pipeline from every other module
Per `module-package-contract.md`, the `motion-graphics` package is explicitly exempt from the standard `module.json`/`module_releases` pipeline (`type: "standalone-package"`, `distributable: false`, no `modules` DB row, `ModuleReleaseProducerAdapter` returns `STATUS_SKIPPED_NO_MODULE_ROW`). It has its own fully independent versioning/publish lifecycle (`MOTION_GRAPHICS_OWNERSHIP.md`). Two release lifecycles exist on the same platform with zero cross-references. This is tolerable today because Motion's own lifecycle is arguably *more* rigorous than the module one — but it means "how do I check what's published for this product" has two different answers depending on product class. **Recommendation:** document this exemption explicitly as a permanent, intentional exception in `module-package-contract.md` (not just implied by a status enum value), rather than letting it read as an oversight.

### 9.7 Schema exists, behavior doesn't — a repeated pattern
Three unrelated features share the same shape: declared in schema/manifest, never implemented or exercised.
- Module uninstall (`"uninstall": {"type": "soft"|"hard", ...}` in `module.json`) — `ModuleUninstallStrategyInterface` has no implementation.
- Release `deprecated`/`validated` statuses — no command sets either.
- Motion's `addon`/`required` commercial modes (vs. the only-exercised `bundled`) — declared, unused.

None of these block anything today. They are flagged together because the pattern itself is a risk: a schema field that looks load-bearing but silently does nothing is exactly the kind of thing a future engineer will build on top of, assuming it works. **Recommendation:** track these as one visible backlog list (not scattered across docs) so nobody discovers the gap by shipping a customer-facing feature on top of it.

### 9.8 Module entitlement on `customer-site` is fail-open, contradicting the documented fail-closed doctrine
`docs/architecture/entitlement/CUSTOMER_PROJECTION.md` states the tenant-side entitlement cache is "strictly derivative... fail-closed." The actual binding in `customer-site` is `NullModuleLicenseAdapter` (`app/Services/NullModuleLicenseAdapter.php`, bound in `AppServiceProvider.php`): `checkEntitlement()` unconditionally returns `allowed = true` and `logDenial()` is a no-op, with an explicit doc comment stating "customer-site never denies access." Whole-license validity (`License::isValid()`) does fail closed on domain mismatch/expiry/revocation, but that is a coarser gate than per-module entitlement, and does not make the per-module claim true. **This must be resolved explicitly, not silently patched:** either (a) `CUSTOMER_PROJECTION.md`'s wording is wrong and per-module entitlement is intentionally delegated entirely to the platform-side API gate (as Motion Graphics' manifest-fetch path already does — no module code path in `customer-site` runs without a prior authenticated platform round-trip), in which case the doc must be corrected to describe what actually happens, or (b) there exists a local module code path that runs without any such round-trip, in which case this is a real fail-open gap and must be closed before any module class relies on `customer-site`-local enforcement. Flagging this as the highest-priority open item in this contract pending an owner decision — do not build new product classes assuming `customer-site` enforces entitlement locally until this is resolved.

### 9.9 Local, ungoverned theme assets exist in `customer-site` outside this contract entirely
`SiteThemeTemplate` (a full local theme-template library — `blocks`/`global_styles`/`demo_data` JSON, its own migrations) and the 20 hardcoded presets in `TenantThemeResolver::presets()` (`app/Services/ThemeEngine/TenantThemeResolver.php`) are versionless, have no master/instance split, no lifecycle, and are invisible to the marketplace/licensing layer described in §8. They are not "themes" in the §2/§4 product sense — they are local code-shipped conveniences, structurally identical to the `BlockRegistry` problem in §9.2. **Recommendation:** either explicitly exempt them here as customer-site-local, non-commercial conveniences (mirroring the §9.6 Motion exemption pattern — documented as intentional, not discovered-by-accident), or graduate them into the platform's Theme product pipeline before any of them is marketed or sold individually.

---

## 10. Governance

### 10.1 This document's authority
This contract is binding for all new product-class work. Existing ADRs listed below remain the detailed implementation authority for their mechanism; this contract must never be read as superseding their specifics — only as the constitution their specifics must not violate.

Reference set (do not duplicate their content here — read them):
- `docs/architecture/module-package-contract.md`
- `docs/architecture/module-release-lifecycle.md`
- `docs/architecture/module-version-authority-adr.md`
- `docs/architecture/module-release-changelog-convention.md`
- `docs/architecture/distributed-module-delivery.md`
- `docs/architecture/update-safety-rules.md`
- `docs/architecture/MOTION_GRAPHICS_OWNERSHIP.md`
- `docs/architecture/MOTION_GRAPHICS_PRODUCT_MODEL.md`
- `docs/architecture/entitlement/*.md`
- `docs/theme-builder-motion/*.md`

### 10.2 Amendment process
A change to this contract requires an explicit ADR that references this document by name, states which section it amends, and states why. A feature PR is never sufficient grounds to silently deviate from a rule here — if a feature needs to deviate, the contract is amended first.

This document is not fixed forever, and it is not this document's job to pretend it is complete. Any agent or engineer who, during unrelated work, discovers a missing architecture rule, a better SaaS pattern than one written here, a risk this contract does not cover, or a direct contradiction between this contract and what the code actually does, must:
1. **Stop** before implementing around the gap.
2. **Explain the finding** — cite the file/section in conflict, concretely, the way §9 does.
3. **Propose an amendment** to this document.
4. Update this contract only after that amendment is approved — never as a silent side effect of an unrelated task.
§9.8 and §9.9 are the working example of this process: findings recorded and flagged for a decision, not silently resolved by the discovering agent.

### 10.3 New product class onboarding checklist
Before writing implementation code for a new product class (icon systems, design systems, general AI-generated assets, or anything not in §2's table), the following must exist first, in this order:
1. A row added to the §2 taxonomy table naming its master authority and customer-instance authority.
2. A completed §4 identity-contract table row (identity, ownership, version, lifecycle, compatibility, docs, release history, update strategy, marketplace capability) — even if some cells are deliberately deferred, they must be marked deferred, not silently absent.
3. Confirmation it reuses the existing entitlement/commerce layer (§8) rather than introducing a new one, or an ADR justifying why it cannot.
4. Confirmation its update path satisfies the seven guarantees in §7, or an explicit, time-bound waiver.
5. A defined quality gate appropriate to the product class before it may reach `published`/marketplace-listed status (§11).

### 10.4 Precedence on conflict
If a future ADR conflicts with this contract without going through §10.2, this contract wins and the ADR must be revised.

---

## 11. Minimum Quality Gate for `published` Status (all product classes)

A product version may not transition to `published` unless it has passed, at minimum:
- **Functional correctness** — the platform's standard automated test suite for that product class passes.
- **Rendering validation** — actual rendered output captured (not just "the code ran"), reviewed against the previous published version for unintended visual change, for any product class with a visual surface (Themes, Blocks, Motion, Icons).
- **Responsive/breakpoint check** — for any product class rendered in a browser.
- **Accessibility baseline** — automated WCAG-level scan (e.g., axe-core) for any customer-facing visual product; this is currently absent platform-wide (§9.4) and is the single most important gap this contract flags for near-term closure.
- **Compatibility declaration** — a structured (not free-text) statement of what platform/module/product versions this version requires, per §9.3.

A heuristic marketplace-appeal score (as `ThemeMarketplaceQualityService` currently produces) is a **marketplace ranking signal**, not a substitute for this gate.

---

## 12. Pre-Task Compliance Checklist

Read this document before starting any implementation task touching themes, modules, motion, icons, blocks, design systems, or any future product class. Before writing code, check the proposed approach against:

1. Does this create something reusable, or a one-off tied to a single customer? (§8A)
2. Is it versionable through an append-only release ledger, not just a mutable column? (§5)
3. Does it fit an existing product class's identity contract (§4), or does it require a new taxonomy row and onboarding checklist first (§10.3)?
4. Does it protect customer data and customer-owned overrides from being touched or destroyed by a product update? (§3.4, §6, §7)
5. Does it avoid inventing a parallel commerce, licensing, or entitlement mechanism instead of reusing §8's existing layer?
6. Does it avoid creating a migration the platform will be forced to redo later because the product/customer-instance split was skipped now?

If any answer is no, **report the conflict before coding** — following the process in §10.2 — rather than proceeding and documenting the gap afterward.

---

## 13. Summary of Binding Rules

1. Every product class must eventually satisfy every row of §4's identity contract before it can be marketplace-listed.
2. A version is only real once it exists in an append-only release ledger; the ledger always wins over any cached pointer.
3. Every product class uses the same `draft → validated/built → published → deprecated` lifecycle vocabulary.
4. Master and customer instance are never the same row and never a copy; customer instances hold pointers + config, not authoring data.
5. Customer content tables are never written to by a product update, and this must be test-enforced, not assumed.
6. All seven update-safety guarantees in §7 apply to every product class.
7. There is exactly one commerce layer and one entitlement layer platform-wide; no product class builds its own.
8. Cross-product dependencies are compatibility ranges, never coupled version numbers.
9. No product reaches `published` without passing the §11 quality gate.
10. This contract can only be amended by ADR, never by silent deviation.
11. Never ship a hardcoded, one-off, or unversioned solution as the final answer — every solution is evaluated against "can this be sold, updated, and maintained commercially?" (§8A).
12. Before any implementation task, run the §12 checklist; a "no" answer is reported before coding, not discovered after.

**All future architecture work in this repository must reference this document.**
