# MARKETPLACE THEME SEED PLAN
> TB3 Sprint 10.1 — 2026-06-20

## Overview

The `marketplace:seed` command populates the marketplace with **25 production-ready themes** covering **24 distinct business categories**. Each theme ships with **8 complete pages** and full marketplace metadata.

---

## Command Usage

```bash
# Add new themes only (safe, idempotent)
php artisan marketplace:seed

# Wipe and re-seed all platform themes
php artisan marketplace:seed --fresh

# Update missing pages on existing themes (non-destructive)
php artisan marketplace:seed --enrich

# Score all themes after seeding
php artisan marketplace:score
```

---

## 25 Themes — Category Coverage

| # | Slug | Name | Category | Price | Modules |
|---|------|------|----------|-------|---------|
| 1 | nexus-pro-corporate | Nexus Pro Corporate | corporate | رایگان | blog, forms |
| 2 | nexus-pro-personal | Nexus Pro Personal | personal | رایگان | blog, forms |
| 3 | nexus-pro-store | Nexus Pro Store | store | رایگان | shop, blog |
| 4 | khabar-online | خبر آنلاین | news | رایگان | blog, forms |
| 5 | akademi-danesh | اکادمی دانش | educational | رایگان | courses, blog |
| 6 | clinic-salamat | کلینیک سلامت | medical | رایگان | forms, tickets |
| 7 | daftar-hoqooqi | دفتر حقوقی | legal | رایگان | forms, tickets |
| 8 | azhans-maskan | آژانس مسکن | real_estate | رایگان | blog, forms |
| 9 | restaurant-lux | رستوران لوکس | restaurant | رایگان | forms, shop |
| 10 | portfolio-khalaq | پورتفولیو خلاق | portfolio | رایگان | blog, forms |
| 11 | azhans-digital | آژانس دیجیتال | agency | رایگان | blog, forms |
| 12 | bazar-online | بازار آنلاین | marketplace | رایگان | shop, subscription |
| 13 | narmafzar-abri | نرم‌افزار ابری | saas | رایگان | subscription, blog |
| 14 | landing-herfei | لندینگ پیج حرفه‌ای | landing | رایگان | forms, subscription |
| 15 | majale-online | مجله آنلاین | blog | رایگان | blog, forms |
| 16 | **vakil-pro** | **وکیل پرو** | **lawyer** | 290,000 | forms, tickets |
| 17 | **salon-ziba** | **سالن زیبا** | **beauty** | 190,000 | forms, shop |
| 18 | **safar-lux** | **سفر لوکس** | **travel** | 250,000 | shop, blog |
| 19 | **khodro-pro** | **خودرو پرو** | **automotive** | 350,000 | shop, forms |
| 20 | **sanat-molodi** | **صنعت مولودی** | **industrial** | 390,000 | shop, blog |
| 21 | **sakhteman-iran** | **ساختمان ایران** | **construction** | 350,000 | blog, forms |
| 22 | **mode-fa** | **مد ایرانی** | **fashion** | 290,000 | shop, subscription |
| 23 | **digi-shop** | **دیجی شاپ** | **electronics** | 390,000 | shop, subscription |
| 24 | **mahsool-vije** | **محصول ویژه** | **single_product** | 190,000 | shop, subscription |
| 25 | **enterprise-cloud** | **Enterprise Cloud** | saas (enterprise) | 590,000 | subscription, tickets |

Bold rows = new in Sprint 10.1.

---

## Page Structure (8 pages per theme)

| Page Key | Slug | Title | Core Blocks |
|----------|------|-------|-------------|
| `home` | `/` | صفحه اصلی | hero/hero_split/hero_gradient + category-specific blocks |
| `about` | `/about` | درباره ما | hero + heading + text + stats_row + team + testimonials_slider + cta |
| `contact` | `/contact` | تماس با ما | hero + contact_split + faq + cta |
| `blog` | `/blog` | وبلاگ | hero + module:blog_posts + email_capture |
| `archive` | `/archive` | آرشیو | breadcrumb + heading + module:blog_posts + module:blog_categories |
| `single` | `/single` | مقاله | breadcrumb + heading + text + divider + module:blog_posts |
| `error` | `/404` | صفحه یافت نشد | hero (404) + module:blog_posts |
| `search` | `/search` | جستجو | hero + search_bar + module:blog_posts + module:shop_product_grid |

---

## ThemeMarketplace Metadata (per theme)

| Field | Source | Description |
|-------|--------|-------------|
| `annual_price` | seed | قیمت سالانه به تومان |
| `lifetime_price` | seed | قیمت یکبار پرداخت |
| `is_new_release` | seed | true برای تمام تم‌های Sprint 10.1 |
| `is_featured` | seed | enterprise-cloud = true |
| `category_tags` | seed | آرایه تگ‌های دسته‌بندی فارسی |
| `screenshots` | seed | 3 URL پلیس‌هولدر با رنگ اصلی تم |
| `requirements` | seed | وابستگی‌های ماژولی |
| `version_notes` | seed | توضیح نسخه اول |
| `supported_modules_json` | seed | JSON رشته ماژول‌ها |
| `has_demo_data` | seed | همیشه true |

---

## New Category Constants (Theme.php)

```php
CATEGORY_LAWYER        = 'lawyer'         // وکیل
CATEGORY_BEAUTY        = 'beauty'         // زیبایی
CATEGORY_TRAVEL        = 'travel'         // گردشگری
CATEGORY_AUTOMOTIVE    = 'automotive'     // خودرو
CATEGORY_INDUSTRIAL    = 'industrial'     // صنعتی
CATEGORY_CONSTRUCTION  = 'construction'   // ساختمان
CATEGORY_FASHION       = 'fashion'        // مد و فشن
CATEGORY_ELECTRONICS   = 'electronics'    // الکترونیک
CATEGORY_SINGLE_PRODUCT= 'single_product' // محصول واحد
```

---

## Execution Sequence

```
1. php artisan marketplace:seed --fresh   ← full re-seed
2. php artisan marketplace:score          ← score all 25 themes
3. Navigate to /admin/theme-marketplace   ← verify
```

Or use the one-click "راه‌اندازی" button in `/admin/theme-marketplace` which runs both commands automatically.
