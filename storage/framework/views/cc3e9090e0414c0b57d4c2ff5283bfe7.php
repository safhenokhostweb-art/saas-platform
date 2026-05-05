

<?php $__env->startSection('header', 'پلن‌های پشتیبانی'); ?>

<?php $__env->startSection('content'); ?>
<a href="<?php echo e(route('admin.support-plans.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">افزودن پلن</a>
<?php if(session('success')): ?><div class="alert alert-success mt-2"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">عنوان</th>
                <th class="p-3 text-right">قیمت (ریال)</th>
                <th class="p-3 text-right">مدت (روز)</th>
                <th class="p-3 text-right">تیکت</th>
                <th class="p-3 text-right">پیامک</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="border-t">
                <td class="p-3"><?php echo e($plan->title); ?></td>
                <td class="p-3"><?php echo e(number_format($plan->price)); ?></td>
                <td class="p-3"><?php echo e($plan->duration_days); ?></td>
                <td class="p-3"><?php echo e($plan->has_ticket ? 'بله' : 'خیر'); ?></td>
                <td class="p-3"><?php echo e($plan->has_sms ? 'بله' : 'خیر'); ?></td>
                <td class="p-3">
                    <a href="<?php echo e(route('admin.support-plans.edit', $plan)); ?>" class="text-indigo-600 hover:underline">ویرایش</a>
                    <form method="POST" action="<?php echo e(route('admin.support-plans.destroy', $plan)); ?>" class="inline"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button type="submit" class="text-red-600" onclick="return confirm('حذف شود؟');">حذف</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/support-plans/index.blade.php ENDPATH**/ ?>