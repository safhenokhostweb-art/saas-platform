# Product Ecosystem Gap Resolution Plan

> **📋 PROPOSED / PENDING APPROVAL**
> This document is a design proposal, not an implemented or scheduled change.
> **No production dependency exists on any recommendation in this document
> until it is explicitly approved** via a follow-up ADR referencing the
> specific contract section it amends (per
> `COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md` §10.2). The gaps described
> (§9.1, §9.8, §9.9, §11) are current, real, and already tracked in the
> contract itself — but the *recommendations* in this plan for closing them
> are not adopted, scheduled, or in progress. Safe to read for context; not
> safe to build against as if it were current or committed direction.

**Status:** Proposed — pending approval. This document does not itself amend `COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md`. Per that contract's §10.2 amendment process, adopting any recommendation below requires a follow-up ADR referencing the specific section it amends. No code, migration, or business logic was changed to produce this plan.
**Governs:** Resolution of contract gaps §9.1 (Theme versioning), §9.8 (entitlement fail-open), §9.9 (ungoverned local theme assets), and §11 (quality gate), all previously recorded under ADR-020.
**Scope:** saas-platform and customer-site.
**Method:** Every claim below was re-verified directly against current code (file:line citations inline), not assumed from the contract's prior description.

---

## Gap 1 — Theme Product Lifecycle

### Current state
- `Theme` (`app/Models/Theme.php`) has `site_id`, `status` (draft/published), `version` (int), `published_at`, plus marketplace fields. **There is no `publish()` method on the model** — mutators like `setPageBlocks()` call `update()` directly, bypassing any lifecycle gate.
- `ThemeCanvasController::save()` writes `pages` straight to the live row — no version bump, no revision, no status check. It is a **separate, unlinked route** from `ThemeBuilderController`'s publish action. A canvas edit is instantly live on the row marked "published," with no gate between them.
- `ThemeBuilderService::publish()` only does:
  ```php
  $theme->update(['status' => STATUS_PUBLISHED, 'published_at' => now(), 'version' => $theme->version + 1]);
  ```
  It does not call `saveRevision()`. `ThemeRevision` (an append-only snapshot table) is only written when a user manually hits a separate "save revision" button — entirely decoupled from `publish()`.
- `ThemeLicense.installed_theme_version` is set once at grant time and **never updated afterward** — no code path writes to it again. The moment any theme is republished, every existing customer license is permanently flagged `isOutdated()` with no reconciliation.
- **Mechanical contrast with Modules:** a Module release is only ever written by `ModuleReleaseProducerAdapter::record()` — a single, transactional, monotonic-version-enforced writer producing an immutable `module_releases` row, which no other code path is allowed to bypass. A Theme has no equivalent: "published" is a status flag on the same mutable row that Canvas already writes to, with no ledger guarding what actually went live.
- There is only **one live row per theme** — no working-copy/live-copy separation. `ThemeRevision.snapshot` is an optional point-in-time backup, not a draft/published pair.

### Business risk if ignored
- "Published version" is not a truthful concept today: a customer can be shown content that was never actually versioned, and a marketplace listing's "version 3" may not correspond to any immutable snapshot. This directly violates the contract's core §3.2/§5 doctrine ("a version is not real until an append-only ledger exists for it").
- `ThemeLicense.installed_theme_version` drift means the platform has no reliable way to answer "what version is this customer actually running" at scale — at hundreds/thousands of installs this becomes an unanswerable support and billing question, not a cosmetic one.
- No rollback path exists: an in-place, unlogged Canvas edit that breaks a theme has no automatic "last known good" to restore, other than an optional manual revision the editor may not have taken.

### Recommended final architecture
Adopt the same shape Modules already use, adapted to Theme's existing tables rather than inventing new vocabulary:

1. **Repurpose `ThemeRevision` as the mandatory release ledger.** Every `publish()` call writes exactly one `ThemeRevision` row inside the same transaction that bumps `theme.version` — never optional, never separable from publish. Enforce monotonic versioning the same way `ModuleReleaseProducerAdapter` rejects `version <= latest`.
2. **Split draft vs. published payload.** Canvas/Builder saves (`ThemeCanvasController::save()`, `ThemeBuilderService::updateTheme()`) write only to a draft-side payload (new `draft_*` columns or a `theme_drafts` table keyed 1:1 to `Theme`). All customer-facing, marketplace, and customer-site consumption reads exclusively from the last published `ThemeRevision` snapshot (or a denormalized `published_payload` refreshed only by the release writer) — never from the draft columns. This is what actually closes the "canvas save is instantly live" hole; the status flag alone does not.
3. **Introduce a `ThemeReleaseWriterContract`** — the single designated writer for `ThemeRevision`, mirroring `ModuleReleaseProducerAdapter`'s guarantees (transactional, monotonic, append-only, sole writer). `ThemeBuilderService::publish()` becomes a caller of this contract, not an inline `update()`.
4. **Customer-instance edits get their own ledger, separate from the master release ledger.** A customer's Theme override (`site_id = <site>`) should track its own edit history distinctly from master publishes — a master republish must never silently overwrite a customer's instance row; adopting a new master version becomes an explicit, auditable opt-in step (mirroring `module:publish` + `composer update`), which must either preserve the customer's overrides or refuse with a clear reason (contract §7 guarantee #7) — never silently discard them.
5. **Fix `ThemeLicense.installed_theme_version`** as part of the same opt-in step: write it only when a customer's site actually adopts a new published version, so "outdated" reflects a real pending update rather than a permanently stale flag.

### Required DB/contracts/services (design only — no migrations written)
- New/changed table: `ThemeRevision` becomes non-optional and gains whatever columns are needed to be a true release snapshot (it is already close: `theme_id, version, label, snapshot, user_id`).
- New: draft-side storage (columns or a `theme_drafts` table) distinct from the published snapshot.
- New: `ThemeReleaseWriterContract` + a `ThemePublishService` implementing it (draft → ledger → pointer, transactional).
- New: a customer-instance edit ledger (can reuse `ThemeInstanceRevision`'s existing shape from customer-site as the pattern, applied server-side to `Theme.site_id != null` rows).
- Changed: `ThemeLicense` gains a real write path for `installed_theme_version`, triggered by the opt-in update step, not by grant-time only.

### Phases
- **1a** — Make `ThemeRevision` mandatory-on-publish, transactional, monotonic-enforced. *(No draft/publish split yet — closes the "publish leaves no ledger" hole first.)*
- **1b** — Split draft vs. published payload; repoint Canvas/Builder writes to draft, all customer-facing reads to published.
- **1c** — Customer-instance override ledger + explicit opt-in update flow + `ThemeLicense.installed_theme_version` sync fix.

### Dependencies
1b depends on 1a (need a real ledger before it's safe to decouple draft/published reads). 1c depends on 1b (a safe customer opt-in update needs something real to diff against).

### Complexity
Medium–High overall. No column needs to be destructively dropped; old columns can be kept and backfilled, so this is additive schema work plus read/write-path rewiring across Canvas, Builder, Marketplace, and `ThemeLicense`.

### Must-have before customer #1
**Yes — 1a and 1b are mandatory.** Without them, "published theme version" is not a truthful concept, which breaks the exact doctrine this contract exists to protect, for the platform's most commercially active product class. 1c can follow shortly after (phase 2) if customer #1 is not yet actively editing an installed theme independently, but should not wait past the first handful of customers.

---

## Gap 2 — Entitlement and Licensing Model

### Current state
- saas-platform already has **two parallel, non-communicating gates**: `EntitlementResolver`/`ModuleEntitlementResolverContract` (checks `platform_licenses` → core → free-assignable → `ModuleLicenseService::hasValidLicense()`) and `App\Adapters\ModuleLicenseAdapter` (bound to `ModuleLicenseServiceInterface`, called by Motion Graphics packages), which **re-implements the same branch logic inline** rather than calling `EntitlementResolver`. Both bottom out in `ModuleLicenseService`, but neither calls the other.
- Theme licensing is a **third, separate mechanism** — `ThemeLicense` is a bare model with static `grant()`/`isOutdated()`, checked ad hoc directly by marketplace controllers, with no resolver involved at all.
- customer-site's `ModuleLicenseServiceInterface` is bound to `NullModuleLicenseAdapter` **unconditionally**, via a plain `bind()` in `AppServiceProvider::register()` — not gated by `environment()`, identical in local, testing, and production. `checkEntitlement()` always returns `allowed = true`; `logDenial()` is a no-op.
- `License::isValid()` in customer-site checks only whole-install validity (domain/expiry/revocation) — there is no per-module or per-asset check anywhere in customer-site outside `NullModuleLicenseAdapter`.
- Motion Graphics is the one product class that avoids this entirely: `MotionRuntimeManifestReader` makes a fresh authenticated round-trip to saas-platform on every uncached read, and its own docblock states entitlement is "re-checked platform-side on every fetch (never cached as a local override)." No environment-conditional service-binding precedent exists anywhere in either repo today.

### Business risk if ignored
This is the single highest-severity item in the whole audit. Any current or future module/theme/motion/icon code running in customer-site that calls `ModuleLicenseServiceInterface::checkEntitlement()` — which is exactly what its name and interface signature invite an engineer to do — is unconditionally told "allowed," in production, regardless of whether the customer ever purchased anything. The platform has avoided real damage so far only because Motion deliberately bypasses this interface with its own live-revalidation path. The moment a future product class is built assuming this interface is a real gate (a completely reasonable assumption from its name), it ships with zero enforcement. At marketplace scale this is a direct, ongoing commercial-loss vector: a lapsed or nonexistent license keeps full access indefinitely.

### Determinations
1. **Is fail-open acceptable anywhere?** Only in a clearly-named, environment-gated local/dev convenience binding — never unconditionally, never in production, never on anything reachable from a real customer route.
2. **Should production always fail-closed?** Yes — unconditionally. This should become a new binding rule in the contract once this plan is approved.
3. **Should development have a separate adapter?** Yes: keep the null adapter (renamed to something self-evidently dangerous, e.g. `AlwaysAllowLocalDevAdapter`) bound only under `environment(['local', 'testing'])`; bind a real adapter otherwise. No existing precedent for conditional bindings exists in either repo, but this is a standard, low-risk Laravel pattern to introduce.
4. **How should Theme/Module/Motion/Icon/Asset share one entitlement system?** Consolidate on `EntitlementResolver` (saas-platform) as the sole resolver — it already has the richest, most complete branch logic and an explicit deny-reason taxonomy. `ModuleLicenseAdapter` should delegate to it instead of duplicating logic. `ThemeLicense`'s ad hoc controller checks should be replaced by resolver calls, using a `product_type` discriminator (already anticipated by the contract's §8 corollary) so every product class resolves through one call shape. customer-site's real adapter must call a single unified platform entitlement endpoint (mirroring Motion's manifest pattern) rather than re-implementing any resolution logic locally — customer-site must never become a second source of entitlement truth.

### Required contracts/services
- Generalize `Product`/`Module` in `EntitlementResolver`'s signature to a `product_type` + `product_id` pair or a shared `Entitlable` interface.
- One new platform endpoint, e.g. `GET /api/entitlement/{product_type}/{product_id}`, mirroring `/api/motion-runtime/manifest`'s authenticated, no-local-trust pattern.
- Environment-conditional bindings in customer-site's `AppServiceProvider` (and saas-platform's `InfrastructureServiceProvider` if it also needs a dev-mode split).

### Phases
- **2a (do first, independent, urgent):** gate `NullModuleLicenseAdapter`'s binding by environment in customer-site. Smallest possible change; closes the live production hole without waiting on anything else.
- **2b (independent of 2a):** collapse saas-platform's two gates — `ModuleLicenseAdapter` delegates to `EntitlementResolver` instead of reimplementing.
- **2c (depends on 2b):** generalize `EntitlementResolver` to a `product_type` discriminator; migrate `ThemeLicense` checks onto it.
- **2d (depends on 2c):** build the real customer-site adapter as an authenticated round trip against the new unified endpoint; bind it for production/staging.

### Dependencies
2a and 2b are independent of each other and of everything else in this plan. 2c requires 2b to be done first (no point generalizing a resolver mid-refactor). 2d requires 2c (the endpoint it calls needs the generalized resolver).

### Complexity
2a = Low (one conditional binding, no schema). 2b = Medium (refactor, no schema). 2c = Medium–High (schema: discriminator columns, contract redesign). 2d = Medium (new endpoint + adapter, but Motion already provides a working template to copy).

### Must-have before customer #1
**2a is mandatory before any customer goes live with module-gated functionality on customer-site** — this is a live gap today, independent of this plan's timeline. 2b–2d are important hardening but not blocking, since Motion already demonstrates a safe interim pattern (live re-validation) works for at least one product class.

---

## Gap 3 — Theme Templates and Presets

### Current state
- **Answer: (A) internal runtime presets, not commercial products — confirmed cleanly separate from the real theme-commerce system.** `MarketplaceThemeSeedCommand` seeds an entirely unrelated `Theme`/`ThemeMarketplace` family (real prices, license keys, purchase orders) and never references either `SiteThemeTemplate` or `TenantThemeResolver`.
- `SiteThemeTemplate` (customer-site): has `name`, `tags`, `author`, `thumbnail_url` — and vestigial `version` (default `'1.0.0'`) and `license_type` (default `'free'`) columns that **no code anywhere writes or reads**. No `site_id` (single-tenant install assumption). No seeder; rows are created only via an admin "save as template" action and consumed only as a block-insertion picker inside the Canvas.
- `TenantThemeResolver`'s 20 presets are hardcoded PHP arrays (CSS design tokens), cached only as a token cache (not a version record). No DB table backs them.

### Business risk if ignored
Low today — these are free and unmarketed. Two concrete risks exist regardless: (1) `SiteThemeTemplate`'s dead `version`/`license_type` columns are exactly the "schema exists, behavior doesn't" pattern the contract's §9.7 already warns about — a future engineer will reasonably assume `version` is real and build an update/marketplace mechanism on top of it, discovering only in production that nothing populates or checks it. (2) `SiteThemeTemplate` has no `site_id` — fine under a strict one-install-per-customer deployment model, but this assumption should be confirmed explicitly, not left implicit, since it would need retrofitting before (not after) any change to that deployment model.

### Determinations
- **Should they become Product entities?** Split by type, not treated identically:
  - `TenantThemeResolver`'s 20 presets are genuinely infrastructure (visual tokens) — fine to stay code-shipped and versionless, but only as an **explicit, documented exemption** (mirroring the contract's §9.6 Motion exemption), never sold individually while code-only.
  - `SiteThemeTemplate` already has most of the shape a real product needs (name/author/tags/thumbnail) and is a better candidate to graduate — but only on an actual business decision to monetize "premium block packs," not speculatively.
- **Should they have versions?** Only if `SiteThemeTemplate` is deliberately graduated into a real product later. Until then, its dead `version`/`license_type` columns should be removed (nothing reads them) or explicitly marked "reserved, unused" to prevent the §9.7 trap.
- **Should they appear in Marketplace?** Not today, for either.
- **How should they relate to Theme Builder?** No change — both are correctly scoped as Canvas/Builder-internal conveniences; that relationship is already right.

### Phases
- **3a** — Explicit exemption decision, recorded in the contract, plus remove (or mark reserved) `SiteThemeTemplate`'s dead columns.
- **3b (optional, business-gated, not architecture-mandatory)** — only if "premium block packs" becomes a real commercial goal: design `SiteThemeTemplate` as a full product class via the contract's §10.3 onboarding checklist.

### Dependencies
3b depends on a business decision, not on 3a. 3a should happen regardless of that decision.

### Complexity
3a = Trivial. 3b = Medium–High, and explicitly deferred — the contract's own §8A rule against building for hypothetical future requirements applies directly here.

### Must-have before customer #1
Only 3a (cheap, prevents a future trap). 3b should not be built speculatively.

---

## Gap 4 — Professional Quality Gate

### Current state
- `ThemeMarketplaceQualityService`'s 5 scoring dimensions are **all pure heuristics over stored JSON/strings** — none render, execute, or measure anything real (e.g. `performance_score` is a page-count/duplicate-ID guess, not a measured metric).
- `CaptureThemeScreenshotsCommand` captures new marketing screenshots (desktop only) but **never diffs against a prior baseline** — it cannot catch a visual regression, only produce images.
- `AccessibilityGovernanceValidationService` does something real (regex/static scan of Blade for `aria-*`, alt text, etc.) but is **not wired into any product's publish/quality-score path** — dead weight from this pipeline's perspective.
- No axe-core, pa11y, Percy, or BackstopJS anywhere in either repo. CI (`frontend-governance.yml`) runs static Blade analysis only — no browser is ever launched in CI.
- Motion Graphics — supposedly the reference implementation — is not meaningfully more mature here: its `PerformanceBudgetDecision`, `ResponsiveSupportDecision`, and `LicenseCompatibilityDecision` are explicit, honestly-labeled stubs (`::notImplemented()`), not real checks.

### Business risk if ignored
The contract's own §11 gate (functional/rendering/responsive/accessibility/compatibility) is currently unenforced for every product class — a broken or inaccessible theme or motion pack can reach `published`/marketplace status today with a passing heuristic score of 100. At scale this is both a customer-trust risk and a legal-exposure risk (WCAG compliance is often contractually required for institutional customers), plus a support-cost risk from unregression-tested visual changes shipped to hundreds of installs at once.

### Recommended final architecture
A real, staged, product-class-generic gate that runs before `published`, built by wiring together pieces that mostly already exist rather than adopting new tooling wholesale:
- **Functional correctness** — existing PHPUnit suites, already real; make passing them a hard gate on publish, not just a CI nicety.
- **Rendering/visual regression** — extend the existing `PlaywrightExtractionService`/`CaptureThemeScreenshotsCommand` pipeline with a stored baseline (keyed to a `ThemeRevision`/release ID from Gap 1) and a pixel-diff step that fails the gate past a threshold, instead of silently overwriting.
- **Responsive/mobile** — extend the currently desktop-only viewport list to standard breakpoints; require all to pass.
- **Accessibility** — wire the existing `AccessibilityGovernanceValidationService` in as a mandatory pre-publish check, complemented by a real headless axe-core scan via the Playwright integration already present (static scanning catches authoring mistakes; axe catches computed/runtime issues — they're complementary, not redundant).
- **Performance** — replace the heuristic `performance_score` with a real Lighthouse/Playwright-based metric (LCP/CLS/TBT) captured in the same screenshot pass.
- **Compatibility** — formalize as a structured field per the contract's §9.3 planned compatibility resolver, checked at gate time rather than merely declared.
- All of the above unify into one `ProductQualityGateContract::evaluate($releaseCandidate): QualityGateResult`, called by each product class's release writer (`ThemeReleaseWriterContract` from Gap 1, `ModuleReleaseProducerAdapter`, Motion's publish path) — one shared gate, not five bespoke ones, consistent with the "one shared layer" doctrine the contract already established for entitlement (§8).

### Phases
- **4a** — Wire existing real pieces together: make `AccessibilityGovernanceValidationService` + the full test suite a hard pre-publish gate; expand screenshots to 3 breakpoints.
- **4b** — Add real pixel-diff visual regression against a stored baseline (depends on Gap 1's release ledger existing, to key baselines to a release).
- **4c** — Add axe-core headless scanning and real performance capture.
- **4d** — Build the shared `ProductQualityGateContract`; migrate Module/Motion/Theme release paths onto it; demote the heuristic scores to marketplace-ranking-only (already the contract's stated intent).

### Dependencies
4b depends on Gap 1 Phase 1a (needs a stable release identity to key baselines to) — sequence Gap 1 before Quality Gate 4b. 4a is independent and can start immediately. 4c depends on 4a/4b's pipeline plumbing. 4d depends on 4a–4c producing real signals, and on Module/Motion adopting a shared contract (a coordination cost more than a technical one).

### Complexity
4a = Low–Medium. 4b = Medium (new baseline storage + diff logic). 4c = Medium (axe-core is a natural fit with Playwright already present; Lighthouse is the newer piece). 4d = Medium–High.

### Must-have before customer #1
**4a is strongly recommended before customer #1** if any near-term commercial launch is planned — it is cheap and closes the largest embarrassment/legal risk (a broken or inaccessible theme reaching a paying customer). 4b–4d are hardening, not launch-blocking, but should not be deferred indefinitely once the marketplace has multiple external themes competing for trust.

---

## Additional gaps discovered during this analysis

1. **Two non-communicating entitlement gates already exist inside saas-platform itself** (`EntitlementResolver` vs. `ModuleLicenseAdapter`) — this predates and is independent of the customer-site fail-open issue, and was found only because Gap 2 required reading both fully. It must be resolved (Phase 2b) as part of the "one entitlement layer" doctrine even though the task only named customer-site.
2. **`ThemeLicense.installed_theme_version` has no update path at all, today** — a live, already-existing bug separate from Gap 1's larger redesign: the moment any theme is republished, every existing customer license is marked outdated permanently, with no reconciliation job or UI found anywhere. Worth an isolated, immediate fix regardless of how the full release-ledger redesign is sequenced.
3. **Motion Graphics' quality gate is not meaningfully more mature than Theme's**, despite the original contract implying Motion was the reference implementation for everything. Gap 4's plan applies equally to Motion's `PerformanceBudgetDecision`/`ResponsiveSupportDecision`/`LicenseCompatibilityDecision` stubs, not only to Theme.
4. **`SiteThemeTemplate`'s missing `site_id`** is worth an explicit, recorded deployment-model confirmation: is customer-site always exactly one Laravel install per customer, permanently? If that assumption ever changes, this table (and any other un-scoped table like it) needs tenant scoping retrofitted before the change, not discovered after.

---

## Summary table

| Gap | Must-have before customer #1 | Complexity | Depends on |
|---|---|---|---|
| 1a — Mandatory release ledger for Theme | **Yes** | Medium | — |
| 1b — Draft/published split | **Yes** | Medium-High | 1a |
| 1c — Customer-instance ledger + license sync | No (soon after) | Medium | 1b |
| 2a — Gate null adapter by environment | **Yes — urgent, live gap today** | Low | — |
| 2b — Collapse saas-platform's two entitlement gates | No (soon after) | Medium | — |
| 2c — Generalize resolver to all product classes | No | Medium-High | 2b |
| 2d — Real customer-site entitlement adapter | No | Medium | 2c |
| 3a — Resolve dead columns / exemption decision | Yes (cheap) | Trivial | — |
| 3b — Graduate SiteThemeTemplate to a product | No — deferred, business-gated | Medium-High | business decision |
| 4a — Wire existing real checks into a hard gate | Recommended | Low-Medium | — |
| 4b — Real visual regression diffing | No | Medium | 1a |
| 4c — Real a11y + performance scanning | No | Medium | 4a, 4b |
| 4d — Unified cross-product quality gate contract | No | Medium-High | 4a-4c |

**Recommended immediate sequence:** 2a (urgent, standalone) → 1a → 3a → 4a → 1b → 2b → 4b → 2c → 4c → 1c → 2d → 4d → (3b only if/when a business decision is made).
