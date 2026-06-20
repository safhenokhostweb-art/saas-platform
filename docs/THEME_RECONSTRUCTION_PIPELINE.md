# THEME RECONSTRUCTION PIPELINE

**Component:** `ReverseThemeReconstructorService` + `VisualReverseEngineeringService`
**Sprint:** TB3 Sprint 9.5
**Version:** 1.0

---

## Pipeline Overview

```
Input
  ↓
[Orchestrator] VisualReverseEngineeringService
  ↓
  ├─ Phase 1: DomExtractionService::extract()
  │     → DomStructure (landmark + section data)
  │
  ├─ Phase 2: LayoutReconstructionService::reconstruct()
  │     → LayoutMap (columns, spacing, hierarchy, flow)
  │
  ├─ Phase 3: VisualAnalysisService::analyze()
  │     → VisualProfile (colors, typography, buttons, radius, shadow)
  │
  ├─ Phase 4: CommerceAnalysisService::analyze()
  │     → CommerceProfile (cards, prices, gallery, reviews, cart)
  │
  ├─ Phase 5: SimilarityEngineService::score()
  │     → SimilarityScores (per-site + overall)
  │
  ├─ Phase 6: ReverseThemeReconstructorService::reconstruct()
  │     → ThemePack (DB) + ThemeTemplates (DB)
  │
  └─ Phase 7: ReverseEngineeringQualityService::validate()
        → QualityReport (findings + verdict + score)
```

---

## Entry Points

### URL Mode
```php
$service->fromUrl('https://apple.com', 'Apple Theme', 'Focus on hero')
// 1. HTTP fetch with User-Agent spoofing
// 2. Full DOM XPath extraction
// 3. Complete 7-phase pipeline
```

### Screenshot Mode
```php
$service->fromScreenshot($base64Jpg, 'image/jpeg', 'Amazon Clone', 'Persian RTL')
// 1. Claude Vision API (claude-sonnet-4-6) → JSON analysis
// 2. DomExtractionService::extractFromVision()
// 3. Phases 2–7
```

### HTML Mode
```php
$service->fromHtml($htmlString, 'Custom Theme', 'Notes')
// 1. Full DOM XPath extraction (no HTTP fetch)
// 2. Complete 7-phase pipeline
```

### Prompt Mode
```php
$service->fromPrompt('فروشگاه شبیه دیجی‌کالا با رنگ قرمز', 'Digikala Clone')
// 1. Keyword heuristics for section detection
// 2. DomExtractionService::extractFromPrompt()
// 3. Phases 2–7 with PHP fallback (no HTML signals)
```

---

## Phase 6 Detail: Theme Reconstruction

### Global Styles Construction

```php
global_styles = {
  primary_color:    visualProfile.color_palette.primary,
  secondary_color:  visualProfile.color_palette.secondary,
  accent_color:     visualProfile.color_palette.accent,
  background_color: visualProfile.color_palette.background,
  text_color:       visualProfile.color_palette.text,
  font_family:      'Vazirmatn',   // always Persian RTL font
  border_radius:    visualProfile.border_radius.scale,
  direction:        'rtl',
  shadow:           visualProfile.shadow_system.card,
  dark_mode:        visualProfile.dark_mode,
}
```

### Section → Block Type Mapping

| Section | Block Type |
|---------|-----------|
| header | `nav_header` |
| hero (centered) | `hero` |
| hero (split) | `hero_split` |
| features (3-col) | `features_grid` |
| services | `services_cards` |
| pricing | `pricing_cards` |
| faq | `faq` |
| testimonials | `testimonials_slider` |
| blog | `card_grid` |
| products | `card_grid` |
| cta | `cta` |
| stats | `stats_row` |
| team | `team` |
| footer | `nav_footer` |

### AI vs PHP Fallback Decision

```
if ANTHROPIC_API_KEY set:
  → Claude claude-sonnet-4-6 generates complete ThemePack JSON
  → Full Persian content, accurate block props, professional layout
  → Falls back to PHP on JSON parse error

if no API key or AI fails:
  → ThemePlaceholderService generates per-section block data
  → Original Persian placeholder content from curated text banks
  → SVG placeholder images (no external dependencies)
```

### AI Prompt Structure

The AI receives:
1. Site name
2. Best structural match (e.g., "Digikala, score: 76")
3. Section list to generate
4. Whether it's a commerce site
5. Global styles object (colors, font, radius)
6. Primary color + font from visual analysis

The AI returns a complete ThemePack JSON with:
- `global_styles`, `responsive_settings`
- `headers[]`, `footers[]`
- `pages[]` with home page (6–10 blocks)
- `templates[]` — one per section type
- `demo_data` with site name and tagline

---

## Data Flow through DB

```
reverse_engineering_jobs
  id: 1
  status: complete
  dom_structure:    JSON (Phase 1 output)
  layout_map:       JSON (Phase 2 output)
  visual_profile:   JSON (Phase 3 output)
  commerce_profile: JSON (Phase 4 output)
  similarity_scores:JSON (Phase 5 output)
  theme_pack_id:    → theme_packs.id
  quality_report:   JSON (Phase 7 output)

theme_packs
  id: 42
  name: "Digikala Clone"
  pack_data.similarity.best_match: "digikala"
  tags: ["reverse-engineered", "tb3-sprint9_5", "match:digikala"]
  author: "Visual Reverse Engineering Engine (TB3 Sprint 9.5)"

theme_templates (one per section)
  tags: ["reverse-engineered", "tb3-sprint9_5"]
```

---

## Install Flow

```
POST /admin/theme-builder/reverse-engineer/{job}/install
  ↓
ThemePack.pack_data extracted
  → headers[0].blocks → Theme.header_blocks
  → footers[0].blocks → Theme.footer_blocks
  → pages[]           → Theme.pages (keyed by slug)
  → global_styles     → Theme.global_styles
  → responsive_settings → Theme.settings.responsive
  ↓
Theme created with source=generator, status=draft
  ↓
Redirect to Canvas editor for customization
```

---

## Safe Mode Specification

The engine NEVER copies:

| Prohibited | Why | Alternative |
|-----------|-----|-------------|
| Source HTML | Copyright | Original block structure |
| Source CSS | Copyright | Extracted design tokens only |
| Source images | Copyright | Inline SVG placeholders |
| Source text | Copyright | Persian placeholder text banks |
| Fonts via URL | License | Vazirmatn always |
| External scripts | Security | No external deps in pack |

Extracted for reconstruction:
- ✅ Color values (hex/rgb numbers)
- ✅ Typography properties (font names, size units)
- ✅ Layout measurements (column counts, max-width)
- ✅ Structural patterns (section order, grid type)
- ✅ Commerce feature presence (boolean detections)

---

## Error Handling

| Error | Response |
|-------|----------|
| URL fetch fails (4xx/5xx) | RuntimeException → job.status=failed |
| AI returns invalid JSON | PHP fallback activated, continues |
| AI API key missing | PHP fallback, no AI enrichment |
| Zero sections detected | Minimum set [header,hero,cta,footer] used |
| Quality FAIL | Warning on result page, install still allowed |
| DB write fails | Exception propagated, job.status=failed |

---

## Performance Notes

- URL fetch timeout: 20s
- AI API timeout: 90s
- PHP fallback: < 200ms
- DOM extraction (DOMXPath): < 50ms on 500KB HTML
- Total pipeline (with AI): 10–30s
- Total pipeline (PHP only): < 500ms

---

## Certification Checklist

- [x] Phase 1: DOM Extraction — header, mega-menu, hero, grids, cards, features, CTA, pricing, testimonials, FAQ, blog, footer
- [x] Phase 2: Layout Reconstruction — columns, containers, spacing, hierarchy, responsive, relationships
- [x] Phase 3: Visual Analysis — color palette, typography, buttons, radius, shadows, density
- [x] Phase 4: Commerce Analysis — cards, prices, discounts, gallery, reviews, specs, related, cross-sell, upsell
- [x] Phase 5: Similarity Engine — 6 reference sites, 4 dimensions, weighted overall, confidence level
- [x] Phase 6: Theme Reconstruction — AI + PHP fallback, ThemePack + ThemeTemplates, safe mode
- [x] Phase 7: Quality Validation — 10 checks, PASS/FAIL/PARTIAL verdict, score 0–100
- [x] Architecture docs (3 files)
- [x] Migration + Model + Controller + Routes + Views
- [x] Safe mode (no copyright violations)
- [x] Persian RTL + Vazirmatn font throughout
