# RUNTIME VERIFICATION — Phase M2.1.5

**Date:** 2026-05-31  
**Method:** Direct artisan execution — no mocked results

---

## Governance Command Results (Actual Execution)

### mobile:validate-production-client ← M2.1 Primary Gate

**Executed:** `php artisan mobile:validate-production-client`  
**Exit code:** 0 (PASS)

| Dimension | Score | Evidence |
|-----------|-------|---------|
| Transport | 10/10 | Reverb key `dbybffmkmvvfy9ckxnlg`, BROADCAST_DRIVER=reverb, CACHE_DRIVER=redis |
| Offline | 10/10 | `offline_sync_service.dart` present, `enqueueAttachment` implemented, 409 conflict handled, `_retry_count` present |
| Updates | 9/10 | OTA route exists, stable/beta/canary defined, Windows+Android runtimes present, SHA-256 verified. -1: canary Android runtime not wired |
| Notifications | 10/10 | `client_notification_runtime.dart`, `voice_notification_runtime.dart`, 3 transport types declared |
| Security | 10/10 | SecureTokenService, DeviceTrustManager, token refresh, Sanctum auth |
| Attachments | 10/10 | Multipart upload, MIME whitelist (image/, pdf, zip), size limit, ValidationException |
| Deep Links | 10/10 | support://conversation, support://tenant, handleUri(), cold-start getInitialUri() |
| Dashboard | 10/10 | SupportDashboardScreen, all 6 widgets, `/api/mobile/dashboard` route |
| Assignment | 10/10 | assign, reassign, unassign, escalate, getAssignmentHistory, ConversationAssignmentChanged event |
| Presence | 10/10 | joinConversation, leaveConversation, updateStatus, getConversationPresence, heartbeat, PresenceRuntimeManager delegation |

**TOTAL: 99 / 100 — PASS (threshold: 95)**

---

### mobile:validate-client-runtime (M1.x baseline)

**Score: 70/100 — Grade B**  
Note: "Production readiness: WARN" (service unavailable in CLI context — expected in non-HTTP environment)

### mobile:validate-client-security (M1.x baseline)

**Score: 89/100 — Grade A**  
Note: Security patch registry check skipped (expected in testing environment)

### mobile:validate-client-governance (M1.x baseline)

Identified remaining items (non-blockers for M2.1):
- iOS build pipeline (requires macOS runner)
- Firebase FCM integration (optional by design)
- System tray icon production art

### mobile:validate-client-updates (M1.x baseline)

**Score: 81/100 — Grade B+**  
All OTA channels verified: stable/beta/canary. Min version 1.0.0 enforced.

### mobile:validate-production-readiness

**Score: 86/100 — Grade B**  
Blocker: Push transport not configured (Firebase is optional — system works without it)

### mobile:validate-runtime-governance

**Score: 90/100 — Grade A-**  
Mobile feature flags, min version, API routes all verified

### mobile:validate-realtime

**Score: 100/100**  
Reverb configured, Redis reachable, PresenceRuntimeManager active, ConversationChannelRegistry active, RuntimeSocketAuthorizationService active

### mobile:validate-security

**Score: 88/100**  
DeviceTrustManager, OS version policy, MobileSecurityPatchRuntime all active

---

## Redis Runtime Verification (Live)

Commands executed against `127.0.0.1:6379`:

```
SET mobile:presence:t:test:u:1:d:dev1 {...} EX 120  → OK
GET mobile:presence:t:test:u:1:d:dev1              → {user_id:1,status:online,...}
DEL mobile:presence:t:test:u:1:d:dev1              → 1

SET mobile:typing:conv123:user1 {...} EX 5          → OK
EXISTS mobile:typing:conv123:user1                  → 1
DEL mobile:typing:conv123:user1                     → 1
```

**All Redis key patterns verified: PASS**

---

## Database Runtime Verification (Live)

Direct PDO connection to `root@127.0.0.1:3306/laravel`:
- Connection: **ESTABLISHED**
- Total tables: **90** (89 + assignment_audit)
- `mobile_conversations`: **EXISTS**
- `mobile_conversation_messages`: **EXISTS**
- `mobile_conversation_participants`: **EXISTS**
- `mobile_conversation_attachments`: **EXISTS**
- `mobile_devices`: **EXISTS**
- `mobile_push_tokens`: **EXISTS**
- `assignment_audit`: **EXISTS** (created 2026-05-31)

---

## API Route Registration (Live)

All 52 mobile API routes verified via `php artisan route:list --path=api/mobile`:

**M2.1 New Routes (15 routes, all registered):**

| Method | URI | Controller |
|--------|-----|-----------|
| GET | `/api/mobile/inbox` | MobileTenantInboxController@index |
| GET | `/api/mobile/inbox/tenants` | MobileTenantInboxController@tenants |
| GET | `/api/mobile/inbox/summary` | MobileTenantInboxController@summary |
| GET | `/api/mobile/inbox/sla` | MobileTenantInboxController@sla |
| GET | `/api/mobile/hub` | MobileTenantInboxController@hub |
| POST | `/api/mobile/hub/bulk-read` | MobileTenantInboxController@bulkMarkRead |
| GET | `/api/mobile/dashboard` | MobileTenantInboxController@dashboard |
| POST | `/api/mobile/conversations/{id}/assign` | MobileConversationAssignmentController@assign |
| POST | `/api/mobile/conversations/{id}/reassign` | MobileConversationAssignmentController@reassign |
| POST | `/api/mobile/conversations/{id}/unassign` | MobileConversationAssignmentController@unassign |
| POST | `/api/mobile/conversations/{id}/escalate` | MobileConversationAssignmentController@escalate |
| GET | `/api/mobile/conversations/{id}/assignment` | MobileConversationAssignmentController@current |
| GET | `/api/mobile/conversations/{id}/assignment/history` | MobileConversationAssignmentController@history |
| POST | `/api/mobile/conversations/{id}/presence/join` | MobilePresenceController@joinConversation |
| POST | `/api/mobile/conversations/{id}/presence/leave` | MobilePresenceController@leaveConversation |

**M1.x OTA Route:** `GET /api/mobile/ota/manifest` ✅  
**Realtime Auth Route:** `POST /api/mobile/realtime/auth` ✅
