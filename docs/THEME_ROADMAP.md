# THEME ROADMAP
> TB3 Theme Builder — Long-Term Marketplace Vision

---

## Completed

### Sprint 10 — Professional Marketplace Foundation (2026-06-20)
- 12 themes × 15 categories
- Quality scoring dashboard (5 dimensions)
- Search + category filter
- Bestseller / New Release badges
- Annual + Lifetime pricing
- Demo data manifest
- Per-theme preview page
- Install tracking

### Sprint 10.1 — Marketplace Population (2026-06-20)
- +10 new categories: lawyer, beauty, travel, automotive, industrial, construction, fashion, electronics, single_product + enterprise saas
- Total: **25 themes, 24 categories, 8 pages each (200 pages)**
- `--enrich` flag: adds missing pages to existing themes without delete
- Rich ThemeMarketplace metadata: screenshots, requirements, version_notes
- Placeholder thumbnails via placehold.co (branded per color)

### Sprint 11 — API Enrichment + Customer UI (2026-06-20)
- API exposes all Sprint 10 fields
- Customer marketplace: filter, quality chip, all badges
- Detail page, my-licenses with update indicator

### Sprint 12 — Theme Detail + My-Licenses (2026-06-20)
- Full detail page (hero, pricing sidebar, screenshots, CTA)
- my-licenses: thumbnail, is_outdated badge, version notes

---

## Near-Term Roadmap

### Sprint 10.2 — Real Theme Thumbnails
**Goal:** Replace placehold.co URLs with real screenshots

**Approach:**
1. Run each theme in canvas-theme layout on customer-site
2. Use headless Chrome (Puppeteer/Browsershot) to capture screenshots
3. Store in `storage/app/public/themes/{slug}/` 
4. Update `thumbnail` + `preview_image` + ThemeMarketplace `screenshots`

**Command:** `php artisan marketplace:screenshot {slug?}`

---

### Sprint 10.3 — Theme Preview Live URL
**Goal:** Every theme gets a real `/preview/{slug}` URL on customer-site

**Approach:**
1. Customer-site: `GET /preview/{themeSlug}` route
2. Load theme from platform API by slug
3. Render via ThemeCanvasRenderer with demo data
4. No auth required, public preview

---

### Sprint 10.4 — Real Demo Content
**Goal:** Replace placeholder block props with real demo text/images

**Approach:**
1. Add `demo_content` JSON per block type to `ThemeDemoDataService`
2. When rendering in preview mode, substitute demo data
3. Demo images: use Unsplash API or local Lorem Picsum

---

### Sprint 13 — Theme Rating System
**Goal:** Tenants can rate purchased themes (1-5 stars)

**Schema:**
- `theme_ratings` table: theme_id, site_id, rating, review, created_at

**Features:**
- POST /api/theme-marketplace/{id}/rate
- Average rating displayed in index + detail
- Rating filter in marketplace

---

### Sprint 14 — Theme Collections / Bundles
**Goal:** GAP-010 Theme Bundle System (deferred from Sprint 10)

**Schema:**
- `theme_bundles`: id, name, slug, description, bundle_price, theme_ids (JSON)
- `theme_bundle_orders`: id, bundle_id, site_id, payment_reference, status

**Features:**
- Admin CRUD for bundles
- Discounted bundle price vs sum of individual
- One purchase → all themes licensed
- Customer-site: bundle index + purchase flow

---

### Sprint 15 — AI-Assisted Theme Customization
**Goal:** Tenant types business description → AI adjusts colors + copy

**Approach:**
1. "پرسونالایز کن" button in canvas editor
2. POST /api/theme-builder/personalize
3. Claude API: input = business type + existing theme → adjusted color palette + hero text
4. Apply changes to active canvas theme

---

## Category Expansion Pipeline

| Category | Slug | Priority | Themes Planned |
|----------|------|----------|----------------|
| Healthcare SaaS | healthcare_saas | HIGH | health-crm |
| E-learning Platform | elearning | HIGH | course-platform |
| Gym / Fitness | fitness | MEDIUM | sport-gym |
| Dental Clinic | dental | MEDIUM | smile-clinic |
| Law Firm (Multi-partner) | law_firm | MEDIUM | lawfirm-pro |
| Accounting / Finance | finance | MEDIUM | hesab-pro |
| Photography Studio | photography | LOW | photo-studio |
| Wedding / Events | events | LOW | event-luxury |
| Non-Profit / NGO | nonprofit | LOW | khair-ngo |
| Pharmacy | pharmacy | LOW | daro-khaneh |

---

## Quality Score Targets

| Dimension | Current Avg | Target |
|-----------|-------------|--------|
| Design | 75 | 85 |
| Commerce | 70 | 80 |
| Performance | 80 | 90 |
| Completeness | 85 | 95 |
| Marketplace | 72 | 85 |

Completeness score improves automatically now that all themes have 8 pages.

---

## Architecture Notes

- All themes are `source = 'seeder'`, `site_id = null` — platform-level templates
- Tenant installs create a copy (`site_id = tenant`) via ThemeLicense + install flow
- Version tracking: `themes.version` INT + `theme_licenses.installed_theme_version` INT
- Update detection: `isOutdated()` = installed_version < theme.version
- Future: `theme_changelogs` table for per-version diff notes
