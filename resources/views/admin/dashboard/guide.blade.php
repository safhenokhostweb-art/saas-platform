@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\NumberHelper::toPersianNumbers($eduArticle->published_at?->format('Y/m/d') ?? '—'),
        'id'         => 'dashboard',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای داشبورد', 'updated' => '۱۴۰۴/۱۲/۰۵', 'id' => 'dashboard'])
    <p class="font-medium text-gray-800">خلاصه داشبورد</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>کارت‌های بالای صفحه: تعداد مشتری‌ها، سایت‌ها و فرم‌ها.</li>
        <li>فرم‌ها و وضعیت در سایت مشتری: فعال/غیرفعال کردن هر فرم در هر سایت.</li>
        <li>وضعیت ماژول‌های سایت‌ها: از منوی سایت‌ها ماژول‌ها را مدیریت کنید.</li>
        <li>نمودار پایین صفحه آمار کلی سیستم را نشان می‌دهد.</li>
    </ul>
@endcomponent
@endif
