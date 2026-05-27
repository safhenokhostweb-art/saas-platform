<?php $__env->startSection('header','مشتری‌ها'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.customers.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<a href="<?php echo e(route('admin.customers.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">
    افزودن مشتری
</a>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
        <tr>
            <th class="p-3 text-right">نام / شرکت</th>
            <th class="p-3 text-right">موبایل</th>
            <th class="p-3 text-right">ایمیل</th>
            <th class="p-3 text-right">تعداد سایت‌ها</th>
            <th class="p-3 text-right">عملیات</th>
        </tr>
        </thead>
        <tbody>
        <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="border-t">
                <td class="p-3">
                    <span class="font-medium"><?php echo e($customer->name); ?></span>
                    <?php if($customer->company_name): ?>
                        <span class="text-gray-500 block text-xs"><?php echo e($customer->company_name); ?></span>
                    <?php endif; ?>
                </td>
                <td class="p-3"><?php echo e($customer->mobile); ?></td>
                <td class="p-3"><?php echo e($customer->email ?? '—'); ?></td>
                <td class="p-3">
                    <?php $count = $customer->sites()->count(); ?>
                    <?php if($count > 0): ?>
                        <a href="<?php echo e(route('admin.sites.index')); ?>?customer_id=<?php echo e($customer->id); ?>" class="text-indigo-600 hover:underline"><?php echo e($count); ?> سایت</a>
                    <?php else: ?>
                        <span class="text-gray-400">۰</span>
                    <?php endif; ?>
                </td>
                <td class="p-3 space-x-2">
                    <a href="<?php echo e(route('admin.customers.edit',$customer)); ?>" class="text-indigo-600">ویرایش</a>
                    <form method="POST" action="<?php echo e(route('admin.customers.destroy',$customer)); ?>" class="inline">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button class="text-red-600" onclick="return confirm('مشتری و سایت‌هایش حذف شوند؟');">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php echo e($customers->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/customers/index.blade.php ENDPATH**/ ?>