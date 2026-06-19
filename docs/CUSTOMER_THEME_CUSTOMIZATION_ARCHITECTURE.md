# Customer Theme Customization Architecture

> **ADR-009 — Theme Builder Access Boundary**
> Issued: 2026-06-20
> Status: ACTIVE — applies to all future TB3 sprints and beyond

---

## Architectural Principle

Theme **creation** is a Platform Admin capability.
Theme **customization** is a Customer capability.

These two concerns are permanently separated at the route and controller layer.
No future sprint may merge them.

---

## Mental Model: Elementor Analogy

A customer receives an installed marketplace theme the same way a WordPress user receives
an Elementor template: every block, section, page, header, footer, and style token is
fully editable. The customer owns the content; the platform owns the catalog.

```
Platform Admin                    Customer (Tenant Admin)
────────────────                  ────────────────────────
Creates themes           ───►     Installs themes
Runs AI Generator        ───►     Edits installed theme freely
Publishes to Marketplace ───►     Browses + purchases from Marketplace
Manages Theme Packs      ───►     Saves page templates locally
Administers licenses     ───►     Applies saved templates
```

---

## Section 1 — Allowed: Customer Full Customization Rights

All of the following are **permitted and encouraged** in the customer-site admin panel.

### 1.1 Canvas Block Editor

| Capability | Route | Controller |
|-----------|-------|------------|
| Open canvas editor | `GET /admin/theme-design/canvas/edit` | `ThemeDesignController::canvasEdit` |
| Save all blocks | `PUT /admin/theme-design/canvas/save-blocks` | `ThemeDesignController::canvasSaveBlocks` |
| Render block (live preview) | `POST /admin/theme-design/canvas/render-block` | `ThemeDesignController::canvasRenderBlockAjax` |
| Live iframe preview | `GET /admin/theme-design/canvas/live-preview` | `ThemeDesignController::canvasLivePreview` |

Block-level operations permitted:
- Edit any block's content (text, images, links, lists)
- Edit any block's style (background, padding, border, shadow, opacity, animation)
- Add blocks from the block library
- Remove blocks
- Reorder blocks (drag-and-drop)
- Duplicate blocks (clone with new ID)
- Undo / redo (25-step history)
- Per-block responsive overrides (D/T/M)

### 1.2 Page Management

| Capability | Route | Controller |
|-----------|-------|------------|
| Add page | `POST /admin/theme-design/canvas/pages` | `ThemeDesignController::canvasAddPage` |
| Delete page | `DELETE /admin/theme-design/canvas/pages/{key}` | `ThemeDesignController::canvasDeletePage` |
| Canvas publish | `POST /admin/theme-design/canvas/publish` | `ThemeDesignController::canvasPublish` |
| Discard draft | `POST /admin/theme-design/canvas/discard-draft` | `ThemeDesignController::canvasDiscardDraft` |

Customers may:
- Create new pages
- Clone pages (add-page with copied block data)
- Delete pages they created
- Publish changes live
- Discard unpublished drafts

### 1.3 Global Styles

| Capability | Route | Controller |
|-----------|-------|------------|
| Save global styles | `PUT /admin/theme-design/canvas/global-styles` | `ThemeDesignController::canvasSaveGlobalStyles` |
| Activate color preset | `POST /admin/theme-design/preset` | `ThemeDesignController::activatePreset` |
| Reset to preset | `POST /admin/theme-design/reset-preset` | `ThemeDesignController::resetPreset` |
| Update theme design | `PUT /admin/theme-design` | `ThemeDesignController::update` |

Customers may edit:
- Typography (font family, sizes, weights)
- Color palette (all 8 preset tokens)
- Spacing scale
- Border radius, shadow depth
- Responsive breakpoint settings

### 1.4 Header & Footer

Header and footer are standard canvas block groups (`header_blocks`, `footer_blocks`)
stored in `active_canvas_theme_data`. They are edited through the same canvas editor.
Customers have full add/edit/remove/reorder rights over header and footer blocks.

### 1.5 Navigation / Menus

Menu blocks (e.g. `block_nav`, `block_mobile_nav`) are canvas blocks.
Customers edit menu items through the block content editor.
There is no separate menu-administration feature — menus live inside the canvas.

### 1.6 Media

| Capability | Route | Controller |
|-----------|-------|------------|
| Browse media | `GET /admin/theme-design/canvas/media` | `ThemeDesignMediaController::index` |
| Upload image/video | `POST /admin/theme-design/canvas/media/upload` | `ThemeDesignMediaController::upload` |
| Delete media | `DELETE /admin/theme-design/canvas/media` | `ThemeDesignMediaController::destroy` |

Customers may replace any image, video, or media asset in any block.

### 1.7 Page Templates (Save & Reuse)

| Capability | Route | Controller |
|-----------|-------|------------|
| Browse templates | `GET /admin/theme-design/canvas/templates` | `ThemeDesignController::canvasTemplates` |
| Save page as template | `POST /admin/theme-design/canvas/save-as-template` | `ThemeDesignController::canvasSaveAsTemplate` |
| Insert template into page | `POST /admin/theme-design/canvas/insert-template` | `ThemeDesignController::canvasInsertTemplate` |

Saved templates are stored in `site_theme_templates` scoped by `site_id`.
Each tenant's template library is private to that tenant.

### 1.8 Theme Marketplace — Browse, Purchase, Install

| Capability | Route | Controller |
|-----------|-------|------------|
| Browse marketplace | `GET /admin/theme-marketplace` | `ThemeMarketplaceController::index` |
| My licenses | `GET /admin/theme-marketplace/my-licenses` | `ThemeMarketplaceController::myLicenses` |
| Purchase theme | `POST /admin/theme-marketplace/{id}/purchase` | `ThemeMarketplaceController::purchase` |
| Install purchased theme | `POST /admin/theme-marketplace/{id}/install` | `ThemeMarketplaceController::install` |

Customers may browse all published themes, pay for them, and install them.
Installation writes `active_canvas_theme_data` to the site's settings — immediately editable.

### 1.9 Export / Import Canvas Data

| Capability | Route |
|-----------|-------|
| Export current canvas | `GET /admin/theme-design/canvas/export` |
| Import canvas JSON | `POST /admin/theme-design/canvas/import` |

Customers may back up and restore their own customized canvas.

### 1.10 Theme Deactivation

| Capability | Route |
|-----------|-------|
| Deactivate canvas theme | `POST /admin/theme-design/canvas/deactivate` |

Deactivation returns the site to the default CSS-preset theme.

---

## Section 2 — Forbidden: Customer Restrictions

The following capabilities exist exclusively in the platform admin (`saas-platform`).
They must never appear in `customer-site` routes, controllers, views, or UI.

### 2.1 Theme Creation

| Forbidden Action | Platform Admin Route |
|-----------------|---------------------|
| Create new theme | `GET/POST /admin/theme-builder/create` |
| Edit theme (non-installed) | `GET/PUT /admin/theme-builder/{id}/edit` |
| Delete theme | `DELETE /admin/theme-builder/{id}` |
| Import theme | `POST /admin/theme-builder/import` |
| Clone theme | `POST /admin/theme-builder/{id}/clone` |
| Assign theme to site | `POST /admin/theme-builder/assign` |

### 2.2 AI Theme Generator

| Forbidden Action | Platform Admin Route |
|-----------------|---------------------|
| AI Generator form | `GET /admin/theme-builder/ai-generator` |
| AI Generator detect | `POST /admin/theme-builder/ai-generator/detect` |
| AI Generator generate | `POST /admin/theme-builder/ai-generator/{job}/generate` |
| AI Generator result | `GET /admin/theme-builder/ai-generator/{job}/result` |
| AI Generator export | `GET /admin/theme-builder/ai-generator/{job}/export` |
| AI Generator install | `GET /admin/theme-builder/ai-generator/{job}/install` |
| AI Analyzer form | `GET /admin/theme-builder/ai-analyze` |
| AI Analyzer submit | `POST /admin/theme-builder/ai-analyze` |

**Rule:** Customers must never see an "AI Theme Generator" button, link, or mention.

### 2.3 Theme Pack Management

| Forbidden Action | Platform Admin Route |
|-----------------|---------------------|
| List theme packs | `GET /admin/theme-packs` |
| Create theme pack | `GET/POST /admin/theme-packs/create` |
| Export pack JSON/ZIP | `GET /admin/theme-packs/{id}/export-*` |
| Import pack | `POST /admin/theme-packs/import-*` |
| Delete pack | `DELETE /admin/theme-packs/{id}` |

### 2.4 Theme Publishing & Marketplace Administration

| Forbidden Action | Platform Admin Route |
|-----------------|---------------------|
| Publish theme | `POST /admin/theme-builder/{id}/publish` |
| Unpublish theme | `POST /admin/theme-builder/{id}/unpublish` |
| Update marketplace listing | `PUT /admin/theme-marketplace/{id}/listing` |
| Toggle featured | `POST /admin/theme-marketplace/{id}/featured` |
| View all licenses | `GET /admin/theme-marketplace/licenses` |
| Revoke license | `DELETE /admin/theme-marketplace/licenses/{id}` |
| Grant license | `POST /admin/theme-marketplace/licenses/grant` |

### 2.5 Theme Manager (ThemeProfile)

| Forbidden Action | Platform Admin Route |
|-----------------|---------------------|
| Create/edit ThemeProfile | `GET/POST /admin/theme-manager/create` |
| Assign profile to sites | `POST /admin/theme-manager/{id}/assign-to-sites` |
| Global reset | `POST /admin/theme-manager/reset-default` |

---

## Section 3 — Boundary Enforcement

### 3.1 Repository Boundary

```
saas-platform/routes/web.php        ← All forbidden actions live here ONLY
customer-site/routes/web.php        ← All allowed customer actions live here ONLY
```

No controller from Section 2 (`ThemeBuilderController`, `ThemeAiController`,
`ThemeAiGeneratorController`, `ThemePackController`, `ThemeMarketplaceAdminController`)
must ever be registered in `customer-site/routes/web.php`.

### 3.2 Middleware Verification (Current State)

All customer-site admin routes are protected by:
```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(...)
```

All platform admin routes are protected by:
```php
Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(...)
```

The `platform.admin` middleware ensures platform admin routes cannot be reached
by a tenant admin session.

### 3.3 No "Create Theme" Button in Customer UI

The customer's theme panel (`admin.theme-design.*` views) must contain:
- Marketplace install button → allowed
- Canvas editor access → allowed
- No "Create New Theme" button → forbidden
- No "AI Generator" link → forbidden
- No "Theme Packs" section → forbidden

---

## Section 4 — Data Flow: Install → Customize

```
Platform Admin creates theme (saas-platform)
    │
    ▼
Published to Marketplace (themes table, marketplace_listed = true)
    │
    ▼
Customer browses Marketplace (customer-site ThemeMarketplaceController)
    │
    ▼
Customer purchases license (platform handles payment)
    │
    ▼
Customer installs theme (ThemeMarketplaceController::install)
    │  Writes: active_canvas_theme_data (JSON) → settings table
    │
    ▼
Customer opens Canvas Editor (ThemeDesignController::canvasEdit)
    │  Reads: active_canvas_theme_data
    │  Renders: BlockRegistry → all blocks editable
    │
    ▼
Customer edits freely:
  - Block content (text/images/links)
  - Block styles (colors/padding/border/shadow)
  - Header / footer blocks
  - Pages (add/delete/clone)
  - Global typography + color tokens
  - Responsive overrides
  - Page templates (save & reuse)
    │
    ▼
Customer publishes (POST /admin/theme-design/canvas/publish)
    │
    ▼
Live site renders from active_canvas_theme_data (ThemeCanvasPageController)
```

---

## Section 5 — Future Sprint Rules

Any future TB3 sprint (Sprint 9+) that adds a new feature must classify it
before implementation:

### Check 1 — Creation or Customization?

| Is this feature about... | Classification | Where to implement |
|--------------------------|---------------|-------------------|
| Creating a new theme from scratch | Creation | saas-platform ONLY |
| Generating a theme with AI | Creation | saas-platform ONLY |
| Packaging themes into packs | Creation | saas-platform ONLY |
| Publishing themes to marketplace | Creation | saas-platform ONLY |
| Editing blocks on an installed theme | Customization | customer-site |
| Changing colors/fonts on an installed theme | Customization | customer-site |
| Adding a new page to an installed theme | Customization | customer-site |
| Saving a page layout as template | Customization | customer-site |

### Check 2 — UI Placement

If a new button or section is added to the customer's admin panel:
- Does it reference "AI Generation"? → Remove, belongs in platform admin.
- Does it reference "Create Theme" or "New Theme"? → Remove.
- Does it reference "Publish to Marketplace"? → Remove.
- Does it reference "Theme Pack"? → Remove.

### Check 3 — Controller Placement

If a new controller is created for theme features:
- Platform admin controllers: `saas-platform/app/Http/Controllers/Admin/Theme*`
- Customer admin controllers: `customer-site/app/Http/Controllers/Admin/ThemeDesign*`
  and `customer-site/app/Http/Controllers/Admin/ThemeMarketplace*`

No controller that calls `ThemeAiAnalyzerService`, `ThemeAiGeneratorService`,
`ThemePackService`, or `ThemeMarketplaceAdminService` belongs in customer-site.

---

## Section 6 — Current Compliance Status (2026-06-20)

Audit of the current codebase against this architecture:

| Rule | Status | Evidence |
|------|--------|---------|
| AI Generator not in customer-site routes | ✅ PASS | `ThemeAiController` and `ThemeAiGeneratorController` only in `saas-platform/routes/web.php` |
| Theme creation not in customer-site routes | ✅ PASS | `ThemeBuilderController::create/store` only in `saas-platform/routes/web.php` |
| Theme Pack management not in customer-site | ✅ PASS | `ThemePackController` only in `saas-platform/routes/web.php` |
| Marketplace administration not in customer-site | ✅ PASS | `ThemeMarketplaceAdminController` (listing/featured/licenses) only in `saas-platform` |
| Customer has canvas editor access | ✅ PASS | Full `theme-design/canvas/edit` route with all sub-routes in customer-site |
| Customer can save page templates | ✅ PASS | `save-as-template` / `insert-template` routes in customer-site |
| Customer can buy and install themes | ✅ PASS | `ThemeMarketplaceController` (browse/purchase/install) in customer-site |
| Customer can manage pages | ✅ PASS | `canvas/pages` add/delete routes in customer-site |
| Customer can manage media | ✅ PASS | `ThemeDesignMediaController` upload/delete in customer-site |
| Customer can edit global styles | ✅ PASS | `canvas/global-styles` route in customer-site |

**Overall compliance: 10/10 — PASS**

The current codebase already respects this boundary.
This document formalizes it as a permanent architectural decision.

---

## Section 7 — Related Documents

- `docs/theme-engine/theme-architecture.md` — Token system and ThemeProfile
- `docs/theme-engine/theme-governance.md` — Design token governance
- `docs/TB3_SPRINT6_CERTIFICATION.md` — Template Library sprint cert
- `docs/TB3_SPRINT8_CERTIFICATION.md` — AI Generator sprint cert
- `docs/project-memory/DECISIONS.md` — ADR-009 entry

---

> This document supersedes any implicit assumption that customers and platform admins
> share the same theme creation surface.
> All theme CREATION flows through the platform. All theme CUSTOMIZATION flows through the customer.
