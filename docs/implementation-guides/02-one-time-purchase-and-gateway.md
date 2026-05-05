# راهنمای بخش ۲: خرید یک‌بار (محصولات، سبد، درگاه، فاکتور)

## هدف

- تعریف **محصولات** در پلتفرم (لایسنس قالب، ماژول افزودنی، پلن پشتیبانی).
- **سبد خرید** و **تسویه** با درگاه زرین‌پال.
- پس از پرداخت موفق: **صدور لایسنس**، **الحاق ماژول**، یا **تمدید پشتیبانی** بر اساس نوع محصول.

---

## ۱. جداول و مدل‌ها (پلتفرم)

### مایگریشن‌ها

- `2026_02_26_160000_create_products_table.php`  
  - `sku`, `name_fa`, `name_en`, `type` (license_template | module_addon | support_plan), `price_irr`, `is_one_time`, `module_id`, `support_plan_id`, `is_active`, `description`.
- `2026_02_26_160001_create_invoices_table.php`  
  - `customer_id`, `amount_irr`, `status` (pending | paid | failed | refunded), `gateway`, `gateway_ref`, `paid_at`.
- `2026_02_26_160002_create_invoice_items_table.php`  
  - `invoice_id`, `product_id`, `quantity`, `price_irr`, `site_id`, `platform_license_id`.

اجرا:

```bash
cd /path/to/saas-platform
php artisan migrate
```

### مدل‌ها

- **Product:** ثابت‌های نوع (`TYPE_LICENSE_TEMPLATE`, `TYPE_MODULE_ADDON`, `TYPE_SUPPORT_PLAN`)، رابطه با `Module` و `SupportPlan`، متد `typeLabel()`.
- **Invoice:** رابطه با `Customer` و `InvoiceItem`، متد `isPaid()`.
- **InvoiceItem:** رابطه با `Invoice`, `Product`, `Site`, `PlatformLicense`.

---

## ۲. درگاه پرداخت (زرین‌پال)

### تنظیمات

در `config/services.php`:

```php
'zarinpal' => [
    'merchant_id' => env('ZARINPAL_MERCHANT_ID', ''),
    'sandbox' => filter_var(env('ZARINPAL_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
],
```

در `.env`:

```env
ZARINPAL_MERCHANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
ZARINPAL_SANDBOX=true
```

برای تست از محیط سندباکس زرین‌پال استفاده کنید.

### سرویس درگاه

- **Interface:** `App\Services\Payment\PaymentGatewayInterface`  
  - `request($amountRial, $callbackUrl, $description, $orderId)` → آرایه با `success`, `redirect_url` یا `error`.  
  - `verify($authority, $amountRial)` → آرایه با `verified`, `ref` یا `error`.
- **پیاده‌سازی:** `App\Services\Payment\ZarinpalGateway`  
  - درخواست پرداخت به WebGate و ریدایرکت به آدرس پرداخت.  
  - تأیید با `PaymentVerification` و برگرداندن `RefID`.

---

## ۳. سبد خرید و تسویه

### CartService

- **Session key:** `platform_cart`.
- آیتم‌ها: `product_id`, `quantity`, `site_id` (اختیاری برای ماژول/پشتیبانی).
- متدها: `add()`, `remove()`, `clear()`, `items()`, `details()` (به‌همراه مدل Product و جمع کل).

### روت‌ها (برای کاربر لاگین‌شده)

- `GET /store` — لیست محصولات و افزودن به سبد.
- `GET /store/cart` — نمایش سبد و فرم تسویه (انتخاب سایت یا ایجاد سایت جدید برای لایسنس).
- `POST /store/cart/add` — افزودن به سبد (برای ماژول/پشتیبانی انتخاب سایت الزامی است).
- `GET /store/cart/remove/{key}` — حذف آیتم از سبد.
- `POST /checkout` — ایجاد فاکتور، ایجاد آیتم‌های فاکتور، درخواست به درگاه و ریدایرکت.
- `GET /checkout/callback/{invoice}` — callback درگاه؛ تأیید پرداخت، به‌روزرسانی فاکتور، اجرای Fulfillment.

### کنترلرها

- **StoreController:** `index`, `cart`, `addToCart`, `removeFromCart`.
- **CheckoutController:** `process` (ایجاد فاکتور و ریدایرکت به درگاه)، `callback` (تأیید و fulfill).

در `process`: برای محصول نوع «لایسنس قالب» در صورت ارسال `new_site_title` یک سایت جدید برای مشتری ساخته می‌شود و به آیتم فاکتور همان سایت نسبت داده می‌شود.

---

## ۴. Fulfillment (پس از پرداخت)

**OrderFulfillmentService:**

- متد `fulfill(Invoice $invoice)` برای هر آیتم فاکتور بر اساس نوع محصول:
  - **license_template:** صدور لایسنس برای `site` آیتم از طریق `LicenseServerService::issue()` و ذخیره `platform_license_id` در آیتم.
  - **module_addon:** الحاق `product.module_id` به سایت با `syncWithoutDetaching` و `is_active => true`.
  - **support_plan:** تمدید `site.support_expires_at` بر اساس `duration_days` پلن و ثبت رکورد در `site_support_subscriptions`.

فقط برای فاکتورهای با وضعیت `paid` اجرا می‌شود.

---

## ۵. پنل ادمین — محصولات

- **روت‌ها:** `resource('products', AdminProductController::class)->except('show')`.
- **کنترلر:** `App\Http\Controllers\Admin\ProductController` — CRUD با انتخاب ماژول و پلن پشتیبانی در فرم.
- **ویوها:** `admin/products/index`, `create`, `edit`, `_form`.

ادمین از منوی پنل می‌تواند محصولات (لایسنس قالب، ماژول، پلن پشتیبانی) را تعریف و قیمت (ریال) و نوع را تنظیم کند.

---

## ۶. سناریوی استفاده

1. ادمین در پنل یک یا چند **محصول** (مثلاً لایسنس قالب، ماژول وبلاگ، پلن پشتیبانی یک‌ماهه) تعریف می‌کند.
2. مشتری (کاربر دارای پروفایل Customer) به `/store` می‌رود، محصول را به سبد اضافه می‌کند (برای ماژول/پشتیبانی سایت را انتخاب می‌کند).
3. در سبد خرید برای «لایسنس قالب» عنوان و دامنهٔ سایت جدید را وارد می‌کند (یا از قبل سایت دارد و در آینده می‌توان برای آن لایسنس خرید).
4. «پرداخت و انتقال به درگاه» را می‌زند؛ فاکتور ایجاد و به زرین‌پال ریدایرکت می‌شود.
5. پس از پرداخت، callback تأیید را انجام می‌دهد؛ فاکتور `paid` می‌شود و Fulfillment لایسنس صادر، ماژول به سایت الحاق، یا پشتیبانی تمدید می‌کند.

---

## ۷. نکات امنیتی و عملیاتی

- **Callback:** فقط با `Status=OK` و `Authority` پرداخت تأیید می‌شود؛ مبلغ با `amount_irr` فاکتور مقایسه می‌شود.
- **Idempotency:** در callback اگر فاکتور از قبل `paid` باشد، دوباره fulfill نمی‌شود و فقط پیام مناسب نمایش داده می‌شود.
- **محصولات ماژول/پشتیبانی:** باید `module_id` یا `support_plan_id` متناسب با نوع در فرم ادمین تنظیم شود تا Fulfillment درست کار کند.

---

## ۸. عیب‌یابی

- **خطای درگاه:** `ZARINPAL_MERCHANT_ID` و حالت سندباکس را بررسی کنید؛ در سندباکس از مرچنت تست استفاده شود.
- **فاکتور paid نشد:** لاگ‌های `Zarinpal verify` و پاسخ درگاه را چک کنید.
- **لایسنس صادر نشد:** مطمئن شوید برای آیتم نوع لایسنس قالب، `site_id` در آیتم فاکتور ست شده (با سایت جدید یا انتخاب شده).
