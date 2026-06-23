# APP_URL_AUDIT
> Phase 1: APP_URL & Generated URL Audit  
> Date: 2026-06-23  
> Auditor: Claude Code (automated)  
> Status: **APP_URL_AUDIT_COMPLETE — 3 FIXES APPLIED**

---

## Executive Summary

| Component | Before | After | Status |
|-----------|--------|-------|--------|
| saas-platform APP_URL | `https://panel.safhenokhost.ir` | — | ✅ PASS (was correct) |
| customer-site APP_URL | `https://safhenokhost.ir` | — | ✅ PASS (was correct) |
| customer-site PLATFORM_URL | `http://panel.safhenokhost.ir` | `https://panel.safhenokhost.ir` | ✅ FIXED |
| saas-platform VITE_APP_URL | `http://127.0.0.1:8000` | `https://panel.safhenokhost.ir` | ✅ FIXED |
| customer-site LOG_LEVEL | `debug` | `error` | ✅ FIXED |
| Payment callback URLs | `route()` → inherits APP_URL | — | ✅ PASS |
| Storage URLs | `env('APP_URL').'/storage'` | — | ✅ PASS |
| Asset URLs | `asset()` → inherits APP_URL | — | ✅ PASS |
| Signed URLs | None used | — | ✅ N/A |

---

## Section 1: saas-platform APP_URL

### Production .env (verified via SSH)

```
APP_URL=https://panel.safhenokhost.ir   ✅
APP_ENV=production                      ✅
APP_DEBUG=false                         ✅
VITE_APP_URL=https://panel.safhenokhost.ir  ✅ (FIXED from http://127.0.0.1:8000)
```

**Verdict:** PASS. All URL-dependent helpers (`route()`, `asset()`, `url()`, `Storage::url()`) use the HTTPS base URL.

---

## Section 2: customer-site APP_URL

### Production .env (verified via SSH)

```
APP_URL=https://safhenokhost.ir         ✅
APP_ENV=production                      ✅
APP_DEBUG=false                         ✅
PLATFORM_URL=https://panel.safhenokhost.ir  ✅ (FIXED from http://panel.safhenokhost.ir)
LOG_LEVEL=error                         ✅ (FIXED from debug)
QUEUE_CONNECTION=database               ✅
```

**Finding F-URL-01 (FIXED):** `PLATFORM_URL=http://panel.safhenokhost.ir` — customer-site was calling the platform API over unencrypted HTTP. When ArvanCloud CDN is active and `panel.safhenokhost.ir` resolves to CDN IPs, this would route the API call through the CDN over HTTP, adding unnecessary latency and sending data unencrypted.  
**Fix applied:** Changed to `https://panel.safhenokhost.ir` + ran `php artisan config:cache`.

---

## Section 3: Payment Callback URLs

### Saas-Platform (CheckoutController)

```php
// Priority 1: explicit env var
$callbackBase = config('services.payment_callback_url'); // PAYMENT_CALLBACK_URL env
$callbackUrl = $callbackBase
    ? rtrim($callbackBase, '/') . '/payment/callback?invoice=' . $invoice->id
    : route('checkout.callback', ['invoice' => $invoice->id]);
```

- `.env.example` has `PAYMENT_CALLBACK_URL=https://panel.safhenokhost.ir`
- If `PAYMENT_CALLBACK_URL` is not set in production, falls back to `route()` which uses `APP_URL=https://...`
- Both paths produce HTTPS callback URLs ✅

### Theme Marketplace & Bundle Payment

```php
$callbackUrl = route('theme-marketplace.payment-callback', ['order' => $order->id]);
$callbackUrl = route('theme-bundles.payment-callback', ['order' => $order->id]);
```

- Uses `route()` directly → inherits `APP_URL=https://panel.safhenokhost.ir` ✅

---

## Section 4: Storage & Asset URLs

### filesystems.php

```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',   // ← https://panel.safhenokhost.ir/storage
],

'uploads' => [
    'driver' => 'local',
    'root' => public_path('uploads'),
    'url' => env('APP_URL') . '/uploads', // ← https://panel.safhenokhost.ir/uploads
],
```

Both disk URL configurations inherit `APP_URL` ✅

---

## Section 5: Signed URLs

**No signed routes (`URL::signedRoute`, `URL::temporarySignedRoute`) found** in either repository. N/A.

---

## Fixes Applied (2026-06-23)

```bash
# Fix 1: customer-site PLATFORM_URL HTTP → HTTPS
sed -i "s|PLATFORM_URL=http://panel.safhenokhost.ir|PLATFORM_URL=https://panel.safhenokhost.ir|g" /var/www/customer-site/.env

# Fix 2: customer-site LOG_LEVEL debug → error
sed -i "s|^LOG_LEVEL=debug|LOG_LEVEL=error|g" /var/www/customer-site/.env

# Fix 3: saas-platform VITE_APP_URL → production HTTPS URL
sed -i "s|^VITE_APP_URL=http://127.0.0.1:8000|VITE_APP_URL=https://panel.safhenokhost.ir|g" /var/www/saas-platform/.env

# Config cache regenerated on server:
cd /var/www/customer-site && php artisan config:cache
```

---

## Verdict

**APP_URL_AUDIT: PASS**  
All APP_URL and generated URL patterns are correct in production.  
3 low-severity fixes applied (PLATFORM_URL HTTP→HTTPS, LOG_LEVEL debug→error, VITE_APP_URL local→prod).

---

*APP_URL_AUDIT — generated 2026-06-23*
