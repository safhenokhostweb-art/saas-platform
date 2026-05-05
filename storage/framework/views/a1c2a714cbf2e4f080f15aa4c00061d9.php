<?php $__env->startSection('title','ثبت نام'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-center px-4">
    <div class="card w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4 text-center">ثبت نام</h2>

        <?php if($errors->any()): ?>
            <div class="alert alert-danger text-sm mb-4">
                <?php echo e($errors->first()); ?>

            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('register.submit')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>

            <div>
                <label class="form-label">موبایل</label>
                <input type="text" name="mobile" placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹" class="form-control" required dir="rtl">
            </div>
            <div>
                <label class="form-label">رمز عبور</label>
                <input type="password" name="password" placeholder="حداقل ۶ کاراکتر" class="form-control" required dir="rtl">
            </div>
            <div>
                <label class="form-label">تکرار رمز عبور</label>
                <input type="password" name="password_confirmation" placeholder="تکرار رمز عبور" class="form-control" required dir="rtl">
            </div>

            <?php echo $__env->make('partials.auth-captcha', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

            <button type="submit" class="btn btn-primary w-full">
                ثبت نام
            </button>
        </form>
    </div>
</div>
<?php if(!empty($captcha['enabled']) && ($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key'])): ?>
<?php $__env->startPush('scripts'); ?>
<script src="https://js.hcaptcha.com/1/api.js?hl=fa" async defer></script>
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/auth/register.blade.php ENDPATH**/ ?>