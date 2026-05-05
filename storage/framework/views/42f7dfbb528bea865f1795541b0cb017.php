

<?php $__env->startSection('header', 'افزودن محصول'); ?>

<?php $__env->startSection('content'); ?>
<?php $preselectedModuleId = $preselectedModuleId ?? null; ?>
<form method="POST" action="<?php echo e(route('admin.products.store')); ?>" class="card p-4 max-w-2xl">
    <?php echo csrf_field(); ?>
    <?php echo $__env->make('admin.products._form', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/products/create.blade.php ENDPATH**/ ?>