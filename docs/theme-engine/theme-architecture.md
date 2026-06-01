# Theme Engine Architecture — Phase UI-THEME.1

## Overview

The Enterprise Theme Engine provides a centralized, token-driven color system that
propagates theme changes to every surface (dashboard, sidebar, header, buttons, forms,
modals, charts, mobile client, desktop client) **without modifying component code**.

All color decisions live in **ThemeProfile** records. Components always read
CSS custom properties (`--ui-*`, `--ds-*`). No hex values in components.

---

## Layer Stack (load order)

```
[1] design-tokens.css           ← Static defaults, ~346 --ui-* vars
         ↓  @import at build time
[2] app.css (Tailwind v4)       ← Utility classes that reference --ui-* vars
         ↓  rendered in <head>
[3] theme-engine-tokens partial ← Runtime override block from active ThemeProfile
         ↓  :root { --ui-primary-500: #xxx; ... }
[4] platform-styles partial     ← --ds-* backward-compat vars (from DesignSystemService)
[5] platform-admin-sidebar-theme ← sidebar-specific overrides
```

The cascade: layer 3 overrides layer 1. No files are modified at runtime.

---

## ThemeProfile Model

`app/Models/ThemeProfile.php`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | PK |
| name | string | Display name |
| slug | string | Unique URL-safe key |
| description | text | Optional description |
| is_default | boolean | Enterprise Blue marker |
| is_system | boolean | Seeded presets (non-deletable) |
| is_active | boolean | Platform-wide active profile |
| preset_key | string | Matches ThemePresetRegistry key |
| colors | json | primary, secondary, accent, success, warning, danger, info |
| surfaces | json | bg, surface, surface_soft, surface_muted, card |
| typography | json | text_primary, text_secondary, text_muted, link, link_hover |
| borders | json | border, divider, strong |
| sidebar | json | bg, bg_bottom, text, muted, accent, border |
| header | json | bg, text, link_active |

---

## TenantThemeOverride Model

`app/Models/TenantThemeOverride.php`

Pivot: `site_id` → `theme_profile_id`
One row per site. Delete the row to revert to platform theme.
Enforced unique constraint on `site_id`.

---

## Service Architecture

```
ThemePresetRegistry       → Defines the 8 enterprise preset data arrays
       ↓
ThemeProfileManager       → activate, clone, delete, preview, cache management
       ↓
ThemeTokenRenderer        → renderForSite(), renderPlatform(), mobilePayload()
       ↓
DesignSystemService       → Upgraded: prefers ThemeProfile over PlatformSettings
```

**Resolution order** (ThemeProfileManager::resolve($siteId)):
1. `TenantThemeOverride::forSite($siteId)` → tenant's chosen profile
2. `ThemeProfile::activePlatform()` → platform default
3. `ThemeProfile::defaultPreset()` → Enterprise Blue
4. `null` → design-tokens.css static defaults apply

---

## CSS Variable Mapping

`ThemeProfile::toCssVars()` maps semantic color groups to CSS custom properties:

| Group | CSS Variables Emitted |
|-------|----------------------|
| colors.primary | `--ui-primary-50` … `--ui-primary-900` |
| colors.accent | `--ui-indigo`, `--ui-indigo-hover`, `--ui-indigo-light`, `--ui-indigo-ring` |
| colors.secondary | `--ui-purple`, `--ui-purple-hover`, `--ui-purple-light` |
| colors.success/warning/danger/info | `--ui-{status}`, `-hover`, `-bg`, `-text`, `-border`, `-light` |
| surfaces | `--ui-bg`, `--ui-surface`, `--ui-surface-soft`, `--ui-surface-muted` |
| sidebar | `--ui-sidebar-bg`, `--platform-sidebar-bg-top/bottom/text/muted/accent/border` |
| header | `--ds-menu-bg`, `--ds-menu-text`, `--ds-menu-link-active` |
| typography | `--ui-text-primary/secondary/muted`, `--ui-link-color/hover` |
| borders | `--ui-border`, `--ui-border-soft`, `--ui-border-strong` |
| compat | `--ds-primary`, `--ds-secondary`, `--ds-button-primary-bg`, `--ds-link-color` |

---

## Live Preview

Theme preview works without page reload:

1. Button triggers `GET /admin/theme-manager/{id}/preview-tokens` (JSON)
2. JS receives `tokens` map (CSS var → value)
3. JS calls `document.documentElement.style.setProperty(k, v)` for each token
4. Inline style overrides the cascade — no network request needed after initial load
5. "Cancel" restores original computed values
6. "Save/Activate" sends `POST /admin/theme-manager/{id}/activate`

---

## Tenant Isolation

- Platform theme: `ThemeProfile.is_active = true` where no `TenantThemeOverride` exists
- Tenant theme: `TenantThemeOverride` row pointing to a `ThemeProfile`
- Cache keys: `theme_engine:platform:active` and `theme_engine:tenant:{siteId}`
- Changing one tenant's theme never touches the platform cache key
- `ThemeProfileManager::activatePlatform()` runs in a DB transaction — atomically deactivates old profile

---

## Mobile & Desktop Sync

`GET /api/mobile/theme/tokens` (auth:sanctum, `X-Site-Id` header for tenant context)

Returns `ThemeTokenRenderer::mobilePayload()` — a Flutter-friendly JSON structure with
semantic keys (`colors.primary`, `sidebar.background`, etc.). No raw CSS var names.

`GET /api/mobile/theme/css-vars` — returns the raw `--ui-*` CSS var map for web-view rendering.

The Flutter `ClientDesignTokenBridge` should call `theme/tokens` on launch and cache
locally. On theme change (push event or polling), re-fetch and hot-reload.
