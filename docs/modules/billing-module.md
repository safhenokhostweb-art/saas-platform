# Billing & Payment (saas-platform)

## Current Features

- مدل‌های `Invoice` / `InvoiceItem` (مبالغ ریالی `*_irr`)
- سرویس‌های درگاه در `app/Services/Payment/` (رابط، کارخانه، رزولور، Zarinpal، IDPay)
- مدل `PaymentGateway` و مدیریت در پنل ادمین
- جریان checkout و callback (مثلاً `CheckoutController`)

## Suggested Enterprise Features

طبق `.cursor/rules/billing-module.mdc`: چندارزی اختیاری، مالیات/VAT، خروجی PDF فاکتور، retry پرداخت ناموفق، وب‌هوک، گزارش مالی — فقط در صورت نیاز و بدون حذف درگاه‌های ایرانی.

## Architecture

- لایهٔ سرویس پرداخت + مدل فاکتور
- بدون پوشهٔ جداگانهٔ `modules/billing`

## APIs

- مسیرهای وب: `routes/web.php` (checkout، callback)

## Database Tables

- `invoices`, `invoice_items`, `payment_gateways` (و جداول مرتبط خرید/لایسنس در صورت نیاز)

## Last Changes

- ۱۴۰۵/۰۲/۱۹: به‌روزرسانی قانون Cursor و این فایل راهنما.

## Technical Debt

- مدل `Payment.php` در `globs` قانون آمده؛ در صورت نبود فایل، یا ایجاد شود یا از قانون حذف شود.
