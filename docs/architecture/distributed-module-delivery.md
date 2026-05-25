# Distributed Module Delivery Architecture

**PHASE 6.98 — TASK 5**
**Status:** Foundation prepared — Satis not yet deployed

---

## Overview

The SaaS Platform uses a **dual-mode architecture** for delivering canonical modules
to customer servers. Local development continues to use symlink path repositories
(unchanged from previous phases). Production distribution uses a private Satis
composer repository once deployed.

```
saas-platform (source)
    │
    ├── Local Dev ──→ path repositories (symlink)
    │                      └── packages/saas-platform/modules/forms
    │
    └── Production ──→ private Satis repository
                            └── https://packages.example.com
                                    └── customer-site composer.json
```

---

## Mode 1 — Local Development (Current Active Mode)

### How it works

The root `composer.json` declares path repositories pointing to the local packages:

```json
"repositories": [
    { "type": "path", "url": "packages/saas-platform/core-utils" },
    { "type": "path", "url": "packages/saas-platform/modules/forms" },
    { "type": "path", "url": "packages/saas-platform/modules/tickets" }
]
```

Composer installs these as symlinks into `vendor/`, so changes to
`packages/saas-platform/modules/forms/src/` are immediately reflected
without re-running `composer install`.

### Rules

- **Never remove** path repositories from `composer.json`
- **Never force** production Satis mode locally
- All new modules must be added as path repositories before developing
- `module:list` will show source as `🔗 local` for all modules

### Adding a new module locally

```bash
# 1. Create module structure
mkdir -p packages/saas-platform/modules/{slug}/src/Providers

# 2. Add path repository to composer.json repositories array
# { "type": "path", "url": "packages/saas-platform/modules/{slug}" }

# 3. Add to composer.json require
composer require saas-platform/modules-{slug}:@dev

# 4. Validate
php artisan module:build {slug}
```

---

## Mode 2 — Production (Satis Private Repository)

> **NOT YET DEPLOYED.** The `satis.json` at the project root defines the
> architecture. Replace placeholder URLs before deploying.

### How it works

A private [Satis](https://github.com/composer/satis) server hosts built
zip archives of each module at a stable URL. Customer servers add the
Satis URL as a composer repository and receive updates through normal
`composer update` commands.

### Customer-site composer.json (production)

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.example.com"
        }
    ],
    "require": {
        "saas-platform/core-utils": "^1.0",
        "saas-platform/modules-forms": "^1.1",
        "saas-platform/modules-tickets": "^1.0"
    },
    "config": {
        "http-basic": {
            "packages.example.com": {
                "username": "{license-key}",
                "password": "{license-token}"
            }
        }
    }
}
```

### Satis server deployment (when ready)

```bash
# On the Satis server
php satis.phar build satis.json public/

# Serve public/ via nginx/Apache with HTTP Basic Auth
```

---

## Release Workflow

```
developer machine
│
├── 1. php artisan module:version forms --minor
│       Updates forms/module.json  1.1.0 → 1.2.0
│       Updates forms/composer.json
│
├── 2. php artisan module:build forms
│       Validates structure, authority rules, tenant segregation
│       Must pass before release
│
├── 3. php artisan module:release forms
│       Generates storage/app/modules/releases/forms.json
│       Creates git tag:  module-forms-v1.2.0
│
├── 4. git push origin module-forms-v1.2.0
│       Pushes tag to VCS
│
└── 5. (Satis server) php satis.phar build satis.json public/
        Rebuilds the package repository with new version
```

---

## Customer Update Workflow

```
customer server
│
├── 1. php artisan module:list
│       Shows current installed versions and available updates
│
├── 2. composer update saas-platform/modules-forms
│       Downloads new version from Satis
│       Preserves tenant overrides (composer does not touch published files)
│
├── 3. php artisan migrate
│       Runs new migrations safely
│
├── 4. php artisan view:clear && php artisan cache:clear
│       Clears compiled caches
│
└── 5. php artisan platform:validate-authority
        Confirms no regressions in authority architecture
```

---

## Rollback Workflow

```
customer server (on failure)
│
├── 1. composer require saas-platform/modules-forms:1.1.0
│       Downgrades to previous version via Satis
│
├── 2. php artisan migrate:rollback --step=N
│       Reverses migrations from the failed update
│       (all module migrations MUST have down() methods — see update-safety-rules.md)
│
├── 3. php artisan cache:clear && php artisan view:clear
│
└── 4. php artisan module:list
        Confirm rollback succeeded — version shows 1.1.0
```

---

## Tenant Override Preservation Rules

Published files (config, views) are **never overwritten** by composer.
Overrides published by a customer site persist across updates because:

1. `composer update` only replaces `vendor/` — published files live outside vendor
2. `php artisan vendor:publish` with `--force` is **never** run automatically
3. `ModuleUpdateOrchestrator::snapshotPublishedFiles()` records checksums before update
4. `ModuleUpdateOrchestrator::validatePostUpdate()` detects unexpected file changes

If a module update requires customers to re-publish changed configs/views,
this is communicated explicitly in the release manifest's `publish_tags` field.

---

## Module Discovery

`ModuleVersionRegistry` dynamically discovers modules by scanning:

| Mode | Path Pattern |
|------|-------------|
| Local dev | `packages/saas-platform/modules/*/module.json` |
| Production | `vendor/saas-platform/modules-*/module.json` |

Local paths take precedence. No module slugs are hardcoded.

---

## Forms Module — Canonical Reference

The `forms` module is the canonical reference implementation for:
- Package authority architecture
- Tenant/platform view segregation  
- Contract-based service layer
- Module manifest structure

All new modules must follow the Forms module pattern before release.
Run `php artisan module:build {slug}` to validate compliance.

---

## Related Files

| File | Purpose |
|------|---------|
| `satis.json` | Satis server configuration (foundation only) |
| `packages/saas-platform/core-utils/src/Support/ModuleVersionRegistry.php` | Dynamic module discovery |
| `packages/saas-platform/core-utils/src/Support/ReleaseManifest.php` | Release manifest generation |
| `packages/saas-platform/core-utils/src/Services/ModuleUpdateOrchestrator.php` | Update lifecycle coordination |
| `packages/saas-platform/core-utils/src/Services/PlatformCompatibilityService.php` | Pre-update compatibility checks |
| `database/migrations/2026_05_25_000001_create_module_installations_table.php` | Installation audit trail |
| `docs/architecture/update-safety-rules.md` | Mandatory safety rules for updates |
