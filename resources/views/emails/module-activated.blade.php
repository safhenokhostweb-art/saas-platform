<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 1rem; }
        .footer { margin-top: 2rem; font-size: 0.875rem; color: #6b7280; }
    </style>
</head>
<body>
    <p>سلام،</p>

    <p>پرداخت شما با موفقیت انجام شد و ماژول <strong>«{{ $moduleTitle }}»</strong> برای سایت «{{ $siteTitle }}» فعال شد.</p>

    @if($domain)
        <p><strong>دامنه:</strong> {{ $domain }}</p>
    @endif

    <p>از این پس می‌توانید از منوی پنل ادمین سایت خود به قابلیت‌های این ماژول دسترسی داشته باشید.</p>

    <div class="footer">
        این ایمیل به‌صورت خودکار ارسال شده است.
    </div>
</body>
</html>
