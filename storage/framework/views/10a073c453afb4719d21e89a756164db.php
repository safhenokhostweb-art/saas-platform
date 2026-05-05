<?php $__env->startSection('header', 'اتصال فرم به سایت‌ها: ' . $form->title); ?>

<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <p class="mb-4">
        <a href="<?php echo e(route('admin.forms.preview', $form)); ?>" class="btn btn-primary inline-flex items-center gap-1" target="_blank">
            مشاهده فرم نهایی (پیش‌نمایش برای تست)
        </a>
        <a href="<?php echo e(route('admin.forms.edit', $form)); ?>" class="btn btn-secondary inline-flex items-center gap-1 mr-2">ویرایش فرم</a>
    </p>

    <?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('admin.forms.sites.update', $form)); ?>" class="card p-4">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="sites[]"
                       value="<?php echo e($site->id); ?>"
                       <?php echo e(in_array($site->id, $attached) ? 'checked' : ''); ?>>
                <label class="form-check-label">
                    <?php echo e($site->title); ?> (<?php echo e($site->domain); ?>)
                </label>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <button class="btn btn-primary w-100 mt-3">ذخیره اتصال‌ها</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/forms/sites.blade.php ENDPATH**/ ?>