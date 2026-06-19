@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\Jalali::formatFa($eduArticle->published_at, 'Y/m/d'),
        'id'         => 'customers',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای مشتریان', 'updated' => '۱۴۰۴/۱۲/۰۵', 'id' => 'customers'])
    <p class="font-medium text-gray-800">مدیریت مشتری‌ها</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>مشتری‌ها صاحبان سایت‌های زیرمجموعه هستند.</li>
        <li>از این صفحه لیست، افزودن و ویرایش مشتری انجام می‌شود.</li>
        <li>برای مدیریت سایت‌های یک مشتری از منوی سایت‌ها استفاده کنید.</li>
        <li>با هر تغییر در این ماژول، راهنما و تاریخ در عنوان به روز می‌شود.</li>
    </ul>
@endcomponent
@endif
