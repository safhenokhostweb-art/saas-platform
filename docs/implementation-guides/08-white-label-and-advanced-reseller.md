# راهنمای بخش ۸: White-label و امکانات پیشرفته Reseller

## هدف

- امکان **White-label** برای Reseller: دامنه یا زیردامنهٔ اختصاصی، لوگو و نام برند خود.
- امکانات پیشرفتهٔ نمایندگی (اختیاری): تخفیف عمده، گزارش‌گیری، درخواست تسویه.

---

## ۱. White-label — مفاهیم

- **Reseller** با پرچم `is_white_label = true` می‌تواند پنل/فروشگاه را با ظاهر و برند خود به مشتریانش نشان دهد.
- سناریوها:
  - **زیردامنه:** مثلاً `reseller1.platform.com` که همان اپ پلتفرم است ولی تم و عنوان از تنظیمات آن Reseller بارگذاری می‌شود.
  - **دامنهٔ اختصاصی:** Reseller دامنهٔ خود (مثلاً `shop.reseller.ir`) را به همان اپ اشاره می‌دهد؛ بر اساس دامنه، Reseller تشخیص داده شده و تم/برند او اعمال می‌شود.

---

## ۲. پیاده‌سازی پایه

### جدول `resellers` ✓

- فیلد `is_white_label` (boolean) از قبل در مایگریشن وجود دارد.
- فیلدهای اضافه ✓ (مایگریشن `add_white_label_fields_to_resellers_table`):
  - `brand_name` — نام برند
  - `logo_path` — مسیر لوگو در storage
  - `custom_domain` — دامنهٔ اختصاصی (nullable)
  - `subdomain` — زیردامنه (مثلاً `reseller1`) اگر از زیردامنه استفاده شود

### تشخیص Reseller از درخواست ✓

- **میدلور `DetectResellerFromDomain`** و **سرویس `ResellerDetectionService`:** با توجه به `request()->getHost()`:
  - اگر با `custom_domain` یکی از Resellerها مطابقت داشت، آن Reseller در request attributes قرار می‌گیرد.
  - یا اگر هاست از الگوی `{subdomain}.platform.com` بود و `subdomain` در جدول resellers بود، Reseller مشخص می‌شود.

### اعمال برند در ویوها ✓

- **`ResellerBranding::platformName()`** و **`ResellerBranding::logoUrl()`** در navbar و layout:
  - اگر Reseller از دامنه تشخیص داده شد و `is_white_label` بود، عنوان و لوگو از Reseller؛ وگرنه از پلتفرم.

---

## ۳. امکانات پیشرفته Reseller

### داشبورد Reseller

- لیست مشتریان (`customers` با `reseller_id`).
- جمع فروش و لیست کمیسیون‌های pending/paid (راهنمای بخش ۶).

### درخواست تسویه

- Reseller دکمهٔ «درخواست تسویه» بزند؛ رکوردی در جدول `payout_requests` (اختیاری) با جمع کمیسیون‌های pending ایجاد شود.
- ادمین در پنل لیست درخواست‌ها را ببیند، پس از واریز، کمیسیون‌های مربوط را به وضعیت `paid` و `paid_at` به‌روز کند.

### قیمت عمده (اختیاری)

- در جدول `resellers` فیلد `wholesale_discount_percent` یا لیست قیمت ویژه برای محصولات؛ در صفحهٔ فروشگاه وقتی از دامنه/ورود Reseller تشخیص داده شد، قیمت با تخفیف نمایش داده شود و در فاکتور ثبت شود؛ کمیسیون بر اساس همان مبلغ نهایی یا مبلغ پایه طبق سیاست محاسبه شود.

---

## ۴. خلاصه

- White-label با تشخیص Reseller از دامنه/زیردامنه و اعمال `brand_name` و `logo_path` در ویوها قابل پیاده‌سازی است.
- امکانات پیشرفته با داشبورد Reseller، جدول کمیسیون و درخواست تسویه در بخش ۶ پایه‌گذاری شده‌اند و با فاز بعدی (درخواست تسویه و پرداخت توسط ادمین) تکمیل می‌شوند.
