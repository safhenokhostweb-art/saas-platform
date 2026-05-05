

<?php $__env->startSection('header', 'افزودن منو'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl">
    <div class="mb-4">
        <a href="<?php echo e(route('admin.sites.menus.index', $site)); ?>" class="text-indigo-600 hover:underline">← بازگشت به منوها</a>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <form method="POST" action="<?php echo e(route('admin.sites.menus.store', $site)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label">نام منو</label>
                <input type="text" name="name" class="form-control" value="<?php echo e(old('name')); ?>" placeholder="مثلاً منوی اصلی" required>
                <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div class="mb-4">
                <label class="form-label">موقعیت</label>
                <select name="location" class="form-control">
                    <?php $__currentLoopData = \App\Models\Menu::locations(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($val); ?>" <?php echo e(old('location', 'header') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">ذخیره</button>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/menus/create.blade.php ENDPATH**/ ?>