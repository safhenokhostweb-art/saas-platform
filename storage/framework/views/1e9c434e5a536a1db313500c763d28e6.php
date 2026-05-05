<?php $__env->startSection('title','ورود'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-center px-4">
    <div class="card w-full max-w-md p-6">

        <h2 class="text-xl font-bold mb-6 text-center">
            ورود به حساب
        </h2>

        <?php if($errors->any()): ?>
            <div class="alert alert-danger text-sm">
                <?php echo e($errors->first()); ?>

                <?php if($errors->has('captcha') && !empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha'): ?>
                    <p class="mt-2 text-xs opacity-90">اگر روی دامنهٔ تست (مثلاً *.test) هستید: در پنل hCaptcha دامنه را اضافه کنید، یا در فایل <code>.env</code> پروژه مقدار <code>AUTH_CAPTCHA_SKIP_IN_LOCAL=true</code> بگذارید و کش را پاک کنید (فقط برای توسعه).</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('login')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>

            <div>
                <label class="form-label">موبایل</label>
                <input type="text"
                       name="mobile"
                       value="<?php echo e(old('mobile')); ?>"
                       placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹"
                       class="form-control"
                       required dir="rtl">
            </div>

            <div>
                <label class="form-label">رمز عبور</label>
                <input type="password"
                       name="password"
                       placeholder="رمز عبور خود را وارد کنید"
                       class="form-control"
                       required dir="rtl">
            </div>

            <?php echo $__env->make('partials.auth-captcha', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

            <button type="submit" class="btn btn-primary w-full">
                ورود
            </button>

            <p class="text-sm text-center mt-4">
                حساب نداری؟
                <a href="<?php echo e(route('register')); ?>" class="text-indigo-600 font-semibold hover:underline">
                    ثبت نام
                </a>
            </p>
        </form>
    </div>
</div>
<?php if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key'])): ?>
<?php $__env->startPush('scripts'); ?>
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/auth/login.blade.php ENDPATH**/ ?>