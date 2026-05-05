<?php $__env->startSection('header', 'داشبورد'); ?>


<?php $__env->startSection('content'); ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <!-- Customers -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="text-gray-500 text-sm">تعداد مشتری‌ها</div>
        <div class="text-3xl font-bold mt-2"><?php echo e($customers); ?></div>
    </div>

    <!-- Sites -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="text-gray-500 text-sm">تعداد سایت‌ها</div>
        <div class="text-3xl font-bold mt-2"><?php echo e($sites); ?></div>
    </div>

    <!-- Forms -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="text-gray-500 text-sm">تعداد فرم‌ها</div>
        <div class="text-3xl font-bold mt-2"><?php echo e($forms); ?></div>
    </div>

</div>

<!-- هشدارهای انقضا (اعلان خودکار در ادمین اصلی) -->
<?php if(isset($expiryReminders) && $expiryReminders->isNotEmpty()): ?>
<div class="bg-white rounded-2xl shadow p-6 mb-8 border-r-4 border-amber-400">
    <h2 class="font-bold mb-2">هشدارهای انقضا</h2>
    <p class="text-gray-500 text-sm mb-4">اعلان خودکار انقضای پشتیبانی، هاست و دامنه (۱ ماه قبل و هر ۱۰ روز) — در <a href="<?php echo e(route('admin.platform-settings.index')); ?>" class="text-indigo-600 hover:underline">تنظیمات پلتفرم</a> می‌توانید ارسال را فعال/غیرفعال کنید.</p>
    <ul class="space-y-2 max-h-64 overflow-y-auto">
        <?php $__currentLoopData = $expiryReminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reminder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-100">
                <span class="shrink-0 w-2 h-2 mt-1.5 rounded-full <?php echo e($reminder->type === 'support' ? 'bg-blue-500' : ($reminder->type === 'host' ? 'bg-purple-500' : 'bg-teal-500')); ?>" title="<?php echo e($reminder->type === 'support' ? 'پشتیبانی' : ($reminder->type === 'host' ? 'هاست' : 'دامنه')); ?>"></span>
                <div class="min-w-0 flex-1">
                    <span class="font-medium text-gray-800"><?php echo e($reminder->title ?? 'هشدار انقضا'); ?></span>
                    <?php if($reminder->body): ?>
                        <p class="text-sm text-gray-600 mt-0.5"><?php echo e(Str::limit($reminder->body, 100)); ?></p>
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

<!-- دسترسی سریع: مدیریت سایت‌ها (تِننت‌ها)، ماژول‌ها، لایسنس، بروزرسانی -->
<div class="bg-white rounded-2xl shadow p-6 mb-8">
    <h2 class="font-bold mb-4">مدیریت سایت‌ها و سرویس‌ها</h2>
    <div class="flex flex-wrap gap-3">
        <a href="<?php echo e(route('admin.sites.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
            سایت‌ها (تِننت‌ها)
        </a>
        <a href="<?php echo e(route('admin.modules.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            ماژول‌ها
        </a>
        <a href="<?php echo e(route('admin.licenses.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            لایسنس‌ها
        </a>
        <a href="<?php echo e(route('admin.updates.index')); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            داشبورد بروزرسانی‌ها
        </a>
    </div>
</div>

<!-- فرم‌ها و وضعیت در سایت‌ها (فقط در پنل ادمین) -->
<?php if(isset($formSiteColumnExists)): ?>
<?php
    $formsList = $formsList ?? collect();
    $sitesList = $sitesList ?? collect();
?>
<div class="bg-white rounded-2xl shadow p-6 mb-8">
    <h2 class="font-bold mb-4">فرم‌ها و فعال/غیرفعال در سایت مشتری</h2>
    <?php if(!$formSiteColumnExists): ?>
        <div class="alert alert-danger">
            ستون <code>is_active</code> در جدول <code>form_site</code> یافت نشد. برای نمایش این بخش در ترمینال اجرا کنید:
            <pre class="mt-2 p-3 bg-gray-100 rounded text-sm">php artisan migrate</pre>
        </div>
    <?php else: ?>
        <p class="text-gray-500 text-sm mb-4">برای هر فرم می‌توانید در هر سایت مشتری آن را فعال یا غیرفعال کنید. فرم غیرفعال در آن سایت نمایش داده نمی‌شود.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold">فرم</th>
                        <?php $__currentLoopData = $sitesList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <th class="p-3 font-bold whitespace-nowrap"><?php echo e($site->title); ?></th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <th class="p-3 font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $formsList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $form): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3"><?php echo e($form->title); ?></td>
                            <?php $__currentLoopData = $sitesList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $pivot = $form->sites->firstWhere('id', $site->id);
                                    $isActive = $pivot ? (bool) ($pivot->pivot->is_active ?? true) : false;
                                ?>
                                <td class="p-3">
                                    <form action="<?php echo e(route('admin.forms.sites.toggle', [$form, $site])); ?>" method="POST" class="inline">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PATCH'); ?>
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 rounded text-sm <?php echo e($isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'); ?>" title="<?php echo e($isActive ? 'غیرفعال کردن' : 'فعال کردن'); ?>">
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
                            <td colspan="<?php echo e(count($sitesList) + 2); ?>" class="p-4 text-gray-500 text-center">هنوز فرمی تعریف نشده است.</td>
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
<div class="bg-white rounded-2xl shadow p-6 mb-8">
    <h2 class="font-bold mb-2">وضعیت ماژول‌های سایت‌های مشتری</h2>
    <p class="text-gray-500 text-sm mb-4">ماژول‌هایی که از پنل ادمین به هر سایت داده شده و در API لایسنس/سایت‌کانفیگ به آن سایت برگردانده می‌شوند. برای تغییر، از منوی سایت‌ها وارد هر سایت شوید و ماژول‌ها را مدیریت کنید.</p>
    <?php if($allModules->isEmpty()): ?>
        <p class="text-gray-500 text-sm">هنوز ماژولی در سیستم تعریف نشده است. از منوی ماژول‌ها یا سایت‌ها ماژول اضافه کنید.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold">سایت</th>
                        <th class="p-3 font-bold">دامنه</th>
                        <?php $__currentLoopData = $allModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <th class="p-3 font-bold whitespace-nowrap" title="<?php echo e($mod->title ?? $mod->slug); ?>"><?php echo e($mod->slug); ?></th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <th class="p-3 font-bold">وضعیت سایت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $sitesWithModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3">
                                <a href="<?php echo e(route('admin.sites.show', $site)); ?>" class="text-blue-600 hover:underline"><?php echo e($site->title); ?></a>
                                <span class="mx-1">|</span>
                                <a href="<?php echo e(route('admin.sites.modules.edit', $site)); ?>" class="text-sm text-gray-600 hover:underline">ماژول‌ها</a>
                            </td>
                            <td class="p-3 text-sm text-gray-600"><?php echo e($site->domain ?? '—'); ?></td>
                            <?php $__currentLoopData = $allModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $attached = $site->modules->contains('id', $mod->id);
                                ?>
                                <td class="p-3">
                                    <?php if($attached): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-sm bg-green-100 text-green-800" title="فعال برای این سایت">✓</span>
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
                            <td colspan="<?php echo e($allModules->count() + 3); ?>" class="p-4 text-gray-500 text-center">هنوز سایتی ثبت نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php echo $__env->make('admin.dashboard.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<!-- Chart Section -->
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="font-bold mb-4">آمار کلی سیستم</h2>
    <canvas id="statsChart"></canvas>
</div>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        const ctx = document.getElementById('statsChart').getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['مشتری‌ها', 'سایت‌ها', 'فرم‌ها'],
                datasets: [{
                    label: 'آمار سیستم',
                    data: <?php echo json_encode([$customers, $sites, $forms]) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

    });
</script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>