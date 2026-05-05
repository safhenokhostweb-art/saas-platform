

<?php $__env->startSection('header', 'ثبت تیکت پشتیبانی'); ?>

<?php $__env->startSection('content'); ?>
<?php if($errors->any()): ?>
    <div class="alert alert-danger mb-4">
        <ul class="list-disc list-inside mb-0">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($e); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('admin.support-tickets.store')); ?>" class="card p-4 max-w-2xl">
    <?php echo csrf_field(); ?>
    <div class="mb-3">
        <label class="form-label">سایت</label>
        <select name="site_id" class="form-select" required>
            <option value="">— انتخاب کنید —</option>
            <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s->id); ?>" <?php echo e(old('site_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->title); ?> (<?php echo e($s->domain ?: '—'); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">ایمیل مشتری</label>
        <input type="email" name="customer_email" class="form-control" value="<?php echo e(old('customer_email')); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">نام مشتری (اختیاری)</label>
        <input type="text" name="customer_name" class="form-control" value="<?php echo e(old('customer_name')); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">موضوع</label>
        <input type="text" name="subject" class="form-control" value="<?php echo e(old('subject')); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">پیام</label>
        <textarea name="message" class="form-control" rows="5" required><?php echo e(old('message')); ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">اولویت</label>
        <select name="priority" class="form-select w-auto">
            <option value="low" <?php echo e(old('priority') === 'low' ? 'selected' : ''); ?>>کم</option>
            <option value="normal" <?php echo e(old('priority', 'normal') === 'normal' ? 'selected' : ''); ?>>عادی</option>
            <option value="high" <?php echo e(old('priority') === 'high' ? 'selected' : ''); ?>>بالا</option>
        </select>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">ثبت تیکت</button>
        <a href="<?php echo e(route('admin.support-tickets.index')); ?>" class="btn btn-secondary">انصراف</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/support-tickets/create.blade.php ENDPATH**/ ?>