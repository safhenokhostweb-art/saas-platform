# Enterprise Theme Presets — Phase UI-THEME.1

8 professionally curated presets. All are `is_system=true` — non-deletable.
Seeded via `ThemeProfileSeeder`. Defined in `ThemePresetRegistry`.

---

## 1. Enterprise Blue ★ DEFAULT

**Slug:** `enterprise-blue` | **Preset Key:** `enterprise-blue`

Professional corporate blue — the platform default. Clean, trustworthy, accessible.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#3b82f6` (blue-500) |
| Accent | `#4f46e5` (indigo-600) |
| Sidebar | `#1e293b` → `#0f172a` (dark navy gradient) |
| Header | `#ffffff` (white) |
| Background | `#f1f5f9` (slate-100) |

---

## 2. Modern Indigo

**Slug:** `modern-indigo`

Deep indigo palette — bold, modern, high-contrast for tech-forward brands.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#6366f1` (indigo-500) |
| Accent | `#6366f1` (indigo-500) |
| Sidebar | `#312e81` → `#1e1b4b` (deep indigo) |
| Header | `#f8f7ff` |
| Background | `#f5f4ff` |

---

## 3. Emerald Pro

**Slug:** `emerald-pro`

Fresh emerald green — energetic, growth-focused, ideal for fintech and health.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#22c55e` (green-500) |
| Accent | `#059669` (emerald-600) |
| Sidebar | `#065f46` → `#022c22` (deep emerald) |
| Header | `#f0fdf4` |
| Background | `#f0fdf4` |

---

## 4. Royal Purple

**Slug:** `royal-purple`

Majestic purple — premium, creative, luxury SaaS.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#a855f7` (purple-500) |
| Accent | `#7c3aed` (violet-600) |
| Sidebar | `#3b0764` → `#1a0036` (deep purple) |
| Header | `#faf5ff` |
| Background | `#faf5ff` |

---

## 5. Midnight Dark

**Slug:** `midnight-dark`

Full dark mode — near-black background with soft light accents.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#60a5fa` (blue-400, inverted for dark) |
| Accent | `#818cf8` (indigo-400) |
| Sidebar | `#111827` → `#030712` (near-black) |
| Header | `#111827` |
| Background | `#030712` |

---

## 6. Graphite

**Slug:** `graphite`

Neutral gray palette — timeless, understated, versatile for any industry.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#6b7280` (gray-500) |
| Accent | `#374151` (gray-700) |
| Sidebar | `#1f2937` → `#111827` (dark graphite) |
| Header | `#f9fafb` |
| Background | `#f3f4f6` |

---

## 7. Ocean Teal

**Slug:** `ocean-teal`

Refreshing teal-cyan — calm, professional, healthcare and productivity.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#14b8a6` (teal-500) |
| Accent | `#0d9488` (teal-600) |
| Sidebar | `#134e4a` → `#042f2e` (deep teal) |
| Header | `#f0fdfa` |
| Background | `#f0fdfa` |

---

## 8. Executive Gold

**Slug:** `executive-gold`

Warm amber and gold — authoritative, premium, finance and executive dashboards.

| Token Group | Key Colors |
|-------------|-----------|
| Primary | `#f59e0b` (amber-500) |
| Accent | `#b45309` (amber-700) |
| Sidebar | `#292524` → `#1c1917` (dark warm) |
| Header | `#fffbeb` |
| Background | `#fffbeb` |

---

## Seeding

```bash
php artisan db:seed --class=ThemeProfileSeeder
```

Safe to re-run — uses `updateOrCreate` on `slug`. Enterprise Blue is set active.

## Reset to Default

```bash
# Via Artisan
php artisan tinker
>>> app(\App\Services\ThemeEngine\ThemeProfileManager::class)->resetToDefault();

# Or via Admin UI: Theme Manager → "بازنشانی به پیش‌فرض"
```
