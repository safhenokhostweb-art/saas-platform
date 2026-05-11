# راهنمای کامل پروژه — پلتفرم SaaS و سایت مشتری

> **جستجو:** با Ctrl+F (یا Cmd+F) می‌توانید عبارت‌هایی مثل `PLATFORM_URL`, `site-config`, `لایسنس`, `ماژول`, `API` و غیره را در این فایل جستجو کنید.
>
> **آپدیت:** این فایل در هر دو پروژه (saas-platform و customer-site) قرار دارد. با هر تغییری در قابلیت‌ها، مسیرها، API، یا تنظیمات، این راهنما باید به‌روز شود.

---

## فهرست (برای جستجوی سریع)

- [۱. نقش پروژه‌ها](#۱-نقش-پروژه‌ها)
- [۲. متغیرهای محیطی (.env)](#۲-متغیرهای-محیطی-env)
- [۳. APIهای پلتفرم](#۳-apiهای-پلتفرم)
- [۴. مسیرهای مهم (Routes)](#۴-مسیرهای-مهم-routes)
- [۵. ساختار پوشه‌ها و فایل‌های کلیدی](#۵-ساختار-پوشه‌ها)
- [۶. دیتابیس و جداول اصلی](#۶-دیتابیس-و-جداول-اصلی)
- [۷. ماژول‌ها و امکانات](#۷-ماژول‌ها-و-امکانات)
- [۸. جریان UX/UI از پلتفرم به سایت مشتری](#۸-جریان-uxui)
- [۹. لایسنس و سایت‌کانفیگ](#۹-لایسنس-و-سایت‌کانفیگ)
- [۱۰. دستورات آرتیزان و کمندها](#۱۰-دستورات-آرتیزان)
- [۱۱. قانون آپدیت این راهنما](#۱۱-قانون-آپدیت-راهنما)

---

## ۱. نقش پروژه‌ها

| پروژه | مسیر | نقش |
|--------|------|-----|
| **saas-platform** | پنل مدیر اصلی | مدیریت مشتریان، سایت‌ها، لایسنس، ماژول‌های هر سایت، تنظیمات UX (رنگ، فونت، لوگو)، فرم‌ها، دسترسی، کپچا، صفحات ثابت. منبع حقیقت برای «سایت چیست و چه امکاناتی دارد». |
| **customer-site** | سایت زیرمجموعه | یکی از سایت‌های مشتری. محتوا (صفحات، وبلاگ، فروشگاه) را خود مشتری مدیریت می‌کند؛ لایسنس، ظاهر (UX) و لیست ماژول‌های مجاز را از پلتفرم می‌گیرد. |

**قانون:** همهٔ مدیریت‌ها (سایت، ماژول، فرم، مشتری، تنظیمات ظاهری) فقط از **saas-platform** انجام می‌شود. **customer-site** مصرف‌کنندهٔ API و تنظیمات پلتفرم است.

### ۱.۱ استراتژی سئو و امنیت

- **سایت فروش (عمومی):** نمونهٔ **customer-site** که شما برای فروش محصولات و پشتیبانی استفاده می‌کنید، تنها سایت عمومی و قابل ایندکس در موتورهای جستجو است. سئو (متا، sitemap، JSON-LD) روی همین سایت فعال است.
- **پلتفرم (مدیریت):** **saas-platform** نباید در نتایج جستجو ظاهر شود. در پلتفرم با `robots.txt` (Disallow: /)، متا `noindex, nofollow` و هدر `X-Robots-Tag` از ایندکس شدن جلوگیری می‌شود. در سایت فروش به آدرس پلتفرم لینک عمومی داده نمی‌شود.

**جستجو:** `robots` ، `noindex` ، `سئو` ، `امنیت`

---

## ۲. متغیرهای محیطی (.env)

### saas-platform

| متغیر | توضیح | مثال |
|--------|--------|------|
| `APP_NAME` | نام اپلیکیشن | پلتفرم SaaS |
| `APP_URL` | آدرس پایه پلتفرم (برای لینک لوگو در API) | http://saas-platform.test |
| `PAYMENT_CALLBACK_URL` | دامنهٔ callback بانک (مثلاً https://safhenokhost.ir). اگر تنظیم شود، بانک کاربر را به این آدرس برمی‌گرداند. | https://safhenokhost.ir |
| `DB_*` | اتصال دیتابیس | — |
| (سایر متغیرهای استاندارد Laravel) | — | — |

ماژول‌ها و سایت‌ها در پلتفرم از **دیتابیس** و پنل ادمین مدیریت می‌شوند؛ برای فعال/غیرفعال کردن ماژول هر سایت از منوی **سایت‌ها → انتخاب سایت → ماژول‌ها** استفاده کنید.

### customer-site

| متغیر | توضیح | در کدام پروژه |
|--------|--------|----------------|
| `PLATFORM_URL` | آدرس پایهٔ پلتفرم (بدون اسلش آخر). برای اعتبارسنجی لایسنس و دریافت UX از API. | **فقط customer-site** |
| `PLATFORM_CONFIG_CACHE_TTL` | مدت کش تنظیمات و اعلان‌های پلتفرم (ثانیه). پیش‌فرض ۳۰۰ (۵ دقیقه). | **فقط customer-site** |
| `MODULE_BLOG_ENABLED` | فعال بودن ماژول وبلاگ | **فقط customer-site** |
| `MODULE_SHOP_ENABLED` | فعال بودن ماژول فروشگاه | **فقط customer-site** |
| `SITE_NAME` | نام سایت (پیش‌فرض در config) | customer-site |
| `SITE_THEME` | تم فرانت | customer-site |
| `SITE_LOCALE` | زبان | customer-site |
| `OWNER_SITE` | وقتی `true`: سایت مالک؛ callback بانک + صفحهٔ محصولات پلتفرم (`/platform-products`) فعال می‌شود | customer-site |
| `ACCOUNT_WALLET_ENABLED` | فعال بودن کیف پول در داشبورد کاربر نهایی (پیش‌فرض `false`) | customer-site |
| `PROFILE_DELETION_GRACE_DAYS` | مهلت لغو درخواست حذف حساب (روز). پیش‌فرض ۱۴ | customer-site |

**جستجو:** `.env` ، `PLATFORM_URL` ، `OWNER_SITE` ، `MODULE_SHOP_ENABLED` ، `MODULE_BLOG_ENABLED`

---

## ۳. APIهای پلتفرم

همهٔ APIها زیر پیشوند `https://پلتفرم/api/` هستند.

### ۳.۱ اعتبارسنجی لایسنس

- **مسیر:** `POST /api/license/validate`
- **پارامترها (بدن):** `license_key` ، `domain`
- **پاسخ موفق:** `valid: true` ، `expires_at` ، `support_expires_at` ، `support_starts_at` ، `support_level` (basic\|pro\|enterprise) ، `modules` (آرایهٔ slug ماژول‌ها) ، `site_title`
- **پاسخ خطا:** `valid: false` ، `error`: یکی از `invalid_key` ، `revoked` ، `expired` ، `domain_mismatch` ، `site_inactive`

**جستجو:** `license` ، `validate` ، `api/license`

### ۳.۲ تنظیمات سایت (سایت‌کانفیگ + UX)

- **مسیر:** `GET /api/site-config?license_key=...&domain=...`
- **پارامترها (query):** `license_key` ، `domain`
- **پاسخ موفق (۲۰۰):**  
  `allowed: true` ، `site_title` ، `modules` ، `support_expires_at` ، `ux`: `{ general: { platform_name, logo_url, registration_open }, theme: {...}, font: {...} }`
- **پاسخ خطا (۴۰۳):** `allowed: false` ، `error`

**جستجو:** `site-config` ، `API` ، `ux` ، `theme` ، `font`

### ۳.۳ لیست محصولات (برای سایت مالک)

- **مسیر:** `GET /api/products`
- **پاسخ:** `{ "products": [...] }` — لیست محصولات فعال (لایسنس، ماژول، پشتیبانی) برای نمایش در صفحهٔ `/platform-products` سایت مالک.

**جستجو:** `api/products` ، `محصولات`

### ۳.۴ بررسی بروزرسانی

- **مسیر:** `GET /api/updates/check`
- **پارامترها (query):** `product` ، `license_key` ، `domain` ، اختیاری `current_version`
- **پاسخ:** `allowed` ، `update_available` ، `latest_version` ، `changelog` ، `distributed_at`

### ۳.۵ بروزرسانی ماژول‌ها

- **مسیر:** `GET /api/updates/modules` — لیست ماژول‌های دارای بروزرسانی (tenant-aware)
- **مسیر:** `GET /api/updates/module-history` — تاریخچهٔ نسخه و changelog ماژول‌های تخصیص‌یافته به سایت (Phase 5 / Wave 3 / P0-6)
  - **پارامترها (query):** `license_key` ، `domain` ، اختیاری `limit` (۱..۲۰۰، پیش‌فرض ۱۰۰)
  - **gating:** اعتبارسنجی لایسنس (مسیر HMAC + lazy rehash Wave 2) + `ModuleUpdateEligibilityService::isEligibleForModuleUpdates`
  - **پاسخ موفق:** `{ allowed: true, history: [{ module_slug, version, release_type, title, changelog, released_at }] }`
  - **پاسخ ناموفق:** `{ allowed: false, error: 'invalid_license' | 'support_expired' | 'revoked' }` با کد 403
  - **scope:** فقط ماژول‌هایی که سایت در `site_modules.is_active=true` دارد یا در `license->modules` آمده
- **مسیر:** `POST /api/updates/report-versions` — گزارش نسخه‌های نصب‌شده به پلتفرم

**جستجو:** `updates` ، `check` ، `report-versions` ، `module-history`

---

## ۴. مسیرهای مهم (Routes)

### saas-platform (وب)

| نام مسیر | مسیر | توضیح |
|----------|------|--------|
| `home` | `/` | صفحهٔ اصلی |
| `login.show` / `login` | `/login` ، POST `/login` | ورود با موبایل |
| `logout` | POST `/logout` | خروج |
| `register` | `/register` ، POST | ثبت‌نام |
| `page.show` | `/page/{slug}` | صفحهٔ ثابت |
| `admin.dashboard` | `/admin/dashboard` | داشبورد ادمین (گروه admin با middlewareهای `auth` + `admin` + `license.valid` + `permission:dashboard`) |
| `admin.sites.index` | `/admin/sites` | لیست سایت‌ها |
| `admin.sites.show` | `/admin/sites/{site}` | نمایش سایت |
| `admin.sites.modules.edit` | `/admin/sites/{site}/modules` | مدیریت ماژول‌های یک سایت |
| `admin.platform-settings.index` | `/admin/platform-settings` | تنظیمات عمومی پلتفرم (نام، لوگو، رنگ‌ها از جمله نوار کناری پنل، فونت) |
| `admin.customers.index` | `/admin/customers` | مشتری‌ها |
| `admin.forms.index` | `/admin/forms` | فرم‌ها |
| `admin.pages.index` | `/admin/pages` | صفحات ثابت |

**جستجو:** `routes` ، `web.php` ، `admin`

### customer-site (وب)

| نام مسیر | مسیر | توضیح |
|----------|------|--------|
| `login` | `/login` ، POST `/login` | ورود با موبایل |
| `logout` | POST `/logout` | خروج |
| `home` | `/` | صفحهٔ اصلی |
| `page.show` | `/page/{slug}` | صفحه |
| `contact.show` / `contact.submit` | `/contact` | تماس |
| `blog.index` ، `blog.category` ، `blog.series` ، `blog.show` ، `blog.like` | `/blog` ، `/blog/category/{slug}` ، `/blog/series/{slug}` ، `/blog/{slug}` ، POST `/blog/{slug}/like` | وبلاگ (فهرست، دسته، مجموعه، جزئیات، لایک) |
| `shop.index` ، `shop.show` ، `shop.cart` ، `shop.cart.update` ، `shop.cart.remove` ، `shop.checkout` | `/shop` ، ... | فروشگاه (در صورت فعال بودن ماژول) |
| `account.dashboard` | `/account` | داشبورد کاربر نهایی (حساب من) — خلاصه، سفارشات، اشتراک‌ها، اعلان‌ها، امنیت |
| `account.orders.*` | `/account/orders` | سفارشات من (فقط با ماژول فروشگاه) |
| `account.subscriptions.*` | `/account/subscriptions` | اشتراک‌های من (فقط با ماژول اشتراک) |
| `account.forms.index` | `/account/forms` | فرم‌های من (فقط با ماژول فرم) |
| `account.notifications.*` | `/account/notifications` | مرکز اعلان‌ها (inbox کاربر نهایی) |
| `account.security.*` | `/account/security` | امنیت (تغییر رمز، نشست‌های فعال) |
| `account.profile.*` | `/account/profile` | پروفایل (اطلاعات شخصی، آواتار، آدرس‌ها، اعلان‌ها، حریم خصوصی، حذف حساب) |
| `admin.dashboard` | `/admin/dashboard` | داشبورد ادمین |
| `admin.license.index` | `/admin/license` | لایسنس (وارد کردن کلید و دامنه) |
| `admin.modules.index` | `/admin/modules` | ماژول‌های خریداری‌شده، وضعیت پشتیبانی و بروزرسانی |
| `admin.support.index` | `/admin/support` | وضعیت پشتیبانی و ارسال تیکت به پلتفرم |
| `admin.notifications.index` | `/admin/notifications` | مدیریت اعلان‌های تولبار سایت (بنر بالای سایت؛ فقط وقتی ماژول notifications در لایسنس سایت فعال بوده است) |
| `admin.notifications.send-users.form` / `admin.notifications.send-users` | `/admin/notifications/send-users` | ارسال اعلان به کاربران ثبت‌نام‌کرده (inbox داشبورد «حساب من») |
| `admin.notifications.archive` | `/admin/notifications/send-users/archive` | آرشیو اعلان‌های ارسال‌شده به کاربران |

**جریان اعلان‌ها (خلاصه):** از **پلتفرم** (`saas-platform`) برای هر سایت می‌توان اعلان با مخاطب «ادمین» (ویجت شناور پنل ادمین مشتری) یا «کاربران نهایی» (ویجت فرانت + بخش «اعلان سایت» در نوار حساب کاربری) ساخت؛ لیست از `GET /api/site-config` و فیلدهای `id`، `priority`، `created_at` می‌آید. در **customer-site** ادمین سایت می‌تواند اعلان محلی تولبار بسازد، به کاربران **inbox** (`account/notifications`) بفرستد و در صورت فعال بودن ماژول **sms** گزینهٔ ارسال **پیامک** خلاصه را بزند. helper مرتب‌سازی: `App\Helpers\NotificationUi`.

| `admin.settings.index` | `/admin/settings` | تنظیمات سایت (شامل قالب هدر/فوتر سازندهٔ بصری، هدر چسبان، و رنگ‌های نوار کناری پنل ادمین/حساب کاربری) |
| `builder.edit` ، `builder.page.edit` ، `builder.page.show` ، `builder.page.update` ، `builder.preview` ، `builder.templates.index` ، `builder.templates.store` | `/builder/edit/{page}` ، `/builder/page/{page}/edit` ، GET/PUT `/builder/page/{page}` ، POST `/builder/preview` ، GET/POST `/builder/templates` | سازندهٔ بصری صفحه (ادمین): رابط کاربری چندستونه (المان‌ها / **قالب کامل**)، پیش‌نمایش زنده در بالا، درخت ساختار در پایین، جستجو در کتابخانهٔ المان‌ها؛ **ویرایش زنده:** کلیک روی هر بلوک با `id` به شکل `tb-*` در iframe پیش‌نمایش، همان المان را در پنل راست انتخاب می‌کند (`postMessage` با `page-builder-preview` / `page-builder-parent`)؛ سه **Kit** حرفه‌ای (`corporate_pro`، `personal_pro`، `shop_pro`) در `App\Support\BuilderPageKits` + `config/builder-page-kits.php`؛ ذخیرهٔ `builder_content_v2`؛ رندر `promo_banner`، `countdown`، `gallery`، `custom_html` در `BlockRenderer` |
| `admin.pages.*` ، `admin.posts.*` ، `admin.products.*` ، `admin.orders.*` | ... | صفحات، مطالب، محصولات، سفارشات |
| `platform-products.index` | `/platform-products` | فقط سایت مالک؛ محصولات پلتفرم (لایسنس، ماژول، پشتیبانی) از API |
| `payment.callback` | `/payment/callback` | فقط سایت مالک؛ callback بانک → فوروارد به پلتفرم (اعتبارسنجی پارامترهای `invoice`، `Authority` و `Status` قبل از redirect) |
| `sitemap` | `/sitemap.xml` | نقشهٔ سایت (کش از config/seo؛ برای موتورهای جستجو) |
| `sitemap.part` | `/sitemap-part-{n}.xml` | بخش sitemap (برای سایت‌های بزرگ با بیش از ۵۰٬۰۰۰ URL) |
| `guide.search` | GET `/guide-search?q=` | جستجو در راهنما (JSON) |
| `install.*` | `/install/*` | ویزارد نصب اولیه؛ پس از اتمام، فایل `storage/installed.lock` ساخته می‌شود. اگر این فایل حذف شود دوباره ویزارد باز می‌شود؛ در مرحلهٔ ادمین، اگر ایمیل مدیر از قبل در جدول `users` باشد، همان رکورد به‌روز می‌شود (نصب مجدد روی همان دیتابیس). |

**دیتابیس در توسعه:** برای نگه داشتن داده بعد از تغییر کد، فقط `php artisan migrate` بزنید. دستورات `migrate:fresh` و `db:wipe` همه جداول را حذف می‌کنند. اگر بدون دلیل به `/install` برمی‌گردید، `storage/installed.lock` را حذف نکرده باشید و مسیر دیتابیس در `.env` پایدار باشد. ویزارد مرحلهٔ دیتابیس، `DB_CONNECTION=mysql` و خطوط `# DB_*` در `.env.example` را هم درست به‌روز می‌کند.

**جستجو:** `routes` ، `customer-site` ، `admin` ، `sitemap`

**RBAC (مسیرهای ادمین):** در customer-site مسیرهای admin به‌صورت granular نیز محافظت می‌شوند؛ نمونه‌ها: `admin.pages.* -> permission:pages`، `admin.products.* -> permission:products`، `admin.orders.* -> permission:orders`، `admin.settings.* -> permission:settings`، `admin.license.* -> permission:license`، `admin.posts.*` و `admin.post-series.* -> permission:posts`، اقدامات انتشار/تایید/بازگردانی نسخه (`admin.posts.approve`، `reject`، `revisions.restore`) -> `permission:posts_moderate`.

**بهینه‌سازی Layout + لاگ امنیتی:** در `AppServiceProvider` برای `currentPage` اگر دادهٔ `page` از controller موجود باشد، همان استفاده می‌شود تا در `page.show` کوئری تکراری حذف شود. همچنین در middlewareهای `CheckPermission` و `EnsureLicenseValid` برای رد دسترسی/لایسنس (`403` و redirectهای مرتبط) لاگ امنیتی ساخت‌یافته ثبت می‌شود.

**SEO:** متا (title، description، robots)، canonical، OpenGraph و Twitter Card در layout فرانت؛ JSON-LD (Organization، WebSite، WebPage، Article، Product، BreadcrumbList)؛ `SeoService` و helperهای `Seo::*`؛ `SlugHelper::make/sanitize/unique`؛ تنظیمات tenant-level از پلتفرم (`site_settings`: seo_default_title، seo_default_description، seo_robots، seo_og_image)؛ `config/seo.php`؛ sitemap با کش و sitemap index برای سایت‌های بزرگ.

**UX:** راهنمای مراحل بهبود UX در `docs/UX-ROADMAP.md`. Layout واکنش‌گرا با منوی موبایل، Hero در خانه، کارت‌های محتوا، فرم‌های استایل‌شده و Breadcrumb.

---

## ۵. ساختار پوشه‌ها

### saas-platform — فایل‌های کلیدی

| مسیر | توضیح |
|------|--------|
| `app/Helpers/PlatformSettings.php` | کلیدهای تنظیمات عمومی، تم (`THEME_KEYS` شامل رنگ‌های نوار کناری پنل ادمین پلتفرم)، فونت، آپلود؛ متدهای `get` ، `allTheme` ، `themeKeyLabel` ، `allFont` ، `logoUrl` ، `platformName` |
| `app/Http/Controllers/Api/SiteConfigController.php` | API سایت‌کانفیگ؛ برگرداندن `ux` (theme, font, general) در پاسخ |
| `app/Http/Controllers/Api/LicenseController.php` | API اعتبارسنجی لایسنس |
| `app/Http/Controllers/Admin/PlatformSettingsController.php` | ذخیرهٔ تنظیمات عمومی پلتفرم |
| `app/Http/Controllers/Admin/SiteModuleController.php` | ویرایش ماژول‌های هر سایت (با توجه به pivot `site_module_id` در site_module_features) |
| `app/Models/Site.php` | رابطهٔ `modules()` ؛ متد `getEnabledModuleFeatureIds()` برای فیچرهای فعال سایت |
| `app/Models/Setting.php` | ذخیرهٔ key/value تنظیمات |
| `app/Services/LicenseServerService.php` | اعتبارسنجی لایسنس و برگرداندن ماژول‌های سایت |
| `resources/views/partials/platform-styles.blade.php` | خروجی CSS متغیرهای تم و فونت |
| `resources/views/partials/platform-admin-sidebar-theme.blade.php` | رنگ نوار کناری پنل ادمین پلتفرم از `PlatformSettings::allTheme()` |
| `resources/views/admin/platform-settings/index.blade.php` | فرم تنظیمات عمومی پلتفرم |
| `routes/api.php` | مسیرهای API |
| `routes/web.php` | مسیرهای وب |

**جستجو:** `PlatformSettings` ، `SiteConfigController` ، `LicenseServerService` ، `platform-styles`

### customer-site — فایل‌های کلیدی

| مسیر | توضیح |
|------|--------|
| `app/Helpers/PlatformUx.php` | دسترسی به نام پلتفرم، لوگو، تم، فونت، seo از کش/API |
| `app/Helpers/AdminSidebarTheme.php` | کلیدهای رنگ نوار کناری پنل ادمین مشتری و «حساب من» (`admin_sidebar_*`)؛ پیش‌فرض آبی ملایم |
| `app/Helpers/Seo.php` | `title` ، `description` ، `imageUrl` ، `robots` ، `canonical` ، `twitterCard` |
| `app/Helpers/SlugHelper.php` | `make` ، `sanitize` ، `unique` ، `transliterateFa` ، `isValid` |
| `app/Helpers/JsonLd.php` | `organization` ، `webSite` ، `webPage` ، `article` ، `product` ، `breadcrumb` |
| `app/Services/SeoService.php` | سرویس مرکزی SEO با پشتیبانی تنظیمات tenant-level |
| `app/Helpers/Module.php` | `enabled($key)` بر اساس config و .env (MODULE_BLOG_ENABLED ، MODULE_SHOP_ENABLED) |
| `app/Helpers/SiteHelper.php` | `siteName()` ، `shopEnabled()` ، `blogEnabled()` ، `theme()` |
| `app/Services/PlatformUxService.php` | دریافت و کش UX از API پلتفرم؛ متدهای `getUx` ، `allTheme` ، `allFont` ، `platformName` ، `logoUrl` ، `clearCache()` |
| `app/Services/BuilderLayoutService.php` | انتخاب قالب هدر/فوتر سایت از تنظیمات؛ `getHeaderBlocks()` ، `getFooterBlocks()` ، `isStickyHeader()` ؛ اولویت: تنظیم سایت (builder_header_template، builder_footer_template) سپس تم پلتفرم |
| `app/Services/ThemeBuilder/BlockRenderer.php` | رندر بلوک‌های هدر/فوتر و محتوای صفحهٔ سازندهٔ بصری؛ اعتبارسنجی با ElementSchemaRegistry؛ در صورت خطا یا exception نمایش placeholder به‌جای خراب شدن صفحه |
| `app/Services/ThemeBuilder/ElementSchemaRegistry.php` | رجیستری و اعتبارسنجی قرارداد (schema) هر نوع المان؛ قبل از رندر بلوک با آن اعتبارسنجی می‌شود |
| `config/builder-element-schemas.php` | تعریف schema هر نوع المان (props الزامی/اختیاری، نوع، محدودیت طول رشته/URL و عمق تو در تو) |
| `config/builder.php` | لیست کلاس‌های Element SDK (عناصر سازنده) برای ElementRegistry |
| `app/Builder/` | **Element SDK:** Contracts، Elements (BaseElement + المان‌های مشخص)، ElementRegistry، Validation/ElementValidator، Rendering/ElementRenderer، Services/DynamicDataResolver، Testing/ElementTestSuite |
| `config/builder-headers.php` ، `config/builder-footers.php` | قالب‌های ۱۰گانهٔ هدر و فوتر برای انتخاب در تنظیمات |
| `config/builder-section-presets.php` | بخش‌های آماده (هیرو، خدمات، CTA، تماس، FAQ، نظرات، وبلاگ، محصولات) برای درج در سازندهٔ بصری |
| `config/builder-page-kits.php` | قالب‌های صفحهٔ کامل شرکتی، شخصی و فروشگاهی (اعمال یکجا در تب «قالب کامل» سازنده) |
| `app/Services/LicenseActivationService.php` | فراخوانی API لایسنس پلتفرم و ذخیرهٔ نتیجه در مدل License |
| `app/Models/License.php` | `license_key` ، `domain` ، `expires_at` ، `modules` (آرایه) ، `support_expires_at` ، `support_starts_at` ، `support_level` ؛ متد `current()` |
| `config/site.php` | `platform_url` ، `platform_config_cache_ttl` ، `name` ، `theme` ، `locale` |
| `config/modules.php` | تعریف ماژول‌های blog و shop و env مربوطه |
| `app/Http/Middleware/UseFileSessionWhenNotInstalled.php` | هنگام نصب‌نشده بودن سایت، درایور session را موقتاً `file` می‌کند تا ویزارد نصب حتی قبل از آماده شدن دیتابیس/جدول sessions قابل استفاده باشد. |
| `resources/views/admin/layout.blade.php` | قالب پنل ادمین؛ استایل پوسته از `public/css/customer-admin-layout.css` (بدون CDN)؛ رنگ نوار کناری از `partials/customer-admin-sidebar-theme` (تنظیمات `admin_sidebar_*`)؛ صفحات داخلی از `resources/css/app.css` ساخته‌شده با Vite روی همان هاست. |
| `public/css/customer-admin-layout.css` | CSS محلی منو، هدر، فلش‌ها، کارت/جدول/فرم پایهٔ ادمین (بدون Tailwind CDN). |
| `resources/views/admin/{products,orders,forms,pages,posts}/index.blade.php` | صفحات پرتردد ادمین با الگوی یکدست `admin-page` (هدر ابزار، کارت جدول، empty-state، pagination) استاندارد شده‌اند. |
| `resources/views/admin/{products,pages,posts}/{create,edit}.blade.php` | فرم‌های ایجاد/ویرایش پرتردد با الگوی یکسان کارت + `form-group` + اکشن‌های استاندارد بازآرایی شده‌اند. |
| `resources/views/admin/{categories,product-categories}/{index,create,edit}.blade.php` | چرخه کامل CRUD دسته‌بندی‌های وبلاگ/محصولات با همان الگوی واحد لیست و فرم یکدست شده است. |
| `resources/views/admin/{courses,coupons,reviews}/*.blade.php` | صفحات مدیریتی دوره‌ها، کوپن‌ها و نظرات با الگوی `admin-page` و جدول/فرم یکدست هماهنگ شده‌اند. |
| `resources/views/admin/{invoices,business-cards,social-links,advertisements}/*.blade.php` | بلوک بعدی ماژول‌های پرتردد ادمین با الگوی واحد `admin-page`، empty-state یکدست، pagination استاندارد و فرم‌های هم‌ساختار (`admin-page-tools`) هماهنگ شده‌اند. |
| `resources/views/admin/{subscriptions,referrals,sms}/*.blade.php` | صفحات اشتراک، معرفی دوستان و تنظیمات پیامک نیز وارد الگوی یکپارچهٔ ادمین شدند (هدر/ابزار، empty-state، pagination و اکشن فرم استاندارد). |
| `resources/views/admin/notifications/*.blade.php` | ماژول اعلان‌ها (تولبار، ارسال به کاربران، آرشیو) با الگوی `admin-page` یکدست شد؛ صفحات `archive` و `send-users` دیگر jQuery/persian-datepicker را از CDN بارگذاری نمی‌کنند (ورودی تاریخ شمسی با `x-persian-date-input` به‌صورت متنی و بدون وابستگی بیرونی). |
| `resources/views/admin/{messaging,menus}/*.blade.php` | صفحات مدیریت پیام‌رسان‌ها و منوها با الگوی `admin-page` بازآرایی شد (کارت‌بندی، اکشن‌بار، فرم استاندارد و اسکریپت‌های درون `@push('scripts')`). |
| `resources/views/admin/forms/submissions/index.blade.php` | گزارش ارسال فرم‌ها با فیلتر/خروجی یکپارچه شد؛ وابستگی CDN برای datepicker و sortable حذف شد، جابه‌جایی ستون‌ها با Drag & Drop بومی (`HTML5 DnD`) انجام می‌شود، و بازخورد بصری Drag (hover/drag states) اضافه شد. |
| `public/css/customer-admin-layout.css` (مرحله ۲) | ارتقای سیستم طراحی مشترک ادمین: توکن‌های رنگ/feedback، حلقهٔ فوکوس یکنواخت (`focus-visible`)، بهبود دکمه‌ها (border + state)، یکدست‌سازی badgeها و رعایت `prefers-reduced-motion` برای UX بهتر و حرفه‌ای‌تر. |
| `public/assets/fonts/vazirmatn/*` | فایل‌های فونت آفلاین (`woff2`) برای استفاده در کل سایت و ادمین. |
| `resources/views/partials/platform-styles.blade.php` | استفاده از `PlatformUx::allTheme()` و `allFont()` برای استایل |
| `resources/views/layouts/auth-login.blade.php` | لایهٔ یکپارچهٔ ورود (دو ستون، برند، SVG) — همان الگوی بصری `saas-platform`؛ توکن‌های تم از `platform-styles` |
| `resources/views/partials/auth-captcha.blade.php` | بلوک کپچای فرم ورود (با `CaptchaService::getAuthCaptchaConfig`) |
| `resources/views/admin/dashboard.blade.php` | داشبورد + بلوک «ماژول‌ها و امکانات از پلتفرم» |
| `routes/web.php` | مسیرهای نصب، فرانت، ادمین، لایسنس |

**جستجو:** `PlatformUx` ، `PlatformUxService` ، `License` ، `Module::enabled`

---

## ۶. دیتابیس و جداول اصلی

### saas-platform

| جدول | توضیح |
|------|--------|
| `sites` | سایت‌های مشتری (عنوان، دامنه، فعال/غیرفعال، support_expires_at و ...) |
| `site_modules` | pivot سایت–ماژول: `site_id` ، `module_id` ، `is_active` ، `installed_version` ، `activated_at` |
| `site_module_purchases` | خرید/مالکیت ماژول (برای PAID): `site_id` ، `module_id` ، `invoice_id` ، `granted_by` ، `purchased_at` |
| `modules` | ماژول‌ها (slug، title، description، **type**: core\|paid\|internal، **price_irr** برای ماژول‌های paid؛ نوع internal فقط برای پنل مدیر اصلی، نوع core برای ماژول‌های مبتنی بر لایسنس (همراه لایسنس اصلی سایت)، نوع paid برای ماژول‌های پولی قابل خرید) |
| `module_features` | فیچرهای هر ماژول (module_id، key، title) |
| `site_module_features` | pivot: `site_module_id` (رفرنس به site_modules) ، `module_feature_id` ، `is_active` — **بدون site_id** |
| `platform_licenses` | لایسنسهای صادرشده (site_id، hash کلید HMAC-SHA256 با fallback SHA256 legacy، domain_allowed، expires_at) |
| `settings` | key/value تنظیمات پلتفرم (نام، لوگو، رنگ‌ها، فونت‌ها و ...) |
| `customers` | مشتریان |
| `users` | کاربران پلتفرم (ورود با موبایل) |
| `activity_logs` | لاگ فعالیت پنل ادمین پلتفرم (action، description، user_id، subject_type/id، properties json، ip، ua). actions ثبت‌شده: customer.*، site.*، form.*، user.toggled، license.issued، site_update.distributed، module.updated، module.bulk_updated (Phase 5 / Wave 3 / P1-3) |

**جستجو:** `migration` ، `site_modules` ، `site_module_features` ، `platform_licenses` ، `activity_logs`

### customer-site

| جدول | توضیح |
|------|--------|
| `licenses` | لایسنس فعال (license_key، domain، expires_at، modules، support_expires_at، support_starts_at، support_level) |
| `users` | کاربران سایت (شامل mobile برای ورود) |
| `pages` | صفحات ثابت؛ `builder_content` (ساختار سازندهٔ v1)، `builder_content_v2` (ساختار سازندهٔ v2 با شناسهٔ یکتا و درگ‌اند‌دراپ)؛ رندر با اولویت v2 در صورت وجود |
| `posts` ، `categories` ، `tags` ، `post_tag` ، `post_series` ، `post_revisions` | محتوای وبلاگ (مطلب، دسته، برچسب، مجموعه، نسخه‌ها) |
| `products` ، `orders` و جداول وابسته | فروشگاه (در صورت فعال بودن ماژول) |
| `settings` | تنظیمات محلی سایت |
| `user_activity_log` | فعالیت end-user (ورود، تغییر رمز، ویرایش پروفایل و آدرس) |
| `admin_activity_log` | لاگ فعالیت پنل ادمین tenant — Phase 5 / Wave 3 / P1-2 (admin_user_id، action، description، subject_type/id، properties json، ip، ua). actions ثبت‌شده: license.activated، license.refreshed_config، license.refreshed_modules، module.settings_updated. مسیر نمایش: `/admin/activity-log` |
| `module_change_logs` | تاریخچهٔ نسخه و changelog ماژول‌ها (sync از پلتفرم با `GET /api/updates/module-history`) |

**جستجو:** `licenses` ، `migration` ، `customer-site` ، `admin_activity_log` ، `module_change_logs`

---

## ۷. ماژول‌ها و امکانات

### در پلتفرم (saas-platform)

- ماژول‌ها در جدول **modules** تعریف می‌شوند (type: core\|paid\|internal؛ برای PAID فیلد price_irr و سایر فیلدهای مارکت‌پلیس مانند marketplace_visible، category، short_description، screenshots).
- **سه نوع ماژول:** (۱) **internal** — ماژول داخلی فقط برای پنل مدیر اصلی؛ هرگز به customer-site ارسال نمی‌شود؛ (۲) **core** — ماژول مبتنی بر لایسنس اصلی سایت (LICENSE-ASSIGNED)، قابل اختصاص به سایت‌ها از طریق **site_modules** و همراه با لایسنس پلتفرم؛ (۳) **paid** — ماژول پولی (PAID MODULE) که از طریق فروشگاه ماژول قابل خرید است و پس از خرید برای سایت فعال می‌شود.
- **قانون فعال‌سازی:** ماژول فقط زمانی برای یک سایت فعال است که **(الف) در pivot `site_modules` برای آن سایت `is_active=1` باشد** و **(ب) برای ماژول‌های type = paid در جدول `module_licenses` لایسنس معتبر وجود داشته باشد**. ماژول‌های type = core فقط به چک‌باکس `site_modules` وابسته‌اند؛ internal هرگز به سایت‌ها اختصاص داده نمی‌شود.
- برای هر **سایت** در **site_modules** مشخص می‌شود کدام ماژول با `is_active=1` فعال است. ماژول‌های **PAID** فقط در صورت وجود رکورد در **site_module_purchases** (خرید یا grant ادمین) قابل فعال‌سازی هستند. **ModuleAssignmentService::getEnabledSlugsForSite()** فقط ماژول‌های type = core/paid با وضعیت توزیع غیرفعال‌نشده را برای API برمی‌گرداند.
- API لایسنس و سایت‌کانفیگ لیست **slug** ماژول‌های فعال آن سایت را برمی‌گردانند.
- داشبورد ادمین پلتفرم: بخش **«وضعیت ماژول‌های سایت‌های مشتری»** — جدول سایت × ماژول و لینک «ماژول‌ها» برای هر سایت.
- مدیریت ماژول هر سایت: **سایت‌ها → انتخاب سایت → ماژول‌ها** (یا مسیر `admin.sites.modules.edit`). فیچرهای هر ماژول از طریق جدول **site_module_features** (با **site_module_id**) ذخیره می‌شوند.

**جستجو:** `ماژول` ، `site_modules` ، `SiteModuleController` ، `getEnabledModuleFeatureIds`

### در سایت مشتری (customer-site)

- ماژول‌های قابل استفاده در **config/modules.php** تعریف شده‌اند (blog، shop، coupon، reviews، referral، subscription، whatsapp/messaging، sms، courses، invoice، business_card، social_links، advertisements، forms، advanced_search، currency_reform، menu و …).
- **منبع حقیقت برای فعال/غیرفعال بودن ماژول‌ها:** در صورت وجود لایسنس معتبر، `SiteHelper::moduleEnabled($key)` ابتدا از فیلد **modules** مدل **License** (لیست slugهای فعال برای این سایت از پلتفرم) استفاده می‌کند و سپس در صورت عدم وجود، به **config/modules.php** و مقادیر **.env** (`MODULE_*_ENABLED`) fallback می‌کند.
- لیست ماژول‌های **دریافتی از پلتفرم** در مدل **License** (فیلد `modules`) ذخیره و در داشبورد در بلوک **«ماژول‌ها و امکانات از پلتفرم»** و صفحهٔ `admin.modules.index` نمایش داده می‌شود.
- در صفحهٔ `admin.modules.index` برای ماژول **blog** کارت وضعیت حرفه‌ای نمایش داده می‌شود: نسخهٔ نصب‌شده، نسخهٔ قابل‌دریافت، هشدار وجود آپدیت، دکمهٔ دریافت آپدیت و میانبر همگام‌سازی/نمایش changelog وبلاگ.
- در همان صفحه برای ماژول **shop** هم کارت وضعیت حرفه‌ای اضافه شده است (نمایش نسخهٔ نصب‌شده/قابل‌دریافت، هشدار آپدیت، میانبر لاگ تغییرات و همگام‌سازی لاگ).
- صفحهٔ `admin.modules.changelog` از پارامتر `module` پشتیبانی می‌کند (مثلاً `?module=blog`) تا لاگ تغییرات یک ماژول خاص فیلتر شود.
- برای اکثر ماژول‌ها (وبلاگ، فروشگاه، کوپن، نظرات، معرفی دوستان، اشتراک، پیامک، دوره‌ها، فاکتور، کارت ویزیت، لینک‌های اجتماعی، تبلیغات، فرم‌ساز، اصلاح پول ملی و …) یک بخش کامل مدیریت در پنل ادمین سایت وجود دارد (`routes/web.php`، گروه `admin.*` و کلاس‌های زیر `App\Http\Controllers\Admin\*`).
- ماژول `blog` (نسخهٔ 1.4.0):
  - ویرایشگر WYSIWYG (TinyMCE از `public/vendor/tinymce` با partial `admin.partials.tinymce-professional-base`) برای بدنهٔ مطلب؛ خروجی HTML با `PostHtml::safe()` در فرانت سانitize می‌شود؛ ورودی زمان انتشار/زمان‌بندی در فرم ادمین با datepicker شمسی (`persian-datepicker`) و ذخیرهٔ میلادی در دیتابیس؛ فهرست و فرم ویرایش زمان انتشار و تاریخ ثبت/ویرایش رکورد را با `Jalali::formatFa` نشان می‌دهند.
  - وضعیت تحریریه: `editorial_status` (پیش‌نویس، در انتظار بازبینی، تایید، رد، منتشر) همراه با `is_published` و `published_at`؛ انتشار و زمان‌بندی فقط با دسترسی `posts_moderate` (مدیر سایت/نقش tenant_admin به‌صورت پیش‌فرض)؛ کارمند با فقط `posts` می‌تواند پیش‌نویس ذخیره یا برای بازبینی ارسال کند.
  - سئو هر مطلب: `meta_title`، `meta_description`، `canonical_url` اختیاری، `og_image` اختیاری (اولویت در `SeoService::imageUrl` نسبت به تصویر شاخص).
  - برچسب‌ها (جدول `tags` و واسط `post_tag`) و مجموعهٔ مطالب (`post_series`، فیلدهای `series_id` و `part_number` روی `posts`)؛ CRUD مجموعه در `admin.post-series.*`؛ فیلتر برچسب در `blog.index` و صفحهٔ `blog.series`.
  - گالری تصاویر (`gallery_images` JSON)، بازدید با یک‌بار شمارش در هر نشست، لایک ساده (یک‌بار در نشست، مسیر POST `blog.like`).
  - تاریخچهٔ نسخه‌ها (`post_revisions` با snapshot JSON)، صفحهٔ `admin.posts.revisions` و بازگردانی نسخه با `posts_moderate`.
  - جداول: `tags`، `post_series`، `post_tag`، `post_revisions`؛ مایگریشن `2026_05_08_180000_blog_professional_features.php` و به‌روزرسانی رکورد `module_versions` / `module_change_logs` برای slug `blog`.
- ماژول `shop` (نسخهٔ 2.0.0) — به‌روزرسانی ۱۴۰۵/۰۲/۱۸:
  - **کاتالوگ:** تنوع (واریانت) با SKU، قیمت/حراج و موجودی جدا؛ گالری `product_images`؛ بستهٔ محصول با اجزا و قیمت ثابت یا جمع با تخفیف؛ برچسب و ویژگی‌های فنی با فیلتر در لیست؛ دستهٔ سلسله‌مراتبی؛ مرتبط / upsell / cross_sell؛ پیش‌نویس/انتشار (`status`)؛ حداقل/حداکثر سفارش؛ پیش‌فروش (`allow_backorder`) و جدول `product_stock_notifications`.
  - **صفحهٔ محصول:** تب‌ها (توضیح، مشخصات، ارسال/بازگشت، FAQ)، جدول سایز، شمارش معکوس `sale_ends_at`، اعتماد (گارانتی/تضمین)، گالری و ویدیو، JSON-LD و متای canonical/og.
  - **تعامل:** نظرات `product_reviews` (بازبینی ادمین)، علاقه‌مندی و مقایسهٔ نشستی، شمارندهٔ بازدید (یک بار به ازای نشست)، `shop_cart_events` برای رویداد افزودن به سبد، لاگ اختیاری `product_audit_logs` برای قیمت/موجودی.
  - **سبد و سفارش:** کلید ردیف `productId:variantId`، ذخیرهٔ `product_variant_id` روی `order_items`، کوپن با `ShopCartHelper::distinctProductIds`.
  - **ادمین:** فرم گسترش‌یافتهٔ محصول (TinyMCE محلی برای توضیح بلند، ارسال/بازگشت، گارانتی، تضمین، جدول سایز HTML؛ FAQ به‌صورت ردیف‌های فرم با ویرایشگر غنی برای پاسخ)، `sale_ends_at` با datepicker شمسی و ساعت؛ CRUD ویژگی‌های فنی (`admin.product-attributes.*`)، مدیریت نظرات فروشگاه (`admin.shop-product-reviews.*`)؛ فهرست و ویرایش محصول تاریخ ثبت/ویرایش و در صورت وجود پایان حراج را با `Jalali::formatFa` نشان می‌دهند.
  - نسخهٔ قبلی مستند 1.1.0: فیلتر قیمت `effective_price`، اعتبارسنجی موجودی سبد، checkout تراکنشی و `lockForUpdate` همچنان برقرار است.
- **کپچا (customer-site):** مسیر `admin.captcha.*`؛ انواع `persian_number`، `normal` و `hcaptcha` در جدول `settings`. با **فعال بودن ماژول** (`captcha_enabled=1`) و **گزینهٔ ورود** (`auth_captcha_enabled=1`)، کپچا روی صفحهٔ `login` نمایش و در `LoginController` اعتبارسنجی می‌شود (`getAuthCaptchaConfig`، همان منطق پلتفرم). برای توسعهٔ محلی: `AUTH_CAPTCHA_SKIP_IN_LOCAL=true` در `.env` و `config/services.php` → `hcaptcha.skip_in_local`.
- ماژول `forms` (فرم‌ساز در customer-site):
  - جدول `forms`: پس از مایگریشن `2026_05_09_140000_add_seo_to_forms_table.php` فیلدهای `meta_title`، `meta_description`، `canonical_url`، `meta_robots`، `intro_text`؛ نامک پایدار با `SlugHelper`؛ ادمین `admin.forms.*` با کارت آماری (تعداد فیلد فعال و ارسال)، لینک مستقیم عمومی و بخش سئو/متن راهنما.
  - فرانت `forms.index` / `forms.show` / `forms.submit`: breadcrumb، متا و canonical از فیلدهای فرم، `@section('meta_robots')` در `themes/default/layout.blade.php` برای robots صفحه‌ای، JSON-LD (`WebPage` / `CollectionPage`)؛ انواع فیلد شامل `select` / `radio` / `checkbox` با گزینه‌های چندخطی؛ تاریخ شمسی در ارسال با `Jalali::toCarbon` به `Y-m-d` در JSON ارسال.
  - **Sitemap:** اگر `SiteHelper::moduleEnabled('forms')` باشد، URL فهرست فرم‌ها و هر فرم فعال در `SitemapController::collectEntries`؛ `SitemapController::clearCache()` پس از ذخیره/حذف فرم در `FormController`.
- صفحهٔ `admin.modules.index` علاوه‌بر نسخهٔ نصب‌شده، آخرین changelog همگام‌شدهٔ هر ماژول را نیز نشان می‌دهد تا مقایسهٔ نسخهٔ نصب‌شده/قابل‌دریافت شفاف‌تر باشد.
- ماژول `advanced_search`:
  - مسیرهای فرانت: گروه `module:advanced_search` در `routes/web.php` (`search.index`، `search.suggest`، `search.results`).
  - صفحهٔ تنظیمات در پنل ادمین: `admin.modules.show` با `slug=advanced_search`، که از آن‌جا می‌توان تعیین کرد جستجو روی محصولات و/یا مطالب وبلاگ فعال باشد و تعداد نتایج در هر صفحه (ذخیره در `settings` با کلیدهای `advanced_search_include_products`، `advanced_search_include_posts`، `advanced_search_results_per_page`).
  - رفتار فرانت در `AdvancedSearchController` بر اساس همین تنظیمات و فعال‌بودن ماژول (`SiteHelper::moduleEnabled('advanced_search')`) کنترل می‌شود.
- ماژول `menu` (به‌روزرسانی ۱۴۰۵/۰۲/۱۸):
  - اگر در لایسنس/تنظیمات فعال باشد (`SiteHelper::moduleEnabled('menu')`)، layout پیش‌فرض (`resources/views/themes/default/layout.blade.php`) از منوی منتشرشده در جدول `menus` (موقعیت‌های `primary`، `footer`، `mobile`، `secondary`، `topbar`) و در نبود آن از `PlatformUx::menus()` استفاده می‌کند.
  - **حالت local:** `PlatformUxService::getMenus()` در `menu_mode=local` منوی منتشرشدهٔ دیتابیس را با کلیدهای `header`/`footer`/`mobile`/`secondary`/`topbar` به خروجی API‌شکل یکسان می‌کند؛ اگر برای یک کلید در دیتابیس آیتمی نباشد، از `menu_custom_menus` در `settings` استفاده می‌شود. `decodeMenus` و `enrichMenuTreeItems` فیلدهای غنی (UTM، badge، مگامنو، …) را یکدست می‌کنند.
  - **ادمین:** مسیر `admin.menus.*` — درخت تو در تو با SortableJS، ذخیرهٔ ساختار از طریق `menus.reorder-tree`، کپی شاخه (`copy-branch`)، پیش‌نویس/انتشار و `go_live_at` با datepicker شمسی و ساعت (partial `admin.menus.partials.go-live-at-*`)، پیش‌نمایش با `preview_token`، نسخه‌گردانی (`menu_revisions` + بازگردانی با تاریخ شمسی در فهرست)، لاگ اختیاری (`menu_change_logs`).
  - **آیتم:** انواع `custom_url`، `internal_page`، `blog`، `blog_category`، `blog_tag`، `shop`، `category`؛ فیلدهای `utm_query`/`utm_json` (کلیدهای `utm_*`)، `rel_nofollow`، `open_in_new_tab`، `badge_label`، `image_url`، `mega_column`، `featured_html`، `auto_list_children` (زیردستهٔ خودکار برای دستهٔ فروشگاه)، `visible_modules` (فیلتر بر اساس `SiteHelper::moduleEnabled`)، `title_en` و `labels` JSON.
  - **فرانت:** `partials/site-menu.blade.php` و `MenuActiveTrail` برای مسیر فعال؛ مگامنو چندستونه از `mega_columns`؛ موبایل آکاردئونی (`partials/site-menu-mobile.blade.php`) با `aria-expanded`/`aria-hidden` و قفل اسکرول بدنه (`config/menu.php` → `mobile_body_scroll_lock`).
  - **کش:** `MenuService::clearMenuCache` و `PlatformUxService::clearCache` پس از ذخیره.
  - **API:** `GET /site-api/menus` (`site-api.menus`) خروجی JSON منوها وقتی ماژول فعال است.
  - **پیکربندی:** `config/menu.php` (`MENU_MAX_DEPTH`، پیش‌فرض ۴).
  - صفحهٔ تنظیمات سادهٔ منو در `admin.modules.show` با `slug=menu` همچنان برای حالت «فقط JSON در settings» وجود دارد؛ اولویت نمایش در حالت local با منوی دیتابیس است.
  - نسخهٔ منتشرشده در پلتفرم برای ماژول منو: **۱.۲.۰** (مایگریشن `2026_05_08_210000_release_menu_module_1_2_0` در saas-platform).
- ماژول `seo_advanced`:
  - در صورت فعال بودن (`SiteHelper::moduleEnabled('seo_advanced')`) یک صفحهٔ اختصاصی در پنل ادمین با route نام‌گذاری‌شده `admin.seo-advanced.index` و مسیر `/admin/seo-advanced` نمایش می‌دهد.
  - این صفحه وضعیت ماژول، تنظیمات سئوی دریافتی از پلتفرم (seo_default_title، seo_default_description، seo_robots، seo_twitter_card) و نمای کلی محتوای قابل ایندکس (تعداد صفحات، مطالب و محصولات) را نشان می‌دهد؛ منطق تولید متا در `App\Helpers\Seo` و `App\Services\SeoService` پیاده‌سازی شده است.

**جستجو:** `config/modules.php` ، `SiteHelper::moduleEnabled` ، `License::current()->modules` ، `admin.modules.*` ، `advanced_search` ، `menu_custom_menus`

---

## ۸. جریان UX/UI از پلتفرم به سایت مشتری

1. در **پلتفرم** در **تنظیمات عمومی پلتفرم** نام، لوگو، رنگ‌ها (THEME_KEYS) و فونت‌ها (FONT_KEYS) تنظیم می‌شوند و در جدول **settings** ذخیره می‌شوند.
2. **سایت مشتری** با داشتن لایسنس معتبر به **GET /api/site-config?license_key=...&domain=...** درخواست می‌زند.
3. پاسخ شامل **ux** (general، theme، font) است. **PlatformUxService** در customer-site این را کش می‌کند (پیش‌فرض ۵ دقیقه).
4. ویوهای ورود و پنل ادمین سایت مشتری از **PlatformUx** (و partial **platform-styles**) استفاده می‌کنند و همان رنگ، فونت و لوگو اعمال می‌شود.
5. پس از **فعال/غیرفعال کردن لایسنس** در سایت مشتری، **PlatformUx::clearCache()** فراخوانی می‌شود تا در درخواست بعدی UX از API دوباره گرفته شود.

**جستجو:** `UX` ، `platform-styles` ، `PlatformUx` ، `theme` ، `font` ، `logo`

---

## ۹. لایسنس و سایت‌کانفیگ

- **صدور لایسنس:** در پلتفرم از بخش سایت یا لایسنس برای یک سایت و دامنه لایسنس صادر می‌شود. کلید فقط یک‌بار نمایش داده می‌شود.
- **فعال‌سازی در سایت مشتری:** در **ادمین → لایسنس** کلید و دامنه وارد می‌شود. **LicenseActivationService** با **POST /api/license/validate** اعتبارسنجی می‌کند و در صورت موفقیت رکورد **licenses** به‌روز می‌شود (شامل modules و support_expires_at).
- **همگام‌سازی دوره‌ای:** middleware **EnsureLicenseValid** هر چند دقیقه یک‌بار لایسنس را با پلتفرم چک می‌کند. **خطاهای موقت** (شبکه، timeout، پاسخ نامعتبر، HTTP غیرموفق بدون کد قطعی ابطال) نباید `platform_valid` را کاذباً `false` کنند؛ فقط پاسخ معتبر API با خطاهای قطعی مثل `revoked`، `expired`، `invalid_key`، `domain_mismatch`، `site_inactive` وضعیت «ابطال در پلتفرم» را در دیتابیس محلی منعکس می‌کنند.
- **دامنه:** مقایسهٔ دامنهٔ ذخیره‌شده با host درخواست با **نرمال‌سازی یکسان** (`License::normalizeDomain`) انجام می‌شود تا اختلاف حروف بزرگ/کوچک یا اسلش انتها باعث قطع دسترسی نشود.
- **سایت‌کانفیگ:** برای دریافت نام سایت، ماژول‌ها، انقضای پشتیبانی و **ux** از **GET /api/site-config** با همان license_key و domain استفاده می‌شود. پاسخ برای کش UX و نمایش در داشبورد استفاده می‌شود.

**جستجو:** `لایسنس` ، `license_key` ، `site-config` ، `LicenseActivationService`

---

## ۱۰. دستورات آرتیزان و کمندها

### customer-site

| دستور | توضیح |
|--------|--------|
| `php artisan user:set-mobile --email=... --mobile=0912...` | تنظیم موبایل کاربر برای ورود |
| `php artisan user:make-admin --email=...` یا `--id=...` یا `--mobile=...` | ادمین کردن کاربر برای دسترسی به پنل |
| `php artisan migrate` | اجرای مایگریشن‌ها (مثلاً اضافه شدن ستون mobile به users) |
| `php artisan test` | اجرای تست‌ها. نیاز به دیتابیس MySQL با نام `customer_site_test` دارد (یا فعال بودن pdo_sqlite برای SQLite درون‌حافظه). |
| `powershell -ExecutionPolicy Bypass -File scripts/test.ps1` | اجرای پایدار تست‌ها در ویندوز با تشخیص خودکار PHP (`PHP_BIN` یا Laragon) |
| `php scripts/ci-lint.php` | lint سراسری فایل‌های PHP پروژه برای CI |
| `composer test:ci` | اجرای `ci-lint` + `artisan test --stop-on-failure` |

**جستجو:** `artisan` ، `user:set-mobile` ، `user:make-admin` ، `test`

**تست‌ها:** در **phpunit.xml** برای تست‌ها از MySQL با دیتابیس `customer_site_test` استفاده می‌شود. قبل از اجرای تست‌ها دیتابیس را بسازید: `CREATE DATABASE customer_site_test;` (یا از phpMyAdmin). اگر pdo_sqlite در PHP فعال است، می‌توانید در phpunit.xml به `sqlite` و `:memory:` برگردانید. فایل `.env.testing.example` به‌عنوان الگوی محیط تست اضافه شده و در CI برای ساخت `.env.testing` استفاده می‌شود.

**CI:** فایل `.github/workflows/php-tests.yml` روی `push`/`pull_request` اجرا می‌شود، سرویس MySQL بالا می‌آورد، مایگریشن تست را اجرا می‌کند و سپس `composer test:ci` را اجرا می‌کند.

### saas-platform

- دستورات استاندارد Laravel (migrate، cache:clear و ...). ماژول‌ها و سایت‌ها از پنل ادمین مدیریت می‌شوند.

---

## ۱۱. قانون آپدیت راهنما

- این فایل (**docs/PROJECT-GUIDE.md**) در **هر دو** پروژه (saas-platform و customer-site) قرار دارد و باید **هم‌محتوای** یکدیگر باشند.
- **با هر تغییری** که به موارد زیر مربوط شود، این راهنما باید به‌روز شود:
  - اضافه/حذف مسیر (route) یا کنترلر مهم
  - تغییر یا اضافه شدن API (مسیر، پارامتر، پاسخ)
  - متغیرهای جدید .env یا config
  - جدول یا مایگریشن جدید با نقش مهم
  - قابلیت یا ماژول جدید
  - تغییر در جریان لایسنس، سایت‌کانفیگ یا UX

**جستجو:** `آپدیت` ، `PROJECT-GUIDE` ، `راهنما`

---

*آخرین به‌روزرسانی راهنما: ۱۴۰۵/۰۲/۱۹ — کپچای فارسی رنگی/آشفتهٔ بصری؛ فهرست تبلیغات با KPI و تاریخ شمسی؛ فرم‌ساز و منو و layout مطابق نسخهٔ قبلی.*
