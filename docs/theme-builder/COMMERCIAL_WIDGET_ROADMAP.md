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

## 9. Phase 0.2c delivery: one block-definition authority (2026-10-26)

**Done (ADR-062):**

- **Security patch first (customer-site).** The notifications bar and the
  advertisement zone render admin-entered links through `escapeUrl()`.
  - A refused notification link renders the message as plain text.
  - A refused ad link becomes `#`.
  - `ModuleAdminLinkSafetyTest` covers 11 cases. On the pre-patch renderer,
    all 8 unsafe cases fail (`javascript:`, mixed case, `data:`, `vbscript:`).
- **Audit.** All 65 customer-site local copies of canonical types differed
  from canonical. Beyond labels, the audit found:
  - controls neither editor rendered (`spacing`-typed tokens, `blocks`);
  - options no renderer honours;
  - fields no renderer reads;
  - host-only fields the host renderers do read.

  Option lists were checked against both CSS contracts and a survey of stored
  values across 3,773 theme JSON files and both dev databases.
- **Harmonized into canonical** (rules in ADR-062):
  - 47 missing labels added;
  - `section.padding` and `grid`/`columns.gap` became spacing-scale selects;
  - `heading.level` became an H1–H6 select; `text.content` a textarea;
  - `image.align`, `button.style`, `promo_banner.style` and
    `nav_menu.location` became selects offering only honoured values;
  - `video` gains poster/autoplay/loop/controls, and `url` is a media field
    (`media_kind: video`);
  - `blog_posts` gains the host's layout and source fields;
  - `social_icons` gains a real `links` list and `style`;
  - `cta.background` became a colour control; the `module:motion` controls
    were aligned;
  - `slider` defaults to two starter slides (an inserted slider was empty).
- **Removed because no renderer reads them:**
  - host: `gallery.columns`, `product_grid.title`, `accordion.title`;
  - canonical: `courses_grid.show_instructor`, `pricing_table`
    `highlight_plan`/`show_annual_toggle`, `reviews_grid` `carousel`.
- **Renderer fixes (shared):**
  - `renderCta()` and `renderNavMenu()` no longer interpolate raw values into
    a class attribute;
  - a CTA's CSS colour is now actually applied;
  - `renderVideo()` validates `src`/`poster` and reads switches strictly.
- **Pruned.** customer-site's registry is canonical plus `HOST_PATCHES`
  (1,413 → 213 lines). The one patch is `module:shop_product_grid.source`
  without `bestselling`, since the host has no sales-backed query.
  - The host `renderVideo()` copy is deleted.
  - The social-icon override keeps only its `SocialLink` fallback.
- **Editors:**
  - both hide `blocks` fields and give `text`/`rich_text` a textarea;
  - the platform shows `motion` ids;
  - customer-site gains the guarded JSON `array` editor and media-kind
    previews;
  - customer-site's selects now show the stored value after a reload; they
    used to show the first option.

**Evidence:**

- **Effective customer-site validation contract**, snapshotted before and
  after: unchanged except `section`/`grid`/`columns.blocks` (now `mixed`) and
  `stats_row.background_image` (now `media`). Neither rejects any surveyed
  value.
- **Host vs canonical:** strictly identical (`===`, field order included) for
  all 158 offered types except the declared patch.
- **saas-platform:**
  - `Phase02cCanonicalHarmonizationTest` 9/9;
  - JS 92/92;
  - theme suites 894 tests, the same 10 pre-existing failures. All 10 also
    fail with this phase's package files reverted.
- **customer-site:**
  - `CanonicalBlockDefinitionParityTest` 6/6;
  - `ModuleAdminLinkSafetyTest` 11/11;
  - theme suites 807 tests, the same 3 pre-existing failures.
  - One regression found and fixed: an inserted slider rendered empty.
- **Browser:**
  - `phase02c-harmonized-controls.spec.ts` (platform) and
    `phase02c-host-controls.spec.js` (customer-site) cover author → save →
    reload → preview/public render at three widths, then clean up;
  - phase01, P1, phase02, phase02b, header-modern-fast-path and
    phase3c-commercial stay green;
  - customer-site's heading-level, container and save-to-public-render specs
    stay green.

**Rendering changes for stored content:**

- 4 customer-site CTAs with a hex `background` now show that colour.
- Unknown CTA tokens and `nav_menu` locations fall back to the default class.
- Unsafe video URLs render the empty state.

Everything else renders byte-identically.

**Remaining (next):**

- **Wire `bestselling`** in `renderModuleProductGrid()` to
  `ProductDataSource`'s `best_selling_30d`, then drop the host patch.
- **`gallery.columns`:** honouring it reflows live galleries (39 stored blocks
  hold `'3'`; today they use auto-fit). Ship it as a deliberate visual change.
- **Pricing annual toggle and highlighted plan** (Phase 3) need real renderer
  support before their fields return.
- **Host nested `number`/`color`/`select` fields** below depth 1 still edit as
  text inputs.

## 10. Phases 0.3 and 0.4 delivery: one block stylesheet, local fonts, Persian numbers (2026-10-27)

**Done (ADR-063):**

- **One block stylesheet.**
  - `theme-runtime/resources/css/theme-runtime-blocks.css` (357 rules) now
    holds every block's CSS.
  - The platform preview partial shrinks from 348 to 26 lines and keeps only
    `body`. customer-site's partial shrinks from 514 to 93 lines and keeps only
    its own chrome.
  - Consolidated from the live customer-site rules:
    - the 37 conflicting selectors take the live values;
    - six stale preview-only rules that out-ranked live ones are dropped
      (`.tb-hero h1|p|.tb-btn`, `.tb-cta h2|p|a`).
  - The preview `body` matches the live one: tokens, and no forced RTL.
- **Fixes:**
  - bento `auto-rows` becomes `grid-auto-rows`;
  - `hero_split` and `hero_gradient` titles have normal letter-spacing in
    RTL; the negative tracking is kept for LTR.
- **Local Vazirmatn everywhere.** The package owns the five faces
  (`theme-runtime::css.fonts`).
  - No Google Fonts in the platform Canvas preview, live iframe or editor
    layout.
  - No Google Fonts in customer-site's Canvas editor, legacy builder, pay,
    restore-pay or suspended pages. Those last three never actually loaded
    Vazirmatn: their `@import` came after other rules.
- **`Support\PersianNumber`:**
  - Persian digits both ways;
  - ٬ ٫ separators;
  - Rial→Toman, `toman()`;
  - `compact()` («۱٫۲ میلیون»);
  - an RTL-safe minus.

**Evidence:**

- **Parity harness.** Real Chromium; full computed style of every element
  and pseudo-element; 1366/768/390. It was validated by a zero-diff
  self-test.
- **customer-site live render** (229 documents: every block type plus the 29
  canonical theme snapshots):
  - the only diffs are the requested fixes, plus four blocks that were
    unstyled on live Canvas pages now getting the styling the preview always
    showed: project cards, about section, hero trust, contact-info grid;
  - the four blocks occur only in `acool-hvac-service`; the other 28 themes
    are identical.
- **Preview vs live on the same markup:**
  - before: 38,540 distinct computed-style differences across 576 element
    kinds (excluding the `--platform-primary` variable);
  - after: 48, all on `div.tb-skill-bar-fill`, from the host-only
    skill-bar rule.
- **saas-platform:**
  - `Phase03SharedStylesheetContractTest` 5/5;
  - `PersianNumberTest` 29/29;
  - theme suites 929 tests, the same 10 pre-existing failures;
  - one directory-lock flake under load passed in isolation.
- **customer-site:** theme suites 807 tests, the same 3 pre-existing
  failures. A first run collided with another session's test run on
  `customer_site_test`; the clean re-run is the figure above.
- **Browser:**
  - `phase03-shared-css-fonts` on both hosts checks:
    - no font-CDN request;
    - the five local faces, with 400/700 loaded;
    - the shared sheet on the live page and in the editor iframe;
    - normal RTL hero tracking and `grid-auto-rows`;
    - no overflow at three widths.
  - phase01, P1, phase02, phase02b, phase02c, header-modern-fast-path and
    phase3c stay green.
  - customer-site's phase02c, heading-level and save-to-public-render specs
    stay green.
  - phase01 and P1 now treat aborted local font fetches from a superseded
    preview document like the aborted document itself.

**Remaining (next):**

- **Wire `PersianNumber`** into the canonical demo prices (they still use
  `number_format()` with Latin digits).
  - Decide whether live prices move from «۱,۲۵۰,۰۰۰» to the Persian «۱٬۲۵۰٬۰۰۰»
    separators.
  - Add `tabular-nums` for prices and counters.
- **Negative tracking on Persian text** outside the hero titles:
  - `.tb-nav-header-logo`, `.tb-features-section-title` and
    `.tb-newsletter-title` (−0.01em);
  - `header-modern.css` brand (−0.025em).
- **0.5 tokens over hex** can now be done once, in the shared sheet.

## 11. Phase 0.5 delivery: tokens over hex, colour control, Persian numbers (2026-10-27)

**Done (ADR-064):**

- **CSS parity harness committed** in `tests/Support/CssParity/`:
  - `corpus.php` (now fails on any renderer warning instead of hiding it);
  - `css.php` (token modes `default`, `none` and a palette file);
  - `compare.cjs` (before/after corpora, colour normalisation);
  - `summarize.php` and a README.
- **Tokens over hex.** Shared stylesheets and renderers read
  `var(--tb-<token>, <old literal>)`:
  - borders, surfaces and muted text;
  - text on brand fills (`--tb-on-primary`);
  - urgent states (`--tb-error`);
  - indigo tints as `color-mix()` of `--tb-primary`.
- **Brand registry defaults** are tokens with fallbacks.
  `header_modern.ctaColor` stays hex, because it drives contrast maths.
- **One colour grammar**, `HtmlSanitizer::isCssColor()`, used by:
  - `BlockRenderer::cssColor()` (every colour prop, both hosts);
  - the Inspector's `normalizeColor()`.
- **Hardening:**
  - colour props can no longer break out of `style`;
  - `renderHero()` gets the CTA background contract and an allowlisted
    `align`;
  - `renderSection()` applies `var()` and `rgba()` inline instead of
    producing a broken class.
- **Colour control in both editors** (shared `canvas-color.js`):
  - eight theme swatches;
  - an opacity slider (`color-mix()` for a swatch, `rgba()` for a fixed
    colour);
  - «بی‌رنگ» (transparent);
  - a checkerboard preview resolved against the theme.

  Nested list colours get the swatches as well.
- **Persian numbers in shared renderers:**
  - demo prices «۱٬۲۵۰٬۰۰۰ تومان»;
  - counters, stat values and suffixes;
  - badges, cart and category counts, plan prices.

  These run through `PersianNumber::localize()`. Years and leading-zero
  numbers are not grouped, and `data-count` stays Latin. A host can opt out
  with the context key `digits => 'latin'`.
- **Negative tracking** is LTR-only on the four remaining Persian headings.

**Evidence:**

- **Parity harness, customer-site live render** (229 documents, 16,381
  elements per width, 1366/768/390):
  - **No tokens (legacy fallback path):** 148 distinct diffs, none of them a
    colour. All come from the four tracking fixes and from Persian digits
    changing stat-value widths.
  - **Default tokens (a theme with no palette):** diffs are exactly the
    intended alignment:
    - indigo tints follow `--tb-primary` (`#4f46e5` instead of `#6366f1`);
    - muted empty-state text uses the WCAG-AA token;
    - two fallback backgrounds move one RGB step;
    - newly inserted hero gradients end in `--tb-secondary`.
  - **Custom palette** (rose/teal): 45 element kinds now take their colour
    from the theme, and no colour change lands outside the palette.
  - **Text-on-primary defaults:** a separate run on every block type at its
    defaults found 0 diffs in both modes.
- **Preview vs live, same markup:** 48 distinct diffs across 230 platform documents, all on `div.tb-skill-bar-fill` from the host-only animation rule (unchanged since Phase 0.3).
- **Stored colours:** 118 distinct values across 19 colour fields, from both
  repos' theme JSON and both databases. `cssColor()` rejects none of them.
- **Tests:**
  - `Phase05TokensAndNumbersTest`;
  - `PersianNumberTest` (45);
  - `canvas-color.test.mjs` (6);
  - JS 98/98;
  - saas-platform theme + architecture suites 1,106 tests with the 10 known failures plus one pre-existing Architecture failure (`features_grid` Inspector type, fails identically with this phase reverted); customer-site 807 tests, the 3 known failures.
- **A bug the suites caught:** `renderHero()` read an undefined `align` key,
  which broke the quality-gate, publish and marketplace tests. It is fixed,
  with a regression test. The harness had hidden it, so it now reports
  renderer warnings.
- **Re-pinned snapshots, each proven before re-pinning:**
  - product-slider structural hashes: a HEAD-vs-new line diff shows only
    the Persian price strings and the muted-text token;
  - the Phase 3B registry golden hash: mapping the three token defaults
    back reproduces the old pin;
  - the universal-theme palette count, 98 → 116: re-derived from
    `byCategory()` for the seven palette categories.
- **Browser:** new `phase05-color-tokens` specs on both hosts (swatch, opacity, transparent, native picker; stored values; save → reload; preview/public colours; Persian prices and counters; three widths); phase03, phase02c, phase02b, phase01, P1, header-modern-fast-path, customer-site heading-level and the universal-theme editor spec stay green.

**Remaining (next):**

- **customer-site live prices** (`CurrencyHelper`) still use `,` separators.
  Moving them to «٬» is a site-wide change outside the shared renderers.
- **Not yet tokenized:**
  - `header_modern`'s colour pipeline (hex-based contrast maths);
  - the bento demo card palette;
  - the module-placeholder palette (preview only).
- **Nested colour fields** have swatches but no opacity slider yet.

## 12. Phases 0.6 and 0.7 delivery: SVG icons and picker, real newsletter and search (2026-10-27)

**Done (ADR-065, ADR-066):**

- **Open decisions settled:**
  - customer-site `CurrencyHelper` formats through `PersianNumber::format()`,
    so storefront prices use «٬» like the runtime blocks
    («۱٬۲۵۰٬۰۰۰ تومان»);
  - `header_modern` keeps its hex contrast pipeline.
- **SVG icon system (0.6):**
  - 155 Lucide icons (ISC) are vendored in
    `theme-runtime/resources/icons/catalog.json`, with Persian labels and
    keywords, in five categories (commerce, navigation, communication,
    tech, ui). The bodies are allowlisted geometry.
  - `Support\IconLibrary` and `BlockRenderer::iconHtml()` draw a catalog name
    as inline SVG (`currentColor`, 1em). Any other value, such as a stored
    emoji, renders exactly as before.
  - All 11 icon fields are `type: 'icon'` with catalog defaults, and registry
    demo data uses icon names.
  - Hard-coded 🖼️ 📦 🛒 👤 📞 ✉️ 📍 🕐 🎓 ⭐ placeholders are SVG in both
    hosts' renderers.
  - One picker (`canvas-icons.js` model, `canvas/icon-picker` markup with
    its own scoped CSS) is used at every depth of both editors. It offers:
    - Persian or English search (Arabic ی/ک folded);
    - category chips;
    - RTL arrow-key navigation;
    - Esc, which returns focus to the trigger;
    - a clear button;
    - a legacy emoji/text field.
  - Feature and service icons sit on a brand-tinted tile. Slot colours touch
    only the SVG.
- **Newsletter (0.7):**
  - customer-site `POST /newsletter/subscribe` (web/CSRF, `throttle:6,1`)
    accepts an email or an Iranian mobile in any digit script, normalised.
  - Contacts are unique, a repeat is reported, and a honeypot catches bots.
    Answers are Persian JSON for the script, with a redirect for a plain
    post.
  - The shared block posts there only when the host provides the endpoint
    (`siteEndpoints()`). The script (`blocks/site-forms.js`) adds:
    - client validation;
    - one request per submit;
    - debounced re-validation;
    - Persian toasts and an `aria-live` status line.
- **Search (0.7):**
  - `search_bar` (new `scope` prop) and the `nav_header` search
    (`search_scope`) are real GET forms against the host's routes:
    - `search.index` with `type=products|posts`, plus `search.suggest`,
      when `advanced_search` is on;
    - otherwise the shop and blog `?q=` listings.
  - On live pages they open one dialog: a centred modal on desktop, full
    screen at ≤640 px. It has:
    - a focus trap and Esc;
    - debounced suggestions with Persian prices;
    - scope buttons;
    - «مشاهدهٔ همهٔ نتایج».

    The header field folds into an icon on phones.
  - customer-site's `renderSearchBar()` copy is deleted. The Builder preview
    and the customer editor stay inert.
- **Layout fixes found on the way:**
  - the nav header had no side gutter below 80rem;
  - the newsletter field was 240 px tall on phones;
  - `search_bar` had no styles at all.

**Evidence:**

- **Parity harness, customer-site live render** (229 documents, 16,381
  elements per width, 1366/768/390):
  - **Old markup under new vs old CSS:** 13 element kinds change in both
    token modes, all intended: search-bar styling, the restored 16 px header
    gutter, the phone-only newsletter field, and the header search folding
    into its icon at ≤640 px. No icon slot changes, so stored emoji look
    exactly as before.
  - **Markup, old vs new corpus:** 103 documents change, all in expected
    places:
    - newsletter (38), search bar (23) and nav-header search (12) forms;
    - the replaced placeholder glyphs and the registry-default icons.

    Across the 221 theme documents no stored icon value changed.
- **Preview vs live, same markup:** 48 distinct diffs across 230 platform
  documents, all on `div.tb-skill-bar-fill` (the host-only rule, unchanged),
  besides the host-chrome `--platform-primary` variable.
- **Tests:**
  - `Phase06IconSystemTest`, `Phase07SiteFormsTest` (platform);
  - `NewsletterSubscriptionTest` (customer-site, 11);
  - `canvas-icons.test.mjs` and `site-forms.test.mjs`; JS 111/111;
  - saas-platform suites 1,123 tests with only the 10 known failures plus
    the pre-existing Architecture one;
  - customer-site suites 818 tests with only the 3 known failures
    (`CanonicalStructuralParityTest`'s order-dependent nexus testimonials
    diff, `ElementSdkCanonicalBoundaryTest`, `ThemeRuntimeCanonicalParityTest`).
- **Re-pinned or updated, each proven:**
  - product-slider hashes: mapping the two placeholder SVGs back to 🖼️/📦
    reproduces both old pins;
  - Inspector type lists gain `icon` (the editors render it);
  - `CanonicalStructuralParityTest` hands canonical the host's endpoints;
  - two old selectors (`button[aria-expanded]`, the Phase 0.5 token
    selector in the universal-theme spec) were made exact;
  - the install acceptance test now checks `class="tb-search-bar-wrap"`
    (the element), since the class name also appears in the shared
    stylesheet.
- **Browser** (three widths each):
  - new `phase06-icon-picker` and `phase07-site-forms` (platform);
  - new `phase06-07-icons-forms` (customer-site: real newsletter route,
    live search dialog);
  - phase01, 02, 02b, 02c, 03, 05, P1, header-modern, smoke, default
    homepage, save-to-public and the universal theme stay green.

**Remaining (next):**

- **Owner action:** record a `migration_policy.reviewed` entry for
  `2026_10_27_120000_create_newsletter_subscribers_table.php` in
  customer-site's release policy. Its `down()` drops only its own table, but
  the policy requires an owner review.
- **Stored theme content** still holds emoji icons. They render as before; an
  opt-in emoji → icon-name mapping command could upgrade them.
- **Newsletter management:** there is no admin list or export of subscribers
  yet, and no unsubscribe link.
- **Header-modern and theme-specific headers** (FoodMart, Sleep Shiman) have
  their own search chrome, not yet on the shared dialog.

## 13. Phases 1 and 2 delivery: commercial header and footer, RTL carousels, product and blog cards (2026-10-28)

**Done (ADR-067):**

- **Header (`nav_header`):**
  - smart sticky with a scroll-shrink;
  - hide on a down-scroll, back on an up-scroll;
  - transparent over a first-section hero, with automatic light or dark
    text from the hero (or a fixed colour). The hero is padded by the
    header's height.

  Everything runs on the page's banner element, so it works inside
  customer-site's `<header>` chrome, where the old inline sticky could not
  stick.
- **Mega menu:**
  - a full-width panel with a hover bridge on desktop;
  - an accordion on touch devices and phones;
  - delegated toggling (Esc, outside click, a truthful `aria-expanded`);
  - a promo CTA slot.

  The phone menu now shows the block's own links and mega menus instead
  of a fixed generic list, and keeps the cart and search icons visible.
- **Mini-cart drawer:**
  - the cart icon links to the real cart page (`/shop/cart`, previously a
    404 at `/cart`) with a live Persian badge;
  - it opens a drawer that slides in from the reading end, with lines,
    Toman totals, remove, Esc and a focus trap;
  - customer-site gains `GET /shop/cart/mini` and JSON answers from add and
    remove.
- **Footer (`nav_footer`):**
  - newsletter on the Phase 0.7 endpoint;
  - social links as icons (network chosen, or recognised from the URL;
    unknown ones keep their label);
  - Google Play, App Store, Bazaar, Myket and direct-download badges;
  - Enamad and Samandehi seals rebuilt from their ids only (a pasted
    official snippet is parsed; nothing else survives).
- **Carousel (one engine):**
  - logical-side outward arrows;
  - the arrow key on the next slide's side;
  - pointer drag for touch and mouse that follows the finger;
  - a swipe towards the reading end advances.

  The old touch handler was reversed in RTL.
- **Product card (one primitive):**
  - the real discount percentage («۲۱٪ تخفیف») and other badges via
    `badgeHtml()`;
  - a Toman price with the old price struck through;
  - a stock pill;
  - the approved-review star rating;
  - instant «افزودن به سبد خرید», which opens the drawer. Products with
    options link to their page, and out-of-stock products are disabled.
- **Blog card (one primitive):** a category pill, author avatar or
  initial, Persian date («۵ آبان ۱۴۰۵»), reading time, and grid, list or
  slider layout. The Builder preview shows labelled sample posts.

**Evidence:**

- **Parity harness** (customer-site live render, 229 documents, three
  widths; before is `83a4cdbb`):
  - **Old markup under new vs old CSS:** 24 element kinds in both token
    modes, all from this phase:
    - the mega menu anchors to the header (links `position: static`);
    - the restyled arrows;
    - the carousel grab cursor.

    No other block changes.
  - **Markup:** 206 documents change.
    - 200 sticky headers gain their `data-tb-header` flag; the other 25
      headers are identical.
    - 12 footers' social links become icons or text pills; the other 189
      footers are identical apart from whitespace.
    - Carousels change only in the arrows.
  - **Preview vs live:** 48 distinct diffs across 230 platform documents,
    all on `div.tb-skill-bar-fill` (the host-only rule, unchanged since
    Phase 0.3).
- **Tests:**
  - `Phase1HeaderFooterCarouselTest`, `ThemeCartDrawerAndCardsTest` and
    `site-header-cart.test.mjs`; JS 116/116;
  - saas-platform suites with only the known failures;
  - customer-site suites with only the known failures.
- **Browser** (three widths):
  - `phase1-2-header-footer-carousel` (platform, stub origin):
    - header transparency, shrink, hide and reveal, and hero clearance;
    - the mega menu;
    - the drawer;
    - add-to-cart;
    - RTL arrows, keys and drag;
    - the footer.
  - `phase1-2-cart-drawer-cards` (customer-site, real routes).

  The earlier specs stay green; three platform specs flaked under suite
  load and pass on an idle rerun.
- **Bugs the checks caught:**
  - an undefined `layout` key in `blogCardsLayout()`, which broke the
    publish quality gate;
  - an inline `@php(...)` that did not compile, which broke every
    customer-site public page.

  Both are fixed with regression assertions.

**Remaining (next):**

- `header_modern_v1` and the theme-specific headers (FoodMart, Sleep
  Shiman) keep their own chrome. FoodMart's Swiper carousels are LTR-only
  by design.
- There is no quantity stepper in the drawer yet (remove only), and no
  wishlist on the card.
- Stored themes keep their emoji icons (see §12).

## 14. Phase 3 (first part) delivery: drawer stepper, product badges, bestselling, Jalali countdown, FAQ (2026-10-28)

**Done (ADR-068):**

- **Mini-cart drawer:**
  - a «+ / −» quantity stepper per line. The line total, subtotal and
    header badge change at once, in the host's own Toman format, and the
    host's answer replaces the estimate after 350 ms;
  - one request per pause with the last quantity, and every cart write in
    one serial queue;
  - a refused step (e.g. stock) shows the host's message and restores its
    cart;
  - removing asks inline («… از سبد حذف شود؟», «حذف» / «انصراف»). Esc
    cancels and returns focus.

  customer-site's `shop.cart.update` now answers JSON.
- **Product badges:** derived overlays on the card image — real discount,
  «پرامتیاز» (average ≥ ۴٫۵ over ≥ ۵ approved reviews) and «جدید» — then
  the free-text badge, at most three.
- **Bestselling:** `module:shop_product_grid` source «پرفروش» reads the
  shared 30-day sales ordering (`Product::scopeBestSelling`, also behind
  data binding `best_selling_30d`). The last `HOST_PATCHES` entry is gone.
  The module grid and slider use the shared product card.
- **Countdown:**
  - Jalali end dates («۱۴۰۵/۰۸/۱۵», any digits) and an optional end time,
    in Tehran time (+03:30), shipped as one ISO instant;
  - Persian digits, a «پایان: …» line, a custom expiry text, and a card or
    promo-banner style;
  - stored Gregorian dates keep their instant.
- **FAQ:**
  - one canonical accordion (customer-site's override merged): exclusive
    mode, first answer open, chevrons, line breaks kept, rich answers
    sanitized, and an empty-state hint;
  - `module:faq_dynamic` uses it, and its visible items now join the page's
    one FAQPage JSON-LD.
- **Newsletter migration:** `down()` drops its table again. The content
  matches the existing owner review.

**Evidence:**

- **Parity harness** (customer-site live render, 229 documents, three
  widths; before is `82194f5d` + customer-site `852949e`):
  - **Markup:** 34 documents change, all FAQ (24 with items, 10 empty).
    Countdowns in the corpus carry no date, and the corpus's product cards
    gain no overlay.
  - **Old markup under new vs old CSS:** four element kinds, all the
    FAQ restyle (`summary.tb-faq-question`, `div.tb-faq-answer`,
    `details.tb-faq-item`, `div.tb-faq`). No other block changes.
  - **Preview vs live:** 48 distinct diffs across 230 platform documents,
    all on `div.tb-skill-bar-fill` (the known host-only rule).
- **Re-pinned on purpose:** the countdown with-date snapshot and the
  product-slider snapshot. For the slider, a HEAD-vs-new render diff shows
  only the three badge spans.
- **Tests:**
  - `Phase3CommerceWidgetsTest` (platform) and `ThemePhase3CommerceTest`
    (customer-site);
  - countdown PHP and JS tests extended; JS 116/116;
  - saas-platform suites with only the known failures (10);
  - customer-site suites with only the known failures (3).
- **Browser** (three widths):
  - `phase3-commerce-widgets` (platform, stub origin): stepper, debounce,
    422 rollback, inline confirm and Esc, badges on the image, ticking
    Jalali countdown, expired state, exclusive FAQ, no horizontal overflow;
  - `phase1-2-cart-drawer-cards` (customer-site, real routes): now steps
    and confirms removal unconditionally. It used to skip removal when the
    fixture header had no cart trigger.

**Remaining (next):**

- Bento v2 (layout presets, spotlight, media cards, entrance animation),
  social proof (testimonial marquee, aggregate rating, logo wall), and
  pricing (monthly/annual toggle, comparison table).
- FAQ categories and search.
- No wishlist on the card yet.
- `header_modern_v1` and the theme-specific headers keep their own chrome.
- Stored themes keep their emoji icons (see §12).

## 15. Phase 3b delivery: Bento v2, reviews, pricing (2026-10-30)

**Done (ADR-069):**

- **Bento v2:**
  - a 12-column grid: 2 columns on tablets, 1 on phones; sizes one-third,
    half, two-thirds, full width, tall and featured;
  - dynamic cells: a live product card (the chosen product, or the newest),
    a Jalali countdown teaser, or an icon metric («۱۲٬۰۰۰+ مشتری راضی»);
  - in the platform editor the kind-specific fields appear only for their
    kind.
- **Reviews:**
  - one review card: SVG stars, «خریدار تاییدشده», a Jalali date, and the
    avatar or initial;
  - an optional Persian star breakdown;
  - customer-site's reviews grid verifies buyers against paid orders and
    publishes schema.org Review microdata, plus AggregateRating for a
    single product.
- **Pricing:**
  - a «ماهانه / سالانه» switch with a discount pill, and a popular-plan
    ribbon;
  - check/x features, with tooltips that can be tapped and dismissed with
    Esc.
- **Bestselling** counts paid orders (paid, processing, shipped, completed).

**Evidence:**

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
- **Tests:**
  - `Phase3bBentoReviewsPricingTest` (platform) and
    `ThemePhase3bReviewsBentoTest` (customer-site);
  - bestselling across statuses in `ThemePhase3CommerceTest`; JS 116/116;
  - saas-platform suites with only the known failures (10);
  - customer-site suites with only the known 3.
    `RealUniversalThemeInstallAcceptanceTest` returned 502s for remote
    media under load, and was 7/7 on an idle rerun.
- **Browser** (three widths):
  - `phase3b-bento-reviews-pricing` (stub origin): each size's column share,
    no product-card clipping (a bug this caught and the fix), a ticking
    countdown, breakdown bars, the toggle, tooltip hover, Esc and tap, and
    no horizontal overflow;
  - `phase01-schema-repeater` (real editor): 12 / 2 / 1 columns, the new
    size labels, and kind-specific fields following `show_if`;
  - the Phase 1/2 and Phase 3 specs stay green. The Phase 3 spec failed
    twice under load and passed 6 of 8 runs; it is a timing flake that
    does not reproduce on an idle machine.

**Remaining (next):**

- FAQ categories and search.
- A testimonial marquee and a logo wall.
- A pricing comparison table across plans.
- No wishlist on the card.
- `header_modern_v1` and the theme-specific headers keep their own chrome.
- Stored themes keep their emoji icons (see §12).
- customer-site's editor does not apply `show_if`.

**2026-10-31:** the local-vs-production widget audit, the WU-A link-scheme
XSS delivery record (deployed: saas-platform `b92570d2`, customer-site
`a717b684`), and implementation-ready specs for WU-B (carousel controller)
and WU-C (commercial tokens) live in
`CANVAS_WIDGET_LOCAL_VS_PROD_AUDIT_2026-10-30.md` §6–§7.

**2026-10-31 (WU-D):** the breakpoint scale (480/768/1024/1280), column and grid slots as size containers (`@container tb-slot`), fluid title floors and the boxed slider's controls. Zero visual diff on both hosts at 1366/768/390 and zero horizontal overflow at 360–1280. Delivery record: `CANVAS_WIDGET_LOCAL_VS_PROD_AUDIT_2026-10-30.md` §10; decision: ADR-071. The four legacy `max-width: 768px` rules stay pinned (owner decision 2026-10-31).

## 16. Schema registry, header/footer commercial fields, Sentinel verifier (2026-11-02, ADR-076)

Owner brief 2026-11-02: give every widget a stable id and schema, fix the
page tabs and the header/footer gaps, and replace manual checks with an
automated verifier. Phases 1 and 2 are delivered. Phase 3 is planned.

### 16.1 Phase 1: schema-driven registry (delivered)

- The registry already held stable ids, categories and schemas. ADR-076
  adds the missing gate, `Widget\WidgetSchemaContract`:
  - `violations()` fails CI when a definition leaves the vocabulary the
    editors render;
  - `manifest()` publishes id → category, slots and field → control.
- The Inspector renders two more things from schema alone, in both
  editors:
  - `ui: 'segmented'` (button group);
  - `group` (sidebar sections).
- Hooks:
  - `data-widget-type` on every block wrapper;
  - `data-widget-slot` on header and footer slots;
  - `data-canvas-page-tab` on page tabs.

### 16.2 Phase 2: Canvas fixes (delivered)

- **Page tabs:** one shared strip, `theme-runtime::canvas.tab-strip`, in
  both editors. It scrolls horizontally, shows edge chevrons only when tabs
  are hidden, scrolls on the mouse wheel and keeps the active tab in view.
- **Header (`nav_header`):**
  - an uploaded logo image with desktop and mobile height, plus alt text;
  - menu placement (right / centre / left);
  - quick-access social and messenger icons, with optional brand colours,
    shown on mobile if the author chooses;
  - grouped sidebar sections and real colour controls.
- **Footer (`nav_footer`):**
  - Soroush Plus and Gap are added;
  - Eitaa, Bale, Rubika, Soroush and Gap get their own vector marks;
  - optional brand colours.
  One network list (`Support\SocialNetworks`) feeds both the header and the
  footer.

### 16.3 Phase 3: Sentinel verifier suite (planned)

Constraints from AGENTS.md:

- Sentinel stays the post-deploy auditor that the Hub triggers on
  `deployment.completed`.
- Agents in `../agents` are read-only toward the projects and talk only
  through registered Task contracts and bus topics.
- The suite below is the verifier's browser layer. It runs against any
  host via `BASE_URL`.

1. **Suite** (`tests/sentinel/`, Playwright, one config):
   - `BASE_URL` selects the target: local `127.0.0.1:8000` /
     `customer-site.test`, or production.
   - Read-only credentials come from env (`SENTINEL_USER`,
     `SENTINEL_PASSWORD`). Without them, the suite audits public pages only.
   - It never writes. Editor checks open a throwaway theme only when
     `SENTINEL_ALLOW_WRITE=1`, which is local-only, and clean up.
   - The widget list comes from a read-only JSON endpoint serving
     `WidgetSchemaContract::manifest()`, behind auth on the platform and
     never enumerated in the suite.
2. **Visual regression and overflow:**
   - for each public page and each Canvas preview, at 360, 390, 768, 1024,
     1280 and 1440 px;
   - flags any element whose box exceeds its `[data-widget-type]` container
     or the viewport (the existing `tests/Support/CssParity/overflow.cjs`
     logic, moved into the suite);
   - flags horizontal scroll on `<html>`;
   - flags clipped text (`scrollWidth > clientWidth` on single-line
     labels);
   - flags broken images and failed requests;
   - adds the page-tab strip check: with 30 pages, the toolbar never exceeds
     the viewport and the chevrons reach both ends.
   - Screenshot baselines are kept per host and width. A diff above a
     threshold is a finding with both images attached.
3. **Feature audit:**
   - a `WidgetFeatureProfile` per widget kind, declared next to the
     registry. For example:
     - header: logo (image or text), menu with placement, actions slot,
       `dir="rtl"`, a working mobile menu;
     - footer: social slot, legal links, a contrast-AA text colour;
     - hero: a CTA with a real href.
   - The suite renders each manifest widget and checks the profile against
     the live DOM through `data-widget-slot`. It also checks RTL (logical
     direction, Vazirmatn loaded), `prefers-reduced-motion`, and tap
     targets of at least 44 px on mobile.
4. **Defect report:**
   - one JSON and one Markdown artifact per run under `storage/sentinel/`.
     Each finding has a code (for example `WIDGET_OVERFLOW`,
     `FEATURE_MISSING_LOGO`, `TAB_STRIP_UNREACHABLE`), the widget, slot,
     width, a selector, evidence and the required change, written as a
     task.
   - The report maps to the agents finding schema in `../agents/README.md`,
     so `deployment.readiness` and the Hub can consume it. A `blocker`
     finding stops a release.
   - Registering the verifier as an agent follows the README's "Adding an
     agent" section. It is an owner decision because it touches
     `agents.json`.
5. **Order of work:**
   1. manifest endpoint and feature profiles (PHP, unit-tested);
   2. the overflow and visual layer;
   3. the feature audit;
   4. the report;
   5. local CI wiring;
   6. the Hub hook (owner).

### 16.4 Delivery record

See ADR-076 for the decisions. Base: saas-platform `e153ef33`,
customer-site `928c1d1` (same tree as `a66b552`).

- **Contract:** `WidgetSchemaContract::violations()` is empty for all 163
  booted platform types (118 canonical + 45 host) and for the customer-site
  host registry.
- **PHPUnit:**
  - platform `Adr076WidgetSchemaHeaderFooterTest`: 13 tests;
  - customer-site `Adr076CanvasHooksTest`: 4 tests;
  - platform baseline set (`tests/Unit/ThemeBuilder tests/Feature/ThemeBuilder
    tests/Feature/ThemeCanvas tests/Feature/Admin`): 1078 tests. The one
    failure was `ProductSliderRenderSnapshotTest`'s public pin, which
    changed only by `data-widget-type`. Without that attribute the markup
    hashes to the old pin, and it was re-pinned with that reason;
  - customer-site baseline set: 846 tests, only the 3 known failures. The
    media sweep covers the new `logo_image` automatically.
- **JS:** 130/130, including `canvas-tab-strip.test.mjs` (RTL edge, step
  and reveal geometry).
- **Browser:** `adr076-canvas-tabs-header.spec.ts`, 2 tests.
  - With 30 pages at 1280 and 1024 px, there is no page overflow, the strip
    is inside the viewport and at least 280 px wide, the chevrons reach both
    ends and the wheel scrolls back.
  - Header groups and segmented placement drive `menu_align`. The editor
    iframe carries `data-widget-type`, and the preview shows the centred
    menu, a 44 px logo, five brand-filled messenger marks (Eitaa fill
    `rgb(217, 72, 15)`), quick-access hidden below 768 px, and no overflow
    at 1440/1024/768/390.
- **CSS parity** (customer host, before = `e153ef33` package + `928c1d1`
  host):
  - 201 of 229 corpus documents change, and only by `data-widget-*`
    attributes;
  - old markup and CSS against new markup and CSS: 0 distinct diffs over
    18,006 elements per width at 1366/768/390;
  - both corpora render with no renderer warnings.
- **Readiness:** `deployment.readiness --strict` gives 0 blockers. The
  warnings are the expected pre-commit ones (uncommitted package,
  cross-repo coupling, `.claude/CLAUDE.md` outside the release paths).
  Artifact digest `3c978bed…78ca`.
