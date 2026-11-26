# ساختار و راهنمای ورود گروهی دارایی‌های Canvas (Canvas Assets Import Schema)

این سند راهنمای استاندارد قالب داده‌های JSON برای ورود دسته‌ای دارایی‌های ویرایشگر Canvas، شامل چهار خانواده دارایی، قوانین نسخه‌بندی SemVer و تاریخچه تغییرات (Changelog) است.

---

## ۱. نحوه استفاده

### ۱.۱. از طریق خط فرمان (Artisan CLI)
```bash
php artisan canvas:import-assets /path/to/assets.json
# یا با آدرس نسبی از ریشه پروژه:
php artisan canvas:import-assets database/seeders/fixtures/canvas_sample_assets.json
```

### ۱.۲. از طریق پنل مدیریت پلتفرم (SaaS Admin)
1. وارد پنل ادمین پلتفرم شوید.
2. به بخش **مدیریت دارایی‌های Canvas** (`/admin/canvas-assets`) بروید.
3. روی دکمه **ورود گروهی با JSON** کلیک کنید.
4. فایل `.json` را بارگذاری کنید یا متن JSON را مستقیماً در کادر مربوطه جای‌گذاری کرده و روی «شروع پردازش و ورود اطلاعات» کلیک نمایید.

---

## ۲. مشخصات ساختار JSON (Schema)

فایل ورودی می‌تواند شامل یک، چند یا هر ۴ کلید اصلی زیر باشد:

```json
{
  "button_packs": [ ... ],
  "icon_packs": [ ... ],
  "header_presets": [ ... ],
  "motion_graphics": [ ... ]
}
```

---

### ۲.۱. کتابخانه دکمه‌ها (`button_packs`)
هر عضو آرایه بیانگر یک «پکیج دکمه» با سبک مشترک است که می‌تواند شامل چندین «پریست» استایل باشد:

| فیلد | نوع | الزامی؟ | توضیحات |
|---|---|---|---|
| `slug` | رشته | بله | شناسه یکتا (مثال: `glassmorphism-buttons`) |
| `title` | رشته | بله | عنوان فارسی پکیج (مثال: پکیج دکمه‌های شیشه‌ای) |
| `description` | رشته | خیر | توضیحات پکیج |
| `category_label` | رشته | خیر | دسته‌بندی موضوعی (فانتزی، شیشه‌ای، کلاسیک، مدرن) |
| `preview_image` | رشته | خیر | آدرس تصویر پیش‌نمایش |
| `access_type` | رشته | خیر | نوع دسترسی: `free` یا `paid` (پیش‌فرض: `free`) |
| `price` | عدد | خیر | قیمت به ریال/تومان در صورت پولی بودن |
| `discount_price` | عدد | خیر | قیمت با تخفیف |
| `version` | رشته | خیر | نگارش SemVer پکیج (پیش‌فرض: `1.0.0`) |
| `changelog` | رشته | خیر | توضیح تغییرات این نگارش |
| `is_active` | بولین | خیر | فعال بودن در مارکت‌پلیس (پیش‌فرض: `true`) |
| `sort_order` | عدد | خیر | ترتیب نمایش (پیش‌فرض: `0`) |
| `presets` | آرایه | خیر | لیست پریست‌های داخل این پکیج |

#### فیلدهای داخل هر پریست (`presets`):
- `slug`: شناسه یکتا در پکیج (مثال: `glass-primary`)
- `title`: عنوان پریست (مثال: دکمه شیشه‌ای اصلی)
- `css_tokens`: آبجکت توکن‌های طراحی شامل `btn_bg`, `btn_text`, `btn_border`, `btn_radius`, `btn_shadow`, `btn_hover_bg`, `btn_hover_text`, `btn_hover_shadow`, `btn_active_scale`
- `preview_html`: کد HTML برای رندر زنده کارت پیش‌نمایش
- `sort_order`: ترتیب نمایش داخل پکیج

---

### ۲.۲. کتابخانه آیکن‌ها (`icon_packs`)
هر عضو آرایه بیانگر یک «پکیج آیکن SVG» است:

| فیلد | نوع | الزامی؟ | توضیحات |
|---|---|---|---|
| `slug` | رشته | بله | شناسه یکتا (مثال: `duotone-business`) |
| `title` | رشته | بله | عنوان فارسی پکیج |
| `description` | رشته | خیر | توضیحات پکیج |
| `category_label` | رشته | خیر | دسته‌بندی موضوعی |
| `access_type` | رشته | خیر | `free` یا `paid` |
| `price` | عدد | خیر | قیمت دارایی |
| `version` | رشته | خیر | نگارش SemVer |
| `changelog` | رشته | خیر | تاریخچه تغییرات |
| `items` | آرایه | خیر | لیست آیکن‌های داخل این پکیج |

#### فیلدهای داخل هر آیکن (`items`):
- `slug`: شناسه یکتای آیکن در این پکیج (مثال: `chart-growth`)
- `title`: نام آیکن (مثال: نمودار رشد کسب‌وکار)
- `keywords`: آرایه‌ای از کلمات کلیدی فارسی و انگلیسی جهت جستجو در انتخابگر Canvas
- `svg_content`: کدهای خام استاندارد `<svg ...>...</svg>`
- `sort_order`: ترتیب نمایش

---

### ۲.۳. قالب‌های آماده هدر (`header_presets`)
الگوهای آماده سربرگ سایت برای جای‌گذاری سریع در Canvas:

| فیلد | نوع | الزامی؟ | توضیحات |
|---|---|---|---|
| `slug` | رشته | بله | شناسه یکتا (مثال: `header-luxury-center`) |
| `title` | رشته | بله | عنوان فارسی قالب هدر |
| `category_label` | رشته | خیر | دسته‌بندی (مثال: لوکس، مینیمال، شرکتی) |
| `component_alias` | رشته | بله | نام کامپوننت Blade متناظر در رندرر |
| `preview_image` | رشته | خیر | تصویر پیش‌نمایش هدر |
| `access_type` | رشته | خیر | `free` یا `paid` |
| `price` | عدد | خیر | قیمت |
| `version` | رشته | خیر | نگارش SemVer |
| `changelog` | رشته | خیر | تاریخچه تغییرات |

---

### ۲.۴. موشن گرافیک‌ها (`motion_graphics`)
انیمیشن‌ها و ویجت‌های تعاملی متحرک:

| فیلد | نوع | الزامی؟ | توضیحات |
|---|---|---|---|
| `slug` | رشته | بله | شناسه موشن (مثال: `hero-tech-waves`) |
| `name` یا `title` | رشته | بله | نام نمایشی موشن |
| `description` | رشته | خیر | توضیحات کارکرد |
| `category` | رشته | خیر | دسته‌بندی (فناوری، انتزاعی، لودینگ) |
| `access_type` | رشته | خیر | `free` یا `paid` |
| `price` | عدد | خیر | قیمت انفرادی موشن |
| `version` | رشته | خیر | نگارش SemVer |
| `changelog` | رشته | خیر | تاریخچه تغییرات |

---

## ۳. نمونه فایل معتبر JSON (`canvas_sample_assets.json`)

```json
{
  "button_packs": [
    {
      "slug": "modern-gradients",
      "title": "پکیج گرادیانت‌های مدرن",
      "category_label": "مدرن",
      "access_type": "free",
      "price": 0,
      "version": "1.0.0",
      "changelog": "انتشار اولیه پکیج گرادیانت مدرن",
      "presets": [
        {
          "slug": "sunset-glow",
          "title": "درخشش غروب (Sunset Glow)",
          "css_tokens": {
            "btn_bg": "linear-gradient(135deg, #f97316, #ec4899)",
            "btn_text": "#ffffff",
            "btn_radius": "12px",
            "btn_shadow": "0 4px 14px 0 rgba(249, 115, 22, 0.39)"
          },
          "preview_html": "<button style=\"background:linear-gradient(135deg, #f97316, #ec4899);color:#fff;border-radius:12px;padding:8px 16px;box-shadow:0 4px 14px rgba(249,115,22,0.39);border:none;\">پیش‌نمایش</button>"
        }
      ]
    }
  ],
  "icon_packs": [
    {
      "slug": "finance-duotone",
      "title": "آیکن‌های مالی و بانکی",
      "category_label": "کسب‌وکار",
      "access_type": "free",
      "version": "1.0.0",
      "changelog": "مجموعه اولیه آیکن‌های مالی",
      "items": [
        {
          "slug": "wallet",
          "title": "کیف پول",
          "keywords": ["کیف پول", "مالی", "wallet"],
          "svg_content": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4\"/><path d=\"M4 6v12c0 1.1.9 2 2 2h14v-4\"/><circle cx=\"18\" cy=\"14\" r=\"1\"/></svg>"
        }
      ]
    }
  ]
}
```
