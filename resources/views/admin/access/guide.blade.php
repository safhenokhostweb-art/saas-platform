@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\NumberHelper::toPersianNumbers($eduArticle->published_at?->format('Y/m/d') ?? '—'),
        'id'         => 'access',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای دسترسی و منو', 'updated' => '۱۴۰۴/۱۲/۰۷', 'id' => 'access'])
    <p class="font-medium text-gray-800">دسترسی و آیتم‌های منوی ادمین</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>از این صفحه آیتم‌های منوی پنل ادمین را نمایش یا مخفی کنید.</li>
        <li><strong>منوهای سایت:</strong> با فعال بودن این آیتم، لینک «منوها» در عملیات هر سایت نمایش داده می‌شود و می‌توان منوهای هدر/فوتر آن سایت را مدیریت کرد.</li>
        <li><strong>ماژول‌های مختص پلتفرم:</strong> امکاناتی که فقط در پنل ادمین وجود دارند (مثل اعلان). با غیرفعال کردن، لینک‌های مربوطه حذف می‌شوند.</li>
        <li><strong>ماژول‌های سایت مشتری:</strong> ماژول‌هایی که به هر سایت اختصاص داده می‌شوند (وبلاگ، فروشگاه، فرم‌ساز، منو و …).</li>
        <li><strong>منوی پنل مشتری:</strong> برای کاربر نهایی (غیرادمین) فقط داشبورد و پروفایل نمایش داده می‌شود.</li>
    </ul>
@endcomponent
@endif
