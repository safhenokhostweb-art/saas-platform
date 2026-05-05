

<?php $__env->startSection('header', 'لایسنس‌های ماژول'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
    <div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div>
<?php endif; ?>
<?php if(session('error')): ?>
    <div class="alert alert-danger mb-4"><?php echo e(session('error')); ?></div>
<?php endif; ?>
<?php if(session('info')): ?>
    <div class="alert alert-info mb-4"><?php echo e(session('info')); ?></div>
<?php endif; ?>

<form method="get" class="mb-4 flex flex-wrap gap-2 items-end">
    <select name="site_id" class="form-control w-auto">
        <option value="">همهٔ سایت‌ها</option>
        <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($s->id); ?>" <?php echo e(request('site_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->title); ?> (<?php echo e($s->domain); ?>)</option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <select name="module_id" class="form-control w-auto">
        <option value="">همهٔ ماژول‌ها</option>
        <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($m->id); ?>" <?php echo e(request('module_id') == $m->id ? 'selected' : ''); ?>><?php echo e($m->title); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <select name="status" class="form-control w-auto">
        <option value="">همهٔ وضعیت‌ها</option>
        <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>فعال</option>
        <option value="expired" <?php echo e(request('status') === 'expired' ? 'selected' : ''); ?>>منقضی</option>
        <option value="revoked" <?php echo e(request('status') === 'revoked' ? 'selected' : ''); ?>>لغو شده</option>
    </select>
    <button type="submit" class="btn btn-secondary">فیلتر</button>
</form>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">ماژول</th>
                <th class="p-3 text-right">نوع</th>
                <th class="p-3 text-right">تاریخ خرید</th>
                <th class="p-3 text-right">انقضا</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $licenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $license): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3"><?php echo e($license->site->title ?? '—'); ?> <span class="text-gray-500">(<?php echo e($license->site->domain ?? '—'); ?>)</span></td>
                    <td class="p-3"><?php echo e($license->module->title ?? '—'); ?></td>
                    <td class="p-3"><?php echo e($license->license_type === 'subscription' ? 'اشتراک' : 'یک‌بار'); ?></td>
                    <td class="p-3"><?php echo e($license->purchased_at ? \App\Helpers\Jalali::formatFa($license->purchased_at, 'Y/m/d') : '—'); ?></td>
                    <td class="p-3"><?php echo e($license->expires_at ? \App\Helpers\Jalali::formatFa($license->expires_at, 'Y/m/d') : 'بدون انقضا'); ?></td>
                    <td class="p-3">
                        <?php if($license->isActive()): ?>
                            <span class="text-green-600">فعال</span>
                        <?php elseif($license->isRevoked()): ?>
                            <span class="text-red-600">لغو شده</span>
                        <?php else: ?>
                            <span class="text-gray-500">منقضی</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <?php if($license->isActive()): ?>
                            <form action="<?php echo e(route('admin.module-licenses.revoke', $license)); ?>" method="post" class="inline" onsubmit="return confirm('لغو این لایسنس؟');">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="text-red-600 hover:underline">لغو</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="7" class="p-6 text-center text-gray-500">لایسنس یافت نشد.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="p-3 border-t"><?php echo e($licenses->links()); ?></div>
</div>

<h3 class="text-lg font-bold mt-8 mb-2">اعطای لایسنس تبلیغاتی</h3>
<form method="post" action="<?php echo e(route('admin.module-licenses.grant')); ?>" class="max-w-xl space-y-2">
    <?php echo csrf_field(); ?>
    <div class="flex flex-wrap gap-2 items-center">
        <select name="site_id" class="form-control w-auto" required>
            <option value="">انتخاب سایت</option>
            <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s->id); ?>"><?php echo e($s->title); ?> (<?php echo e($s->domain); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <select name="module_id" class="form-control w-auto" required>
            <option value="">انتخاب ماژول</option>
            <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($m->id); ?>"><?php echo e($m->title); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <input type="text" name="expires_at" class="form-control w-40" placeholder="انقضا (شمسی Y/m/d)" dir="ltr">
        <button type="submit" class="btn btn-primary">اعطا</button>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/module-licenses/index.blade.php ENDPATH**/ ?>