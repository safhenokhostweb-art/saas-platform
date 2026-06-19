@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\Jalali::formatFa($eduArticle->published_at, 'Y/m/d'),
        'id'         => 'profile-fields',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای فیلدهای پروفایل', 'updated' => '۱۴۰۴/۱۲/۰۵', 'id' => 'profile-fields'])
    <p class="font-medium text-gray-800">فیلدهای پروفایل و فرم پروفایل</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>فیلدهای پروفایل از این بخش تعریف و در فرم پروفایل استفاده می‌شوند.</li>
        <li>تنظیمات فرم پروفایل از منوی مربوطه قابل تغییر است.</li>
        <li>با هر تغییر راهنما و تاریخ در عنوان به روز می‌شود.</li>
    </ul>
@endcomponent
@endif
