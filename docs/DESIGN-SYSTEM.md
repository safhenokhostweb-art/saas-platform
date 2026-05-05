# سیستم طراحی یکپارچه — پلتفرم و سایت مشتری

این سند توکن‌های بصری مشترک بین **saas-platform** و **customer-site** را تعریف می‌کند. مقادیر از **config/design.php** و **تنظیمات عمومی پلتفرم** می‌آیند؛ **DesignSystemService** آن‌ها را با overrideهای tenant ادغام و از طریق `platform-styles` به هر دو پروژه اعمال می‌کند.

---

## توکن‌های رنگ و فونت (CSS Variables)

متغیرهای `--ds-*` منبع حقیقت هستند؛ `--platform-*` برای سازگاری معکوس نگه داشته شده‌اند.

| توکن | متغیر CSS (جدید) | متغیر قدیمی | پیش‌فرض | کاربرد |
|------|------------------|---------------------|---------|--------|
| رنگ اصلی | `--ds-primary` | `--platform-primary` | `#4f46e5` | برند، لوگو، لینک اصلی |
| رنگ ثانویه | `--ds-secondary` | `--platform-secondary` | `#6366f1` | حالت hover، accent |
| پس‌زمینه منو | `--ds-menu-bg` | `--platform-menu-bg` | `#ffffff` | نوار بالا، هدر |
| متن منو | `--ds-menu-text` | `--platform-menu-text` | `#374151` | آیتم‌های منو |
| لینک فعال منو | `--ds-menu-link-active` | `--platform-menu-link-active` | `#2563eb` | آیتم فعال یا hover |
| رنگ عنوان | `--ds-title-color` | `--platform-title-color` | `#111827` | h1, h2, h3 |
| رنگ لینک | `--ds-link-color` | `--platform-link-color` | `#4f46e5` | لینک‌های محتوا |
| hover لینک | `--ds-link-hover` | `--platform-link-hover` | `#4338ca` | حالت hover لینک |
| پس‌زمینه سایدبار | `--ds-sidebar-bg` | `--platform-sidebar-bg` | `#ffffff` | پنل ادمین |
| متن سایدبار | `--ds-sidebar-text` | `--platform-sidebar-text` | `#374151` | آیتم‌های سایدبار |
| پس‌زمینه آیتم فعال سایدبار | `--ds-sidebar-active-bg` | `--platform-sidebar-active-bg` | `#eff6ff` | آیتم فعال |
| متن آیتم فعال سایدبار | `--ds-sidebar-active-text` | `--platform-sidebar-active-text` | `#2563eb` | متن آیتم فعال |
| پس‌زمینه دکمه اصلی | `--ds-button-primary-bg` | `--platform-button-primary-bg` | `#4f46e5` | دکمه‌های primary |
| متن دکمه اصلی | `--ds-button-primary-text` | `--platform-button-primary-text` | `#ffffff` | متن دکمه |
| فونت متن | `--ds-font-body` | `--platform-font-body` | Vazirmatn, Tahoma, sans-serif | body |
| فونت عنوان | `--ds-font-title` | `--platform-font-title` | Vazirmatn, Tahoma, sans-serif | h1–h6 |
| اندازه فونت پایه | `--ds-font-size-base` | `--platform-font-size-base` | 16px | body |
| ضریب اندازه عنوان | `--ds-font-size-title` | `--platform-font-size-title` | 1.25 | عنوان‌ها |

---

## قوانین استفاده

1. **رنگ‌ها:** در استایل‌های جدید از متغیرهای بالا استفاده شود تا با تم پلتفرم یکسان بمانند.
2. **فونت:** در هر دو پروژه فونت پیش‌فرض **Vazirmatn** است؛ در صورت تنظیم فونت سفارشی در پلتفرم، از طریق همان متغیرها اعمال می‌شود.
3. **فاصله (spacing):** یک واحد = ۴px؛ برای فاصلهٔ یکسان از مضارب (مثلاً 0.5rem = 8px، 1rem = 16px) استفاده شود.
4. **شعاع گوشه (border-radius):** دکمه و کارت: `0.5rem` (۸px).
5. **راست‌به‌چپ:** همهٔ layoutها `dir="rtl"` و `lang="fa"` دارند.

---

## محل استفاده

- **پالت‌ها:** `config/design.php` — پالت‌های default، dark، light؛ مقیاس تایپوگرافی؛ تعریف فونت.
- **پلتفرم:** `DesignSystemService::getCssVariables(null)` و `platform-styles.blade.php`.
- **سایت مشتری:** API site-config برمی‌گرداند `ux.css_variables` و `ux.font_imports`؛ `platform-styles` آن‌ها را رندر می‌کند.
- **Overrideهای tenant:** `site_settings` برای هر سایت؛ `DesignSystemService::resolveTheme($siteId)` آن‌ها را ادغام می‌کند.

---

## تصاویر و عملکرد

- **Lazy loading:** برای تصاویر محتوا (مثل تصویر شاخص مطلب یا تصویر محصول) از `loading="lazy"` استفاده شود تا بارگذاری اولیه سبک‌تر شود.
- **ابعاد:** در صورت امکان `width` و `height` روی تگ `img` قرار گیرد تا از layout shift جلوگیری شود.

---

## به‌روزرسانی

با تغییر در تنظیمات تم یا فونت در پلتفرم، مقادیر در جدول **settings** ذخیره و در درخواست بعدی سایت مشتری (با لایسنس معتبر) از API site-config برگردانده می‌شوند.
