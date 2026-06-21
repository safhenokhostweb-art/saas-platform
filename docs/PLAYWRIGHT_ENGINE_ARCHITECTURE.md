# Playwright Headless Engine — Architecture Reference

**Sprint:** TB3 Sprint 11.5  
**Date:** 2026-06-21

---

## Overview

The Playwright Headless Engine is a two-layer system: a **Node.js extraction layer** that runs the actual browser, and a **PHP orchestration layer** that calls it, generates snapshots, and feeds enriched data into the existing 7-phase RE pipeline.

```
HTTP Request
     │
     ▼
VisualReverseEngineeringService.fromUrl()
     │
     ├─ detectRenderMode() → SPA/CSR detected?
     │        │
     │        ▼ YES (or force_headless=true)
     │  fromUrlHeadless(job, url, device)
     │        │
     │        ▼
     │  PlaywrightExtractionService.extract(url, timeout, device)
     │        │
     │        ▼ proc_open → node scripts/headless/extract.mjs <url> <timeout> <device>
     │        │
     │        ▼ JSON payload (rendered_html, screenshot_base64, components, framework, performance, css_variables)
     │        │
     │  PlaywrightSnapshotService.generateAll(playwright, domStructure)
     │        │── generateDomSnapshot()  → sections, products, header, hero, footer, RTL, framework
     │        │── generateCssSnapshot()  → primary_color, fonts, radius, shadow, animations, style_framework
     │        └── generateLayoutSnapshot() → column_signature, section_grid_map, layout_style, density
     │        │
     │  runPipelineFromDom(job, domStructure, html, name, isHeadless=true, snapshots)
     │        │
     │        ├─ Phase 2: LayoutReconstructionService
     │        ├─ Phase 3: VisualAnalysisService
     │        ├─ Phase 4: CommerceAnalysisService
     │        ├─ Phase 5: SimilarityEngineService.score(... isHeadless=true, cssSnapshot, domSnapshot)
     │        ├─ Phase 6: ReverseThemeReconstructorService
     │        └─ Phase 7: ReverseEngineeringQualityService
     │
     └─ HTTP fallback (when Playwright unavailable)
          └─ runPipeline(job, html, url, name)
```

---

## Component Inventory

### `scripts/headless/extract.mjs` (Node.js)

Entry point for the Playwright browser. Called via `proc_open` from PHP.

**CLI:** `node extract.mjs <url> [timeoutMs=30000] [device=desktop]`

**Device viewports:**
| Name | Width | Height |
|------|-------|--------|
| desktop | 1440 | 900 |
| tablet | 768 | 1024 |
| mobile | 390 | 844 |
| all | Runs desktop + mobile, returns merged result |

**Extraction sequence:**
1. Launch Chromium (system Chrome or Playwright bundled)
2. Set viewport, navigate with `networkidle` wait
3. `scrollForLazyLoad()` — 4 scroll passes with 600ms delays to trigger lazy images/components
4. `waitForHydration()` — polls for SPA content signals (product cards, nav, h1) up to 5 seconds
5. Extract:
   - Rendered HTML (full DOM after JS execution)
   - Screenshot (base64 PNG, full-page)
   - Navigation items (up to 20)
   - Hero headline, subheadline, CTA buttons
   - Product grid (60+ selectors including `data-testid`, heuristics)
   - Colors (computed `getComputedStyle`, saturation-filtered, top 15)
   - Typography (font families, base size)
   - Sliders, filters, mega-menus, search, sections
   - CSS custom properties (design tokens from `document.styleSheets`)
   - ARIA landmarks
   - Framework fingerprints (Next.js, React, Vue, Angular, Nuxt, Tailwind, Bootstrap, MUI, Ant Design)
   - Performance metrics (navigation time, FCP, LCP, JS bundle count)
6. Output single JSON object to stdout

**Product detection selectors (60+):**
- Generic: `[class*="product"]`, `[class*="item"]`, `[class*="card"]`
- Data attributes: `[data-testid*="product"]`, `[data-product-id]`
- Persian: `[class*="محصول"]`, `[class*="کالا"]`
- Heuristics: Elements with price + image children (JS-detected)
- ARIA: `[role="listitem"]` inside `[role="list"]`

---

### `PlaywrightExtractionService.php`

PHP wrapper that manages the `proc_open` child process.

**Key behavior:**
- On Windows: uses temp files for stdout/stderr (avoids non-blocking pipe deadlock)
- On Linux: uses non-blocking pipes
- Timeout: 90s process timeout (kills `taskkill /F /T /PID` on Windows)
- Sets `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` env var when system Chrome is found
- Auto-discovers Node.js binary from known paths + `where.exe` fallback

---

### `PlaywrightSnapshotService.php`

Converts the raw Playwright JSON payload into typed snapshot objects consumed by the SimilarityEngine.

**Three snapshot types:**

#### DOM Snapshot
```json
{
  "section_count": 6,
  "sections_detected": ["header", "hero", "product_grid", "features", "cta", "footer"],
  "has_header": true,
  "has_hero": true,
  "has_footer": true,
  "product_count": 12,
  "nav_count": 7,
  "footer_link_count": 18,
  "is_rtl": false,
  "framework": "Next.js"
}
```

#### CSS Snapshot
```json
{
  "primary_color": "#27c0e2",
  "color_scheme": "light",
  "fonts": ["Vazirmatn", "sans-serif"],
  "border_radius": "8px",
  "shadow": "0 2px 8px rgba(0,0,0,0.1)",
  "has_animations": true,
  "style_framework": "tailwind",
  "css_variables": {"--color-primary": "#27c0e2", "--font-sans": "Vazirmatn"}
}
```

#### Layout Snapshot
```json
{
  "column_signature": "1-4-1",
  "section_grid_map": [
    {"type": "header", "columns": 1},
    {"type": "product_grid", "columns": 4},
    {"type": "footer", "columns": 1}
  ],
  "layout_style": "grid",
  "density": "medium"
}
```

---

### `SimilarityEngineService.php` — Headless Enhancements

**Extended signature:**
```php
public function score(
    array $domStructure,
    array $layoutMap,
    array $visualProfile,
    array $commerceProfile,
    bool  $isHeadless  = false,
    array $cssSnapshot = [],
    array $domSnapshot = [],
): array
```

**Headless quality bonus (`calcHeadlessQualityBonus`):**
```
+5 pts  if products >= 5
+3 pts  if sections >= 4
+4 pts  if colors detected (non-empty)
+2 pts  if nav items >= 5
+1 pt   if footer links >= 3
────────
Max: 15 pts
```

Applied to weighted sub-scores BEFORE brand boost:
- `$structureScore *= 0.8 + 0.2 * bonus/15`
- `$commerceScore *= 1.0 + 0.0 * bonus/15` (already has full data)
- `$layoutScore *= 0.6 + 0.4 * bonus/15`

**Brand boost by mode:**
- HTTP: +32 pts for known reference sites
- Headless: +38 pts (higher confidence)

**Confidence tiers:**
- 90%+: EXCELLENT (new tier)
- 70-89%: HIGH
- 50-69%: MEDIUM
- 30-49%: LOW
- <30%: VERY_LOW

---

### Admin Result View Sections

**Screenshot Preview** (`$meta['headless_screenshot']`)  
Stores PNG at `re-screenshots/{uuid}.png` via Laravel Storage. Displayed with `Storage::url()`.

**Performance Metrics** (`$meta['playwright']['performance']`)  
- Navigation time (ms) with fast/medium/slow badge
- FCP (ms) with good/needs-improvement/poor badge
- JS bundle count with optimized/medium/high badge
- CSS variable count with Design System badge

**Snapshots Explorer** (`$meta['snapshots']`)  
Three-column layout:
- CSS Snapshot: primary color swatch, color scheme, fonts, border-radius, animations flag
- DOM Snapshot: section count, product count, section type badges, RTL flag, framework
- Layout Snapshot: column signature, layout style, density, section grid map list

---

## Data Flow Diagram

```
extract.mjs output
    ├── rendered_html           → DomExtractionService.extractFromRenderedDom()
    ├── screenshot_base64       → Storage::put('re-screenshots/{uuid}.png')
    ├── components
    │   ├── nav                 → PlaywrightSnapshotService → DOM snapshot
    │   ├── hero                → PlaywrightSnapshotService → DOM snapshot
    │   ├── product_grid        → PlaywrightSnapshotService → DOM + Commerce
    │   └── sections_detected   → PlaywrightSnapshotService → DOM snapshot
    ├── framework               → PlaywrightSnapshotService → DOM snapshot
    ├── css_variables           → PlaywrightSnapshotService → CSS snapshot
    ├── colors                  → PlaywrightSnapshotService → CSS snapshot
    └── performance             → $meta['playwright']['performance']
```

---

## Fallback Chain

```
fromUrl(url, forceHeadless=false)
    │
    ├─ fetchUrl(url) → detectRenderMode()
    │   ├─ Signals: html < 8KB, Next.js __NEXT_DATA__, Vue __nuxt, Angular ng-version
    │   └─ needsHeadless() → true
    │
    ├─ playwright.isAvailable() → true
    │   └─ fromUrlHeadless() ← HEADLESS PATH
    │
    └─ playwright.isAvailable() → false
        ├─ Log warning: "SPA detected but Playwright unavailable"
        └─ runPipeline() ← HTTP FALLBACK
```

---

## Installation

```bash
# Install Node dependencies + Playwright browser
cd scripts/headless
npm install
npx playwright install chromium

# Verify
node extract.mjs https://www.apple.com 30000 desktop

# PHP availability check
php artisan tinker
>>> app(App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService::class)->availabilityReport()
```
