<?php $__env->startSection('header', 'داشبورد'); ?>


<?php $__env->startSection('content'); ?>

<?php
    $modulesCount = $modulesCount ?? 0;
    $activeLicensesCount = $activeLicensesCount ?? 0;
?>
<?php $__env->startPush("head"); ?>
<style>
.glass-card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.25);box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.glass-card:hover{box-shadow:0 12px 48px rgba(15,23,42,0.1)}
.glass-card-strong{background:rgba(255,255,255,0.85);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.4);box-shadow:0 8px 32px rgba(15,23,42,0.08)}
.glass-table thead th{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:.75rem 1rem;background:rgba(99,102,241,0.06);border-bottom:2px solid rgba(99,102,241,0.15);white-space:nowrap}
.glass-table tbody td{padding:.75rem 1rem;border-bottom:1px solid rgba(226,232,240,0.5);font-size:.875rem}
.glass-table tbody tr:hover{background:rgba(99,102,241,0.03)}
.badge-glass{display:inline-flex;align-items:center;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:600;gap:.375rem}
.badge-glass--success{background:rgba(16,185,129,0.15);color:#059669}
.badge-glass--danger{background:rgba(239,68,68,0.15);color:#dc2626}
.stat-tile{position:relative;display:block;overflow:hidden;border-radius:1rem;padding:1.25rem;box-shadow:0 8px 24px rgba(15,23,42,0.08);transition:all .3s;color:#fff!important;text-decoration:none;border:1px solid rgba(255,255,255,0.15)}
.stat-tile:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 20px 48px rgba(15,23,42,0.18)}
.stat-tile--violet{background:linear-gradient(135deg,#7c3aed,#1e1b4b)}
.stat-tile--sky{background:linear-gradient(135deg,#0284c7,#0f172a)}
.stat-tile--emerald{background:linear-gradient(135deg,#059669,#022c22)}
.stat-tile--rose{background:linear-gradient(135deg,#e11d48,#4c0519)}
.stat-tile--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.stat-tile--indigo{background:linear-gradient(135deg,#6366f1,#1e1b4b)}
.pulse-dot{display:inline-block;width:8px;height:8px;border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.6;transform:scale(1.2)}}
.progress-glass{height:.5rem;border-radius:9999px;background:rgba(226,232,240,0.5);overflow:hidden}
.progress-glass__bar{height:100%;border-radius:9999px;background:linear-gradient(135deg,#6366f1,#a855f7)}
.scrollbar-thin::-webkit-scrollbar{width:6px;height:6px}
.scrollbar-thin::-webkit-scrollbar-thumb{background:rgba(148,163,184,0.3);border-radius:8px}
</style>
<?php $__env->stopPush(); ?>
<div class="glass-card overflow-hidden mb-8">
<div class="absolute inset-0 bg-gradient-to-l from-indigo-600/10 via-purple-600/5 to-transparent pointer-events-none"></div>
<div class="relative z-10 p-6 sm:p-8">
<h2 class="text-2xl sm:text-3xl font-bold text-gray-900">خوش آمدید، <span class="bg-gradient-to-l from-indigo-600 to-purple-600 bg-clip-text text-transparent"><?php echo e(auth()->user()->name ?? 'مدیر'); ?></span> 👋</h2>
<p class="text-gray-500 text-sm mt-2">آخرین وضعیت پلتفرم در زیر نمایش داده شده است.</p>
</div>
</div>





<p class="text-slate-600 text-sm mb-4">آمار و میانبرهای اصلی پلتفرم — هر کاشی را برای ورود به بخش مربوطه بزنید.</p>

<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
    <a href="<?php echo e(route('admin.customers.index')); ?>" class="stat-tile stat-tile--violet">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">مشتری‌ها</div>
    </a>
    <a href="<?php echo e(route('admin.sites.index')); ?>" class="stat-tile stat-tile--sky">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">سایت‌ها (تِننت)</div>


        <div class="stat-tile__value"><?php echo e(\App\Helpers\NumberHelper::formatFa($sites)); ?></div>
        <div class="stat-tile__hint">سایت‌های مشتری</div>
    </a>
    <a href="<?php echo e(route('admin.forms.index')); ?>" class="stat-tile stat-tile--emerald">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">فرم‌ها</div>
    </a>
    <a href="<?php echo e(route('admin.modules.index')); ?>" class="stat-tile stat-tile--rose">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">تعریف ماژول</div>

        <div class="stat-tile__value"><?php echo e(\App\Helpers\NumberHelper::formatFa($modulesCount)); ?></div>
        <div class="stat-tile__hint">کاتالوگ ماژول</div>
    </a>
    <a href="<?php echo e(route('admin.licenses.index')); ?>" class="stat-tile stat-tile--amber">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">لایسنس فعال</div>
        <div class="stat-tile__value"><?php echo e(\App\Helpers\NumberHelper::formatFa($activeLicensesCount)); ?></div>
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
    <a href="<?php echo e(route('admin.platform-settings.index')); ?>" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 text-lg">⚙</span>
        <span class="font-medium text-slate-800">تنظیمات پلتفرم</span>
    </a>
    <a href="<?php echo e(route('admin.updates.index')); ?>" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700 text-lg">↻</span>
        <span class="font-medium text-slate-800">بروزرسانی‌ها</span>
    </a>
    <a href="<?php echo e(route('admin.users.index')); ?>" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-700 text-lg">👤</span>
        <span class="font-medium text-slate-800">کاربران</span>
    </a>
    <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 text-lg">⌂</span>
        <span class="font-medium text-slate-800">داشبورد کاربر عادی</span>
    </a>
</div>

<!-- هشدارهای انقضا (اعلان خودکار در ادمین اصلی) -->
<?php if(isset($expiryReminders) && $expiryReminders->isNotEmpty()): ?>
<div class="glass-card-strong overflow-hidden p-6 mb-8 border-r-4 border-r-amber-400">
    <h2 class="font-bold mb-2">هشدارهای انقضا</h2>
    <p class="text-gray-500 text-sm mb-4">اعلان خودکار انقضای پشتیبانی، هاست و دامنه (۱ ماه قبل و هر ۱۰ روز) — در <a href="<?php echo e(route('admin.platform-settings.index')); ?>" class="text-indigo-600 hover:underline">تنظیمات پلتفرم</a> می‌توانید ارسال را فعال/غیرفعال کنید.</p>
    <ul class="space-y-2 max-h-64 overflow-y-auto">
        <?php $__currentLoopData = $expiryReminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reminder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-100">
                <span class="shrink-0 w-2 h-2 mt-1.5 rounded-full <?php echo e($reminder->type === 'support' ? 'bg-blue-500' : ($reminder->type === 'host' ? 'bg-purple-500' : 'bg-teal-500')); ?>" title="<?php echo e($reminder->type === 'support' ? 'پشتیبانی' : ($reminder->type === 'host' ? 'هاست' : 'دامنه')); ?>"></span>
                <div class="min-w-0 flex-1">
                    <span class="font-medium text-gray-800"><?php echo e(\App\Helpers\NumberHelper::toPersianNumbers($reminder->title ?? 'هشدار انقضا')); ?></span>
                    <?php if($reminder->body): ?>
                        <p class="text-sm text-gray-600 mt-0.5"><?php echo e(\App\Helpers\NumberHelper::toPersianNumbers(Str::limit($reminder->body, 100))); ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-1"><?php echo e($reminder->sent_at ? \App\Helpers\Jalali::formatFa($reminder->sent_at, 'Y/m/d H:i') : ''); ?></p>
                </div>
                <?php if($reminder->site): ?>
                    <a href="<?php echo e(route('admin.sites.edit', $reminder->site)); ?>" class="shrink-0 text-sm text-indigo-600 hover:underline">ویرایش سایت</a>
                <?php endif; ?>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</div>
<?php endif; ?>

<!-- فرم‌ها و وضعیت در سایت‌ها (فقط در پنل ادمین) -->
<?php if(isset($formSiteColumnExists)): ?>
<?php
    $formsList = $formsList ?? collect();
    $sitesList = $sitesList ?? collect();
?>
<div class="glass-card-strong overflow-hidden p-6 mb-8">
    <h2 class="font-bold mb-4">فرم‌ها و فعال/غیرفعال در سایت مشتری</h2>
    <?php if(!$formSiteColumnExists): ?>
        <div class="rounded-xl bg-red-50/80 border border-red-200 p-4 backdrop-blur-sm">
            ستون <code>is_active</code> در جدول <code>form_site</code> یافت نشد. برای نمایش این بخش در ترمینال اجرا کنید:
            <pre class="mt-2 p-3 bg-gray-100 rounded text-sm">php artisan migrate</pre>
        </div>
    <?php else: ?>
        <p class="text-gray-500 text-sm mb-4">برای هر فرم می‌توانید در هر سایت مشتری آن را فعال یا غیرفعال کنید. فرم غیرفعال در آن سایت نمایش داده نمی‌شود.</p>
        <div class="overflow-x-auto scrollbar-thin rounded-xl border border-gray-100">
            <table class="w-full text-right border-collapse glass-table">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">فرم</th>
                        <?php $__currentLoopData = $sitesList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <th class="p-3 font-bold whitespace-nowrap"><?php echo e(\App\Helpers\NumberHelper::toPersianNumbers($site->title)); ?></th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $formsList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $form): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="border-b hover:bg-indigo-50/30 transition-colors">
                            <td class="p-3"><?php echo e(\App\Helpers\NumberHelper::toPersianNumbers($form->title)); ?></td>
                            <?php $__currentLoopData = $sitesList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $pivot = $form->sites->firstWhere('id', $site->id);
                                    $isActive = $pivot ? (bool) ($pivot->pivot->is_active ?? true) : false;
                                ?>
                                <td class="p-3">
                                    <form action="<?php echo e(route('admin.forms.sites.toggle', [$form, $site])); ?>" method="POST" class="inline">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PATCH'); ?>
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium <?php echo e($isActive ? 'badge-glass badge-glass--success' : 'bg-gray-100/80 text-gray-500 hover:bg-gray-200/80'); ?>" title="<?php echo e($isActive ? 'غیرفعال کردن' : 'فعال کردن'); ?>">
                                            <?php if($isActive): ?>
                                                <span aria-hidden="true">✓</span> فعال
                                            <?php else: ?>
                                                <span aria-hidden="true">—</span> غیرفعال
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <td class="p-3">
                                <a href="<?php echo e(route('admin.forms.sites.edit', $form)); ?>" class="text-blue-600 hover:underline text-sm">تنظیم سایت‌ها</a>
                                <span class="mx-1">|</span>
                                <a href="<?php echo e(route('admin.forms.edit', $form)); ?>" class="text-blue-600 hover:underline text-sm">ویرایش فرم</a>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="<?php echo e(count($sitesList) + 2); ?>" class="text-center text-gray-400 py-8">هنوز فرمی تعریف نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- وضعیت ماژول‌های سایت‌ها (چک‌لیست امکاناتی که به هر سایت مشتری داده شده) -->
<?php if(isset($sitesWithModules) && isset($allModules)): ?>
<div class="glass-card-strong overflow-hidden p-6 mb-8">
    <h2 class="font-bold mb-2">وضعیت ماژول‌های سایت‌های مشتری</h2>
    <p class="text-gray-500 text-sm mb-4">ماژول‌هایی که از پنل ادمین به هر سایت داده شده و در API لایسنس/سایت‌کانفیگ به آن سایت برگردانده می‌شوند. برای تغییر، از منوی سایت‌ها وارد هر سایت شوید و ماژول‌ها را مدیریت کنید.</p>
    <?php if($allModules->isEmpty()): ?>
        <p class="text-gray-500 text-sm">هنوز ماژولی در سیستم تعریف نشده است. از منوی ماژول‌ها یا سایت‌ها ماژول اضافه کنید.</p>
    <?php else: ?>
        <div class="overflow-x-auto scrollbar-thin rounded-xl border border-gray-100">
            <table class="w-full text-right border-collapse glass-table">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">سایت</th>
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">دامنه</th>
                        <?php $__currentLoopData = $allModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <th class="p-3 font-bold whitespace-nowrap" title="<?php echo e($mod->title ?? $mod->slug); ?>"><?php echo e($mod->slug); ?></th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">وضعیت سایت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $sitesWithModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="border-b hover:bg-indigo-50/30 transition-colors">
                            <td class="p-3">
                                <a href="<?php echo e(route('admin.sites.show', $site)); ?>" class="text-blue-600 hover:underline"><?php echo e(\App\Helpers\NumberHelper::toPersianNumbers($site->title)); ?></a>
                                <span class="mx-1">|</span>
                                <a href="<?php echo e(route('admin.sites.modules.edit', $site)); ?>" class="text-sm text-gray-600 hover:underline">ماژول‌ها</a>
                            </td>
                            <td class="p-3 text-sm text-gray-600"><?php echo e(\App\Helpers\NumberHelper::toPersianNumbers($site->domain ?? '—')); ?></td>
                            <?php $__currentLoopData = $allModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $attached = $site->modules->contains('id', $mod->id);
                                ?>
                                <td class="p-3">
                                    <?php if($attached): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-sm badge-glass badge-glass--success" title="فعال برای این سایت">✓</span>
                                    <?php else: ?>
                                        <span class="text-gray-300" title="غیرفعال">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <td class="p-3">
                                <?php if($site->is_active): ?>
                                    <span class="text-green-600 text-sm">فعال</span>
                                <?php else: ?>
                                    <span class="text-red-600 text-sm">غیرفعال</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="<?php echo e($allModules->count() + 3); ?>" class="text-center text-gray-400 py-8">هنوز سایتی ثبت نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php echo $__env->make('admin.dashboard.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>




<div class="glass-card-strong overflow-hidden mb-8">
<div class="p-6">
<div class="flex items-center gap-3 mb-4">
<span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 text-lg">📊</span>
<h2 class="font-bold text-gray-900 text-lg">آمار کلی سیستم</h2>
</div>
<div class="chart-container relative w-full" style="min-height:300px">
<canvas id="statsChart"></canvas>
</div>
</div>
</div>


<div class="glass-card overflow-hidden">
<div class="p-6">
<div class="flex items-center justify-between flex-wrap gap-4">
<div class="flex items-center gap-3">
<span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-600">💚</span>
<div>
<p class="font-semibold text-gray-800">وضعیت سیستم</p>
<p class="text-sm text-gray-500">همه بخش‌ها به صورت عادی در حال فعالیت هستند</p>
</div>
</div>
<div class="flex items-center gap-2"><span class="pulse-dot bg-green-500"></span><span class="text-sm font-medium text-green-700">فعال</span></div>
</div>
</div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
var ctx=document.getElementById('statsChart').getContext('2d');
new Chart(ctx,{type:'bar',data:{labels:['مشتری‌ها','سایت‌ها','فرم‌ها'],datasets:[{label:'آمار سیستم',data:<?php echo json_encode([$customers, $sites, $forms]) ?>,backgroundColor:['rgba(124,58,237,0.7)','rgba(2,132,199,0.7)','rgba(5,150,105,0.7)'],borderColor:['rgba(124,58,237,1)','rgba(2,132,199,1)','rgba(5,150,105,1)'],borderWidth:2,borderRadius:6,barPercentage:0.6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(255,255,255,0.9)',titleColor:'#1e293b',bodyColor:'#475569',borderColor:'rgba(226,232,240,1)',borderWidth:1,cornerRadius:8,rtl:true,titleFont:{family:'Vazirmatn'},bodyFont:{family:'Vazirmatn'}}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(226,232,240,0.4)'},ticks:{font:{family:'Vazirmatn'}}},x:{grid:{display:false},ticks:{font:{family:'Vazirmatn'}}}}}});
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>