<?php $__env->startSection('header', 'ایجاد فرم جدید'); ?>

<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-xl shadow p-6 max-w-xl">

    <form method="POST" action="<?php echo e(route('admin.forms.store')); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>

        <div>
            <label class="block mb-1 font-medium">عنوان فرم</label>
            <input type="text" name="title"
                   class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-200">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" checked>
            <label>فعال باشد</label>
        </div>

        <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            ذخیره فرم
        </button>
    </form>
<?php if($errors->any()): ?>
    <div class="bg-red-100 text-red-700 p-3 rounded mb-3">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/forms/create.blade.php ENDPATH**/ ?>