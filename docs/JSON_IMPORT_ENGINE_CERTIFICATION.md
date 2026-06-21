# JSON IMPORT ENGINE — CERTIFICATION

**Status:** JSON_IMPORT_ENGINE_OPERATIONAL  
**Date:** 2026-06-21  
**Version:** 1.0.0

---

## Overview

The JSON Theme Import Engine converts page-builder exports from 5 different formats into ThemePack v1 structures that can be directly installed into the Canvas Editor.

### Pipeline

```
JSON File Upload
    ↓
ThemeStructureAnalyzer::detect()      — auto-detect source format
    ↓
ThemeStructureAnalyzer::analyze()     — metadata summary (pages, sections, widgets)
    ↓
Adapter::convert()                    — format-specific normalization
    ↓
WidgetMapper (called by adapters)     — source widget type → ThemeBuilder block type
    ↓
ThemePackGenerator::generate()        — ThemePack + optional ThemeMarketplace entry
    ↓
Result: ThemePack with quality score
```

---

## Supported Sources

| Source | Format | Detection | Adapter |
|--------|--------|-----------|---------|
| Elementor | WordPress page builder export | `elType` key in content nodes | `ElementorAdapter` |
| Bricks | Bricks Builder JSON | `source: bricks` or `children` key | `BricksAdapter` |
| Shopify | Shopify theme `sections.json` | `sections[*].type` structure | `ShopifyAdapter` |
| Webflow | Webflow export JSON | `@webflow` type marker or `payload.site` | `WebflowAdapter` |
| Custom | ThemePack v1 / our own format | `format: theme-pack-v1` or passthrough | `CustomBuilderAdapter` |

---

## Widget Mapping Coverage

### Elementor (35 widget types mapped)
| Source Widget | → Our Block |
|---------------|-------------|
| heading | heading |
| text-editor | text |
| image | image |
| button | button |
| video | video |
| icon / icon-box | icon |
| counter / countdown | counter |
| progress | progress_bar |
| gallery / image-gallery / image-carousel | gallery |
| divider / spacer | divider |
| posts / portfolio | blog_posts |
| testimonial / testimonial-carousel | testimonials |
| slides / media-carousel | slider |
| accordion / toggle | accordion |
| tabs | tabs |
| call-to-action | hero |
| price-table / price-list | pricing_table |
| flip-box | hero_split |
| woocommerce-products | product_grid |
| team-member / team-members | team |
| nav-menu / social-icons | icon / heading |

### Bricks (25 widget types mapped)
| Source Widget | → Our Block |
|---------------|-------------|
| section | section |
| container / block / div | container |
| heading | heading |
| text / text-basic / rich-text / paragraph | text |
| image | image |
| button | button |
| video | video |
| icon / icon-box | icon |
| divider / spacer | divider |
| counter | counter |
| progress | progress_bar |
| gallery | gallery |
| posts | blog_posts |
| accordion | accordion |
| tabs | tabs |
| slider | slider |
| testimonials | testimonials |
| team-members | team |
| pricing-table | pricing_table |
| hero | hero |

### Shopify (15 section types mapped)
| Source Section | → Our Block |
|----------------|-------------|
| image-banner | hero |
| slideshow | slider |
| featured-collection / collection-list / featured-product | product_grid |
| rich-text | text |
| video | video |
| multicolumn | columns |
| newsletter / contact-form | text |
| testimonials | testimonials |
| blog-posts | blog_posts |
| image-with-text | hero_split |
| collapsible-content | accordion |
| countdown-timer | counter |
| before-after-image | gallery |
| popup | modal |

### Webflow (16 node types mapped)
| Source Node | → Our Block |
|-------------|-------------|
| Section / article / main | section |
| Container / Div Block | container |
| Heading / h1-h4 | heading |
| Paragraph / Text Block / RichText | text |
| Image | image |
| Video | video |
| Link / Link Block / Button | button |
| Grid | grid |
| Columns / Column | columns |
| List / List Item | text |
| Form Wrapper | text |
| NavbarWrapper / nav / header | heading |
| Slider / Slide | slider |
| Tab Menu | tabs |
| Lightbox | gallery |

---

## Quality Scoring System

The `ThemePackGenerator` scores each import on 100 points:

| Criterion | Max Points | Logic |
|-----------|-----------|-------|
| Page coverage | 25 | min(page_count, 5) × 5 |
| Block type variety | 25 | min(block_type_variety, 5) × 5 |
| Has header | 10 | binary |
| Has footer | 10 | binary |
| Total block count | 20 | min(total_blocks, 20) |
| Home page content | 10 | ≥5 blocks=10, ≥2=5 |

| Score | Grade |
|-------|-------|
| 80–100 | EXCELLENT |
| 60–79 | PASS |
| 40–59 | PARTIAL |
| 0–39 | FAIL |

---

## File Structure

```
app/Services/ThemeBuilder/JsonImport/
├── ThemeStructureAnalyzer.php         — format detection + metadata analysis
├── WidgetMapper.php                   — source widget → ThemeBuilder block mapping
├── ThemePackGenerator.php             — ThemePack creation + quality scoring
├── JsonImportPipelineService.php      — pipeline orchestrator
└── Adapters/
    ├── ElementorAdapter.php           — Elementor JSON → normalized structure
    ├── BricksAdapter.php              — Bricks JSON → normalized structure (flat + nested)
    ├── ShopifyAdapter.php             — Shopify sections.json → normalized structure
    ├── WebflowAdapter.php             — Webflow export → normalized structure
    └── CustomBuilderAdapter.php       — ThemePack v1 / generic passthrough

app/Http/Controllers/Admin/
└── ThemeJsonImportController.php      — HTTP controller (6 actions)

resources/views/admin/theme-builder/
├── json-import.blade.php              — Upload form + drag-drop + auto-detect
└── json-import-result.blade.php       — Result page (quality, stats, actions)
```

---

## Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/admin/theme-builder/json-import` | `admin.theme-builder.json-import.form` | Upload form |
| POST | `/admin/theme-builder/json-import/detect` | `admin.theme-builder.json-import.detect` | AJAX detect |
| POST | `/admin/theme-builder/json-import/import` | `admin.theme-builder.json-import.import` | Full pipeline |
| GET | `/admin/theme-builder/json-import/{pack}/result` | `admin.theme-builder.json-import.result` | Result page |
| POST | `/admin/theme-builder/json-import/{pack}/install` | `admin.theme-builder.json-import.install` | Install → Canvas |
| GET | `/admin/theme-builder/json-import/{pack}/export` | `admin.theme-builder.json-import.export` | Download JSON |

---

## ThemePack Output Schema

The pipeline always produces a `ThemePack` with `pack_data` in the following structure:

```json
{
  "global_styles": {
    "primaryColor": "#6366f1",
    "secondaryColor": "#8b5cf6",
    "backgroundColor": "#ffffff",
    "textColor": "#1f2937",
    "fontFamily": "Vazirmatn",
    "direction": "rtl"
  },
  "headers": [ [ { "id": "...", "type": "heading", "props": {...} } ] ],
  "footers": [ [ { "id": "...", "type": "text", "props": {...} } ] ],
  "pages": {
    "home": {
      "title": "صفحه اصلی",
      "slug": "",
      "blocks": [
        { "id": "...", "type": "hero", "props": { "title": "...", "subtitle": "..." }, "blocks": [] },
        { "id": "...", "type": "section", "props": {...}, "blocks": [...] }
      ]
    },
    "about": { "title": "درباره ما", "slug": "about", "blocks": [...] }
  },
  "templates": [],
  "demo_data": [],
  "import_meta": {
    "source": "elementor",
    "source_label": "Elementor",
    "original_title": "My Page",
    "imported_at": "2026-06-21T...",
    "stats": { "page_count": 1, "total_blocks": 12, "block_type_variety": 7 }
  }
}
```

---

## Installation Flow

```
Upload JSON → POST /detect (AJAX, auto-identify source)
           → POST /import (full pipeline)
           → GET  /{pack}/result (quality score + stats)
           → POST /{pack}/install (create Theme + open Canvas)
```

Or optionally: `GET /{pack}/export` to download normalized JSON for external use.

---

## Known Limitations

1. **Elementor Pro dynamic content** (custom fields, global widgets) — mapped to placeholder blocks; real data requires re-entry.
2. **Shopify Liquid templates** — Only settings/schema from `sections.json` are parsed; Liquid rendering logic is not converted.
3. **Webflow CMS collections** — Dynamic collection data is not imported; only static layout structure.
4. **Bricks dynamic data** — Query-based data is mapped to equivalent dynamic block types (blog_posts, product_grid, etc.).
5. **Proprietary fonts/icons** — Source builder font faces and icon sets are replaced with Vazirmatn + Heroicons equivalents.
6. **Nested column layouts** — Complex multi-level grid nesting is flattened to `section > block` hierarchy where possible.

---

## Certification Verdict

**JSON_IMPORT_ENGINE_OPERATIONAL**

All 5 source adapters implemented and wired.  
Widget mapper covers 90+ widget types across all sources.  
Quality scoring system with 6 dimensions.  
Full admin UI: upload → detect → import → result → install.  
Routes registered and Theme Builder index button wired.
