

<?php $__env->startSection('header', \App\Helpers\ModuleLabels::getTitle('module_labels')); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.module-labels.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="max-w-4xl">
    <div class="bg-white rounded-2xl shadow p-6">
        <p class="text-gray-500 text-sm mb-6">
            در این صفحه می‌توانید عنوان و توضیح هر آیتم منو (ماژول) را به‌صورت سفارشی تغییر دهید. مثلاً «تنظیمات کپچا» را به «کد امنیتی» تغییر دهید. اگر فیلد را خالی بگذارید، عنوان پیش‌فرض نمایش داده می‌شود.
        </p>

        <form method="POST" action="<?php echo e(route('admin.module-labels.update')); ?>" class="space-y-6">
            <?php echo csrf_field(); ?>
            <?php if($siteId !== null): ?>
                <input type="hidden" name="site_id" value="<?php echo e($siteId); ?>">
            <?php endif; ?>

            <div class="space-y-4">
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/50">
                        <input type="hidden" name="labels[<?php echo e($index); ?>][key]" value="<?php echo e($item['key']); ?>">
                        <div class="mb-3">
                            <span class="text-xs font-mono text-gray-400"><?php echo e($item['key']); ?></span>
                            <span class="text-gray-500 text-sm mr-2">— پیش‌فرض:</span>
                            <span class="font-medium"><?php echo e($item['default_title']); ?></span>
                        </div>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="form-label">عنوان نمایشی</label>
                                <input type="text"
                                       name="labels[<?php echo e($index); ?>][custom_title]"
                                       class="form-control"
                                       value="<?php echo e(old('labels.'.$index.'.custom_title', $item['custom_title'])); ?>"
                                       placeholder="<?php echo e($item['default_title']); ?>">
                            </div>
                            <div>
                                <label class="form-label">توضیح (اختیاری)</label>
                                <textarea name="labels[<?php echo e($index); ?>][custom_description]"
                                          class="form-control"
                                          rows="2"
                                          placeholder="<?php echo e($item['default_description'] ?: 'توضیح کوتاه'); ?>"><?php echo e(old('labels.'.$index.'.custom_description', $item['custom_description'])); ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary">ذخیره عنوان و جزئیات ماژول‌ها</button>
                <a href="<?php echo e(route('admin.access.index')); ?>" class="btn btn-secondary mr-2">بازگشت به دسترسی و منو</a>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/module-labels/index.blade.php ENDPATH**/ ?>