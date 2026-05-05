

<?php $__env->startSection('header', 'ویرایش پلن پشتیبانی'); ?>

<?php $__env->startSection('content'); ?>
<form action="<?php echo e(route('admin.support-plans.update', $supportPlan)); ?>" method="post">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>
    <div class="card p-4 max-w-md">
        <div class="form-group mb-3"><label>عنوان</label><input type="text" name="title" class="form-control" value="<?php echo e(old('title', $supportPlan->title)); ?>" required></div>
        <div class="form-group mb-3"><label>قیمت (ریال)</label><input type="number" name="price" class="form-control" value="<?php echo e(old('price', $supportPlan->price)); ?>" min="0" required></div>
        <div class="form-group mb-3"><label>مدت (روز)</label><input type="number" name="duration_days" class="form-control" value="<?php echo e(old('duration_days', $supportPlan->duration_days)); ?>" min="1" required></div>
        <div class="form-group mb-3"><label><input type="checkbox" name="has_ticket" value="1" <?php echo e(old('has_ticket', $supportPlan->has_ticket) ? 'checked' : ''); ?>> تیکت پشتیبانی</label></div>
        <div class="form-group mb-3"><label><input type="checkbox" name="has_sms" value="1" <?php echo e(old('has_sms', $supportPlan->has_sms) ? 'checked' : ''); ?>> پیامک</label></div>
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <a href="<?php echo e(route('admin.support-plans.index')); ?>" class="btn btn-secondary">انصراف</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/support-plans/edit.blade.php ENDPATH**/ ?>