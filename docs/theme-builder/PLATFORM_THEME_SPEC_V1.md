# PLATFORM-THEME-SPEC-v1 — commercial energy theme ("Oxin Solar")

Decision record: ADR-083 (`docs/project-memory/DECISIONS.md`). Quality gates:
`PROJECT_CONSTITUTION.md` §16 (ADR-060).

The spec turns one reference site (https://oxinsolar.org/, captured
2026-11-12 at 1280px and 390px) into platform parts that any tenant can use:
a token preset, seven neutral widgets and seven library templates. Nothing in
it is specific to one host: it lives in `packages/saas-platform/modules/theme-runtime`
and reaches customer-site through the package mirror.

## 1. Token preset

`resources/library/tokens/solar-dark.json`, read by `TemplateLibrary::tokenPresets()`.
Its `global_styles` is the shape `ThemeTokenNormalizer` reads.

| Token | Value | Reference use |
|---|---|---|
| `--tb-background` | `#0B0B0B` | page body |
| `--tb-surface` | `#1E1E1E` | cards, team tiles |
| `--tb-secondary`, `--tb-input-bg` | `#12121F` | form fields |
| `--tb-primary` / `--tb-accent` | `#FF7900` | buttons, highlights |
| `--tb-primary-hover` | `#E06600` | primary button hover |
| `--tb-border` | `#2A2A2A` | card and header borders |
| `--tb-radius-sm` / `--tb-radius-md` | `6px` / `10px` | buttons / cards |
| `--tb-font-*` | `IRANSans, Vazir, Vazirmatn, Tahoma, sans-serif` | Vazirmatn is the face the platform ships |

`primary_hover` and `input_bg` are opt-in commercial tokens
(`ThemeTokenNormalizer::COMMERCIAL_TOKENS`): a theme that does not define
them emits nothing, and each consumer keeps its old value as the `var()`
fallback.

## 2. Widgets

`CommercialBlockTypes` (schemas, registered in `ThemeRuntimeServiceProvider::boot()`
through `registerHostType`) and `CommercialBlockRendererTrait` (renderers,
used by `BlockRenderer`). Styles are the `.tb-cx-*` rules at the end of
`theme-runtime-blocks.css`.

| Type | Category | What it is | Data source |
|---|---|---|---|
| `hero_with_stats` | marketing | eyebrow, title + highlighted phrase, tagline, two CTAs, image or stat tiles | — |
| `zigzag_rows` | content | alternating text/image rows, or numbered steps | — |
| `target_audience_grid` | content | 2–4 column icon cards with an outline CTA | — |
| `event_calendar` | dynamic | month grid (Saturday-first), status legend with counts, event list | `courses`, `events` |
| `dual_sale_product_grid` | dynamic | cards with a buy action and a details action | `products` |
| `course_video_filter` | dynamic | free/paid filter and sort over course cards | `courses` |
| `downloadable_assets` | content | file cards with a download button | `downloads` |

Rules every widget follows:

- **No colour props.** Every colour is a `--tb-*` token in the sheet.
  `CommercialWidgetResilienceTest` fails if a hardcoded colour appears
  outside a `var()` fallback.
- **Zero-gap default.** `section_spacing` is `none` by default. A template
  opts into `compact` (32–48px) or `regular` (48px on phones to 72px on
  desktop, the reference rhythm).
- **Data source.** The four list widgets declare the Phase 3C `data_source`
  contract, with the `items` repeater as the static source. For `internal`,
  the host's resolver answers. With no resolver, or a failing one, the
  authored items render. A resolver that returns no rows renders the empty
  state.
- **Cart.** `dual_sale_product_grid` uses `productActionHtml()` when an item
  has a numeric `product_id` and the host exposes cart endpoints. The shared
  `cart.js` drawer, live `[data-tb-cart-count]` badges and toasts then
  apply. Without them it renders a plain link.
- **Resilience.** Props are untrusted. The sweep feeds every field
  malformed values in both render modes (more than 1000 renders) and
  expects markup back, with no exception, no unescaped HTML and no
  `javascript:` URL.

## 3. Global shell

The shell already exists, so the spec configures it rather than duplicating
it:

| Spec item | Provided by |
|---|---|
| Sticky header, blur past 60px | `nav_header`: `sticky`, `scroll_threshold: 60`, `backdrop_blur: 14`, semi-transparent `bg_color`/`scrolled_bg_color` (`blocks/site-header.js`, ADR-067/082). Blur and threshold are blocked on `main`; see §5. |
| Three-column mega dropdown | `nav_links[].mega_columns` (3 columns each) + promo strip (`mega_banner_*`) |
| Mobile drawer | `burger_breakpoint: tablet`, `drawer_style: side` (ADR-082 step 4) |
| Cart drawer, live counters, toast | `show_cart` + `blocks/cart.js` (ADR-067/068). The cart is server-side, so a `localStorage` copy would disagree with it, and none was added. |
| Footer shell | `nav_footer`, four columns → two → one |

## 4. Templates

`resources/library/templates/solar-*.json`, all `spec: PLATFORM-THEME-SPEC-v1`.
They contain no hex colours, only library route or https links, and no media,
because the reference photos belong to the site owner. Image slots stay empty
for the tenant's own photos, and `hero_with_stats` shows its stat tiles
instead.

| Template | Route | Sections (reference order) |
|---|---|---|
| `solar-header` | — | logo, 4 mega items (3 columns each) + about + contact, search, cart, account (as the reference: no header CTA) |
| `solar-footer` | — | brand + tagline, quick links, services, contact |
| `solar-page-home` | `home` | hero + stats, 6 services, about, recent projects, articles, contact |
| `solar-page-about` | `about` | hero + stats, company rows, why us (4), 4-step approach, mission/vision/values, capabilities, CTA |
| `solar-page-courses` | `courses` | academy hero, event calendar, current courses (dual CTA), video filter, downloads |
| `solar-page-products` | `shop` | store hero, three equipment groups, product grid (internal `products`) |
| `solar-page-contact` | `contact` | contact hero, form + office details, contact cards |

`TemplateLibrary::ROUTES` gained `about`. It is the dynamic page with the
slug `about`, not a fixed route: customer-site's `EntityLinkCatalog` maps it
to `page.show` (`/page/about`), so a tenant creates a page with that slug and
the link resolves (owner decision 2026-11-12). A products page is the shop:
`/products` links through `@link:shop`.

The download cards ship without a file (owner decision 2026-11-12). Each
renders a disabled "به‌زودی" button until the tenant picks the uploaded PDF,
so no template depends on any one domain.

## 5. Known gaps (owner decisions)

- **Header style wiring is missing on `main`.** Merge `08512a47` (2026-11-09)
  dropped `HeaderStyleVars::forHeader()` and `data-tb-hdr-set` from
  `renderNavHeader` (they were released in `007f2471`). The 768px wrapper
  breakpoint and a `renderGrid` hunk were lost in the same merge. Until that
  is restored, the header's Style-tab values (`backdrop_blur`, `min_height`,
  menu and drawer colours) and `scroll_threshold` do not reach the page, so
  the blur and 60px threshold in `solar-header` do not apply yet. Sticky,
  mega menus, drawer and cart work today.
- The mega promo strip renders only with `mega_banner_image`. The templates
  ship no media, so it stays hidden until the tenant adds an image.

- The reference's team section (staff names and personal mobiles) and the
  course registration form are not in the templates.
- `WidgetDefinition::CATEGORIES` (`commerce`) and `BlockRegistry`
  (`ecommerce`) disagree, so a data-source widget can only be `dynamic`, as
  `dynamic_product_grid` already is. The owner accepted `dynamic` for
  `dual_sale_product_grid` (2026-11-12).
