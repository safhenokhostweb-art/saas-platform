

<?php $__env->startSection('header', 'افزودن نماینده'); ?>

<?php $__env->startSection('content'); ?>
<?php if($users->isEmpty()): ?>
    <div class="alert alert-warning">هیچ کاربر فعالی برای تخصیص به نماینده وجود ندارد. همهٔ کاربران فعال قبلاً نماینده شده‌اند یا کاربری ثبت نشده است.</div>
    <a href="<?php echo e(route('admin.resellers.index')); ?>" class="btn btn-secondary">بازگشت</a>
<?php else: ?>
<form method="POST" action="<?php echo e(route('admin.resellers.store')); ?>" class="card p-4 max-w-2xl">
    <?php echo csrf_field(); ?>
    <?php echo $__env->make('admin.resellers._form', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <button type="submit" class="btn btn-primary">ذخیره</button>
</form>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/resellers/create.blade.php ENDPATH**/ ?>