@if(isset($eduGuides) && $eduGuides->isNotEmpty())
    @php $eduArticle = $eduGuides->first(); @endphp
    @component('admin.partials.module-guide-box', [
        'guideTitle' => $eduArticle->title,
        'updated'    => \App\Helpers\NumberHelper::toPersianNumbers($eduArticle->published_at?->format('Y/m/d') ?? '—'),
        'id'         => 'activity-log',
    ])
        {!! $eduArticle->body !!}
    @endcomponent
@else
@component('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای لاگ فعالیت', 'updated' => '۱۴۰۵/۰۲/۲۴', 'id' => 'activity-log'])
    <p class="font-medium text-gray-800">ثبت فعالیت‌های کاربران</p>
    <ul class="list-disc list-inside space-y-1 pr-2">
        <li>لاگ فعالیت رویدادهای مهم (ورود، تغییر تنظیمات، ایجاد/ویرایش رکوردها، اعطا/لغو entitlement و ...) را در پلتفرم ثبت می‌کند.</li>
        <li>از این صفحه می‌توانید فیلتر بر اساس کاربر، تاریخ یا نوع رویداد اعمال کنید و خروجی بگیرید.</li>
        <li>برای ردیابیٔ تغییر دسترسی ماژول، رویدادهای <code>entitlement_granted</code> و <code>entitlement_revoked</code> از سرویس ledger ثبت می‌شوند؛ از ثبت تکراری برای همان پرداخت خودداری می‌شود.</li>
        <li>نگهداری داده: دستور <code>php artisan activity-log:prune</code> رکوردهای قدیمی‌تر از پنجرهٔ retention (پیش‌فرض از <code>config/audit.php</code> یا <code>AUDIT_RETENTION_DAYS</code>) را حذف می‌کند؛ با <code>AUDIT_PRUNE_DRY_RUN=true</code> فقط گزارش می‌شود. زمان‌بندی پیش‌فرض روزانه ساعت ۰۳:۰۰ است.</li>
        <li>با هر تغییر در نوع رویدادهای ثبت‌شده یا قابلیت‌های این صفحه، این راهنما و تاریخ آخرین بروزرسانی در عنوان به‌روز می‌شود.</li>
    </ul>
@endcomponent
@endif
