# Theme Productization & Migration Architecture (Phase 4)

> **📋 PROPOSED / PENDING APPROVAL**
> This document is a design proposal, not an implemented or scheduled change.
> **No production dependency exists on any recommendation in this document
> until it is explicitly approved** via a follow-up ADR (per
> `COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` §10.2). The platform's current
> production behavior does not assume, require, or partially implement
> anything described below. Safe to read for context; not safe to build
> against as if it were current architecture.

**Status:** Proposed — architecture and migration design only. No code, migration, or business logic has been changed. Per `COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` §10.2, adopting this requires a follow-up ADR before implementation begins.
**Relationship to prior work:** This document finalizes and supersedes the Theme-specific portions of `PRODUCT_ECOSYSTEM_GAP_RESOLUTION_PLAN.md` (Gap 1 and Gap 3), which proposed the general direction; this document designs the concrete schema, migration strategy, and dependency model those gaps left open.
**Core principle carried through everything below:** there are no permanent "presets." Anything assignable to a customer, listable in Marketplace, or creating customer value is a Theme Product with the full identity contract from `COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` §4 — or it is explicitly, on the record, exempted infrastructure. Nothing is allowed to sit in between.

---

## Phase 1 — Complete Audit

### Inventory: every theme/template/preset that exists today

| System | Count | What it actually is | Where it renders |
|---|---|---|---|
| `Theme` + `ThemeMarketplace` (saas-platform) | **27** seeded via `MarketplaceThemeSeedCommand` (its own docblock claims 25 — already a drift bug) | Full multi-page authored themes: 8 standard pages each (home/about/contact/blog/archive/single/error/search), real one-time + annual/lifetime pricing, category, license_type | Downloaded to customer-site, cached as `Setting.active_canvas_theme_data`, rendered live on the public site by `ThemeCanvasPageController` |
| `SiteThemeTemplate` (customer-site) | Variable, per-install, admin-created | Canvas "save as template" block/section snippets — name/author/tags/thumbnail, plus two dead columns (`version` default `'1.0.0'`, `license_type` default `'free'`) nothing reads | Canvas block-insertion picker only, inside the page builder |
| `TenantThemeResolver` (customer-site) | 20 hardcoded PHP arrays | Site-wide CSS design-token layer (colors/fonts), not a page/layout system | Every layout on the customer install: dashboard, fallback default theme, **and** the installed Theme's canvas layout simultaneously |

### Current purpose of each
- **The 27 `Theme`/`ThemeMarketplace` records** are the platform's real commercial theme product family — sellable, priced, categorized, and this task's actual subject.
- **`SiteThemeTemplate`** is a per-install authoring convenience for reusing a block layout while building a page — not a distributable product.
- **`TenantThemeResolver`** is infrastructure: base color/typography tokens that render underneath whatever Theme (or the fallback default) is active, at a lower CSS priority than the installed Theme's own `global_styles` (verified in `layouts/canvas-theme.blade.php`, where the installed theme's inline `<style>` block is explicitly commented "higher priority than the preset").

### What is prototype-only
Per this task's framing, all 27 seeded themes are treated as prototypes: they were created by a seed command for testing/demo purposes, not through the Theme Product lifecycle this document defines. Concretely, prototype status is not just a label — it is *architecturally true* today:
- **None of the 27 has ever been through a real `publish()` release cycle that snapshots content.** `ThemeBuilderService::publish()` only bumps `version`/`status`/`published_at` on the same mutable row; it never writes to `ThemeRevision`. So whatever "version" a seeded theme claims has no corresponding immutable snapshot anywhere.
- The 3 "premium" themes' motion dependency (`clinic-premium`, `restaurant-premium`, `real-estate-premium`) is a hand-authored, unenforced JSON blob (`settings.motion.required_assets`) — advisory only; the seed command's own comment admits the referenced asset "must be published + assigned... otherwise this block safely renders nothing."
- `supported_modules` is stored twice, independently, in two different formats: `Theme.settings['supported_modules']` (array) and `ThemeMarketplace.supported_modules_json` (JSON string) — already drifted-prone duplication, not a designed two-tier model.

### What conflicts with the Commercial Product Architecture
1. No append-only release ledger exists for any of the 27 themes (contract §3.2/§5 violation, already tracked as gap §9.1).
2. Theme-to-Motion/Module dependency is free text or an unenforced ad-hoc blob, not a structured, versioned compatibility declaration (contract §9.3 violation).
3. `ThemeMarketplace.requirements` (human-authored free text) and the real dependency data it's supposed to describe can silently drift apart — nothing keeps them in sync.
4. `SiteThemeTemplate`'s dead `version`/`license_type` columns are the exact "schema exists, behavior doesn't" trap the contract's §9.7 already flags — left as-is, a future engineer will build on top of them assuming they work.
5. Quality status is a heuristic score, never a real gate (already tracked as gap §11/Gap 4).

### What must be migrated or replaced
- All 27 `Theme` rows: need a real first release snapshot (backfill), and their implicit dependencies (motion assets, modules) extracted into a structured, queryable form.
- `ThemeMarketplace`'s duplicated `supported_modules_json`: retired in favor of one authoritative source.
- Nothing needs to be migrated on the **customer-site** side — see Phase 4 (Customer Safety) for why.
- `SiteThemeTemplate`'s dead columns and `TenantThemeResolver`: not migrated into the product system at all — formally exempted as infrastructure (Phase 5).

---

## Phase 2 — Final Theme Product Model

Every Theme Product is composed of three layers, each with one clear owner, closing the ambiguity that exists today:

### 1. Catalog identity (`Theme` — retained, trimmed)
Identity, ownership, and *current* commercial listing state only: `slug`, `name`, `category`, `site_id` (null = master), `is_marketplace`, `license_type`, `marketplace_badge`. `Theme.version`/`status`/`published_at` become **denormalized pointers only** — always written by, and only by, the release ledger below (contract §5's "ledger wins" rule, applied for the first time to Themes).

### 2. Release ledger (`ThemeRevision`, repurposed — mandatory, not optional)
One immutable row per released version: `theme_id`, `version`, `changelog`, `snapshot` (the full `pages`/`header_blocks`/`footer_blocks`/`global_styles`/`settings` payload, frozen), `released_by`, `released_at`. Written by exactly one designated writer (`ThemeReleaseWriterContract`, per the Gap 1 plan), transactional, monotonic-version-enforced — the same guarantee `ModuleReleaseProducerAdapter` already gives Modules. **This is the theme's actual release history**, not a cosmetic changelog field.

### 3. Structured dependency declarations (`theme_release_dependencies` — new)
One row per `{theme_release_id, dependency_type, dependency_ref, version_constraint, is_required}`:
- `dependency_type`: `module | motion_asset | icon_pack | block_pack` (an open enum — new types add a row shape, not a schema rewrite, so a future Icon Pack dependency needs no new migration).
- `dependency_ref`: the slug/id of the thing depended on (a module slug, a motion asset id, etc.).
- `version_constraint`: a structured range string (`>=1.0.0`, mirroring `module.json`'s existing compatibility syntax), not free text.
- `is_required`: whether the theme functions without it (the 3 premium themes' motion blocks are `is_required = false` today — they "safely render nothing" without the asset — and should stay that way unless a product decision changes it).

This is the generalization of `ModuleDeploymentDependencyResolver`'s pattern the original contract's §9.3 already called for — built here for Themes first, designed so Modules' existing dependency data can migrate onto the same shape later without re-architecting again.

### 4. Marketplace extension (`ThemeMarketplace` — retained, de-duplicated)
Marketplace-only data that doesn't belong on the catalog row or the release ledger: quality scores (until Gap 4's real quality gate replaces them), extended pricing tiers (`annual_price`/`lifetime_price`), screenshots, `sales_count` (distinct from `Theme.installs_count`), `category_tags`. **`requirements` (free text) and `supported_modules_json` are retired as stored fields** — the marketplace page renders its "included assets / required modules / compatibility" section by reading `theme_release_dependencies` for the theme's current published release, live, at request time. This is what "marketplace represents real products, not database records" means concretely: the listing can never say something the release doesn't actually declare, because there is no second copy to drift.

### 5. Quality status (per release, from Gap 4)
A `quality_gate_status` (`pending | passed | failed`) + `evaluated_at` on each `ThemeRevision` row, populated by the real `ProductQualityGateContract` once Gap 4 Phase 4a–4c exist. Until then, the existing heuristic score continues to exist on `ThemeMarketplace` but is explicitly labeled ranking-only, never a publish gate (already the direction Gap 4 set).

### 6. Update strategy
Unchanged from the Gap 1 plan: customer-site's Theme instance is a pointer (`source_theme_id`/`source_theme_version` on `ThemeInstanceRevision`) plus its own edit ledger; adopting a new master release is an explicit customer opt-in step that must preserve customer overrides or refuse with a clear reason (contract §7 guarantee #7) — never silent.

### Worked example — Clinic Premium v1.0
- `Theme` row: slug `clinic-premium`, category `medical`, `is_marketplace = true`.
- `ThemeRevision` v1 (backfilled — see Phase 3): snapshot of its current 8 pages + 3 `module:motion` blocks + global styles, changelog "Initial productized release."
- `theme_release_dependencies` for that release:
  - `motion_asset` / `medical-hero-intro` / no version constraint yet / `is_required = false`
  - `motion_asset` / `medical-process-flow` / — / `false`
  - `motion_asset` / `medical-cta-pulse` / — / `false`
  - `module` / whatever `settings.supported_modules` currently lists / — / `false` (unconstrained until real version data exists — see Phase 3, this must not be fabricated)
- `ThemeMarketplace`: pricing, screenshots, quality score — unchanged shape, `requirements`/`supported_modules_json` no longer separately stored.

---

## Phase 3 — Migration Strategy

**This is explicitly not a rename.** Every existing `Theme` row is missing real release history and structured dependencies; both must be constructed, not relabeled.

### Per-theme migration procedure
For each of the 27 existing `Theme` rows:
1. **Product name / category** — already present and reusable as-is (`name`, `category`, `slug`) — no change needed, these were always real catalog fields.
2. **Initial version** — do **not** invent a false history (no fabricated v0.1–v0.9). The theme's current content becomes release **v1.0.0**, dated at migration time, changelog: *"Initial productized release — migrated from pre-productization prototype state."* Fabricating earlier version numbers would violate the "ledger is truth" principle by asserting a history that never existed.
3. **Dependencies** — extract mechanically where a structured source already exists, extract manually where it doesn't:
   - `settings.supported_modules` / `ThemeMarketplace.supported_modules_json` → one `theme_release_dependencies` row per module slug, `version_constraint = null` (unconstrained) since no real range data has ever been captured — a human/product decision, not something inferable from code, and must not be guessed at migration time.
   - The 3 premium themes' `settings.motion.required_assets` blob → one `motion_asset` dependency row per referenced asset id, `is_required = false` (matches current "safely renders nothing" behavior).
   - `icon_pack` dependencies: none exist today (Icon System isn't built yet) — no rows created, but the schema already supports adding them later with no migration.
4. **Marketplace information** — `ThemeMarketplace` row carried over as-is (pricing, screenshots, category_tags), except `requirements`/`supported_modules_json` are dropped once the marketplace page is confirmed rendering correctly from `theme_release_dependencies` (see rollout order below — do not drop until the read path is cut over and verified).
5. **Migration requirements per theme** — every theme needs step 2 (backfill v1.0.0) and step 3 (dependency extraction); only the 3 premium themes need the motion-specific sub-step; all 27 need the module-dependency sub-step.

### Rollout order (not a single big-bang migration)
1. Add the new tables (`theme_release_dependencies`) and repurpose `ThemeRevision` as mandatory — additive schema only, nothing dropped yet.
2. Run the backfill script (v1.0.0 snapshot + dependency extraction) for all 27 themes, reviewed theme-by-theme for the 3 premium ones since their dependency data carries real product meaning.
3. Cut the marketplace listing page over to read dependencies from `theme_release_dependencies` instead of `ThemeMarketplace.requirements`; verify every one of the 27 listings renders identically or better.
4. Only after step 3 is verified in a real environment: drop `ThemeMarketplace.requirements` and `.supported_modules_json`, and `SiteThemeTemplate`'s dead `version`/`license_type` columns (Phase 5).
5. Wire `ThemeBuilderService::publish()` to the new `ThemeReleaseWriterContract` so every *future* publish produces a real release row — this is what makes productization permanent rather than a one-time cleanup.

---

## Phase 4 — Customer Safety

**Finding: no existing customer install is at risk from this migration, and this is worth stating plainly rather than assuming risk exists.** Customer-site never stores a copy of the master's ledger — it already keeps its own independent `Setting.active_canvas_theme_data` snapshot at install time (captured today, imperfect version-tracking aside) plus its own `ThemeInstanceRevision` edit ledger. This migration only touches the **master** catalog/ledger side on saas-platform; it writes no customer-site data, reads no customer-site data, and does not require any customer-site deployment or migration to execute.

The one real, pre-existing gap this migration does not by itself fix (already tracked in the Gap 1 plan as Phase 1c): `ThemeLicense.installed_theme_version` has no update path today, so it will keep drifting regardless of this migration until that fix ships. This migration does not make that worse, but does not fix it either — it's explicitly out of scope here and remains Gap 1 Phase 1c's job.

**Rule enforced going forward, unchanged from the contract:** Master Theme Product → Customer Theme Instance → Customer Data. A master republish (from the new `ThemeReleaseWriterContract`) never writes to `Setting`/`ThemeInstanceRevision` rows; a customer only receives a new master version through the explicit opt-in update step designed in the Gap 1 plan.

---

## Phase 5 — Remove Architectural Ambiguity

Verified relationship (not assumed): `TenantThemeResolver` and `SiteThemeTemplate` are **not** competing commercial systems and never have been — they are both infrastructure, confirmed by direct trace of what actually renders on a live customer page:
- `TenantThemeResolver`'s tokens render on *every* layout (dashboard, fallback theme, canvas theme) as a base CSS layer; the installed Theme Product's `global_styles` deliberately override it at higher priority in the same document. It is a **shared visual-foundation layer underneath every Theme Product**, not an alternative to one.
- `SiteThemeTemplate` is purely a Canvas-authoring convenience (save/reuse a block snippet while building a page) with no distribution, no marketplace exposure, no cross-install existence at all (no `site_id` — it doesn't even travel between customers).

**Determination:**
- Neither becomes a Theme Product. Both are formally declared **exempted infrastructure** — recorded explicitly in `COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` (mirroring the existing §9.6 Motion exemption pattern) rather than left ambiguous. This satisfies "one clear source of truth": the Theme Product ecosystem is the sole source of truth for anything sellable, assignable, or marketplace-visible; `TenantThemeResolver`/`SiteThemeTemplate` are declared out of that scope, permanently, unless a future explicit business decision graduates one of them (same "do not build for hypothetical future requirements" rule as before).
- `SiteThemeTemplate`'s dead `version`/`license_type` columns are dropped as part of this migration's rollout step 4 — they exist only because someone once assumed this table might become a product; since that decision has now been made explicitly (it will not), the misleading columns should not remain.
- Nothing is removed from `TenantThemeResolver` itself — its hardcoded preset arrays are legitimate design-token infrastructure and remain code-shipped.

---

## Phase 6 — Marketplace Readiness

With Phases 2–3 in place, every Theme Product can genuinely provide every item this phase requires, sourced from a single place each — no field is hand-authored in a way that can drift from what the release actually is:

| Requirement | Source after this design |
|---|---|
| Description, purpose | `ThemeMarketplace.marketplace_description` |
| Version | `Theme.version` (pointer, refreshed only by the release ledger) |
| Changelog | `ThemeRevision.changelog`, per version, real history |
| Screenshots | `ThemeMarketplace.screenshots` (still sourced from the existing Playwright capture pipeline — Gap 4 upgrades its rigor, doesn't change where it's stored) |
| Compatibility | `theme_release_dependencies` (`module`/`motion_asset`/`icon_pack` rows with real version constraints, once captured) |
| Required modules | `theme_release_dependencies` filtered to `dependency_type = module` — read live, not a second stored copy |
| Included assets | `theme_release_dependencies` filtered to `motion_asset`/`icon_pack` |
| Pricing | `Theme.marketplace_price` / `ThemeMarketplace.annual_price`/`lifetime_price` |
| License | `Theme.license_type` |

---

## Quality Rule (carried from Gap 4, applies here unchanged)

Rendering is not completeness. Before any of the 27 migrated themes — or any new one — is treated as a real commercial release, it must pass the same gate Gap 4 designed: automated tests, real rendering validation with a stored baseline, responsive/mobile breakpoints, a real accessibility scan (not just the existing static Blade check), a real performance measurement, and a structured compatibility check. This document does not re-design that gate (see `PRODUCT_ECOSYSTEM_GAP_RESOLUTION_PLAN.md` Gap 4); it depends on it — see Dependency Impact below.

---

## Final Report

### 1. Existing theme/preset inventory
27 seeded `Theme`/`ThemeMarketplace` rows (the real commercial theme product family — docblock claims 25, itself a small drift bug worth a one-line fix), of which 3 ("Clinic Premium," "Restaurant Premium," "Real Estate Premium") bundle unenforced motion-asset references. Separately: `SiteThemeTemplate` (per-install Canvas snippet picker, no distribution) and `TenantThemeResolver`'s 20 hardcoded design-token presets (site-wide CSS foundation layer, renders under every theme).

### 2. Current architectural problems
No append-only release ledger for any theme; theme-to-Motion/Module dependency is either free text or an unenforced ad-hoc blob; `supported_modules` is stored twice in two formats already at risk of drift; `SiteThemeTemplate` carries dead schema fields that invite future misuse; quality status is a heuristic, not a real gate.

### 3. Final Theme Product architecture
Three-layer model: catalog identity (`Theme`, trimmed to identity/ownership/pointers) + mandatory append-only release ledger (`ThemeRevision`, repurposed) + structured, generalized dependency declarations (`theme_release_dependencies`, new — open enum covering modules/motion/icon packs today and future product classes without further schema changes) + a marketplace extension (`ThemeMarketplace`, de-duplicated) that renders live from the release/dependency data instead of storing a second, driftable copy.

### 4. Migration strategy for existing templates
Not a rename: every theme gets a real, honestly-dated v1.0.0 first release (no fabricated history) plus mechanically-extracted dependency rows where structured source data exists (modules, the 3 premium themes' motion assets) and explicitly unconstrained version ranges where no real range data has ever existed (a product decision, not something migration can invent). Rollout is staged — additive schema first, backfill second, cut the marketplace read path over third, drop deprecated/dead columns only after that's verified.

### 5. Required database/service changes (design only, none implemented)
New: `theme_release_dependencies` table. Changed: `ThemeRevision` becomes mandatory-on-publish and the authoritative version source; `Theme.version`/`status` become pointer-only; `ThemeMarketplace.requirements`/`.supported_modules_json` retired; `SiteThemeTemplate.version`/`.license_type` dropped. New service: `ThemeReleaseWriterContract` (from the Gap 1 plan) becomes the sole writer of both the release row and its dependency rows in one transaction.

### 6. Dependency impact on Motion, Icons, Modules, Marketplace
- **Motion:** the 3 premium themes' existing ad-hoc `required_assets` blob becomes real, queryable dependency rows — no change to Motion's own systems required, this only formalizes how Themes *reference* Motion assets.
- **Icons:** no existing dependency to migrate (Icon System isn't built), but the dependency table's open `dependency_type` enum means adding Icon Pack dependencies later requires no further schema change — directly serves the original contract's §9.3 goal.
- **Modules:** module dependencies move from a flat, duplicated, unversioned slug list to structured rows with a version-constraint field ready to be enforced once a real range is captured — but this migration deliberately does **not** fabricate those ranges.
- **Marketplace:** listing pages must be re-pointed to read dependencies live from `theme_release_dependencies` rather than the two fields being retired — this is the one behavioral change in the rollout that needs verification before the old fields are dropped (rollout step 3).

### 7. Recommended implementation order
1. Ship Gap 1 Phase 1a (`ThemeRevision` mandatory-on-publish, transactional, monotonic) — this document's ledger depends on it existing first.
2. Add `theme_release_dependencies` (additive schema).
3. Run the 27-theme backfill (v1.0.0 snapshot + dependency extraction), premium themes reviewed individually.
4. Cut the marketplace listing page over to the new dependency read path; verify all 27 listings.
5. Drop the now-deprecated `ThemeMarketplace` fields and `SiteThemeTemplate`'s dead columns.
6. Record the `TenantThemeResolver`/`SiteThemeTemplate` infrastructure exemption explicitly in the contract.
7. Wire `ThemeBuilderService::publish()` to the release writer so all *future* publishes are real releases, not just the backfilled ones.

### 8. Risks if postponed
- Every additional theme built or sold before this ships adds to the backfill burden and increases the chance a real customer-facing "version 2" is announced with still no actual immutable snapshot behind it.
- The unenforced motion-dependency blob on the 3 premium themes remains a silent-failure risk (a renamed/removed motion asset on the platform side would make a paid theme silently render a gap, with no warning anywhere) until dependencies are structured and checkable.
- `SiteThemeTemplate`'s dead columns remain a live trap for the next engineer who assumes they work, per the contract's own §9.7 pattern — cheap to fix, cheap to keep ignoring, but the cost is not zero and grows the longer it's left unlabeled.
- Continued duplication of `supported_modules` in two formats means the two copies can (and eventually will) disagree, with no mechanism today that would even notice.
