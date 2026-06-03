@platform
    @if(isset($eduGuides) && $eduGuides->isNotEmpty())
        @php $eduArticle = $eduGuides->first(); @endphp
        @component(config('forms.components.module-guide-box', 'admin.partials.module-guide-box'), [
            'guideTitle' => $eduArticle->title,
            'updated'    => \App\Helpers\NumberHelper::toPersianNumbers($eduArticle->published_at?->format('Y/m/d') ?? '—'),
            'id'         => 'forms',
        ])
            {!! $eduArticle->body !!}
        @endcomponent
    @else
        @component(config('forms.components.module-guide-box', 'admin.partials.module-guide-box'), ['guideTitle' => 'راهنمای فرم‌ها', 'updated' => '۱۴۰۴/۱۲/۰۵', 'id' => 'forms'])
            <p class="font-medium text-gray-800">فرم‌ها و گزارش ارسال‌ها</p>
            <ul class="list-disc list-inside space-y-1 pr-2">
                <li>فرم‌ها از این پنل تعریف و در سایت‌های مشتری نمایش داده می‌شوند.</li>
                <li>فیلدها و سایت‌های فعال برای هر فرم از این بخش قابل مدیریت است.</li>
                <li>گزارش ارسال فرم‌ها از منوی مربوطه قابل مشاهده است.</li>
                <li>با هر تغییر در فرم یا گزارش، راهنما و تاریخ در عنوان به روز می‌شود.</li>
            </ul>
        @endcomponent
    @endif
@endplatform
