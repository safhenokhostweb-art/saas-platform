# راهنمای بخش ۶: سیستم Reseller و کمیسیون

## هدف

نمایندگان (Reseller) تعریف شوند؛ مشتریان با `reseller_id` در فروش آن‌ها کمیسیون داشته باشند؛ داشبورد Reseller برای مشاهدهٔ فروش و کمیسیون.

## ۱. دیتابیس

- **resellers:** `user_id`, `company_name`, `commission_rate`, `bank_info`, `is_white_label`
- **customers.reseller_id** (nullable)
- **reseller_commissions:** `reseller_id`, `invoice_id`, `amount_irr`, `status` (pending|paid), `paid_at`

## ۲. ثبت کمیسیون

در `OrderFulfillmentService::fulfill()` پس از پرداخت فاکتور، اگر `invoice->customer->reseller_id` و `commission_rate` مثبت باشد، یک رکورد `ResellerCommission` با مبلغ `amount_irr * commission_rate / 100` ایجاد شود.

## ۳. پنل ادمین

- در فرم مشتری فیلد «نماینده» (انتخاب Reseller) برای ست کردن `reseller_id`. ✓
- مدیریت Resellerها: CRUD جدول resellers و تخصیص کاربر به Reseller. ✓ (ادمین → نمایندگان)

## ۴. داشبورد Reseller ✓

- روت‌های جدا با میدلور Reseller (`/reseller/dashboard`); نمایش لیست مشتریان، جمع فروش، لیست کمیسیون‌های pending/paid.
- لینک «پنل نمایندگی» در نوار بالا برای کاربران دارای Reseller.

## ۵. تسویه کمیسیون ✓

- صفحهٔ ادمین → تسویه کمیسیون: لیست کمیسیون‌های در انتظار و دکمهٔ «تسویه» برای هر رکورد.
