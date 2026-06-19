@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\Jalali::formatFa($eduArticle->published_at, 'Y/m/d'),
        'id'         => 'payment-gateways',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', [
    'guideTitle' => 'راهنمای درگاه‌های پرداخت بانکی',
    'updated' => '۱۴۰۴/۱۲/۰۵',
])
<p>درگاه‌های پرداخت بانک‌های ایرانی (زرین‌پال، آی‌دی‌پی و …) را از اینجا مدیریت کنید. تنظیمات حساس (مرچنت کد، API Key) به‌صورت رمزنگاری‌شده ذخیره می‌شوند.</p>
<ul class="list-disc list-inside mt-2 space-y-1">
    <li><strong>ماژول درگاه بانکی:</strong> در «دسترسی و منو» ماژول «درگاه بانکی» را فعال کنید. سپس در ویرایش هر سایت، آن ماژول را به سایت اختصاص دهید.</li>
    <li><strong>تخصیص درگاه به سایت:</strong> فقط برای سایت‌هایی که ماژول درگاه بانکی دارند، فیلد انتخاب درگاه در ویرایش سایت نمایش داده می‌شود.</li>
    <li><strong>درگاه پیش‌فرض:</strong> وقتی به سایتی درگاه تخصیص نشده، از درگاه پیش‌فرض پلتفرم استفاده می‌شود.</li>
    <li><strong>افزودن درگاه جدید:</strong> ساختار قابل توسعه است؛ درگاه‌های جدید با افزودن driver و کلاس مربوطه قابل اضافه‌شدن هستند.</li>
    <li><strong>حالت سندباکس:</strong> برای تست بدون پرداخت واقعی فعال کنید.</li>
</ul>
@endcomponent
@endif
