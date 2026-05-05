<?php if(!empty($captcha['enabled'] ?? false)): ?>
<div>
    <label class="form-label">کپچا</label>
    <p class="text-gray-500 text-xs mb-1">در صورت عدم نمایش کپچا یا خطای «منقضی شده»، صفحه را بروز (رفرش) کنید و از همان آدرس دامنهٔ ورود استفاده کنید.</p>
    <?php if(($captcha['type'] ?? '') === 'persian_number' && !empty($captcha['persian_code'])): ?>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-2xl font-bold tracking-widest text-gray-700 bg-gray-100 px-3 py-2 rounded select-none" aria-hidden="true"><?php echo e($captcha['persian_code']); ?></span>
            <input type="text" name="captcha_answer" class="form-control w-32" placeholder="عدد بالا را وارد کنید" maxlength="6" required autocomplete="off">
        </div>
    <?php elseif(($captcha['type'] ?? '') === 'normal' && !empty($captcha['normal_text'])): ?>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xl font-bold text-gray-700 bg-gray-100 px-3 py-2 rounded select-none" aria-hidden="true"><?php echo e($captcha['normal_text']); ?></span>
            <input type="text" name="captcha_answer" class="form-control w-24" placeholder="جواب" maxlength="4" required autocomplete="off">
        </div>
    <?php elseif(($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key'])): ?>
        <div class="h-captcha" data-sitekey="<?php echo e($captcha['hcaptcha_site_key']); ?>" data-hl="fa"></div>
    <?php endif; ?>
    <?php $__errorArgs = ['captcha'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <div class="text-danger small mt-1"><?php echo e($message); ?></div>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>
<?php endif; ?>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/partials/auth-captcha.blade.php ENDPATH**/ ?>