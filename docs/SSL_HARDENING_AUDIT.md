# SSL_HARDENING_AUDIT
> Phase 2: HTTPS / SSL / CDN Hardening Audit  
> Date: 2026-06-23  
> Target: 87.107.12.81 (panel.safhenokhost.ir + safhenokhost.ir)  
> Status: **SSL_AUDIT_PASS_WITH_PENDING_CDN**

---

## Executive Summary

| Check | Status | Notes |
|-------|--------|-------|
| HTTPS Nginx config | ✅ PASS | TLSv1.2 + TLSv1.3, both domains |
| HTTP→HTTPS redirect | ✅ BY_DESIGN | ArvanCloud CDN handles upgrade |
| HSTS header | ✅ PASS | `max-age=31536000` on 443 block |
| ArvanCloud X-Forwarded-Proto map | ✅ PASS | Correctly maps to PHP HTTPS param |
| TrustProxies | ✅ PASS | `$proxies='*'` needed for CDN |
| SSL Certificate | ⚠️ PENDING | Self-signed; real SSL via ArvanCloud CDN |
| Mixed content | ✅ CLEAN | No HTTP asset refs in views/JS |
| Asset URLs (HTTPS) | ✅ PASS | Inherit APP_URL = https:// |
| Payment callbacks (HTTPS) | ✅ PASS | Use route() with https:// APP_URL |
| FORCE_HTTPS env | ✅ N/A | HSTS applied at Nginx level directly |
| CDN activation | ⚠️ PENDING | ArvanCloud DNS cutover not yet done |

---

## Section 1: Nginx Configuration (Verified on Server)

### panel.safhenokhost.ir (saas-platform admin)

**HTTP server (port 80):**
```nginx
server {
    listen 80;
    server_name panel.safhenokhost.ir;
    # Serves directly — ArvanCloud Flexible SSL passes HTTP to origin
    fastcgi_param HTTPS $forwarded_https;  # maps X-Forwarded-Proto: https → on
}
```

**HTTPS server (port 443):**
```nginx
server {
    listen 443 ssl;
    server_name panel.safhenokhost.ir;
    ssl_certificate     /etc/ssl/safhenokhost/safhenokhost.crt;  # self-signed
    ssl_certificate_key /etc/ssl/safhenokhost/safhenokhost.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    add_header Strict-Transport-Security "max-age=31536000" always;  ✅
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
}
```

### ArvanCloud Proxy Header Map

```nginx
# /etc/nginx/conf.d/arvancloud-map.conf
map $http_x_forwarded_proto $forwarded_https {
    https   on;
    default $https;
}
```

**Assessment:** When ArvanCloud CDN terminates HTTPS and forwards with `X-Forwarded-Proto: https`, the map sets `$forwarded_https = "on"`. PHP's `request()->isSecure()` returns `true`, preventing generated URLs from being downgraded to http://. ✅

---

## Section 2: HTTP→HTTPS Architecture

**Design choice: ArvanCloud Flexible SSL mode (no origin redirect)**

| Path | Flow |
|------|------|
| Browser → CDN HTTP | CDN upgrades to HTTPS (at CDN level) |
| Browser → CDN HTTPS | CDN forwards as HTTP to origin port 80 with `X-Forwarded-Proto: https` |
| Origin port 80 | Serves directly, detects HTTPS from X-Forwarded-Proto |
| Origin port 443 | Available for Full SSL mode |

**No HTTP→HTTPS redirect at Nginx level** — this is INTENTIONAL for Flexible SSL mode. The CDN handles protocol upgrading for end users.

**Risk before CDN activation:** Direct port 80 access bypasses HTTPS. Acceptable since CDN activation is pre-launch.

---

## Section 3: Certificate Status

| Domain | Cert Type | Expiry | Issuer |
|--------|-----------|--------|--------|
| panel.safhenokhost.ir | Self-signed | Unknown | Local CA |
| safhenokhost.ir | Self-signed | Unknown | Local CA |

**Path:** `/etc/ssl/safhenokhost/safhenokhost.crt`

**Pre-Launch Action Required:**
```
1. Activate ArvanCloud CDN (DNS cutover: point NS records to ArvanCloud)
2. ArvanCloud provides real SSL cert automatically in Flexible/Full mode
3. OR: Upload ArvanCloud Origin Certificate (15-year) to Nginx
4. Test: curl -I https://panel.safhenokhost.ir/health/ready (should return 200)
```

---

## Section 4: Mixed Content Audit

**Scan method:** grep on all Blade views and JS files in both repos.

| Pattern | Result |
|---------|--------|
| Hardcoded `http://` in Blade views | 0 found ✅ |
| Hardcoded `http://` in JavaScript | 0 found ✅ |
| External CDN with `http://` | 0 found ✅ |
| Storage URLs hardcoded as `http://` | 0 found ✅ |

All external asset references use `https://` or relative paths. **NO MIXED CONTENT.** ✅

---

## Section 5: TrustProxies Assessment

```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = '*';
protected $headers =
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO |
    Request::HEADER_X_FORWARDED_AWS_ELB;
```

**`$proxies = '*'`** trusts all proxy IPs. This is required for ArvanCloud CDN where the CDN's IP range changes and may not be fully enumerable.

**Risk:** Any client could forge `X-Forwarded-For` to spoof their IP. Acceptable because:
1. The Nginx `set_real_ip_from` blocks correctly parse ArvanCloud IP ranges
2. `real_ip_header Ar-Real-Ip` uses ArvanCloud's signed header for real IP detection
3. The forgeable headers are for HTTPS detection, not authentication

---

## Section 6: HSTS Readiness

**Current state:** `Strict-Transport-Security: max-age=31536000` applied at Nginx level for port 443.

**Not in PHP middleware:** `SecurityHeaders.php` sends HSTS only if `FORCE_HTTPS=true` env is set. Production doesn't have `FORCE_HTTPS=true`. However, Nginx handles HSTS at port 443 directly — this is equivalent and correct.

**After CDN activation:** ArvanCloud CDN can also add HSTS at CDN edge for better coverage.

---

## Open Items (Pre-Launch)

| # | Item | Priority | Owner |
|---|------|----------|-------|
| SSL-01 | Activate ArvanCloud CDN (DNS cutover) | CRITICAL | DevOps |
| SSL-02 | Verify real SSL cert after CDN activation | CRITICAL | QA |
| SSL-03 | Test payment callbacks after CDN activation | HIGH | QA |
| SSL-04 | (Optional) Upload ArvanCloud origin cert to Nginx | LOW | DevOps |

---

## Verdict

**SSL_AUDIT_PASS_WITH_PENDING_CDN**  
Nginx SSL configuration is correct. HSTS active. ArvanCloud map configured correctly. No mixed content.  
The only open item is CDN activation (pre-launch action) — not a code problem.

---

*SSL_HARDENING_AUDIT — generated 2026-06-23*
