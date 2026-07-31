# CRM

- **Slug:** `crm`
- **Version:** 1.0.0

## Installation

```
php artisan module:validate crm
php artisan module:build crm
php artisan module:release crm 1.0.0
```

## Dependencies

See `module.json` → `dependencies.modules`. None declared by default.

## Upgrade Notes

Document breaking or notable changes here for each released version.

## Uninstall Strategy

See `module.json` → `uninstall`. Default is `soft` (entitlement disabled,
customer data kept). See docs/architecture/module-package-contract.md
بخش ۶ for the full soft/hard contract.
