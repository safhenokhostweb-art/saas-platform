# TB3 SPRINT 10 — GAP ANALYSIS
> Date: 2026-06-20  
> Based on: TB3_SPRINT10_AUDIT.md  
> Input: All 18 features audited

---

## 1. MISSING FEATURES

### GAP-001 — MarketplaceThemeSeedCommand (CRITICAL)
**What is missing:** A single Artisan command `marketplace:seed` that seeds 20+ professional themes covering all 15 categories.  
**Why it matters:** This is the primary deliverable of Sprint 10. Without seeded themes, the marketplace is empty for all 12 uncovered categories.  
**Required output per category:**
- Unique color system (`global_styles`)
- Complete page set (home + 2–4 inner pages with full blocks)
- Header + footer blocks
- `category`, `license_type`, `is_marketplace=true`, `marketplace_price`, `settings.version`
- ThemeMarketplace meta record (description, requirements, version_notes)

**Categories with zero themes:** News, Educational, Medical, Legal, Real Estate, Restaurant, Portfolio, Agency, Marketplace, SaaS, Landing Page  
**Categories with themes but missing `category` field:** Corporate, Personal, Store (3 existing themes need `category` backfill)

---

### GAP-002 — Migration Not Applied (CRITICAL)
**What is missing:** `php artisan migrate` has not been run after creating `2026_06_20_000001_add_category_and_scoring_to_theme_marketplace.php`.  
**Blocked by this gap:** ALL Sprint 10 features — category, license_type, marketplace_badge, installs_count, all 5 score columns, annual_price, lifetime_price, is_new_release, is_bestseller, preview_url, category_tags.  
**Action required:** Run migration, then backfill `category` on all existing marketplace themes.

---

### GAP-003 — MarketplaceSeedScoresCommand (HIGH)
**What is missing:** An Artisan command `marketplace:score` that iterates all marketplace themes, calls `ThemeMarketplaceQualityService::scoreAndPersist()`, and outputs a report.  
**Why it matters:** The quality service exists but has no entry point. Scores are never calculated or stored.  
**Required:** `php artisan marketplace:score [--theme=slug]` — score one or all themes.

---

### GAP-004 — Scoring Dashboard Admin View (HIGH)
**What is missing:** `resources/views/admin/theme-marketplace/scoring.blade.php` — a view showing all themes with their 5 dimension scores, overall score, score badge (excellent/good/fair/needs-work), and quality issues list.  
**Required route:** `GET /admin/theme-marketplace/scoring` → `ThemeMarketplaceAdminController::scoring()`

---

### GAP-005 — Marketplace Search (HIGH)
**What is missing:**
- Platform admin: search field in `ThemeMarketplaceAdminController::index()` to filter by name/slug/category
- Customer-site: search bar in marketplace listing to filter by name/description
- API: `PlatformApiService::marketplaceThemes($filters)` must accept `search`, `category`, `featured`, `sort`

---

### GAP-006 — Marketplace Category Filter UI (HIGH)
**What is missing:**
- Platform admin marketplace index: category filter dropdown
- Customer-site marketplace index: category pill filters (horizontal scroll on mobile)
- `ThemeMarketplaceAdminController::index()` must accept `?category=` param
- Customer-site ThemeMarketplaceController must pass filters to platform API

---

### GAP-007 — Bestseller Badge + Auto-Promotion Logic (MEDIUM)
**What is missing:**
- `is_bestseller` badge display in admin and customer-site views
- Auto-promotion: when `sales_count >= 10`, mark `is_bestseller = true`
- Can be handled in `ThemeMarketplaceAdminController` or a scheduler command

---

### GAP-008 — New Release Badge + Auto-Flag Logic (MEDIUM)
**What is missing:**
- `is_new_release` badge display in admin and customer-site views
- Auto-flag: themes created within the last 30 days get `is_new_release = true`
- "New Arrivals" section in customer-site marketplace (similar to "Featured" section)

---

### GAP-009 — Annual / Lifetime License Pricing UI (MEDIUM)
**What is missing:**
- `annual_price` and `lifetime_price` fields are in the migration but never displayed
- No purchase flow choice: "Monthly" vs "Annual" vs "Lifetime"
- `ThemeMarketplaceAdminController::updateListing()` does not save `annual_price` / `lifetime_price`
- Customer-site purchase form doesn't offer license type selection

---

### GAP-010 — Theme Bundle System (LOW)
**What is missing:** Entirely. No `theme_bundles` table, no model, no purchase flow, no UI. The `LICENSE_BUNDLE` constant on Theme is a placeholder only.  
**Scope required:** theme_bundles table (id, name, theme_ids JSON, bundle_price), BundleController, purchase flow extension.

---

### GAP-011 — Theme Update Notification System (LOW)
**What is missing:** No mechanism to notify a tenant site that a new version of their installed theme is available.  
**Required:** Track `installed_version` per site per theme, compare with current `settings.version`, surface "update available" badge in customer-site.

---

### GAP-012 — Demo Data Install / Reset / Uninstall (LOW)
**What is missing:**
- `ThemeTemplateDemoDataService` resolves placeholder tokens in blocks — it does NOT create posts/products/courses
- No "Install Demo Content" button in marketplace install flow
- No "Reset to Factory Demo" button in customer-site theme settings
- No demo data versioning (track which version of demo data is installed)

---

### GAP-013 — Marketplace Preview Page (LOW)
**What is missing:**
- No dedicated full-screen theme preview page accessible before purchase
- No `preview_url` being set or used (column in migration)
- Customer-site marketplace cards have no "Preview" button
- Required: `GET /admin/theme-builder/{theme}/marketplace-preview` or public iframe-based preview

---

## 2. BROKEN FEATURES

### BRK-001 — `ThemeMarketplaceQualityService` Is Orphaned
**Problem:** The service is fully implemented (5 dimensions, score + validate + persist methods) but is connected to nothing. No controller imports it. No route calls it. No command runs it.  
**Impact:** Quality scoring is entirely non-functional despite code existing.  
**Fix:** Wire to `ThemeMarketplaceAdminController` + add `marketplace:score` command + add scoring view.

---

### BRK-002 — `installs_count` Is Never Incremented
**Problem:** `installs_count` field is defined in migration and in `Theme.$fillable`, but `ThemeMarketplaceController::install()` (customer-site) does NOT call any platform API endpoint to increment it. The platform has no endpoint for this.  
**Impact:** `installs_count` will always be 0.  
**Fix:** Add `POST /api/v1/themes/{id}/installed` platform API endpoint; call it from customer-site install action.

---

### BRK-003 — Existing Themes Missing `category` Field
**Problem:** NexusSeedCommand seeds 3 themes; ProfessionalThemesSeeder seeds 3; SalesSupportThemeSeeder seeds 1. None set `category`. After migration runs, all have `category = NULL`.  
**Impact:** Category filter will return 0 results for all categories until backfill runs.  
**Fix:** Backfill after migration: `nexus-pro-corporate → corporate`, `nexus-pro-personal → personal`, `nexus-pro-store → store`, `sales-support-pro → store`, `professional-corporate → corporate`, etc.

---

### BRK-004 — Two Disconnected Version Systems
**Problem:** `ThemeRevision` stores integer version snapshots (1, 2, 3…). `Theme.settings.version` stores semantic strings ("1.0.0", "1.1.0"…). These are never synchronized. A revision restore does not update semantic version. Semantic version is manually set in seeders.  
**Impact:** Version history and semantic versioning are inconsistent. Changelog in `settings.changelog` is never auto-updated.  
**Fix:** On `ThemeBuilderController::saveRevision()` and `publish()`, auto-increment semantic patch version and append to changelog.

---

### BRK-005 — Customer-site Marketplace Has No `category` or `score` in API Response
**Problem:** `PlatformApiService::marketplaceThemes()` fetches themes from platform API. The API controller (`ThemeMarketplaceApiController`) returns theme data, but neither `category` nor quality scores are included in the response payload.  
**Impact:** Even after migration runs, customer-site cannot filter by category or show scores.  
**Fix:** Add `category`, `license_type`, `design_score`, `marketplace_score` to the API response.

---

## 3. DUPLICATE FEATURES

### DUP-001 — Two Theme Seeding Approaches
`NexusSeedCommand` (Artisan command, `nexus:seed`) and `ProfessionalThemesSeeder` (Laravel Seeder, called via `themes:regenerate`) both seed corporate/personal/store-type themes. They produce overlapping themes with different slugs and different block structures.  
**Risk:** Confusion about which is the "canonical" marketplace theme set.  
**Recommendation:** Unify under `MarketplaceThemeSeedCommand`. Deprecate `NexusSeedCommand` and `ProfessionalThemesSeeder` or clearly document their separate purposes.

---

### DUP-002 — Two Preview Routes
`GET admin/theme-builder/{theme}/preview` (ThemeBuilderController::preview) and `GET admin/theme-builder/{theme}/canvas/{pageKey}/preview` (ThemeCanvasController::preview) both render previews. Different implementations, different contexts.  
**Risk:** Maintenance burden.  
**Recommendation:** Standardize on canvas-based preview; keep builder preview for non-canvas themes only.

---

### DUP-003 — `ThemeMarketplace.screenshots` vs `Theme.preview_image`
`Theme.preview_image` stores a single cover image URL. `ThemeMarketplace.screenshots` stores an array of screenshot URLs. These overlap in purpose.  
**Recommendation:** Keep `preview_image` as the hero/thumbnail. Use `screenshots` for the gallery carousel. Document this convention.

---

## 4. REFACTOR OPPORTUNITIES

### REF-001 — `ThemeMarketplaceAdminController::updateListing()` Is Growing
Currently handles: `is_marketplace`, `marketplace_price`, `marketplace_description`, `screenshots`, `requirements`, `version_notes`, `is_featured`. After Sprint 10 it must also handle: `annual_price`, `lifetime_price`, `license_type`, `category`, `is_new_release`, `is_bestseller`, `preview_url`.  
**Recommendation:** Extract into a `ThemeMarketplaceListingRequest` FormRequest + `ThemeMarketplaceListingService` to keep the controller thin.

---

### REF-002 — `PlatformApiService::marketplaceThemes()` Returns Raw Array
The response from the platform API is an untyped array. Adding more fields (category, scores, license type) makes it harder to maintain.  
**Recommendation:** Introduce a `MarketplaceThemeDto` value object for the API response shape.

---

### REF-003 — Category Constants on `Theme` Model Are Disconnected from `ThemeTemplate::CATEGORIES`
`Theme` has 15 marketplace `CATEGORY_*` constants. `ThemeTemplate` has its own `CATEGORIES` array (15 categories but different keys: hero, features, pricing, etc. — not matching marketplace taxonomy).  
**Recommendation:** Clearly document the distinction: `Theme::CATEGORY_*` = marketplace theme type; `ThemeTemplate::CATEGORIES` = block section type. They serve different purposes and should stay separate.

---

### REF-004 — `ThemeMarketplaceQualityService::COMMERCE_BLOCKS` Is Brittle
The COMMERCE_BLOCKS constant is a hardcoded array of block type strings. As new block types are added to `BlockRegistry`, this list will silently go stale.  
**Recommendation:** Either source this list from `BlockRegistry::all()` by category filter, or add a `is_commerce_block` flag to block definitions.

---

## 5. TECHNICAL DEBT

### TD-001 — Migration `2026_06_20_000001` Partially Committed
The migration was created as part of an interrupted Sprint 10 session. Theme model and ThemeMarketplace model were updated to reference new columns, but the migration has NOT been run. The codebase is in an inconsistent state where PHP code references columns that don't exist in the DB.  
**Severity: CRITICAL**  
**Action: Run migration on local + production BEFORE any Sprint 10 feature is tested.**

---

### TD-002 — `ThemeMarketplaceQualityService` Has No Tests
The service was created in Sprint 10 but no test file exists for it.  
**Severity: MEDIUM**  
**Action: Write `tests/Feature/ThemeMarketplaceQualityServiceTest.php` covering all 5 dimensions + edge cases (empty theme, no header, no pages, no marketplace meta).

---

### TD-003 — `NexusSeedCommand` Seeds Themes Without ThemeMarketplace Records
`nexus:seed` creates Theme rows with `is_marketplace=true` but does NOT create corresponding `ThemeMarketplace` rows (no description, no screenshots, no requirements). The quality scorer penalizes this (MKT-004: "رکورد مارکت‌پلیس وجود ندارد").  
**Severity: MEDIUM**  
**Action: Update NexusSeedCommand to also create ThemeMarketplace meta records.

---

### TD-004 — `installs_count` Is a Column With No Increment Logic
The column is defined but nothing ever writes to it (see BRK-002). Keeping an always-zero column creates false data.  
**Severity: LOW**  
**Action: Either implement the increment API endpoint (recommended) or remove the column until it's needed.

---

### TD-005 — Marketplace Scoring Is Not Scheduled
Even after wiring `ThemeMarketplaceQualityService`, scores will go stale as themes are updated. There is no scheduled re-scoring.  
**Severity: LOW**  
**Action: Add `marketplace:score` to Kernel.php weekly schedule after the command is created.

---

## RECOMMENDED IMPLEMENTATION ORDER

| Priority | Gap | Effort |
|----------|-----|--------|
| P0 | Run migration (TD-001) | 1 min |
| P0 | Backfill `category` on existing themes (BRK-003) | 30 min |
| P1 | `MarketplaceThemeSeedCommand` — 20+ themes all 15 categories (GAP-001) | 4 hrs |
| P1 | `marketplace:score` command + wire scoring service (GAP-003, BRK-001) | 1 hr |
| P1 | Scoring dashboard admin view (GAP-004) | 1 hr |
| P2 | Marketplace search + category filter UI (GAP-005, GAP-006) | 2 hrs |
| P2 | API response: add category + scores (BRK-005) | 1 hr |
| P2 | Bestseller + new release badges (GAP-007, GAP-008) | 1 hr |
| P3 | Annual / lifetime pricing UI (GAP-009) | 2 hrs |
| P3 | `installs_count` increment endpoint (BRK-002) | 1 hr |
| P4 | Theme update notification system (GAP-011) | 3 hrs |
| P4 | Demo data install/reset (GAP-012) | 3 hrs |
| P5 | Theme bundle system (GAP-010) | 4 hrs |
| P5 | Marketplace preview page (GAP-013) | 2 hrs |
