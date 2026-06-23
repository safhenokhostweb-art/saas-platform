# CORS_SECURITY_AUDIT
> Phase 3: CORS Security Audit  
> Date: 2026-06-23  
> Status: **CORS_AUDIT_PASS**

---

## Executive Summary

| Check | Status | Notes |
|-------|--------|-------|
| Wildcard origin in production | ✅ PASS | Empty array (no origins), not `*` |
| Allowed methods | ✅ ACCEPTABLE | Full REST methods for API |
| Allowed headers | ✅ PASS | Minimal required set |
| Credentials support | ✅ PASS | `supports_credentials: false` |
| CORS scope | ✅ PASS | Only `api/*` paths |
| Production CORS_ALLOWED_ORIGINS | ✅ SECURE | Not set → empty array (deny all) |
| Customer-site cross-origin calls | ✅ N/A | Server-to-server via GuzzleHttp |

---

## Section 1: CORS Configuration Review

### config/cors.php

```php
return [
    'paths' => ['api/*'],                     // ✅ Only API paths, not web routes

    'allowed_methods' => [
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'
    ],                                         // ✅ Full REST — acceptable for API

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))
    ),                                         // ✅ Empty string → empty array

    'allowed_origins_patterns' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS', '')))
    ),

    'allowed_headers' => [
        'Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'X-License-Key'
    ],                                         // ✅ Minimal required set

    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,          // ✅ No credential sharing
];
```

### Previous Issue (RESOLVED 2026-06-08)

The original CORS config had `'allowed_origins' => [env('CORS_ALLOWED_ORIGIN', '*')]` — a wildcard that allowed any origin. This was fixed in INFRA audit to the current `array_filter()` pattern.

---

## Section 2: Production CORS State

### Verified via SSH

```bash
grep CORS /var/www/saas-platform/.env
# Output: CORS not set in production
```

**Result:** `CORS_ALLOWED_ORIGINS` is not set in production `.env`.

**Effect:** `env('CORS_ALLOWED_ORIGINS', '')` returns `''`. After `explode`,`array_map`, and `array_filter`, the result is `[]` (empty array).

**Laravel behavior with empty `allowed_origins`:** The CORS middleware will not match any `Origin` header and will not add `Access-Control-Allow-Origin` response headers. This means **no cross-origin browser requests are allowed** to the platform API.

**Verdict:** SECURE by default. ✅

---

## Section 3: Cross-Origin API Call Analysis

### customer-site → saas-platform API calls

The customer-site communicates with the saas-platform via `PlatformApiService`:

```php
// GuzzleHttp server-to-server call
$response = $this->client->post('/api/v1/marketplace/...', [
    'headers' => ['Authorization' => 'Bearer ' . $this->apiToken],
    'json'    => $payload,
]);
```

**Server-to-server calls do not involve browsers and are NOT subject to CORS.**  
No `Origin` header is sent by GuzzleHttp. The CORS middleware is irrelevant for these calls. ✅

### Mobile app → saas-platform API calls

Mobile apps (Flutter) authenticate via Sanctum token. CORS does not apply to native mobile HTTP requests.

### Browser JS → saas-platform API calls

**Potential scenario:** If a canvas block or admin page makes a direct `fetch('/api/...')` call to the platform API from a browser context on a different domain, CORS would apply.

**Audit result:** No browser-side JavaScript in either repo makes cross-origin API calls to the saas-platform domain. All admin JS is same-origin.

---

## Section 4: API Endpoint Security Beyond CORS

| Endpoint Group | Auth | CORS Needed |
|----------------|------|-------------|
| `/api/v1/education/*` | Sanctum token | No |
| `/api/v1/studio/*` | Platform API token | No |
| `/api/theme-marketplace/*` | License key header | No |
| `/api/v1/subscription/*` | Sanctum token | No |
| `/api/v1/forms/*` | Sanctum token | No |

All API endpoints require authentication. CORS is a defense-in-depth layer, not the primary auth control.

---

## Section 5: Sanctum Stateful Domains

```bash
# Production .env:
SANCTUM_STATEFUL_DOMAINS=panel.safhenokhost.ir
```

Sanctum's stateful (cookie-based) authentication is scoped to `panel.safhenokhost.ir` only. SPA requests from other origins are not eligible for stateful auth. ✅

---

## Recommendation: Post-Launch Configuration

If future features require browser-side JavaScript on `safhenokhost.ir` to call `panel.safhenokhost.ir/api/*`:

```env
# Add to production .env:
CORS_ALLOWED_ORIGINS=https://safhenokhost.ir,https://www.safhenokhost.ir
CORS_ALLOWED_ORIGINS_PATTERNS=^https://.*\.safhenokhost\.ir$
```

For now, no action needed.

---

## Verdict

**CORS_AUDIT_PASS**  
No wildcard origins. `supports_credentials: false`. Scope limited to `api/*`. Empty allowed-origins in production is secure by default. Customer-site API calls are server-to-server (no CORS required).

---

*CORS_SECURITY_AUDIT — generated 2026-06-23*
