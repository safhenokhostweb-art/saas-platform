@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای تنظیمات عمومی پلتفرم', 'updated' => '۱۴۰۴/۱۲/۰۷', 'id' => 'platform-settings'])
    <p class="font-medium text-gray-800">نام، لوگو، تم، فونت، ویجت‌ها و تنظیمات Enterprise</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>عمومی: نام پلتفرم، لوگو، تماس، ثبت‌نام.</li>
        <li>تم و رنگ: منو، سایدبار، دکمه‌ها.</li>
        <li>فونت: متن و عنوان.</li>
        <li>ویجت راهنما: فعال/غیرفعال، موقعیت، پالت رنگ.</li>
        <li>ویجت اعلان ادمین: موقعیت دکمهٔ زنگوله در پنل سایت مشتری.</li>
        <li>حجم و آپلود: حداکثر حجم تصویر.</li>
        <li><strong>Enterprise:</strong> تنظیمات ایمیل (SMTP)، پیامک (ملی‌پیامک، کاوه‌نگار، قاصدک)، کلیدهای API (Google Maps، reCAPTCHA)، Feature flags (همگام‌سازی مارکت‌پلیس، چندارزی، کیف پول، تحلیل پیشرفته).</li>
    </ul>
@endcomponent
