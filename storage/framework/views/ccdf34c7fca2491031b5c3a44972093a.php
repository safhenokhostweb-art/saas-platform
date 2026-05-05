

<?php $__env->startSection('header', 'صفحه جدید'); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('admin.pages.form', [
        'action' => route('admin.pages.store'),
        'page' => new \App\Models\Page([
            'menu_position' => 'top',
            'menu_order' => 0,
            'is_active' => true,
            'icon_default' => 'document',
        ]),
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/pages/create.blade.php ENDPATH**/ ?>