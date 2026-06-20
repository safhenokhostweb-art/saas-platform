# VISUAL REVERSE ENGINEERING ARCHITECTURE

**Sprint:** TB3 Sprint 9.5
**Status:** CERTIFIED COMPLETE — 2026-06-20
**Engine:** Visual Reverse Engineering Engine v1.0

---

## Overview

The Visual Reverse Engineering Engine is a 7-phase pipeline that analyzes any website
(via URL, screenshot, HTML upload, or text prompt) and reconstructs a structurally
similar, visually informed Persian ThemePack — WITHOUT copying any source code,
assets, or copyrighted content.

---

## Architecture Diagram

```
INPUT                  PHASES                           OUTPUT
─────                  ──────                           ──────
URL ──────────┐        Phase 1: DOM Extraction          ┌─────────────────┐
Screenshot ───┤  ───►  Phase 2: Layout Reconstruction   │  ReverseEngineering
HTML ─────────┤        Phase 3: Visual Analysis    ───► │  Job (DB record)  │
Prompt ───────┘        Phase 4: Commerce Analysis       └─────────────────┘
                       Phase 5: Similarity Engine        ↓
                       Phase 6: Theme Reconstruction     ThemePack (DB)
                       Phase 7: Quality Validation       ↓
                                                         ThemeTemplates (DB)
                                                         ↓
                                                         Canvas Install
```

---

## Phase 1 — DOM Extraction (`DomExtractionService`)

### Responsibility
Parse raw HTML with DOMDocument + DOMXPath and detect all structural landmarks.

### Detected Components
| Component | Detection Method |
|-----------|-----------------|
| Header | `//header`, `[role="banner"]`, `.header`, `#header` |
| Mega Menu | `.mega`, `.flyout`, `.nav-dropdown`, column count |
| Hero | `.hero`, `.banner`, `.jumbotron`, `section[1]` |
| Grid Layouts | `grid-cols-*`, `col-md-*`, `w-1/*` class patterns |
| Cards | `.card`, `.tile`, `.product-card`, `.blog-card` |
| Feature Sections | `.features`, `.benefits`, `.icon-box` patterns |
| CTA Sections | `.cta`, `.call-to-action`, `.get-started` |
| Pricing | `.pricing`, `.plans`, `.price-table` |
| Testimonials | `.testimonials`, `.reviews`, `blockquote` |
| FAQ | `.faq`, `.accordion`, `.questions` |
| Blog | `.blog`, `.news`, `.post-grid` |
| Footer | `footer`, `[role="contentinfo"]`, `.footer` |

### Input Adapters
- `extract(html, url)` — Full DOM XPath analysis
- `extractFromVision(visionResult)` — From Claude Vision JSON
- `extractFromPrompt(prompt)` — From text description

### Output: DomStructure
```json
{
  "url": "...",
  "title": "...",
  "meta": { "description": "...", "theme_color": "#hex" },
  "landmarks": [{ "type": "header", "confidence": 85 }],
  "header": { "detected": true, "nav_items": 6, "is_sticky": true },
  "mega_menu": { "detected": false },
  "hero": { "detected": true, "layout": "split", "has_cta": true },
  "grid_layouts": { "primary_layout": "col-3", "total_containers": 8 },
  "cards": { "count": 12, "types": ["product","blog"] },
  "features": { "detected": true, "item_count": 6, "layout": "grid-3" },
  "cta_sections": { "detected": true, "count": 2 },
  "pricing": { "detected": true, "tiers": 3, "has_toggle": true },
  "testimonials": { "detected": true, "layout": "slider" },
  "faq": { "detected": true, "items": 8 },
  "blog": { "detected": true, "grid_cols": 3 },
  "footer": { "detected": true, "columns": 4, "has_newsletter": true },
  "depth_map": [{ "level": 1, "text": "Main headline" }]
}
```

---

## Phase 2 — Layout Reconstruction (`LayoutReconstructionService`)

### Responsibility
Reconstruct the layout grid system, spacing, container config, and section hierarchy.

### Extracted Properties
- **Columns**: Per-section column count (header=1, features=3, products=4, etc.)
- **Containers**: `max-width`, padding, Tailwind size token
- **Spacing System**: Tailwind vs Bootstrap vs custom, scale unit
- **Section Hierarchy**: Heading structure (H1→H2→H3)
- **Responsive Structure**: Breakpoints, mobile-first vs desktop-first
- **Component Relationships**: Flow graph (`header → hero → features → footer`)

### Reference Column Signatures
| Site | hero | features | products | pricing | footer |
|------|------|----------|---------|---------|--------|
| Apple | 1 | 3 | 4 | — | 4 |
| Stripe | 2 | 3 | — | 3 | 4 |
| Shopify | 1 | 3 | — | 3 | 4 |
| Digikala | 1 | — | 4 | — | 4 |
| Amazon | 1 | — | 5 | — | 5 |
| Malltina | 1 | — | 4 | — | 4 |

---

## Phase 3 — Visual Analysis (`VisualAnalysisService`)

### Responsibility
Extract the complete visual design system from CSS, inline styles, and class patterns.

### Extracted Properties
| Property | Extraction Method |
|----------|-----------------|
| Color Palette | Hex regex, RGB extraction, meta `theme-color` |
| Typography Scale | `font-family`, `font-size`, `font-weight` CSS, Google Fonts link |
| Button Styles | `rounded-full`, `btn-outline`, `btn-ghost`, shadow detection |
| Border Radius | `rounded-*` Tailwind classes, `border-radius` CSS values |
| Shadow System | `shadow-*` classes, `box-shadow` CSS, intensity classification |
| Visual Density | Section + card count heuristic + spacing class detection |

### Brand Color Signatures (Reference)
```
Apple:    #000000, #1d1d1f, #0071e3
Stripe:   #635bff, #0a2540, #00d4ff
Shopify:  #96bf48, #5c6ac4, #212b36
Digikala: #ef3e56, #ff6b35, #212121
Amazon:   #ff9900, #232f3e, #146eb4
Malltina: #e63946, #ff6b6b, #2d3748
```

---

## Phase 4 — Commerce Analysis (`CommerceAnalysisService`)

### Responsibility
Detect e-commerce patterns and measure commerce maturity.

### Detected Patterns
| Pattern | Detection Signals |
|---------|-----------------|
| Product Cards | `.product-card`, `.goods-item`, `.dk-product`, grid density |
| Price Blocks | Persian toman patterns, `itemprop="price"`, `data-price` |
| Discount Badges | `.badge`, `.ribbon`, `%\s*تخفیف`, percentage patterns |
| Gallery Layouts | `.thumbnail`, Swiper/Slick presence, zoom detection |
| Reviews | `.review`, star rating patterns, `★` characters |
| Specifications | `<dl>`, `<dt>`, `.spec-table`, accordion patterns |
| Related Products | `.related`, `محصولات مشابه`, carousel detection |
| Cross-Sell | `frequently.bought`, `همراه با` patterns |
| Upsell | `upgrade`, `premium.version` patterns |
| Cart Flow | Add-to-cart buttons, wishlist, compare detection |

### Platform Fingerprinting
Detects: Shopify, WooCommerce, Magento, OpenCart, Digikala, Amazon, Malltina, Laravel

---

## Phase 5 — Similarity Engine (`SimilarityEngineService`)

### Responsibility
Compute multi-dimensional similarity scores against 6 reference sites.

### Scoring Dimensions

#### Structure Score (section sequence match)
- Compares section types present against reference site sections
- Bonus for matching order (flow graph comparison)
- Structural sections (header/footer) always weighted

#### Visual Score (design system match)
- Color proximity (Euclidean distance in RGB space)
- Typography family match
- Border radius scale match
- Visual density match
- Animation presence match

#### Commerce Score (commerce pattern match)
- Commerce presence agreement (both have / both don't)
- Commerce score proximity (quality of patterns detected)

#### Layout Score (grid column match)
- Column signature comparison per section type
- Layout style match (standard/editorial/full-bleed/bento)
- Hero layout match (centered/split/standard)

### Weighted Overall Score

| Site Type | Structure | Visual | Commerce | Layout |
|-----------|-----------|--------|----------|--------|
| ecommerce | 25% | 20% | 35% | 20% |
| saas | 30% | 30% | 10% | 30% |
| tech | 25% | 35% | 15% | 25% |
| general | 25% | 25% | 25% | 25% |

### Confidence Levels
- **HIGH** (≥80): Strong structural + visual match
- **MEDIUM** (60–79): Partial match, usable result
- **LOW** (40–59): Weak match, PHP fallback likely
- **VERY_LOW** (<40): Input had insufficient signals

---

## Phase 6 — Theme Reconstruction (`ReverseThemeReconstructorService`)

### Responsibility
Generate a complete ThemePack from all analysis phases.

### Safe Mode Guarantees
- ✅ All text is original Persian placeholder content
- ✅ Images are inline SVG data URIs
- ✅ No source HTML or CSS copied
- ✅ No external asset URLs
- ✅ No copyrighted or proprietary content

### Generation Flow
1. Build `global_styles` from visual profile (colors, font, radius, direction=rtl)
2. Determine sections from layout map
3. If `ANTHROPIC_API_KEY` set → AI generates full pack via Claude claude-sonnet-4-6
4. If AI fails or no key → PHP `ThemePlaceholderService` generates fallback
5. Persist as `ThemePack` + per-section `ThemeTemplate` records
6. Tag with `['reverse-engineered', 'tb3-sprint9_5', "match:{bestMatch}"]`

### ThemePack Structure
```json
{
  "global_styles": { "primary_color": "#...", "direction": "rtl", "font_family": "Vazirmatn" },
  "responsive_settings": { "desktop": {...}, "tablet": {...}, "mobile": {...} },
  "headers": [{ "name": "هدر اصلی", "blocks": [...] }],
  "footers": [{ "name": "فوتر اصلی", "blocks": [...] }],
  "pages": [{ "title": "صفحه اصلی", "slug": "", "blocks": [...] }],
  "templates": [{ "name": "...", "category": "hero", "blocks": [...] }],
  "demo_data": { "site_name": "...", "tagline": "..." },
  "similarity": { "best_match": "stripe", "score": 72 }
}
```

---

## Phase 7 — Quality Validation (`ReverseEngineeringQualityService`)

### Responsibility
Validate the reconstructed ThemePack against 10 quality criteria.

### Checks

| # | Check | Severity | Score Impact |
|---|-------|----------|-------------|
| 1 | Missing required sections (header/hero/footer) | HIGH | -15 each |
| 2 | Too few sections (< 3) | MEDIUM | -10 |
| 3 | Broken heading hierarchy (H1→H3 skip) | LOW | -5 |
| 4 | Duplicate block IDs | HIGH | -20 |
| 5 | Invalid block types | MEDIUM | -10 |
| 6 | Low similarity score (< 40) | MEDIUM | -10 |
| 7 | Low structure score (< 30) | LOW | -5 |
| 8 | Incomplete visual profile (no color/font) | LOW | -3 each |
| 9 | Weak commerce detection (< 30) | MEDIUM | -8 |
| 10 | Incomplete pack (no headers/footers/pages) | MEDIUM | -5 each |

### Verdict Rules
- **PASS**: Score ≥ 75 AND zero HIGH findings
- **PARTIAL**: Score 50–74 OR 1–2 HIGH findings
- **FAIL**: Score < 50 OR CRITICAL finding OR ≥3 HIGH findings

---

## Database

### `reverse_engineering_jobs` Table

```sql
id, uuid, input_type, input_source, input_notes, suggested_name,
dom_structure (JSON), layout_map (JSON), visual_profile (JSON),
commerce_profile (JSON), similarity_scores (JSON),
theme_pack_id, quality_report (JSON),
status, error_message, meta (JSON),
created_at, updated_at

INDEX: (status, created_at), (input_type)
```

### Status Lifecycle
```
pending → extracting → analyzing → scoring → reconstructing → validating → complete
                                                                        ↘ failed
```

---

## Routes

```
GET  /admin/theme-builder/reverse-engineer              → form
POST /admin/theme-builder/reverse-engineer/analyze      → AJAX full pipeline
GET  /admin/theme-builder/reverse-engineer/{job}/result → result page
GET  /admin/theme-builder/reverse-engineer/{job}/export → JSON download
POST /admin/theme-builder/reverse-engineer/{job}/install→ Canvas install
DEL  /admin/theme-builder/reverse-engineer/{job}       → delete job
```

---

## Key Files

```
app/
├── Models/
│   └── ReverseEngineeringJob.php
├── Http/Controllers/Admin/
│   └── ThemeReverseEngineeringController.php
├── Services/ThemeBuilder/ReverseEngineering/
│   ├── VisualReverseEngineeringService.php      ← Orchestrator
│   ├── DomExtractionService.php                  ← Phase 1
│   ├── LayoutReconstructionService.php           ← Phase 2
│   ├── VisualAnalysisService.php                 ← Phase 3
│   ├── CommerceAnalysisService.php               ← Phase 4
│   ├── SimilarityEngineService.php               ← Phase 5
│   ├── ReverseThemeReconstructorService.php      ← Phase 6
│   └── ReverseEngineeringQualityService.php      ← Phase 7
database/migrations/
└── 2026_06_20_900001_create_reverse_engineering_jobs_table.php
resources/views/admin/theme-builder/
├── reverse-engineer.blade.php          ← Input form
└── reverse-engineer-result.blade.php   ← Results dashboard
docs/
├── VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md
├── SIMILARITY_SCORING_ENGINE.md
└── THEME_RECONSTRUCTION_PIPELINE.md
```

---

## Invariants

1. **Safe mode**: Never copy source HTML, CSS, or assets
2. **Persian-first**: All generated text in Farsi, direction=rtl, font=Vazirmatn
3. **PHP fallback**: Always works without API key (AI enriches when available)
4. **No site_id on packs**: Platform admin packs use `site_id=null`
5. **Dedup by UUID**: All block IDs are server-generated UUIDs
6. **Quality gate**: FAIL verdict warns user before install
