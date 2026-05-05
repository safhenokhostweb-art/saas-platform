

<?php $__env->startSection('header', 'افزودن درگاه پرداخت'); ?>

<?php $__env->startSection('content'); ?>
<form method="POST" action="<?php echo e(route('admin.payment-gateways.store')); ?>" class="card p-4 max-w-2xl">
    <?php echo csrf_field(); ?>
    <?php echo $__env->make('admin.payment-gateways._form', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/payment-gateways/create.blade.php ENDPATH**/ ?>