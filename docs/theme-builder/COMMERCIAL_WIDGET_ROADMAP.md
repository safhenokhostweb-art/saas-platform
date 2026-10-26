# Commercial Widget Engine — Audit & Roadmap

> Status: Phase 0.1 READY_FOR_REVIEW_LOCAL (2026-10-25). Not committed. See §5.
> Scope: `saas-platform` (shared `theme-runtime` package, admin Canvas) and
> `customer-site` (tenant runtime, Canvas editor, data-bound overrides).
> Every finding cites the file it was observed in. Re-verify before relying on
> line numbers — the working tree carried uncommitted engine edits at audit time.

---

## 1. Architecture as found

| Layer | saas-platform | customer-site |
|---|---|---|
| Block registry | `packages/.../theme-runtime/src/BlockRegistry.php` — ~125 canonical types incl. 36 `module:*` | `app/Services/ThemeCanvas/BlockRegistry.php` — **local fork of ~40 canonical types, host wins** (`+` merge at L1273), then canonical, then `ext:*` |
| Renderer | `BlockRenderer.php` (4,327 lines, `render{Studly}()` dispatch) + FoodMart/SleepShiman/Dynamic traits | `ThemeCanvas/ThemeCanvasRenderer.php` (real data: products, blog, reviews, courses…) + legacy `ThemeBuilder/BlockRenderer.php` |
| Block CSS | `resources/views/admin/theme-builder/partials/canvas-css-contract.blade.php` (348 lines) | `resources/views/partials/theme-canvas-style-contract.blade.php` (514 lines) — **~727 differing lines** |
| Package CSS/JS | `theme-runtime/resources/css/theme-runtime-contract.css`, `block-sdk.js`, `carousel.js`, `countdown_adapter.js`, `header-modern/*` | consumed |
| Editor | `resources/views/admin/theme-builder/canvas.blade.php` (3,544 lines, Alpine) | `admin/theme-design/canvas-editor.blade.php` (3,129) + `builder/edit.blade.php` (1,418) |
| Structured data | — | `ThemeCanvas/CanvasStructuredData.php` (WebPage + FAQPage JSON-LD) |

**Core finding:** ADR-029 says "ONE canonical registry", but in practice the
schema authority is split. customer-site's own comment (BlockRegistry.php
L1266–1270) admits the ~40 overlaps were never re-verified field-by-field.
customer-site has the richer, schema-driven nested repeater
(`storage => 'array'`, up to 3 levels); the platform Canvas does not. Result:
the same block edits differently depending on which host's editor is open.

---

## 2. Widget health check

### Critical (correctness / security)

| # | Where | Defect |
|---|---|---|
| C1 | `canvas.blade.php` L1077 | Repeater is hard-wired to Bento: `type === 'repeater' \|\| propKey === 'items'`. **Every** block whose prop is named `items` (testimonials_slider, timeline, faq, contact_info…) gets the Bento card editor (title/text/size/glow), not its real fields — editing corrupts the data. `item_schema` is ignored. |
| C2 | `BlockRenderer::renderBentoGrid` (uncommitted WIP) | Card `link` goes through `escapeAttr()`, not `escapeUrl()` → `javascript:` href survives. Schema types it `string`, so the Inspector link validator never runs. Also `<a>` nested inside `<a>` when both `link` and `link_url` are set. |
| C3 | `renderPricingCards` (`cta_url`), `renderLoginRegister` (`login_url`/`register_url`) | Same `escapeAttr()`-instead-of-`escapeUrl()` class. |
| C4 | `BlockRegistry` `columns.ratios` (uncommitted WIP) | Options are a list of `{label,value}` objects; the Inspector `select` template iterates `(label, val)` → shows `[object Object]` and writes `"0".."3"`. The ratios visual picker also renders, so there are **two** controls and the broken one wins on change. |
| C5 | Bento defaults (uncommitted WIP) | Default `bg_color_from: transparent` → `bg_color_to: rgba(0,0,0,.7)` always applies a dark gradient, including on light cards with dark text (`#f0fdf4` / `#14532d`) → contrast failure out of the box. `size` options `['normal','large','small']` don't match the renderer's `large/tall/featured/small` (`normal` silently becomes `small`). |
| C6 | `database/seeders/ThemeBuilderCommercialDemoSeeder.php` (untracked) | Uses block types that do not exist (`header-megamenu`, `slider-hero`, `section-pricing`, `footer-enterprise`…), wrong countdown prop (`target` vs `end_date`), non-idempotent `Theme::create`. Renders empty fallbacks. Replace it; don't fix it. |

### High (commercial quality)

- **JSON-textarea authoring** for features, stats, steps, plans, members, badges, services, logos, skills, timeline, testimonials in the canonical schema. Only `bento_grid` is a repeater on the platform side.
- **Emoji icons** (⚡🔒🚀) are the only icon system. There is no SVG icon set or icon picker.
- **Hardcoded indigo** `#4f46e5`/`#6366f1`/`#7c3aed` as defaults across hero_gradient, cta_band, newsletter, header_modern, bento, and the color-picker fallback. They should be `var(--tb-primary)`.
- **Fake interactions**: newsletter `onsubmit=preventDefault();reset()` (looks like it worked but nothing is sent), search bar/header search `action="#"`. Both are dead ends on a live site.
- **Product card**: no discount %, no rating, no add-to-cart in the canonical version. The canonical demo uses `number_format()` (Latin digits) while customer-site uses `CurrencyHelper`, so the preview and the live site disagree on digits.
- **Carousel**: arrows/dots are inline-styled white/gray with no props. RTL glyphs are inverted (`‹` sits on the right as "previous"). No swipe threshold/drag affordance is exposed, and `aria-label="اسلایدر {id}"` leaks the internal ID.
- **Pricing**: no monthly/annual toggle, and prices are free-text strings.
- **Header**: only `sticky`. There is no transparent-over-hero mode, no shrink-on-scroll, no search modal, no cart drawer. Mega-menu data normalization exists (`normalizeMegaMenuData`) but the variants aren't productized.
- **Footer**: no newsletter slot, no Enamad/Samandehi trust slot, no app-badge row.
- **FAQ**: the canonical renderer emits no JSON-LD (customer-site adds FAQPage, so the platform preview diverges). No search or category grouping.
- **Blog/testimonials (canonical)**: preview shows a one-line placeholder, so the admin preview cannot show the real design.

### Medium (polish)

- `<input type=color>` can't show `transparent`/`rgba()` (it displays `#000000`). There are no brand-token swatches and no alpha.
- Number controls ignore schema `min/max/step` (`:min="0"` is hardcoded). There are no sliders or units.
- Repeater has no reorder, duplicate, or collapse, and it runs `JSON.parse` inline per keystroke, so one malformed value throws.
- Three editors (platform Canvas, customer Canvas, legacy `builder/edit`) means three inspector implementations.

---

## 3. Typography / RTL / digits

| Item | State |
|---|---|
| Vazirmatn files | Present offline in both repos (`public/assets/fonts/vazirmatn/*.woff2`, incl. variable `Vazirmatn[wght]`) |
| customer-site runtime | ✅ preload + inline `@font-face` (`partials/vazirmatn-font-faces-inline`) |
| **platform Canvas preview** | ❌ loads **Google Fonts** (`canvas-preview.blade.php` L57–58). This is unreliable from Iran, causes FOUT, and differs from the runtime. Must use the local faces. |
| Line-height | Body text 1.85, headings 1.1–1.4 (OK for Persian). Hero split `letter-spacing:-0.02em` is wrong for Arabic-script text; remove it. |
| Persian digits | Hardcoded in label strings only. The package has **no digit/currency formatter** and can't import `App\Services\PersianFormatter` (Constitution §3), so it needs a package-local `PersianNumber` utility. |
| RTL | `dir=rtl` + logical properties mostly used. Carousel arrow glyphs are inverted, the boolean toggle needs an `rtl:` translate fix (present), and the Bento link arrow `←` is correct. |

---

## 4. Roadmap

Every work unit follows `docs/quality/TESTING_AND_DELIVERY_GOVERNANCE.md`
(Builder/Canvas row: L1 + L2 + L3 browser save→reload→preview→public +
responsive + L7) and `WORK_UNIT_DELIVERY_WORKFLOW.md`.

### Phase 0 — Foundation (blocks everything else)
- **0.1 Schema-driven repeater engine** (platform Canvas): render any `type: repeater` from its `item_schema` (string/textarea/number/boolean/select/color/image/link/icon, one nested level) with add/duplicate/reorder/collapse and safe parse. Remove the `propKey === 'items'` hijack. Stabilize the Bento/columns WIP (C2, C4, C5).
- **0.2 Schema convergence**: promote customer-site's verified repeater schemas into the canonical registry, field by field with tests. customer-site keeps only data-binding overrides.
- **0.3 One CSS contract**: move block CSS into the package (`theme-runtime/resources/css`), included by both hosts, with parity test.
- **0.4 Typography & numbers**: local Vazirmatn in the platform preview; package `PersianNumber` (digits, toman, compact `۱٫۲ میلیون`, Jalali-agnostic); `font-variant-numeric: tabular-nums` for prices/counters.
- **0.5 Tokens over hex**: every default becomes `var(--tb-*)`. The color control gets brand swatches, alpha, and `transparent`.
- **0.6 Icon system**: bundled SVG icon set (Lucide subset, MIT) + icon picker. Emoji stays as a legacy value.
- **0.7 Honest interactions**: newsletter → real endpoint (customer-site) with success/error states; search → real `/search`; preview-only behavior is labeled in the editor.

### Phase 1 — Global components
- `header_pro`: variants (classic / centered logo / split), sticky + shrink, transparent-over-hero, mega-menu, search modal (⌘K/`/`), cart-drawer trigger with live count, mobile drawer with focus trap.
- `footer_pro`: multi-column, newsletter, trust slot (Enamad/Samandehi placeholders, admin-pasted embed sanitized), socials, app badges, back-to-top.

### Phase 2 — Sliders (one hardened carousel controller)
- Controller: swipe/drag, RTL-correct arrows, arrow/dot style props (shape, position, color), keyboard, reduced-motion, lazy slides.
- Product carousel: discount % badge, custom badges, rating, add-to-cart, toman with Persian digits, real data binding.
- Blog slider: cover overlay, reading time, tags, author, Jalali date.
- Hero/media slider: video background (poster + mobile fallback), parallax, animated headline, customizable arrows/dots.

### Phase 3 — Conversion sections
- Bento v2: layout presets, hover glow/spotlight, media cards, entrance animation.
- Social proof: testimonial grid/slider/marquee, aggregate rating, logo wall.
- FAQ: categories, search, FAQPage JSON-LD in both hosts.
- Pricing: monthly/annual toggle with savings badge, highlighted plan, feature comparison table.

### Phase 4 — Commercial demo site
Replace `ThemeBuilderCommercialDemoSeeder` with an idempotent seeder that uses only registered types. Pages: Home, Shop, Product, Blog list, Blog post, About, Contact. Acceptance: Playwright 3-width visual + axe + Lighthouse (mobile) per page, save→reload→public chain.

---

## 5. Phase 0.1 delivery (2026-10-25)

**Done:** C1–C5 fixed, plus one gap the new contract test found (`list.items` was a
repeater with no fields). Constitution §16 and ADR-060 adopted.

- **Generic repeater:** the platform Canvas renders every `type: 'repeater'`
  from its `fields`. It supports add, duplicate, reorder, remove, and collapse,
  with `show_if`, undo/redo, and live preview. A guarded JSON editor covers
  schema-less `array` props.
- **Canonical repeater schemas:** `bento_grid`, `faq`, `testimonials_slider`,
  and `list`.
- **`columns.ratios`:** one schema-generated column picker (`ui: 'column_picker'`).
  The default is back to `1/2,1/2`, which matches HEAD and customer-site.
- **Bento:**
  - card links render through `escapeUrl()`, and a card never contains a
    nested `<a>`;
  - `size` is strictly allowlisted;
  - the gradient overlay applies only over a `bg_image`;
  - `bg_color` and `text_color` are allowlisted.
- **Other URLs:** pricing `cta_url` and login/register URLs render through
  `escapeUrl()`.

**Evidence:**
- `Phase01RepeaterContractTest`: 15/15.
- Theme Builder, Canvas, ThemeEngine, and Admin suites: 881 tests. The same 10
  failures as baseline, none new (pre-existing: 28→29 catalog count,
  FoodMart CRLF fixture, untracked ThemeManager/ThemeTokenRenderer WIP tests,
  one CustomerDeployment test).
- JS: 85/85.
- `tests/browser/phase01-schema-repeater.spec.ts`: 6/6 consecutive passes
  after the save-race isolation below. It covers author → save → reload →
  preview at 1366/768/390 and gates on console errors, page errors, failed
  requests, and 4xx/5xx responses.
- `phase3c-commercial-widgets.spec.ts`: still green.
- Not exercised: the customer-site public render of these blocks (it needs a
  theme installed to a tenant).

**New findings (pre-existing, not fixed here):**
- **P1 — save race:**
  - A manual `saveCurrentPage()` (`saving` guard) and an insertion-triggered
    `autoSave()` (`quickSaving` guard) can run at the same time. Both replay
    the same insert operation, so the second save gets a 422
    `Duplicate node id`. The built-in operations-free retry recovers it.
  - `markSaved(this.blocks)` after an in-flight save also marks edits made
    during that save as saved without sending them. Possible silent loss of an
    edit made mid-save.
  - Fix: one shared save mutex/queue.
- **customer-site Bento CSS:** uses `auto-rows:` (invalid) instead of
  `grid-auto-rows:`. Deferred to 0.3, because the file is mirrored into the
  FoodMart golden fixture.
- **Testimonials:**
  - `font-style: italic` on Persian text produces faux-oblique glyphs;
  - carousel arrows overlap the card edges at tablet width.
  - Target: Phase 2/3.
- **FAQ:** uses the native `details` marker with no designed open state.
  Target: Phase 3.

---

## 6. P1 delivery: single serial save lane (2026-10-26)

**Fixed:** every Canvas save goes through one `CanvasSaveQueue`. This covers
toolbar, Ctrl+S, zone switch, extension change, structural autosave, and
inline-edit autosave.

- **One save at a time:** at most one request is on the wire. A request made
  during a save joins a single follow-up run, which captures state when it
  starts.
- **Correct acknowledgement:** a run acknowledges only the snapshot and the
  operation objects it sent (`markSavedSnapshot`). `isDirty` is recomputed
  against the live tree, so a mid-flight edit stays visibly unsaved and is
  never silently dropped.
- **Retry keeps mid-flight work:** the structure-error retry no longer wipes
  operations queued mid-flight.
- **Publish:** publish aborts when the save before it fails.
- **Leaving mid-save:** a `beforeunload` guard applies only while a save is
  on the wire or queued.
- **UI:** the Save button shows `در صف ذخیره…` or `در حال ذخیره...` with
  `aria-busy`, plus a polite live status. Editing is never locked.
- **Cleanup:** removed the dead `canvasSavePayload()` and
  `CanvasDocumentState::markSaved()`.

**Evidence:**
- JS: 88/88, including 3 new queue/snapshot tests.
- `tests/browser/p1-save-queue.spec.ts`: holds every save response for 800 ms;
  passes on P1 and fails on the pre-P1 code.
- PHP suites: 881 tests, the same 10 pre-existing failures.
- Full browser suite on pre-P1 and P1, with identical failures:
  - `zones:382` and `extension-authoring` (extension output);
  - `phase3c-dynamic-widgets`;
  - `global-styles` (publish hits a 30 s PHP limit in `PlaywrightExtractionService`);
  - `reference-counter-fast-path.spec.js` (its config ignores
    `PLAYWRIGHT_CHROME_PATH`).
- PHP-shelling specs need `php` on PATH. With it, checkpoint (8/8 ×2), hero,
  theme-list, and 9/10 zones tests pass.
- Intermittent in multi-spec runs only, and each passes when re-run:
  `zones:288` (1 of 5 runs) and checkpoint export (1 of 4).

---

## 7. Phase 0.2 delivery: list definitions promoted to canonical (2026-10-26)

**Done:** all 23 customer-site list (repeater) definitions on canonical types
now exist canonically with the same `fields`/`storage` contract.

- **Promoted fields:**
  - `slider.slides` (was `type: blocks`) and `gallery.items`;
  - `module:faq_dynamic.items`;
  - features / stats / logos / steps;
  - `timeline.items` and `team_cards.members` (both `storage: json`);
  - skills / badges / plans / services;
  - `nav_header.nav_links`;
  - `nav_footer.columns` / `links` / `social_links`;
  - `about_section.features` / `stats`;
  - `service_cards.cards`, `project_cards.cards`, `contact_info.items`.
- **Defaults untouched:** every existing canonical `default` is byte-identical,
  so theme snapshots and golden fixtures are unaffected.
- **Retired:** customer-site's stale local `bento_grid` override (size and
  colours as plain text, no link/media/glow). The canonical definition is now
  the authority on both hosts.
- **Host harmonized:** `testimonials_slider.items.rating` → `number`.

**Parity mechanism:** `customer-site/tests/Feature/ThemeCanvas/CanonicalListSchemaParityTest.php`.
- Every host repeater on a canonical type must match canonical storage and
  every child control, recursively. Canonical may add fields.
- The 23 promoted fields are pinned.
- Each authored list must change the public render.
- The 11 historically-JSON fields must render byte-identically from array
  and JSON-string storage.

**Evidence:**
- Parity test: 4/4 (106 assertions).
- customer-site theme suites: 785 tests, 3 failures, identical on the true
  baseline (pre-0.1 package plus unmodified host registry):
  - `spacer` / `setMode` host-override checks;
  - an order-dependent `nexus-pro-corporate/testimonials_slider` structural
    diff that passes in isolation.
- saas-platform suites: 881 tests, the same 10 pre-existing failures.
- Browser: `phase02-promoted-lists.spec.ts` passes. It proves `storage: array`
  becomes an array on edit and `storage: json` stays a JSON string, through
  save → reload → preview. phase01, P1, and phase3c-commercial specs stay green.

**Operational notes:**
- `customer-site/vendor/saas-platform/modules-theme-runtime` is a Composer
  mirror (`symlink: false`). Package changes reach customer-site only after
  `composer reinstall saas-platform/modules-theme-runtime`. This leaves
  `composer.lock` unchanged.
- Phases 0.1, P1, and 0.2 are therefore live in customer-site only after its
  release process re-mirrors the package. Confirm that step in the
  customer-site release pipeline.
- Test isolation: run customer-site and saas-platform PHPUnit suites one at a
  time per database. A concurrent `RefreshDatabase` run corrupts the other run
  (seen as 86 spurious "Unknown column" errors).

**Remaining (next):**
- **0.2b — nested repeaters in the platform Canvas:**
  - pricing `plans[].features`;
  - footer `columns[].links`;
  - header `nav_links[].mega_columns[].items` (2 levels deep).
  - The schema is promoted, but the editor shows "unsupported" for these
    nested fields.
- **0.2c — non-list drift on the 64 other shadowed types:**
  - `heading.level`, `section.padding`, `video.*`, `button.style`, and others;
  - then retire host overrides that become identical.
- **Host string-typed controls promoted verbatim for parity:**
  - `pricing.plans[].highlight` is a string, and `(bool)'false'` is true;
  - several URL fields are typed `string`, not `link`.
  - Fix them in both hosts together.

## 8. Phase 0.2b delivery: nested list editor, link fields, strict booleans (2026-10-26)

**Done (ADR-061):**

- **Nested lists in the platform Canvas.**
  - One recursive template (`partials/canvas-repeater.blade.php`) renders every
    list depth up to the registry cap (depths 0, 1 and 2).
  - Newly editable:
    - pricing `plans[].features` (compact inline rows);
    - footer `columns[].links`;
    - the mega menu `nav_links[].mega_columns[].items`;
    - FoodMart footer links;
    - SleepShiman filter options and footer links.
  - Add, duplicate, move, remove and collapse work at every depth.
  - Lists are addressed by path (`CanvasListPath`, DOM-free and unit-tested).
    Only the ancestors on the path are copied, so sibling items keep their
    identity.
  - Each structural edit is its own undo step; field edits coalesce per item
    field.
- **Legacy scalar items** (`features: ["a", "b"]`) adopt `{text}` on first
  edit, in both editors. Previously, copying an item spread the string into
  `{0: "a", …}`.
- **`link` fields.**
  - All 21 navigational URL fields are `type: 'link'` on both hosts.
  - Renderers read them through `linkValue()` / `linkHref()`, which accepts a
    URL string or a structured `{url}` link and always passes through
    `escapeUrl()`.
  - The platform link input warns, inline, about a URL the renderer will
    refuse.
- **`highlight`** is a real boolean switch. `BlockRenderer::flag()` reads
  legacy `"false"`, `"0"` and `""` as false.
- **Security gaps closed** (these rendered `javascript:` URLs):
  - shared renderer: timeline CTA, about button, header logo and CTA,
    `services_cards`, promo banner;
  - customer-site: both social-icon renderers, plus the service-card and
    project-card overrides.
- **customer-site:**
  - its editor gains link and switch controls at every nested level;
  - `InternalLinkResolver` resolves a picked page in any top-level prop;
  - `ElementSchemaRegistry` gains a `link` validation type.

**Evidence:**

- **saas-platform:**
  - `Phase02bNestedListContractTest` 4/4;
  - JS 92/92 (4 new `CanvasListPath` tests);
  - theme suites 885 tests, the same 10 pre-existing failures.
- **customer-site:**
  - `CanonicalListSchemaParityTest` 8/8;
  - `BuilderInternalLinkingTest` 5/5;
  - theme suites 790 tests, the same 3 pre-existing failures.
- **Browser:** `phase02b-nested-lists.spec.ts` covers:
  - adding, reordering, duplicating and removing a feature in plan 2, with
    undo/redo of each structural step;
  - the highlight switch;
  - footer column collapse and the unsafe-URL warning;
  - a two-level mega menu;
  - save, reload, and the stored shapes;
  - preview at 1366, 768 and 390.

  The phase01, P1, phase02, phase3c-commercial and header-modern-fast-path
  specs stay green. The fast-path spec extracts `updateProp` by its signature,
  which now reads `updateProp(propKey, value, options)`.

**Remaining (next):**

- **0.2c:** non-list drift on the other shadowed types, then retire host
  overrides that become identical.
- **Admin-entered URLs in customer-site module blocks** still use bare
  `escapeAttr()`: the notifications-bar link and the advertisement link in
  `ThemeCanvasRenderer`. These are not Canvas fields; track them separately.
- **Older top-level button and hero URL entries** in
  `config/builder-element-schemas.php` are still validated as `string`, so a
  structured `{url}` link there is rejected (page-picker values are resolved
  first and unaffected).
