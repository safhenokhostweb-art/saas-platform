<?php $__env->startSection('header','فیلدهای پروفایل'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.profile-fields.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<a href="<?php echo e(route('admin.profile-fields.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded">
    افزودن فیلد
</a>

<div class="mt-4 bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
        <tr>
            <th class="p-3 text-right">عنوان</th>
            <th class="p-3 text-right">نوع</th>
            <th class="p-3 text-right">عملیات</th>
        </tr>
        </thead>
        <tbody>
        <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="border-t">
                <td class="p-3"><?php echo e($field->label); ?></td>
                <td class="p-3"><?php echo e($field->type); ?></td>
                <td class="p-3">
                    <div class="flex items-center gap-2">
                        <a href="<?php echo e(route('admin.profile-fields.edit', $field)); ?>" class="text-indigo-600 hover:text-indigo-800">ویرایش</a>
                        <form action="<?php echo e(route('admin.profile-fields.destroy', $field)); ?>" method="POST" class="inline" onsubmit="return confirm('آیا از حذف این فیلد اطمینان دارید؟');">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600 hover:text-red-800">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/profile-fields/index.blade.php ENDPATH**/ ?>