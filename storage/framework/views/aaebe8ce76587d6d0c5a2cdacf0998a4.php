<?php $__env->startSection('content'); ?>
<h1 class="text-xl font-bold mb-4">
    مدیریت ماژول‌های سایت: <?php echo e($site->domain); ?>

</h1>
<p class="text-gray-600 mb-4">
    در اینجا فقط ماژول‌های <strong>رایگان (قابل اختصاص دستی)</strong> و <strong>فقط با لایسنس</strong> نمایش داده می‌شوند.
    ماژول‌های <strong>پولی</strong> فقط از طریق فروشگاه ماژول در پنل مشتری قابل خرید و فعال‌سازی هستند.
</p>

<form method="POST" action="<?php echo e(route('admin.sites.modules.update', $site)); ?>">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>

    <?php $__empty_1 = true; $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $siteModule = $site->modules->firstWhere('id', $module->id);
            $isActive = $siteModule && $siteModule->pivot->is_active;
            $hasLicense = $hasLicenseByModuleId[$module->id] ?? true;
            $canToggle = $module->isFreeAssignable() || ($module->isLicenseOnly() && $hasLicense);
        ?>
        <div class="border p-4 rounded mb-4">
            <label class="flex items-center gap-2">
                <input type="checkbox"
                       name="modules[<?php echo e($module->id); ?>]"
                       value="1"
                       <?php echo e($isActive ? 'checked' : ''); ?>

                       <?php echo e(!$canToggle ? 'disabled' : ''); ?>>
                <strong><?php echo e($module->title); ?></strong>
                <?php if($module->isLicenseOnly()): ?>
                    <span class="text-xs text-amber-600">(فقط با لایسنس — <?php echo e($hasLicense ? 'لایسنس دارد' : 'لایسنس صادر کنید'); ?>)</span>
                <?php else: ?>
                    <span class="text-xs text-gray-500">(رایگان)</span>
                <?php endif; ?>
            </label>

            <?php if($module->features->count()): ?>
                <div class="mt-2 mr-6 space-y-2">
                    <?php $__currentLoopData = $module->features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-2">
                            <input type="checkbox"
                                   name="features[<?php echo e($feature->id); ?>]"
                                   value="1"
                                   <?php echo e(in_array($feature->id, $enabledFeatureIds ?? [], true) ? 'checked' : ''); ?>>
                            <?php echo e($feature->title); ?>

                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p class="text-gray-500">هیچ ماژولی برای اختصاص دستی تعریف نشده است. ماژول‌های پولی در فروشگاه ماژول پنل مشتری نمایش داده می‌شوند.</p>
    <?php endif; ?>

    <?php if($modules->isNotEmpty()): ?>
        <button class="btn btn-primary">ذخیره تنظیمات</button>
    <?php endif; ?>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/sites/modules.blade.php ENDPATH**/ ?>