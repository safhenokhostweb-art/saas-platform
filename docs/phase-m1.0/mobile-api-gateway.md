# Mobile API Gateway — Phase M1.0

## Registration

Routes are loaded in `RouteServiceProvider::boot()` under `prefix('api/mobile')` with `middleware('api')`.

File: `packages/saas-platform/core-utils/Routes/mobile-api.php`

## Controllers (app/Http/Controllers/Api/Mobile/)

| Controller | Routes |
|---|---|
| `MobileAuthController` | POST auth/token, POST auth/refresh, POST auth/logout, POST auth/logout-all |
| `MobileRuntimeManifestController` | GET runtime/manifest |
| `MobileConversationController` | CRUD conversations + messages |
| `MobilePresenceController` | Heartbeat, presence read, typing start/stop |
| `MobileNotificationController` | List, mark-read, push token register, preferences |
| `MobileDeviceController` | Register, unregister, get/update device info |
| `MobileOtaController` | manifest, checkUpdate, getBundle |
| `MobileRealtimeController` | Connection config, channel auth, stats |

## Authentication

- **Auth**: Laravel Sanctum (`auth:sanctum` middleware)
- **Token creation**: `MobileAuthController::token()` runs credential check, device registration, session creation, then mints a Sanctum token
- **Rate limit**: 120 req/min (separate from main API limiter)

## HTTP Status Codes

| Code | Meaning |
|---|---|
| 200 | Success |
| 201 | Created |
| 401 | Unauthenticated |
| 403 | Forbidden (insufficient role/capability) |
| 404 | Not found |
| 422 | Validation error |
| 426 | Upgrade Required (hard_update enforcement) |
| 429 | Rate limit exceeded |

## Role Resolution

`MobileRuntimeManifestController::resolveRole()` maps platform users to mobile roles:

| User State | Mobile Role |
|---|---|
| `isSuperAdmin()` → true | `platform_admin` |
| `hasRole('tenant_admin')` → true | `tenant_admin` |
| `hasRole('support_agent')` → true | `support_agent` |
| Default | `end_user` |

## Headers

Mobile clients should send:

```http
X-App-Version: 1.2.3
X-Device-Id: {uuid}
Authorization: Bearer {sanctum_token}
Accept: application/json
```
