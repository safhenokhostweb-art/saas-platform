# Deployment Target UI Architecture

**Phase:** PHASE-003D.5  
**Status:** Complete  
**Date:** 2026-06-28

---

## Overview

The Deployment Target UI is a driver-aware, fully dynamic admin interface for managing
remote deployment environments. It replaces the old SSH-only "Remote Server" concept with
a polymorphic "Deployment Target" that can represent any hosting type.

**Core principle:** The UI never hardcodes driver-specific logic. All behavior is driven
by the `driverMetaMap()` static method on the controller. Adding a new driver requires
only one change: adding an entry to that map.

---

## Architecture

### Controller: `CustomerRemoteServerAdminController`

The controller has two key design decisions:

1. **`driverMetaMap(): array`** — single source of truth for all driver UI metadata.
   Every view reads from this map. No driver-type conditionals exist outside it.

2. **Type-locked editing** — the `type` field is set at creation and never changed.
   The `update()` method reads `$server->type`, not `$request->input('type')`.

### View Structure

```
resources/views/admin/infrastructure/customer-deployment/servers/
  index.blade.php   — List all targets, driver type badges, capability chips
  create.blade.php  — Step 1: type selector grid → Step 2: dynamic form (Alpine.js)
  edit.blade.php    — Driver-aware form, type locked, credential update optional
```

### Data Flow

```
Controller::driverMetaMap()
    ↓ passed to all views as $driverMeta
    ↓
index.blade.php  → renders type badges and capability chips from $driverMeta
create.blade.php → serializes $driverMeta into Alpine.js capabilityMap
edit.blade.php   → reads $driverMeta[$server->type] for server's capabilities
```

---

## Create View: Two-Step Flow

### Step 1 — Type Selector Grid

Six cards rendered (5 active + 1 disabled):

| Card | Color | Status |
|------|-------|--------|
| VPS / سرور اختصاصی (SSH) | Indigo | Active |
| DirectAdmin | Blue | Coming soon |
| cPanel | Orange | Coming soon |
| SFTP خالص | Teal | Coming soon |
| Docker Host | Cyan | Coming soon |
| Kubernetes | Gray | Disabled |

Selecting a card sets `selectedType` in Alpine and reveals Step 2.

### Step 2 — Dynamic Form

The form fields shown depend on `selectedType`:

| Field | SSH | DirectAdmin | cPanel | SFTP | Docker |
|-------|-----|-------------|--------|------|--------|
| Name | ✓ | ✓ | ✓ | ✓ | ✓ |
| Host | ✓ | ✓ | ✓ | ✓ | ✓ |
| Port | ✓ (22) | ✓ (2222) | ✓ (2083) | ✓ (22) | ✓ (22) |
| Username | ✓ | ✓ | ✓ | ✓ | ✓ |
| SSH Auth (key/password) | ✓ | — | — | — | ✓ |
| API Token | — | ✓ | ✓ | — | — |
| SFTP Password | — | — | — | ✓ | — |
| Domain | — | ✓ | ✓ | ✓ | — |
| Path | — | ✓ | ✓ | ✓ | — |

### Step 3 — Capability Preview

After type selection, a capability grid auto-renders using `capabilityMap[selectedType]`
from the Alpine component. No hardcoded capability lists.

---

## Edit View: Driver-Aware Static Form

The edit view uses Blade `@if(in_array($server->type, [...]))` directives instead of
Alpine.js because the type is fixed — no dynamic switching needed at edit time.

Key behaviors:
- Credential fields are optional (empty = keep existing)
- Type badge displayed but not editable
- "Coming soon" warning for unimplemented drivers
- Test connection button only rendered for `$meta['implemented'] === true`

---

## Adding a New Driver (Zero UI Changes)

To add a new hosting driver to the UI, only one change is needed:

```php
// CustomerRemoteServerAdminController::driverMetaMap()
'newdriver' => [
    'label'        => 'New Driver',
    'label_short'  => 'NewDrv',
    'description'  => 'Description shown in create form',
    'color'        => 'purple',
    'implemented'  => false,
    'auth_types'   => ['api_token' => 'API Token'],
    'default_port' => 443,
    'fields'       => ['host', 'port', 'username', 'api_token'],
    'capabilities' => [
        'queue' => false, 'supervisor' => false, 'redis' => false,
        'ssl' => true, 'database' => true, 'storage' => false,
        'cron' => false, 'docker' => false, 'reboot' => false,
    ],
],
```

Then add the corresponding card in `create.blade.php` (copy any existing card, change
color + type value). The dynamic form fields, capability preview, and edit view all
respond automatically via the existing meta-driven logic.
