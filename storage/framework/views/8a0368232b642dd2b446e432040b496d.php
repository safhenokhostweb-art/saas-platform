

<?php $__env->startSection('header', 'نمایندگان (Reseller)'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.resellers.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<a href="<?php echo e(route('admin.resellers.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن نماینده</a>
<?php if(session('success')): ?><div class="alert alert-success mt-2"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">کاربر / شرکت</th>
                <th class="p-3 text-right">درصد کمیسیون</th>
                <th class="p-3 text-right">تعداد مشتریان</th>
                <th class="p-3 text-right">کمیسیون‌ها</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $resellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="border-t">
                    <td class="p-3">
                        <span class="font-medium"><?php echo e($r->company_name ?: ($r->user->mobile ?? '—')); ?></span>
                        <?php if($r->company_name && $r->user): ?>
                            <span class="block text-gray-500 text-xs"><?php echo e($r->user->mobile); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3"><?php echo e(number_format((float) $r->commission_rate, 1)); ?>٪</td>
                    <td class="p-3"><?php echo e($r->customers->count()); ?></td>
                    <td class="p-3">
                        <?php $pending = $r->commissions->where('status', 'pending')->sum('amount_irr'); ?>
                        <?php if($pending > 0): ?>
                            <span class="text-amber-600"><?php echo e(number_format($pending)); ?> ریال (در انتظار)</span>
                        <?php else: ?>
                            <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 space-x-2">
                        <a href="<?php echo e(route('admin.resellers.edit', $r)); ?>" class="text-indigo-600 hover:underline">ویرایش</a>
                        <form method="POST" action="<?php echo e(route('admin.resellers.destroy', $r)); ?>" class="inline">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600" onclick="return confirm('نماینده حذف شود؟ مشتریانش بدون نماینده می‌مانند.');">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php echo e($resellers->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/resellers/index.blade.php ENDPATH**/ ?>