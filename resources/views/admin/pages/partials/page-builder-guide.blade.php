{{-- راهنمای صفحه‌ساز و پسوندهای مجاز آپلود --}}
@php
    $allowedImageExtensions = 'JPG، JPEG، PNG، GIF، BMP، WEBP';
    $maxImageSizeKb = (int) \App\Helpers\PlatformSettings::get('upload_image_max_kb', '5120');
    $maxImageSizeMb = $maxImageSizeKb >= 1024 ? round($maxImageSizeKb / 1024, 1) : ($maxImageSizeKb . ' کیلوبایت');
@endphp
<details class="mt-4 border border-gray-200 rounded-xl bg-blue-50/50 overflow-hidden" id="page-builder-guide">
    <summary class="px-4 py-3 cursor-pointer font-semibold text-gray-800 hover:bg-blue-100/50 flex items-center gap-2 list-none">
        <span class="text-indigo-600">?</span>
        راهنمای صفحه‌ساز و قوانین بارگذاری — آخرین بروزرسانی: ۱۴۰۴/۱۲/۰۵
    </summary>
    <div class="px-4 py-3 pt-0 text-sm text-gray-700 space-y-4 border-t border-gray-200">
        <p class="font-medium text-gray-800">کار با بلوک‌ها</p>
        <ul class="list-disc list-inside space-y-1 pr-2">
            <li>با دکمه‌های نوار بالا بلوک جدید اضافه کنید.</li>
            <li>برای جابجایی، روی علامت <strong>⋮⋮</strong> کنار هر بلوک کلیک کرده و بکشید.</li>
            <li>قبل از افزودن بلوک جدید یا جابجایی، محتوای فعلی به‌طور خودکار ذخیره می‌شود.</li>
            <li>با دکمه «حذف» روی هر بلوک می‌توانید آن را حذف کنید.</li>
        </ul>

        <p class="font-medium text-gray-800">انواع بلوک</p>
        <ul class="list-disc list-inside space-y-1 pr-2">
            <li><strong>عنوان / پاراگراف / متن غنی:</strong> متن ساده یا قالب‌بندی‌شده (پررنگ، لینک، لیست).</li>
            <li><strong>Hero:</strong> بخش بالای صفحه با عنوان، زیرعنوان و دکمه با رنگ پس‌زمینه.</li>
            <li><strong>ویژگی‌ها:</strong> یک عنوان و سه کارت (عنوان + متن) در سه ستون.</li>
            <li><strong>CTA:</strong> جعبه دعوت به اقدام با دکمه و رنگ پس‌زمینه.</li>
            <li><strong>دو ستون:</strong> دو ناحیه متنی کنار هم.</li>
            <li><strong>تصویر:</strong> یک تصویر با تراز چپ، راست، وسط یا تمام عرض. آدرس یا «مرور و آپلود».</li>
            <li><strong>گالری تصویر:</strong> چند تصویر یا فیلم با عنوان؛ در صفحه به‌صورت کاروسل (کالری رونده) با دکمه‌های قبلی/بعدی نمایش داده می‌شود.</li>
            <li><strong>تصویر و متن:</strong> یک تصویر در کنار متن (تراز چپ یا راست).</li>
            <li><strong>متن غنی:</strong> عنوان (H2/H3)، پررنگ، لینک، درج تصویر، لیست.</li>
            <li><strong>لیست / نقل‌قول / کد / جداکننده / فاصله / HTML:</strong> برای ساختار و محتوای متنی یا خام.</li>
        </ul>

        <p class="font-medium text-gray-800">بارگذاری تصویر در بلوک «تصویر»</p>
        <ul class="list-disc list-inside space-y-1 pr-2">
            <li><strong>پسوندهای مجاز:</strong> {{ $allowedImageExtensions }}</li>
            <li><strong>حداکثر حجم هر فایل:</strong> {{ $maxImageSizeMb }}{{ is_numeric($maxImageSizeMb) ? ' مگابایت' : '' }} (قابل تغییر از <strong>تنظیمات عمومی پلتفرم → حجم و آپلود فایل</strong>)</li>
            <li>می‌توانید آدرس تصویر را دستی وارد کنید یا با دکمه <strong>«مرور و آپلود»</strong> فایل را از رایانه انتخاب کنید؛ پس از آپلود، آدرس به‌طور خودکار در فیلد قرار می‌گیرد.</li>
            <li>برای نمایش تصاویر آپلودشده در سایت، در سرور دستور <code class="bg-gray-200 px-1 rounded">php artisan storage:link</code> را یک بار اجرا کنید (لینک نمادین از <code>public/storage</code> به <code>storage/app/public</code> ساخته می‌شود). اگر تصویری با پسوند مجاز (مثل PNG) آپلود کردید اما در صفحه مشاهده دیده نمی‌شود، ابتدا این دستور را اجرا کنید و سپس صفحه را رفرش کنید.</li>
        </ul>

        <p class="font-medium text-gray-800">سئو (SEO)</p>
        <ul class="list-disc list-inside space-y-1 pr-2">
            <li>فیلد <strong>توضیح متا</strong> در تنظیمات صفحه برای موتورهای جستجو استفاده می‌شود. در صورت خالی بودن، از عنوان صفحه استفاده می‌شود.</li>
            <li>متاتگ‌های Open Graph و Twitter و آدرس canonical به‌صورت خودکار برای هر صفحه تولید می‌شوند.</li>
            <li>پیشنهادات سئو زیر فیلد توضیح متا نمایش داده می‌شوند (طول عنوان، طول توضیح متا).</li>
        </ul>

        <p class="font-medium text-gray-800">آیکن صفحه و لوگو</p>
        <ul class="list-disc list-inside space-y-1 pr-2">
            <li>برای آیکن منو می‌توانید یکی از آیکن‌های پیش‌فرض را انتخاب کنید یا یک فایل تصویر (مثل {{ $allowedImageExtensions }} یا SVG) از طریق «آیکن سفارشی» بارگذاری کنید.</li>
            <li><strong>مسیر ذخیره در پروژه:</strong> فایل‌های آیکن صفحه در <code class="bg-gray-200 px-1 rounded">storage/app/public/pages/</code> و لوگوی پلتفرم در <code class="bg-gray-200 px-1 rounded">storage/app/public/platform/</code> ذخیره می‌شوند. برای نمایش در سایت حتماً یک بار دستور <code class="bg-gray-200 px-1 rounded">php artisan storage:link</code> را اجرا کنید تا لینک <code>public/storage</code> به این پوشه‌ها ساخته شود.</li>
        </ul>
    </div>
</details>
