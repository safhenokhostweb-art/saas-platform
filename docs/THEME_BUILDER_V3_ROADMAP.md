# THEME BUILDER V3 — ELEMENTOR-GRADE AUDIT & EVOLUTION ROADMAP

> **Produced:** 2026-06-15  
> **Scope:** Full audit of current V2, gap analysis vs Elementor/Bricks/Divi, V3 architecture, marketplace evolution, demo dataset system, and phased implementation roadmap.  
> **Status:** Planning document — no code implemented.

---

## PART 1 — CURRENT STATE AUDIT

### 1.1 Platform Theme Builder (saas-platform)

**Location:** `c:\laragon\www\saas-platform`

#### Controllers

| Controller | File | Methods |
|---|---|---|
| `ThemeBuilderController` | `app/Http/Controllers/Admin/ThemeBuilderController.php` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `publish`, `unpublish`, `saveRevision`, `restoreRevision`, `cloneTheme`, `export`, `import`, `preview`, `assign` |
| `ThemeAiController` | `app/Http/Controllers/Admin/ThemeAiController.php` | `form`, `analyze`, `confirm`, `generate`, `analyzeAjax` |
| `ThemeMarketplaceAdminController` | `app/Http/Controllers/Admin/ThemeMarketplaceAdminController.php` | `index`, `updateListing`, `toggleFeatured`, `licenses`, `revokeLicense`, `grantLicense` |

**Canvas Controller (inline in ThemeBuilderController):** `canvas`, `canvasSave`, `canvasPreviewAjax`, `addPage`, `removePage`, `pagePreview`, `installToSite`, `syncPages`

#### Services

| Service | Key Methods |
|---|---|
| `ThemeBuilderService` | `createTemplate`, `createForSite`, `updateTheme`, `saveRevision`, `restoreRevision`, `publish`, `unpublish`, `resolveForSite`, `payloadForSite`, `cloneTheme`, `exportTheme`, `importTheme`, `defaultGlobalStyles` |
| `ThemeAiAnalyzerService` | `analyzeUrl`, `createFromJson`, `fetchPage`, `extractPageData`, `buildPrompt`, `callClaude`, `callGapGpt`, `parseThemeJson` |
| `GlobalStyleEngine` | `getCssVariables`, `renderCssBlock` — outputs 10+ `--tb-*` CSS vars (primary, secondary, accent, background, surface, text, fonts, radius, shadows) |
| `BlockRegistry` | `all`, `byCategory`, `categoryLabels`, `categoryIcons` — 80+ block definitions in 7 categories |
| `BlockRenderer` | `renderBlocks`, `renderBlock`, `setContext` — PHP server-side rendering, 30+ render methods for all block types |

#### Models

| Model | Table | Key Fields |
|---|---|---|
| `Theme` | `themes` | `name`, `slug`, `type`, `site_id`, `header_blocks`, `footer_blocks`, `pages` (JSON), `global_styles` (JSON), `status`, `version`, `is_marketplace`, `marketplace_price` |
| `ThemeRevision` | `theme_revisions` | `theme_id`, `version`, `label`, `snapshot`, `user_id` |
| `ThemeMarketplace` | `theme_marketplace` | `theme_id`, `marketplace_description`, `screenshots`, `is_featured`, `sales_count` |
| `ThemeLicense` | `theme_licenses` | `site_id`, `theme_id`, `license_key`, `is_permanent`, `purchased_at` |
| `ThemePurchaseOrder` | `theme_purchase_orders` | `site_id`, `theme_id`, `amount_rial`, `status`, `gateway`, `paid_at` |
| `ThemeProfile` | `theme_profiles` | `name`, `slug`, `preset_key`, `colors`, `surfaces`, `typography`, `borders`, `sidebar`, `header` — 8 built-in presets |

#### Views

```
resources/views/admin/theme-builder/
├── index.blade.php           — Theme list
├── create.blade.php          — Create form
├── edit.blade.php            — Meta edit (name/desc/status)
├── canvas.blade.php          — 3-panel canvas editor (THE MAIN EDITOR)
├── canvas-preview.blade.php  — Preview renderer
├── preview-frame.blade.php   — Header/footer preview iframe
├── ai-analyze.blade.php      — AI URL analyzer form
├── ai-confirm.blade.php      — AI analysis confirmation/generation

resources/views/admin/theme-marketplace/
├── index.blade.php           — Marketplace management
├── licenses.blade.php        — License management
```

#### Routes Summary (Platform)

```
GET  /admin/theme-builder                           → index
POST /admin/theme-builder                           → store
GET  /admin/theme-builder/{id}/edit                 → edit
PUT  /admin/theme-builder/{id}                      → update
DELETE /admin/theme-builder/{id}                    → destroy
POST /admin/theme-builder/{id}/publish              → publish
POST /admin/theme-builder/{id}/unpublish            → unpublish
GET  /admin/theme-builder/{id}/canvas               → canvas editor
PUT  /admin/theme-builder/{id}/canvas/save          → save blocks
POST /admin/theme-builder/{id}/canvas/pages         → add page
DELETE /admin/theme-builder/{id}/canvas/pages/{key} → delete page
GET  /admin/theme-builder/{id}/canvas/{key}/preview → page preview
POST /admin/theme-builder/{id}/install-to-site      → install to site
GET  /admin/theme-builder/{id}/export               → export JSON
POST /admin/theme-builder/import                    → import JSON
POST /admin/theme-builder/{id}/clone                → clone theme

GET  /admin/theme-builder/ai-analyze                → AI form
POST /admin/theme-builder/ai-analyze-ajax           → AI fetch
POST /admin/theme-builder/ai-generate               → AI generate

GET  /admin/theme-marketplace                       → marketplace
PUT  /admin/theme-marketplace/{id}/update-listing   → update listing
GET  /admin/theme-marketplace/licenses              → licenses
POST /admin/theme-marketplace/grant-license         → grant license

GET  /api/theme-marketplace                         → API: browse
POST /api/theme-marketplace/{id}/initiate-purchase  → purchase
GET  /api/theme-marketplace/{id}/download           → download JSON
```

#### Canvas Editor Architecture (V2)

The platform canvas editor (`canvas.blade.php`) is a **3-panel Alpine.js SPA**:

- **Panel A (Right — RTL):** Block library organized in 7 collapsible categories, 2-column grid, search filter
- **Panel B (Center):** Vertical sorted list of block cards — NOT a live rendered iframe. Each card shows block type label + text preview + category color bar + drag handle.
- **Panel C (Left):** Schema-driven props panel — 6 field types: `string`, `number`, `boolean`, `select`, `color`, `textarea`
- **Toolbar:** Page tabs, dirty indicator, Preview (new tab), Save (AJAX PUT), Publish
- **Global Styles Drawer:** 3 color pickers (primary, secondary, background) + font text input
- **SortableJS:** Drag-to-reorder within the block list

**Block data format (platform):** `{ id, type, data: { ...props } }`  
**Block data format (customer-site):** `{ id, type, props: { ...props } }` (normalizer bridges the gap)

---

### 1.2 Customer Site Theme System (customer-site)

**Location:** `c:\laragon\www\customer-site`

#### Controllers

| Controller | File | Methods |
|---|---|---|
| `ThemeDesignController` | `app/Http/Controllers/Admin/ThemeDesignController.php` | `index`, `update`, `activatePreset`, `resetPreset`, `canvasTheme`, `canvasDeactivate`, `canvasExport`, `canvasImport`, `canvasEdit`, `canvasSaveBlocks`, `canvasAddPage`, `canvasDeletePage`, `canvasSaveGlobalStyles`, `canvasPublish`, `canvasDiscardDraft`, `canvasPreviewPublic` |
| `ThemeCanvasPageController` | `app/Http/Controllers/Front/ThemeCanvasPageController.php` | `index`, `show`, `renderPage`, `prepareRenderer`, `buildMenuHtml`, `loadThemeData`, `findHomePage`, `findPage` |
| `PageBuilderController` | `app/Http/Controllers/Front/PageBuilderController.php` | `edit`, `update`, `preview` |
| `ContentBuilderController` | `app/Http/Controllers/Admin/ContentBuilderController.php` | `editPost`, `savePost`, `editProduct`, `saveProduct` |

#### Services

| Service | Description |
|---|---|
| `ThemeCanvasRenderer` | Extends `BlockRenderer`. Overrides `renderBlock` to intercept `module:*` types and render them with live DB data (Posts, Products, Courses, etc.) |
| `BlockRegistry` (customer-site) | Mirror of platform registry, same block types |

**Models:** `ThemeSetting` (`theme_settings` table) — basic CSS vars: primary/secondary colors, fonts, border-radius, button style

#### Routes (Customer Site)

```
GET  /admin/theme-design                              → settings dashboard
GET  /admin/theme-design/canvas                       → canvas info/management
GET  /admin/theme-design/canvas/edit                  → 3-panel editor
PUT  /admin/theme-design/canvas/save-blocks           → save blocks AJAX
PUT  /admin/theme-design/canvas/global-styles         → save global styles
POST /admin/theme-design/canvas/publish               → publish draft
POST /admin/theme-design/canvas/discard-draft         → discard draft

GET  /builder/edit/{page}                             → Visual Page Builder
PUT  /builder/page/{page}                             → save page builder content
GET  /admin/builder/posts/{post}                      → Post visual builder
PUT  /admin/builder/posts/{post}                      → save post
GET  /admin/builder/products/{product}                → Product visual builder
PUT  /admin/builder/products/{product}                → save product

GET  /                                                → ThemeCanvasPageController@index
GET  /page/{slug}                                     → ThemeCanvasPageController@show
```

#### Visual Page Builder

`customer-site/resources/views/builder/edit.blade.php` — Standalone HTML (no `@extends`, CDN Tailwind + Alpine.js + SortableJS).  
Block format: `{blocks: [{id, type, props}]}` stored in:
- `pages.builder_content_v2` (JSON)
- `posts.builder_content` (JSON)  
- `products.builder_content` (JSON)

---

### 1.3 Identified Technical Debt

| ID | Severity | Issue |
|---|---|---|
| TD-001 | HIGH | `block.data` (platform) vs `block.props` (customer-site) inconsistency — normalizer bridges but adds fragility |
| TD-002 | HIGH | Center panel in both editors is a block LIST, not a live rendered page — users cannot see how the page looks while editing |
| TD-003 | MEDIUM | Nested containers (section→columns→grid→widget) exist in schema but editor UI is flat — no recursive visual tree |
| TD-004 | MEDIUM | GlobalStyleEngine has 10+ CSS vars but canvas editor global styles drawer only exposes 3 (primary, secondary, background) + font |
| TD-005 | MEDIUM | Revision history exists in DB (ThemeRevision model) but UI provides no revision comparison or per-revision preview |
| TD-006 | LOW | Visual Page Builder and Canvas Editor are two separate systems — divergent UX patterns for content editing |
| TD-007 | LOW | Image fields in props panel are text inputs (URL string) — no upload, gallery, or crop capability |

---

## PART 2 — GAP ANALYSIS

### 2.1 Feature Comparison Matrix

| Feature | Elementor | Bricks | Divi | Current V2 | Gap Level |
|---|---|---|---|---|---|
| **1. Inline Editing** | ✅ Click text to edit in-place | ✅ Full inline | ✅ Full inline | ❌ Props panel only | MISSING |
| **2. Real Drag & Drop** | ✅ Drag from library to canvas | ✅ Full D&D to canvas | ✅ Full D&D | ⚠️ Click to add; sort-only drag | PARTIAL |
| **3. Nested Containers** | ✅ Section→Column→Widget tree | ✅ Full nesting | ✅ Full nesting | ⚠️ Schema supports it; editor is flat | PARTIAL |
| **4. Multi-column Layouts** | ✅ Visual column resizing | ✅ Visual resize | ✅ Visual resize | ⚠️ `columns` block with ratio string | PARTIAL |
| **5. Responsive Editing** | ✅ Desktop/Tablet/Mobile toggle | ✅ 4 breakpoints | ✅ 3 breakpoints | ⚠️ Visual Page Builder only, not canvas | PARTIAL |
| **6. Typography Controls** | ✅ Font/size/weight/line-height per block | ✅ Full typography | ✅ Full typography | ❌ No per-block typography | MISSING |
| **7. Color System** | ✅ Global palette + per-element | ✅ Color palette | ✅ Global colors | ⚠️ 3 global colors only | PARTIAL |
| **8. Design Tokens** | ✅ Global kit with variables | ⚠️ Partial | ⚠️ Partial | ⚠️ GlobalStyleEngine has tokens; no UI | PARTIAL |
| **9. Media Manager** | ✅ Upload/Library/Search/Crop | ✅ Full | ✅ Full | ❌ Text input URL only | MISSING |
| **10. Image Crop** | ✅ In-place crop & focal point | ✅ Crop | ✅ Crop | ❌ Not implemented | MISSING |
| **11. Global Styles** | ✅ Kit: colors/fonts/buttons | ✅ Global styles | ✅ Global styles | ⚠️ 3 colors + 1 font only | PARTIAL |
| **12. Theme Templates** | ✅ Page, Section, Block templates | ✅ Templates | ✅ Layouts | ⚠️ Full pages only; no section templates | PARTIAL |
| **13. Header Builder** | ✅ Visual header with nav builder | ✅ Dedicated header | ✅ Theme Builder | ⚠️ Header block exists; no nav builder | PARTIAL |
| **14. Footer Builder** | ✅ Visual footer with widgets | ✅ Dedicated footer | ✅ Theme Builder | ⚠️ Footer block exists; no nav builder | PARTIAL |
| **15. Dynamic Content** | ✅ ACF, custom fields, post data | ✅ Query builder | ✅ Dynamic content | ⚠️ `module:*` blocks pull DB data | PARTIAL |
| **16. Template Library** | ✅ 300+ section templates | ✅ Template library | ✅ Layout library | ⚠️ NEXUS PRO themes only; no sections | PARTIAL |
| **17. Theme Marketplace** | ✅ Template Kits marketplace | ⚠️ Limited | ❌ None | ✅ Full marketplace with licensing | SUPPORTED |
| **18. Versioning** | ✅ Revision history with preview | ⚠️ Revisions | ✅ Undo history | ⚠️ ThemeRevision model; no UI preview | PARTIAL |
| **19. Rollback** | ✅ One-click restore | ⚠️ Partial | ✅ Rollback | ⚠️ `restoreRevision()` exists; no UI | PARTIAL |
| **20. Live Preview** | ✅ Real-time iframe canvas | ✅ Live iframe | ✅ Live preview | ❌ Separate tab preview only | MISSING |

### 2.2 Critical Missing Features

**MISSING (must build from scratch):**
1. Live iframe canvas — the center panel must show the actual rendered page, not a block list
2. Inline editing — click any text/button in the iframe to edit it directly
3. Typography controls per block
4. Media manager with upload, library, crop
5. Image focal point selector

**PARTIAL (upgrade required):**
6. Responsive editor — extend from visual-builder-only to canvas editor
7. Design token editor — expose all GlobalStyleEngine vars, not just 3
8. Section-level template saving/inserting
9. Nested container visual editor — render the block tree recursively
10. Header/Footer nav builder
11. Revision history UI with diff view
12. Global color palette with 8+ swatches

---

## PART 3 — THEME BUILDER V3 ARCHITECTURE

### 3.1 Design Principles

V3 must achieve:
- **Live canvas** — users see the actual rendered page while editing
- **Click-to-edit** — inline text editing with contenteditable or overlay input
- **Unified block format** — eliminate `block.data` vs `block.props` split
- **Professional property panel** — Content / Style / Advanced tabs per Elementor
- **Zero build-tool dependency** — CDN-based like the current Visual Page Builder (avoids Vite production issues)

---

### TB-01 — Container Engine

**Purpose:** Enable nested visual layouts with sections, containers, rows, and columns.

**Block hierarchy:**
```
Page
└── Section (full-width band, background, padding)
    └── Container (max-width wrapper, centering)
        └── Row (flex row)
            ├── Column (flex child, width fraction)
            │   └── Widget (leaf block: text, image, button…)
            └── Column
                └── Widget
```

**Schema changes:**
- `section.children[]` — list of `container|row|column|widget` blocks
- `column.width` — fraction value: `1/2`, `1/3`, `2/3`, `1/4`, `3/4`, `full`
- `row.cols` — shorthand for equal columns: `2`, `3`, `4`, `5`, `6`

**Rendering:** `BlockRenderer::renderSection()` already exists. Upgrade to recursive rendering with CSS Grid/Flexbox output per column fraction.

**Editor:** Center panel renders each Section as a collapsed/expanded node. Columns are shown as side-by-side colored placeholders. Widgets render inside columns.

---

### TB-02 — Inline Editing

**Purpose:** User clicks directly on text/heading/button/image caption inside the live canvas and edits it. No props-panel-only workflow.

**Implementation strategy:**

The live canvas (iframe or div overlay) renders server-generated HTML. Inline editing is achieved via:
1. `contenteditable="true"` on text/heading elements
2. `data-tb-prop="title"` attribute on each editable element
3. Alpine.js `@blur` / `@input` handler syncs changes back to the block's props object
4. On save, props reflect the inline-edited values

**Editable elements:**
| Block Type | Inline-Editable Fields |
|---|---|
| `heading` | `text`, `tag` |
| `text` | `content` (rich text — Quill.js) |
| `button` | `label`, `url` |
| `hero` | `title`, `subtitle`, `cta_label` |
| `image` | `alt` text, `caption` |
| `cta` | `title`, `description`, `button_label` |

**Non-inline (always panel):** Colors, spacing, layout properties, module blocks.

---

### TB-03 — Professional Property Panel

**Purpose:** Replace flat schema-driven form with tabbed professional panel.

**Tab structure:**

```
┌─────────────────────────────────────────┐
│  [Content]  [Style]  [Advanced]          │
└─────────────────────────────────────────┘
```

**Content Tab:** Block-specific fields (title, text, image URL, items list, etc.) — same as current props panel but with rich field types:
- `richtext` — Quill.js editor embedded in panel
- `image` — opens media manager
- `repeater` — list of sub-items with add/remove/reorder
- `link` — URL + target + rel
- `icon` — icon picker

**Style Tab:** Universal per-block style overrides:

```
Background
  ├── Color (solid, gradient, image)
  ├── Gradient (angle, stops)
  └── Image (URL, position, size, repeat)

Typography
  ├── Font family
  ├── Weight (100–900)
  ├── Size (px/rem/vw)
  ├── Line height
  ├── Letter spacing
  └── Color

Spacing
  ├── Padding (T/R/B/L, lock toggle)
  └── Margin (T/R/B/L, lock toggle)

Border
  ├── Style (none/solid/dashed/dotted)
  ├── Width
  ├── Color
  └── Radius (T/R/B/L corners)

Box Shadow
  ├── X, Y offset
  ├── Blur
  ├── Spread
  └── Color

Opacity
  └── Range 0–100%
```

**Advanced Tab:**
- Custom CSS class
- Custom CSS (block-scoped)
- Entrance animation (fade-in, slide-up, zoom-in, etc.)
- Animation delay & duration
- Responsive: hide on Mobile / Tablet / Desktop
- z-index override
- Motion reduction (respects `prefers-reduced-motion`)

**Data model for per-block styles:**
```json
{
  "id": "blk_abc123",
  "type": "heading",
  "props": { "text": "سلام دنیا", "tag": "h2" },
  "style": {
    "background_color": "#f0f4ff",
    "padding": "2rem 1.5rem",
    "border_radius": "0.5rem",
    "font_size": "2rem",
    "color": "#1e1e2e",
    "animation": "fade-in-up"
  },
  "responsive": {
    "mobile": { "font_size": "1.25rem", "hidden": false },
    "tablet": { "font_size": "1.5rem" }
  }
}
```

---

### TB-04 — Media Manager

**Purpose:** Replace text-input image fields with a full media manager modal.

**Features:**
- Upload tab — drag-and-drop or file picker
- Library tab — all uploaded images for this site (paginated grid)
- Search — by filename
- Select & insert — returns URL + metadata to the block prop
- Crop — simple aspect-ratio crop before insert
- Delete — remove from library
- Image metadata badges (SmartImageUploadService already exists — reuse)

**Integration point:** Triggered when any `image` or `image_url` type field is focused in the Property Panel. Opens as a slide-over modal.

**Storage:** Uses existing `SmartImageUploadService` (already in customer-site). Same WebP conversion + GD processing.

**API endpoints needed:**
```
GET  /admin/media                       → paginated library
POST /admin/media/upload                → upload + WebP convert
DELETE /admin/media/{id}                → delete
POST /admin/media/{id}/crop             → crop & save new variant
```

---

### TB-05 — Responsive Preview

**Purpose:** See exactly how the design looks on desktop, tablet, and mobile — within the editor.

**Implementation:**

Toolbar adds a device toggle:
```
[🖥 Desktop] [📱 Tablet] [📱 Mobile]
```

The center iframe/canvas applies a CSS class that constrains max-width:
- Desktop: `max-w-none` (full width)
- Tablet: `max-w-[768px]`
- Mobile: `max-w-[375px]`

The BlockRenderer outputs `data-tb-responsive` classes that match the `responsive` block property, enabling the preview to show/hide blocks by breakpoint in real time.

Responsive-specific style overrides (from TB-03 Advanced tab) are applied as scoped CSS injected into the preview.

---

### TB-06 — Theme Builder Templates

**Header Templates:** Dedicated header page (`__header__` key, already exists) with:
- Visual nav builder: add/reorder nav items with label + URL
- Logo block: image + site name text
- CTA button block
- Sticky header toggle

**Footer Templates:** Dedicated footer page (`__footer__` key, already exists) with:
- Footer columns layout
- Social links
- Copyright text
- Newsletter signup widget

**Archive Templates:** New page type `__archive_posts__`, `__archive_products__`, `__archive_courses__` — rendered when visiting `/blog`, `/shop`, `/courses`

**Single Templates:** New page type `__single_post__`, `__single_product__`, `__single_course__` — rendered for individual content pages

**Landing Pages:** Any custom page (already works)

**Template page types in DB:**
```php
Theme::TYPE_FULL = 'full';        // existing
Theme::TYPE_HEADER = 'header';    // existing
Theme::TYPE_FOOTER = 'footer';    // existing
Theme::TYPE_ARCHIVE = 'archive';  // NEW
Theme::TYPE_SINGLE = 'single';    // NEW
Theme::TYPE_LANDING = 'landing';  // NEW
```

---

### 3.2 V3 Editor Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  TOOLBAR: [← Back] [Theme Name] | [Page Tabs] | [Responsive] [History]  │
│           [Preview] [Save Draft] [Publish] [⋯ More]                      │
├─────────┬─────────────────────────────────────────────┬──────────────────┤
│         │                                             │                   │
│ LEFT    │         LIVE IFRAME CANVAS                  │  RIGHT PANEL     │
│ PANEL   │                                             │                  │
│         │  ┌───────────────────────────────────────┐  │  [Content] [Style]│
│ [Blocks]│  │                                       │  │  [Advanced]      │
│ [Pages] │  │   ← actual rendered page HTML →       │  │                  │
│ [Settings]│  │                                       │  │  Per-block or   │
│         │  │   Click element → inline edit          │  │  global styles   │
│ Block   │  │   Drag from left → drop here           │  │                  │
│ Library │  │   Click block border → select          │  │  Media Manager   │
│         │  │                                        │  │  (inline slide)  │
│ Category│  └───────────────────────────────────────┘  │                  │
│ Accordion│                                             │  Typography      │
│         │  Device: [🖥] [⊞] [📱]  Zoom: [100%▾]      │  Spacing         │
│         │                                             │  Border          │
└─────────┴─────────────────────────────────────────────┴──────────────────┘
```

---

## PART 4 — MARKETPLACE EVOLUTION

### 4.1 Platform Admin Capabilities (saas-platform)

| Action | Current | V3 Target |
|---|---|---|
| Create theme | ✅ | ✅ Enhanced with template types |
| Version theme | ⚠️ Revision model only | ✅ Semantic versioning (1.0.0, 1.1.0) |
| Publish theme | ✅ | ✅ + Changelog per version |
| Sell theme | ✅ Zarinpal/IDPay | ✅ + Pricing tiers (Free/Pro/Enterprise) |
| Distribute theme | ✅ ThemeLicense | ✅ + Auto-update push to licensed sites |
| Create theme variants | ❌ | ✅ Color scheme variants per theme |
| Demo content bundling | ❌ | ✅ Demo dataset per theme |
| Bundle sections | ❌ | ✅ Publish reusable section templates |

### 4.2 Customer Site Capabilities

| Action | Current | V3 Target |
|---|---|---|
| Browse marketplace | ✅ | ✅ + Filter by category/type/price |
| Install theme | ✅ | ✅ + Preview before install |
| Update theme | ❌ | ✅ One-click update with changelog |
| Duplicate theme | ❌ | ✅ Clone for experimental editing |
| Customize theme | ✅ Canvas editor | ✅ V3 editor with full style panel |
| Rollback to version | ❌ | ✅ Version history per theme |
| Import section template | ❌ | ✅ From section library |
| Create marketplace theme | ❌ CANNOT | ❌ CANNOT (enforced — platform only) |

### 4.3 Theme Distribution Architecture

```
Platform Admin
  → Creates Theme (V3 editor)
  → Adds semantic version (e.g. 1.2.0)
  → Bundles demo dataset reference
  → Publishes to marketplace

Marketplace API (/api/theme-marketplace)
  → Returns theme payload + version manifest
  → Licensed sites receive update notification

Customer Site
  → Polls /api/theme-marketplace/updates (daily)
  → Shows "Update available" badge
  → Admin clicks Update → pulls new payload
  → ThemeRevision auto-created before update (rollback safety)
```

### 4.4 New Marketplace DB Schema

```sql
-- Version history per marketplace theme
CREATE TABLE theme_versions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    theme_id BIGINT NOT NULL REFERENCES themes(id),
    version VARCHAR(20) NOT NULL,        -- '1.2.0'
    changelog TEXT,
    payload_snapshot JSON,
    demo_dataset_key VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Section template library
CREATE TABLE theme_sections (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    preview_image VARCHAR(500),
    blocks JSON NOT NULL,
    is_free BOOLEAN DEFAULT TRUE,
    is_premium BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## PART 5 — DEMO DATASET SYSTEM

### 5.1 Concept

`safhenokhost.ir` becomes the official **Demo Site** for the platform.

When a theme designer is working in the Platform Theme Builder, they see **realistic demo content** rather than placeholder text. This allows accurate visualization of how a theme will look when deployed to a real tenant site.

### 5.2 Architecture

```
Demo Site (safhenokhost.ir)
  site_id = DEMO_SITE_ID (env: DEMO_SITE_ID=X)
  
Demo Dataset includes:
  - 10 demo Posts (various categories)
  - 8 demo Products (various types, with images)
  - 4 demo Courses (with walkthroughs)
  - 3 demo Forms (contact, subscribe, quiz)
  - Navigation menus (main, footer)
  - 6 demo Categories
```

### 5.3 Demo Data Seed

A new artisan command:

```bash
php artisan theme:seed-demo --site-id=X
```

Seeds the demo site with:
- Predefined posts with diverse content
- Products with prices, images, categories
- Courses with walkthroughs
- Category structure
- Menu config in `settings` table

### 5.4 Demo vs Live Data Switch

**While designing (platform admin):**

`BlockRenderer::setContext(['demo_mode' => true, 'demo_site_id' => X])`

Module blocks (`module:blog_posts`, `module:shop_product_grid`, `module:courses_grid`, etc.) pull data from the demo site.

**After installation (tenant site):**

`BlockRenderer::setContext(['demo_mode' => false, 'site_id' => tenant_site_id])`

Module blocks pull data from the tenant's own content.

**How the switch works:**

```php
// In ThemeCanvasRenderer::renderModuleBlock()
$siteId = $this->context['demo_mode']
    ? $this->context['demo_site_id']
    : $this->context['site_id'];

$posts = Post::published()->where('site_id', $siteId)->take(6)->get();
```

No block JSON changes needed — the data source shifts automatically.

### 5.5 Demo Data Isolation

- Demo posts/products/courses have `is_demo = true` flag (add to each content table)
- Never shown to real users on tenant sites
- Cleanup command: `php artisan theme:clear-demo --site-id=X`

### 5.6 DB Changes for Demo Support

```sql
ALTER TABLE posts     ADD COLUMN is_demo BOOLEAN DEFAULT FALSE;
ALTER TABLE products  ADD COLUMN is_demo BOOLEAN DEFAULT FALSE;
ALTER TABLE courses   ADD COLUMN is_demo BOOLEAN DEFAULT FALSE;
ALTER TABLE settings  ADD COLUMN is_demo_site BOOLEAN DEFAULT FALSE;
```

---

## PART 6 — IMPLEMENTATION ROADMAP

### THEME_BUILDER_V3_ROADMAP.md — Phase Table

---

### Phase TB3-01 — Unified Block Format & Data Normalizer

**Priority:** CRITICAL (prerequisite for all other phases)  
**Effort:** 0.5 days  
**Complexity:** LOW  
**Risk:** MEDIUM (touches both repos)

**Problem:** `block.data` (platform) vs `block.props` (customer-site) creates fragility.

**Solution:** Standardize on `block.props` everywhere. Run a migration normalizer on DB rows. Update `BlockRenderer::renderBlock()` to read only `block.props`. Remove the ad-hoc normalizers.

**DB Changes:** None (JSON column, in-place transformation via migration script)  
**API Changes:** All endpoints that accept/return block JSON must use `props`  
**Frontend Changes:** Platform `canvas.blade.php` — change `block.data[propKey]` → `block.props[propKey]`  
**Tests:** Update all `ThemeBuilderTest` and `CanvasTest` fixtures

---

### Phase TB3-02 — Live Iframe Canvas

**Priority:** CRITICAL  
**Effort:** 3–4 days  
**Complexity:** HIGH  
**Risk:** HIGH (largest architectural change)

**Problem:** Center panel shows block list, not rendered page.

**Solution:**

Replace the center block-list div with an `<iframe>` pointing to a `/admin/theme-builder/{id}/canvas/live-preview` endpoint. The endpoint:
1. Renders all blocks server-side via `BlockRenderer` with demo data context
2. Injects a JavaScript bridge: `window.tbBridge = { selectBlock, updateProp, moveBlock }`
3. Canvas editor outer window communicates via `postMessage`

```
Outer Window (Editor)          ←postMessage→    Inner iframe (Rendered Page)
  Alpine: selectedBlock                            contenteditable elements
  Panel: update props                              data-tb-block-id attributes
  Toolbar: device resize                           tbBridge.selectBlock(id)
```

**Live update flow:**
1. User edits prop in Panel → `updateProp(id, key, value)` called
2. Editor sends `postMessage({ action: 'update', blockId, key, value })`
3. iframe JS applies inline style / updates DOM node / triggers PHP re-render for complex blocks
4. For simple text blocks: DOM update only (no server round-trip)
5. For module blocks: AJAX re-render of just that block

**DB Changes:** None  
**API Changes:** New endpoint `GET /admin/theme-builder/{id}/canvas/live-preview?page={key}&token={token}`  
**Frontend Changes:** Replace center panel entirely; add postMessage bridge  
**Estimated Effort:** 3–4 days

---

### Phase TB3-03 — Drag from Library to Canvas

**Priority:** HIGH  
**Effort:** 1.5 days  
**Complexity:** MEDIUM  
**Risk:** MEDIUM

**Problem:** Currently "add block" is click-to-append. No drag-from-library-to-position.

**Solution:** 

Use SortableJS cross-list dragging:
- Block library items become SortableJS draggable items
- Canvas drop zones appear between existing blocks on drag-hover
- On drop: `addBlockAtPosition(type, index)` called

The iframe approach (TB3-02) complicates this because dragging crosses iframe boundary. Alternative: use an overlay DIV on top of the iframe that intercepts drag events.

**DB Changes:** None  
**API Changes:** None  
**Frontend Changes:** SortableJS cross-list config, iframe overlay drag handler

---

### Phase TB3-04 — Inline Editing

**Priority:** HIGH  
**Effort:** 2 days  
**Complexity:** HIGH  
**Risk:** MEDIUM

**Problem:** No in-place editing — all editing goes through props panel.

**Solution:**

In the live iframe (TB3-02 prerequisite), add `contenteditable="true"` and `data-tb-prop` attributes to text nodes during editor mode.

```html
<h2 
  contenteditable="true" 
  data-tb-block-id="blk_abc"
  data-tb-prop="text"
  @blur="tbBridge.syncProp(this)"
>عنوان صفحه</h2>
```

On blur, the bridge sends `postMessage({ action: 'propChanged', blockId, prop, value })` to the outer editor. Outer editor updates its block state (which syncs back to panel).

For rich text (paragraph blocks): use Quill.js inside the iframe. On blur, serialize delta → HTML and sync.

**DB Changes:** None  
**API Changes:** None  
**Frontend Changes:** BlockRenderer adds data attributes in editor mode; inline bridge script

---

### Phase TB3-05 — Professional Property Panel (Content/Style/Advanced)

**Priority:** HIGH  
**Effort:** 2 days  
**Complexity:** MEDIUM  
**Risk:** LOW

**Problem:** Flat props panel with 6 field types. No Style or Advanced tab.

**Solution:**

Replace single-tab props panel with 3-tab structure:

**Content Tab:** Same as current schema-driven panel. Add new field types:
- `richtext` — Quill.js instance in panel
- `image` — opens Media Manager modal (TB3-07)
- `repeater` — dynamic list with add/remove/reorder
- `link` — URL + target + nofollow

**Style Tab:** Universal CSS-property controls (same for every block):
```
Background: type (none/solid/gradient/image) | color | gradient stops | image URL
Typography: family | weight | size | line-height | spacing | color
Spacing: padding T/R/B/L | margin T/R/B/L (with lock-corners toggle)
Border: style | width | color | radius T/R/B/L
Shadow: X | Y | blur | spread | color
Opacity: 0–100%
```

**Advanced Tab:**
```
Extra CSS class
Custom CSS (textarea, scoped to this block)
Entrance animation (none / fade-in / slide-up / zoom-in / slide-right)
Animation delay (ms)
Animation duration (ms)
Responsive visibility: [☑ Show on Desktop] [☑ Show on Tablet] [☑ Show on Mobile]
```

**Block format addition:** `block.style{}` and `block.responsive{}` objects

**DB Changes:** None (JSON column, backward compatible)  
**API Changes:** None  
**Frontend Changes:** Panel UI rebuild, Alpine.js extended state

---

### Phase TB3-06 — Typography System

**Priority:** MEDIUM  
**Effort:** 1 day  
**Complexity:** MEDIUM  
**Risk:** LOW

**Problem:** No typography controls. All blocks inherit global font.

**Solution:**

**Global Typography Presets** in Global Styles panel:
```
Heading font:    Vazirmatn (dropdown or text)
Body font:       Vazirmatn
H1 size:         2.5rem
H2 size:         2rem
H3 size:         1.5rem
Body size:       1rem
Line height:     1.7
```

These become `--tb-h1-size`, `--tb-h2-size`, etc. CSS vars rendered by `GlobalStyleEngine`.

**Per-block typography** in Style Tab (TB3-05): overrides global values.

**Font loader:** Add Google Fonts API integration — user types font name, system appends `<link>` to preview iframe.

**DB Changes:** Extend `global_styles` JSON with `typography.*` sub-keys (GlobalStyleEngine already supports this)  
**API Changes:** None  
**Frontend Changes:** Global Styles drawer extended; Style tab typography section

---

### Phase TB3-07 — Media Manager

**Priority:** MEDIUM  
**Effort:** 2 days  
**Complexity:** MEDIUM  
**Risk:** LOW

**Problem:** Image fields are text-input URLs. No upload, library, or crop.

**Solution:**

New `MediaManagerController` in customer-site:
- `GET /admin/media` — paginated library (images only, for now)
- `POST /admin/media/upload` — reuse SmartImageUploadService (WebP, resize)
- `DELETE /admin/media/{id}` — delete
- `POST /admin/media/{id}/crop` — client-side crop coordinates → server crop via GD

UI: Slide-over modal triggered from image fields in props panel.
- Tab 1: Upload (drag-drop zone)
- Tab 2: Library (grid of thumbnails, search by name)
- Click thumbnail → inserts URL into block prop
- Crop button → opens simple aspect-ratio cropper (Cropper.js)

**New Table:**
```sql
CREATE TABLE media_library (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT NOT NULL,
    filename VARCHAR(300),
    path VARCHAR(500),
    url VARCHAR(500),
    size INT,
    width INT,
    height INT,
    mime_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**DB Changes:** 1 new table  
**API Changes:** 3 new routes  
**Frontend Changes:** Media Manager modal component, Cropper.js CDN, image field type upgrade

---

### Phase TB3-08 — Responsive Editor

**Priority:** MEDIUM  
**Effort:** 1 day  
**Complexity:** LOW  
**Risk:** LOW

**Problem:** Responsive editing only exists in Visual Page Builder, not in canvas editor.

**Solution:**

Add device toggle to canvas toolbar:
```html
<button data-device="desktop" class="active">🖥</button>
<button data-device="tablet">⊞</button>
<button data-device="mobile">📱</button>
```

Selected device constrains iframe width:
- Desktop: 100%
- Tablet: 768px centered
- Mobile: 375px centered

Advanced Tab (TB3-05) allows hiding blocks per breakpoint. CSS output includes:
```css
@media (max-width: 768px) {
  [data-block-id="blk_xyz"] { display: none !important; }
}
```

**DB Changes:** None  
**API Changes:** None  
**Frontend Changes:** Toolbar device toggle, iframe constraint, responsive block style generation

---

### Phase TB3-09 — Design Token Editor

**Priority:** MEDIUM  
**Effort:** 1 day  
**Complexity:** LOW  
**Risk:** LOW

**Problem:** GlobalStyleEngine has 10+ `--tb-*` CSS vars but editor only exposes 3 colors + 1 font.

**Solution:**

Replace the current Global Styles drawer (3 inputs) with a full-featured design token editor:

```
Colors
  ├── Primary       [🎨 #4f46e5]
  ├── Secondary     [🎨 #6366f1]
  ├── Accent        [🎨 #818cf8]
  ├── Background    [🎨 #ffffff]
  ├── Surface       [🎨 #f9fafb]
  ├── Text          [🎨 #111827]
  └── Text Muted    [🎨 #6b7280]

Typography
  ├── Heading font  [Vazirmatn        ]
  ├── Body font     [Vazirmatn        ]
  └── Scale         [1.25▾]

Borders
  ├── Radius SM     [0.25rem]
  ├── Radius MD     [0.5rem]
  └── Radius LG     [0.75rem]

Shadows
  ├── Small         [...]
  ├── Medium        [...]
  └── Large         [...]

Dark Mode
  └── [Enable] (toggle shows dark color overrides)

Custom CSS
  └── [textarea for global custom CSS]
```

**DB Changes:** None (extends existing `global_styles` JSON)  
**API Changes:** None  
**Frontend Changes:** Global Styles panel rebuilt

---

### Phase TB3-10 — Nested Container Visual Editor

**Priority:** MEDIUM  
**Effort:** 2 days  
**Complexity:** HIGH  
**Risk:** MEDIUM

**Problem:** Schema supports nested `section→container→columns→widgets` but editor UI is flat.

**Solution:**

The live iframe (TB3-02) naturally solves this for the center panel — sections render visually with nested columns.

For the **left block list** (when not in live-iframe mode, as fallback), render a tree view:
```
📦 Section (Hero Area)
  └── ⬛ Container (2 columns)
        ├── ⬜ Column (1/2)
        │   └── 🔠 Heading
        └── ⬜ Column (1/2)
            └── 🖼️ Image
```

Clicking any item in the tree selects it and opens its props.

Adding a widget to a column: drag from library to the column placeholder.

**DB Changes:** None  
**API Changes:** None  
**Frontend Changes:** Tree view component, recursive Alpine rendering

---

### Phase TB3-11 — Revision History UI

**Priority:** LOW  
**Effort:** 1 day  
**Complexity:** LOW  
**Risk:** LOW

**Problem:** ThemeRevision model exists but UI only has "Save Revision" button with no management.

**Solution:**

Revision panel (slide-over from toolbar):
- List of saved revisions with timestamp and label
- Click any revision to preview in new tab
- "Restore" button creates new working copy from that revision
- Auto-save revision before every publish

**DB Changes:** None (ThemeRevision model complete)  
**API Changes:** `GET /admin/theme-builder/{id}/revisions` → list  
**Frontend Changes:** Revision slide-over panel

---

### Phase TB3-12 — Section Template Library

**Priority:** LOW  
**Effort:** 1.5 days  
**Complexity:** MEDIUM  
**Risk:** LOW

**Problem:** No way to save/reuse section designs. Template library has full themes but not sections.

**Solution:**

**Save Section:** Right-click menu on any Section block → "Save as Template" → enters name → saves to `theme_sections` table.

**Insert Section:** In left panel, add a "Sections" tab alongside "Blocks". Shows saved sections with preview thumbnails.

**Platform Sections vs Tenant Sections:**
- Platform admin publishes section templates to `theme_sections` with `site_id = NULL`
- Tenant can save their own sections with `site_id = tenant_id`
- Both types appear in tenant's section library (platform first)

**DB Changes:** `theme_sections` table (see Part 4)  
**API Changes:** `GET/POST /admin/theme-builder/sections`, `POST /admin/theme-builder/sections/{id}/clone`  
**Frontend Changes:** Sections tab in left panel, save-as-template context menu

---

### Phase TB3-13 — Header/Footer Nav Builder

**Priority:** LOW  
**Effort:** 1.5 days  
**Complexity:** MEDIUM  
**Risk:** LOW

**Problem:** Header/footer blocks exist but navigation menus are not visually configurable.

**Solution:**

Special visual editor for `__header__` page:
- "Nav Items" special block renders a visual menu editor
- Add item (label + URL + icon + dropdown children)
- Drag to reorder
- Nested dropdown support (one level)
- Logo block: image picker + site name toggle + link

Header templates:
- Classic (logo left, nav right)
- Centered (logo center, nav below)
- Sidebar nav

**DB Changes:** Menu stored in theme `pages.__header__.blocks[nav_menu].props.items[]`  
**API Changes:** None  
**Frontend Changes:** NavBuilder component in header editor mode

---

### Phase TB3-14 — Demo Dataset System

**Priority:** LOW  
**Effort:** 1.5 days  
**Complexity:** LOW  
**Risk:** LOW

**See Part 5 for full specification.**

**DB Changes:**
- `ALTER TABLE posts ADD is_demo BOOLEAN`
- `ALTER TABLE products ADD is_demo BOOLEAN`
- `ALTER TABLE courses ADD is_demo BOOLEAN`
- `ALTER TABLE settings ADD is_demo_site BOOLEAN`

**API Changes:** `demo_mode` context flag in BlockRenderer  
**Frontend Changes:** Demo data badge in platform canvas editor toolbar  
**New Commands:** `php artisan theme:seed-demo`, `php artisan theme:clear-demo`

---

### Phase TB3-15 — Marketplace V2 (Versioning + Auto-Update)

**Priority:** LOW  
**Effort:** 2 days  
**Complexity:** MEDIUM  
**Risk:** LOW

**See Part 4 for full specification.**

**DB Changes:**
- `theme_versions` table (semantic versioning + changelogs)
- Add `current_version` column to `theme_licenses`

**API Changes:**
- `GET /api/theme-marketplace/updates` — returns available updates for installed themes
- `POST /api/theme-marketplace/{id}/update` — pull latest version to tenant

**Frontend Changes:** Update badge in customer-site marketplace page, version history modal in platform admin

---

## ROADMAP SUMMARY TABLE

| Phase | Feature | Complexity | Risk | Effort | DB | API | Frontend | Priority |
|---|---|---|---|---|---|---|---|---|
| TB3-01 | Unified Block Format | LOW | MEDIUM | 0.5d | None | Medium | Minor | CRITICAL |
| TB3-02 | Live Iframe Canvas | HIGH | HIGH | 4d | None | 1 new | Major | CRITICAL |
| TB3-03 | Drag from Library | MEDIUM | MEDIUM | 1.5d | None | None | Medium | HIGH |
| TB3-04 | Inline Editing | HIGH | MEDIUM | 2d | None | None | Medium | HIGH |
| TB3-05 | Property Panel Tabs | MEDIUM | LOW | 2d | None | None | Major | HIGH |
| TB3-06 | Typography System | MEDIUM | LOW | 1d | None | None | Medium | MEDIUM |
| TB3-07 | Media Manager | MEDIUM | LOW | 2d | 1 table | 3 routes | Major | MEDIUM |
| TB3-08 | Responsive Editor | LOW | LOW | 1d | None | None | Minor | MEDIUM |
| TB3-09 | Design Token Editor | LOW | LOW | 1d | None | None | Medium | MEDIUM |
| TB3-10 | Nested Container UI | HIGH | MEDIUM | 2d | None | None | Major | MEDIUM |
| TB3-11 | Revision History UI | LOW | LOW | 1d | None | 1 route | Minor | LOW |
| TB3-12 | Section Templates | MEDIUM | LOW | 1.5d | 1 table | 2 routes | Medium | LOW |
| TB3-13 | Header/Footer Nav | MEDIUM | LOW | 1.5d | None | None | Medium | LOW |
| TB3-14 | Demo Dataset | LOW | LOW | 1.5d | 4 cols | 1 flag | Minor | LOW |
| TB3-15 | Marketplace V2 | MEDIUM | LOW | 2d | 2 tables | 2 routes | Medium | LOW |

**Total estimated effort:** ~26 development days  
**Critical path (must ship in order):** TB3-01 → TB3-02 → TB3-03, TB3-04, TB3-05 (parallel) → rest

---

## RECOMMENDED SPRINT PLAN

### Sprint 1 — Foundation (Days 1–5)
- TB3-01: Unified block format
- TB3-02: Live iframe canvas (days 2–5)

### Sprint 2 — Core UX (Days 6–10)
- TB3-03: Drag from library to canvas
- TB3-04: Inline editing
- TB3-05: Property panel tabs (Content/Style/Advanced)

### Sprint 3 — Professional Controls (Days 11–15)
- TB3-06: Typography system
- TB3-07: Media manager
- TB3-08: Responsive editor
- TB3-09: Design token editor

### Sprint 4 — Advanced Features (Days 16–21)
- TB3-10: Nested container visual editor
- TB3-11: Revision history UI
- TB3-12: Section template library

### Sprint 5 — Ecosystem (Days 22–26)
- TB3-13: Header/footer nav builder
- TB3-14: Demo dataset system
- TB3-15: Marketplace V2 with versioning

---

## ARCHITECTURAL INVARIANTS FOR V3

1. **No React/Vue** — maintain Alpine.js + CDN pattern (proven in visual builder, avoids Vite production issues)
2. **Server-side rendering first** — `BlockRenderer` generates HTML; browser only handles interaction
3. **postMessage bridge** — outer editor ↔ iframe communication is the only cross-frame channel
4. **`block.props` is canonical** — after TB3-01, `block.data` is removed from all code paths
5. **Demo data is isolated** — `is_demo=true` rows never pollute tenant content queries
6. **site_id on every new table** — `media_library`, `theme_sections` must have `site_id`
7. **No platform App\ imports in customer-site** — `ThemeCanvasRenderer` continues to use config-based pattern
8. **Mobile-first CSS output** — BlockRenderer style output uses min-width breakpoints
9. **RTL-safe layout** — all flexbox/grid layout blocks must set `dir="rtl"` and `font-family: Vazirmatn`
10. **Marketplace theme editing is read-only** — installed marketplace theme creates a local fork; original is never modified

---

*End of THEME_BUILDER_V3_ROADMAP.md*
