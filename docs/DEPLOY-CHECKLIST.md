# چک‌لیست Deploy — پلتفرم و سایت مشتری

این سند مراحل و تنظیمات لازم برای استقرار (deploy) را فهرست می‌کند.

---

## ۱. پلتفرم (saas-platform)

### ۱.۱ تنظیمات `.env`

| متغیر | توضیح | مثال |
|-------|--------|------|
| `APP_ENV` | `production` | production |
| `APP_DEBUG` | `false` | false |
| `APP_URL` | آدرس پلتفرم | https://panel.example.com |
| `DB_*` | دیتابیس production | — |
| `ZARINPAL_MERCHANT_ID` | مرچنت درگاه | — |
| `ZARINPAL_SANDBOX` | `false` برای production | false |
| `PAYMENT_CALLBACK_URL` | دامنهٔ callback بانک | https://example.com |
| `CORS_ORIGINS` | دامنهٔ سایت مالک (اختیاری) | https://example.com |
| `MAIL_*` | SMTP برای ارسال ایمیل | — |
| `HCAPTCHA_*` | کپچا | — |

### ۱.۲ دستورات قبل از deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

### ۱.۳ مجوزها

- `storage/` و `bootstrap/cache/` قابل نوشتن توسط وب‌سرور

---

## ۲. سایت مشتری (customer-site)

### ۲.۱ تنظیمات `.env`

| متغیر | توضیح | مثال |
|-------|--------|------|
| `APP_ENV` | `production` | production |
| `APP_DEBUG` | `false` | false |
| `APP_URL` | آدرس سایت | https://shop.example.com |
| `DB_*` | دیتابیس | — |
| `PLATFORM_URL` | آدرس پلتفرم | https://panel.example.com |
| `OWNER_SITE` | فقط برای سایت مالک | true یا false |

### ۲.۲ سایت مالک (سایت فروش شما)

اگر این نصب سایت مالک است (دامنهٔ اصلی، لینک بانک):

```
OWNER_SITE=true
PLATFORM_URL=https://panel.example.com
```

و در پلتفرم:

```
PAYMENT_CALLBACK_URL=https://your-main-domain.com
```

### ۲.۳ دستورات قبل از deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan storage:link
```

---

## ۳. وب‌سرور (Nginx/Apache)

### ۳.۱ SSL

- گواهی SSL برای هر دامنه
- ریدایرکت HTTP → HTTPS

### ۳.۲ Document Root

- پلتفرم: `public/`
- سایت مشتری: `public/`

### ۳.۳ نمونهٔ Nginx (Laravel)

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## ۴. چک‌لیست نهایی

| مورد | پلتفرم | سایت مشتری |
|------|--------|------------|
| `APP_DEBUG=false` | ☐ | ☐ |
| SSL فعال | ☐ | ☐ |
| مایگریشن اجرا شده | ☐ | ☐ |
| `storage` قابل نوشتن | ☐ | ☐ |
| ایمیل (MAIL_*) تنظیم شده | ☐ | — |
| درگاه پرداخت (ZARINPAL) | ☐ | — |
| `PAYMENT_CALLBACK_URL` (سایت مالک) | ☐ | — |
| `PLATFORM_URL` | — | ☐ |
| لایسنس فعال (سایت مشتری) | — | ☐ |

---

*آخرین بروزرسانی: ۱۴۰۴/۱۲/۰۵*
