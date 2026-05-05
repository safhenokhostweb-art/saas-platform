

<?php $__env->startSection('header', 'لایسنس‌ها'); ?>

<?php $__env->startSection('content'); ?>
<?php $__env->startComponent('admin.partials.module-guide-box', ['guideTitle' => 'راهنمای لایسنس‌ها', 'updated' => '۱۴۰۴/۱۲/۰۸', 'id' => 'licenses']); ?>
    <p>لیست لایسنس‌های صادرشده برای سایت‌های مشتری. کلید لایسنس فقط یک‌بار هنگام صدور نمایش داده می‌شود. برای صدور لایسنس جدید، از منوی <strong>سایت‌ها</strong> وارد سایت موردنظر شوید و از لینک «لایسنس» استفاده کنید. از این صفحه می‌توانید لایسنس را <strong>غیرفعال (ابطال)</strong> یا دوباره <strong>فعال</strong> کنید؛ لایسنس‌های ابطال‌شده را می‌توان حذف دائمی کرد.</p>
<?php echo $__env->renderComponent(); ?>

<?php if(session('success')): ?>
    <div class="alert alert-success mt-4"><?php echo e(session('success')); ?></div>
<?php endif; ?>
<?php if(session('error')): ?>
    <div class="alert alert-danger mt-4"><?php echo e(session('error')); ?></div>
<?php endif; ?>
<?php if(session('info')): ?>
    <div class="alert alert-info mt-4"><?php echo e(session('info')); ?></div>
<?php endif; ?>

<div class="flex flex-wrap gap-2 mt-4 mb-4">
    <a href="<?php echo e(route('admin.licenses.index')); ?>" class="px-3 py-1.5 rounded text-sm <?php echo e(!request('status') ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'); ?>">همه</a>
    <a href="<?php echo e(route('admin.licenses.index', ['status' => 'active'])); ?>" class="px-3 py-1.5 rounded text-sm <?php echo e(request('status') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'); ?>">فعال</a>
    <a href="<?php echo e(route('admin.licenses.index', ['status' => 'expired'])); ?>" class="px-3 py-1.5 rounded text-sm <?php echo e(request('status') === 'expired' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'); ?>">منقضی</a>
    <a href="<?php echo e(route('admin.licenses.index', ['status' => 'revoked'])); ?>" class="px-3 py-1.5 rounded text-sm <?php echo e(request('status') === 'revoked' ? 'bg-gray-200 text-gray-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'); ?>">ابطال‌شده</a>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">سایت / مشتری</th>
                <th class="p-3 text-right">پیشوند کلید</th>
                <th class="p-3 text-right">صادر شده</th>
                <th class="p-3 text-right">انقضا</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $licenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $license): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">
                        <?php if($license->site): ?>
                            <a href="<?php echo e(route('admin.sites.edit', $license->site)); ?>" class="font-medium text-indigo-600 hover:underline"><?php echo e($license->site->title); ?></a>
                            <span class="block text-xs text-gray-500"><?php echo e($license->site->customer->company_name ?? $license->site->customer->name ?? '—'); ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="p-3 font-mono text-xs"><?php echo e($license->license_key_prefix ?? '—'); ?></td>
                    <td class="p-3"><?php echo e($license->issued_at ? \App\Helpers\Jalali::format($license->issued_at) : '—'); ?></td>
                    <td class="p-3"><?php echo e($license->expires_at ? \App\Helpers\Jalali::format($license->expires_at) : 'بدون انقضا'); ?></td>
                    <td class="p-3">
                        <?php if($license->isRevoked()): ?>
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-700">ابطال‌شده</span>
                        <?php elseif($license->isExpired()): ?>
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">منقضی</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">فعال</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 flex flex-wrap gap-2 items-center">
                        <?php if($license->site): ?>
                            <a href="<?php echo e(route('admin.sites.edit', $license->site)); ?>" class="text-indigo-600 hover:underline text-xs">سایت</a>
                        <?php endif; ?>
                        <?php if(!$license->isRevoked() && !$license->isExpired()): ?>
                            <form action="<?php echo e(route('admin.licenses.revoke', ['platform_license' => $license])); ?>" method="post" class="inline" onsubmit="return confirm('لایسنس این سایت غیرفعال می‌شود و سایت با این کلید دیگر معتبر نخواهد بود. ادامه می‌دهید؟');">
                                <?php echo csrf_field(); ?>
                                <?php $__currentLoopData = request()->only('status', 'site_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if($v !== null && $v !== ''): ?><input type="hidden" name="<?php echo e($k); ?>" value="<?php echo e($v); ?>"><?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <button type="submit" class="text-amber-600 hover:underline text-xs">غیرفعال کردن</button>
                            </form>
                        <?php endif; ?>
                        <?php if($license->isRevoked()): ?>
                            <form action="<?php echo e(route('admin.licenses.reactivate', ['platform_license' => $license])); ?>" method="post" class="inline">
                                <?php echo csrf_field(); ?>
                                <?php $__currentLoopData = request()->only('status', 'site_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if($v !== null && $v !== ''): ?><input type="hidden" name="<?php echo e($k); ?>" value="<?php echo e($v); ?>"><?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <button type="submit" class="text-green-600 hover:underline text-xs">فعال‌سازی مجدد</button>
                            </form>
                            <form action="<?php echo e(route('admin.licenses.destroy', ['platform_license' => $license])); ?>" method="post" class="inline" onsubmit="return confirm('رکورد این لایسنس برای همیشه حذف می‌شود. ادامه می‌دهید؟');">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('DELETE'); ?>
                                <?php $__currentLoopData = request()->only('status', 'site_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if($v !== null && $v !== ''): ?><input type="hidden" name="<?php echo e($k); ?>" value="<?php echo e($v); ?>"><?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <button type="submit" class="text-red-600 hover:underline text-xs">حذف دائمی</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" class="p-6 text-center text-gray-500">لایسنسی صادر نشده است.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if($licenses->hasPages()): ?>
    <div class="mt-4">
        <?php echo e($licenses->links()); ?>

    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/licenses/index.blade.php ENDPATH**/ ?>