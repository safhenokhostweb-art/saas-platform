# PLATFORM SEEDER SPECIFICATION
> Date: 2026-06-26
> Status: FINAL — SPECIFICATION COMPLETE
> Resolves: BLOCKER-1 (PlatformSeeder does not exist)

---

## Problem Statement

`php artisan db:seed --class=PlatformSeeder` is referenced in the installer workflow and
disaster recovery playbook (Scenario D, Step 4), but `PlatformSeeder` does not exist.

`DatabaseSeeder` exists but seeds: ProfileFieldSeeder, ModuleSeeder, ProfessionalThemesSeeder,
ThemeProfileSeeder — none of which are required for a functioning platform install.

`InfrastructureProductionSeeder` exists but hardcodes production server details —
inappropriate for a generic fresh install.

A fresh install needs exactly 3 things to be operational: an admin user, at least one
subscription plan, and platform settings (name/timezone/locale). Without these, the platform
cannot function.

---

## Critical Constraint: users.name Column

**USERS-NAME-001 (RESOLVED 2026-06-25):** The `users` table has NO `name` column. `name` is a
virtual accessor (`getNameAttribute()`). Any seeder that writes `name` to the `users` table,
or uses `User::factory()->create(['name' => ...])`, will cause a DB error.

**Rule:** All user creation in seeders must use `first_name` + `last_name` columns directly,
or use `User::create(['first_name' => ..., 'last_name' => ...])`.

---

## PlatformSeeder — Master Seeder

```
database/seeders/PlatformSeeder.php
```

**Calls these sub-seeders in order:**

```php
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,       // Step 1: platform admin account
            DefaultPlanSeeder::class,     // Step 2: subscription plans
            PlatformSettingsSeeder::class, // Step 3: platform config defaults
        ]);
    }
}
```

**All 3 seeders are idempotent** — safe to run multiple times with `updateOrCreate()`.

---

## Required Seeders

### AdminUserSeeder

**File:** `database/seeders/AdminUserSeeder.php`

**Purpose:** Create the platform admin user that can log in to `/admin` immediately after install.

**Behavior:**
- Reads credentials from environment variables (installer sets these before seeding)
- Falls back to hardcoded safe defaults if env vars are absent (development only)
- Uses `updateOrCreate(['email' => ...])` — idempotent

**Required ENV vars (set by installer before seeding):**
```
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=changeme1234
ADMIN_FIRST_NAME=مدیر
ADMIN_LAST_NAME=پلتفرم
```

**Data written:**
```php
User::updateOrCreate(
    ['email' => env('ADMIN_EMAIL', 'admin@platform.local')],
    [
        'first_name'        => env('ADMIN_FIRST_NAME', 'مدیر'),
        'last_name'         => env('ADMIN_LAST_NAME', 'سیستم'),
        'email'             => env('ADMIN_EMAIL', 'admin@platform.local'),
        'password'          => Hash::make(env('ADMIN_PASSWORD', 'changeme1234')),
        'mobile'            => env('ADMIN_MOBILE', '09000000000'),
        'is_admin'          => true,
        'is_platform_admin' => true,
        'email_verified_at' => now(),
    ]
);
```

**Forbidden:** Do NOT write to `users.name` column. Do NOT use User::factory().

---

### DefaultPlanSeeder

**File:** `database/seeders/DefaultPlanSeeder.php`

**Purpose:** Create at least one subscription plan so sites can be created immediately after install.

**Behavior:** Uses `updateOrCreate(['slug' => ...])` — idempotent.

**Plans to seed:**

```
Plan 1: استارتر (Starter)
  slug: starter
  price: 0 (free / trial)
  duration_days: 30
  max_storage_mb: 512
  max_bandwidth_gb: 10
  max_pages: 10
  features: ['blog', 'forms', 'crm']
  is_default: true
  is_active: true

Plan 2: حرفه‌ای (Professional)
  slug: professional
  price: 0 (placeholder — set via admin panel)
  duration_days: 365
  max_storage_mb: 5120
  max_bandwidth_gb: 100
  max_pages: 100
  features: ['blog', 'forms', 'crm', 'education', 'sms', 'inventory']
  is_default: false
  is_active: true
```

**Why 2 plans:** The platform needs at least 1 active plan. Two plans (free trial + paid)
establish the pricing model without requiring admin panel configuration before first use.
Prices are set to 0 (placeholder) — admin must update via `/admin/subscriptions/plans` before going live.

---

### PlatformSettingsSeeder

**File:** `database/seeders/PlatformSettingsSeeder.php`

**Purpose:** Insert the minimum required rows into `settings` table for the platform to render correctly.

**Behavior:** Uses `Setting::updateOrCreate(['key' => ...])` — idempotent.

**Settings to seed:**

```
platform_name         → "SaaS Platform"  (shown in platform admin title bar)
platform_timezone     → "Asia/Tehran"    (default for all date calculations)
platform_locale       → "fa"             (Persian UI)
platform_currency     → "IRR"            (Iranian Rial)
platform_sms_provider → ""               (empty — admin must configure)
platform_version      → "1.0.0"          (displayed in admin footer)
```

**Note:** Site-level settings (site_id IS NOT NULL) are NOT seeded here. Only
`site_id = NULL` (platform-level) settings are written by this seeder.

---

## Optional Seeders (Safe to Skip)

These add value but are NOT required for a functional install.

| Seeder | Command | When to Run |
|--------|---------|-------------|
| `InfrastructureProductionSeeder` | `db:seed --class=InfrastructureProductionSeeder` | Only on the known production server (87.107.12.81) — hardcodes production IP |
| `ProfessionalThemesSeeder` | `db:seed --class=ProfessionalThemesSeeder` | After install, to populate theme library |
| `ThemeProfileSeeder` | `db:seed --class=ThemeProfileSeeder` | After install, for mobile theme profiles |
| `MarketplaceThemeSeedCommand` | `php artisan marketplace:seed` | After install, for 12 marketplace themes |
| `AcoolHvacSeedCommand` | `php artisan nexus:seed` / `acool:seed` | After install, for specific industry themes |

---

## Forbidden Seeders

These must NEVER be called from PlatformSeeder or run on a fresh production install.

| Seeder | Why Forbidden |
|--------|---------------|
| Any seeder writing real customer data | Violates blank-slate principle for fresh installs |
| Any seeder hardcoding specific site_id values | Breaks tenant isolation; assumes a specific DB state |
| Any seeder writing to `users.name` column | Column does not exist — will throw DB exception |
| `DatabaseSeeder` as PlatformSeeder replacement | DatabaseSeeder seeds themes + modules — not required at install; runs AFTER |
| Any seeder containing real API keys, passwords, or credentials | Security violation — credentials belong in .env only |

---

## Execution Order (Fresh Install)

```bash
php artisan migrate --force
php artisan db:seed --class=PlatformSeeder    ← required (3 seeders)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

**After first admin login (optional):**
```bash
php artisan db:seed --class=DatabaseSeeder          ← themes + modules
php artisan marketplace:seed                         ← 12 marketplace themes
```

---

## Validation

After running PlatformSeeder, the following must be true:

```sql
-- Admin user exists
SELECT COUNT(*) FROM users WHERE is_platform_admin = 1;  -- must be >= 1

-- At least one plan exists
SELECT COUNT(*) FROM subscription_plans WHERE is_active = 1;  -- must be >= 1

-- Platform settings exist
SELECT COUNT(*) FROM settings WHERE site_id IS NULL;  -- must be >= 6
```

---

*PLATFORM_SEEDER_SPECIFICATION — generated 2026-06-26 — FINAL*
