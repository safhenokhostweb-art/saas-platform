@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\Jalali::formatFa($eduArticle->published_at, 'Y/m/d'),
        'id'         => 'users',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای کاربران پلتفرم', 'updated' => '۱۴۰۴/۱۲/۰۵', 'id' => 'users'])
    <p class="font-medium text-gray-800">مدیریت کاربران پلتفرم</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>کاربران پلتفرم کسانی هستند که به پنل ادمین مرکزی (همین saas-platform) دسترسی دارند.</li>
        <li>می‌توانید کاربر جدید اضافه کنید، نقش ادمین بدهید یا دسترسی را محدود کنید.</li>
        <li>با هر تغییر در قابلیت‌های این بخش، این راهنما و تاریخ آخرین بروزرسانی در عنوان به روز می‌شود.</li>
    </ul>
@endcomponent
@endif
