# Mobile Runtime Architecture — Phase M1.0

## Overview

Phase M1.0 establishes a **Self-Hosted Mobile Communication Runtime Foundation** for the SaaS platform. The platform is the single authority for all mobile app behavior — no external SaaS service governs the app runtime.

## Namespace Structure

```
packages/saas-platform/core-utils/src/MobileRuntime/
├── Governance/          Core runtime governance, feature flags, compatibility, manifest builder
├── Versioning/          Version policies and update channel management
├── Capabilities/        Per-tenant, per-role capability resolution
├── Security/            Security patch coordination
├── Realtime/            WebSocket coordination (Reverb), channel registry, typing
├── Presence/            Device presence tracking, online/away/busy/offline
├── Communication/       Conversations, messages, attachments (independent from tickets)
├── DeviceManagement/    Device registry, trust, sessions, push tokens
├── OTA/                 Over-The-Air governance, manifests, UI schema, theme
├── Notifications/       Push delivery, routing, priority, policy
└── Telemetry/           Crash reporting, metrics, delivery analytics, diagnostics
```

## Design Principles

1. **Platform-first authority** — all runtime decisions originate from the platform backend
2. **Firebase as transport only** — FCM carries Android push bytes; it has zero governance authority
3. **Self-hosted WebSockets** — Laravel Reverb is the realtime transport; no third-party WebSocket SaaS
4. **Multi-tenant isolation** — all Cache keys are prefixed with `tenantId`; no cross-tenant data leakage
5. **Module inheritance** — all future modules automatically become mobile-runtime compatible through `ModuleFrontendInheritanceService`
6. **OTA governance** — the app downloads its UI schema, navigation, feature flags, theme, and permissions from `/api/mobile/runtime/manifest` on every launch

## Service Registration

All services are registered as singletons in `CoreUtilsServiceProvider` under the `// PHASE M1.0` section. Laravel's container resolves constructor dependencies automatically.

## API Gateway

Mobile API routes are loaded in `RouteServiceProvider` under `prefix('api/mobile')` with `middleware('api')`. The route file is at:

```
packages/saas-platform/core-utils/Routes/mobile-api.php
```

Public routes (no auth): `POST /api/mobile/auth/token`, `POST /api/mobile/auth/refresh`

All other routes require `auth:sanctum`.

## Database Tables

| Table | Purpose |
|---|---|
| `mobile_devices` | Device registry with trust level and capabilities |
| `mobile_push_tokens` | FCM/APNs push tokens per device |
| `mobile_conversations` | Conversation entities (independent from tickets) |
| `mobile_conversation_participants` | Participant membership with roles |
| `mobile_conversation_messages` | Messages with soft-delete and read receipts |
| `mobile_conversation_attachments` | File attachments linked to messages |

Sessions are Cache-backed (no DB table), TTL 24h, max 10 per user.
