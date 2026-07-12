# انیمیشن شبکه کسب‌وکار «صفحه نخست»

یک Web Component مستقل و بدون وابستگی خارجی که با SVG و CSS اجرا می‌شود. پس‌زمینه خود کامپوننت شفاف است؛ پس‌زمینه تیره در `demo.html` فقط برای نمایش است.

## فایل‌ها

- `iran-provinces-source.svg`: مرز برداری واقعی ۳۱ استان ایران
- `iran-outline-source.svg`: مسیر مستقل مرز خارجی ایران برای ترسیم پایانی
- `map-data.js`: نام فارسی استان‌ها، تنظیم برچسب‌ها و مختصات شهرها
- `brand-config.js`: مبدأ، مقصدها، متن برند، زمان‌بندی و رنگ‌ها
- `iran-business-network.js`: ساخت SVG و منطق اتصال‌ها
- `styles.css`: حرکت‌ها، حالت موبایل و reduced-motion
- `demo.html`: پیش‌نمایش مستقل

## استفاده

```html
<script type="module" src="/assets/iran-business-network/iran-business-network.js"></script>
<iran-business-network style="max-width:1100px"></iran-business-network>
```

برای تنظیم در زمان اجرا، پس از ثبت کامپوننت:

```html
<script type="module">
  import '/assets/iran-business-network/iran-business-network.js';

  document.querySelector('iran-business-network').animationConfig = {
    origin: 'Qazvin',
    destinations: ['Tehran', 'Isfahan', 'Shiraz', 'Mashhad', 'Tabriz'],
    brand: { name: 'صفحه نخست' },
    colors: { primary: '#38bdf8', accent: '#2dd4bf' }
  };
</script>
```

نام مقصدها باید یکی از کلیدهای `cities` در `map-data.js` باشد. برای افزودن شهر، فقط مختصات و برچسب آن را به همان فایل اضافه کنید. کامپوننت در خارج viewport متوقف می‌شود و تنظیم سیستم‌عامل برای کاهش حرکت را رعایت می‌کند.

هندسه مرز استان‌ها از نقشه آزاد SimpleMaps استفاده می‌کند (Free for Commercial Use). اطلاعات مجوز و انتساب داخل فایل SVG منبع حفظ شده است.
