

<?php $__env->startSection('header', 'داشبورد بروزرسانی‌ها'); ?>

<?php $__env->startSection('content'); ?>
<?php $__env->startComponent('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای بروزرسانی', 'updated' => '۱۴۰۴/۱۲/۰۸', 'id' => 'updates']); ?>
    <p>وضعیت بروزرسانی ماژول‌ها برای هر سایت. سایت‌های واجد شرایط (دارای پشتیبانی فعال) می‌توانند بروزرسانی دریافت کنند. برای توزیع بروزرسانی دستی، از منوی سایت‌ها وارد هر سایت شوید و از لینک «بروزرسانی» استفاده کنید.</p>
<?php echo $__env->renderComponent(); ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold mb-4">وضعیت بروزرسانی سایت‌ها</h2>
        <div class="space-y-3 max-h-96 overflow-y-auto">
            <?php $__empty_1 = true; $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php $status = $siteStatuses[$site->id] ?? ['eligible' => false, 'updates' => []]; ?>
                <div class="flex items-center justify-between p-3 rounded-lg border <?php echo e($status['eligible'] ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-gray-200'); ?>">
                    <div>
                        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="font-medium text-indigo-600 hover:underline"><?php echo e($site->title); ?></a>
                        <span class="block text-xs text-gray-500"><?php echo e($site->domain ?? '—'); ?></span>
                    </div>
                    <div class="text-left">
                        <?php if($status['eligible']): ?>
                            <?php if(count($status['updates']) > 0): ?>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800"><?php echo e(count($status['updates'])); ?> بروزرسانی</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">به‌روز</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">غیرفعال</span>
                        <?php endif; ?>
                        <a href="<?php echo e(route('admin.sites.updates.index', $site)); ?>" class="block text-xs text-indigo-600 mt-1 hover:underline">جزئیات</a>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-gray-500 text-sm">سایتی ثبت نشده است.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold mb-4">آخرین توزیع‌های بروزرسانی</h2>
        <?php if($recentSiteUpdates->isEmpty()): ?>
            <p class="text-gray-500 text-sm">هنوز توزیعی ثبت نشده است.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php $__currentLoopData = $recentSiteUpdates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $up): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center justify-between p-2 rounded border border-gray-100">
                        <div>
                            <span class="font-medium"><?php echo e($up->version_label); ?></span>
                            <span class="text-gray-500 text-sm"> — <?php echo e($up->site->title ?? '—'); ?></span>
                        </div>
                        <span class="text-xs text-gray-500"><?php echo e($up->distributed_at ? \App\Helpers\Jalali::format($up->distributed_at) : '—'); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <a href="<?php echo e(route('admin.sites.index')); ?>" class="block mt-4 text-indigo-600 text-sm hover:underline">مشاهده همه سایت‌ها</a>
        <?php endif; ?>
    </div>
</div>

<?php if($allModules->isNotEmpty()): ?>
<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="font-bold mb-4">ماژول‌های دارای بروزرسانی (به تفکیک سایت)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3 text-right">سایت</th>
                    <th class="p-3 text-right">ماژول‌های قدیمی</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $status = $siteStatuses[$site->id] ?? ['updates' => []]; ?>
                    <?php if(count($status['updates']) > 0): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3">
                                <a href="<?php echo e(route('admin.sites.updates.index', $site)); ?>" class="text-indigo-600 hover:underline"><?php echo e($site->title); ?></a>
                            </td>
                            <td class="p-3">
                                <?php $__currentLoopData = $status['updates']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 mr-1">
                                        <?php echo e($u['title'] ?? $u['slug']); ?> (<?php echo e($u['installed_version'] ?? '—'); ?> → <?php echo e($u['available_version'] ?? '—'); ?>)
                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/updates/index.blade.php ENDPATH**/ ?>