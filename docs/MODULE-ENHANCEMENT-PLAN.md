# Module Enhancement Plan — Production-Grade SaaS Quality

> **هدف:** ارتقای همهٔ ماژول‌های قابل اختصاص به tenant به کیفیت تجاری و قابل عرضه در SaaS.
>
> **قوانین:** بدون CRUD ساده، بدون بازسازی معماری موجود، فقط تکمیل و ارتقا.

---

## معیارهای تکمیل ماژول (Module Quality Standard)

هر ماژول باید شامل باشد:

1. **End-user functionality** — قابلیت برای کاربر نهایی
2. **Admin management** — مدیریت در پنل ادمین
3. **Tenant configuration** — تنظیمات سطح tenant
4. **Permissions integration** — یکپارچگی با سیستم دسترسی
5. **UI integration** — یکپارچگی با منو و layout
6. **Error handling** — مدیریت خطا و validation
7. **Documentation comments** — توضیحات در کد

---

## وضعیت فعلی و اولویت‌بندی

### Tier 1: کامل (فقط ارتقاهای جزئی)

| ماژول | وضعیت | ارتقاهای پیشنهادی |
|-------|-------|-------------------|
| **blog** | کامل | افزودن SEO meta per-post، schema.org، sitemap بهبود |
| **shop** | کامل | کوپن در checkout، گزارش فروش، export سفارشات |
| **captcha** | کامل | تست captcha در صفحه تنظیمات |
| **module_labels** | کامل (internal) | بدون تغییر |
| **payment_gateways** | کامل (platform) | بدون تغییر |

### Tier 2: تقریباً کامل (نیاز به تکمیل)

| ماژول | موجود | کمبود | ارتقا |
|-------|-------|-------|-------|
| **subscription** | CRUD پلن، views | Frontend ثبت‌نام، پرداخت، مدیریت اشتراک کاربر | صفحهٔ انتخاب پلن، checkout اشتراک، پنل کاربر |
| **forms** | Platform مدیریت، frontend ارسال | Tenant preview، analytics | گزارش ارسال‌ها در tenant، export |
| **advertisements** | CRUD، zone، widget | آمار کلیک، A/B، زمان‌بندی پیشرفته | آمار نمایش/کلیک، فیلتر تاریخ |
| **currency_reform** | Toggle، helper | راهنمای بهتر، تست نمایش | راهنمای ماژول، تست نمایش قیمت |
| **invoice** | CRUD، pay، frontend | پرداخت آنلاین، وضعیت‌های بیشتر | اتصال به درگاه، وضعیت paid/pending |
| **messaging/whatsapp** | کانال‌ها، ویجت | تنظیمات tenant، آمار | Tenant config، آمار پیام |

### Tier 3: ناقص (نیاز به تکمیل قابل توجه)

| ماژول | موجود | کمبود | ارتقا |
|-------|-------|-------|-------|
| **coupon** | CRUD، views | اتصال به checkout، حداقل خرید، محصول خاص | اعمال در shop checkout، شرایط پیشرفته |
| **referral** | Index read-only | ایجاد کد، مدیریت، frontend | CRUD کد معرفی، لینک در frontend |
| **reviews** | Index، approve/delete | ایجاد از frontend، پاسخ، امتیاز | فرم نظر frontend، پاسخ ادمین، امتیاز ستاره |
| **business_card** | CRUD، نمایش | QR، اشتراک، آمار | QR code، آمار بازدید، اشتراک |
| **social_links** | CRUD، footer | ترتیب، آیکن سفارشی | Drag reorder، آیکن سفارشی |
| **courses** | CRUD، models | Frontend، ثبت‌نام، ویدیو | صفحه دوره‌ها، ثبت‌نام، پیشرفت |
| **advanced_search** | Frontend جستجو | Admin تنظیمات، فیلترها | Admin config، فیلتر پیشرفته |
| **sms** | تنظیمات | ارسال واقعی، قالب | اتصال سرویس SMS، قالب پیام |

---

## طرح ارتقا به تفکیک ماژول

### ۱. اشتراک (subscription)

**قابلیت‌های تجاری کامل:**
- پلن‌های اشتراک با قیمت، مدت، ویژگی‌ها
- صفحهٔ انتخاب پلن برای کاربر (frontend)
- checkout و پرداخت اشتراک
- مدیریت اشتراک فعال کاربر
- تمدید خودکار (اختیاری)
- لغو اشتراک

**موجود:** CRUD پلن در ادمین، مدل Subscription.

**کمبود:**
- [ ] صفحهٔ frontend انتخاب پلن
- [ ] checkout اشتراک (اتصال به shop/درگاه)
- [ ] پنل کاربر: اشتراک من
- [ ] Validation پیشرفته (تاریخ، تداخل)
- [ ] راهنمای ماژول

---

### ۲. پیام / واتساپ (messaging)

**قابلیت‌های تجاری کامل:**
- چند کانال (واتساپ، تلگرام، …)
- ویجت شناور با تنظیمات
- پیام پیش‌فرض
- آمار کلیک (اختیاری)

**موجود:** MessagingChannel، ویجت، CRUD کانال.

**کمبود:**
- [ ] Tenant-level config (مثلاً موقعیت ویجت)
- [ ] آمار کلیک روی لینک
- [ ] Validation و error handling بهتر
- [ ] راهنمای ماژول

---

### ۳. جستجوی پیشرفته (advanced_search)

**قابلیت‌های تجاری کامل:**
- جستجو در مطالب، محصولات، صفحات
- پیشنهاد خودکار (autocomplete)
- فیلتر نوع نتیجه
- SEO-friendly URL

**موجود:** AdvancedSearchController، view جستجو، API suggest/results.

**کمبود:**
- [ ] Admin: تنظیم فیلدهای جستجو
- [ ] Admin: فعال/غیرفعال کردن نوع محتوا
- [ ] Permissions
- [ ] راهنمای ماژول

---

### ۴. درگاه بانکی (payment_gateways)

**وضعیت:** کامل در پلتفرم. Tenant از درگاه سایت استفاده می‌کند.

**ارتقا:**
- [ ] نمایش درگاه اختصاص‌یافته در tenant (read-only) در تنظیمات
- [ ] راهنمای واضح‌تر

---

### ۵. عنوان و جزئیات ماژول‌ها (module_labels)

**وضعیت:** کامل (internal).

**ارتقا:** بدون تغییر ضروری.

---

### ۶. فرم‌ها (forms)

**قابلیت‌های تجاری کامل:**
- فرم‌ساز در پلتفرم
- اختصاص به سایت‌ها
- ارسال از frontend با captcha
- گزارش ارسال‌ها

**موجود:** FormController، FormFrontendController، form_site، submissions.

**کمبود:**
- [ ] Tenant: مشاهدهٔ خلاصه ارسال‌های سایت خود (read-only یا با مجوز)
- [ ] Export CSV با فیلتر تاریخ
- [ ] Validation پیشرفته فیلدها
- [ ] راهنمای ماژول

---

### ۷. کارت ویزیت دیجیتال (business_card)

**قابلیت‌های تجاری کامل:**
- کارت با نام، تلفن، ایمیل، شبکه‌ها
- لینک اختصاصی
- QR code
- آمار بازدید

**موجود:** BusinessCard model، CRUD، نمایش frontend.

**کمبود:**
- [ ] QR code برای لینک
- [ ] آمار بازدید (view count)
- [ ] اشتراک‌گذاری (share)
- [ ] Validation و sanitization
- [ ] راهنمای ماژول

---

### ۸. کوپن تخفیف (coupon)

**قابلیت‌های تجاری کامل:**
- کد تخفیف درصدی یا مبلغ ثابت
- حداقل خرید
- محدودیت تعداد استفاده
- انقضا
- اعمال در checkout

**موجود:** Coupon model، CRUD، validation پایه.

**انجام‌شده:**
- [x] اتصال به ShopController checkout
- [x] حداقل خرید (min_order_amount)
- [x] پیام خطا واضح در checkout
- [x] راهنمای ماژول
- [x] نمایش کوپن در جزئیات سفارش

**کمبود:**
- [ ] محصول/دسته خاص (اختیاری)

---

### ۹. معرفی دوستان (referral)

**قابلیت‌های تجاری کامل:**
- کد معرفی برای کاربران
- ردیابی معرفی‌ها
- پاداش (اختیاری)
- لینک در frontend

**موجود:** ReferralCode، Referral، index read-only.

**کمبود:**
- [ ] ایجاد/ویرایش کد از ادمین
- [ ] فعال/غیرفعال کردن کد
- [ ] لینک معرفی در پروفایل/داشبورد کاربر
- [ ] Validation
- [ ] راهنمای ماژول

---

### ۱۰. تبلیغات (advertisements)

**قابلیت‌های تجاری کامل:**
- بنر با تصویر، لینک، موقعیت
- زمان‌بندی نمایش
- zone‌های مختلف
- آمار کلیک (اختیاری)

**موجود:** Advertisement CRUD، advertisement-zone، starts_at/expires_at.

**کمبود:**
- [ ] آمار نمایش (impression) و کلیک
- [ ] فیلتر پیشرفته در لیست
- [ ] Validation تصویر و لینک
- [ ] راهنمای ماژول

---

### ۱۱. حذف ۴ صفر پول ملی (currency_reform)

**موجود:** Toggle، CurrencyHelper، راهنما.

**کمبود:**
- [ ] راهنمای ماژول (module-guide-box)
- [ ] تست نمایش قیمت در همان صفحه
- [ ] دکمهٔ «پیش‌نمایش» با نمونه

---

### ۱۲. دوره‌ها (courses)

**قابلیت‌های تجاری کامل:**
- دوره با عنوان، توضیح، تصویر
- کلاس‌ها/جلسات
- ثبت‌نام
- پیشرفت

**موجود:** Course، CourseClass، CourseEnrollment، CRUD.

**کمبود:**
- [ ] Frontend: لیست دوره‌ها
- [ ] Frontend: صفحه دوره و ثبت‌نام
- [ ] مدیریت ثبت‌نام‌ها در ادمین
- [ ] Validation
- [ ] راهنمای ماژول

---

### ۱۳. فاکتور آنلاین (invoice)

**قابلیت‌های تجاری کامل:**
- فاکتور با آیتم‌ها
- پرداخت آنلاین
- وضعیت‌های paid/pending/cancelled
- نمایش در frontend

**موجود:** Invoice، InvoiceItem، CRUD، pay، frontend show.

**کمبود:**
- [ ] اتصال به درگاه پرداخت
- [ ] وضعیت‌های بیشتر و workflow
- [ ] PDF/چاپ
- [ ] Validation
- [ ] راهنمای ماژول

---

### ۱۴. فروشگاه (shop)

**وضعیت:** کامل.

**ارتقا:**
- [ ] اعمال کوپن در checkout
- [ ] گزارش فروش (تاریخ، مجموع)
- [ ] Export سفارشات CSV
- [ ] SEO محصولات (meta، schema)

---

### ۱۵. تنظیمات کپچا (captcha)

**وضعیت:** کامل.

**ارتقا:**
- [ ] دکمهٔ «تست captcha» در صفحه تنظیمات سایت

---

### ۱۶. لینک‌های شبکه‌های اجتماعی (social_links)

**موجود:** SocialLink CRUD، footer widget.

**کمبود:**
- [ ] ترتیب نمایش (order)
- [ ] آیکن سفارشی یا انتخاب از لیست
- [ ] Validation URL
- [ ] راهنمای ماژول

---

### ۱۷. نظرات و امتیازدهی (reviews)

**قابلیت‌های تجاری کامل:**
- نظر برای محصول/مطلب
- امتیاز ستاره
- تایید/رد
- پاسخ ادمین

**موجود:** Review model، index، approve/delete.

**کمبود:**
- [ ] فرم ارسال نظر در frontend (محصول/مطلب)
- [ ] امتیاز ستاره
- [ ] پاسخ ادمین
- [ ] Validation
- [ ] راهنمای ماژول

---

### ۱۸. وبلاگ (blog)

**وضعیت:** کامل.

**ارتقا:**
- [ ] SEO meta per-post
- [ ] Schema.org Article
- [ ] Sitemap بهبود

---

## ترتیب پیاده‌سازی پیشنهادی

| Phase | ماژول‌ها | دلیل |
|-------|----------|------|
| **1** | coupon، referral، reviews | اتصال به shop و UX؛ تاثیر بالا |
| **2** | business_card، social_links | تکمیل سریع |
| **3** | subscription، invoice | پرداخت و اشتراک |
| **4** | courses، advanced_search | قابلیت محتوا |
| **5** | advertisements، messaging، currency_reform، forms | تنظیمات و آمار |
| **6** | blog، shop، captcha | ارتقاهای جزئی |

---

## چک‌لیست هر ماژول قبل از اتمام

- [ ] End-user functionality
- [ ] Admin management
- [ ] Tenant configuration
- [ ] Permissions integration
- [ ] UI integration (منو، RTL)
- [ ] Error handling و validation
- [ ] Documentation comments
- [ ] راهنمای ماژول (module-guide-box)
- [ ] تست دستی
