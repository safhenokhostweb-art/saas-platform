

<?php $__env->startSection('header', 'درگاه‌های پرداخت بانکی'); ?>

<?php $__env->startSection('content'); ?>
<a href="<?php echo e(route('admin.payment-gateways.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن درگاه</a>
<?php if(session('success')): ?><div class="alert alert-success mt-2"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert alert-danger mt-2"><?php echo e(session('error')); ?></div><?php endif; ?>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نام</th>
                <th class="p-3 text-right">نوع</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">پیش‌فرض</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $gateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t">
                    <td class="p-3"><strong><?php echo e($gw->name); ?></strong></td>
                    <td class="p-3"><?php echo e(\App\Models\PaymentGateway::drivers()[$gw->driver] ?? $gw->driver); ?></td>
                    <td class="p-3"><?php echo e($gw->is_active ? 'فعال' : 'غیرفعال'); ?></td>
                    <td class="p-3"><?php echo e($gw->is_default ? 'بله' : '—'); ?></td>
                    <td class="p-3">
                        <a href="<?php echo e(route('admin.payment-gateways.edit', $gw)); ?>" class="text-indigo-600 hover:underline">ویرایش</a>
                        <?php if(!$gw->invoices()->exists()): ?>
                            <form method="POST" action="<?php echo e(route('admin.payment-gateways.destroy', $gw)); ?>" class="inline"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                <button type="submit" class="text-red-600" onclick="return confirm('حذف شود؟');">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">هنوز درگاهی تعریف نشده. از پنل یا .env استفاده می‌شود.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php echo $__env->make('admin.payment-gateways.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/payment-gateways/index.blade.php ENDPATH**/ ?>