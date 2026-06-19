@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\Jalali::formatFa($eduArticle->published_at, 'Y/m/d'),
        'id'         => 'menus',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای منو و ناوبری', 'updated' => '۱۴۰۴/۱۲/۰۷', 'id' => 'menus'])
    <p class="font-medium text-gray-800">مدیریت منوهای هدر، فوتر و سایدبار هر سایت</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>هر سایت می‌تواند چند منو داشته باشد (هدر، فوتر، سایدبار).</li>
        <li>آیتم‌های منو: عنوان، آدرس (URL)، نوع (لینک، صفحه، مطلب، محصول، دسته، سفارشی)، باز شدن در همان پنجره یا پنجرهٔ جدید.</li>
        <li>آیتم‌های زیرمنو با انتخاب parent_id در آینده پشتیبانی می‌شوند.</li>
        <li>منوها از لیست سایت‌ها → عملیات → منوها قابل دسترسی هستند.</li>
    </ul>
@endcomponent
@endif
