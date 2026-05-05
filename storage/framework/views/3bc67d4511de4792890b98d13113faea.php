

<?php $__env->startSection('header', 'ویرایش صفحه: ' . $page->title); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('admin.pages.form', [
        'action' => route('admin.pages.update', $page),
        'page' => $page,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/pages/edit.blade.php ENDPATH**/ ?>