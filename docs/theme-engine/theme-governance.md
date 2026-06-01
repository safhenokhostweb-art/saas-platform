# Theme Engine Governance — Phase UI-THEME.1

## Invariants

1. **No hardcoded hex values in components.** All colors are read from CSS custom
   properties (`var(--ui-*)`). The only place hex values are defined is in
   `ThemePresetRegistry` and user-created `ThemeProfile` records.

2. **design-tokens.css is never modified at runtime.** The engine injects an
   override block AFTER the static file via the `theme-engine-tokens` Blade partial.
   Cascading handles the override. No file writes needed.

3. **Exactly one active platform theme.** `ThemeProfileManager::activatePlatform()`
   runs in a DB transaction — atomically sets the new profile active and unsets all others.

4. **System presets are immutable in structure.** `is_system=true` profiles cannot be
   deleted. Color values can be updated (for admin customization), but structural fields
   (`preset_key`, `is_system`) are protected in `ThemeProfileManager::update()`.

5. **Tenant isolation enforced at cache level.** Platform cache key and tenant cache
   keys are separate. Changing one tenant's theme never invalidates another tenant's cache.

6. **Mobile payload never exposes raw CSS var names.** `ThemeTokenRenderer::mobilePayload()`
   returns semantic Flutter keys (`colors.primary`, not `--ui-primary-500`).

---

## Governance Commands

```bash
# Full governance check (10 dimensions, 0-100 score)
php artisan ui:validate-theme-governance

# JSON output for CI
php artisan ui:validate-theme-governance --json

# Strict mode (warnings treated as failures)
php artisan ui:validate-theme-governance --strict

# Fail CI if score < 95
php artisan ui:validate-theme-governance --fail-below=95
```

Checks:
1. `theme_profiles` + `tenant_theme_overrides` tables migrated
2. All 8 system presets in DB
3. Exactly one active platform profile
4. Enterprise Blue (`is_system=true`) present
5. No custom profiles with `is_system=true`
6. All profiles produce non-empty CSS var sets
7. No hardcoded hex in `platform-styles.blade.php`
8. `ThemeTokenRenderer` resolves without error
9. No orphaned `TenantThemeOverride` records
10. Mobile payload has all required fields

---

## Cross-Platform Parity Commands

```bash
# Parity check (10 dimensions)
php artisan ui:validate-theme-parity --json --fail-below=95
```

Checks:
1. All 8 presets have all 7 color groups
2. All presets have sidebar tokens (6 keys)
3. All presets have header tokens (3 keys)
4. All presets have typography tokens (4 keys)
5. All presets have border tokens
6. CSS var naming follows `--ui-*` / `--ds-*` governance
7. No `--tb-*` vars leaked into platform tokens
8. Mobile payload has no null required fields
9. All profiles emit ≥ 30 CSS vars
10. Both projects import `design-tokens.css`

---

## Admin Authorization

Theme management routes are under `prefix('admin')->middleware(['auth', 'admin'])`.
Only `is_admin` users can:
- Activate / deactivate themes
- Create / edit / delete custom themes
- Clone presets
- Reset to default

Tenants cannot directly modify platform themes. Tenant theme assignment is admin-only.

---

## Rollback

If a theme causes issues:
1. Admin UI: "بازنشانی به پیش‌فرض" button
2. Artisan: `php artisan tinker` → `app(ThemeProfileManager::class)->resetToDefault()`
3. Direct SQL: `UPDATE theme_profiles SET is_active=0; UPDATE theme_profiles SET is_active=1 WHERE preset_key='enterprise-blue';`

Cache is cleared automatically on reset.
