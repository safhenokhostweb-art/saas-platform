<?php if (\Illuminate\Support\Facades\Blade::check('platform')): ?>


<?php $__env->startSection('header', 'اتصال فرم به سایت‌ها: ' . $form->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto">
    <div class="mb-4 flex flex-wrap gap-3">
        <a href="<?php echo e(route('admin.forms.preview', $form)); ?>" target="_blank"
           class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl inline-flex items-center gap-2 text-sm hover:opacity-90 transition">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            پیش‌نمایش فرم نهایی
        </a>
        <a href="<?php echo e(route('admin.forms.edit', $form)); ?>"
           class="border border-indigo-600 text-indigo-600 px-4 py-2 rounded-xl inline-flex items-center gap-2 text-sm hover:bg-indigo-50 transition">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
            </svg>
            ویرایش فرم
        </a>
    </div>

    <?php if(session('success')): ?>
        <div class="bg-emerald-100 text-emerald-700 p-4 rounded-xl mb-6"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <form method="POST" action="<?php echo e(route('admin.forms.sites.update', $form)); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="space-y-3">
                <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="sites[]" value="<?php echo e($site->id); ?>"
                               <?php echo e(in_array($site->id, $attached) ? 'checked' : ''); ?>

                               class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="font-medium"><?php echo e($site->title); ?></span>
                            <span class="text-sm text-gray-500 mr-2">(<?php echo e($site->domain); ?>)</span>
                        </div>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="mt-6 pt-4 border-t">
                <button type="submit"
                        class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl hover:opacity-90 transition w-full">
                    ذخیره اتصال‌ها
                </button>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php endif; ?>

<?php echo $__env->make(config('forms.layouts.admin'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\forms\src\Providers/../../resources/views/admin/forms/sites.blade.php ENDPATH**/ ?>