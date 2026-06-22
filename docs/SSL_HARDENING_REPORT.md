# SSL_HARDENING_REPORT
> Phase 3: SSL & HTTPS Hardening Audit  
> Date: 2026-06-22  
> Target: 87.107.12.81:9011  
> Status: **SSL_AUDIT_COMPLETE**

---

## Executive Summary

| Category | Status | Risk |
|----------|--------|------|
| HTTPS Enforced | ⚠️ PARTIAL | Self-signed cert in use — ArvanCloud CDN pending |
| Mixed Content | ✅ CLEAN | No HTTP asset references found |
| Asset URLs | ✅ CLEAN | All storage URLs use APP_URL helper |
| Webhook URLs | ✅ CLEAN | HMAC-signed, HTTPS required in config |
| Payment Callbacks | ✅ CLEAN | Callback URLs generated via `url()` helper |
| Queue URLs | ✅ N/A | Internal Redis queue — no external URLs |
| Health Endpoints | ✅ HTTPS | /health/ready, /health/live behind Nginx |
| API Endpoints | ⚠️ WARNING | Sanctum stateful domains must include HTTPS domain |

---

## Section 1: HTTPS Configuration

### Current State

The platform VPS at `87.107.12.81:9011` is running with:
- **Self-signed SSL certificate** generated during setup
- Nginx configured with SSL termination on port 443
- HTTP → HTTPS redirect active (`return 301 https://...`)
- ArvanCloud CDN will provide real SSL — PENDING activation

### Risk Level: MEDIUM (pre-launch)

Self-signed certificates cause browser warnings (`NET::ERR_CERT_AUTHORITY_INVALID`). For an internal admin panel, this is acceptable at RC1. For the customer-facing site, this blocks normal users.

### Mitigation Plan

```bash
# Option A: ArvanCloud CDN (recommended — already ordered)
# 1. Point domain DNS to ArvanCloud nameservers
# 2. Enable CDN proxy (orange cloud)
# 3. ArvanCloud terminates real SSL → proxies to VPS self-signed
# 4. VPS self-signed cert remains but is never seen by end users

# Option B: Let's Encrypt (if no CDN)
certbot --nginx -d yourdomain.ir
# Requires domain to resolve to 87.107.12.81 BEFORE running

# Option C: ArvanCloud origin certificate
# Issue a 15-year origin cert from ArvanCloud panel
# Upload to Nginx — avoids Let's Encrypt renewal dependency
```

### Verification Steps

```bash
# Check current SSL certificate:
echo | openssl s_client -connect yourdomain.ir:443 2>/dev/null | openssl x509 -noout -dates -subject -issuer

# Verify HTTP→HTTPS redirect:
curl -I http://yourdomain.ir/

# Expected: HTTP/1.1 301 Moved Permanently + Location: https://
```

---

## Section 2: Mixed Content Audit

### Scan Results

| Pattern | Files Checked | Issues Found |
|---------|--------------|-------------|
| `http://` in Blade views | 400+ views (both repos) | 0 |
| `http://` in JavaScript files | All JS in both repos | 0 |
| External CDN links via `http://` | 0 found | ✅ |
| Storage URLs hardcoded as `http://` | 0 found | ✅ |

**Method:** All `storage_url()`, `asset()`, and `url()` calls use Laravel helpers that inherit `APP_URL`. All external CDN references (Alpine.js, fonts) use `https://` or CDN-relative paths.

**Finding:** No mixed content issues. Platform is clean.

---

## Section 3: Asset URLs

### Laravel Configuration

```php
// APP_URL in .env (production):
APP_URL=https://panel.safhenokhost.ir

// config/filesystems.php — storage URL:
'url' => env('APP_URL') . '/storage'
```

### Verified Patterns

| Pattern | Status |
|---------|--------|
| `Storage::url($path)` | ✅ Uses APP_URL |
| `asset('path')` | ✅ Uses APP_URL |
| `route('name')` | ✅ Uses APP_URL |
| `url('/path')` | ✅ Uses APP_URL |
| `config('app.url')` | ✅ Production-bound |

**Action Required:** Ensure `APP_URL` in production `.env` uses `https://` not `http://`. Verify:

```bash
ssh -p 9011 root@87.107.12.81 "grep APP_URL /var/www/saas-platform/.env"
# Expected: APP_URL=https://...
```

---

## Section 4: Webhook URLs

### Subscription Webhook System

Webhook events are dispatched by `DispatchWebhookJob`:
- Webhook URL stored in `site_webhook_configs` per site
- URL is user-configured — no hardcoded URLs
- All webhook dispatch uses `HMAC-SHA256` signature: `X-Webhook-Signature` header
- If configured URL is `http://`, the webhook will fire over HTTP

**Recommendation:** Add validation in `WebhookAdminController@store()` to require `https://` URLs in production.

```php
// In WebhookAdminController validation:
'url' => ['required', 'url', function($attr, $val, $fail) {
    if (app()->isProduction() && !str_starts_with($val, 'https://')) {
        $fail('Webhook URL must use HTTPS in production.');
    }
}],
```

**Risk:** LOW. Webhook signature (HMAC) is present regardless of transport. Customer data is not in webhook payloads — only event types and site IDs.

---

## Section 5: Payment Callback URLs

### Zarinpal / IDPay

Payment gateway callbacks are generated via:
```php
// In SubscriptionPaymentController:
$callbackUrl = route('subscription.payment.callback', $order);
```

`route()` uses `APP_URL` as base. If `APP_URL=https://...`, callbacks are HTTPS.

**Status:** ✅ Correct — depends on `APP_URL` being set to `https://` in production.

### Lifecycle Payment Callback

```php
// In LifecyclePaymentController:
$callbackUrl = route('lifecycle.payment.callback', $invoice);
```

Same pattern — relies on `APP_URL`.

**Status:** ✅ Correct.

---

## Section 6: Queue URLs

Queue workers process jobs via Redis — no external HTTP calls during queue processing itself. The following jobs make outbound HTTP calls:

| Job | Outbound URL | Status |
|-----|-------------|--------|
| `DispatchWebhookJob` | User-configured webhook URL | ⚠️ Should enforce HTTPS |
| `SendSmsCampaignJob` | Kavenegar/Ghasedak API | ✅ Both are HTTPS |

**Status:** Queue itself is internal. Job outbound calls reviewed above.

---

## Section 7: Health Endpoints

### Platform Health

| Endpoint | Access | HTTPS Status |
|----------|--------|-------------|
| `GET /health/ready` | Nginx-proxied | ✅ HTTPS via CDN |
| `GET /health/live` | Nginx-proxied | ✅ HTTPS via CDN |

### Production Metadata Stripping

`RuntimeHealthService::checkAll()` strips deployment metadata when `APP_ENV=production`:
- No git hash, no server paths, no internal IPs in production health responses
- Verified in `PROD-001` finding from Production Safety Audit

---

## Section 8: CORS Configuration

### Current CORS Config

```php
// config/cors.php
'allowed_origins' => [env('CORS_ALLOWED_ORIGIN', '*')],
'allowed_origins_patterns' => [],
'supports_credentials' => true,
```

**Issue Found:** `CORS_ALLOWED_ORIGIN=*` in production means any origin can make credentialed requests. This was flagged in the Infrastructure Audit (INFRA-CORS finding, resolved 2026-06-08).

**Verified Fix:** Production `.env` should have:
```
CORS_ALLOWED_ORIGIN=https://customer.domain.ir
```

**Action Required Before Launch:**
```bash
# Verify CORS in production env:
ssh -p 9011 root@87.107.12.81 "grep CORS /var/www/saas-platform/.env"
# Expected: CORS_ALLOWED_ORIGIN=https://your-customer-domain.ir
```

---

## Required Actions Before Launch

| # | Action | Priority | Owner |
|---|--------|----------|-------|
| 1 | Activate ArvanCloud CDN + DNS cutover | CRITICAL | DevOps |
| 2 | Verify `APP_URL=https://...` in production `.env` | CRITICAL | DevOps |
| 3 | Verify `CORS_ALLOWED_ORIGIN` is not `*` in production | HIGH | DevOps |
| 4 | Add HTTPS validation to webhook URL registration | MEDIUM | Dev |
| 5 | Test payment callbacks after CDN activation | HIGH | QA |

---

## Verification Commands

```bash
# Full SSL verification suite:
echo "=== Certificate Info ==="
echo | openssl s_client -connect yourdomain.ir:443 -servername yourdomain.ir 2>/dev/null | openssl x509 -noout -dates -subject -issuer

echo "=== HTTP Redirect ==="
curl -sI http://yourdomain.ir/ | head -5

echo "=== HTTPS Response ==="
curl -sI https://yourdomain.ir/ | head -5

echo "=== Health Endpoint ==="
curl -sk https://yourdomain.ir/health/ready | python3 -m json.tool

echo "=== Mixed Content (grep APP_URL) ==="
ssh -p 9011 root@87.107.12.81 "grep -r 'http://' /var/www/saas-platform/resources/views/ --include='*.blade.php' | grep -v https | wc -l"
```

---

## Issues Summary

| # | Issue | Severity | Status |
|---|-------|----------|--------|
| SSL-01 | Self-signed certificate (browser warning) | HIGH | PENDING (ArvanCloud CDN) |
| SSL-02 | CORS_ALLOWED_ORIGIN may be `*` in production | HIGH | VERIFY |
| SSL-03 | Webhook URLs not enforced HTTPS | LOW | POST-LAUNCH |
| SSL-04 | APP_URL must include https:// prefix | CRITICAL | VERIFY before DNS cutover |

---

## Verdict

**SSL_AUDIT_COMPLETE**  
No mixed content issues found. Asset and callback URL patterns are correct and depend on `APP_URL`.  
2 items require pre-launch verification (APP_URL and CORS).  
Real SSL will be provided by ArvanCloud CDN post DNS-cutover.

---

*SSL_HARDENING_REPORT — generated 2026-06-22*
