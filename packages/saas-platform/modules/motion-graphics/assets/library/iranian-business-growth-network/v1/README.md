# شبکه رشد کسب‌وکار ایرانی

دارایی SVG مستقل، responsive و بدون وابستگی خارجی برای Hero، معرفی، خدمات، لندینگ و پروفایل حرفه‌ای.

## استفاده عمومی

    <img src="/assets/motion/iranian-business-growth-network/v1/animation.svg"
         alt="شبکه رشد کسب‌وکار ایرانی" width="960" height="540" loading="lazy">

برای Hero بالای صفحه، در صورت قرارگیری داخل viewport اولیه می‌توان loading=lazy را حذف کرد. نسبت تصویر با width:100% و height:auto حفظ می‌شود.

## استفاده در Laravel

پس از انتشار توسط زیرساخت آینده، دارایی از manifest نسخه منتشرشده مصرف می‌شود. مسیر source این پوشه نباید مستقیماً در اختیار tenant قرار گیرد:

    <x-motion.graphics name="iranian-business-growth-network" />

برای تغییر CSS variableها باید SVG به‌صورت inline و پس از sanitization سمت سرور درج شود. در حالت img متغیرهای داخلی با مقادیر پیش‌فرض production-ready کار می‌کنند.
