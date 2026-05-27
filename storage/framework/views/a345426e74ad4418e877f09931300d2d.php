<?php $__env->startSection('header', \App\Helpers\ModuleLabels::getTitle('captcha') . ': ' . $site->title); ?>

<?php $__env->startSection('content'); ?>

<div class="max-w-2xl">
    <p class="mb-4">
        <a href="<?php echo e(route('admin.sites.edit', $site)); ?>" class="text-blue-600 hover:underline">بازگشت به ویرایش سایت</a>
        <span class="mx-2">|</span>
        <a href="<?php echo e(route('admin.captcha.index')); ?>" class="text-blue-600 hover:underline">تنظیمات پیش‌فرض کپچا</a>
    </p>

    <div class="bg-gray-50 rounded-xl p-4 mb-6 text-sm">
        <strong>پیش‌فرض پلتفرم:</strong> کپچا <?php echo e($platformEnabled ? 'فعال' : 'غیرفعال'); ?>، نوع: <?php echo e($platformType === 'persian_number' ? 'عدد فارسی' : ($platformType === 'normal' ? 'معمولی' : 'hCaptcha')); ?>

    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-4">تنظیمات کپچا برای این سایت</h2>
        <form method="POST" action="<?php echo e(route('admin.captcha.site.update', $site)); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="captcha_enabled" value="1" class="form-check-input" <?php echo e($setting->captcha_enabled ?? false ? 'checked' : ''); ?>>
                <span>فعال‌سازی کپچا برای فرم‌های این سایت</span>
            </label>
            <div>
                <label class="form-label">نوع کپچا</label>
                <select name="captcha_type" class="form-select w-full max-w-xs">
                    <option value="persian_number" <?php echo e(($setting->captcha_type ?? '') === 'persian_number' ? 'selected' : ''); ?>>کپچای عدد فارسی</option>
                    <option value="normal" <?php echo e(($setting->captcha_type ?? '') === 'normal' ? 'selected' : ''); ?>>کپچای معمولی (ریاضی فارسی)</option>
                    <option value="hcaptcha" <?php echo e(($setting->captcha_type ?? '') === 'hcaptcha' ? 'selected' : ''); ?>>hCaptcha</option>
                </select>
            </div>
            <div>
                <label class="form-label">کلید سایت hCaptcha</label>
                <input type="text" name="hcaptcha_site_key" class="form-control" value="<?php echo e(old('hcaptcha_site_key', $setting->hcaptcha_site_key ?? '')); ?>" placeholder="خالی = استفاده از پیش‌فرض پلتفرم">
            </div>
            <div>
                <label class="form-label">کلید مخفی hCaptcha</label>
                <input type="password" name="hcaptcha_secret_key" class="form-control" value="<?php echo e(old('hcaptcha_secret_key', $setting->hcaptcha_secret_key ?? '')); ?>" placeholder="خالی = استفاده از پیش‌فرض پلتفرم">
            </div>
            <button type="submit" class="btn btn-primary">ذخیره</button>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/captcha/site.blade.php ENDPATH**/ ?>