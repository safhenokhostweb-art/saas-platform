<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 1rem; }
        .license-key { font-family: monospace; font-size: 1.1rem; background: #f3f4f6; padding: 0.75rem 1rem; border-radius: 0.5rem; margin: 1rem 0; word-break: break-all; }
        .footer { margin-top: 2rem; font-size: 0.875rem; color: #6b7280; }
    </style>
</head>
<body>
    <p>سلام {{ $customerName }}،</p>

    <p>پرداخت شما با موفقیت انجام شد و کلید لایسنس برای سایت «{{ $siteTitle }}» صادر شد.</p>

    <p><strong>کلید لایسنس:</strong></p>
    <div class="license-key">{{ $licenseKey }}</div>

    <p>این کلید را در پنل ادمین سایت خود (مسیر: لایسنس) وارد کنید. کلید را در جای امنی نگه دارید و با کسی به اشتراک نگذارید.</p>

    @if($domain)
        <p><strong>دامنهٔ مجاز:</strong> {{ $domain }}</p>
    @endif

    @if($expiresAt)
        <p><strong>تاریخ انقضا:</strong> {{ $expiresAt }}</p>
    @endif

    <div class="footer">
        این ایمیل به‌صورت خودکار ارسال شده است. در صورت سؤال با پشتیبانی تماس بگیرید.
    </div>
</body>
</html>
