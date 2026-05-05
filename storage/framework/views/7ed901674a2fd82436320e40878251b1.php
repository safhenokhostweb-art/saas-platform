

<?php $__env->startSection('header', 'تسویه کمیسیون نمایندگان'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.reseller-commissions.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php if(session('success')): ?><div class="alert alert-success mt-2"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert alert-danger mt-2"><?php echo e(session('error')); ?></div><?php endif; ?>

<div class="card p-4 mb-4">
    <strong>جمع کمیسیون در انتظار:</strong> <span class="text-amber-600 font-bold"><?php echo e(number_format($totalPending)); ?> ریال</span>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">نماینده</th>
                <th class="p-3 text-right">فاکتور</th>
                <th class="p-3 text-right">مبلغ (ریال)</th>
                <th class="p-3 text-right">تاریخ</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $pending; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t">
                    <td class="p-3"><?php echo e($c->reseller->company_name ?: $c->reseller->user->mobile ?? '—'); ?></td>
                    <td class="p-3">#<?php echo e($c->invoice_id); ?></td>
                    <td class="p-3 font-medium"><?php echo e(number_format($c->amount_irr)); ?></td>
                    <td class="p-3 text-gray-500"><?php echo e($c->created_at->format('Y/m/d')); ?></td>
                    <td class="p-3">
                        <form method="POST" action="<?php echo e(route('admin.reseller-commissions.mark-paid', $c)); ?>" class="inline">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <button type="submit" class="text-green-600 hover:underline">تسویه</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="p-4 text-center text-gray-500">کمیسیون در انتظاری وجود ندارد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php echo e($pending->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/reseller-commissions/index.blade.php ENDPATH**/ ?>