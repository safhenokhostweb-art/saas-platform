# Theme Parity Report — Phase UI-THEME.1

## Summary

| Surface | Token Source | Status |
|---------|-------------|--------|
| saas-platform dashboard | ThemeProfile.toCssVars() → theme-engine-tokens partial | ✓ |
| saas-platform sidebar | `--platform-sidebar-*` emitted by toCssVars() + existing partial | ✓ |
| saas-platform header/navbar | `--ds-menu-*` emitted by toCssVars() | ✓ |
| saas-platform buttons | `--ui-indigo` / `--ds-button-primary-bg` | ✓ |
| saas-platform forms/inputs | `--ui-border`, `--ui-indigo` (focus ring) | ✓ |
| saas-platform badges | `--ui-success/warning/danger/info` | ✓ |
| saas-platform tables | `--ui-border`, `--ui-table-header-bg` (static from design-tokens.css) | ✓ |
| customer-site dashboard | TenantThemeResolver → theme-engine-tokens partial | ✓ |
| customer-site sidebar | `--admin-sidebar-*` emitted by TenantThemeResolver | ✓ |
| mobile client (Flutter) | `GET /api/mobile/theme/tokens` → semantic payload | ✓ |
| desktop client (Electron) | Same API endpoint | ✓ |

---

## Token Coverage Audit

### Fully Token-Driven (no hardcoded hex)
- Primary color palette (9 shades)
- Accent / Indigo colors
- Secondary / Purple colors
- All status colors (success, warning, danger, info)
- Sidebar gradient and text colors
- Header/navbar background and text
- Button primary background
- Focus ring color
- Link colors

### Partially Token-Driven (static in design-tokens.css, not dynamically overridable)
- Table header background (`--ui-table-header-bg`)
- Skeleton shimmer (`--ui-skeleton-bg`)
- Glass morphism (`--ui-glass-bg`)
- Scrollbar colors

### Remaining Hardcoded (known exceptions, documented)
- Sidebar menu group accent colors (`nth-child` rules in platform-admin-sidebar-theme.blade.php)
  — These use `--grp-color` CSS var which does reference the theme accent, but the 10
  per-group values are preset and not part of the ThemeProfile model.
  **Roadmap:** Phase UI-THEME.2 — add 10 `group_colors` array to ThemeProfile.

---

## Cross-Project Parity

Both `saas-platform` and `customer-site` import the identical `design-tokens.css` from
`packages/saas-platform/core-utils/resources/css/design-tokens.css`.

Both inject a `theme-engine-tokens` partial that fires the appropriate resolver:
- `saas-platform` → `ThemeTokenRenderer::renderForSite()`
- `customer-site` → `TenantThemeResolver::renderCssBlock()`

The `--ui-*` CSS var names are identical in both contexts, ensuring visual parity.

---

## Mobile Parity

Flutter client reads `GET /api/mobile/theme/tokens` which returns:
- `colors.primary` — maps to `--ui-primary-500`
- `colors.accent` — maps to `--ui-indigo`
- `sidebar.background` — maps to `--ui-sidebar-bg`
- `header.background` — maps to `--ds-menu-bg`

Flutter's `ClientDesignTokenBridge` should map these to `MaterialColor` and `ThemeData`.
Dark mode: `Midnight Dark` preset provides dark-appropriate colors with inverted palette.

---

## Desktop Parity

Electron / desktop client uses the same `/api/mobile/theme/tokens` endpoint.
No separate theme definition. Single source of truth.

---

## Parity Validation

```bash
php artisan ui:validate-theme-parity --json
```

Run in CI on every PR that touches `design-tokens.css`, `ThemePresetRegistry`,
or any Blade layout file.
