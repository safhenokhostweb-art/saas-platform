

<?php $__env->startSection('header', 'ایجاد تم جدید'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
    <div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<form action="<?php echo e(route('admin.theme-builder.store')); ?>" method="post" class="max-w-xl">
    <?php echo csrf_field(); ?>
    <div class="card">
        <div class="card-body space-y-4">
            <div>
                <label class="form-label">نام تم</label>
                <input type="text" name="name" class="form-control" value="<?php echo e(old('name')); ?>" required placeholder="مثلاً تم شرکتی">
            </div>
            <div>
                <label class="form-label">شناسه (slug) — اختیاری</label>
                <input type="text" name="slug" class="form-control" value="<?php echo e(old('slug')); ?>" placeholder="خالی = خودکار از نام">
            </div>
            <div>
                <label class="form-label">توضیح کوتاه</label>
                <input type="text" name="description" class="form-control" value="<?php echo e(old('description')); ?>" placeholder="اختیاری">
            </div>
            <div>
                <label class="form-label">وابسته به سایت — اختیاری (خالی = قالب پلتفرم)</label>
                <select name="site_id" class="form-select">
                    <option value="">قالب پلتفرم (برای همه)</option>
                    <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($s->id); ?>" <?php echo e(old('site_id', $siteId) == $s->id ? 'selected' : ''); ?>><?php echo e($s->title); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">ایجاد تم</button>
                <a href="<?php echo e(route('admin.theme-builder.index')); ?>" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </div>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/theme-builder/create.blade.php ENDPATH**/ ?>