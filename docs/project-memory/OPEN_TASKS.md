# OPEN TASKS
> Current epics, features, tasks, status, dependencies, and acceptance criteria.
> Last Updated: 2026-11-17

---

## COMPLETE: ONBOARDING PROFILE THEME INSTALL — RELEASE 277 DEPLOYED — 2026-11-17 ✅

Production had an empty master theme catalog (clean-slate purge
2026-11-01), so `solar-industry` was never installed. `theme-release:backfill`
had nothing to release, and the wizard offered only «بدون قالب».

| Item | Status |
|------|--------|
| Fix: `theme-catalog:rebuild --slug=`, wizard reason + fix commands | ✅ RELEASED — `c8a6dcf160b2e16b14a2ac052d6909eabaadd928` (source `3ab12386`, base `edcad535`) |
| Hub release | ✅ 277 (`f3143abc-e6b7-4fb6-8e9b-d019d7fd5910`) — ready 2026-11-17 10:35:55 UTC |
| Artifact | ✅ 306 (`b0954918-40f1-47d2-9300-5c8a22cce10f`) — available, verified; sha256 `d328912e31941a27780b1782f0ef5b9077ffd866dd174f17895aff4b47566a9c`; evidence `83a1a8f14c31def59d75f2246c7a2fbad984636642fad0b08da9973468b7c302` |
| Deployment | ✅ DEPLOYED — deployment 360 (`ddab53b9-fc39-4680-8e15-1b6501ea719a`), succeeded 2026-11-17 11:34:03 UTC, HTTP health verified; server manifest `c8a6dcf1` |
| Post-deploy data step | ✅ `theme-catalog:rebuild --slug=solar-industry` (exit 0), then `theme-release:backfill --slug=solar-industry` (exit 0, 1.0.0 released) |
| Result on production | ✅ theme id 61 «قالب صنعت برق», release 7 v1.0.0 published, quality report 1 passed, commercially eligible; offered in the wizard; solar_energy profile available (theme 61, kit 63) |

Full descriptor: `.deployment/releases/onboarding-profile-theme-install.json`.

Release 276 (site delete fix, `edcad535`, artifact 305) is DEPLOYED as
deployment 358. On production the migration ran and all five history tables
now use `ON DELETE SET NULL`.

---

## COMPLETE: UNIFIED ONBOARDING WIZARD (ADR-088) — RELEASED & DEPLOYED — 2026-11-17 ✅

Owner brief 2026-11-16: one wizard for customer, sites per mobile, domain,
licence and kits, template and provisioning; no manual server steps.

| Component | Status |
|-----------|--------|
| `admin/onboarding` wizard + `SiteOnboardingService` (mobile identity, domain policy, kit/vertical rules, industry profiles) | ✅ DONE — commits `13cc8ec9`, `36fc0471` |
| Pipeline: `APPLY_SITE_ENTITLEMENTS`, `CLEAR_APPLICATION_CACHE`, real `INSTALL_THEME`, provision secret in `.env`, owner attachment fix | ✅ DONE |
| Post-install theme push (`PushSiteTemplateJob`, retry on the installation page) | ✅ DONE |
| Legacy customers/create, sites/create, sites/wizard, customer-deployment wizard → 301 | ✅ DONE |
| Owner sign-off (licensing behaviour: kits granted at onboarding, Constitution §9) | ✅ APPROVED by the owner 2026-11-17 |
| Release | ✅ RELEASED — saas-platform `6624063f` (Hub release 274, artifact 303); customer-site `d205f32e` (release 275, artifact 304) |
| Deployment | ✅ DEPLOYED — Hub deployment 354 (saas-platform) and 357 (customer-site) SUCCEEDED; server manifests show both commits |
| Production prerequisites (`solar_industry_kit` migration, `theme-release:backfill`, the same `CUSTOMER_PROVISION_SECRET` in both `.env` files) | ✅ DONE by the owner; secret equality checked by hash |

**Release note:** the work reached `main` by a direct merge and was delivered
with an earlier version of `scripts/deploy-release-pipeline.ps1`, not through
`release-prepare`. As a result, the Hub metadata for releases 274 and 275 lists only that
script in `changed_files`, and its `test_results` are the Hub's own
exact-commit checks. The test evidence is the work unit's own run
(73 onboarding and pipeline tests, plus a full-suite sweep classified in
`docs/KNOWN-TEST-FAILURES.md`) on `36fc0471`. That commit is code-identical to
`6624063f` except for that script. The script was then rewritten to wrap
`release-prepare` (fail-fast on unowned changes, no fabricated test
results, `base_sha` = the production HEAD after fetch).

---

## OPEN: SOLAR CALCULATOR MODULE (ADR-085) — 2026-11-14

Owner brief: a site-specific solar calculator (sizing, itemized quote,
editable formula table, exchange-rate pricing, lead gate) for the «قالب صنعت
برق» customer, invisible to every other tenant.

| Component | Status |
|-----------|--------|
| customer-site `app/Modules/SolarCalculator` (formula engine, settings, calculation, lead + CRM capture, quote view, admin: leads/formulas/settings) | ✅ BUILT — branch `feat/solar-calculator` |
| Generic exchange-rate store `App\Services\ExchangeRates` (manual + SSRF-guarded HTTPS JSON provider, hourly `exchange-rates:sync`) | ✅ BUILT |
| Canvas widget `module:solar_calculator` (host-only, license-gated palette entry) | ✅ BUILT |
| Platform catalog row `solar_calculator` (`marketplace_visible=false`, `license_required=true`) | ✅ BUILT — branch `feat/solar-calculator` |
| Release (saas first, then customer-site) | ⏳ via work-unit delivery workflow |
| Activation: assign `solar_calculator` to the customer's license, set the USD rate, add the widget to the calculator page | ⏳ OWNER |

**Follow-up:**
- Server-side PDF: dompdf cannot join Persian letters; the quote is a print
  view («دانلود PDF» = browser Save as PDF). A true PDF file needs mPDF
  (dependency decision for the owner).
- A platform-wide exchange-rate feed would be one more
  `ExchangeRateProvider`; the admin page's gate (`module:solar_calculator`)
  widens when a second module reads rates.

## OPEN: CLEAN SLATE PURGE — LEGACY THEMES, PAGES, MENUS — 2026-11-01

Owner brief: purge every legacy theme, page, header/footer and menu in both
repositories (no live customers) and start from the commercial Canvas only.

| Component | Status |
|-----------|--------|
| `themes:clean-slate` (platform): themes, releases, licenses, assignments, marketplace, revisions, quality reports, templates, packs, bundles, site menus; `sites.theme_id` detached | ✅ DONE — `ThemeCleanSlateCommandTest` 7/7 |
| `canvas:clean-slate` (customer-site): active/draft/backup/rotating theme settings, `theme_instance_revisions` + their media usages, pages, page/section templates, menus; then installs the empty canvas (`CanvasBlankBaselineSeeder`) | ✅ DONE — `CanvasCleanSlateCommandTest` 5/5 |
| Recovery path (constitution §5): verified JSON-lines snapshot + SHA-256 manifest before any delete; `--restore` into empty tables; `--confirm-database` required in production | ✅ DONE — shared `App\Support\DataPurge` (identical file in both repos) |
| Admin marketplace "راه‌اندازی" button (re-seeded 22 legacy themes) | ✅ REMOVED (route, action, button) |
| Orphan seeders `KhabShimanaTabletThemeSeeder`, `ThemeBuilderSampleThemesSeeder` | ✅ DELETED (their image assets kept) |
| Local dev DBs `laravel` / `customer_site` | ✅ PURGED 2026-11-01; purge → restore → purge digests identical in both; e2e fixtures reseeded (#216/#217) |
| Production databases | ⏳ OWNER DECISION — not run |

Ids are reset except `themes` and `theme_releases` (platform): the kept
`storage/app/public/theme-media/{theme id}/` folders and customer-site
provenance refer to those ids.

**Kept on purpose:** block types and renderers (`foodmart_*`, `sleepshiman_*`;
Canvas overhaul owns them), media files (`theme-media/**`,
`theme-assets/foodmart/**`, `customer_media`), colour/token presets
(`theme_profiles`, preset slugs, `theme_settings`), the admin module menu
(`menu_mode`, `menu_custom_menus`), and the release-gate fixtures
(`theme-baselines/foodmart/integrity-manifest.tsv`, `tests/Support/FoodMart/*`,
customer `storage/app/*-snapshot.json`, the Universal QA export fixture).

**Follow-up (needs the Canvas overhaul owner, then this unit):**
- Delete the remaining legacy catalog seeders/commands — FoodMart,
  SleepShiman, Universal QA, SalesSupport, ArianaMultiPlus seeders and
  `marketplace:seed` / `nexus:seed` / `acool:seed` / `malltina:*` /
  `theme-catalog:rebuild` — together with the ~30 platform tests that seed
  through them, once the template library replaces them as test fixtures.
- Customer-site legacy page builder presets (`config/builder-section-presets.php`,
  `item_schema` in `config/builder-elements.php`) belong to the old page
  builder, not Canvas; retiring it is a separate decision.

---

## COMPLETE: ARIANA MULTIPLUS — PERSIAN MULTIPURPOSE THEME — 2026-10-12 ✅

**Status:** READY_FOR_FINAL_REVIEW_LOCAL.

| Component | Status |
|-----------|--------|
| `database/seeders/ArianaMultiPlusThemeSeeder.php` (slug `ariana-multiplus`, v1.0.0) | ✅ DONE — canonical block types only |
| Five professional slider families (slider, product_slider, category_slider, testimonials_slider, client_logos) | ✅ DONE |
| Vazirmatn + fa-IR + RTL global styles/locale contract | ✅ DONE |
| Mobile-number-only identity — zero email in the persisted document | ✅ DONE (contact_split excluded; pricing copy fixed) |
| Virtual imagery via `ThemePlaceholderService::svgPlaceholder` | ✅ DONE |
| Published + honest `ThemeMarketplace` row | ✅ DONE |
| Canonical source registration in `ThemeCatalogRebuildCommand` | ✅ DONE |
| `ArianaMultiPlusThemeSeederTest` | ✅ 6/6 (157 assertions) |
| `ThemeCatalogRebuildCommandTest` | ✅ 8/8 |
| `theme-release:backfill` (commercial eligibility) | ⏳ NEXT — requires explicit authorization |
| Commit/push/deploy (ADR-019) | ⏳ NOT AUTHORIZED / NOT RUN |

**Acceptance criteria met:** all blocks are canonical and render
non-empty through the real shared renderer; every prop matches the
canonical schema so the customer can edit everything later in Canvas;
the theme is deterministic and idempotent through
`theme-catalog:rebuild`.

---

## OPEN: COMMITTED CANVAS EDITOR/PREVIEW BASELINE GAPS

The clean customer source still has four inherited editor/preview failures
covered by the explicitly authorized, expiring canvas-homepage release registry.
Exact cases, owners and source fingerprints are canonical in
[the customer registry](../../../customer-site/.deployment/known-issues.canvas-homepage.json),
referenced from its `docs/KNOWN-TEST-FAILURES.md`.

Finish the standalone-to-shared-runtime migration separately, including sender
identity guards, document locale/direction and fallback-chrome parity. Preserve
the unrelated authoring WIP. Remove the corresponding group annotations and
registry exemptions once the normal full suite passes; approval expires on
2026-10-16 and fails closed if version or baseline source changes earlier.

## OPEN: PHASE 10 FOLLOW-UP — SCALE FAST-PATH TO ALL STANDARD BLOCKS

**Status:** In progress. The `reference_counter` pilot is complete and
`header_modern_v1` is the first standard opt-in: only `bgColor`, `textColor`,
and `ctaColor` use the fast-path. Slow-path rendering remains the default
until each candidate and property proves it can repaint correctly from
`state:update` without server HTML replacement.

**Goal:** extend the two-tier Canvas interaction model to standard blocks
whose high-frequency Inspector edits are property-only, while preserving the
canonical `canvas/render-block` POST for structural, server-resolved,
asset-dependent, or lifecycle-sensitive changes.

**Sub-tasks:**

- Review and opt in high-frequency blocks (for example text, color, and
  counters) to the fast-path.
- For every candidate, classify each editable property as fast-path-safe or
  slow-path-required; do not opt in a whole type when only some properties can
  repaint locally.
- Add a block-owned `ThemeBlockSDK.EVENTS.STATE_UPDATE` subscriber with
  stamped block-ID/type filtering and a deterministic `paint()` operation.
- Extend browser coverage to prove immediate effective-DOM changes, zero
  `canvas/render-block` requests for fast-path properties, unchanged POST
  behavior for slow-path properties, and save/reload/preview/public parity.
- Keep `countdown` on the slow-path unless a later ADR deliberately replaces
  ADR-058's rendered `data-end-date` configuration authority.

**Acceptance criteria:** every new allowlist entry has property-level safety
evidence, focused SDK/runtime coverage, and real-browser proof. Unlisted and
unsupported properties continue through the existing slow-path with no
behavior change.

---

## RESOLVED: SDK-002 — a block's controller handle was gone by the time the SDK released it

**Status:** RESOLVED (Phase 9.2). The `test.todo` in
`tests/js/block-sdk-lifecycle.test.mjs` is now a real regression test and
passes. Decision recorded as ADR-056 (the phase gate itself) and ADR-058
(the adapter-based block lifecycle it serves) in `DECISIONS.md`.

**Found by:** the Phase 9.2 SDK-to-runtime integration test, which boots the
real `runtime.blade.php` + the real `block-sdk.js` + the real `carousel.js` in
one `vm` context and drives a real block replacement through them.

**Symptom:** drive N block replacements through
`lifecycle('destroy')` → replace element → `initBlock(new)`. The carousel
controller is **initialised N+1 times but torn down once**. The
`window` `resize` listener count grows by one per replacement, so a long
editor session accumulates one stranded listener per block edit, each
pinning its detached carousel.

**Ruled out along the way** (each was checked, not assumed):
- The block's `onDestroy` IS entered on every replacement.
- The instance released is provably the same object the SDK created for that
  element — verified by object identity, not inferred.
- `BaseBlock.state` is assigned exactly once per instance
  (`block-sdk.js:165`), and `this.state` is identical in `init` and
  `onDestroy` (`stateIsSame=true`, `ctxStateSame=true`).
- `destroy()` on the controller does unregister the listener — proven
  directly by `carousel-lifecycle.test.mjs`, which is green.

**So the handle is already null when `onDestroy` runs**, despite `init`
having assigned it. The cause is in the SDK's instance/handle lifetime
around `replaceNode`, not in the carousel block.

**Root cause (found by per-phase trace, correcting the characterisation
above):** not a premature release at all — the destroy path always ran and
`onDestroy` was always entered. The handle was nulled by a **race between
the block's async init and the SDK's update phase**. `replaceNode()` runs
destroy and update synchronously back-to-back; the SDK's update hook calls
`runtime.initBlock(root)` and immediately fans out `onUpdate` to the fresh
instance. A block whose init returns a promise (the carousel awaits
`ctx.ready()`) completes its body one microtask LATER — after `onUpdate`
had already bound the controller — and then re-ran `initCarousel()`, hit
the element's `data-tb-carousel-init` guard, received `null`, and
**overwrote the live `state.handle` with it**. The first replacement
destroyed cleanly precisely because nothing raced its init (no update
phase precedes the initial mount); every subsequent destroy found
`handle === null` and leaked one window listener + one timer.

**Fix (structural, in the SDK's orchestration):** the SDK now serialises
lifecycle phases per instance (`gateHandler`/`gateInit` in
`block-sdk.js`). While a block's async init is pending: update/refresh
phases are dropped (the pending continuation already produces the post-init
state), and destroy phases are HELD until the continuation settles, then
delivered — so `releaseInstance()` is now ordered strictly AFTER
`onDestroy` completes and can never orphan a block's teardown. The carousel
gained an idempotence guard in its init continuation as defence in depth.
See ADR-056 for the full decision, the rejected alternatives, and the
`onUpdate` reachability ruling (the "unreachable on the replace path"
claim was an artefact of testing update phases against already-released
instances; it is reachable for the NEW instance).

**Two related findings from the same investigation**, both fixed in Phase 9.2:
- `releaseInstance()` deletes the instance from the live set, and
  `replaceNode()` emits `destroy` BEFORE `update`. So the SDK's own
  `onUpdate` hook is **unreachable on the real replace path** — nothing is
  left to call. A block that put its only re-init logic there would silently
  never re-initialise. Asserted in
  `block-sdk-lifecycle.test.mjs` so the property stays visible.
- `ThemeBlockSDK` is already the *created* instance, so calling
  `.create()` on it throws. That throw also aborted the shared file's public
  self-initialise fallback, because both carriers live in one file.

**Acceptance criteria:** a block replacement leaves exactly one live
controller handle and one `window` listener, and the
`test.todo` above passes. The `onUpdate` unreachability question needs a
decision recorded in `DECISIONS.md` — whether `onUpdate` should be reachable
at all, given the current destroy-then-update ordering.

---

## OPEN: DEV-REFERENCETHEMES-01 — FoodMart golden test depends on a gitignored directory

**Status:** Open. Tracked deliberately — NOT skipped, NOT fixed here.

**What broke it:** commit `040f0ab` (`chore(gitignore): ignore tool scratch,
reference themes, and transient reports`) added
`_reference-themes/` to `.gitignore`. That directory is ~70 MB of
third-party vendor theme source, which is the right call for repository
size, but it is also the INPUT side of a real contract test.

**The dependency:** `Phase3CContractsTest::foodmart_golden_manifest_and_every_referenced_file_keep_their_sha256()`
reads its 70 golden files from
`_reference-themes/FoodMart-main/FoodMart-main/`, and hashes them against
`storage/app/theme-baselines/foodmart/integrity-manifest.tsv`.

**Current behaviour:** GREEN on any machine where the directory was
downloaded (including this one). RED on a fresh clone and in CI with
`FoodMart golden file missing: <path>`.

**Why it was not fixed immediately:** the fix is a policy decision, not a
code fix, and it must not be conflated with unrelated carousel work. The
two candidate resolutions are mutually exclusive and have opposite costs:

  (a) Un-ignore `_reference-themes/`. Restores the test everywhere, costs
      ~70 MB in git history forever.
  (b) Make the test skip when the directory is absent. Keeps the repo
      small, but a skip that is silent can hide genuine golden drift —
      the failure mode is a *falsely green* integrity gate.

**Acceptance criteria:** the FoodMart integrity gate is either green on a
fresh clone by construction, or explicitly skip-guarded with the skip
condition logged — never silently green. Whichever is chosen, the decision
belongs in `DECISIONS.md`, not only here.

**Related:** introduced while cleaning the working tree for Phase 9.2. It
is a regression I introduced, distinct from the six pre-existing suite
failures (see the PHASE 9.1 entry below).

---

## COMPLETE: PHASE 9.2 — CAROUSEL MIGRATION TO THE BLOCK SDK — 2026-09-30

**Status:** COMPLETE. All `renderCarousel()` callers migrated; the
per-instance inline controller (`legacyCarouselScript()`) is deleted.
Decision recorded as ADR-058 in `DECISIONS.md`.

**Goal:** move `renderCarousel()`'s client logic onto the shared-asset /
BaseBlock carrier, one block type at a time. `product_slider` was the
canonical proof; the other carousel call sites followed.

**Decisions (2026-09-30):**
- **Shared asset, two carriers.** The controller lives once in
  `resources/js/blocks/carousel.js`. On canvas surfaces it registers as a
  real `BaseBlock` subclass; on PUBLIC pages — where no layout loads the
  canvas runtime, and which are therefore the only place a customer ever
  sees this — it self-initialises by scanning for `.tb-carousel`. Both
  carriers run the same controller and share the `data-tb-carousel-init`
  guard, so a page mixing both cannot double-bind a carousel.
- **Render snapshot is the gate.** `ProductSliderRenderSnapshotTest` pins a
  structural sha256 of the rendered markup in both the neutral and
  MODE_PUBLIC renders, captured BEFORE the migration and re-verified after.
  The hashes were re-pinned once for the intended two-`<script>` emission
  (controller + adapter); no other markup moved.
- **The four remaining types are migrated, not carried.** `slider`,
  `category_slider`, `testimonials_slider` and `client_logos` are registered
  against the SDK by `resources/js/blocks/legacy_carousel.js`, which reuses
  the shared controller and gives them the `destroy()` their inline script
  never had. Migration meant deleting the old carrier, not adding a second
  one, so `legacyCarouselScript()` is gone.

**Why the "two carriers" shape was forced:** the original plan was to make
the SDK the sole carrier. That would have shipped a dead carousel on every
live tenant site, because the block SDK is included on exactly three admin
views and on no public layout. The failure would have been invisible in the
admin preview, which is the one surface where the SDK *is* loaded.

---

## COMPLETE: PHASE 9.3 — COUNTDOWN MIGRATION TO THE BLOCK SDK — 2026-09-30

**Status:** COMPLETE. The inline ticker is out of `renderCountdown()`; the
packaged adapter `resources/js/blocks/countdown_adapter.js` carries it.
Decision recorded as ADR-058 in `DECISIONS.md`.

**What it fixed (2026-09-30):** the pre-migration inline copy had two defects
that moving the logic out fixed for free —
1. it never kept the interval handle, so an editor replacement of the block
   kept ticking against a detached node until the countdown ran out;
2. it set its init guard *before* validating the date, so an unparseable
   `end_date` bricked the block permanently even if the value were corrected
   live.

The adapter validates before guarding, exposes a handle whose `destroy()`
clears the interval, and runs the same controller under both carriers with
the same `data-tb-countdown-init` guard. Configuration stays in
`data-end-date` on the block root — the props channel (`ctx.props`) can only
disagree with the markup that actually shipped.

**Verification:** `tests/js/countdown-lifecycle.test.mjs` (9 tests),
`tests/Unit/ThemeBuilder/CountdownRenderTest.php`.

---

## COMPLETE: PHASE 9 / STEP 1 — STANDARDIZED BLOCK SDK & DX — 2026-09-29

**Delivered** — `ThemeBlockSDK`, the standardized developer layer over the
unchanged `ThemeCanvasRuntime` (protocol version 1):

- `resources/js/block-sdk.js` — `BaseBlock` + `defineBlock`, both authoring
  forms; `init`/`onUpdate`/`onRefresh`/`onDestroy` bridged onto the runtime's
  two registration idioms; scoped `ctx` (`element`, `type`, `blockId`,
  `props`, `on`/`off`/`emit`, plus `$`, `ready`, `setProp`, `block`, `state`);
  `deps` resolved through the runtime's own `trackScript()` (its first real
  consumer) and a deduped `<link>` for css; per-block error containment
  (`data-tb-block-error`, `block:error`); a frozen published `EVENTS` schema
  with name validation and automatic `{ blockId, type }` stamping.
- `canvas/block-sdk.blade.php` + `ThemeRuntimeServiceProvider::blockSdkContractPath()`
  — the same distributable-JS seam as the identity contract, so Node tests and
  Blade hosts cannot drift.
- Bootstrap order position 2 (after `runtime`, before `identity`) plus a third
  fail-fast version gate; `canvas-preview.blade.php` includes `runtime` +
  `block-sdk` (render-only, no identity).
- `data-tb-block-props` is now emitted by `BlockRenderer` in both editor and
  public mode — the attribute the runtime always read but nothing ever wrote,
  which is why `ctx.props` was permanently empty. Child tree excluded; empty
  props emit nothing, so blocks without props render byte-identically.
- `reference_counter` — the worked example (class form, declared deps, all four
  lifecycle phases, `ctx.$`, `ctx.setProp` + `state:update`, block-local
  events). Intentionally **not** in `BlockRegistry`.

**Verification** — `tests/js/block-sdk.test.mjs` 27 tests against the real
`runtime.blade.php` source (not a stub); `canvas-editor-bootstrap.test.mjs`
5 tests; `BlockSdkPropsChannelTest` 11 tests; full JS suite 46/46. PHP
regression: 3 pre-existing failures only, each confirmed identical with this
phase's changes stashed (see KNOWN_ISSUES). No migration, no route change, no
protocol change.

**Not in this step (deliberately)** — editor-side asset distribution still
rides `ExtensionCanvasPaletteAdapter`/`ExtensionElementDefinition`; and
`ctx.setProp` is local client state, not an editor write-back path.

**Next** — DONE. Phase 9.2 migrated the carousel and Phase 9.3 the countdown
onto the SDK as the worked examples; see the PHASE 9.2 and PHASE 9.3 entries
above and ADR-058. The open question this step deferred — whether
customer-site should include the SDK in its canvas bootstrap — remains open.

---

## READY FOR FINAL REVIEW: PHASE 3C MILESTONE 2 — 2026-09-26

| Deliverable | Status |
|---|---|
| `dynamic_product_grid` and `dynamic_feed_slider` | DONE LOCAL |
| Per-renderer fetch lifecycle and explicit states | PASS — loading/empty/error/success |
| Data-source sanitization and escaping | PASS — HTTPS + exact host allowlist; internal source allowlist; bounded JSON-lite |
| Product `category_id` / `collection_slug` binding | PASS — existing tenant registry reused |
| Primitive Inspector controls and RTL/LTR CSS | PASS |
| Phase 3A/3B/3C, JS, browser, FoodMart regression | PASS |
| Production/commit/push/deploy | NOT AUTHORIZED / NOT RUN |

---

## READY FOR FINAL REVIEW: PHASE 3C MILESTONE 1 — 2026-09-26

| Deliverable | Status |
|---|---|
| Six commercial widget definitions and renderers | DONE LOCAL |
| Legacy `features_grid` / `stats_row` compatibility | PASS — child mode is additive; JSON fallback retained |
| Token-scoped logical RTL/LTR runtime CSS | PASS |
| Persian feature and stats/trust Section Library entries | DONE LOCAL |
| Phase 3C TDD contracts | 9 PASS |
| Phase 3A/3B, JS, browser, FoodMart golden regression | PASS — FoodMart 20/20; all 70 golden SHA-256 values intact |
| Production/commit/push/deploy | NOT AUTHORIZED / NOT RUN |

---

## READY FOR FINAL REVIEW: SLEEPSHIMAN HOME BLUE — 2026-09-24

| Deliverable | Status |
|---|---|
| 22 editable shared-runtime blocks | DONE LOCAL |
| Manual-first page without optional modules | DONE LOCAL |
| Products, categories, blog, approved reviews | DONE LOCAL |
| Localized/hash-pinned assets and responsive RTL | DONE LOCAL |
| Canvas save/reload/publish-fixture/public journey | PASS |
| Exact Figma frame verification | BLOCKED — Starter MCP call limit; `2014:755` remains unverified |
| Production/commit/push/deploy | NOT AUTHORIZED / NOT RUN |

---

## COMPLETE: FOODMART CANVAS COMPLETION GATE — 2026-09-23

| Deliverable | Status |
|---|---|
| Meaningful logical-element editing | DONE — schema-driven block/repeater item selection; no arbitrary DOM editing |
| Hero add/edit/remove/reorder safety | DONE — complete defaults plus declarative full-document lifecycle refresh |
| Full draft Preview | DONE — canonical runtime document/layout/assets; no independent Tailwind preview |
| Module presentation capability contract | DONE — active-theme declarations only |
| Products and Product Categories insertion | DONE — FoodMart presentations, real binding, before/after selected block |
| Unsupported modules | DONE — unavailable until domain binding and theme presentation both exist |

Next candidates, not started: Blog/Blog Categories binding and FoodMart presentation; then Menu, Forms, Cart, Search, Settings, and Social capability slices. Do not expose any as insertable until both binding and theme presentation are truthful.

---

## COMPLETE: FOODMART PHASE 4B — REAL DATA BINDING FOUNDATION — 2026-09-23

| Deliverable | Status |
|---|---|
| Generic allowlisted binding registry | DONE — `products`, `product_categories` only |
| FoodMart Products/Categories semantic adapters | DONE |
| Canvas binding controls | DONE — declared source; sort/filter/manual IDs/limit/empty policy |
| Published/tenant-safe resolution | DONE — customer database boundary, no connection/model selectors |
| FoodMart-native dynamic DOM and existing interactions | DONE |
| Static reference mode | DONE — byte-identical without a binding |
| Controlled fixtures, generated images, screenshots, cleanup | DONE |

Next candidates, not started: Blog/Blog Categories through this registry; an explicit checkpoint for a Brand model or category image migration; and an approved definition of “trending.” Phase 4B does not invent those concepts.

---

## OPEN: PHASE-009 ENTERPRISE CERTIFICATION GATES

**Status:** CONDITIONAL — code-level audit complete; certification withheld. Canonical scope and evidence: `FINAL_GAP_ANALYSIS.md`.

| Gate | Status |
|------|--------|
| Implement or hide cPanel, DirectAdmin, SFTP, Docker driver stubs | OPEN |
| Implement distinct clone, move, reinstall semantics | OPEN |
| Define safe installation retry after automatic rollback | OPEN |
| Implement tenant-storage and multi-customer restore | OPEN |
| Version/provision restore worker control assets | OPEN |
| Repair local Composer dev autoload and pass regression suites | BLOCKED |
| Run controlled interruption/reboot/disk/connectivity drills | OPEN |
| Collect representative performance baselines and approve SLOs | OPEN |

---

## COMPLETE: P1-004 — LOCAL RECOVERY REPOSITORY — 2026-06-28 ✅

**Status:** LOCAL_RECOVERY_REPOSITORY_COMPLETE — All 33 requirements certified PASS

| Task | Status |
|------|--------|
| Migration: local_recovery_logs | ✅ DONE |
| Model: LocalRecoveryLog | ✅ DONE |
| Config: installer.recovery_repository section | ✅ DONE |
| Service: LocalRecoveryRepository (store/verify/list/status) | ✅ DONE |
| Controller: RecoveryRepositoryController | ✅ DONE |
| View: recovery-repository/index.blade.php (4 status cards, config form, 2 backup tables) | ✅ DONE |
| Routes + AdminMenu (زیرساخت / DevOps → خزانه بازیابی محلی) | ✅ DONE |
| UpgradeAdapter integration (platform baseline local copy) | ✅ DONE |
| InitializeBackupStage integration (customer baseline local copy + policy fix) | ✅ DONE |
| RestoreAdapter + PlatformInstallerController (4th source: recovery_repository) | ✅ DONE |
| source-select.blade.php (🛡️ Local Recovery Repository section) | ✅ DONE |
| docs/LOCAL_RECOVERY_REPOSITORY.md | ✅ DONE |
| docs/LOCAL_RECOVERY_CERTIFICATION.md | ✅ DONE |
| Deploy to production | ✅ DONE |

---

## COMPLETE: HOTFIX-001 + HOTFIX-002 + HOTFIX-003 — PRODUCTION_BUGS_FIXED — 2026-06-27 ✅

**Status:** All production 500 bugs resolved and verified

| Bug | Root Cause | Fix | Status |
|-----|-----------|-----|--------|
| BUG-001 | InstallerService ParseError (];→};) | Fixed in-place + deploy | ✅ RESOLVED |
| BUG-002 | RestoreWizardController::validate() conflicts with base | Renamed to checkBackup() | ✅ RESOLVED |
| BUG-003 | INSTALLER_MASTER_KEY missing from .env | Generated via artisan command | ✅ RESOLVED |
| BUG-004 | sites.name column doesn't exist (is 'title') | orderBy('title') in RestoreWizardController | ✅ RESOLVED |
| BUG-005 | Alpine x-show on mode-select never re-evaluates | Rewrote to vanilla JS with data-mode | ✅ RESOLVED |
| BUG-005-B | Alpine x-model on source-select never populated | Rewrote to vanilla JS with data-source | ✅ RESOLVED |
| BUG-006 | SlackAlertService 1-arg calls (ArgumentCountError) | Added 2nd arg to all calls in 3 files | ✅ RESOLVED |
| BUG-007 | Customer-deployment/servers 500 (transient mid-deploy) | No code change needed; key present | ✅ RESOLVED |

---

## COMPLETE: PHASE-002 + PHASE-003A + PHASE-003B + PHASE-003C — CUSTOMER_INSTALLATION_ENGINE

**Status:** PHASE-003C_COMPLETE — 2026-06-27 ✅

| Component | Status |
|-----------|--------|
| PostInstallValidationService (8 checks: database/queue/redis/storage/license/scheduler/domain/ssl) | ✅ DONE |
| SshRemoteDriver reconnect (MAX_RECONNECTS=2, 0.8s pause) | ✅ DONE |
| CustomerReadyStage — validation-driven, persists post_install_validation | ✅ DONE |
| InitializeBackupStage — INITIAL_CUSTOMER_BASELINE → Arvan → SHA-256 | ✅ DONE |
| CustomerInstallAcceptanceCommand (--full: disk/extensions/MySQL/Supervisor/Redis) | ✅ DONE |
| Migration: baseline + validation columns on customer_installations | ✅ DONE |
| CustomerInstallationServiceProvider — PostInstallValidationService + AcceptanceCommand registered | ✅ DONE |

**Post-deploy actions required:**
- `php artisan migrate --force` (new migration: 2026_06_27_200000_...)
- `php artisan customer-install:acceptance-test {server_id} --full` (verify server readiness)

---

## COMPLETE: PHASE-002 + PHASE-003A + PHASE-003B — CUSTOMER_INSTALLATION_ENGINE (previous record)

**Status:** PHASE-003B_COMPLETE — 2026-06-27 ✅

| Component | Status |
|-----------|--------|
| Architecture (5 docs) | ✅ DONE |
| 5 Migrations | ✅ DONE |
| 5 Models (incl. immutable log) | ✅ DONE |
| DTOs + Contracts | ✅ DONE |
| CredentialVault (AES-256-GCM) | ✅ DONE |
| SshRemoteDriver (phpseclib3) | ✅ DONE |
| FakeRemoteDriver (test isolation) | ✅ DONE |
| 20 Stage classes | ✅ DONE |
| CustomerInstallationOrchestrator | ✅ DONE |
| CustomerInstallationRollbackService | ✅ DONE |
| 3 Jobs + 2 Commands | ✅ DONE |
| CustomerInstallationServiceProvider | ✅ DONE |
| 31/31 Tests passing | ✅ DONE |
| PHASE-003A Deployed to production | ✅ DONE |
| CustomerDeploymentController | ✅ DONE |
| CustomerRemoteServerAdminController | ✅ DONE |
| CustomerInstallationProgressController (SSE) | ✅ DONE |
| Dashboard view (6 stat cards) | ✅ DONE |
| Wizard view (8-step Alpine.js) | ✅ DONE |
| History view (filterable) | ✅ DONE |
| Installation detail view (timeline + SSE) | ✅ DONE |
| Packages list view | ✅ DONE |
| Server CRUD views (index/create/edit) | ✅ DONE |
| AdminMenu entry (customer_deployment) | ✅ DONE |
| 15 routes registered | ✅ DONE |
| 4 documentation files | ✅ DONE |

**Remaining phases:**

| Phase | Description | Status |
|-------|-------------|--------|
| C | Integration with real Arvan package download | ⏳ NOT STARTED |
| D | SSH live-connection test to real servers | ⏳ NOT STARTED |
| E | All 5 install modes fully tested end-to-end | ⏳ NOT STARTED |

---

---

## COMPLETE: PERSIAN_LOCALIZATION_GLOBAL_STANDARD — 2026-06-26 ✅

**Status:** PERSIAN_LOCALIZATION_GLOBAL_STANDARD_DEPLOYED

| Task | Status |
|------|--------|
| PersianFormatter service (both repos) | ✅ DONE |
| Global helpers + composer autoload (both repos) | ✅ DONE |
| 8 Blade directives in AppServiceProvider (both repos) | ✅ DONE |
| `<x-jalali-datepicker>` component (saas-platform) | ✅ DONE |
| `.persian-date-input/.persian-date-defer` jQuery system (customer-site) | ✅ DONE |
| All number_format() violations fixed — saas-platform (49 files) | ✅ DONE |
| All number_format() violations fixed — customer-site (54+ files) | ✅ DONE |
| Date input conversion — saas-platform (users/_filters) | ✅ DONE |
| Date input conversion — customer-site (orders/index, users/index) | ✅ DONE |
| Controller Jalali→Gregorian conversion — UserController, OrderController, UserPanelController | ✅ DONE |
| 4 certification docs generated | ✅ DONE |
| project-memory updated | ✅ DONE |

**Exceptions (documented):**
- SVG stroke-dasharray/stroke-dashoffset in readiness-score.blade.php — intentionally excluded
- datetime-local input in sms/campaigns/create.blade.php — MANUAL REVIEW

---

## COMPLETE: SITE_THEME_PRESET_FIX — 2026-06-26 ✅

**Status:** SITE_THEME_PRESET_FIX_DEPLOYED

| Task | Status |
|------|--------|
| Migration: add 9 color columns to theme_settings | ✅ DONE (batch 111) |
| ThemeSetting model: fillable + defaults + toCssVariables() | ✅ DONE |
| TenantThemeResolver: fromThemeSetting() comprehensive CSS vars | ✅ DONE |
| Public layout (default): add theme-engine-tokens include | ✅ DONE |
| Canvas layout: add theme-engine-tokens include | ✅ DONE |
| Admin theme-design: custom card + 4-section color form | ✅ DONE |
| Controller: validate 9 new fields + auto-set preset=custom | ✅ DONE |

---

## COMPLETE: THEME_COLOR_FULL_ISOLATION — 2026-06-27 ✅

**Status:** THEME_COLOR_ISOLATION_DEPLOYED

**Rule:** All colors in every default theme template must come from the selected palette. No hardcoded hex blue.

| Task | Status |
|------|--------|
| layout.blade.php: css_block injected BEFORE theme-engine-tokens (theme wins) | ✅ DONE |
| home.blade.php: hero gradient — `--platform-link-hover` → `--ui-indigo-hover` | ✅ DONE |
| TenantThemeResolver: all `--platform-*` vars set in both preset and custom mode | ✅ DONE |
| blog/show.blade.php: tag badges `#eef2ff/#4338ca` → CSS vars | ✅ DONE |
| blog/index.blade.php: focus outline → `var(--platform-primary)` | ✅ DONE |
| content/show.blade.php: AI badge, category link, excerpt border, tags, steps, body links | ✅ DONE |
| content/index.blade.php: filter tabs, AI badge, "مطالعه" link, pagination active | ✅ DONE |
| page.blade.php: content body link color → `var(--platform-link-color)` | ✅ DONE |
| search/index.blade.php: hero gradient end, filter chip, JS price color | ✅ DONE |
| shop/cart.blade.php: SVG, empty btn, count badge, price, focus, total, coupon, form, checkout btn | ✅ DONE |
| shop/index.blade.php: focus outline → CSS var | ✅ DONE |
| shop/show.blade.php: installment card border/bg, SVG, text, hover, plan name, monthly payment | ✅ DONE |

**CSS Var Mapping:**
- `--platform-primary` → main theme color (buttons, icons, active elements)
- `--platform-link-color` → link colors in body text
- `--platform-primary-light` → badge/card backgrounds (`--ui-bg` from preset)
- `--ui-indigo-hover` → gradient endpoint / secondary accent

---

## COMPLETE: CORE_BLOCKERS_IMPLEMENTATION — 2026-06-27 ✅

**Status:** CORE_BLOCKERS_IMPLEMENTATION_COMPLETE — Core Health 95/100 — CORE_READY_FOR_FREEZE

| Task | Status |
|------|--------|
| P0-1: executePlatformDatabaseRestore() + RestorePlatformDatabaseJob | ✅ DONE |
| P0-2: Queue reliability — restore connection + retry_after=7200 | ✅ DONE |
| P0-2b: InstallerValidator sync → hard FAIL | ✅ DONE |
| P0-3: Restore atomicity — DB::transaction() + parameterized SQL | ✅ DONE |
| P1-4: BackupTenantDatabaseCommand + Kernel schedule 02:30 | ✅ DONE |
| P1-5: ArvanBackupService::prune() + called in all 3 backup commands | ✅ DONE |
| P1-6: Scheduler heartbeat + SCHEDULER_DEAD fix | ✅ DONE |
| P2-7: MYSQL_PWD everywhere — RestoreService + BackupDatabaseCommand | ✅ DONE |
| P2-8a: listContents() O(1) fix in ArvanBackupService | ✅ DONE |
| P2-8b: Composite index migration on infra_backup_runs | ✅ DONE |
| docs/CORE_BLOCKERS_IMPLEMENTATION.md | ✅ DONE |
| docs/CORE_HEALTH_REASSESSMENT.md | ✅ DONE |
| docs/CORE_FREEZE_READINESS_V2.md | ✅ DONE |
| project-memory updated | ✅ DONE |
| deploy.ps1 | ✅ DONE |

**Post-deploy required on server:**
- `php artisan migrate --force` (composite index migration)
- `supervisorctl status` (verify redis worker)
- `php artisan db:backup-tenants --dry-run` (verify tenant backup)

**Accepted warnings (Phase 3 targets):**
- SUP-001: Supervisor group name mismatch
- DR-SCORE-01/DR-ALERT-02: DR score/alert cosmetic issues
- FIN-IDEM-01/FIN-BOUND-02: Finance minor gaps
- SEC-RATE-01: License endpoint rate limiting

---

## COMPLETE: DR_CENTER_PHASE2_COMPLETION — 2026-06-26 ✅

**Status:** DISASTER_RECOVERY_CENTER_ENTERPRISE_READY

| Feature | Status |
|---------|--------|
| FEATURE 1: Recovery Readiness Score (10-component weighted, ring gauge UI) | ✅ DONE |
| FEATURE 2: Backup Age (Jalali Persian, every backup card) | ✅ DONE |
| FEATURE 3: Simulation History (stored, paginated, NON-DESTRUCTIVE) | ✅ DONE |
| FEATURE 4: Arvan Storage Status (total files, latest upload, verification, NOT AVAILABLE for capacity) | ✅ DONE |

**Migration required on server:** `php artisan migrate` (creates `dr_simulations`)

**Docs:**
- `docs/DR_CENTER_PHASE2_COMPLETION.md`
- `docs/DR_CENTER_FINAL_CERTIFICATION.md`

---

## COMPLETE: DR_CENTER_OPERATIONAL — 2026-06-26 ✅

---

## COMPLETE: PRE_LAUNCH_POLISH_AND_HARDENING — 2026-06-22 ✅

**Status:** PRE_LAUNCH_HARDENING_COMPLETE

| Phase | Status |
|-------|--------|
| Phase 1: Customer UX Polish — CUX-006/008/009 implemented, score 89→92 | ✅ DONE |
| Phase 2: Admin UX Polish — AUX-008 state bar, score 90→93 | ✅ DONE |
| Phase 3: SSL Hardening — SSL_HARDENING_REPORT.md (no mixed content found) | ✅ DONE |
| Phase 4: Offsite Backup Audit — OFFSITE_BACKUP_AUDIT.md (CRITICAL gap documented) | ✅ DONE |
| Phase 5: Monitoring Hardening — 3 gaps implemented in ScanRuntimeAnomaliesCommand | ✅ DONE |
| Phase 6: Launch Day Runbook — LAUNCH_DAY_RUNBOOK.md (T-60→T+48h sequence) | ✅ DONE |
| lifecycle archive deadline push from SiteLifecycleService | ✅ DONE |
| suspended.blade.php archive deadline block | ✅ DONE |
| pay-failed.blade.php Alpine.js 3-min cooldown | ✅ DONE |
| installments/show.blade.php next-payment amber highlight | ✅ DONE |
| lifecycle/overview.blade.php 5-color state distribution bar | ✅ DONE |

**Pre-launch open actions (non-code) — UPDATED 2026-06-23:**
1. ✅ FIXED: `APP_URL=https://...` in production .env (was already correct)
2. CRITICAL: ArvanCloud CDN activation (real SSL) — DevOps action
3. ✅ FIXED: `CORS_ALLOWED_ORIGINS` in production defaults to empty array (secure — no wildcard)
4. HIGH: Manual DB backup offsite copy before DNS cutover
5. ✅ FIXED: `customer-site-worker` FATAL → restarted (now RUNNING)
6. ✅ FIXED: `PLATFORM_URL=http://` → `https://` on customer-site production

---

## COMPLETE: RC1_LAUNCH_FREEZE_ENFORCEMENT — 2026-06-22 ✅

**Status:** LAUNCH_FREEZE_ACTIVE

| Component | Status |
|-----------|--------|
| Phase 1: LAUNCH_FREEZE_RULES.md — allowed/prohibited/approval/emergency/gate | ✅ DONE |
| Phase 2: PRE_LAUNCH_CLEANUP_REPORT.md — zero placeholder pages, 3 minor findings | ✅ DONE |
| Phase 3: CUSTOMER_UX_REVIEW.md — 10 improvements, score 89/100 | ✅ DONE |
| Phase 4: ADMIN_UX_REVIEW.md — 12 improvements, score 90/100 | ✅ DONE |
| Phase 5: OPERATIONAL_HEALTH_REPORT.md — 24 schedulers, queue, backup, SMS all verified | ✅ DONE |
| Phase 6: Roadmap Gate active — FEATURE/ARCHITECTURE → POST_LAUNCH_ROADMAP.md only | ✅ DONE |

---

## COMPLETE: CUSTOMER_MENU_OPTIMIZED — 2026-06-22 ✅

**Status:** CUSTOMER_MENU_OPTIMIZED

| Component | Status |
|-----------|--------|
| Audit 54 sidebar items → CUSTOMER_MENU_AUDIT.md | ✅ DONE |
| AdminMenu.php: MODULE_DESCRIPTION_ONLY constant (24 modules) | ✅ DONE |
| AdminMenu.php: getDynamicPlatformMenuKeys() filter | ✅ DONE |
| AdminMenu.php: getStaticGroups() 11 semantic groups | ✅ DONE |
| AdminMenu.php: isVisible() additions (crm/inventory/seo_advanced/sms_campaigns) | ✅ DONE |
| CUSTOMER_MENU_BEFORE_AFTER.md | ✅ DONE |
| CUSTOMER_MENU_REFACTOR_REPORT.md | ✅ DONE |
| CUSTOMER_MENU_CERTIFICATION.md | ✅ DONE |
| Deploy | ✅ DONE |

---

## COMPLETE: RELEASE_CANDIDATE_1 — RC1_READY — 2026-06-22 ✅

**Status:** RC1_READY — All 15 systems AUDIT_PASS. Final gap (GAP-L07) fixed. Documents generated.

| Component | Status |
|-----------|--------|
| Phase 1: GAP-L07 fix — renewal URL in grace period SMS | ✅ DONE |
| Phase 2: System audit — 14 PASS / 1 WARN / 0 FAIL | ✅ DONE |
| Phase 3: RELEASE_CANDIDATE_1.md | ✅ DONE |
| Phase 3: GO_LIVE_CHECKLIST.md | ✅ DONE |
| Phase 3: POST_LAUNCH_ROADMAP.md | ✅ DONE |
| Phase 4: POST_LAUNCH_ROADMAP — H01, Collaborative Editing, Analytics, Marketplace, AI | ✅ DONE |
| Deploy saas-platform | ✅ DONE |

---

## COMPLETE: TB3 Sprint 12 — ZIP Template Import Engine — 2026-06-22 ✅

**Status:** ZIP_IMPORT_ENGINE_OPERATIONAL (2026-06-22)

| Component | Status |
|-----------|--------|
| ZipTemplateExtractor (safe extraction, 100MB/500 file limits, path traversal guard) | ✅ DONE |
| ZipTemplateAnalyzer (6 origins, JSON priority, metadata extraction) | ✅ DONE |
| ZipTemplateImportService (7-phase orchestrator, 5-dim similarity scoring) | ✅ DONE |
| ThemeZipImportController (form/detect/import/result/install/export) | ✅ DONE |
| zip-import.blade.php (drag-drop + AJAX detect banner + step indicator) | ✅ DONE |
| zip-import-result.blade.php (score dashboard + breakdown + phase timeline) | ✅ DONE |
| Routes: 6 endpoints at /admin/theme-builder/zip-import | ✅ DONE |
| AdminMenu: zip_import key + sidebar icon | ✅ DONE |
| Docs: ARCHITECTURE + CERTIFICATION + VALIDATION_REPORT | ✅ DONE |
| Deploy saas-platform | ✅ DONE |

**Supported sources:** TemplateGoat · ThemeForest · Envato · Elementor Library · Bricks Library · Shopify · Webflow

---

## COMPLETE: TB3 Sprint 11.5 — Playwright Headless Engine — 2026-06-21 ✅

**Status:** TB3_SPRINT11_5_CERTIFIED (2026-06-21)

| Component | Status |
|-----------|--------|
| extract.mjs: multi-viewport + lazy-scroll + hydration wait + 60+ selectors | ✅ DONE |
| PlaywrightSnapshotService: DOM/CSS/Layout snapshots | ✅ DONE |
| SimilarityEngineService: headless quality bonus + EXCELLENT tier | ✅ DONE |
| VisualReverseEngineeringService: device param + snapshot storage | ✅ DONE |
| PlaywrightExtractionService: device param to Node | ✅ DONE |
| Admin result view: screenshot + perf metrics + snapshots explorer | ✅ DONE |
| ValidateHeadlessEngineeringCommand: 6-site validation suite | ✅ DONE |
| Docs: CERTIFICATION + ARCHITECTURE + VALIDATION_REPORT | ✅ DONE |

---

## COMPLETE: Business Flow Remediation — BATCH 3 (LOW) — 2026-06-22 ✅

**Status:** BFA_BATCH3_COMPLETE (2026-06-22)

### LOW — ALL DONE ✅

| ID | Task | Status |
|----|------|--------|
| GAP-M10 | Lifecycle Delete No Physical Cleanup — SiteArchiveService::deleteArchiveZip() wired in SiteLifecycleService::delete() | ✅ DONE |
| GAP-L01 | License Expiry Not Shown — API returns expires_at (purchase+1yr for non-permanent), blade shows it | ✅ DONE |
| GAP-L02 | Payment Failure No Retry Guidance — pay-failed.blade.php rebuilt with 4-step guidance + support contacts | ✅ DONE |
| GAP-L03 | Campaign Delivery No CSV Export — SmsCampaignController::exportCsv(), route, button in show.blade | ✅ DONE |
| GAP-L05 | Admin Lifecycle No Days-Until-Archive — countdown chip in lifecycle/index.blade.php (red ≤30d, amber ≤90d) | ✅ DONE |
| GAP-L06 | Archive ZIP Not Verified — verifyZip() called in createArchivePackage() (size + ZipArchive::RDONLY check) | ✅ DONE |
| GAP-L08 | Post-Restore Counters Zero — SiteLifecycleService::restore() calls calculator->calculate() immediately | ✅ DONE |
| GAP-L09 | Site Create No Post-Create Checklist — redirect to edit + just_created flash + 4-action checklist banner | ✅ DONE |

---

## COMPLETE: Business Flow Remediation — BATCH 2 — 2026-06-22 ✅

**Status:** BFA_BATCH2_COMPLETE (2026-06-22)

### HIGH — ALL DONE ✅

| ID | Task | Status |
|----|------|--------|
| GAP-H04 | Overage Fees Never Billed — BillResourceOverageCommand (monthly, SiteLifecycleInvoice TYPE_OVERAGE, SMS) | ✅ DONE |
| GAP-H05 | Dunning for Failed Renewals — ProcessDunningCommand (day3/7/14 SMS, SubscriptionLog tracking) | ✅ DONE |
| GAP-H06 | Marketplace/Bundle No PDF Invoice — `/my-licenses/{key}/invoice` + licenseInvoiceData API + PDF download | ✅ DONE |
| GAP-H07 | Upload Not Throttled — SmartImageUploadService enforceStorageLimit() (512MB default, 5min cache) | ✅ DONE |

### MEDIUM — ALL DONE ✅

| ID | Task | Status |
|----|------|--------|
| GAP-M01 | Canvas No Autosave — setInterval 60s in canvas-editor.blade.php init() | ✅ DONE |
| GAP-M02 | No Published Theme Revision History — rotateRevisions() in canvasPublish() (5-slot cap) | ✅ DONE |
| GAP-M03 | Suspended Page Dead End — support_phone + support_telegram contact section in suspended.blade.php | ✅ DONE |
| GAP-M04 | Bundle No "Install All" Button — per-theme install buttons + "Install First Theme" in bundle show | ✅ DONE |
| GAP-M07 | Platform URL Not Configured — admin layout amber warning banner with .env instructions | ✅ DONE |
| GAP-M08 | Resource Push Silent Failure — alertPushFailed() in SiteResourceAlertService, wired in CalculateSiteResourcesCommand | ✅ DONE |
| GAP-M09 | Site Create No Customer SMS — SiteController::sendSiteCreatedSms() after Site::create() | ✅ DONE |

---

## COMPLETE: Business Flow Remediation — PRODUCTION_BLOCKERS — 2026-06-22 ✅

**Status:** ALL 5 CRITICAL BLOCKERS RESOLVED (2026-06-22)
**Certification:** See `CRITICAL_BLOCKER_PROGRESS.md` for full details.

### Critical — ALL DONE ✅

| ID | Task | Status | Certification |
|----|------|--------|---------------|
| BFA-C01 | Restore invoice payment flow (customer → Zarinpal/IDPay → restore) | ✅ DONE | C01_RESTORE_PAYMENT_OPERATIONAL |
| BFA-C02 | Wire subscription renewal → lifecycle activate() | ✅ DONE | C02_REACTIVATION_OPERATIONAL |
| BFA-C03 | ProcessScheduledSmsCampaignsCommand + Kernel entry | ✅ DONE | C03_SMS_SCHEDULER_OPERATIONAL |
| BFA-C04 | Customer self-service restore button on suspended page | ✅ DONE | C04_SELF_SERVICE_OPERATIONAL |
| BFA-C05 | Physical file deletion on archive (SiteArchiveService::deleteActiveFiles) | ✅ DONE | C05_STORAGE_RELEASE_OPERATIONAL |

### High Priority — ALL DONE ✅

| ID | Task | Hours | Status |
|----|------|-------|--------|
| BFA-H03 | Grace period mid-reminder SMS scheduler | 2h | ✅ DONE (2026-06-22) |
| BFA-H02 | Theme install backup + rollback mechanism | 3h | ✅ DONE (2026-06-22) |
| BFA-M05 | Clear canvas_draft_data on new theme install | 0.5h | ✅ DONE (2026-06-22) |

---

## COMPLETE: Acool HVAC Theme v2.0 + [invalid] Block Fix — 2026-06-22 ✅

**Status:** ACOOL_HVAC_V2_CERTIFIED (2026-06-22)

| Component | Status |
|-----------|--------|
| builder-element-schemas.php: 10 new block types registered | ✅ DONE |
| BlockRenderer.php: 10 new render methods (inline-styled HVAC HTML) | ✅ DONE |
| AcoolHvacSeedCommand v2.0: 12 pages, header_blocks/footer_blocks correct | ✅ DONE |
| Stale config cache (root@localhost) fixed on production | ✅ DONE |
| Theme ID 54, marketplace_score=92, is_featured=true | ✅ DONE |
| [invalid] blocks (nav_header, client_logos, stats_row, trust_badges) fixed | ✅ DONE |

---

## COMPLETE: SMS Campaign Manager — 2026-06-21 ✅

**Status:** SMS_CAMPAIGN_MANAGER_COMPLETE (2026-06-21)

| Component | Status |
|-----------|--------|
| Migration: sms_campaigns + sms_campaign_recipients | ✅ DONE |
| SmsCampaign model (6 statuses, 4 audience types, deliveryRate()) | ✅ DONE |
| SmsCampaignRecipient model (pending/sent/failed) | ✅ DONE |
| SmsService — real Kavenegar v1 + Ghasedak v2 HTTP implementations | ✅ DONE |
| SmsCampaignService — create/update/buildRecipients/send/cancel/duplicate/getStats | ✅ DONE |
| SendSmsCampaignJob — queued, tries=2, atomic counter update, COMPLETED detection | ✅ DONE |
| SmsCampaignController — 10 actions (index/create/store/show/edit/update/destroy/send/cancel/duplicate) | ✅ DONE |
| 4 Blade views (index/create/show/edit) — Persian RTL, stats cards, delivery progress bars | ✅ DONE |
| Routes (10 routes under sms/campaigns prefix) | ✅ DONE |
| AdminMenu: sms_campaigns key + megaphone sidebar icon | ✅ DONE |
| Deploy customer-site | ✅ DONE |

**⚠️ PENDING:** `php artisan migrate` on production server (customer-site)

---

## COMPLETE: JSON Import Validation — 2026-06-21 ✅

**Status:** JSON_IMPORT_VALIDATION_PASSED (2026-06-21)

| Format | Checks | Quality | Grade |
|--------|--------|---------|-------|
| Elementor | 15/15 ✅ | 80/100 | EXCELLENT |
| Bricks Builder | 15/15 ✅ | 80/100 | EXCELLENT |
| Shopify | 15/15 ✅ | 90/100 | EXCELLENT |
| Webflow | 15/15 ✅ | 90/100 | EXCELLENT |
| Custom/ThemePack v1 | 15/15 ✅ | 95/100 | EXCELLENT |

**Bugs fixed:**
- BUG-001: ThemePackGenerator annual_price/lifetime_price NULL constraint
- BUG-002: Shopify multi-page detection (pages[].sections pattern)
- BUG-003: Validation command multi-page check format-awareness

**Artifacts:** `storage/json-import-samples/` (5 files), `docs/JSON_IMPORT_VALIDATION_REPORT.md`, `app/Console/Commands/ValidateJsonImportCommand.php`

---

## COMPLETE: GAP-010 Theme Bundle System — 2026-06-20 ✅

**Status:** COMPLETE (2026-06-20)

| Component | Status |
|-----------|--------|
| Migration: theme_bundles + theme_bundle_orders | ✅ DONE |
| Models: ThemeBundle (grantLicensesToSite), ThemeBundleOrder (STATUS_*) | ✅ DONE |
| Platform Admin: ThemeBundleAdminController (CRUD + orders view) | ✅ DONE |
| Platform Admin Views: index, create, edit, orders | ✅ DONE |
| Platform API: ThemeBundleApiController (index/show/purchase + formatBundle) | ✅ DONE |
| Payment Callback: ThemeBundlePaymentCallbackController (Zarinpal+IDPay) | ✅ DONE |
| Platform Routes: admin group + API group + callback route | ✅ DONE |
| Customer API: PlatformApiService::themeBundles/themeBundle/purchaseBundle | ✅ DONE |
| Customer Controller: ThemeBundleController (index/show/purchase + cache) | ✅ DONE |
| Customer Views: index (featured banner + grid), show (detail + pricing sidebar) | ✅ DONE |
| Customer Routes: theme-bundles group in admin routes | ✅ DONE |
| Customer AdminMenu: theme_marketplace + theme_bundles wired (gated on platform_url) | ✅ DONE |
| Sidebar icons: store (marketplace), grid (bundles) | ✅ DONE |
| Both repos deployed | ✅ DONE |

---

## COMPLETE: GLOBAL DATE LOCALIZATION AUDIT — 2026-06-20 ✅

**Status:** DATE_LOCALIZATION_CERTIFIED (2026-06-20)

| Phase | Status |
|-------|--------|
| diffForHumansFaStatic() in CoreJalali | ✅ DONE |
| diffForHumansFa() proxy in App\Helpers\Jalali | ✅ DONE |
| Fix 5 CRITICAL findings in controllers (saas-platform) | ✅ DONE |
| Fix 52 HIGH findings in Blade templates (both repos) | ✅ DONE |
| Fix NumberHelper+Gregorian anti-pattern in 16 guide.blade.php | ✅ DONE |
| Fix diffForHumans() in all Blade files — both repos | ✅ DONE |
| Fix ExpirySmsService + ExpiryReminderService | ✅ DONE |
| Fix CSV exports — CustomerController + UserController | ✅ DONE |
| Fix customer-site CRM, EDU, inventory, webhooks, users | ✅ DONE |
| Reports: GLOBAL_DATE_LOCALIZATION_AUDIT.md + DATE_LOCALIZATION_FIX_REPORT.md | ✅ DONE |
| Deploy saas-platform | ✅ DONE |
| Deploy customer-site | ✅ DONE |

---

## COMPLETE: TB3 Sprint 12 — Theme Detail Page + My-Licenses Upgrade — 2026-06-20 ✅

**Status:** COMPLETE (2026-06-20)

| Phase | Status |
|-------|--------|
| Platform API myLicenses(): is_outdated, thumbnail, version, version_notes | ✅ DONE |
| Customer: ThemeMarketplaceController::show() + cache | ✅ DONE |
| Customer: GET /{themeId} route → show named theme-marketplace.show | ✅ DONE |
| Customer: admin/theme-marketplace/show.blade.php (hero, pricing sidebar, screenshots, CTA) | ✅ DONE |
| Customer: my-licenses upgraded (thumbnails, update banner, amber CTA for outdated) | ✅ DONE |
| Customer: index — theme names link to detail page | ✅ DONE |

---

## COMPLETE: TB3 Sprint 11 — Marketplace API + Customer UI — 2026-06-20 ✅

**Status:** COMPLETE (2026-06-20)

| Phase | Status |
|-------|--------|
| Platform API formatTheme(): Sprint 10 fields (category/badges/quality/pricing/preview_url) | ✅ DONE |
| Platform API download(): records install + installed_theme_version + bestseller auto-promote | ✅ DONE |
| Customer: ThemeCategoryHelper service (15 categories) | ✅ DONE |
| Customer: index rewritten (Alpine.js filter+search, quality score, pricing tiers, all badges) | ✅ DONE |

---

## COMPLETE: TB3 Sprint 10 — Professional Marketplace Themes — 2026-06-20 ✅

**Status:** CRITICAL+HIGH GAPS RESOLVED. LOW gaps pending.

| Phase | Status |
|-------|--------|
| GAP-001 CRITICAL: MarketplaceThemeSeedCommand (12 themes / 15 categories) | ✅ DONE |
| GAP-002 CRITICAL: Migration fix (raw SQL + hasColumn guards) | ✅ DONE |
| GAP-003 CRITICAL: MarketplaceSeedScoresCommand (wire quality service) | ✅ DONE |
| BRK-001: ThemeMarketplaceQualityService wired to controller | ✅ DONE |
| BRK-002: installs_count increment endpoint + auto-bestseller | ✅ DONE |
| BRK-003: Backfill existing themes with category field | ✅ DONE |
| GAP-004 HIGH: Scoring Dashboard (/admin/theme-marketplace/scoring) | ✅ DONE |
| GAP-005 HIGH: Marketplace search (name/slug filter) | ✅ DONE |
| GAP-006 HIGH: Category filter dropdown in admin index | ✅ DONE |
| GAP-007 MEDIUM: Bestseller badge + auto-promote logic | ✅ DONE |
| GAP-008 MEDIUM: New release badge display | ✅ DONE |
| GAP-009 MEDIUM: Annual/Lifetime License Pricing UI | ✅ DONE (form inputs + validation + persist) |
| GAP-010 LOW: Theme Bundle System | ✅ DONE (2026-06-20) |
| GAP-011 LOW: Theme Update Notification System | ✅ DONE (2026-06-20 — installed_theme_version + outdatedLicensesCount) |
| GAP-012 LOW: Demo Data Install/Reset | ✅ DONE (ThemeDemoDataService + manifest UI in preview page) |
| GAP-013 LOW: Marketplace Preview Page | ✅ DONE (/admin/theme-marketplace/{theme}/preview) |

**ALL GAPS RESOLVED — including GAP-010 (Bundle System) and GAP-011 (Update Notifications).**

**TB3 SPRINT 10 STATUS: TB3_SPRINT10_FULLY_CERTIFIED** (2026-06-20)

---

## COMPLETE: TB3 Sprint 9.5 — Visual Reverse Engineering Engine — 2026-06-20 ✅

**Status:** CERTIFIED COMPLETE (2026-06-20) — TB3_SPRINT9_5_CERTIFIED

| Phase | Status |
|-------|--------|
| Migration: reverse_engineering_jobs | ✅ DONE |
| Model: ReverseEngineeringJob (8 statuses, 4 input types) | ✅ DONE |
| Phase 1: DomExtractionService (DOMXPath, 12 components) | ✅ DONE |
| Phase 2: LayoutReconstructionService (columns, spacing, flow graph) | ✅ DONE |
| Phase 3: VisualAnalysisService (colors, typography, buttons, radius, shadow) | ✅ DONE |
| Phase 4: CommerceAnalysisService (prices, cards, gallery, reviews) | ✅ DONE |
| Phase 5: SimilarityEngineService (4 dims × 6 sites, Euclidean RGB) | ✅ DONE |
| Phase 6: ReverseThemeReconstructorService (AI+PHP fallback, ThemePack) | ✅ DONE |
| Phase 7: ReverseEngineeringQualityService (10 checks, PASS/FAIL/PARTIAL) | ✅ DONE |
| Orchestrator: VisualReverseEngineeringService | ✅ DONE |
| Controller: ThemeReverseEngineeringController (6 actions) | ✅ DONE |
| View: reverse-engineer.blade.php (4-tab form, progress animation) | ✅ DONE |
| View: reverse-engineer-result.blade.php (score dashboard, install) | ✅ DONE |
| Routes (6 endpoints under /admin/theme-builder/reverse-engineer) | ✅ DONE |
| Docs: VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md | ✅ DONE |
| Docs: SIMILARITY_SCORING_ENGINE.md | ✅ DONE |
| Docs: THEME_RECONSTRUCTION_PIPELINE.md | ✅ DONE |
| Docs: TB3_SPRINT9_5_CERTIFICATION.md | ✅ DONE |
| Deploy saas-platform | ✅ DONE |

---

## COMPLETE: TB-AI Studio + Import Pipeline — 2026-06-20 ✅

**Status:** CERTIFIED COMPLETE (2026-06-20) — TB_AI_STUDIO_CERTIFIED

| Phase | Status |
|-------|--------|
| TB-AI Studio: composer.json, config.php, .env.example | ✅ DONE |
| Providers: Claude, OpenAI, DeepSeek, Custom + ProviderFactory | ✅ DONE |
| Analyzers: HtmlAnalyzer, SectionDetector, VisionAnalyzer | ✅ DONE |
| Generator: ThemePackGenerator + ZipExporter | ✅ DONE |
| Uploader: SaasPlatformUploader (Bearer + HMAC) | ✅ DONE |
| UI: 3-step Alpine.js (layout.php + home.php) | ✅ DONE |
| Platform: AuthenticateStudioApi middleware | ✅ DONE |
| Platform: ThemePackStudioController (5 API endpoints) | ✅ DONE |
| Platform: ThemePackStudioAdminController + 2 Blade views | ✅ DONE |
| Platform: routes/api.php + routes/web.php + Kernel.php + services.php | ✅ DONE |
| Docs: TB_AI_STUDIO_ARCHITECTURE.md | ✅ DONE |
| Docs: THEME_PACK_IMPORT_PIPELINE.md | ✅ DONE |
| Docs: LOCAL_THEME_GENERATION_WORKFLOW.md | ✅ DONE |
| Deploy saas-platform | ✅ DONE |

**Pending (developer action required):**
- `composer install` in `c:\laragon\www\tb-ai-studio\`
- Set `STUDIO_API_KEY` in production `.env`

---

## COMPLETE: TB3 Sprint 8 — AI Theme Generator — 2026-06-20 ✅

**Status:** CERTIFIED COMPLETE (2026-06-20) — TB3_SPRINT8_CERTIFIED

| Phase | Status |
|-------|--------|
| Migration: ai_generation_jobs | ✅ DONE |
| Model: AiGenerationJob | ✅ DONE |
| ThemeSectionDetectorService (11 sections, heuristic + Vision) | ✅ DONE |
| ThemePlaceholderService (SVG placeholders, Persian text banks) | ✅ DONE |
| ThemeAiGeneratorService (URL/Screenshot/HTML/Prompt pipelines) | ✅ DONE |
| ThemeAiGeneratorController (detect/generate/result/export/install) | ✅ DONE |
| ai-generator.blade.php (5-step Alpine.js UI, 4-tab input) | ✅ DONE |
| ai-generator-result.blade.php (result + actions) | ✅ DONE |
| Routes (6 new routes, import in web.php) | ✅ DONE |
| Theme Builder index — new "مولد هوشمند" button | ✅ DONE |
| TB3_SPRINT8_CERTIFICATION.md | ✅ DONE |
| Deploy both repos | ✅ DONE |

---

## COMPLETE: TB3 Sprint 6 — Professional Template Library — 2026-06-19 ✅

**Status:** CERTIFIED COMPLETE (2026-06-19) — TB3_SPRINT6_CERTIFIED

| Phase | Status |
|-------|--------|
| Migrations (theme_templates + theme_packs + site_theme_templates) | ✅ DONE |
| Models (ThemeTemplate + ThemePack + SiteThemeTemplate) | ✅ DONE |
| Services (ThemeTemplateService + ImportExport + DemoData + SiteThemeTemplateService) | ✅ DONE |
| Admin views — theme-templates (index/create/edit/show) | ✅ DONE |
| Admin views — theme-packs (index/create/show) | ✅ DONE |
| Canvas API endpoints — 3 endpoints × 2 repos | ✅ DONE |
| Canvas UI — Template Library modal + Save As Template modal × 2 repos | ✅ DONE |
| Routes + AdminMenu | ✅ DONE |
| Deploy both repos | ✅ DONE |
| TB3_SPRINT6_CERTIFICATION.md | ✅ DONE |

---

## COMPLETE: LIFECYCLE-1 — Customer Site Lifecycle Management System — 2026-06-19 ✅

**Status:** COMPLETE (2026-06-19) — both repos updated

| Sub-task | Status |
|----------|--------|
| 3 migrations (sites lifecycle columns, logs, invoices) | ✅ DONE |
| SiteLifecycleLog + SiteLifecycleInvoice models; Site model methods | ✅ DONE |
| SiteLifecycleService (state machine + push to customer-site DB) | ✅ DONE |
| SiteArchiveService (ZIP archive package) | ✅ DONE |
| SiteLifecycleNotificationService (SMS + dashboard) | ✅ DONE |
| 4 scheduler commands (check-expired/grace/suspended/archived) | ✅ DONE |
| Kernel.php — 4 daily schedule entries | ✅ DONE |
| SiteLifecycleController (7 actions) | ✅ DONE |
| 3 admin views (overview/index/settings) | ✅ DONE |
| Routes in web.php (7 lifecycle routes) | ✅ DONE |
| AdminMenu + sidebar icons | ✅ DONE |
| config/lifecycle.php | ✅ DONE |
| customer-site: CheckSiteLifecycle middleware | ✅ DONE |
| customer-site: bootstrap/app.php middleware registration | ✅ DONE |
| customer-site: suspended.blade.php (professional 503 page) | ✅ DONE |
| customer-site: lifecycle-widget.blade.php (dashboard component) | ✅ DONE |
| customer-site: dashboard.blade.php — @include widget | ✅ DONE |
| CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md | ✅ DONE |

**⚠️ PENDING PRODUCTION STEP:**
```bash
# Run migrations on production:
php artisan migrate
```

---

## COMPLETE: TB3 Sprint 3 — Inline Editing + Advanced Style + Media Manager — 2026-06-19 ✅

**Status:** COMPLETE (2026-06-19) — both repos updated and deployed

| Sub-task | Status |
|----------|--------|
| TB3-04: `editableAttr()` PHP + dblclick inline editing in iframe JS | ✅ DONE (both repos) |
| TB3-05: 3-tab panel (محتوا/استایل/پیشرفته), 6 style sections, Advanced tab | ✅ DONE (both repos) |
| TB3-07: Media Manager — upload/library/delete, ThemeMediaController, 3 routes | ✅ DONE (both repos) |
| `applyBlockStyle()` expanded to 20+ CSS properties | ✅ DONE (both repos) |
| Visibility classes, animation @keyframes in iframe CSS | ✅ DONE (both repos) |
| Deploy both repos | ✅ DONE |

---

## COMPLETE: TB3 Sprint 2 — Undo/Redo + Duplication + Per-Block Style — 2026-06-16 ✅

**Status:** COMPLETE (2026-06-16) — both repos updated and deployed

| Sub-task | Status |
|----------|--------|
| Undo/Redo history array (25 entries, Ctrl+Z/Y) | ✅ DONE |
| Block duplication (duplicateBlock + new ID + insert after) | ✅ DONE |
| 4-button action bar (up/down/duplicate/delete) | ✅ DONE |
| Content/Style tabs in props panel | ✅ DONE |
| Per-block style tab (bgColor, textColor, paddingY, paddingX, borderRadius, opacity, reset) | ✅ DONE |
| `applyBlockStyle()` in PHP BlockRenderer | ✅ DONE |
| Mirror to customer-site canvas-editor.blade.php | ✅ DONE |
| Commit + Deploy (both repos) | ✅ DONE |

**Potential Sprint 3 items:** Nested columns, typography overrides, block copy/paste across pages, export theme ZIP

---

## COMPLETE: Production Safety Hardening — CERTIFIED 2026-06-19 ✅

**Status:** PRODUCTION_CERTIFIED — Independent re-audit complete. All 12 PROD findings verified. 1 new critical finding (cache health) discovered and fixed during audit.

| Finding | Severity | Resolution |
|---------|----------|-----------|
| PROD-001 Health endpoint disclosure | CRITICAL | PASS — confirmed metadata stripped in production |
| PROD-002 MHC migrations | CRITICAL | PASS — all migrations applied batch 10 |
| PROD-003/004/005 Storage ops | HIGH | PASS — StorageManager boundary enforced, all exceptions documented |
| PROD-006 site_id indexes | MEDIUM | PASS — verified |
| PROD-007 OPcache / PHP-FPM restart | MEDIUM | PASS — `systemctl restart php8.3-fpm` in deploy.sh |
| PROD-008 Sanctum domains | MEDIUM | PASS — `SANCTUM_STATEFUL_DOMAINS` confirmed in production .env |
| PROD-009 Cache driver assertion | MEDIUM | PASS — AppServiceProvider throws if `CACHE_DRIVER=file` in production |
| PROD-010 Redis active | LOW | PASS — Redis 7.0.15 running, DB1 accessible |
| PROD-011 KB menu | LOW | PASS — AdminMenu.php confirmed |
| PROD-012 Dead code / migrations | LOW | PASS — nothing to migrate |
| NEW-001 Cache health false-negative | CRITICAL | FIXED — string sentinel replaces strict int compare in checkCache() |
| NEW-002 ZIP creation intermittent | LOW | DOCUMENTED — non-structural, workaround via direct SCP |
| NEW-003 deploy.sh rsync source | LOW | DOCUMENTED — redundant safety net, primary sync (zip) works |

**Certification Report:** `docs/PRODUCTION_CERTIFICATION_AUDIT.md`

---

## COMPLETE: TB3 Sprint 1 — Live Iframe Canvas — 2026-06-16 ✅

**Status:** COMPLETE (2026-06-16)

| Sub-task | Status |
|----------|--------|
| TB3-01: block.props canonical (data→props normalizer in both repos) | ✅ DONE |
| TB3-02: `wrapEditorMode()` in BlockRenderer + ThemeCanvasRenderer | ✅ DONE |
| TB3-02: `livePreview` + `renderBlockAjax` endpoints (both repos) | ✅ DONE |
| TB3-02: `canvas-live-preview.blade.php` (platform + customer-site) | ✅ DONE |
| TB3-02: Platform `canvas.blade.php` rewritten (iframe + device toggle) | ✅ DONE |
| TB3-02: Customer-site `canvas-editor.blade.php` rewritten (iframe) | ✅ DONE |
| TB3-03: Library items draggable + drop overlay + drop zones | ✅ DONE |
| Deploy to production | ✅ DONE |

**Pending (Sprint 2):** TB3-04 (nested columns), TB3-05 (block styles), TB3-06 through TB3-15 — awaiting user go-ahead.

---

## COMPLETE: MHC — Monitoring & Health Center — 2026-06-15 ✅

**Status:** COMPLETE (2026-06-15)  
**Scope:** Full enterprise monitoring + incident management for saas-platform

| Sub-task | Status |
|----------|--------|
| MHC-1: 6 migrations | ✅ DONE |
| MHC-2: 5 models (InfraIncident rewritten, 4 new) | ✅ DONE |
| MHC-3: 7 services (3 new, 4 extended) | ✅ DONE |
| MHC-3: 2 commands (MonitorPlatform new, MonitorSites rewritten) | ✅ DONE |
| MHC-5: Incident UI (index + show with timeline/lifecycle) | ✅ DONE |
| MHC-4: MonitoringController extended (show + platformHistory) | ✅ DONE |
| MHC-4: monitoring/index.blade.php extended (SMS/AI/Storage/Scheduler/Backup) | ✅ DONE |
| MHC-4: monitoring/site.blade.php created (uptime + drilldown) | ✅ DONE |
| MHC-6: Routes (incident routes + site drilldown + platform-history API) | ✅ DONE |
| MHC-6: Kernel.php (monitor:platform every 2 min) | ✅ DONE |
| MHC-6: customer-site /api/health extended (failed_jobs + disk + memory) | ✅ DONE |
| AdminMenu: incidents key + monitoring_center label + sidebar icons | ✅ DONE |
| Deploy to production | ✅ DONE |

---

## COMPLETE: EDU-INFRA Audit — Platform vs Customer Module Separation — 2026-06-15 ✅

**Status:** COMPLETE (2026-06-15)  
**Scope:** Full architecture audit of all modules across both repositories.

### Findings
| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| ARC-001 | CRITICAL | `forms_submissions` in Platform Admin menu — route never registered in platform mode | FIXED |
| ARC-002 | LOW | KB routes registered but absent from AdminMenu — tech debt, not misclassification | NOTED |
| ARC-003 | LOW | Orphaned `FormFieldController`/`FormSiteController`/`FormSubmissionController` in saas-platform with no routes | NOTED |

### Changes Made
1. `app/Helpers/AdminMenu.php` — removed `forms_submissions` from MENU_KEYS, MENU_ROUTES, MENU_ROUTE_PATTERNS, getGroups()
2. `resources/views/admin/partials/sidebar-icon.blade.php` — removed `forms_submissions` icon block
3. `resources/views/admin/partials/sidebar-icon-bare.blade.php` — removed `forms_submissions` icon block

### Result
All 30+ modules correctly classified. Full classification table in `AUDIT_HISTORY.md`.

---

## COMPLETE: 5-Phase Business Features Batch — 2026-06-15 ✅

| Phase | Description | Status |
|-------|-------------|--------|
| CRM | crm_contacts + crm_deals + crm_activities + CrmService + CrmController + 5 views + Kanban pipeline | ✅ DONE |
| Analytics | Already existed (AnalyticsDashboardController + Chart.js) | ✅ CONFIRMED |
| Customer Portal | DashboardController + account/dashboard enhanced with course progress + installments | ✅ DONE |
| Blog/SEO | Already existed (OG meta + JSON-LD + sitemap) | ✅ CONFIRMED |
| Inventory | warehouses + inventory_items + stock_movements + InventoryService + InventoryController + 6 views | ✅ DONE |
| Platform Admin | Module Adoption overview (6 KPI cards, site lists, recent activations) in saas-platform | ✅ DONE |

---

## COMPLETE: 5-Module Batch — 2026-06-13 ✅

| Module | Description | Status |
|--------|-------------|--------|
| INSTALL | خرید قسطی — migrations, models, InstallmentService, checkout, account panel, admin panel | ✅ DONE |
| LANG | چند زبانه AI — HasTranslations trait, ContentTranslationService (Claude Haiku), queue, middleware, admin | ✅ DONE |
| VPLAY | پخش فیلم حرفه‌ای — HomepageVideo, admin CRUD, video-player component, canvas block | ✅ DONE |
| FORM-EXT | لیست سفید Excel — AccessList/Gate models, AccessListImportService, public verify, admin UI (customer-site only) | ✅ DONE |
| IMG | آپلود هوشمند — SmartImageUploadService, smart-image-picker component, applied to posts/products/courses | ✅ DONE |

---

---

## COMPLETED EPIC: EDU-16 — Human Review & Approval Workflow ✅

**Status:** COMPLETE (2026-06-06)  
**Architectural note:** `pending_review` status eliminated. Final state machine: `draft → published (approve) | archived (reject)`.  
**Delivered:**
- Migration: `reviewed_by_id`, `reviewed_at`, `review_note` + composite index
- `POST .../approve` + `POST .../reject` with Gate + policy checks
- `EducationDraftReviewService` bound via `EducationDraftReviewServiceInterface`
- Admin review dashboard (RTL, Vazirmatn, FIFO queue, Alpine inline actions)
- `EducationGeneratedContentController`: removed `pending_review` key; analytics uses `reviewed_at IS NOT NULL`
- 30 new tests (DraftReviewServiceTest + ReviewEndpointTest)
- KI-001: confirmed wired in all 4 commands from EDU-15.2

---

## CERTIFIED EPIC: EDU-17 — Live AI Provider Integration ✅

**Status:** CERTIFIED COMPLETE (2026-06-07)  
**Certification:** EDU17_CERTIFIED_COMPLETE  
**Tests:** 57 passed / 0 failed / 77 assertions  
**Validation score:** 100/100

### Commit Evidence

| Phase | Commit | Description |
|-------|--------|-------------|
| Phase 2 — Circuit Breaker | `da0cbbc` | feat(edu-17): add circuit breaker and Persian prompt builder |
| Phase 3 — Persian Prompt Builder | `da0cbbc` | feat(edu-17): add circuit breaker and Persian prompt builder |
| Phase 4 — TenantAiQuota + Integration | `18e218c` | feat(edu-17): complete AI provider integration — Phases 2-5 |
| Phase 5 — Tests (mocked HTTP) | `18e218c` | feat(edu-17): complete AI provider integration — Phases 2-5 |

### Task List

| # | Task | Status |
|---|------|--------|
| 1 | Implement real AI provider adapter (Claude + OpenAI) for education | ✅ Done (prev session) |
| 2 | Wire `EDUCATION_AI_PROVIDER` env to `EducationServiceProvider` binding | ✅ Done (prev session) |
| 3 | Connect to CircuitBreakerAiContentProvider (CLOSED/OPEN/HALF_OPEN) | ✅ Done (prev session) |
| 4 | Set rate limits: `TenantAiQuotaManager` integration | ✅ Done (2026-06-07) |
| 5 | Test real generation round-trip with mocked HTTP (no live API calls in CI) | ✅ Done — 57/57 pass |

### Delivered

- `education.ai.quota.*` config section added (`EDUCATION_AI_QUOTA_ENABLED`, `EDUCATION_AI_QUOTA_DEFAULT_DAILY`)
- `CircuitBreakerAiContentProvider`: optional `TenantAiQuotaManager` injected — quota check before circuit, increment after success
- `ClaudeAiContentProvider`: real Claude API HTTP adapter
- `PersianEducationPromptBuilder`: Persian-language prompt construction
- `EducationServiceProvider`: env-driven provider selection, `TenantAiQuotaManager` injected when quota enabled
- `TenantAiQuotaIntegrationTest.php`: 5 tests (quota disabled, within limit, exceeded, key format, null siteId)
- 57/57 AI provider tests pass (CircuitBreaker + Claude HTTP + Quota + NullProvider)

### Technical Debt — EDU-17

| ID | Severity | Description | Action |
|----|----------|-------------|--------|
| M-01 | LOW | `education:validate` does not check EDU-17-specific components (ClaudeAiContentProvider, CircuitBreaker config, PersianEducationPromptBuilder registration, EDU-17 test presence) | Add EDU-17 checks to validation command in a future maintenance pass — do NOT reopen EDU-17 |

---

## COMPLETED: Subscription UX — Auto-renew Toggle + Post-expiry CTA ✅ (2026-06-08)

**Auto-renew:** toggleAutoRenew() controller method + route + toggle button in show/index views (badge).
**Post-expiry:** amber warning bar on index + expired CTA card on show page + "تمدید" row button in history.
**Admin CSV:** already existed; verified.
**Tests:** 13/13 pass.

---

## COMPLETED: Invoice PDF + Subscription Webhook ✅ (2026-06-08)

**Invoice PDF:** barryvdh/laravel-dompdf, RTL template, front+admin download routes. 5/5 tests.
**Subscription Webhook:** webhook_endpoints + webhook_events tables, WebhookDispatchService, DispatchWebhookJob (HMAC-signed, 3 retries), SubscriptionService integration (6 events), admin CRUD UI. 16/16 tests.
**Time-based Coupons:** Already complete — starts_at/expires_at exist and are validated.
**Total tests:** 406/406 pass.

---

## COMPLETED: 8.3b DB Backup + 52 Test Fixes ✅ (2026-06-08)

**8.3b:** BackupDatabaseCommand — daily 02:00, retention, gzip, integrity check. 8/8 tests.
**Test fixes:** 52 → 0 failures — Tag::posts(), coupons view, CaptchaService static calls, customer_mobile, SoftDeletes migrations (tags, forms), FormService slug, FormController redirect, FormFieldRequest validation, forms breadcrumb+guide.
**Result:** 385/385 pass, 0 failures, installed.lock preserved.

---

## COMPLETED: Admin Subscription Panel ✅ (2026-06-07)

**Status:** COMPLETE — 12/12 tests, installed.lock fix delivered
- force-extend (N days) + manual plan-change + group member remove
- Tests: AdminSubscriberActionsTest — 12 passed / 32 assertions
- Bug fixed: TestCase.php now preserves installed.lock across test runs

---

## COMPLETED: SaaS Infrastructure Foundation ✅ (2026-06-08)

**Infrastructure Audit + Readiness:**
- 23 findings (CRITICAL/HIGH/MEDIUM/LOW) — see `docs/infrastructure/INFRASTRUCTURE_READINESS_REPORT.md`
- CORS fixed (F1.3) — now env-driven via `CORS_ALLOWED_ORIGINS`
- SMS failover: KavenegarGateway + GhasedakGateway + FailoverSmsGateway deployed
- 5 deployment scenarios designed — see `docs/infrastructure/DEPLOYMENT_ARCHITECTURE.md`

**Infrastructure Management Module (admin panel):**
- 5 migrations: infra_servers, infra_domains, infra_hosting, infra_storage_providers, infra_database_servers
- 5 models, 6 controllers, 14 Blade views (RTL, Persian, professional)
- 31 routes under `admin/infrastructure/*`
- Menu: group `زیرساخت / DevOps` in admin sidebar

**Remaining from audit (open):**
- F2.1: PlatformSettings.php — use Storage facade for logo uploads (INFRA-2)
- F2.3–F2.6: CSV export services — use Storage streams (INFRA-2)
- F2.2: InstallController — .env file_put_contents (INFRA-3)
- `UPLOAD_DISK` / `EXPORT_DISK` env vars (INFRA-2)
- Deploy migrations to production: `php artisan migrate` (5 new tables)

---

## COMPLETED: CACHE-1 — Redis Cache Architecture ✅ (2026-06-08)

**Status:** CERTIFIED COMPLETE  
**Tests:** 15/15 CacheManager tests pass. Zero regressions from CACHE-1.

**Delivered:**
- `app/Services/Infrastructure/CacheManager.php` — central cache abstraction
  - `key($name, ?$siteId)` — tenant-isolated key builder
  - `store($name)` — named store access
  - `remember/forget/put/get/flushStore` — per-store ops (never global flush)
- `config/database.php` — 7 new Redis connections (DB2–DB8) for named stores
- `config/cache.php` — 6 named stores: users(DB4), customers(DB5), themes(DB6), tickets(DB7), modules(DB8), rate_limit(DB3)
- Eliminated all `Cache::flush()` calls — replaced with `Cache::store('X')->flush()`
- Migrated all cache `remember/forget` calls to named stores:
  - `UserRepository` + `UserService` → `Cache::store('users')`
  - `CustomerRepository` + `CustomerService` → `Cache::store('customers')`
  - `SupportTicketRepository` → `Cache::store('tickets')`
  - `ModuleFeatureService` → `Cache::store('modules')`
  - `ThemeProfileManager` → `Cache::store('themes')`
- `tests/Feature/Infrastructure/CacheManagerTest.php` — 15 tests

**Architecture:** Each named store = own Redis DB → `flushStore('users')` only flushes DB4, never touches DB5+ 

---

## COMPLETED: Public Learning Portal (customer-site) ✅ (2026-06-11)

**پورتال یادگیری عمومی در customer-site**

- `CourseClassCompletion` migration + model (course_class_completions جدول)
- `Front\CourseController` — 6 action: index, show, enroll, myLearning, learn, markComplete
- 4 view کامل: courses/index, courses/show, courses/learn, courses/my-learning
- Routes زیر `module:courses` middleware (13 route)
- `SiteHelper::coursesEnabled()` helper
- `MODULE_COURSES_ENABLED=true` در production env
- 423/423 tests pass
- Deploy شد به سرور production

**URL های جدید:**
- `GET /courses` — لیست دوره‌های فعال
- `GET /courses/{slug}` — جزئیات دوره
- `POST /courses/{slug}/enroll` — ثبت‌نام (auth لازم، رایگان = فوری)
- `GET /my-learning` — داشبورد دانشجو
- `GET /courses/{slug}/learn/{classOrder?}` — محیط یادگیری
- `POST /courses/{slug}/learn/{classId}/complete` — تکمیل جلسه (AJAX)

---

## COMPLETED: Platform Site Agent ✅ (2026-06-11)

**ادمین پلتفرم در سایت مشتری — کامل شد**

- `platform_site_agents` جدول در saas-platform
- `PlatformSiteAgentService` — اتصال داینامیک به DB مشتری + provision/revoke/log
- UI کامل در `/admin/sites/{site}/agent`
- `is_platform_agent` column در customer-site + UserObserver (deletion guard)
- لاگ دوطرفه: platform (activity_logs) + customer (admin_activity_log)
- نمایش رمز عبور (AJAX, یک‌بار) + بازنشانی رمز
- دکمه "ادمین پلتفرم" در صفحه ویرایش سایت

**Migration لازم:**
```bash
# saas-platform:
php artisan migrate

# customer-site (هر سایت مشتری باید اجرا شود):
php artisan migrate
```

---

## INFRASTRUCTURE BACKLOG

### Phase 8.x Remaining

| ID | Task | Priority |
|----|------|----------|
| 8.3a | Private Satis/Packagist for package distribution | ✅ DONE (2026-06-11) |
| 8.3b | Automated DB backup before deploy | ✅ DONE (2026-06-11) |
| 8.3c | GitHub Actions CI pipeline | ✅ DONE (2026-06-07) |
| 8.3d | Slack webhook alerting for deployment/anomaly events | ✅ DONE (2026-06-11) |
| 8.4a | DB indexes on site_id columns (all tables) | ✅ DONE (2026-06-07) |
| 8.4b | Cache for domain resolution in TenantContext | ✅ DONE (CACHE-1) |
| 8.4c | External alerting: critical anomaly → Slack | ✅ DONE (2026-06-11) — via SlackAlertService |
| 8.4d | OpenTelemetry export to Jaeger/Tempo | ✅ DONE (2026-06-11) |
| CACHE-1 | Redis named stores + CacheManager + no global flush | ✅ DONE (2026-06-08) |

### Infrastructure Remediation Backlog (from INFRA audit 2026-06-08)

| ID | Task | Priority |
|----|------|----------|
| INFRA-1 | Production .env setup checklist + run 5 new migrations | Critical |
| INFRA-2 | Storage facade migration (PlatformSettings, CSV exports) | ✅ DONE (pre-existing + verified 2026-06-11) |
| INFRA-3 | InstallController .env safety + LocalAI endpoint guard | ✅ DONE (2026-06-11) |
| INFRA-4 | DB backup command + scheduler integration | ✅ DONE (2026-06-11) |
| INFRA-5 | SMS delivery statistics dashboard | ✅ DONE (2026-06-11) |
| INFRA-6 | Remote Agent Layer (Scenario E) | ✅ DONE (2026-06-11) |

---

## MOBILE BACKLOG

| ID | Task | Priority |
|----|------|----------|
| M2.1-a | File attachment upload flow | ✅ DONE (2026-06-11) |
| M2.1-b | iOS code signing pipeline | ✅ DONE (2026-06-11) |
| M2.1-c | Firebase FCM injection | ✅ DONE (2026-06-11) |
| M2.1-d | Deep-link routing | ✅ DONE (2026-06-11) |
| M2.1-e | AppImage packaging (Linux) | Low |
| M2.1-f | System tray icon assets | Low |

---

## FRONTEND BACKLOG

| ID | Task | Priority |
|----|------|----------|
| FE-01 | Wire x-core-ui::navigation-item/sidebar-group into layout files | ✅ DONE (2026-06-11) |
| FE-02 | Retrofit admin views to x-core-ui::button | ✅ DONE (2026-06-11) |
| FE-03 | Resolve Tailwind v4 shadow-var optimizer warnings | ✅ DONE (2026-06-11) |
| FE-04 | Remaining raw controls in forms/analytics, forms/create, forms/submissions | ✅ DONE (2026-06-11) |
| FE-05 | Retrofit tickets/show to x-core-ui::button | ✅ DONE (2026-06-11) |

---

## EDUCATION MODULE COMMANDS REFERENCE

All Artisan commands available for the education module:

```
education:validate               # Score module health (target: ≥ 95/100)
education:coverage               # Coverage report
education:adopt                  # Adoption analytics
education:generate-content       # Generate articles from content deficit queue
education:generate-gap-content   # Generate articles from knowledge gap queue
education:generate-articles      # ArticleDraftGenerator pipeline
education:generate-walkthroughs  # WalkthroughDraftGenerator pipeline
education:refresh-recommendations # Expire stale + regenerate
education:build-gap-queue        # Build critical/high gap remediation queue
education:build-content-deficit-queue # Build content deficit queue
education:sync-customer {id}     # Sync one customer site
education:sync-all-customers     # Sync all active sites
education:verify-sync {id}       # Verify sync state (--fix to auto-repair)
education:generate-docs          # Generate documentation
education:generate-recommendations # Generate recommendations
education:discover-features      # Discover platform features
education:coverage-report        # Full coverage report
education:migrate-kb             # Migrate legacy KB to education module
education:list-sites             # List all sites
```
