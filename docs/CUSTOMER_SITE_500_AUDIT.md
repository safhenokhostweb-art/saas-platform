# Customer Site 500 Audit

**Date:** 2026-06-25  
**Status:** RESOLVED  

---

## Symptom

`safhenokhost.ir` returned HTTP 500 on every request.

---

## Root Cause

**PHP Fatal Error at Composer autoloader** — `Permission denied` on:
```
/var/www/customer-site/vendor/mtdowling/jmespath.php/src/JmesPath.php
```

6 vendor directories had wrong permissions (`drw-r--r--` / no execute bit):
- `vendor/aws/` — added 2026-06-25 13:17
- `vendor/mtdowling/` — added 2026-06-25 13:17
- `vendor/maennchen/`
- `vendor/markbaker/`
- `vendor/nikic/`
- `vendor/phpoffice/`

PHP-FPM (www-data) requires execute (`x`) on directories to traverse them. Without it, `require` for any file inside fails with `Permission denied`, crashing the entire application before Laravel bootstraps.

---

## Trigger

The `ARVAN_OFFSITE_BACKUP_OPERATIONAL` deploy (2026-06-25 ~13:13) installed new S3 packages (`league/flysystem-aws-s3-v3`, `aws/aws-sdk-php`, `mtdowling/jmespath.php`). The deploy ZIP extraction created these vendor directories with `644` instead of `755` permissions.

---

## Fix Applied

```bash
find /var/www/customer-site/vendor/ -type d ! -perm /111 -exec chmod 755 {} \;
cd /var/www/customer-site
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart customer-site-worker:customer-site-worker_00
```

---

## Verification

| Check | Result |
|-------|--------|
| `http://safhenokhost.ir/` | HTTP 200 ✅ |
| New Permission errors in nginx.log | 0 ✅ |
| customer-site-worker | RUNNING ✅ |
| Directories missing execute bit | 0 ✅ |

---

## Prevention (Deferred to Post-Launch)

Add to customer-site deploy script after vendor extraction:
```bash
find /var/www/customer-site/vendor/ -type d -exec chmod 755 {} \;
```

---

## Verdict: RESOLVED — Homepage fully operational.
