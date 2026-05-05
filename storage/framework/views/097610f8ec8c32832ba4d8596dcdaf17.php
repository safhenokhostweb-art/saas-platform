<?php $__env->startSection('header', \App\Helpers\ModuleLabels::getTitle('captcha')); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.captcha.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="max-w-2xl space-y-6">
    <?php if(!$moduleEnabled): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4">
            ماژول کپچا غیرفعال است. برای استفاده از کپچا، از منوی <a href="<?php echo e(route('admin.access.index')); ?>" class="underline">دسترسی و منو</a> ماژول «کپچا» را فعال کنید و سپس این صفحه را بروز کنید.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">تنظیمات پیش‌فرض کپچا (پلتفرم)</h2>
        <p class="text-gray-500 text-sm mb-4">این تنظیمات به‌صورت پیش‌فرض برای فرم‌های سایت‌های مشتری استفاده می‌شود. هر سایت می‌تواند در تنظیمات خودش کپچا را فعال/غیرفعال کند و کلید hCaptcha جداگانه داشته باشد.</p>

        <form method="POST" action="<?php echo e(route('admin.captcha.update')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="captcha_enabled" value="1" class="form-check-input" <?php echo e($captchaEnabled ? 'checked' : ''); ?>>
                <span>فعال‌سازی کپچا برای فرم‌های سایت‌ها (پیش‌فرض)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="auth_captcha_enabled" value="1" class="form-check-input" <?php echo e($authCaptchaEnabled ?? false ? 'checked' : ''); ?>>
                <span>فعال‌سازی کپچا برای فرم ورود و ثبت‌نام</span>
            </label>
            <div>
                <label class="form-label">نوع کپچا</label>
                <select name="captcha_type" class="form-select w-full max-w-xs">
                    <option value="persian_number" <?php echo e($captchaType === 'persian_number' ? 'selected' : ''); ?>>کپچای عدد فارسی</option>
                    <option value="normal" <?php echo e($captchaType === 'normal' ? 'selected' : ''); ?>>کپچای معمولی (ریاضی فارسی)</option>
                    <option value="hcaptcha" <?php echo e($captchaType === 'hcaptcha' ? 'selected' : ''); ?>>hCaptcha</option>
                </select>
            </div>
            <div>
                <label class="form-label">کلید سایت hCaptcha (اختیاری)</label>
                <input type="text" name="hcaptcha_site_key" class="form-control" value="<?php echo e(old('hcaptcha_site_key', $hcaptchaSiteKey)); ?>" placeholder="برای نوع hCaptcha وارد کنید">
            </div>
            <div>
                <label class="form-label">کلید مخفی hCaptcha (اختیاری)</label>
                <input type="password" name="hcaptcha_secret_key" class="form-control" value="<?php echo e(old('hcaptcha_secret_key', $hcaptchaSecretKey)); ?>" placeholder="برای نوع hCaptcha وارد کنید">
                <p class="text-gray-500 text-xs mt-1">برای تأیید پاسخ کاربر در سرور لازم است.</p>
            </div>
            <?php if($captchaType === 'hcaptcha'): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-sm text-blue-800">
                <strong>نکته برای hCaptcha:</strong> دامنهٔ سایت شما (مثلاً <code><?php echo e(request()->getHost()); ?></code>) باید در پنل hCaptcha در بخش «Passed Hostnames» یا «Domains» اضافه شود، وگرنه تأیید در سرور رد می‌شود. برای محیط لوکال (لاراگون با دامنه *.test) می‌توانید در فایل <code>.env</code> مقدار <code>AUTH_CAPTCHA_SKIP_IN_LOCAL=true</code> قرار دهید تا موقتاً تأیید رد نشود (فقط برای توسعه).
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">ذخیره</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-bold text-lg mb-2">تنظیمات کپچا به‌ازای هر سایت</h2>
        <p class="text-gray-500 text-sm mb-4">برای هر سایت مشتری می‌توانید از صفحه ویرایش سایت یا لینک زیر، کپچا را جداگانه فعال/غیرفعال و نوع و کلید hCaptcha را تنظیم کنید.</p>
        <ul class="list-disc list-inside space-y-1">
            <?php $__currentLoopData = \App\Models\Site::orderBy('title')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><a href="<?php echo e(route('admin.captcha.site', $s)); ?>" class="text-blue-600 hover:underline"><?php echo e($s->title); ?></a> (<?php echo e($s->domain); ?>)</li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/captcha/index.blade.php ENDPATH**/ ?>