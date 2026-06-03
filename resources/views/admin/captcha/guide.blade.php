@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\NumberHelper::toPersianNumbers($eduArticle->published_at?->format('Y/m/d') ?? '—'),
        'id'         => 'captcha',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای تنظیمات کپچا', 'updated' => '۱۴۰۴/۱۲/۰۵', 'id' => 'captcha'])
    <p class="font-medium text-gray-800">کپچا و امنیت فرم‌ها</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>تنظیمات کپچا برای فرم‌های عمومی در این بخش انجام می‌شود.</li>
        <li>سرویس کپچا و کلیدها برای پلتفرم یا هر سایت مشتری قابل تنظیم است.</li>
        <li>با هر تغییر، راهنما و تاریخ در عنوان به روز می‌شود.</li>
    </ul>
@endcomponent
@endif
