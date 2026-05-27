# Enterprise Search Architecture — Phase 9.4

## Overview

A unified search and command center that spans tickets, forms, tenants, and custom resources — keyboard-first, with telemetry, recent history, and AI-enhanced suggestions.

## Service Inventory

| Service | Responsibility |
|---|---|
| `GlobalSearchEngine` | Multi-driver federated search with relevance ranking |
| `SmartCommandPalette` | Command registry, fuzzy matching, recent history |
| `SearchTelemetryService` | Zero-result query tracking, usage analytics |

## Architecture

```
User types query → SmartCommandPalette (local) + GlobalSearchEngine (remote)
                           ↓
               Federated across registered drivers
                           ↓
               Results merged + ranked by relevance score
                           ↓
                   SearchTelemetryService records query
```

## Search Driver Registration

```php
$engine->registerDriver('tickets', function(string $query, string $tenantId, int $limit): array {
    // Return array of ['id','label','meta','url','relevance']
});
```

Drivers are tenant-isolated — `tenantId` is passed to every driver. Cross-tenant search is not possible.

## Core-UI Components

| Component | Trigger |
|---|---|
| `command-palette` | `Ctrl/Cmd + K` — inline panel in admin header |
| `spotlight-overlay` | Floating FAB button or `Ctrl/Cmd + K` — full-screen overlay |

Both components:
- Debounce input at 150–200ms
- Combine local command matching + remote `/api/search` API
- Support keyboard navigation (↑↓ + Enter)
- Record command usage via `/api/search/record-command`
- Show recent commands when query is empty

## Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| `Ctrl/Cmd + K` | Open command palette |
| `↑ / ↓` | Navigate results |
| `Enter` | Execute selected command |
| `ESC` | Close palette |
| `n t` | New ticket (registered command shortcut) |
| `n f` | New form (registered command shortcut) |
| `g d` | Go to dashboard |

## Telemetry

`SearchTelemetryService` tracks:
- Total query count per tenant
- Zero-result queries (stored + ranked by frequency)
- Daily query logs (7-day retention)

Zero-result query data surfaces in the operational analytics dashboard to identify content gaps.

## API Endpoints Required

```
GET  /api/search?q={query}&tenant_id={id}         → search results
GET  /api/search/suggest?q={query}&tenant_id={id} → autocomplete suggestions
POST /api/search/record-command                    → usage tracking
```
