# SleepShiman Home blue — implementation record

Status: implemented locally; Figma verification pending because the configured Figma Starter account returned `You've reached the Figma MCP tool call limit on the Starter plan.` No production publish, assignment, migration, deploy, commit, or push was performed.

## Source and scope

- Design file key: `8XemokBtUnZug7xOKbgh1i`.
- Candidate frame supplied by the brief: `2014:755`; recorded as unverified until Figma access is restored.
- Offline evidence: the supplied handoff inventory, screenshots, asset manifest, and reference implementation under `_reference-themes/SleepShiman-Home-Blue-Handoff/`.
- The pre-existing `KhabShimanaTabletThemeSeeder` remains a separate tablet-oriented preset. Home blue is a desktop-first commercial preset; both use the existing Theme/Canvas lifecycle and no parallel builder or persistence model was created.

## Runtime contract

The shared `modules/theme-runtime` package owns all definitions and markup. Platform Builder preview and customer public/Canvas rendering therefore use one renderer. Every block has a non-empty schema and `defaultProps`; repeaters carry logical group/index markers for contextual selection. Text is escaped, URLs are allowlisted, media stays localizable through the existing asset pipeline, and authored HTML/CSS/JS is not executed.

| Order | Stable type | Canvas model | Optional real source |
|---:|---|---|---|
| 1 | `sleepshiman_header` | logo, brand, search/account/cart routes | existing search/auth/cart routes |
| 2 | `sleepshiman_navigation` | link repeater and badges | manual navigation |
| 3 | `sleepshiman_quick_categories` | category-card repeater | `product_categories` |
| 4 | `sleepshiman_featured_product` | product fields, CTA, metrics | `products` |
| 5 | `sleepshiman_services` | service repeater | manual |
| 6 | `sleepshiman_amazing_offers` | product repeater and slider | `products` |
| 7 | `sleepshiman_mattresses` | product repeater and slider | `products` |
| 8 | `sleepshiman_promo_banners` | two banner repeater | manual |
| 9 | `sleepshiman_bedding_products` | product repeater and slider | `products` |
| 10 | `sleepshiman_blanket_products` | product repeater and slider | `products` |
| 11 | `sleepshiman_sleep_banner` | image, copy, CTA | manual |
| 12 | `sleepshiman_brands` | linked-logo repeater | manual; no Brand domain is invented |
| 13 | `sleepshiman_published_products` | product repeater and slider | `products` |
| 14 | `sleepshiman_topic_cards` | linked editorial-card repeater | manual |
| 15 | `sleepshiman_articles` | article-card repeater | `blog_posts` |
| 16 | `sleepshiman_editorial` | editorial copy, image, CTA | manual |
| 17 | `sleepshiman_reviews` | review repeater and slider | approved `reviews` |
| 18 | `sleepshiman_guide_intro` | heading and description | manual |
| 19 | `sleepshiman_guide_cards` | linked guide repeater | manual |
| 20 | `sleepshiman_product_filters` | nested filter groups/options | manual links |
| 21 | `sleepshiman_stats` | value/label repeater | manual |
| 22 | `sleepshiman_footer` | copy, nested link columns, contacts, form CTA | configurable existing Forms URL |

Manual is the complete default and remains visible without commerce, blog, review, or forms modules. Dynamic queries are opt-in, allowlisted, bounded, and tenant-database scoped. An empty dynamic result may hide, render an empty state, or fall back to the saved manual content. Bound commerce cards use real product IDs, stock, variant requirements, CSRF and cart routes; manual cards never pretend to be cart-connected. The newsletter treatment links to a configured existing Forms route rather than exposing a fake submit control.

## Assets, responsive behavior, and interaction

The versioned `1.0.0` package contains local Vazirmatn, CSS, JS, and image files. The manifest pins SHA-256 for every asset and records image dimensions, purpose, and non-empty alt text. Installation uses the existing localization service, including direct and repeater media fields.

RTL layouts are defined for desktop, tablet, and mobile. Product/review sliders use scoped controls, native smooth scrolling, scroll snapping, and disabled state when no overflow exists. Static visual acceptance loads every image eagerly for verification, rejects broken images and horizontal page overflow, and captures 1370, 768, and 390 pixel outputs.

## Known comparison limit

The local screenshot implementation follows the supplied section inventory and handoff images, but it must not be described as Figma pixel-perfect until metadata/design-context access is restored and the candidate frame is confirmed. On restoration, re-run frame discovery, compare exact typography/spacing/geometry, and update this record only for evidenced discrepancies.

## Local acceptance evidence

- Theme definition, security, renderer, manifest, draft lifecycle and install-payload tests: 8 passed / 212 assertions.
- Shared platform runtime regression slice: 52 passed / 412 assertions.
- Customer SleepShiman contract: 5 passed / 35 assertions; Canvas parity plus contract: 8 passed / 91 assertions.
- Isolated Playwright Canvas journey: 1/1 passed.
- Static responsive acceptance: 1370 / 768 / 390 passed, all images decoded, no page overflow, slider movement asserted.
- Vite production builds: both repositories passed; both reported the same pre-existing generated-CSS parser warning around `--ui-indigo-200`.
- Full customer ThemeCanvas sweep: 393 tests / 2,009 assertions ran. One inventory-count failure caused by the 22 new types was corrected and its focused test passes. The remaining performance failure is outside this theme path: the universal fixture contains no SleepShiman/Review block, while the captured query log showed repeated Menu visibility calls through `SiteHelper` to `License::current()` (24 queries vs the existing ceiling of 20). The ceiling was not weakened and that separate debt was not expanded into this task.
