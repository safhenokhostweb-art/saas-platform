<?php $__env->startSection('header','افزودن مشتری'); ?>

<?php $__env->startSection('content'); ?>

<form method="POST" action="<?php echo e(route('admin.customers.store')); ?>" class="bg-white p-6 rounded shadow max-w-lg space-y-4">
    <?php echo csrf_field(); ?>
    <input name="name" placeholder="نام مشتری" class="w-full border rounded px-3 py-2">
    <input name="mobile" placeholder="شماره موبایل" class="w-full border rounded px-3 py-2">
    <button class="bg-indigo-600 text-white px-4 py-2 rounded">ذخیره</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/customers/create.blade.php ENDPATH**/ ?>