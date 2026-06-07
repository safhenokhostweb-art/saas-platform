# HANDOVER — Subscription Module
> فایل اختصاصی ماژول Subscription.
> اگر فقط یک چت باز است: این فایل + HANDOVER.md را بخوان.
> اگر چند چت همزمان باز است: فقط این فایل را بخوان.
> Last Updated: 2026-06-06

---

## وضعیت فعلی

**ماژول:** Subscription (در customer-site)
**وضعیت:** ساختار اصلی کامل — payment gateway وصل نیست
**اولویت بعدی:** وصل کردن درگاه پرداخت واقعی به confirm()

---

## آنچه کاملاً ساخته شده ✅

| بخش | توضیح |
|-----|-------|
| `Subscription` model | ۵ status، helpers، relationships |
| `SubscriptionPlan` model | قیمت ویژه، trial، benefits، تخفیف محصول، pivot به products |
| `SubscriptionLog` model | تمام actions لاگ می‌شوند |
| `SubscriptionService` | subscribe، cancel، renew، expire، createInvoice + SMS |
| `Account/SubscriptionController` | plans، subscribe (با کوپن)، cancel، deletePending |
| `Admin/SubscriptionDashboardController` | آمار کامل، درآمد ماه‌ها، چارت ۳۰روزه، top plans |
| `Admin/SubscriptionPlanController` | CRUD کامل برای پلن‌ها |
| `Front/SubscriptionPaymentController` | pay، confirm (فعلاً manual)، activate |
| DB Schema | ۶ migration، product-scope، coupon integration |
| `Coupon` integration | در subscribe() کوپن validate و discount محاسبه می‌شود |
| `ExpireSubscriptions` Command | منقضی کردن خودکار اشتراک‌ها |
| Views (account) | index، plans، show |
| Views (admin) | dashboard، index، create، edit، show، subscribers، pending-requests |
| Routes | account + admin + front همه وایر |

---

## مشکلات فعلی (به‌ترتیب اولویت)

### ✅ COMPLETED — Membership Benefits Engine (2026-06-06)

**فایل‌های جدید / تغییر یافته:**
- `app/Dto/MembershipDiscountResult.php` — NEW DTO
- `app/Services/MembershipBenefitService.php` — NEW Service (singleton)
- `database/migrations/2026_06_06_100000_add_membership_discount_to_orders_table.php` — NEW (migrated)
- `app/Models/Order.php` — membership_discount_amount + membership_plan_name اضافه شد
- `app/Http/Controllers/Front/ShopController.php` — cart() + checkout() آپدیت شد
- `app/Providers/AppServiceProvider.php` — MembershipBenefitService singleton register شد
- `resources/views/themes/default/shop/cart.blade.php` — طراحی کامل حرفه‌ای دو‌ستونی
- `resources/views/partials/shop-captcha.blade.php` — static call bug fixed
- `database/factories/UserFactory.php` — email حذف، mobile اضافه شد (PHP 8.4 fix)
- `database/migrations/2026_06_04_100000_create_push_subscriptions_table.php` — defensive check
- `packages/.../tickets/.../rename_customer_email` migration — defensive check
- `tests/Feature/MembershipBenefitServiceTest.php` — NEW (11 tests ✅)
- `tests/Feature/MembershipCheckoutTest.php` — NEW (7 tests ✅)

**نتیجه:** 18/18 tests pass | 48 assertions | discount واقعی در checkout اعمال می‌شود

---

### ✅ COMPLETED — Points & Cashback Engine (Faz 2 — 2026-06-06)

**فایل‌های جدید / تغییر یافته:**
- `database/migrations/2026_06_06_200000_create_membership_points_table.php` — NEW ✅ migrated
- `database/migrations/2026_06_06_200001_add_points_columns_to_orders_table.php` — NEW ✅ migrated
- `database/migrations/2026_06_06_200002_change_loyalty_multiplier_to_decimal.php` — NEW ✅ migrated
- `app/Models/MembershipPoint.php` — NEW (TYPE/SOURCE constants, getTypeLabel, getSourceLabel)
- `app/Services/MembershipPointsService.php` — NEW singleton (earn/spend/balance/history)
- `app/Models/Order.php` — points_discount_amount + points_spent + points_earned
- `app/Models/SubscriptionPlan.php` — loyalty_multiplier cast: float
- `app/Http/Controllers/Front/ShopController.php` — points spending + earning in checkout
- `app/Services/SubscriptionService.php` — earn points on subscribe() + renew()
- `resources/views/themes/default/shop/cart.blade.php` — points redemption slider UI
- `app/Http/Controllers/Account/PointsController.php` — NEW
- `resources/views/account/points/index.blade.php` — NEW professional view
- `app/Http/Controllers/Account/DashboardController.php` — pointsBalance added
- `resources/views/account/dashboard.blade.php` — امتیاز tile اضافه شد
- `routes/web.php` — account.points.index route
- `app/Providers/AppServiceProvider.php` — MembershipPointsService singleton
- `tests/Feature/MembershipPointsServiceTest.php` — NEW (13 tests ✅)
- `tests/Feature/PointsCheckoutIntegrationTest.php` — NEW (7 tests ✅)

**نتیجه:** 38/38 tests pass | 79 assertions | Points engine کاملاً کار می‌کند

**Earn rates:**
- خرید: `floor(subtotal / 1000) × loyalty_multiplier`
- Subscribe: 500 امتیاز ثابت
- Renew: 300 امتیاز ثابت
- انقضا: 365 روز

**Redemption:** 1000 امتیاز = 10,000 تومان | حداکثر 20% سفارش

---

### ✅ COMPLETED — Payment Gateway Integration (P1 — 2026-06-06)

**فایل‌های جدید:**
- `config/payment.php` — PAYMENT_GATEWAY, PAYMENT_SANDBOX, zarinpal, idpay config
- `app/Services/Payment/PaymentGatewayInterface.php` — contract (request/verify/name)
- `app/Services/Payment/PaymentResult.php` — DTO (success, refId, cardPan, message)
- `app/Services/Payment/GatewayException.php` — domain exception
- `app/Services/Payment/ZarinpalGateway.php` — Zarinpal v4 API (request + verify)
- `app/Services/Payment/IdpayGateway.php` — IDPay v1.1 API (request + verify)
- `app/Services/Payment/PaymentGatewayFactory.php` — env-driven selector + isConfigured()
- `tests/Feature/SubscriptionPaymentGatewayTest.php` — 18 tests (18/18 pass, mocked HTTP)

**فایل‌های تغییریافته:**
- `app/Http/Controllers/Front/SubscriptionPaymentController.php` — pay() → gateway redirect | callback() → verify + activate | confirm() → blocked if gateway configured
- `resources/views/front/subscriptions/pay.blade.php` — gateway-aware CTA (آنلاین vs دستی)
- `routes/web.php` — `GET /subscription/callback/{invoiceNumber}` → subscriptions.callback
- `.env.example` — PAYMENT_GATEWAY, PAYMENT_SANDBOX, ZARINPAL_MERCHANT_ID, IDPAY_API_KEY

**نتیجه:** 18/18 tests pass | 32 assertions | تمام HTTP calls مکارانه mock شدند

**Flow جدید:**
```
pay() → isConfigured? YES → request() → redirect به درگاه → callback() → verify() → activateSubscription()
pay() → isConfigured? NO  → manual page → confirm() → activateSubscription()
```

**تنظیم:**
```env
PAYMENT_GATEWAY=zarinpal   # یا idpay
ZARINPAL_MERCHANT_ID=xxxx  # اگر zarinpal
IDPAY_API_KEY=xxxx         # اگر idpay
PAYMENT_SANDBOX=false      # true برای تست
```

---

### ✅ COMPLETED — Retention Engine (Faz 3 — 2026-06-06)

**فایل‌های جدید:** `SendSubscriptionRemindersCommand`, `ProcessBirthdayGiftsCommand`, migration `reminder_*_sent_at`, SubscriptionLog constants, scheduler entries, 14 tests (52/52 total)
**نتیجه:** SMS ۷روزه + ۱روزه + کوپن ۱۰٪ + هدیه تولد ۲۰۰ امتیاز | idempotent | 52/52 pass

---

### ✅ COMPLETED — Growth Engine (Faz 4 — 2026-06-06)

**فایل‌های جدید / تغییر یافته:**
- `database/migrations/2026_06_06_300000_add_reward_fields_to_referrals_table.php` — NEW ✅ migrated (`rewarded_at`, `points_awarded`)
- `app/Models/Referral.php` — STATUS_PENDING + STATUS_REWARDED + casts آپدیت
- `app/Services/ReferralService.php` — NEW singleton (getOrCreateCode, registerReferral, rewardReferral, getStats, buildLink)
- `app/Services/MembershipPointsService.php` — `earnFromReferral()` اضافه شد
- `app/Http/Controllers/Auth/RegisterController.php` — ref_code field + referral registration
- `app/Services/SubscriptionService.php` — `rewardReferral()` hook بعد از subscribe()
- `resources/views/auth/register.blade.php` — ref_code hidden field + badge تأیید کد
- `app/Http/Controllers/Account/ReferralController.php` — NEW
- `resources/views/account/referrals/index.blade.php` — NEW (hero, stats cards, link+copy, QR, راهنما)
- `app/Http/Controllers/Front/PricingController.php` — NEW (public, no auth required)
- `resources/views/front/pricing/index.blade.php` — NEW (plan cards, badges, FAQ accordion, login-aware CTA)
- `app/Http/Controllers/Admin/SubscriptionDashboardController.php` — MRR + Churn rate computation
- `resources/views/admin/subscriptions/dashboard.blade.php` — MRR card + Churn card (با trend indicator)
- `routes/web.php` — account.referrals.index + pricing.index routes
- `tests/Feature/ReferralServiceTest.php` — NEW (15 tests ✅)
- `tests/Feature/PublicPlansTest.php` — NEW (9 tests ✅)

**نتیجه:** 24/24 tests pass | 43 assertions

**پاداش referral:**
- دعوت‌کننده: ۳۰۰ امتیاز
- دعوت‌شده: ۱۰۰ امتیاز
- شرط: اولین اشتراک پرداخت شده | idempotent

**MRR:** نرمال‌سازی ۳۰روزه از تمام اشتراک‌های فعال  
**Churn:** (لغوشده+منقضی ماه جاری) / (فعال ابتدای ماه) × ۱۰۰ — مقایسه با ماه قبل + trend

---

### ✅ COMPLETED — Auto-renew Scheduler (P2 — 2026-06-06)

**فایل‌های جدید / تغییریافته:**
- `app/Console/Commands/ProcessRenewalsCommand.php` — NEW (`subscriptions:process-renewals`, `--dry-run`, `--window`)
- `app/Models/SubscriptionLog.php` — ACTION_AUTO_RENEWED + ACTION_AUTO_RENEW_INVOICE اضافه شد
- `bootstrap/app.php` — scheduler `->dailyAt('08:00')` ثبت شد
- `tests/Feature/ProcessRenewalsTest.php` — 13 tests (13/13 pass)

**منطق:**
- `auto_renew=true` + `status=active/trial` + `ends_at ≤ now+24h` + `ends_at > now-1h`
- پلن رایگان: مستقیم `SubscriptionService::renew()` → ACTION_AUTO_RENEWED
- پلن پولی: فاکتور pending ایجاد + SMS با لینک پرداخت → ACTION_AUTO_RENEW_INVOICE
- charge کارت خودکار در ایران ممکن نیست — کاربر باید خودش پرداخت کند
- idempotent: renewed_at امروز = skip | فاکتور pending امروز = skip

---

### 🟡 P3 — Benefit Enforcement در Shop Checkout

**مشکل:**
فیلدهای `product_discount_percent`، `free_shipping`، `vip_support`، `early_access`، `loyalty_multiplier` روی `SubscriptionPlan` model وجود دارند اما در shop checkout **اعمال نمی‌شوند**.

**باید:**
- در checkout، plan فعال کاربر check شود
- اگر plan داشت discount_percent → روی محصولات اعمال شود
- اگر free_shipping → هزینه ارسال صفر شود

---

### 🟡 P4 — Expiry Reminder SMS

**مشکل:**
داشبورد admin اشتراک‌های `expiring_soon` را نشان می‌دهد اما هیچ SMS/notification به کاربر قبل از انقضا فرستاده نمی‌شود.

**باید:**
- Artisan command: `subscription:send-expiry-reminders`
- ارسال SMS در ۷ روز و ۱ روز قبل از انقضا
- ثبت در `subscription_logs` که reminder فرستاده شده

---

### 🟡 P5 — Public Pricing Page

**مشکل:**
صفحه `/pricing` یا `/plans` عمومی وجود ندارد. فقط کاربران login‌کرده می‌توانند پلن‌ها را ببینند.

**باید:**
- یک صفحه public که بدون login قابل دیدن باشد
- کارت‌های پلن با benefits badge
- دکمه «خرید» → اگر login نیست، به login redirect کند

---

### 🟡 P6 — Test Coverage ضعیف

**مشکل:**
فقط یک تست خیلی ساده CRUD وجود دارد (`ModuleSubscriptionTest.php`).

**باید:**
- checkout flow (free plan، paid plan، با کوپن)
- payment callback (success، fail)
- cancel، renew، expire
- security: کاربر A نمی‌تواند اشتراک کاربر B را ببیند

---

## مسیرهای کلیدی

```
customer-site/
├── app/
│   ├── Models/
│   │   ├── Subscription.php
│   │   ├── SubscriptionPlan.php
│   │   └── SubscriptionLog.php
│   ├── Services/
│   │   └── SubscriptionService.php        ← منطق اصلی
│   └── Http/Controllers/
│       ├── Account/SubscriptionController.php
│       ├── Admin/SubscriptionDashboardController.php
│       ├── Admin/SubscriptionPlanController.php
│       └── Front/SubscriptionPaymentController.php ← P1 اینجا
├── database/migrations/                   ← 6 migration موجود
├── resources/views/
│   ├── account/subscriptions/
│   ├── admin/subscriptions/
│   └── front/subscriptions/
└── tests/Feature/ModuleSubscriptionTest.php
```

---

## ترتیب پیشنهادی توسعه

```
✅ Faz 1 (حیاتی):   Membership Benefits Engine (discount, free_shipping)
✅ Faz 2 (مهم):     Points & Cashback Engine (earn/spend)
✅ Faz 3 (مهم):     Retention Engine (SMS reminders + birthday gift)
✅ Faz 4 (ارزش):    Growth Engine (referral + /plans public page + MRR/Churn)

✅ P1 (حیاتی):      Payment Gateway (Zarinpal + IDPay) — 18/18 tests
✅ P2 (مهم):        Auto-renew Scheduler — 13/13 tests | رایگان→مستقیم | پولی→فاکتور+SMS
⬜ P3 (مهم):        Benefit Enforcement در Shop Checkout
```

---

## قوانین مهم این ماژول

1. **`SiteHelper::moduleEnabled('subscription')`** در ابتدای هر controller method — اگر ماژول disable شد، 404
2. **فاکتور اول، بعد subscription** — هیچ‌وقت subscription بدون invoice برای پلن‌های پولی
3. **`DB::transaction()`** در subscribe، renew، confirm — هیچ state نیمه‌کاره
4. **SMS notification** بعد از هر تغییر status — از `SubscriptionService` private methods
5. **log** بعد از هر action — `SubscriptionLog::create()`
